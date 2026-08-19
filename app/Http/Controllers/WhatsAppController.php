<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppIntegration;
use App\Models\WhatsAppMessage;
use App\Notifications\WhatsAppMessageNotification;
use App\Services\FlexCrmLookupService;
use App\Services\LeadAutoCreateService;
use App\Services\TwilioCompanyService;
use App\Services\TwilioService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class WhatsAppController extends Controller
{
    public function __construct(
        protected TwilioCompanyService $twilioCompany,
        protected LeadAutoCreateService $leadAutoCreate,
        protected FlexCrmLookupService $crmLookup
    ) {}

    public function index()
    {
        $user = Auth::user();
        $integration = $this->channelIntegrationForCompany($user?->company_id);
        $twilioReady = $user?->company
            ? (bool) $this->twilioCompany->getActiveIntegration($user->company)
            : false;

        return view('dashboard.whatsapp', [
            'integrationConnected' => (bool) ($integration && $twilioReady),
            'businessName' => $integration?->business_name,
            'displayPhone' => $integration?->display_phone_number ?: $integration?->from_number,
        ]);
    }

    public function bootstrap(): JsonResponse
    {
        $user = Auth::user();
        $integration = WhatsAppIntegration::where('company_id', $user->company_id)->first();
        $twilioReady = $user->company
            ? (bool) $this->twilioCompany->getActiveIntegration($user->company)
            : false;

        return response()->json([
            'connected' => (bool) ($integration && $integration->is_active && $twilioReady && $integration->from_number),
            'account' => $integration ? [
                'business_name' => $integration->business_name,
                'display_phone_number' => $integration->display_phone_number ?: $integration->from_number,
                'from_number' => $integration->from_number,
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

        $query = WhatsAppConversation::query()
            ->where('company_id', $user->company_id)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', '%'.$q.'%')
                    ->orWhere('profile_name', 'like', '%'.$q.'%')
                    ->orWhere('phone', 'like', '%'.$q.'%')
                    ->orWhere('wa_id', 'like', '%'.$q.'%')
                    ->orWhere('last_message_preview', 'like', '%'.$q.'%');
            });
        }

        $conversations = $query->limit(500)->get()->map(fn (WhatsAppConversation $c) => $this->formatConversation($c));

        return response()->json(['data' => $conversations]);
    }

    public function messages(WhatsAppConversation $conversation): JsonResponse
    {
        $this->assertCompanyConversation($conversation);

        $messages = WhatsAppMessage::query()
            ->where('whatsapp_conversation_id', $conversation->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(2000)
            ->get()
            ->map(fn (WhatsAppMessage $m) => $this->formatMessage($m));

        $conversation->update(['unread_count' => 0]);
        $this->markConversationNotificationsRead($conversation);

        return response()->json([
            'conversation' => $this->formatConversation($conversation->fresh()),
            'data' => $messages,
        ]);
    }

    public function sendMessage(Request $request, WhatsAppConversation $conversation): JsonResponse
    {
        $this->assertCompanyConversation($conversation);

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:text,image,video,document,audio,location'],
            'text' => ['nullable', 'string', 'max:4096'],
            'media_url' => ['nullable', 'url', 'max:2048'],
            'file_name' => ['nullable', 'string', 'max:256'],
            'file_size' => ['nullable', 'integer', 'min:1'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ]);

        $channel = $this->requireActiveIntegration();
        $twilio = $this->twilioClientForCompany(Auth::user()->company);
        $to = $conversation->wa_id ?: $conversation->phone;

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
        } else {
            $mediaUrl = $validated['media_url'] ?? null;
            if (! $mediaUrl) {
                return response()->json(['message' => 'A media URL is required.'], 422);
            }
            $body = $validated['text'] ?? null;
        }

        try {
            $sent = $twilio->sendWhatsApp(
                (string) $channel->from_number,
                (string) $to,
                $body,
                $channel->statusCallbackUrl(),
                $mediaUrl
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $message = WhatsAppMessage::create([
            'company_id' => $conversation->company_id,
            'whatsapp_conversation_id' => $conversation->id,
            'user_id' => Auth::id(),
            'direction' => 'outbound',
            'wamid' => $sent->sid,
            'type' => $type,
            'text' => $validated['text'] ?? ($type === 'location' ? $body : null),
            'media_url' => $mediaUrl,
            'file_name' => $validated['file_name'] ?? null,
            'file_size' => $validated['file_size'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'status' => $sent->status ?? 'sent',
            'raw_payload' => ['sid' => $sent->sid, 'status' => $sent->status],
            'sent_at' => now(),
        ]);

        $this->touchConversation($conversation, $message);

        $this->leadAutoCreate->fromPhoneChannel(
            (int) $conversation->company_id,
            'whatsapp',
            $conversation->wa_id ?: $conversation->phone,
            $conversation->name
        );

        return response()->json(['data' => $this->formatMessage($message)], 201);
    }

    public function uploadMedia(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:65536'],
            'kind' => ['nullable', 'string', 'in:image,video,document,audio'],
        ]);

        $file = $validated['file'];
        $kind = $validated['kind'] ?? 'document';
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');

        if ($kind === 'image' && ! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return response()->json(['message' => 'Images must be JPEG, PNG, or WebP.'], 422);
        }
        if ($kind === 'video' && ! in_array($ext, ['mp4', '3gp'], true)) {
            return response()->json(['message' => 'Videos must be MP4 or 3GP.'], 422);
        }

        $safeName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'media';
        $path = $file->storeAs(
            'whatsapp/'.Auth::user()->company_id.'/'.date('Y/m'),
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

    public function callLink(WhatsAppConversation $conversation): JsonResponse
    {
        $this->assertCompanyConversation($conversation);

        $phone = preg_replace('/\D+/', '', (string) ($conversation->phone ?: $conversation->wa_id));

        return response()->json([
            'data' => [
                'open_chat' => $phone ? 'https://wa.me/'.$phone : null,
                'call' => $phone ? 'https://wa.me/'.$phone : null,
                'tel' => $phone ? 'tel:+'.$phone : null,
                'has_phone' => (bool) $phone,
            ],
        ]);
    }

    public function webhook(Request $request, string $webhookKey): Response
    {
        $integration = WhatsAppIntegration::where('webhook_key', $webhookKey)
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
                    Log::warning('WhatsApp Twilio webhook signature mismatch', [
                        'company_id' => $integration->company_id,
                    ]);

                    return response('Invalid signature', 403);
                }
            }

            $accountSid = $request->input('AccountSid');
            if ($accountSid && $accountSid !== $twilioIntegration->account_sid) {
                Log::warning('WhatsApp webhook AccountSid mismatch', [
                    'company_id' => $integration->company_id,
                ]);

                return response('OK', 200);
            }
        }

        try {
            $this->handleInboundTwilioMessage($integration, $request);
        } catch (\Throwable $e) {
            Log::error('WhatsApp webhook handler error', ['error' => $e->getMessage()]);
        }

        if (! $integration->webhook_set_at) {
            $integration->webhook_set_at = now();
            $integration->save();
        }

        return response('OK', 200);
    }

    protected function handleInboundTwilioMessage(WhatsAppIntegration $integration, Request $request): void
    {
        $messageSid = $request->input('MessageSid');
        if (! $messageSid) {
            return;
        }

        if (WhatsAppMessage::where('wamid', $messageSid)->exists()) {
            return;
        }

        $from = $this->twilioCompany->normalizePhone((string) $request->input('From', ''));
        $profileName = $request->input('ProfileName');
        $body = $request->input('Body');
        $numMedia = (int) $request->input('NumMedia', 0);

        $conversation = $this->upsertConversation($integration->company_id, $from, $profileName ? (string) $profileName : null);
        $isNewConversation = $conversation->wasRecentlyCreated
            || ! WhatsAppMessage::where('whatsapp_conversation_id', $conversation->id)->exists();

        $conversation->window_expires_at = now()->addHours(24);
        $conversation->is_subscribed = true;

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
                    Log::warning('WhatsApp inbound media download failed', ['error' => $e->getMessage()]);
                    $mediaUrl = (string) $remoteMedia;
                }
            }
        }

        $record = WhatsAppMessage::create([
            'company_id' => $integration->company_id,
            'whatsapp_conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'wamid' => (string) $messageSid,
            'type' => $type,
            'text' => $text,
            'media_url' => $mediaUrl,
            'mime_type' => $mimeType,
            'file_name' => $fileName,
            'status' => $request->input('SmsStatus', 'received'),
            'raw_payload' => $request->except(['MediaUrl0', 'MediaUrl1']),
            'sent_at' => now(),
        ]);

        $conversation->unread_count = (int) $conversation->unread_count + 1;
        $this->touchConversation($conversation, $record);
        $this->notifyUnread($conversation, $record);

        if ($isNewConversation) {
            $this->maybeSendWelcome($integration, $conversation);
        }
    }

    protected function notifyUnread(WhatsAppConversation $conversation, WhatsAppMessage $message): void
    {
        $recipients = $this->whatsappNotifyRecipients((int) $conversation->company_id);

        foreach ($recipients as $recipient) {
            try {
                // Keep one unread notification per conversation per user
                $existing = $recipient->unreadNotifications()
                    ->where('type', WhatsAppMessageNotification::class)
                    ->get()
                    ->first(function ($notification) use ($conversation) {
                        return (int) ($notification->data['conversation_id'] ?? 0) === (int) $conversation->id;
                    });

                if ($existing) {
                    $existing->delete();
                }

                $recipient->notify(new WhatsAppMessageNotification($conversation, $message));
            } catch (\Throwable $e) {
                Log::warning('Failed to notify WhatsApp unread', [
                    'conversation_id' => $conversation->id,
                    'user_id' => $recipient->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @return Collection<int, User>
     */
    protected function whatsappNotifyRecipients(int $companyId)
    {
        return User::query()
            ->where('company_id', $companyId)
            ->where(function ($query) {
                $query->whereHas('role.permissions', function ($q) {
                    $q->where('slug', 'view_whatsapp');
                })->orWhereHas('roles.permissions', function ($q) {
                    $q->where('slug', 'view_whatsapp');
                });
            })
            ->get();
    }

    protected function markConversationNotificationsRead(WhatsAppConversation $conversation): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $user->unreadNotifications()
            ->where('type', WhatsAppMessageNotification::class)
            ->get()
            ->each(function ($notification) use ($conversation) {
                if ((int) ($notification->data['conversation_id'] ?? 0) === (int) $conversation->id) {
                    $notification->markAsRead();
                }
            });
    }

    protected function maybeSendWelcome(WhatsAppIntegration $integration, WhatsAppConversation $conversation): void
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
            $sent = $twilio->sendWhatsApp(
                (string) $integration->from_number,
                (string) $conversation->wa_id,
                $welcome,
                $integration->statusCallbackUrl()
            );

            $message = WhatsAppMessage::create([
                'company_id' => $integration->company_id,
                'whatsapp_conversation_id' => $conversation->id,
                'direction' => 'outbound',
                'wamid' => $sent->sid,
                'type' => 'text',
                'text' => $welcome,
                'status' => $sent->status ?? 'sent',
                'raw_payload' => ['sid' => $sent->sid, 'status' => $sent->status],
                'sent_at' => now(),
            ]);

            $this->touchConversation($conversation, $message);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp welcome message failed', ['error' => $e->getMessage()]);
        }
    }

    protected function storeInboundMedia(
        WhatsAppIntegration $integration,
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

        $path = 'whatsapp/'.$integration->company_id.'/inbound/'.date('Y/m').'/'.$messageSid.'-'.Str::random(6).'.'.$ext;
        Storage::disk('public')->put($path, $binary);

        return public_media_url($path);
    }

    protected function guessMediaType(?string $mimeType): string
    {
        $mime = strtolower((string) $mimeType);

        return match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'audio/') => 'audio',
            default => 'document',
        };
    }

    protected function upsertConversation(int $companyId, string $waId, ?string $profileName): WhatsAppConversation
    {
        $normalized = $this->twilioCompany->normalizePhone($waId);

        $conversation = WhatsAppConversation::firstOrNew([
            'company_id' => $companyId,
            'wa_id' => $normalized,
        ]);

        $conversation->fill([
            'name' => $profileName ?: ($conversation->name ?: $normalized),
            'profile_name' => $profileName ?: $conversation->profile_name,
            'phone' => $conversation->phone ?: preg_replace('/\D+/', '', $normalized),
            'is_subscribed' => true,
        ]);
        $conversation->save();

        $this->leadAutoCreate->fromPhoneChannel($companyId, 'whatsapp', $normalized, $profileName ?: $conversation->name);

        return $conversation;
    }

    protected function touchConversation(WhatsAppConversation $conversation, WhatsAppMessage $message): void
    {
        $preview = match ($message->type) {
            'text' => (string) $message->text,
            'image', 'sticker' => '[Image]',
            'video' => '[Video]',
            'audio' => '[Audio]',
            'document' => '[File] '.($message->file_name ?: ''),
            'location' => '[Location]',
            'contact' => '[Contact] '.($message->contact_name ?: ''),
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

    protected function channelIntegrationForCompany(?int $companyId): ?WhatsAppIntegration
    {
        if (! $companyId) {
            return null;
        }

        return WhatsAppIntegration::where('company_id', $companyId)->where('is_active', true)->first();
    }

    protected function requireActiveIntegration(): WhatsAppIntegration
    {
        $integration = $this->channelIntegrationForCompany(Auth::user()->company_id);

        if (! $integration || ! $integration->from_number) {
            throw new HttpResponseException(
                response()->json(['message' => 'WhatsApp is not connected. Configure it under Integrations.'], 422)
            );
        }

        return $integration;
    }

    protected function assertCompanyConversation(WhatsAppConversation $conversation): void
    {
        if ((int) $conversation->company_id !== (int) Auth::user()->company_id) {
            abort(404);
        }
    }

    protected function formatConversation(WhatsAppConversation $c): array
    {
        return [
            'id' => $c->id,
            'wa_id' => $c->wa_id,
            'name' => $c->name ?: ($c->profile_name ?: 'WhatsApp User'),
            'profile_name' => $c->profile_name,
            'phone' => $c->phone,
            'is_subscribed' => (bool) $c->is_subscribed,
            'unread_count' => (int) $c->unread_count,
            'last_message_preview' => $c->last_message_preview,
            'last_message_at' => $c->last_message_at?->toIso8601String(),
            'window_expires_at' => $c->window_expires_at?->toIso8601String(),
            'within_window' => $c->isWithinMessagingWindow(),
            'lead' => $this->crmLookup->matchAssignedLead(
                $this->crmLookup->assignedLeadIndex((int) $c->company_id),
                $c->phone ?: $c->wa_id,
                null,
                $c->name ?: $c->profile_name
            ),
        ];
    }

    protected function formatMessage(WhatsAppMessage $m): array
    {
        return [
            'id' => $m->id,
            'direction' => $m->direction,
            'type' => $m->type,
            'text' => $m->text,
            'media_url' => $m->media_url,
            'file_name' => $m->file_name,
            'file_size' => $m->file_size,
            'mime_type' => $m->mime_type,
            'latitude' => $m->latitude,
            'longitude' => $m->longitude,
            'contact_name' => $m->contact_name,
            'contact_phone' => $m->contact_phone,
            'status' => $m->status,
            'user_id' => $m->user_id,
            'sent_at' => $m->sent_at?->toIso8601String(),
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }
}
