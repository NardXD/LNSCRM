<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\FacebookConversation;
use App\Models\FacebookIntegration;
use App\Models\FacebookMessage;
use App\Models\User;
use App\Notifications\FacebookMessageNotification;
use App\Services\FacebookGraphHistoryService;
use App\Services\FacebookGraphMessagingService;
use App\Services\FacebookMessageSyncService;
use App\Services\FlexCrmLookupService;
use App\Services\LeadAutoCreateService;
use App\Services\LeadRuleEngine;
use App\Services\MessageContactExtractor;
use App\Services\TimezoneService;
use App\Services\TwilioCompanyService;
use App\Services\TwilioService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class FacebookController extends Controller
{
    public function __construct(
        protected TwilioCompanyService $twilioCompany,
        protected FacebookMessageSyncService $facebookSync,
        protected FacebookGraphMessagingService $graphMessaging,
        protected MessageContactExtractor $messageContacts,
        protected FlexCrmLookupService $crmLookup,
        protected LeadAutoCreateService $leadAutoCreate
    ) {}

    public function index()
    {
        $user = Auth::user();
        $integration = $this->channelIntegrationForCompany($user?->company_id);
        $twilioReady = $user?->company
            ? (bool) $this->twilioCompany->getActiveIntegration($user->company)
            : false;

        if ($integration) {
            $this->facebookSync->correctNaiveUtcTimestamps($integration);
        }

        $connected = (bool) ($integration && $integration->page_id && ($twilioReady || $integration->hasInstagramGraph()));

        return view('dashboard.facebook', [
            'integrationConnected' => $connected,
            'pageName' => $integration?->page_name,
            'instagramUsername' => $integration?->instagram_username,
            'appTimezone' => config('app.timezone'),
        ]);
    }

    public function bootstrap(): JsonResponse
    {
        $user = Auth::user();
        $integration = FacebookIntegration::where('company_id', $user->company_id)->first();
        $twilioReady = $user->company
            ? (bool) $this->twilioCompany->getActiveIntegration($user->company)
            : false;
        $tokenStatus = $this->pageTokenStatus($integration);

        return response()->json([
            'connected' => (bool) ($integration && $integration->is_active && $integration->page_id && ($twilioReady || $integration->hasInstagramGraph())),
            'account' => $integration ? [
                'page_id' => $integration->page_id,
                'page_name' => $integration->page_name,
                'instagram_business_account_id' => $integration->instagram_business_account_id,
                'instagram_username' => $integration->instagram_username,
                'webhook_url' => $integration->webhookUrl(),
                'webhook_set_at' => $integration->webhook_set_at?->toIso8601String(),
                'twilio_connected' => $twilioReady,
                'has_page_access_token' => (bool) $integration->getDecryptedPageAccessToken(),
                'token_expired' => $tokenStatus['expired'],
                'instagram_graph' => $integration->hasInstagramGraph(),
                'integrations_url' => route('integrations'),
            ] : null,
        ]);
    }

    public function conversations(Request $request): JsonResponse
    {
        $user = Auth::user();
        $q = trim((string) $request->query('q', ''));
        $channel = trim((string) $request->query('channel', ''));

        $query = FacebookConversation::query()
            ->where('company_id', $user->company_id)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        if (in_array($channel, ['messenger', 'instagram'], true)) {
            $query->where('channel', $channel);
        }

        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', '%'.$q.'%')
                    ->orWhere('username', 'like', '%'.$q.'%')
                    ->orWhere('peer_id', 'like', '%'.$q.'%')
                    ->orWhere('last_message_preview', 'like', '%'.$q.'%');
            });
        }

        $this->pullRecentChannelMessages($user);

        $conversations = $query->limit(500)->get()->map(fn (FacebookConversation $c) => $this->formatConversation($c));

        return response()->json(['data' => $conversations]);
    }

    protected function pullRecentChannelMessages($user): void
    {
        $companyId = (int) ($user?->company_id ?: 0);
        if ($companyId < 1 || ! $user?->company) {
            return;
        }

        if (! Cache::add('facebook-recent-pull-'.$companyId, 1, 8)) {
            return;
        }

        try {
            $integration = $this->channelIntegrationForCompany($companyId);
            if (! $integration) {
                return;
            }

            $twilio = $this->optionalTwilioClient($user->company);
            $this->facebookSync->ingestRecent($integration, $twilio);
            $this->ensurePageSubscribed($integration);
        } catch (\Throwable $e) {
            Log::warning('Facebook recent message pull failed', ['error' => $e->getMessage()]);
        }
    }

    public function messages(FacebookConversation $conversation): JsonResponse
    {
        $this->assertCompanyConversation($conversation);
        $this->importGraphThread($conversation);

        $messages = FacebookMessage::query()
            ->where('facebook_conversation_id', $conversation->id)
            ->orderBy('sent_at')
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(2000)
            ->get()
            ->map(fn (FacebookMessage $m) => $this->formatMessage($m));

        $conversation->update(['unread_count' => 0]);
        $this->markConversationNotificationsRead($conversation);

        $extracted = $this->messageContacts->applyToConversation($conversation);
        $fresh = $conversation->fresh();
        $payload = $this->formatConversation($fresh);
        if (! ($payload['lead'] ?? null)) {
            $index = $this->crmLookup->assignedLeadIndex((int) $fresh->company_id);
            foreach ($extracted['phones'] as $phone) {
                $payload['lead'] = $this->crmLookup->matchAssignedLead($index, $phone);
                if ($payload['lead']) {
                    break;
                }
            }
            if (! ($payload['lead'] ?? null)) {
                foreach ($extracted['emails'] as $email) {
                    $payload['lead'] = $this->crmLookup->matchAssignedLead($index, null, $email);
                    if ($payload['lead']) {
                        break;
                    }
                }
            }
            if (! ($payload['lead'] ?? null) && ($extracted['names'][0] ?? null)) {
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
        ]);
    }

    protected function importGraphThread(FacebookConversation $conversation): void
    {
        $integration = $this->channelIntegrationForCompany((int) $conversation->company_id);
        $token = $integration?->getDecryptedPageAccessToken();
        if (! $integration || ! $token) {
            return;
        }

        if (! Cache::add('facebook-graph-thread-'.$conversation->id, 1, 20)) {
            return;
        }

        try {
            $graph = app(FacebookGraphHistoryService::class);
            $rows = $graph->thread(
                (string) $integration->page_id,
                $token,
                (string) $conversation->peer_id,
                (string) $conversation->channel,
                array_values(array_filter([
                    (string) $integration->page_id,
                    (string) $integration->instagram_business_account_id,
                ]))
            );
            $imported = $this->facebookSync->importGraphRows($integration, $rows);
            if ($graph->lastError()) {
                Log::warning('Facebook Graph thread lookup failed', [
                    'conversation_id' => $conversation->id,
                    'error' => $graph->lastError(),
                ]);
            } elseif ($imported > 0) {
                Log::info('Facebook Graph thread imported Page Inbox replies', [
                    'conversation_id' => $conversation->id,
                    'imported' => $imported,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Facebook Graph thread import failed', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function sendMessage(Request $request, FacebookConversation $conversation): JsonResponse
    {
        $this->assertCompanyConversation($conversation);

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:text,image,video,audio,file'],
            'text' => ['nullable', 'string', 'max:2000'],
            'media_url' => ['nullable', 'url', 'max:2048'],
            'file_name' => ['nullable', 'string', 'max:256'],
            'file_size' => ['nullable', 'integer', 'min:1'],
        ]);

        $channel = $this->requireActiveIntegration();
        $type = $validated['type'];
        $body = null;
        $mediaUrl = null;

        if ($type === 'text') {
            $body = (string) ($validated['text'] ?? '');
            if ($body === '') {
                return response()->json(['message' => 'Message text is required.'], 422);
            }
        } else {
            $mediaUrl = $validated['media_url'] ?? null;
            if (! $mediaUrl) {
                return response()->json(['message' => 'A media URL is required.'], 422);
            }
            $body = $validated['text'] ?? null;
        }

        $mid = null;
        $status = 'sent';
        $raw = [];

        try {
            if ((string) $conversation->channel === 'instagram') {
                $token = $channel->getDecryptedPageAccessToken();
                if (! $token) {
                    return response()->json([
                        'message' => 'Add a Facebook Page Access Token under Integrations to send Instagram Direct messages.',
                    ], 422);
                }
                $sent = $this->graphMessaging->send(
                    (string) $channel->page_id,
                    $token,
                    (string) $conversation->peer_id,
                    $type,
                    $body,
                    $mediaUrl
                );
                $mid = $sent['message_id'];
                $raw = $sent['raw'];
            } else {
                $twilio = $this->twilioClientForCompany(Auth::user()->company);
                $sent = $twilio->sendMessenger(
                    $channel->senderIdForChannel((string) $conversation->channel),
                    (string) $conversation->peer_id,
                    (string) $conversation->channel,
                    $body,
                    $channel->statusCallbackUrl(),
                    $mediaUrl
                );
                $mid = $sent->sid;
                $status = $sent->status ?? 'sent';
                $raw = ['sid' => $sent->sid, 'status' => $sent->status];
            }
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $message = FacebookMessage::create([
            'company_id' => $conversation->company_id,
            'facebook_conversation_id' => $conversation->id,
            'user_id' => Auth::id(),
            'direction' => 'outbound',
            'mid' => $mid,
            'type' => $type,
            'text' => $validated['text'] ?? null,
            'media_url' => $mediaUrl,
            'file_name' => $validated['file_name'] ?? null,
            'file_size' => $validated['file_size'] ?? null,
            'status' => $status,
            'raw_payload' => $raw,
            'sent_at' => now(),
        ]);

        $this->touchConversation($conversation, $message);

        $extracted = $this->messageContacts->applyToConversation($conversation);
        $lead = $this->leadAutoCreate->fromFacebookConversation($conversation, $extracted);
        $isNew = FacebookMessage::query()->where('facebook_conversation_id', $conversation->id)->count() <= 1;
        $this->leadAutoCreate->applyRules($lead, 'facebook', LeadRuleEngine::outboundTriggers($isNew), [
            'contact_name' => $conversation->name,
            'message' => (string) ($validated['text'] ?? ''),
        ]);

        return response()->json(['data' => $this->formatMessage($message)], 201);
    }

    public function uploadMedia(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:26214'],
            'kind' => ['nullable', 'string', 'in:image,video,audio,file'],
        ]);

        $file = $validated['file'];
        $kind = $validated['kind'] ?? 'file';
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $safeName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'media';
        $path = $file->storeAs(
            'facebook/'.Auth::user()->company_id.'/'.date('Y/m'),
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

    public function syncHistory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => ['nullable', 'integer', 'in:30,90,365,0'],
            'limit' => ['nullable', 'integer', 'min:50', 'max:5000'],
        ]);

        $integration = $this->requireActiveIntegration();
        $twilio = $this->optionalTwilioClient(Auth::user()->company);
        if (! $twilio && ! $integration->getDecryptedPageAccessToken()) {
            return response()->json([
                'message' => 'Add a Facebook Page Access Token under Integrations to import replies sent from Messenger.',
            ], 422);
        }

        @set_time_limit(300);

        try {
            $result = $this->facebookSync->sync(
                $integration,
                $twilio,
                (int) ($validated['days'] ?? 90),
                (int) ($validated['limit'] ?? 2000)
            );
        } catch (\Throwable $e) {
            Log::error('Facebook history sync failed', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => $e->getMessage() ?: 'Could not sync Messenger inbox history.',
            ], 422);
        }

        return response()->json(['data' => $result]);
    }

    public function webhook(Request $request, string $webhookKey): Response
    {
        $integration = FacebookIntegration::where('webhook_key', $webhookKey)
            ->where('is_active', true)
            ->first();

        if (! $integration) {
            return response('Not found', 404);
        }

        if ($request->isMethod('get')) {
            return $this->verifyMetaWebhook($request, $integration);
        }

        $object = strtolower((string) $request->input('object', ''));
        if (in_array($object, ['instagram', 'page'], true)) {
            if (! $this->metaSignatureIsValid($request, $integration)) {
                return response('Invalid signature', 403);
            }

            try {
                $this->handleMetaWebhook($integration, $request, $object);
            } catch (\Throwable $e) {
                Log::error('Meta Instagram webhook handler error', ['error' => $e->getMessage()]);
            }

            if (! $integration->webhook_set_at) {
                $integration->webhook_set_at = now();
                $integration->save();
            }

            return response('EVENT_RECEIVED', 200);
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
                    Log::warning('Facebook Twilio webhook signature mismatch', [
                        'company_id' => $integration->company_id,
                    ]);

                    return response('Invalid signature', 403);
                }
            }

            $accountSid = $request->input('AccountSid');
            if ($accountSid && $accountSid !== $twilioIntegration->account_sid) {
                Log::warning('Facebook webhook AccountSid mismatch', [
                    'company_id' => $integration->company_id,
                ]);

                return response('OK', 200);
            }
        }

        try {
            $this->handleInboundTwilioMessage($integration, $request);
        } catch (\Throwable $e) {
            Log::error('Facebook webhook handler error', ['error' => $e->getMessage()]);
        }

        if (! $integration->webhook_set_at) {
            $integration->webhook_set_at = now();
            $integration->save();
        }

        return response('OK', 200);
    }

    public function acceptTwilioInbound(FacebookIntegration $integration, Request $request): void
    {
        $this->handleInboundTwilioMessage($integration, $request);
    }

    protected function verifyMetaWebhook(Request $request, FacebookIntegration $integration): Response
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

    protected function metaSignatureIsValid(Request $request, FacebookIntegration $integration): bool
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

    protected function handleMetaWebhook(FacebookIntegration $integration, Request $request, string $object): void
    {
        $ownIds = array_values(array_filter([
            (string) $integration->page_id,
            (string) $integration->instagram_business_account_id,
        ]));
        $igId = (string) $integration->instagram_business_account_id;

        foreach ($request->input('entry', []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $entryId = (string) ($entry['id'] ?? '');
            $events = $entry['messaging'] ?? [];
            if (! is_array($events) || $events === []) {
                continue;
            }

            foreach ($events as $event) {
                if (! is_array($event)) {
                    continue;
                }

                $recipientId = (string) ($event['recipient']['id'] ?? '');
                $isInstagram = $object === 'instagram'
                    || ($igId !== '' && ($entryId === $igId || $recipientId === $igId));
                $channel = $isInstagram ? 'instagram' : 'messenger';
                $isEcho = ! empty($event['message']['is_echo']);

                // Messenger inbound still arrives via Twilio; only capture Page Inbox echoes.
                if ($channel === 'messenger' && ! $isEcho) {
                    continue;
                }

                $this->storeMetaMessagingEvent($integration, $event, $channel, $ownIds, $isEcho);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $event
     * @param  array<int, string>  $ownIds
     */
    protected function storeMetaMessagingEvent(
        FacebookIntegration $integration,
        array $event,
        string $channel,
        array $ownIds,
        bool $isEcho
    ): void {
        if (! empty($event['message']['is_deleted'])) {
            return;
        }

        if (empty($event['message']) && empty($event['postback'])) {
            return;
        }

        $senderId = (string) ($event['sender']['id'] ?? '');
        $recipientId = (string) ($event['recipient']['id'] ?? '');
        $direction = $isEcho ? 'outbound' : 'inbound';
        $peerId = $isEcho ? $recipientId : $senderId;
        if ($peerId === '' || in_array($peerId, $ownIds, true)) {
            return;
        }

        $message = is_array($event['message'] ?? null) ? $event['message'] : [];
        $postback = is_array($event['postback'] ?? null) ? $event['postback'] : [];
        $mid = (string) ($message['mid'] ?? ($postback['mid'] ?? ''));
        if ($mid === '') {
            $mid = 'meta-'.md5(json_encode($event) ?: uniqid($channel, true));
        }

        if (FacebookMessage::where('mid', $mid)->exists()) {
            return;
        }

        $text = null;
        if (isset($message['text']) && is_string($message['text']) && $message['text'] !== '') {
            $text = $message['text'];
        } elseif (isset($postback['title']) && is_string($postback['title'])) {
            $text = $postback['title'];
        } elseif (isset($postback['payload']) && is_string($postback['payload'])) {
            $text = $postback['payload'];
        }

        $attachments = is_array($message['attachments'] ?? null) ? $message['attachments'] : [];
        $type = 'text';
        $mediaUrl = null;
        $mimeType = null;
        $fileName = null;

        if ($attachments !== []) {
            $attachment = is_array($attachments[0]) ? $attachments[0] : [];
            $attType = strtolower((string) ($attachment['type'] ?? 'file'));
            $type = in_array($attType, ['image', 'video', 'audio', 'file', 'story_mention', 'share'], true)
                ? ($attType === 'story_mention' || $attType === 'share' ? 'image' : $attType)
                : 'file';
            $payload = is_array($attachment['payload'] ?? null) ? $attachment['payload'] : [];
            $remote = isset($payload['url']) && is_string($payload['url']) ? $payload['url'] : null;
            if ($remote) {
                try {
                    $mediaUrl = $this->storeInboundGraphMedia(
                        $integration,
                        $remote,
                        $type,
                        $mid
                    );
                } catch (\Throwable $e) {
                    Log::warning('Meta inbound media download failed', ['error' => $e->getMessage()]);
                    $mediaUrl = $remote;
                }
            } elseif ($text === null) {
                $text = $attType === 'story_mention' ? '[Story mention]' : '['.ucfirst($attType).']';
                $type = 'text';
            }
        }

        if ($text === null && $mediaUrl === null) {
            return;
        }

        $profileName = null;
        $username = null;
        $profilePic = null;
        $token = $integration->getDecryptedPageAccessToken();
        if ($token && $channel === 'instagram' && ! $isEcho) {
            $profile = $this->graphMessaging->instagramUser($peerId, $token);
            $profileName = isset($profile['name']) && is_string($profile['name']) ? $profile['name'] : null;
            $username = isset($profile['username']) && is_string($profile['username']) ? $profile['username'] : null;
            $profilePic = isset($profile['profile_pic']) && is_string($profile['profile_pic']) ? $profile['profile_pic'] : null;
        }

        $conversation = $this->upsertConversation($integration, $channel, $peerId, $profileName);
        if ($username && ! $conversation->username) {
            $conversation->username = $username;
        }
        if ($profilePic) {
            $conversation->profile_pic = $profilePic;
        }
        if ($username || $profilePic) {
            $conversation->save();
        }

        $timestamp = $event['timestamp'] ?? null;
        $sentAt = is_numeric($timestamp)
            ? TimezoneService::fromExternal(\Carbon\Carbon::createFromTimestampMs((int) $timestamp))
            : now();

        if ($this->facebookSync->findNearDuplicate($conversation->id, $direction, $type, $text, $sentAt)) {
            return;
        }

        $isNewConversation = $conversation->wasRecentlyCreated
            || FacebookMessage::where('facebook_conversation_id', $conversation->id)->count() === 0;

        $record = FacebookMessage::create([
            'company_id' => $integration->company_id,
            'facebook_conversation_id' => $conversation->id,
            'direction' => $direction,
            'mid' => $mid,
            'type' => $type,
            'text' => $text,
            'media_url' => $mediaUrl,
            'mime_type' => $mimeType,
            'file_name' => $fileName,
            'status' => $direction === 'outbound' ? 'sent' : 'received',
            'raw_payload' => $event,
            'sent_at' => $sentAt,
        ]);

        if ($direction === 'inbound') {
            $conversation->unread_count = (int) $conversation->unread_count + 1;
        }
        $this->touchConversation($conversation, $record);

        if ($direction === 'outbound') {
            return;
        }

        $extracted = $this->messageContacts->applyToConversation($conversation);
        $this->notifyUnread($conversation, $record);

        $lead = $this->leadAutoCreate->fromFacebookConversation($conversation, $extracted);
        $this->leadAutoCreate->applyRules($lead, 'facebook', LeadRuleEngine::inboundTriggers($isNewConversation), [
            'contact_name' => $conversation->name,
            'message' => $text,
        ]);

        if ($isNewConversation) {
            $this->maybeSendWelcome($integration, $conversation);
        }
    }

    protected function storeInboundGraphMedia(
        FacebookIntegration $integration,
        string $remoteUrl,
        string $type,
        string $messageSid
    ): string {
        $binary = $this->graphMessaging->download($remoteUrl, $integration->getDecryptedPageAccessToken());
        $ext = match ($type) {
            'image' => 'jpg',
            'video' => 'mp4',
            'audio' => 'mp4',
            default => 'bin',
        };
        $path = 'facebook/'.$integration->company_id.'/inbound/'.date('Y/m').'/'.$messageSid.'-'.Str::random(6).'.'.$ext;
        Storage::disk('public')->put($path, $binary);

        return public_media_url($path);
    }

    protected function handleInboundTwilioMessage(FacebookIntegration $integration, Request $request): void
    {
        $messageSid = $request->input('MessageSid');
        if (! $messageSid) {
            return;
        }

        if (FacebookMessage::where('mid', $messageSid)->exists()) {
            return;
        }

        $from = (string) ($request->input('From') ?: $request->input('ChannelFromAddress', ''));
        $to = (string) ($request->input('To') ?: $request->input('ChannelToAddress', ''));
        $parsedFrom = TwilioService::parseMessengerAddress($from);
        $parsedTo = TwilioService::parseMessengerAddress($to);
        $ownIds = array_values(array_filter([
            (string) $integration->page_id,
            (string) $integration->instagram_business_account_id,
        ]));

        if ($parsedFrom['id'] !== '' && in_array($parsedFrom['id'], $ownIds, true)) {
            return;
        }

        $peerId = $parsedFrom['id'];
        $channel = $parsedFrom['channel'] ?: $parsedTo['channel'];
        if ($peerId === '' && $parsedTo['id'] !== '' && ! in_array($parsedTo['id'], $ownIds, true)) {
            $peerId = $parsedTo['id'];
            $channel = $parsedTo['channel'] ?: 'messenger';
        }

        if ($peerId === '' || in_array($peerId, $ownIds, true)) {
            Log::info('Facebook inbound skipped, no customer peer', [
                'from' => $from,
                'to' => $to,
                'sid' => $messageSid,
            ]);

            return;
        }

        $profileName = $request->input('ProfileName') ?: $request->input('FacebookName');
        $body = $request->input('Body');
        $numMedia = (int) $request->input('NumMedia', 0);

        $conversation = $this->upsertConversation(
            $integration,
            $channel,
            $peerId,
            is_string($profileName) && $profileName !== '' ? $profileName : null
        );

        $isNewConversation = $conversation->wasRecentlyCreated
            || FacebookMessage::where('facebook_conversation_id', $conversation->id)->count() === 0;

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
                    Log::warning('Facebook inbound media download failed', ['error' => $e->getMessage()]);
                    $mediaUrl = (string) $remoteMedia;
                }
            }
        }

        $record = FacebookMessage::create([
            'company_id' => $integration->company_id,
            'facebook_conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'mid' => (string) $messageSid,
            'type' => $type,
            'text' => $text,
            'media_url' => $mediaUrl,
            'mime_type' => $mimeType,
            'file_name' => $fileName,
            'status' => $request->input('SmsStatus', 'received'),
            'raw_payload' => $request->except(['MediaUrl0', 'MediaUrl1']),
            'sent_at' => TimezoneService::fromExternal(
                $request->input('DateSent') ?: $request->input('DateCreated')
            ),
        ]);

        $conversation->unread_count = (int) $conversation->unread_count + 1;
        $this->touchConversation($conversation, $record);
        $extracted = $this->messageContacts->applyToConversation($conversation);
        $this->notifyUnread($conversation, $record);

        $lead = $this->leadAutoCreate->fromFacebookConversation($conversation, $extracted);
        $this->leadAutoCreate->applyRules($lead, 'facebook', LeadRuleEngine::inboundTriggers($isNewConversation), [
            'contact_name' => $conversation->name,
            'message' => $text,
        ]);

        if ($isNewConversation) {
            $this->maybeSendWelcome($integration, $conversation);
        }
    }

    protected function maybeSendWelcome(FacebookIntegration $integration, FacebookConversation $conversation): void
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
            if ((string) $conversation->channel === 'instagram') {
                $token = $integration->getDecryptedPageAccessToken();
                if (! $token) {
                    return;
                }
                $sent = $this->graphMessaging->send(
                    (string) $integration->page_id,
                    $token,
                    (string) $conversation->peer_id,
                    'text',
                    $welcome
                );
                $mid = $sent['message_id'];
                $status = 'sent';
                $raw = $sent['raw'];
            } else {
                $twilio = $this->twilioClientForCompany($company);
                $sent = $twilio->sendMessenger(
                    $integration->senderIdForChannel((string) $conversation->channel),
                    (string) $conversation->peer_id,
                    (string) $conversation->channel,
                    $welcome,
                    $integration->statusCallbackUrl()
                );
                $mid = $sent->sid;
                $status = $sent->status ?? 'sent';
                $raw = ['sid' => $sent->sid, 'status' => $sent->status];
            }

            $message = FacebookMessage::create([
                'company_id' => $integration->company_id,
                'facebook_conversation_id' => $conversation->id,
                'direction' => 'outbound',
                'mid' => $mid,
                'type' => 'text',
                'text' => $welcome,
                'status' => $status,
                'raw_payload' => $raw,
                'sent_at' => now(),
            ]);
            $this->touchConversation($conversation, $message);
        } catch (\Throwable $e) {
            Log::warning('Facebook welcome message failed', ['error' => $e->getMessage()]);
        }
    }

    protected function upsertConversation(
        FacebookIntegration $integration,
        string $channel,
        string $peerId,
        ?string $profileName
    ): FacebookConversation {
        $conversation = FacebookConversation::firstOrNew([
            'company_id' => $integration->company_id,
            'channel' => $channel,
            'peer_id' => $peerId,
        ]);

        $placeholder = $channel === 'instagram' ? 'Instagram User' : 'Messenger User';
        $existingName = $conversation->name;

        if ($profileName) {
            $conversation->name = $profileName;
        } elseif (! $existingName || $existingName === $placeholder) {
            $conversation->name = $placeholder;
        }

        $conversation->save();

        return $conversation;
    }

    protected function storeInboundMedia(
        FacebookIntegration $integration,
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
                str_contains($mimeType, 'gif') => 'gif',
                str_contains($mimeType, 'mp4') => 'mp4',
                str_contains($mimeType, 'ogg') => 'ogg',
                str_contains($mimeType, 'mpeg') => 'mp3',
                str_contains($mimeType, 'pdf') => 'pdf',
                default => 'bin',
            };
        }

        $path = 'facebook/'.$integration->company_id.'/inbound/'.date('Y/m').'/'.$messageSid.'-'.Str::random(6).'.'.$ext;
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
            default => 'file',
        };
    }

    protected function notifyUnread(FacebookConversation $conversation, FacebookMessage $message): void
    {
        $recipients = User::query()
            ->where('company_id', $conversation->company_id)
            ->where(function ($query) {
                $query->whereHas('role.permissions', fn ($q) => $q->where('slug', 'view_facebook'))
                    ->orWhereHas('roles.permissions', fn ($q) => $q->where('slug', 'view_facebook'));
            })
            ->get();

        foreach ($recipients as $recipient) {
            try {
                $existing = $recipient->unreadNotifications()
                    ->where('type', FacebookMessageNotification::class)
                    ->get()
                    ->first(fn ($n) => (int) ($n->data['conversation_id'] ?? 0) === (int) $conversation->id);

                if ($existing) {
                    $existing->delete();
                }

                $recipient->notify(new FacebookMessageNotification($conversation, $message));
            } catch (\Throwable $e) {
                Log::warning('Failed to notify Facebook unread', [
                    'conversation_id' => $conversation->id,
                    'user_id' => $recipient->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function markConversationNotificationsRead(FacebookConversation $conversation): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $user->unreadNotifications()
            ->where('type', FacebookMessageNotification::class)
            ->get()
            ->each(function ($notification) use ($conversation) {
                if ((int) ($notification->data['conversation_id'] ?? 0) === (int) $conversation->id) {
                    $notification->markAsRead();
                }
            });
    }

    protected function touchConversation(FacebookConversation $conversation, FacebookMessage $message): void
    {
        $preview = match ($message->type) {
            'text' => (string) $message->text,
            'image' => '[Image]',
            'video' => '[Video]',
            'audio' => '[Audio]',
            'file' => '[File] '.($message->file_name ?: ''),
            default => '['.ucfirst($message->type).']',
        };

        $conversation->last_message_preview = Str::limit(trim($preview), 480);
        $conversation->last_message_at = $message->sent_at ?: now();
        $conversation->save();
    }

    /**
     * @return array{expired: bool, never_expires: bool}
     */
    protected function pageTokenStatus(?FacebookIntegration $integration): array
    {
        $token = $integration?->getDecryptedPageAccessToken();
        if (! $integration || ! $token) {
            return ['expired' => false, 'never_expires' => false];
        }

        return Cache::remember('facebook-token-status-'.$integration->id, 120, function () use ($token) {
            $info = $this->graphMessaging->inspectToken($token);

            return [
                'expired' => ! $info['valid'] || $this->graphMessaging->isExpiredTokenError($info['error']),
                'never_expires' => (bool) $info['never_expires'],
            ];
        });
    }

    protected function optionalTwilioClient(?Company $company): ?TwilioService
    {
        try {
            return $this->twilioClientForCompany($company);
        } catch (HttpResponseException) {
            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function ensurePageSubscribed(FacebookIntegration $integration): void
    {
        $token = $integration->getDecryptedPageAccessToken();
        $pageId = (string) $integration->page_id;
        if (! $token || $pageId === '') {
            return;
        }

        if (! Cache::add('facebook-page-subscribe-'.$integration->id, 1, 3600)) {
            return;
        }

        try {
            $this->graphMessaging->subscribePage($pageId, $token);
        } catch (\Throwable $e) {
            Log::warning('Facebook Page subscribe failed', ['error' => $e->getMessage()]);
        }
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

    protected function channelIntegrationForCompany(?int $companyId): ?FacebookIntegration
    {
        if (! $companyId) {
            return null;
        }

        return FacebookIntegration::where('company_id', $companyId)->where('is_active', true)->first();
    }

    protected function requireActiveIntegration(): FacebookIntegration
    {
        $integration = $this->channelIntegrationForCompany(Auth::user()->company_id);

        if (! $integration || ! $integration->page_id) {
            throw new HttpResponseException(
                response()->json(['message' => 'Facebook is not connected. Configure it under Integrations.'], 422)
            );
        }

        return $integration;
    }

    protected function assertCompanyConversation(FacebookConversation $conversation): void
    {
        if ((int) $conversation->company_id !== (int) Auth::user()->company_id) {
            abort(404);
        }
    }

    protected function formatConversation(FacebookConversation $c): array
    {
        return [
            'id' => $c->id,
            'channel' => $c->channel,
            'peer_id' => $c->peer_id,
            'name' => $c->name ?: ($c->channel === 'instagram' ? 'Instagram User' : 'Messenger User'),
            'username' => $c->username,
            'profile_pic' => $c->profile_pic,
            'unread_count' => (int) $c->unread_count,
            'last_message_preview' => $c->last_message_preview,
            'last_message_at' => $c->last_message_at?->toIso8601String(),
            'lead' => $this->crmLookup->matchAssignedLead(
                $this->crmLookup->assignedLeadIndex((int) $c->company_id),
                null,
                null,
                $c->name,
                $c->username
            ),
        ];
    }

    protected function formatMessage(FacebookMessage $m): array
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
            'status' => $m->status,
            'user_id' => $m->user_id,
            'sent_at' => $m->sent_at?->toIso8601String(),
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }
}
