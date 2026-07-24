<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppConversation;
use App\Models\WhatsAppIntegration;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppCloudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class WhatsAppController extends Controller
{
    public function index()
    {
        $companyId = Auth::user()?->company_id;
        $integration = $companyId
            ? WhatsAppIntegration::where('company_id', $companyId)->where('is_active', true)->first()
            : null;

        return view('dashboard.whatsapp', [
            'integrationConnected' => (bool) $integration,
            'businessName' => $integration?->business_name,
            'displayPhone' => $integration?->display_phone_number,
        ]);
    }

    public function bootstrap(): JsonResponse
    {
        $user = Auth::user();
        $integration = WhatsAppIntegration::where('company_id', $user->company_id)->first();

        return response()->json([
            'connected' => (bool) ($integration && $integration->is_active),
            'account' => $integration ? [
                'business_name' => $integration->business_name,
                'display_phone_number' => $integration->display_phone_number,
                'phone_number_id' => $integration->phone_number_id,
                'webhook_url' => $integration->webhookUrl(),
                'webhook_set_at' => $integration->webhook_set_at?->toIso8601String(),
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

        $conversations = $query->limit(100)->get()->map(fn (WhatsAppConversation $c) => $this->formatConversation($c));

        return response()->json(['data' => $conversations]);
    }

    public function messages(WhatsAppConversation $conversation): JsonResponse
    {
        $this->assertCompanyConversation($conversation);

        $messages = WhatsAppMessage::query()
            ->where('whatsapp_conversation_id', $conversation->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(500)
            ->get()
            ->map(fn (WhatsAppMessage $m) => $this->formatMessage($m));

        $conversation->update(['unread_count' => 0]);

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

        $integration = $this->requireActiveIntegration();
        $service = WhatsAppCloudService::forIntegration($integration);
        $to = $conversation->wa_id;

        try {
            $response = match ($validated['type']) {
                'text' => $service->sendText($to, (string) ($validated['text'] ?? '')),
                'image' => $service->sendImage($to, (string) $validated['media_url'], $validated['text'] ?? null),
                'video' => $service->sendVideo($to, (string) $validated['media_url'], $validated['text'] ?? null),
                'document' => $service->sendDocument(
                    $to,
                    (string) $validated['media_url'],
                    $validated['file_name'] ?? null,
                    $validated['text'] ?? null
                ),
                'audio' => $service->sendAudio($to, (string) $validated['media_url']),
                'location' => $service->sendLocation(
                    $to,
                    (float) $validated['latitude'],
                    (float) $validated['longitude']
                ),
            };
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $wamid = $response['messages'][0]['id'] ?? null;

        $message = WhatsAppMessage::create([
            'company_id' => $conversation->company_id,
            'whatsapp_conversation_id' => $conversation->id,
            'user_id' => Auth::id(),
            'direction' => 'outbound',
            'wamid' => $wamid ? (string) $wamid : null,
            'type' => $validated['type'],
            'text' => $validated['text'] ?? null,
            'media_url' => $validated['media_url'] ?? null,
            'file_name' => $validated['file_name'] ?? null,
            'file_size' => $validated['file_size'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
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
            'file' => ['required', 'file', 'max:65536'], // 64MB
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
                'url' => asset('storage/'.$path),
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

        // Meta webhook verification (GET)
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
            $service = WhatsAppCloudService::forIntegration($integration);
            if ($raw !== '' && $integration->getDecryptedAppSecret() && ! $service->verifySignature($raw, $signature)) {
                Log::warning('WhatsApp webhook signature mismatch', ['company_id' => $integration->company_id]);

                return response('Invalid signature', 403);
            }
        } catch (\Throwable $e) {
            Log::warning('WhatsApp webhook auth error', ['error' => $e->getMessage()]);

            return response('OK', 200);
        }

        $payload = $request->json()->all();

        try {
            foreach ($payload['entry'] ?? [] as $entry) {
                foreach ($entry['changes'] ?? [] as $change) {
                    if (($change['field'] ?? '') !== 'messages') {
                        continue;
                    }
                    $value = $change['value'] ?? [];
                    $this->handleInboundMessages($integration, $value);
                    $this->handleStatusUpdates($integration, $value);
                }
            }
        } catch (\Throwable $e) {
            Log::error('WhatsApp webhook handler error', ['error' => $e->getMessage()]);
        }

        return response('OK', 200);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    protected function handleInboundMessages(WhatsAppIntegration $integration, array $value): void
    {
        $contacts = collect($value['contacts'] ?? [])->keyBy('wa_id');
        $messages = $value['messages'] ?? [];

        if (empty($messages)) {
            return;
        }

        $service = null;

        foreach ($messages as $message) {
            $waId = $message['from'] ?? null;
            $wamid = isset($message['id']) ? (string) $message['id'] : null;
            if (! $waId) {
                continue;
            }

            if ($wamid && WhatsAppMessage::where('wamid', $wamid)->exists()) {
                continue;
            }

            $contact = $contacts->get($waId) ?? [];
            $profileName = $contact['profile']['name'] ?? null;

            $conversation = $this->upsertConversation($integration->company_id, $waId, $profileName);
            $isNewConversation = $conversation->wasRecentlyCreated
                || ! WhatsAppMessage::where('whatsapp_conversation_id', $conversation->id)->exists();
            $conversation->window_expires_at = now()->addHours(24);
            $conversation->is_subscribed = true;

            $type = $message['type'] ?? 'text';
            $text = null;
            $mediaUrl = null;
            $mediaId = null;
            $mimeType = null;
            $fileName = null;
            $latitude = null;
            $longitude = null;
            $contactName = null;
            $contactPhone = null;
            $storedType = $type;

            if ($type === 'text') {
                $text = $message['text']['body'] ?? null;
            } elseif (in_array($type, ['image', 'video', 'audio', 'document', 'sticker'], true)) {
                $media = $message[$type] ?? [];
                $mediaId = isset($media['id']) ? (string) $media['id'] : null;
                $mimeType = $media['mime_type'] ?? null;
                $fileName = $media['filename'] ?? null;
                $text = $media['caption'] ?? null;
                $storedType = $type === 'sticker' ? 'sticker' : $type;

                if ($mediaId) {
                    try {
                        $service ??= WhatsAppCloudService::forIntegration($integration);
                        $mediaUrl = $this->storeInboundMedia($service, $integration->company_id, $mediaId, $fileName, $mimeType);
                    } catch (\Throwable $e) {
                        Log::warning('WhatsApp inbound media download failed', ['error' => $e->getMessage()]);
                    }
                }
            } elseif ($type === 'location') {
                $latitude = $message['location']['latitude'] ?? null;
                $longitude = $message['location']['longitude'] ?? null;
                $text = $message['location']['name'] ?? null;
            } elseif ($type === 'contacts') {
                $first = $message['contacts'][0] ?? [];
                $contactName = $first['name']['formatted_name'] ?? null;
                $contactPhone = $first['phones'][0]['phone'] ?? null;
                $storedType = 'contact';
                if ($contactPhone && ! $conversation->phone) {
                    $conversation->phone = preg_replace('/\D+/', '', (string) $contactPhone);
                }
            } elseif ($type === 'button') {
                $text = $message['button']['text'] ?? ($message['button']['payload'] ?? null);
                $storedType = 'text';
            } elseif ($type === 'interactive') {
                $interactive = $message['interactive'] ?? [];
                $text = $interactive['button_reply']['title']
                    ?? $interactive['list_reply']['title']
                    ?? null;
                $storedType = 'text';
            }

            $record = WhatsAppMessage::create([
                'company_id' => $integration->company_id,
                'whatsapp_conversation_id' => $conversation->id,
                'direction' => 'inbound',
                'wamid' => $wamid,
                'type' => $storedType,
                'text' => $text,
                'media_url' => $mediaUrl,
                'media_id' => $mediaId,
                'mime_type' => $mimeType,
                'file_name' => $fileName,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'contact_name' => $contactName,
                'contact_phone' => $contactPhone,
                'status' => 'received',
                'raw_payload' => $message,
                'sent_at' => isset($message['timestamp'])
                    ? \Carbon\Carbon::createFromTimestamp((int) $message['timestamp'])
                    : now(),
            ]);

            $conversation->unread_count = (int) $conversation->unread_count + 1;
            $this->touchConversation($conversation, $record);

            if ($isNewConversation) {
                $this->maybeSendWelcome($integration, $conversation);
            }
        }
    }

    protected function maybeSendWelcome(WhatsAppIntegration $integration, WhatsAppConversation $conversation): void
    {
        $welcome = trim((string) ($integration->welcome_message ?? ''));
        if ($welcome === '') {
            return;
        }

        try {
            $service = WhatsAppCloudService::forIntegration($integration);
            $response = $service->sendText($conversation->wa_id, $welcome);
            $wamid = $response['messages'][0]['id'] ?? null;

            $message = WhatsAppMessage::create([
                'company_id' => $integration->company_id,
                'whatsapp_conversation_id' => $conversation->id,
                'direction' => 'outbound',
                'wamid' => $wamid ? (string) $wamid : null,
                'type' => 'text',
                'text' => $welcome,
                'status' => 'sent',
                'raw_payload' => $response,
                'sent_at' => now(),
            ]);

            $this->touchConversation($conversation, $message);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp welcome message failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @param  array<string, mixed>  $value
     */
    protected function handleStatusUpdates(WhatsAppIntegration $integration, array $value): void
    {
        foreach ($value['statuses'] ?? [] as $status) {
            $wamid = isset($status['id']) ? (string) $status['id'] : null;
            if (! $wamid) {
                continue;
            }

            WhatsAppMessage::where('company_id', $integration->company_id)
                ->where('wamid', $wamid)
                ->update(['status' => $status['status'] ?? null]);
        }
    }

    protected function storeInboundMedia(
        WhatsAppCloudService $service,
        int $companyId,
        string $mediaId,
        ?string $fileName,
        ?string $mimeType
    ): string {
        $meta = $service->getMediaUrl($mediaId);
        $binary = $service->downloadMedia($meta['url']);

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

        $path = 'whatsapp/'.$companyId.'/inbound/'.date('Y/m').'/'.$mediaId.'-'.Str::random(6).'.'.$ext;
        Storage::disk('public')->put($path, $binary);

        return asset('storage/'.$path);
    }

    protected function upsertConversation(int $companyId, string $waId, ?string $profileName): WhatsAppConversation
    {
        $conversation = WhatsAppConversation::firstOrNew([
            'company_id' => $companyId,
            'wa_id' => $waId,
        ]);

        $conversation->fill([
            'name' => $profileName ?: ($conversation->name ?: $waId),
            'profile_name' => $profileName ?: $conversation->profile_name,
            'phone' => $conversation->phone ?: preg_replace('/\D+/', '', $waId),
            'is_subscribed' => true,
        ]);
        $conversation->save();

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

    protected function requireActiveIntegration(): WhatsAppIntegration
    {
        $integration = WhatsAppIntegration::where('company_id', Auth::user()->company_id)
            ->where('is_active', true)
            ->first();

        if (! $integration) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
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
