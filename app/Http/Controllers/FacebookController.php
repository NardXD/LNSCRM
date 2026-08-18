<?php

namespace App\Http\Controllers;

use App\Models\FacebookConversation;
use App\Models\FacebookIntegration;
use App\Models\FacebookMessage;
use App\Models\User;
use App\Notifications\FacebookMessageNotification;
use App\Services\FacebookGraphService;
use App\Services\LeadAutoCreateService;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class FacebookController extends Controller
{
    public function __construct(
        protected LeadAutoCreateService $leadAutoCreate
    ) {}

    public function index()
    {
        $companyId = Auth::user()?->company_id;
        $integration = $companyId
            ? FacebookIntegration::where('company_id', $companyId)->where('is_active', true)->first()
            : null;

        return view('dashboard.facebook', [
            'integrationConnected' => (bool) $integration,
            'pageName' => $integration?->page_name,
            'instagramUsername' => $integration?->instagram_username,
        ]);
    }

    public function bootstrap(): JsonResponse
    {
        $user = Auth::user();
        $integration = FacebookIntegration::where('company_id', $user->company_id)->first();

        return response()->json([
            'connected' => (bool) ($integration && $integration->is_active && $integration->page_id),
            'account' => $integration ? [
                'page_id' => $integration->page_id,
                'page_name' => $integration->page_name,
                'instagram_business_account_id' => $integration->instagram_business_account_id,
                'instagram_username' => $integration->instagram_username,
                'webhook_url' => $integration->webhookUrl(),
                'webhook_verify_token' => $integration->webhook_verify_token,
                'webhook_set_at' => $integration->webhook_set_at?->toIso8601String(),
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

        $conversations = $query->limit(500)->get()->map(fn (FacebookConversation $c) => $this->formatConversation($c));

        return response()->json(['data' => $conversations]);
    }

    public function messages(FacebookConversation $conversation): JsonResponse
    {
        $this->assertCompanyConversation($conversation);

        $messages = FacebookMessage::query()
            ->where('facebook_conversation_id', $conversation->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(2000)
            ->get()
            ->map(fn (FacebookMessage $m) => $this->formatMessage($m));

        $conversation->update(['unread_count' => 0]);
        $this->markConversationNotificationsRead($conversation);

        return response()->json([
            'conversation' => $this->formatConversation($conversation->fresh()),
            'data' => $messages,
        ]);
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

        $integration = $this->requireActiveIntegration();
        $service = FacebookGraphService::forIntegration($integration);

        try {
            $response = match ($validated['type']) {
                'text' => $service->sendText($conversation->peer_id, (string) ($validated['text'] ?? '')),
                'image', 'video', 'audio', 'file' => $service->sendAttachment(
                    $conversation->peer_id,
                    $validated['type'],
                    (string) ($validated['media_url'] ?? '')
                ),
            };
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $message = FacebookMessage::create([
            'company_id' => $conversation->company_id,
            'facebook_conversation_id' => $conversation->id,
            'user_id' => Auth::id(),
            'direction' => 'outbound',
            'mid' => isset($response['message_id']) ? (string) $response['message_id'] : null,
            'type' => $validated['type'],
            'text' => $validated['text'] ?? null,
            'media_url' => $validated['media_url'] ?? null,
            'file_name' => $validated['file_name'] ?? null,
            'file_size' => $validated['file_size'] ?? null,
            'status' => 'sent',
            'raw_payload' => $response,
            'sent_at' => now(),
        ]);

        $this->touchConversation($conversation, $message);

        $this->leadAutoCreate->fromFacebook(
            (int) $conversation->company_id,
            (string) $conversation->channel,
            $conversation->name,
            $conversation->username
        );

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

    public function webhook(Request $request, string $webhookKey): Response
    {
        $integration = FacebookIntegration::where('webhook_key', $webhookKey)
            ->where('is_active', true)
            ->first();

        if (! $integration) {
            return response('Not found', 404);
        }

        if ($request->isMethod('get')) {
            $mode = $request->query('hub_mode') ?? $request->query('hub.mode');
            $token = $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
            $challenge = $request->query('hub_challenge') ?? $request->query('hub.challenge');

            if ($mode === 'subscribe' && hash_equals((string) $integration->webhook_verify_token, (string) $token)) {
                $integration->webhook_set_at = now();
                $integration->save();

                return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
            }

            return response('Forbidden', 403);
        }

        $raw = $request->getContent();
        $signature = $request->header('X-Hub-Signature-256');

        try {
            $service = FacebookGraphService::forIntegration($integration);
            if ($raw !== '' && $integration->getDecryptedAppSecret() && ! $service->verifySignature($raw, $signature)) {
                Log::warning('Facebook webhook signature mismatch', ['company_id' => $integration->company_id]);

                return response('Invalid signature', 403);
            }
        } catch (\Throwable $e) {
            Log::warning('Facebook webhook auth error', ['error' => $e->getMessage()]);

            return response('OK', 200);
        }

        $payload = $request->json()->all();

        try {
            $object = $payload['object'] ?? null;
            $channel = $object === 'instagram' ? 'instagram' : 'messenger';

            foreach ($payload['entry'] ?? [] as $entry) {
                $messagingEvents = $entry['messaging'] ?? [];
                foreach ($messagingEvents as $event) {
                    $this->handleMessagingEvent($integration, $channel, $event);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Facebook webhook handler error', ['error' => $e->getMessage()]);
        }

        return response('EVENT_RECEIVED', 200);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    protected function handleMessagingEvent(FacebookIntegration $integration, string $channel, array $event): void
    {
        if (isset($event['message']['is_echo']) && $event['message']['is_echo']) {
            return;
        }

        if (empty($event['message']) || empty($event['sender']['id'])) {
            return;
        }

        $peerId = (string) $event['sender']['id'];
        $mid = isset($event['message']['mid']) ? (string) $event['message']['mid'] : null;
        if ($mid && FacebookMessage::where('mid', $mid)->exists()) {
            return;
        }

        $conversation = $this->upsertConversation($integration, $channel, $peerId);
        $messagePayload = $event['message'];

        $type = 'text';
        $text = $messagePayload['text'] ?? null;
        $mediaUrl = null;
        $mimeType = null;
        $fileName = null;

        $attachments = $messagePayload['attachments'] ?? [];
        if (! empty($attachments[0])) {
            $attachment = $attachments[0];
            $type = match ($attachment['type'] ?? 'file') {
                'image' => 'image',
                'video' => 'video',
                'audio' => 'audio',
                default => 'file',
            };
            $mediaUrl = $attachment['payload']['url'] ?? null;
            if ($mediaUrl) {
                try {
                    $mediaUrl = $this->storeInboundMedia($integration, (string) $mediaUrl, $type);
                } catch (\Throwable $e) {
                    Log::warning('Facebook inbound media download failed', ['error' => $e->getMessage()]);
                }
            }
        }

        $record = FacebookMessage::create([
            'company_id' => $integration->company_id,
            'facebook_conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'mid' => $mid,
            'type' => $type,
            'text' => $text,
            'media_url' => $mediaUrl,
            'mime_type' => $mimeType,
            'file_name' => $fileName,
            'status' => 'received',
            'raw_payload' => $event,
            'sent_at' => isset($event['timestamp'])
                ? Carbon::createFromTimestampMs((int) $event['timestamp'])
                : now(),
        ]);

        $conversation->unread_count = (int) $conversation->unread_count + 1;
        $this->touchConversation($conversation, $record);
        $this->notifyUnread($conversation, $record);

        if ($conversation->wasRecentlyCreated || FacebookMessage::where('facebook_conversation_id', $conversation->id)->count() === 1) {
            $this->maybeSendWelcome($integration, $conversation);
        }
    }

    protected function maybeSendWelcome(FacebookIntegration $integration, FacebookConversation $conversation): void
    {
        $welcome = trim((string) ($integration->welcome_message ?? ''));
        if ($welcome === '') {
            return;
        }

        try {
            $service = FacebookGraphService::forIntegration($integration);
            $response = $service->sendText($conversation->peer_id, $welcome);
            $message = FacebookMessage::create([
                'company_id' => $integration->company_id,
                'facebook_conversation_id' => $conversation->id,
                'direction' => 'outbound',
                'mid' => isset($response['message_id']) ? (string) $response['message_id'] : null,
                'type' => 'text',
                'text' => $welcome,
                'status' => 'sent',
                'raw_payload' => $response,
                'sent_at' => now(),
            ]);
            $this->touchConversation($conversation, $message);
        } catch (\Throwable $e) {
            Log::warning('Facebook welcome message failed', ['error' => $e->getMessage()]);
        }
    }

    protected function upsertConversation(FacebookIntegration $integration, string $channel, string $peerId): FacebookConversation
    {
        $conversation = FacebookConversation::firstOrNew([
            'company_id' => $integration->company_id,
            'channel' => $channel,
            'peer_id' => $peerId,
        ]);

        if (! $conversation->exists || ! $conversation->name) {
            try {
                $profile = FacebookGraphService::forIntegration($integration)->getUserProfile($peerId, $channel);
                $name = $profile['name']
                    ?? trim(($profile['first_name'] ?? '').' '.($profile['last_name'] ?? ''))
                    ?: ($profile['username'] ?? null);
                $conversation->name = $name ?: $conversation->name;
                $conversation->username = $profile['username'] ?? $conversation->username;
                $conversation->profile_pic = $profile['profile_pic'] ?? $conversation->profile_pic;
            } catch (\Throwable) {
                // Profile lookup is best-effort.
            }
        }

        if (! $conversation->name) {
            $conversation->name = $channel === 'instagram' ? 'Instagram User' : 'Messenger User';
        }

        $conversation->save();

        $this->leadAutoCreate->fromFacebook(
            (int) $integration->company_id,
            $channel,
            $conversation->name,
            $conversation->username
        );

        return $conversation;
    }

    protected function storeInboundMedia(FacebookIntegration $integration, string $url, string $type): string
    {
        $token = $integration->getDecryptedPageAccessToken();
        $downloadUrl = $url;
        if ($token && ! str_contains($url, 'access_token=')) {
            $downloadUrl .= (str_contains($url, '?') ? '&' : '?').'access_token='.urlencode($token);
        }

        $response = Http::timeout(60)->withHeaders([
            'User-Agent' => 'LNSCRM-FacebookMessaging/1.0',
        ])->get($downloadUrl);

        if (! $response->successful()) {
            throw new \RuntimeException('Failed to download media from Meta.');
        }

        $ext = match ($type) {
            'image' => 'jpg',
            'video' => 'mp4',
            'audio' => 'mp3',
            default => 'bin',
        };

        $contentType = strtolower((string) $response->header('Content-Type'));
        if (str_contains($contentType, 'png')) {
            $ext = 'png';
        } elseif (str_contains($contentType, 'webp')) {
            $ext = 'webp';
        } elseif (str_contains($contentType, 'gif')) {
            $ext = 'gif';
        } elseif (str_contains($contentType, 'mp4')) {
            $ext = 'mp4';
        } elseif (str_contains($contentType, 'pdf')) {
            $ext = 'pdf';
        }

        $path = 'facebook/'.$integration->company_id.'/inbound/'.date('Y/m').'/'.Str::random(12).'.'.$ext;
        Storage::disk('public')->put($path, $response->body());

        return public_media_url($path);
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

    protected function requireActiveIntegration(): FacebookIntegration
    {
        $integration = FacebookIntegration::where('company_id', Auth::user()->company_id)
            ->where('is_active', true)
            ->first();

        if (! $integration) {
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
