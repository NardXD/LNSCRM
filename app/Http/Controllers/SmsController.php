<?php

namespace App\Http\Controllers;

use App\Models\InfobipIntegration;
use App\Models\SmsConversation;
use App\Models\SmsMessage;
use App\Services\InfobipCompanyService;
use App\Services\InfobipService;
use App\Services\SmsConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class SmsController extends Controller
{
    public function __construct(
        protected InfobipCompanyService $infobipCompany,
        protected SmsConversationService $conversations
    ) {}

    public function index()
    {
        $user = Auth::user();
        $company = $user?->company;
        $integration = $company
            ? $this->infobipCompany->getActiveIntegration($company)
            : null;

        return view('dashboard.sms', [
            'integrationConnected' => (bool) $integration,
            'phoneSystemNumber' => $user?->phone_system_number,
            'canSendSms' => (bool) $user?->hasPermission('send_sms'),
        ]);
    }

    public function bootstrap(): JsonResponse
    {
        $user = Auth::user();
        $integration = InfobipIntegration::where('company_id', $user->company_id)->first();
        $active = $user->company
            ? $this->infobipCompany->getActiveIntegration($user->company)
            : null;

        return response()->json([
            'connected' => (bool) $active,
            'can_send' => $user->hasPermission('send_sms'),
            'account' => [
                'phone_system_number' => $user->phone_system_number,
                'has_number' => (bool) $user->phone_system_number,
                'is_active' => (bool) ($integration && $integration->is_active),
                'integrations_url' => route('integrations'),
                'phone_system_url' => $this->phoneSystemUrl(),
            ],
        ]);
    }

    public function conversations(Request $request): JsonResponse
    {
        $user = Auth::user();
        $q = trim((string) $request->query('q', ''));

        $query = SmsConversation::query()
            ->where('company_id', $user->company_id)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', '%'.$q.'%')
                    ->orWhere('peer_phone', 'like', '%'.$q.'%')
                    ->orWhere('last_message_preview', 'like', '%'.$q.'%');
            });
        }

        // Non-admins only see threads involving their assigned phone number
        if (! $user->hasPermission('manage_twilio_numbers') && $user->phone_system_number) {
            $mine = $this->infobipCompany->normalizePhone($user->phone_system_number);
            $query->where(function ($builder) use ($mine) {
                $builder->where('our_number', $mine)->orWhereNull('our_number');
            });
        } elseif (! $user->hasPermission('manage_twilio_numbers') && ! $user->phone_system_number) {
            return response()->json(['data' => []]);
        }

        $conversations = $query->limit(100)->get()->map(fn (SmsConversation $c) => $this->formatConversation($c));

        return response()->json(['data' => $conversations]);
    }

    public function messages(SmsConversation $conversation): JsonResponse
    {
        $this->assertCompanyConversation($conversation);

        $messages = SmsMessage::query()
            ->where('sms_conversation_id', $conversation->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(500)
            ->get()
            ->map(fn (SmsMessage $m) => $this->formatMessage($m));

        $conversation->update(['unread_count' => 0]);

        return response()->json([
            'conversation' => $this->formatConversation($conversation->fresh()),
            'data' => $messages,
        ]);
    }

    public function startConversation(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user->hasPermission('send_sms')) {
            return response()->json(['message' => 'You do not have permission to send SMS.'], 403);
        }

        if (! $user->phone_system_number) {
            return response()->json([
                'message' => 'You need an assigned phone number to start an SMS conversation.',
            ], 422);
        }

        $validated = $request->validate([
            'to' => ['required', 'string', 'max:32'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $peer = $this->infobipCompany->normalizePhone($validated['to']);
        $from = $this->infobipCompany->normalizePhone($user->phone_system_number);

        $conversation = $this->conversations->upsert(
            (int) $user->company_id,
            $peer,
            $from,
            $validated['name'] ?? null
        );

        return response()->json(['data' => $this->formatConversation($conversation)], 201);
    }

    public function sendMessage(Request $request, SmsConversation $conversation): JsonResponse
    {
        $this->assertCompanyConversation($conversation);

        $user = Auth::user();
        if (! $user->hasPermission('send_sms')) {
            return response()->json(['message' => 'You do not have permission to send SMS.'], 403);
        }

        if (! $user->phone_system_number) {
            return response()->json([
                'message' => 'You need an assigned phone number to send SMS.',
            ], 422);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:1600'],
        ]);

        $to = $conversation->peer_phone;
        $from = $this->infobipCompany->normalizePhone($user->phone_system_number);

        $company = $user->company;
        $integration = $company ? $this->infobipCompany->getActiveIntegration($company) : null;
        if (! $integration) {
            return response()->json(['message' => 'Infobip is not connected. Configure it under Integrations.'], 422);
        }

        $credentials = $this->infobipCompany->getCredentials($integration);
        if (! $credentials) {
            return response()->json(['message' => 'Invalid Infobip credentials.'], 422);
        }

        try {
            $infobip = new InfobipService($credentials['base_url'], $credentials['api_key']);
            $statusUrl = Route::has('infobip.sms-status')
                ? route('infobip.sms-status')
                : (Route::has('twilio.sms-status') ? route('twilio.sms-status') : url('/infobip/sms-status'));
            $sent = $infobip->sendSms($from, $to, $validated['body'], $statusUrl);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $conversation->our_number = $from;
        $conversation->save();

        $message = SmsMessage::create([
            'company_id' => $conversation->company_id,
            'sms_conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'message_sid' => $sent['messageId'] ?? null,
            'direction' => 'outbound',
            'from_number' => $from,
            'to_number' => $to,
            'body' => $validated['body'],
            'status' => $sent['status'] ?? 'pending',
            'sent_at' => now(),
        ]);

        $this->conversations->touch($conversation, $message);

        return response()->json(['data' => $this->formatMessage($message)], 201);
    }

    public function callLink(SmsConversation $conversation): JsonResponse
    {
        $this->assertCompanyConversation($conversation);

        $phone = preg_replace('/\D+/', '', (string) $conversation->peer_phone);

        return response()->json([
            'data' => [
                'tel' => $phone ? 'tel:+'.$phone : null,
                'has_phone' => (bool) $phone,
            ],
        ]);
    }

    protected function phoneSystemUrl(): string
    {
        if (Route::has('phone.call')) {
            return route('phone.call');
        }

        return url('/twilio/call');
    }

    protected function assertCompanyConversation(SmsConversation $conversation): void
    {
        if ((int) $conversation->company_id !== (int) Auth::user()->company_id) {
            abort(404);
        }
    }

    protected function formatConversation(SmsConversation $c): array
    {
        return [
            'id' => $c->id,
            'peer_phone' => $c->peer_phone,
            'our_number' => $c->our_number,
            'name' => $c->name ?: $c->peer_phone,
            'unread_count' => (int) $c->unread_count,
            'last_message_preview' => $c->last_message_preview,
            'last_message_at' => $c->last_message_at?->toIso8601String(),
        ];
    }

    protected function formatMessage(SmsMessage $m): array
    {
        return [
            'id' => $m->id,
            'direction' => $m->direction,
            'body' => $m->body,
            'status' => $m->status,
            'from_number' => $m->from_number,
            'to_number' => $m->to_number,
            'user_id' => $m->user_id,
            'sent_at' => $m->sent_at?->toIso8601String(),
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }
}
