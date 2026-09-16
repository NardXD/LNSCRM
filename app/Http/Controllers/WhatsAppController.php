<?php

namespace App\Http\Controllers;

use App\Models\LeadLabel;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppIntegration;
use App\Models\WhatsAppMessage;
use App\Notifications\WhatsAppMessageNotification;
use App\Services\FlexCrmLookupService;
use App\Services\LeadAutoCreateService;
use App\Services\LeadRuleEngine;
use App\Services\MessageContactExtractor;
use App\Services\TwilioCompanyService;
use App\Services\WhatsAppCloudApiService;
use App\Services\WhatsAppMessageSyncService;
use Illuminate\Database\Eloquent\Builder;
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
        protected FlexCrmLookupService $crmLookup,
        protected WhatsAppMessageSyncService $whatsappSync,
        protected WhatsAppCloudApiService $cloud,
        protected MessageContactExtractor $messageContacts
    ) {}

    public function index()
    {
        $user = Auth::user();
        $integration = $this->channelIntegrationForCompany($user?->company_id);

        return view('dashboard.whatsapp', [
            'integrationConnected' => (bool) ($integration && $integration->isCloudConnected()),
            'businessName' => $integration?->business_name,
            'displayPhone' => $integration?->display_phone_number ?: $integration?->from_number,
        ]);
    }

    public function bootstrap(): JsonResponse
    {
        $user = Auth::user();
        $integration = WhatsAppIntegration::where('company_id', $user->company_id)->first();

        return response()->json([
            'connected' => (bool) ($integration && $integration->isCloudConnected()),
            'account' => $integration ? [
                'business_name' => $integration->business_name,
                'display_phone_number' => $integration->display_phone_number ?: $integration->from_number,
                'from_number' => $integration->from_number,
                'phone_number_id' => $integration->phone_number_id,
                'webhook_url' => $integration->webhookUrl(),
                'webhook_verify_token' => $integration->webhook_verify_token,
                'webhook_set_at' => $integration->webhook_set_at?->toIso8601String(),
                'has_access_token' => (bool) $integration->getDecryptedAccessToken(),
                'integrations_url' => route('integrations'),
            ] : null,
            'templates' => MessageTemplate::listForCompany($user->company_id, MessageTemplate::CHANNEL_WHATSAPP),
            'permissions' => [
                'create_templates' => $user->hasPermission('create_message_templates'),
            ],
        ]);
    }

    public function conversations(Request $request): JsonResponse
    {
        $user = Auth::user();
        $q = trim((string) $request->query('q', ''));
        $readFilter = trim((string) $request->query('read', ''));
        $limit = min(max((int) $request->query('limit', 40), 1), 100);
        $beforeId = (int) $request->query('before_id', 0);

        $query = WhatsAppConversation::query()
            ->where('company_id', $user->company_id)
            ->with('leadLabels')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        if ($readFilter === 'unread') {
            $query->where('unread_count', '>', 0);
        } elseif ($readFilter === 'read') {
            $query->where('unread_count', '<=', 0);
        }

        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', '%'.$q.'%')
                    ->orWhere('profile_name', 'like', '%'.$q.'%')
                    ->orWhere('phone', 'like', '%'.$q.'%')
                    ->orWhere('wa_id', 'like', '%'.$q.'%')
                    ->orWhere('last_message_preview', 'like', '%'.$q.'%');
            });
        }

        if ($beforeId > 0) {
            $before = WhatsAppConversation::query()
                ->where('company_id', $user->company_id)
                ->whereKey($beforeId)
                ->first();

            if ($before) {
                $this->constrainConversationsBefore($query, $before);
            }
        }

        $rows = $query->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        if ($hasMore) {
            $rows = $rows->take($limit);
        }

        return response()->json([
            'data' => $rows->map(fn (WhatsAppConversation $c) => $this->formatConversation($c))->values(),
            'has_more' => $hasMore,
        ]);
    }

    public function messages(Request $request, WhatsAppConversation $conversation): JsonResponse
    {
        $this->assertCompanyConversation($conversation);

        $limit = min(max((int) $request->query('limit', 40), 1), 100);
        $beforeId = (int) $request->query('before_id', 0);
        $isPoll = $request->boolean('poll');

        $query = WhatsAppMessage::query()
            ->where('whatsapp_conversation_id', $conversation->id);

        if ($beforeId > 0) {
            $before = WhatsAppMessage::query()
                ->where('whatsapp_conversation_id', $conversation->id)
                ->whereKey($beforeId)
                ->first();

            if ($before) {
                $this->constrainMessagesBefore($query, $before);
            }
        }

        $messages = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();

        $hasMore = $messages->count() > $limit;
        if ($hasMore) {
            $messages = $messages->take($limit);
        }

        $messages = $messages->reverse()->values()->map(fn (WhatsAppMessage $m) => $this->formatMessage($m));

        $extracted = ['phones' => [], 'emails' => [], 'names' => []];
        if ($beforeId <= 0) {
            if (! $isPoll) {
                $conversation->update(['unread_count' => 0]);
                $this->markConversationNotificationsRead($conversation);
            }
            $extracted = $this->messageContacts->applyToWhatsAppConversation($conversation);
        }

        $payload = $this->formatConversation($conversation->fresh());
        if ($beforeId <= 0 && ! ($payload['lead'] ?? null) && ($extracted['emails'][0] ?? null)) {
            $index = $this->crmLookup->assignedLeadIndex((int) $conversation->company_id);
            $payload['lead'] = $this->crmLookup->matchAssignedLead($index, null, $extracted['emails'][0]);
            if (! $payload['lead'] && ($extracted['names'][0] ?? null)) {
                $payload['lead'] = $this->crmLookup->matchAssignedLead($index, null, null, $extracted['names'][0]);
            }
        }

        return response()->json([
            'conversation' => array_merge($payload, [
                'extracted_phones' => $extracted['phones'],
                'extracted_emails' => $extracted['emails'],
                'extracted_names' => $extracted['names'],
                'extracted_name' => $extracted['names'][0] ?? null,
            ]),
            'data' => $messages,
            'has_more' => $hasMore,
        ]);
    }

    public function updateRead(Request $request, WhatsAppConversation $conversation): JsonResponse
    {
        $this->assertCompanyConversation($conversation);
        $validated = $request->validate([
            'is_read' => ['required', 'boolean'],
        ]);

        $conversation->update(['unread_count' => $validated['is_read'] ? 0 : 1]);

        return response()->json([
            'conversation' => $this->formatConversation($conversation->fresh()),
        ]);
    }

    /**
     * Attach an existing or newly-named label directly to a conversation,
     * independent of any matched lead — lets users tag a WhatsApp thread
     * before it is saved as a lead.
     */
    public function attachLabel(Request $request, WhatsAppConversation $conversation): JsonResponse
    {
        $this->assertCompanyConversation($conversation);
        $validated = $request->validate([
            'label_id' => ['nullable', 'integer', 'exists:lead_labels,id'],
            'name' => ['nullable', 'required_without:label_id', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $companyId = (int) $conversation->company_id;
        $label = null;
        if (! empty($validated['label_id'])) {
            $label = LeadLabel::query()
                ->where('company_id', $companyId)
                ->whereKey($validated['label_id'])
                ->first();
        }

        $name = trim((string) ($validated['name'] ?? ''));
        if (! $label && $name !== '') {
            $label = LeadLabel::query()
                ->where('company_id', $companyId)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->first();
            if (! $label) {
                $label = LeadLabel::create([
                    'company_id' => $companyId,
                    'name' => $name,
                    'color' => $validated['color'] ?? '#4338ca',
                ]);
            }
        }

        if (! $label) {
            return response()->json(['message' => 'Choose or type a label.'], 422);
        }

        $conversation->leadLabels()->syncWithoutDetaching([$label->id]);

        return response()->json([
            'data' => ['id' => $label->id, 'name' => $label->name, 'color' => $label->color],
            'labels' => $this->serializeLabels($conversation->fresh()),
        ], 201);
    }

    public function detachLabel(Request $request, WhatsAppConversation $conversation, LeadLabel $leadLabel): JsonResponse
    {
        $this->assertCompanyConversation($conversation);
        if ((int) $leadLabel->company_id !== (int) $conversation->company_id) {
            abort(404);
        }

        $conversation->leadLabels()->detach($leadLabel->id);

        return response()->json([
            'labels' => $this->serializeLabels($conversation->fresh()),
        ]);
    }

    protected function serializeLabels(WhatsAppConversation $c): array
    {
        return $c->loadMissing('leadLabels')->leadLabels
            ->map(fn (LeadLabel $label) => ['id' => $label->id, 'name' => $label->name, 'color' => $label->color])
            ->values()
            ->all();
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
        $token = $channel->getDecryptedAccessToken();
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
            $body = trim((string) ($validated['text'] ?? ''));
        } else {
            $mediaUrl = $validated['media_url'] ?? null;
            if (! $mediaUrl) {
                return response()->json(['message' => 'A media URL is required.'], 422);
            }
            $body = $validated['text'] ?? null;
        }

        try {
            $sent = $this->cloud->send(
                (string) $channel->phone_number_id,
                (string) $token,
                (string) $to,
                $type,
                $type === 'text' ? $body : ($validated['text'] ?? null),
                $mediaUrl,
                $validated['file_name'] ?? null,
                isset($validated['latitude']) ? (float) $validated['latitude'] : null,
                isset($validated['longitude']) ? (float) $validated['longitude'] : null
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => WhatsAppCloudApiService::sanitizeGraphError($e->getMessage())], 422);
        }

        $message = WhatsAppMessage::create([
            'company_id' => $conversation->company_id,
            'whatsapp_conversation_id' => $conversation->id,
            'user_id' => Auth::id(),
            'direction' => 'outbound',
            'wamid' => $sent['wamid'],
            'type' => $type,
            'text' => $validated['text'] ?? ($type === 'location' ? $body : null),
            'media_url' => $mediaUrl,
            'file_name' => $validated['file_name'] ?? null,
            'file_size' => $validated['file_size'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'status' => $sent['status'] ?? 'sent',
            'raw_payload' => $sent['raw'],
            'sent_at' => now(),
        ]);

        $this->touchConversation($conversation, $message);

        $lead = $this->leadAutoCreate->fromPhoneChannel(
            (int) $conversation->company_id,
            'whatsapp',
            $conversation->wa_id ?: $conversation->phone,
            $conversation->name
        );
        $isNew = WhatsAppMessage::query()->where('whatsapp_conversation_id', $conversation->id)->count() <= 1;
        $this->leadAutoCreate->applyRules($lead, 'whatsapp', LeadRuleEngine::outboundTriggers($isNew), [
            'contact_name' => $conversation->name,
            'phone' => $conversation->wa_id ?: $conversation->phone,
            'message' => (string) ($validated['text'] ?? $body ?? ''),
        ]);

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

    public function sync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'limit' => ['nullable', 'integer', 'min:10', 'max:2000'],
            'recent' => ['nullable', 'boolean'],
            'minutes' => ['nullable', 'integer', 'min:5', 'max:180'],
        ]);

        $integration = $this->requireActiveIntegration();

        if (! empty($validated['recent'])) {
            $minutes = (int) ($validated['minutes'] ?? 90);
            $imported = $this->whatsappSync->ingestRecent($integration, $minutes, 150);

            return response()->json([
                'data' => [
                    'imported' => $imported,
                    'mode' => 'webhook',
                ],
            ]);
        }

        $days = (int) ($validated['days'] ?? 30);
        $limit = (int) ($validated['limit'] ?? 500);

        try {
            $result = $this->whatsappSync->sync($integration, $days, $limit);
        } catch (\Throwable $e) {
            return response()->json(['message' => WhatsAppCloudApiService::sanitizeGraphError($e->getMessage())], 422);
        }

        return response()->json([
            'data' => array_merge($result, ['days' => $days]),
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

        if ($request->isMethod('get')) {
            return $this->verifyMetaWebhook($request, $integration);
        }

        if (! $this->metaSignatureIsValid($request, $integration)) {
            Log::error('WhatsApp webhook signature invalid; event dropped', [
                'integration_id' => $integration->id,
                'company_id' => $integration->company_id,
                'has_app_secret' => (bool) $integration->getDecryptedAppSecret(),
            ]);

            return response('Invalid signature', 403);
        }

        try {
            $this->handleMetaWebhook($integration, $request);
        } catch (\Throwable $e) {
            Log::error('WhatsApp webhook handler error', [
                'error' => WhatsAppCloudApiService::sanitizeGraphError($e->getMessage()),
            ]);
        }

        if (! $integration->webhook_set_at) {
            $integration->webhook_set_at = now();
            $integration->save();
        }

        return response('EVENT_RECEIVED', 200);
    }

    protected function verifyMetaWebhook(Request $request, WhatsAppIntegration $integration): Response
    {
        $mode = (string) ($request->query('hub.mode') ?? $request->input('hub_mode', ''));
        $token = (string) ($request->query('hub.verify_token') ?? $request->input('hub_verify_token', ''));
        $challenge = (string) ($request->query('hub.challenge') ?? $request->input('hub_challenge', ''));
        $expected = (string) ($integration->webhook_verify_token ?? '');

        if ($mode === 'subscribe' && $expected !== '' && hash_equals($expected, $token)) {
            if (! $integration->webhook_set_at) {
                $integration->webhook_set_at = now();
                $integration->save();
            }

            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    protected function metaSignatureIsValid(Request $request, WhatsAppIntegration $integration): bool
    {
        $secret = $integration->getDecryptedAppSecret();
        if (! $secret) {
            return true;
        }

        $header = (string) $request->header('X-Hub-Signature-256', '');
        if (! str_starts_with($header, 'sha256=')) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $header);
    }

    protected function handleMetaWebhook(WhatsAppIntegration $integration, Request $request): void
    {
        $object = strtolower((string) $request->input('object', ''));
        if ($object !== '' && $object !== 'whatsapp_business_account') {
            return;
        }

        foreach ($request->input('entry', []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            foreach ($entry['changes'] ?? [] as $change) {
                if (! is_array($change)) {
                    continue;
                }

                $value = is_array($change['value'] ?? null) ? $change['value'] : [];
                $phoneNumberId = (string) ($value['metadata']['phone_number_id'] ?? '');
                if ($phoneNumberId !== '' && (string) $integration->phone_number_id !== '' && $phoneNumberId !== (string) $integration->phone_number_id) {
                    continue;
                }

                $contacts = is_array($value['contacts'] ?? null) ? $value['contacts'] : [];
                foreach ($value['messages'] ?? [] as $message) {
                    if (is_array($message)) {
                        $this->storeInboundCloudMessage($integration, $message, $contacts);
                    }
                }

                foreach ($value['statuses'] ?? [] as $status) {
                    if (is_array($status)) {
                        $this->applyCloudStatus($status);
                    }
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $message
     * @param  list<array<string, mixed>>  $contacts
     */
    protected function storeInboundCloudMessage(WhatsAppIntegration $integration, array $message, array $contacts): void
    {
        $wamid = (string) ($message['id'] ?? '');
        $from = (string) ($message['from'] ?? '');
        if ($wamid === '' || $from === '') {
            return;
        }

        if (WhatsAppMessage::where('wamid', $wamid)->exists()) {
            return;
        }

        $profileName = null;
        foreach ($contacts as $contact) {
            if (! is_array($contact)) {
                continue;
            }
            $waId = (string) ($contact['wa_id'] ?? '');
            if ($waId !== '' && $waId !== $from) {
                continue;
            }
            $name = $contact['profile']['name'] ?? null;
            if (is_string($name) && $name !== '') {
                $profileName = $name;
                break;
            }
        }

        $parsed = $this->parseCloudMessage($message);
        $conversation = $this->upsertConversation($integration->company_id, $from, $profileName);
        $isNewConversation = $conversation->wasRecentlyCreated
            || ! WhatsAppMessage::where('whatsapp_conversation_id', $conversation->id)->exists();

        $conversation->window_expires_at = now()->addHours(24);
        $conversation->is_subscribed = true;

        $mediaUrl = $parsed['media_url'];
        $mimeType = $parsed['mime_type'];
        if ($parsed['media_id']) {
            try {
                $stored = $this->storeInboundCloudMedia($integration, $parsed['media_id'], $parsed['file_name'], $wamid);
                $mediaUrl = $stored['url'];
                $mimeType = $stored['mime_type'] ?: $mimeType;
                if ($parsed['file_size'] === null && $stored['file_size']) {
                    $parsed['file_size'] = $stored['file_size'];
                }
            } catch (\Throwable $e) {
                Log::warning('WhatsApp inbound media download failed', [
                    'error' => WhatsAppCloudApiService::sanitizeGraphError($e->getMessage()),
                ]);
            }
        }

        $sentAt = now();
        if (! empty($message['timestamp']) && is_numeric($message['timestamp'])) {
            $sentAt = \Carbon\Carbon::createFromTimestamp((int) $message['timestamp']);
        }

        $record = WhatsAppMessage::create([
            'company_id' => $integration->company_id,
            'whatsapp_conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'wamid' => $wamid,
            'type' => $parsed['type'],
            'text' => $parsed['text'],
            'media_url' => $mediaUrl,
            'media_id' => $parsed['media_id'],
            'mime_type' => $mimeType,
            'file_name' => $parsed['file_name'],
            'file_size' => $parsed['file_size'],
            'latitude' => $parsed['latitude'],
            'longitude' => $parsed['longitude'],
            'contact_name' => $parsed['contact_name'],
            'contact_phone' => $parsed['contact_phone'],
            'status' => 'received',
            'raw_payload' => $message,
            'sent_at' => $sentAt,
        ]);

        $conversation->unread_count = (int) $conversation->unread_count + 1;
        $this->touchConversation($conversation, $record);
        $this->notifyUnread($conversation, $record);

        $lead = $this->leadAutoCreate->fromPhoneChannel(
            (int) $conversation->company_id,
            'whatsapp',
            $conversation->wa_id ?: $conversation->phone,
            $conversation->name
        );
        $this->leadAutoCreate->applyRules($lead, 'whatsapp', LeadRuleEngine::inboundTriggers($isNewConversation), [
            'contact_name' => $conversation->name,
            'phone' => $conversation->wa_id ?: $conversation->phone,
            'message' => $parsed['text'],
        ]);

        if ($isNewConversation) {
            $this->maybeSendWelcome($integration, $conversation);
        }
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array{
     *     type: string,
     *     text: ?string,
     *     media_id: ?string,
     *     media_url: ?string,
     *     mime_type: ?string,
     *     file_name: ?string,
     *     file_size: ?int,
     *     latitude: ?float,
     *     longitude: ?float,
     *     contact_name: ?string,
     *     contact_phone: ?string
     * }
     */
    protected function parseCloudMessage(array $message): array
    {
        $type = (string) ($message['type'] ?? 'text');
        $parsed = [
            'type' => 'text',
            'text' => null,
            'media_id' => null,
            'media_url' => null,
            'mime_type' => null,
            'file_name' => null,
            'file_size' => null,
            'latitude' => null,
            'longitude' => null,
            'contact_name' => null,
            'contact_phone' => null,
        ];

        if ($type === 'text') {
            $parsed['text'] = is_string($message['text']['body'] ?? null) ? $message['text']['body'] : null;

            return $parsed;
        }

        if (in_array($type, ['image', 'video', 'audio', 'document', 'sticker', 'voice'], true)) {
            $bucket = $type === 'voice' ? 'audio' : $type;
            $media = is_array($message[$type] ?? null) ? $message[$type] : (is_array($message[$bucket] ?? null) ? $message[$bucket] : []);
            $parsed['type'] = $type === 'voice' ? 'audio' : $type;
            $parsed['media_id'] = is_string($media['id'] ?? null) ? $media['id'] : null;
            $parsed['mime_type'] = is_string($media['mime_type'] ?? null) ? $media['mime_type'] : null;
            $parsed['file_name'] = is_string($media['filename'] ?? null) ? $media['filename'] : null;
            $parsed['text'] = is_string($media['caption'] ?? null) ? $media['caption'] : null;

            return $parsed;
        }

        if ($type === 'location') {
            $location = is_array($message['location'] ?? null) ? $message['location'] : [];
            $parsed['type'] = 'location';
            $parsed['latitude'] = isset($location['latitude']) ? (float) $location['latitude'] : null;
            $parsed['longitude'] = isset($location['longitude']) ? (float) $location['longitude'] : null;
            $parsed['text'] = trim((string) ($location['name'] ?? '').' '.(string) ($location['address'] ?? '')) ?: null;

            return $parsed;
        }

        if ($type === 'contacts') {
            $first = is_array($message['contacts'][0] ?? null) ? $message['contacts'][0] : [];
            $parsed['type'] = 'contact';
            $parsed['contact_name'] = is_string($first['name']['formatted_name'] ?? null) ? $first['name']['formatted_name'] : null;
            $phone = $first['phones'][0]['phone'] ?? $first['phones'][0]['wa_id'] ?? null;
            $parsed['contact_phone'] = is_string($phone) ? $phone : null;
            $parsed['text'] = $parsed['contact_name'];

            return $parsed;
        }

        if ($type === 'button') {
            $parsed['text'] = is_string($message['button']['text'] ?? null)
                ? $message['button']['text']
                : (is_string($message['button']['payload'] ?? null) ? $message['button']['payload'] : null);

            return $parsed;
        }

        if ($type === 'interactive') {
            $interactive = is_array($message['interactive'] ?? null) ? $message['interactive'] : [];
            $parsed['text'] = $interactive['button_reply']['title']
                ?? $interactive['list_reply']['title']
                ?? $interactive['nfm_reply']['body']
                ?? null;
            $parsed['text'] = is_string($parsed['text']) ? $parsed['text'] : null;

            return $parsed;
        }

        $parsed['text'] = '['.$type.']';

        return $parsed;
    }

    /**
     * @param  array<string, mixed>  $status
     */
    protected function applyCloudStatus(array $status): void
    {
        $wamid = (string) ($status['id'] ?? '');
        $state = (string) ($status['status'] ?? '');
        if ($wamid === '' || $state === '') {
            return;
        }

        WhatsAppMessage::query()->where('wamid', $wamid)->update(['status' => $state]);
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
        $token = $integration->getDecryptedAccessToken();
        if ($welcome === '' || ! $token || ! $integration->phone_number_id) {
            return;
        }

        try {
            $sent = $this->cloud->send(
                (string) $integration->phone_number_id,
                $token,
                (string) $conversation->wa_id,
                'text',
                $welcome
            );

            $message = WhatsAppMessage::create([
                'company_id' => $integration->company_id,
                'whatsapp_conversation_id' => $conversation->id,
                'direction' => 'outbound',
                'wamid' => $sent['wamid'],
                'type' => 'text',
                'text' => $welcome,
                'status' => $sent['status'] ?? 'sent',
                'raw_payload' => $sent['raw'],
                'sent_at' => now(),
            ]);

            $this->touchConversation($conversation, $message);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp welcome message failed', [
                'error' => WhatsAppCloudApiService::sanitizeGraphError($e->getMessage()),
            ]);
        }
    }

    /**
     * @return array{url: string, mime_type: ?string, file_size: ?int}
     */
    protected function storeInboundCloudMedia(
        WhatsAppIntegration $integration,
        string $mediaId,
        ?string $fileName,
        string $messageSid
    ): array {
        $token = $integration->getDecryptedAccessToken();
        if (! $token) {
            throw new \RuntimeException('WhatsApp Cloud API token is missing.');
        }

        $downloaded = $this->cloud->downloadMedia($mediaId, $token);
        $mimeType = $downloaded['mime_type'];
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
        Storage::disk('public')->put($path, $downloaded['binary']);

        return [
            'url' => public_media_url($path),
            'mime_type' => $mimeType,
            'file_size' => $downloaded['file_size'],
        ];
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

        if (! $integration || ! $integration->isCloudConnected()) {
            throw new HttpResponseException(
                response()->json(['message' => 'WhatsApp is not connected. Configure the Meta Cloud API under Integrations.'], 422)
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

    protected function constrainConversationsBefore(Builder $query, WhatsAppConversation $before): void
    {
        if ($before->last_message_at) {
            $query->where(function ($builder) use ($before) {
                $builder->where('last_message_at', '<', $before->last_message_at)
                    ->orWhere(function ($inner) use ($before) {
                        $inner->where('last_message_at', $before->last_message_at)
                            ->where('id', '<', $before->id);
                    })
                    ->orWhereNull('last_message_at');
            });

            return;
        }

        $query->whereNull('last_message_at')->where('id', '<', $before->id);
    }

    protected function constrainMessagesBefore(Builder $query, WhatsAppMessage $before): void
    {
        $at = $before->created_at;

        $query->where(function ($builder) use ($before, $at) {
            $builder->where('created_at', '<', $at)
                ->orWhere(function ($inner) use ($before, $at) {
                    $inner->where('created_at', $at)
                        ->where('id', '<', $before->id);
                });
        });
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
            'is_read' => (int) $c->unread_count <= 0,
            'labels' => $this->serializeLabels($c),
            'lead' => $this->crmLookup->matchAssignedLead(
                $this->crmLookup->assignedLeadIndex((int) $c->company_id),
                $c->phone ?: $c->wa_id,
                $c->extracted_email,
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
