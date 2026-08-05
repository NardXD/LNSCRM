<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\ViberConversation;
use App\Models\ViberIntegration;
use App\Models\ViberMessage;
use App\Services\InfobipCompanyService;
use App\Services\InfobipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ViberController extends Controller
{
    public function __construct(protected InfobipCompanyService $infobipCompany) {}

    public function index()
    {
        $user = Auth::user();
        $integration = $this->channelIntegrationForCompany($user?->company_id);
        $infobipReady = $user?->company
            ? (bool) $this->infobipCompany->getActiveIntegration($user->company)
            : false;

        return view('dashboard.viber', [
            'integrationConnected' => (bool) ($integration && $infobipReady),
            'botName' => $integration?->bot_name,
            'botUri' => null,
            'botShareUrl' => null,
        ]);
    }

    public function bootstrap(): JsonResponse
    {
        $user = Auth::user();
        $integration = ViberIntegration::where('company_id', $user->company_id)->first();
        $infobipReady = $user->company
            ? (bool) $this->infobipCompany->getActiveIntegration($user->company)
            : false;

        return response()->json([
            'connected' => (bool) ($integration && $integration->is_active && $infobipReady && $integration->sender_id),
            'bot' => $integration ? [
                'name' => $integration->bot_name,
                'sender_id' => $integration->sender_id,
                'uri' => null,
                'avatar' => null,
                'share_url' => null,
                'webhook_url' => $integration->webhookUrl(),
                'webhook_set_at' => $integration->webhook_set_at?->toIso8601String(),
                'infobip_connected' => $infobipReady,
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
        $infobip = $this->infobipClientForCompany(Auth::user()->company);
        $to = $conversation->phone ?: $conversation->viber_user_id;
        $notifyUrl = $channel->statusCallbackUrl();
        $type = $validated['type'];
        $mediaUrl = null;
        $body = null;

        try {
            if ($type === 'text') {
                $body = (string) ($validated['text'] ?? '');
                if ($body === '') {
                    return response()->json(['message' => 'Message text is required.'], 422);
                }
                $sent = $infobip->sendViberText(
                    (string) $channel->sender_id,
                    (string) $to,
                    $body,
                    $notifyUrl
                );
            } elseif ($type === 'location') {
                $lat = $validated['latitude'] ?? null;
                $lng = $validated['longitude'] ?? null;
                if ($lat === null || $lng === null) {
                    return response()->json(['message' => 'Latitude and longitude are required.'], 422);
                }
                $body = trim(($validated['text'] ?? '').' Location: '.$lat.', '.$lng);
                $sent = $infobip->sendViberText(
                    (string) $channel->sender_id,
                    (string) $to,
                    $body,
                    $notifyUrl
                );
            } elseif ($type === 'contact') {
                $body = trim(($validated['contact_name'] ?? 'Contact').' '.($validated['contact_phone'] ?? ''));
                if ($body === '') {
                    return response()->json(['message' => 'Contact details are required.'], 422);
                }
                $sent = $infobip->sendViberText(
                    (string) $channel->sender_id,
                    (string) $to,
                    $body,
                    $notifyUrl
                );
            } elseif ($type === 'url') {
                $mediaUrl = $validated['media_url'] ?? null;
                $body = $validated['text'] ?? $mediaUrl;
                if (! $mediaUrl && ! $body) {
                    return response()->json(['message' => 'A URL is required.'], 422);
                }
                $sent = $infobip->sendViberText(
                    (string) $channel->sender_id,
                    (string) $to,
                    (string) $body,
                    $notifyUrl
                );
            } else {
                $mediaUrl = $validated['media_url'] ?? null;
                if (! $mediaUrl) {
                    return response()->json(['message' => 'A media URL is required.'], 422);
                }
                $sent = $infobip->sendViberMedia(
                    (string) $channel->sender_id,
                    (string) $to,
                    $type,
                    $mediaUrl,
                    $validated['text'] ?? null,
                    $validated['file_name'] ?? null,
                    $notifyUrl
                );
            }
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $message = ViberMessage::create([
            'company_id' => $conversation->company_id,
            'viber_conversation_id' => $conversation->id,
            'user_id' => Auth::id(),
            'direction' => 'outbound',
            'message_token' => $sent['messageId'] ?? null,
            'type' => $type,
            'text' => $validated['text'] ?? ($type === 'location' || $type === 'contact' || $type === 'url' ? $body : null),
            'media_url' => $mediaUrl,
            'thumbnail_url' => $validated['thumbnail_url'] ?? null,
            'file_name' => $validated['file_name'] ?? null,
            'file_size' => $validated['file_size'] ?? null,
            'duration' => $validated['duration'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'contact_name' => $validated['contact_name'] ?? null,
            'contact_phone' => $validated['contact_phone'] ?? null,
            'status' => $sent['status'] ?? 'sent',
            'raw_payload' => $sent['raw'] ?? $sent,
            'sent_at' => now(),
        ]);

        $this->touchConversation($conversation, $message);

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
                'url' => asset('storage/'.$path),
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

        $infobipIntegration = $this->infobipCompany->getActiveIntegration($company);
        if ($infobipIntegration && ! $this->validateOptionalWebhookSecret($request, $infobipIntegration)) {
            Log::warning('Viber Infobip webhook secret mismatch', [
                'company_id' => $integration->company_id,
            ]);

            return response('Invalid signature', 403);
        }

        try {
            $this->handleInboundMessage($integration, $request);
        } catch (\Throwable $e) {
            Log::error('Viber webhook handler error', ['error' => $e->getMessage()]);
        }

        if (! $integration->webhook_set_at) {
            $integration->webhook_set_at = now();
            $integration->save();
        }

        return response('OK', 200);
    }

    protected function handleInboundMessage(ViberIntegration $integration, Request $request): void
    {
        $results = $request->input('results');
        if (is_array($results) && $results !== []) {
            foreach ($results as $result) {
                if (is_array($result)) {
                    $this->processInfobipInboundResult($integration, $result);
                }
            }

            return;
        }

        if ($request->input('MessageSid') || $request->input('SmsMessageSid')) {
            $this->handleInboundTwilioShapedMessage($integration, $request);
        }
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function processInfobipInboundResult(ViberIntegration $integration, array $result): void
    {
        $messageId = (string) ($result['messageId'] ?? $result['message_id'] ?? '');
        if ($messageId === '') {
            return;
        }

        if (ViberMessage::where('message_token', $messageId)->exists()) {
            return;
        }

        $from = $this->infobipCompany->normalizePhone((string) ($result['from'] ?? ''));
        if ($from === '' || $from === '+') {
            return;
        }

        $contact = is_array($result['contact'] ?? null) ? $result['contact'] : [];
        $profileName = $contact['name'] ?? $result['senderName'] ?? $result['profileName'] ?? null;

        $message = is_array($result['message'] ?? null)
            ? $result['message']
            : (is_array($result['content'] ?? null) ? $result['content'] : []);

        $typeRaw = strtoupper((string) ($message['type'] ?? $result['type'] ?? 'TEXT'));
        $text = null;
        $mediaUrl = null;
        $fileName = null;
        $latitude = null;
        $longitude = null;
        $type = 'text';

        if (in_array($typeRaw, ['TEXT', 'BUTTON', 'REPLY'], true)) {
            $type = 'text';
            $text = $message['text'] ?? $message['caption'] ?? null;
            $text = is_string($text) ? $text : null;
        } elseif (in_array($typeRaw, ['IMAGE', 'PICTURE', 'VIDEO', 'FILE', 'DOCUMENT', 'AUDIO'], true)) {
            $type = match ($typeRaw) {
                'IMAGE', 'PICTURE' => 'picture',
                'VIDEO' => 'video',
                default => 'file',
            };
            $remoteMedia = $message['url'] ?? $message['mediaUrl'] ?? $message['contentUri'] ?? null;
            $text = isset($message['caption']) && is_string($message['caption'])
                ? $message['caption']
                : (isset($message['text']) && is_string($message['text']) ? $message['text'] : null);
            $fileName = isset($message['filename']) && is_string($message['filename'])
                ? $message['filename']
                : (isset($message['fileName']) && is_string($message['fileName']) ? $message['fileName'] : null);

            if ($remoteMedia) {
                try {
                    $mediaUrl = $this->storeInboundMedia(
                        $integration,
                        (string) $remoteMedia,
                        null,
                        $fileName,
                        $messageId
                    );
                } catch (\Throwable $e) {
                    Log::warning('Viber inbound media download failed', ['error' => $e->getMessage()]);
                    $mediaUrl = (string) $remoteMedia;
                }
            }
        } elseif ($typeRaw === 'LOCATION') {
            $type = 'location';
            $latitude = $message['latitude'] ?? $result['latitude'] ?? null;
            $longitude = $message['longitude'] ?? $result['longitude'] ?? null;
            $text = isset($message['name']) && is_string($message['name']) ? $message['name'] : null;
        } elseif ($typeRaw === 'URL') {
            $type = 'url';
            $mediaUrl = $message['url'] ?? $message['mediaUrl'] ?? null;
            $text = is_string($message['text'] ?? null) ? $message['text'] : $mediaUrl;
        } else {
            $type = 'text';
            $text = is_string($message['text'] ?? null) ? $message['text'] : json_encode($message ?: $result);
        }

        $conversation = $this->upsertConversation(
            $integration->company_id,
            $from,
            $profileName ? (string) $profileName : null
        );

        $isNewConversation = $conversation->wasRecentlyCreated
            || ! ViberMessage::where('viber_conversation_id', $conversation->id)->exists();

        $record = ViberMessage::create([
            'company_id' => $integration->company_id,
            'viber_conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'message_token' => $messageId,
            'type' => $type,
            'text' => $text,
            'media_url' => $mediaUrl,
            'file_name' => $fileName,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'status' => 'received',
            'raw_payload' => $result,
            'sent_at' => now(),
        ]);

        $conversation->unread_count = (int) $conversation->unread_count + 1;
        $this->touchConversation($conversation, $record);

        if ($isNewConversation) {
            $this->maybeSendWelcome($integration, $conversation);
        }
    }

    protected function handleInboundTwilioShapedMessage(ViberIntegration $integration, Request $request): void
    {
        $messageSid = $request->input('MessageSid') ?: $request->input('SmsMessageSid');
        if (! $messageSid) {
            return;
        }

        if (ViberMessage::where('message_token', $messageSid)->exists()) {
            return;
        }

        $from = $this->infobipCompany->normalizePhone((string) $request->input('From', ''));
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
            $infobip = $this->infobipClientForCompany($company);
            $to = $conversation->phone ?: $conversation->viber_user_id;
            $sent = $infobip->sendViberText(
                (string) $integration->sender_id,
                (string) $to,
                $welcome,
                $integration->statusCallbackUrl()
            );

            $message = ViberMessage::create([
                'company_id' => $integration->company_id,
                'viber_conversation_id' => $conversation->id,
                'direction' => 'outbound',
                'message_token' => $sent['messageId'] ?? null,
                'type' => 'text',
                'text' => $welcome,
                'status' => $sent['status'] ?? 'sent',
                'raw_payload' => $sent['raw'] ?? $sent,
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
        $infobip = $this->infobipClientForCompany($company);
        $binary = $infobip->downloadMedia($remoteUrl);

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

        return asset('storage/'.$path);
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
        $normalized = $this->infobipCompany->normalizePhone($peerPhone);

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

    protected function validateOptionalWebhookSecret(Request $request, $infobipIntegration): bool
    {
        $provided = (string) (
            $request->header('X-Infobip-Secret')
            ?: $request->header('Authorization')
            ?: ''
        );

        if (preg_match('/^(App|Bearer)\s+(.+)$/i', $provided, $matches)) {
            $provided = $matches[2];
        }

        return $this->infobipCompany->validateWebhookSecret($provided, $infobipIntegration);
    }

    protected function infobipClientForCompany(?Company $company): InfobipService
    {
        if (! $company) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json(['message' => 'Infobip is not connected. Configure it under Integrations.'], 422)
            );
        }

        $service = $this->infobipCompany->getServiceForCompany($company);
        if (! $service) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json(['message' => 'Infobip is not connected. Configure it under Integrations.'], 422)
            );
        }

        return $service;
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
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
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
