<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\ViberConversation;
use App\Models\ViberIntegration;
use App\Models\ViberMessage;
use App\Services\LeadAutoCreateService;
use App\Services\TwilioCompanyService;
use App\Services\TwilioService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ViberController extends Controller
{
    public function __construct(
        protected TwilioCompanyService $twilioCompany,
        protected LeadAutoCreateService $leadAutoCreate
    ) {}

    public function index()
    {
        $user = Auth::user();
        $integration = $this->channelIntegrationForCompany($user?->company_id);
        $twilioReady = $user?->company
            ? (bool) $this->twilioCompany->getActiveIntegration($user->company)
            : false;

        return view('dashboard.viber', [
            'integrationConnected' => (bool) ($integration && $twilioReady),
            'botName' => $integration?->bot_name,
            'botUri' => null,
            'botShareUrl' => null,
        ]);
    }

    public function bootstrap(): JsonResponse
    {
        $user = Auth::user();
        $integration = ViberIntegration::where('company_id', $user->company_id)->first();
        $twilioReady = $user->company
            ? (bool) $this->twilioCompany->getActiveIntegration($user->company)
            : false;

        return response()->json([
            'connected' => (bool) ($integration && $integration->is_active && $twilioReady && $integration->sender_id),
            'bot' => $integration ? [
                'name' => $integration->bot_name,
                'sender_id' => $integration->sender_id,
                'uri' => null,
                'avatar' => null,
                'share_url' => null,
                'webhook_url' => $integration->webhookUrl(),
                'webhook_set_at' => $integration->webhook_set_at?->toIso8601String(),
                'twilio_connected' => $twilioReady,
                'integrations_url' => route('integrations'),
            ] : null,
        ]);
    }

    public function conversations(Request $request): JsonResponse
    {
        $user = Auth::user();
        $q = trim((string) $request->query('q', ''));

        $query = ViberConversation::query()
            ->where('company_id', $user->company_id)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', '%'.$q.'%')
                    ->orWhere('phone', 'like', '%'.$q.'%')
                    ->orWhere('viber_user_id', 'like', '%'.$q.'%')
                    ->orWhere('last_message_preview', 'like', '%'.$q.'%');
            });
        }

        $conversations = $query->limit(500)->get()->map(fn (ViberConversation $c) => $this->formatConversation($c));

        return response()->json(['data' => $conversations]);
    }

    public function messages(ViberConversation $conversation): JsonResponse
    {
        $this->assertCompanyConversation($conversation);

        $messages = ViberMessage::query()
            ->where('viber_conversation_id', $conversation->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(2000)
            ->get()
            ->map(fn (ViberMessage $m) => $this->formatMessage($m));

        $conversation->update(['unread_count' => 0]);

        return response()->json([
            'conversation' => $this->formatConversation($conversation->fresh()),
            'data' => $messages,
        ]);
    }

    public function sendMessage(Request $request, ViberConversation $conversation): JsonResponse
    {
        $this->assertCompanyConversation($conversation);

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:text,picture,video,file,url,location,contact'],
            'text' => ['nullable', 'string', 'max:7000'],
            'media_url' => ['nullable', 'url', 'max:2048'],
            'file_name' => ['nullable', 'string', 'max:256'],
            'file_size' => ['nullable', 'integer', 'min:1'],
            'duration' => ['nullable', 'integer', 'min:1'],
            'thumbnail_url' => ['nullable', 'url', 'max:2048'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:64'],
        ]);

        $channel = $this->requireActiveIntegration();
        $twilio = $this->twilioClientForCompany(Auth::user()->company);
        $to = $conversation->phone ?: $conversation->viber_user_id;

        $body = null;
        $mediaUrl = null;
        $type = $validated['type'];

        if ($type === 'text') {
            $body = (string) ($validated['text'] ?? '');
            if ($body === '') {
                return response()->json(['message' => 'Message text is required.'], 422);
            }
        } elseif ($type === 'location') {
            $lat = $validated['latitude'] ?? null;
            $lng = $validated['longitude'] ?? null;
            if ($lat === null || $lng === null) {
                return response()->json(['message' => 'Latitude and longitude are required.'], 422);
            }
            $body = trim(($validated['text'] ?? '').' Location: '.$lat.', '.$lng);
        } elseif ($type === 'contact') {
            $body = trim(($validated['contact_name'] ?? 'Contact').' '.($validated['contact_phone'] ?? ''));
            if ($body === '') {
                return response()->json(['message' => 'Contact details are required.'], 422);
            }
        } elseif ($type === 'url') {
            $mediaUrl = $validated['media_url'] ?? null;
            $body = $validated['text'] ?? $mediaUrl;
            if (! $mediaUrl && ! $body) {
                return response()->json(['message' => 'A URL is required.'], 422);
            }
        } else {
            $mediaUrl = $validated['media_url'] ?? null;
            if (! $mediaUrl) {
                return response()->json(['message' => 'A media URL is required.'], 422);
            }
            $body = $validated['text'] ?? null;
        }

        try {
            $sent = $twilio->sendViber(
                (string) $channel->sender_id,
                (string) $to,
                $body,
                $channel->statusCallbackUrl(),
                $mediaUrl
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $message = ViberMessage::create([
            'company_id' => $conversation->company_id,
            'viber_conversation_id' => $conversation->id,
            'user_id' => Auth::id(),
            'direction' => 'outbound',
            'message_token' => $sent->sid,
            'type' => $type,
            'text' => $validated['text'] ?? ($type === 'location' || $type === 'contact' ? $body : null),
            'media_url' => $mediaUrl,
            'thumbnail_url' => $validated['thumbnail_url'] ?? null,
            'file_name' => $validated['file_name'] ?? null,
            'file_size' => $validated['file_size'] ?? null,
            'duration' => $validated['duration'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'contact_name' => $validated['contact_name'] ?? null,
            'contact_phone' => $validated['contact_phone'] ?? null,
            'status' => $sent->status ?? 'sent',
            'raw_payload' => ['sid' => $sent->sid, 'status' => $sent->status],
            'sent_at' => now(),
        ]);

        $this->touchConversation($conversation, $message);

        $this->leadAutoCreate->fromPhoneChannel(
            (int) $conversation->company_id,
            'viber',
            $conversation->phone ?: $conversation->viber_user_id,
            $conversation->name
        );

        return response()->json(['data' => $this->formatMessage($message)], 201);
    }

    public function uploadMedia(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:51200'],
            'kind' => ['nullable', 'string', 'in:picture,video,file'],
        ]);

        $file = $validated['file'];
        $kind = $validated['kind'] ?? 'file';
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');

        if ($kind === 'picture' && ! in_array($ext, ['jpg', 'jpeg', 'png', 'gif'], true)) {
            return response()->json(['message' => 'Pictures must be JPEG, PNG, or GIF.'], 422);
        }
        if ($kind === 'video' && $ext !== 'mp4') {
            return response()->json(['message' => 'Videos must be MP4 (H.264).'], 422);
        }

        $safeName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'media';
        $path = $file->storeAs(
            'viber/'.Auth::user()->company_id.'/'.date('Y/m'),
            $safeName.'-'.Str::random(8).'.'.$ext,
            'public'
        );

        return response()->json([
            'data' => [
                'url' => public_media_url($path),
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'mime' => $file->getMimeType(),
                'extension' => $ext,
                'kind' => $kind,
            ],
        ], 201);
    }

    public function callLink(ViberConversation $conversation): JsonResponse
    {
        $this->assertCompanyConversation($conversation);

        $phone = preg_replace('/\D+/', '', (string) ($conversation->phone ?: $conversation->viber_user_id));

        return response()->json([
            'data' => [
                'open_chat' => $phone ? 'viber://chat?number='.$phone : null,
                'call' => $phone ? 'viber://chat?number='.$phone : null,
                'tel' => $phone ? 'tel:+'.$phone : null,
                'has_phone' => (bool) $phone,
            ],
        ]);
    }

    public function webhook(Request $request, string $webhookKey): Response
    {
        $integration = ViberIntegration::where('webhook_key', $webhookKey)
            ->where('is_active', true)
            ->first();

        if (! $integration) {
            return response('Not found', 404);
        }

        $company = Company::find($integration->company_id);
        if (! $company) {
            return response('OK', 200);
        }

        $twilioIntegration = $this->twilioCompany->getActiveIntegration($company);
        if ($twilioIntegration) {
            $credentials = $this->twilioCompany->getCredentials($twilioIntegration);
            $signature = (string) $request->header('X-Twilio-Signature', '');
            if ($credentials && $signature !== '') {
                $twilio = new TwilioService($credentials['sid'], $credentials['token']);
                if (! $twilio->validateRequest($signature, $request->fullUrl(), $request->post())) {
                    Log::warning('Viber Twilio webhook signature mismatch', [
                        'company_id' => $integration->company_id,
                    ]);

                    return response('Invalid signature', 403);
                }
            }

            $accountSid = $request->input('AccountSid');
            if ($accountSid && $accountSid !== $twilioIntegration->account_sid) {
                Log::warning('Viber webhook AccountSid mismatch', [
                    'company_id' => $integration->company_id,
                ]);

                return response('OK', 200);
            }
        }

        try {
            $this->handleInboundTwilioMessage($integration, $request);
        } catch (\Throwable $e) {
            Log::error('Viber webhook handler error', ['error' => $e->getMessage()]);
        }

        if (! $integration->webhook_set_at) {
            $integration->webhook_set_at = now();
            $integration->save();
        }

        return response('OK', 200);
    }

    protected function handleInboundTwilioMessage(ViberIntegration $integration, Request $request): void
    {
        $messageSid = $request->input('MessageSid');
        if (! $messageSid) {
            return;
        }

        if (ViberMessage::where('message_token', $messageSid)->exists()) {
            return;
        }

        $from = $this->twilioCompany->normalizePhone((string) $request->input('From', ''));
        $profileName = $request->input('ProfileName');
        $body = $request->input('Body');
        $numMedia = (int) $request->input('NumMedia', 0);

        $conversation = $this->upsertConversation(
            $integration->company_id,
            $from,
            $profileName ? (string) $profileName : null
        );

        $isNewConversation = $conversation->wasRecentlyCreated
            || ! ViberMessage::where('viber_conversation_id', $conversation->id)->exists();

        $type = 'text';
        $mediaUrl = null;
        $mimeType = null;
        $fileName = null;
        $text = is_string($body) ? $body : null;

        if ($numMedia > 0) {
            $mimeType = $request->input('MediaContentType0');
            $remoteMedia = $request->input('MediaUrl0');
            $type = $this->guessMediaType($mimeType);
            $fileName = $request->input('MediaFileName0');

            if ($remoteMedia) {
                try {
                    $mediaUrl = $this->storeInboundMedia(
                        $integration,
                        (string) $remoteMedia,
                        $mimeType ? (string) $mimeType : null,
                        $fileName ? (string) $fileName : null,
                        (string) $messageSid
                    );
                } catch (\Throwable $e) {
                    Log::warning('Viber inbound media download failed', ['error' => $e->getMessage()]);
                    $mediaUrl = (string) $remoteMedia;
                }
            }
        }

        $record = ViberMessage::create([
            'company_id' => $integration->company_id,
            'viber_conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'message_token' => (string) $messageSid,
            'type' => $type,
            'text' => $text,
            'media_url' => $mediaUrl,
            'file_name' => $fileName,
            'status' => $request->input('SmsStatus', 'received'),
            'raw_payload' => $request->except(['MediaUrl0', 'MediaUrl1']),
            'sent_at' => now(),
        ]);

        $conversation->unread_count = (int) $conversation->unread_count + 1;
        $this->touchConversation($conversation, $record);

        if ($isNewConversation) {
            $this->maybeSendWelcome($integration, $conversation);
        }
    }

    protected function maybeSendWelcome(ViberIntegration $integration, ViberConversation $conversation): void
    {
        $welcome = trim((string) ($integration->welcome_message ?? ''));
        if ($welcome === '') {
            return;
        }

        $company = Company::find($integration->company_id);
        if (! $company) {
            return;
        }

        try {
            $twilio = $this->twilioClientForCompany($company);
            $to = $conversation->phone ?: $conversation->viber_user_id;
            $sent = $twilio->sendViber(
                (string) $integration->sender_id,
                (string) $to,
                $welcome,
                $integration->statusCallbackUrl()
            );

            $message = ViberMessage::create([
                'company_id' => $integration->company_id,
                'viber_conversation_id' => $conversation->id,
                'direction' => 'outbound',
                'message_token' => $sent->sid,
                'type' => 'text',
                'text' => $welcome,
                'status' => $sent->status ?? 'sent',
                'raw_payload' => ['sid' => $sent->sid, 'status' => $sent->status],
                'sent_at' => now(),
            ]);

            $this->touchConversation($conversation, $message);
        } catch (\Throwable $e) {
            Log::warning('Viber welcome message failed', ['error' => $e->getMessage()]);
        }
    }

    protected function storeInboundMedia(
        ViberIntegration $integration,
        string $remoteUrl,
        ?string $mimeType,
        ?string $fileName,
        string $messageSid
    ): string {
        $company = Company::find($integration->company_id);
        $twilio = $this->twilioClientForCompany($company);
        $binary = $twilio->downloadMedia($remoteUrl);

        $ext = 'bin';
        if ($fileName && str_contains($fileName, '.')) {
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) ?: 'bin';
        } elseif ($mimeType) {
            $ext = match (true) {
                str_contains($mimeType, 'jpeg') => 'jpg',
                str_contains($mimeType, 'png') => 'png',
                str_contains($mimeType, 'webp') => 'webp',
                str_contains($mimeType, 'mp4') => 'mp4',
                str_contains($mimeType, 'ogg') => 'ogg',
                str_contains($mimeType, 'pdf') => 'pdf',
                default => 'bin',
            };
        }

        $path = 'viber/'.$integration->company_id.'/inbound/'.date('Y/m').'/'.$messageSid.'-'.Str::random(6).'.'.$ext;
        Storage::disk('public')->put($path, $binary);

        return public_media_url($path);
    }

    protected function guessMediaType(?string $mimeType): string
    {
        $mime = strtolower((string) $mimeType);

        return match (true) {
            str_starts_with($mime, 'image/') => 'picture',
            str_starts_with($mime, 'video/') => 'video',
            default => 'file',
        };
    }

    protected function upsertConversation(int $companyId, string $peerPhone, ?string $profileName): ViberConversation
    {
        $normalized = $this->twilioCompany->normalizePhone($peerPhone);

        $conversation = ViberConversation::firstOrNew([
            'company_id' => $companyId,
            'viber_user_id' => $normalized,
        ]);

        $conversation->fill([
            'name' => $profileName ?: ($conversation->name ?: $normalized),
            'phone' => preg_replace('/\D+/', '', $normalized) ?: $conversation->phone,
            'is_subscribed' => true,
        ]);
        $conversation->save();

        $this->leadAutoCreate->fromPhoneChannel($companyId, 'viber', $normalized, $profileName ?: $conversation->name);

        return $conversation;
    }

    protected function touchConversation(ViberConversation $conversation, ViberMessage $message): void
    {
        $preview = match ($message->type) {
            'text' => (string) $message->text,
            'picture' => '[Image]',
            'video' => '[Video]',
            'file' => '[File] '.($message->file_name ?: ''),
            'url' => '[Link] '.($message->media_url ?: ''),
            'location' => '[Location]',
            'contact' => '[Contact] '.($message->contact_name ?: ''),
            'sticker' => '[Sticker]',
            default => '['.ucfirst($message->type).']',
        };

        $conversation->last_message_preview = Str::limit(trim($preview), 480);
        $conversation->last_message_at = $message->sent_at ?: now();
        $conversation->save();
    }

    protected function twilioClientForCompany(?Company $company): TwilioService
    {
        if (! $company) {
            throw new HttpResponseException(
                response()->json(['message' => 'Twilio is not connected. Configure it under Integrations.'], 422)
            );
        }

        $integration = $this->twilioCompany->getActiveIntegration($company);
        if (! $integration) {
            throw new HttpResponseException(
                response()->json(['message' => 'Twilio is not connected. Configure it under Integrations.'], 422)
            );
        }

        $credentials = $this->twilioCompany->getCredentials($integration);
        if (! $credentials) {
            throw new HttpResponseException(
                response()->json(['message' => 'Invalid Twilio credentials.'], 422)
            );
        }

        return new TwilioService($credentials['sid'], $credentials['token']);
    }

    protected function channelIntegrationForCompany(?int $companyId): ?ViberIntegration
    {
        if (! $companyId) {
            return null;
        }

        return ViberIntegration::where('company_id', $companyId)->where('is_active', true)->first();
    }

    protected function requireActiveIntegration(): ViberIntegration
    {
        $integration = $this->channelIntegrationForCompany(Auth::user()->company_id);

        if (! $integration || ! $integration->sender_id) {
            throw new HttpResponseException(
                response()->json(['message' => 'Viber is not connected. Configure it under Integrations.'], 422)
            );
        }

        return $integration;
    }

    protected function assertCompanyConversation(ViberConversation $conversation): void
    {
        if ((int) $conversation->company_id !== (int) Auth::user()->company_id) {
            abort(404);
        }
    }

    protected function formatConversation(ViberConversation $c): array
    {
        return [
            'id' => $c->id,
            'viber_user_id' => $c->viber_user_id,
            'name' => $c->name ?: 'Viber User',
            'avatar' => $c->avatar,
            'phone' => $c->phone,
            'language' => $c->language,
            'country' => $c->country,
            'is_subscribed' => (bool) $c->is_subscribed,
            'unread_count' => (int) $c->unread_count,
            'last_message_preview' => $c->last_message_preview,
            'last_message_at' => $c->last_message_at?->toIso8601String(),
        ];
    }

    protected function formatMessage(ViberMessage $m): array
    {
        return [
            'id' => $m->id,
            'direction' => $m->direction,
            'type' => $m->type,
            'text' => $m->text,
            'media_url' => $m->media_url,
            'thumbnail_url' => $m->thumbnail_url,
            'file_name' => $m->file_name,
            'file_size' => $m->file_size,
            'duration' => $m->duration,
            'latitude' => $m->latitude,
            'longitude' => $m->longitude,
            'contact_name' => $m->contact_name,
            'contact_phone' => $m->contact_phone,
            'sticker_id' => $m->sticker_id,
            'status' => $m->status,
            'user_id' => $m->user_id,
            'sent_at' => $m->sent_at?->toIso8601String(),
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }
}
