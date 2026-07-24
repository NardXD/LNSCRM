<?php

namespace App\Http\Controllers;

use App\Models\ViberConversation;
use App\Models\ViberIntegration;
use App\Models\ViberMessage;
use App\Services\ViberBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ViberController extends Controller
{
    public function index()
    {
        $companyId = Auth::user()?->company_id;
        $integration = $companyId
            ? ViberIntegration::where('company_id', $companyId)->where('is_active', true)->first()
            : null;

        return view('dashboard.viber', [
            'integrationConnected' => (bool) $integration,
            'botName' => $integration?->bot_name,
            'botUri' => $integration?->bot_uri,
            'botShareUrl' => $integration?->bot_uri
                ? 'viber://pa?chatURI='.$integration->bot_uri
                : null,
        ]);
    }

    public function bootstrap(): JsonResponse
    {
        $user = Auth::user();
        $integration = ViberIntegration::where('company_id', $user->company_id)->first();

        return response()->json([
            'connected' => (bool) ($integration && $integration->is_active),
            'bot' => $integration ? [
                'name' => $integration->bot_name,
                'uri' => $integration->bot_uri,
                'avatar' => $integration->bot_avatar,
                'share_url' => $integration->bot_uri
                    ? 'viber://pa?chatURI='.$integration->bot_uri
                    : null,
                'webhook_url' => $integration->webhookUrl(),
                'webhook_set_at' => $integration->webhook_set_at?->toIso8601String(),
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

        $conversations = $query->limit(100)->get()->map(fn (ViberConversation $c) => $this->formatConversation($c));

        return response()->json(['data' => $conversations]);
    }

    public function messages(ViberConversation $conversation): JsonResponse
    {
        $this->assertCompanyConversation($conversation);

        $messages = ViberMessage::query()
            ->where('viber_conversation_id', $conversation->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(500)
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

        if (! $conversation->is_subscribed) {
            return response()->json(['message' => 'This user is not subscribed to your Viber bot.'], 422);
        }

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

        $integration = $this->requireActiveIntegration();
        $service = ViberBotService::forIntegration($integration);
        $senderName = Auth::user()->name ?: ($integration->bot_name ?: 'Support');
        $senderAvatar = $integration->bot_avatar;

        try {
            $response = match ($validated['type']) {
                'text' => $service->sendText(
                    $conversation->viber_user_id,
                    (string) ($validated['text'] ?? ''),
                    $senderName,
                    $senderAvatar
                ),
                'picture' => $service->sendPicture(
                    $conversation->viber_user_id,
                    (string) $validated['media_url'],
                    $validated['text'] ?? null,
                    $validated['thumbnail_url'] ?? null,
                    $senderName,
                    $senderAvatar
                ),
                'video' => $service->sendVideo(
                    $conversation->viber_user_id,
                    (string) $validated['media_url'],
                    (int) ($validated['file_size'] ?? 0),
                    $validated['duration'] ?? null,
                    $validated['thumbnail_url'] ?? null,
                    $senderName,
                    $senderAvatar
                ),
                'file' => $service->sendFile(
                    $conversation->viber_user_id,
                    (string) $validated['media_url'],
                    (int) ($validated['file_size'] ?? 0),
                    (string) ($validated['file_name'] ?? 'file'),
                    $senderName,
                    $senderAvatar
                ),
                'url' => $service->sendUrl(
                    $conversation->viber_user_id,
                    (string) $validated['media_url'],
                    $senderName,
                    $senderAvatar
                ),
                'location' => $service->sendLocation(
                    $conversation->viber_user_id,
                    (float) $validated['latitude'],
                    (float) $validated['longitude'],
                    $senderName,
                    $senderAvatar
                ),
                'contact' => $service->sendContact(
                    $conversation->viber_user_id,
                    (string) ($validated['contact_name'] ?? 'Contact'),
                    (string) ($validated['contact_phone'] ?? ''),
                    $senderName,
                    $senderAvatar
                ),
            };
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $message = ViberMessage::create([
            'company_id' => $conversation->company_id,
            'viber_conversation_id' => $conversation->id,
            'user_id' => Auth::id(),
            'direction' => 'outbound',
            'message_token' => isset($response['message_token']) ? (string) $response['message_token'] : null,
            'type' => $validated['type'],
            'text' => $validated['text'] ?? null,
            'media_url' => $validated['media_url'] ?? null,
            'thumbnail_url' => $validated['thumbnail_url'] ?? null,
            'file_name' => $validated['file_name'] ?? null,
            'file_size' => $validated['file_size'] ?? null,
            'duration' => $validated['duration'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'contact_name' => $validated['contact_name'] ?? null,
            'contact_phone' => $validated['contact_phone'] ?? null,
            'status' => 'sent',
            'raw_payload' => $response,
            'sent_at' => now(),
        ]);

        $this->touchConversation($conversation, $message);

        return response()->json(['data' => $this->formatMessage($message)], 201);
    }

    public function uploadMedia(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:51200'], // 50MB
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

        // Viber requires a URL whose last path segment includes a recognized extension.
        $safeName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'media';
        $path = $file->storeAs(
            'viber/'.Auth::user()->company_id.'/'.date('Y/m'),
            $safeName.'-'.Str::random(8).'.'.$ext,
            'public'
        );

        $url = asset('storage/'.$path);

        return response()->json([
            'data' => [
                'url' => $url,
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

        $phone = preg_replace('/\D+/', '', (string) $conversation->phone);
        $integration = ViberIntegration::where('company_id', Auth::user()->company_id)->first();

        $links = [
            'open_chat' => $integration?->bot_uri
                ? 'viber://pa?chatURI='.$integration->bot_uri
                : null,
            'call' => $phone ? 'viber://chat?number='.$phone : null,
            'tel' => $phone ? 'tel:+'.$phone : null,
            'has_phone' => (bool) $phone,
        ];

        return response()->json(['data' => $links]);
    }

    public function webhook(Request $request, string $webhookKey): Response
    {
        $integration = ViberIntegration::where('webhook_key', $webhookKey)
            ->where('is_active', true)
            ->first();

        if (! $integration) {
            return response('Not found', 404);
        }

        $raw = $request->getContent();
        $signature = $request->header('X-Viber-Content-Signature');

        try {
            $service = ViberBotService::forIntegration($integration);
            if ($raw !== '' && $signature && ! $service->verifySignature($raw, $signature)) {
                Log::warning('Viber webhook signature mismatch', ['company_id' => $integration->company_id]);

                return response('Invalid signature', 403);
            }
        } catch (\Throwable $e) {
            Log::warning('Viber webhook auth error', ['error' => $e->getMessage()]);

            return response('OK', 200);
        }

        $payload = $request->json()->all();
        $event = $payload['event'] ?? null;

        // Webhook availability check from Viber (set_webhook probe)
        if ($event === 'webhook' || $event === null && empty($payload)) {
            return response()->json(['status' => 0, 'status_message' => 'ok']);
        }

        try {
            match ($event) {
                'message' => $this->handleInboundMessage($integration, $payload),
                'subscribed' => $this->handleSubscribed($integration, $payload),
                'unsubscribed' => $this->handleUnsubscribed($integration, $payload),
                'conversation_started' => $this->handleConversationStarted($integration, $payload),
                'delivered', 'seen', 'failed' => $this->handleStatusCallback($integration, $payload),
                default => null,
            };
        } catch (\Throwable $e) {
            Log::error('Viber webhook handler error', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['status' => 0, 'status_message' => 'ok']);
    }

    protected function handleInboundMessage(ViberIntegration $integration, array $payload): void
    {
        $sender = $payload['sender'] ?? [];
        $message = $payload['message'] ?? [];
        $userId = $sender['id'] ?? null;
        if (! $userId) {
            return;
        }

        $conversation = $this->upsertConversation($integration->company_id, $sender, true);
        $type = $message['type'] ?? 'text';
        $token = isset($payload['message_token']) ? (string) $payload['message_token'] : null;

        if ($token && ViberMessage::where('message_token', $token)->exists()) {
            return;
        }

        $location = $message['location'] ?? [];
        $contact = $message['contact'] ?? [];

        $record = ViberMessage::create([
            'company_id' => $integration->company_id,
            'viber_conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'message_token' => $token,
            'type' => $type,
            'text' => $message['text'] ?? null,
            'media_url' => $message['media'] ?? null,
            'thumbnail_url' => $message['thumbnail'] ?? null,
            'file_name' => $message['file_name'] ?? null,
            'file_size' => $message['file_size'] ?? ($message['size'] ?? null),
            'duration' => $message['duration'] ?? null,
            'latitude' => $location['lat'] ?? null,
            'longitude' => $location['lon'] ?? null,
            'contact_name' => $contact['name'] ?? null,
            'contact_phone' => $contact['phone_number'] ?? null,
            'sticker_id' => isset($message['sticker_id']) ? (string) $message['sticker_id'] : null,
            'status' => 'received',
            'raw_payload' => $payload,
            'sent_at' => now(),
        ]);

        if (! empty($contact['phone_number']) && ! $conversation->phone) {
            $conversation->phone = preg_replace('/\D+/', '', (string) $contact['phone_number']);
        }

        $conversation->unread_count = (int) $conversation->unread_count + 1;
        $this->touchConversation($conversation, $record);
    }

    protected function handleSubscribed(ViberIntegration $integration, array $payload): void
    {
        $user = $payload['user'] ?? [];
        if (empty($user['id'])) {
            return;
        }

        $this->upsertConversation($integration->company_id, $user, true);
    }

    protected function handleUnsubscribed(ViberIntegration $integration, array $payload): void
    {
        $userId = $payload['user_id'] ?? null;
        if (! $userId) {
            return;
        }

        ViberConversation::where('company_id', $integration->company_id)
            ->where('viber_user_id', $userId)
            ->update(['is_subscribed' => false]);
    }

    protected function handleConversationStarted(ViberIntegration $integration, array $payload): void
    {
        $user = $payload['user'] ?? [];
        if (empty($user['id'])) {
            return;
        }

        $conversation = $this->upsertConversation(
            $integration->company_id,
            $user,
            (bool) ($payload['subscribed'] ?? false)
        );

        $welcome = trim((string) ($integration->welcome_message ?? ''));
        if ($welcome === '') {
            return;
        }

        try {
            $service = ViberBotService::forIntegration($integration);
            $response = $service->sendText(
                $conversation->viber_user_id,
                $welcome,
                $integration->bot_name ?: 'Support',
                $integration->bot_avatar
            );

            $message = ViberMessage::create([
                'company_id' => $integration->company_id,
                'viber_conversation_id' => $conversation->id,
                'direction' => 'outbound',
                'message_token' => isset($response['message_token']) ? (string) $response['message_token'] : null,
                'type' => 'text',
                'text' => $welcome,
                'status' => 'sent',
                'raw_payload' => $response,
                'sent_at' => now(),
            ]);

            $this->touchConversation($conversation, $message);
        } catch (\Throwable $e) {
            Log::warning('Viber welcome message failed', ['error' => $e->getMessage()]);
        }
    }

    protected function handleStatusCallback(ViberIntegration $integration, array $payload): void
    {
        $token = isset($payload['message_token']) ? (string) $payload['message_token'] : null;
        if (! $token) {
            return;
        }

        ViberMessage::where('company_id', $integration->company_id)
            ->where('message_token', $token)
            ->update(['status' => $payload['event'] ?? null]);
    }

    protected function upsertConversation(int $companyId, array $user, bool $subscribed): ViberConversation
    {
        $conversation = ViberConversation::firstOrNew([
            'company_id' => $companyId,
            'viber_user_id' => $user['id'],
        ]);

        $conversation->fill([
            'name' => $user['name'] ?? $conversation->name,
            'avatar' => $user['avatar'] ?? $conversation->avatar,
            'language' => $user['language'] ?? $conversation->language,
            'country' => $user['country'] ?? $conversation->country,
            'is_subscribed' => $subscribed,
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

    protected function requireActiveIntegration(): ViberIntegration
    {
        $integration = ViberIntegration::where('company_id', Auth::user()->company_id)
            ->where('is_active', true)
            ->first();

        if (! $integration) {
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
