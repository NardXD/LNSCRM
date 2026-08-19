<?php

namespace App\Http\Controllers;

use App\Models\SmsConversation;
use App\Models\SmsMessage;
use App\Services\FlexCrmLookupService;
use App\Services\LeadAutoCreateService;
use App\Services\SmsConversationService;
use App\Services\TwilioCompanyService;
use App\Services\TwilioService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SmsController extends Controller
{
    public function __construct(
        protected TwilioCompanyService $twilioCompany,
        protected SmsConversationService $conversations,
        protected LeadAutoCreateService $leadAutoCreate,
        protected FlexCrmLookupService $crmLookup
    ) {}

    public function index()
    {
        $user = Auth::user();
        $company = $user?->company;
        $integration = $company
            ? $this->twilioCompany->getActiveIntegration($company)
            : null;

        return view('dashboard.sms', [
            'integrationConnected' => (bool) $integration,
            'twilioNumber' => $user?->twilio_sms_number,
            'canSendSms' => (bool) $user?->hasPermission('send_sms'),
        ]);
    }

    public function bootstrap(): JsonResponse
    {
        $user = Auth::user();
        $active = $user->company
            ? $this->twilioCompany->getActiveIntegration($user->company)
            : null;

        return response()->json([
            'connected' => (bool) $active,
            'can_send' => $user->hasPermission('send_sms'),
            'account' => [
                'twilio_number' => $user->twilio_sms_number,
                'twilio_sms_number' => $user->twilio_sms_number,
                'has_number' => (bool) $user->twilio_sms_number,
                'is_active' => (bool) ($active && $active->is_active),
                'integrations_url' => route('integrations'),
                'phone_system_url' => route('twilio.call'),
            ],
        ]);
    }

    public function conversations(Request $request): JsonResponse
    {
        $user = Auth::user();
        $q = trim((string) $request->query('q', ''));
        $limit = min(max((int) $request->query('limit', 40), 1), 100);
        $beforeId = (int) $request->query('before_id', 0);

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

        // Non-admins only see threads involving their assigned SMS number
        if (! $user->hasPermission('manage_twilio_numbers') && $user->twilio_sms_number) {
            $mine = $this->twilioCompany->normalizePhone($user->twilio_sms_number);
            $query->where(function ($builder) use ($mine) {
                $builder->where('our_number', $mine)->orWhereNull('our_number');
            });
        } elseif (! $user->hasPermission('manage_twilio_numbers') && ! $user->twilio_sms_number) {
            return response()->json(['data' => [], 'has_more' => false]);
        }

        if ($beforeId > 0) {
            $before = SmsConversation::query()
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
            'data' => $rows->map(fn (SmsConversation $c) => $this->formatConversation($c))->values(),
            'has_more' => $hasMore,
        ]);
    }

    public function messages(Request $request, SmsConversation $conversation): JsonResponse
    {
        $this->assertCompanyConversation($conversation);

        $limit = min(max((int) $request->query('limit', 40), 1), 100);
        $beforeId = (int) $request->query('before_id', 0);

        $query = SmsMessage::query()
            ->where('sms_conversation_id', $conversation->id);

        if ($beforeId > 0) {
            $query->where('id', '<', $beforeId);
        }

        $messages = $query
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();

        $hasMore = $messages->count() > $limit;
        if ($hasMore) {
            $messages = $messages->take($limit);
        }

        $messages = $messages->reverse()->values()->map(fn (SmsMessage $m) => $this->formatMessage($m));

        if ($beforeId <= 0) {
            $conversation->update(['unread_count' => 0]);
        }

        return response()->json([
            'conversation' => $this->formatConversation($conversation->fresh()),
            'data' => $messages,
            'has_more' => $hasMore,
        ]);
    }

    public function startConversation(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user->hasPermission('send_sms')) {
            return response()->json(['message' => 'You do not have permission to send SMS.'], 403);
        }

        if (! $user->twilio_sms_number) {
            return response()->json([
                'message' => 'You need an assigned SMS number to start an SMS conversation.',
            ], 422);
        }

        $validated = $request->validate([
            'to' => ['required', 'string', 'max:32'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $peer = $this->twilioCompany->normalizePhone($validated['to']);
        $from = $this->twilioCompany->normalizePhone($user->twilio_sms_number);

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

        if (! $user->twilio_sms_number) {
            return response()->json([
                'message' => 'You need an assigned SMS number to send SMS.',
            ], 422);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:1600'],
        ]);

        $to = $conversation->peer_phone;
        $from = $this->twilioCompany->normalizePhone($user->twilio_sms_number);

        $company = $user->company;
        $integration = $company ? $this->twilioCompany->getActiveIntegration($company) : null;
        if (! $integration) {
            return response()->json(['message' => 'Twilio is not connected. Configure it under Integrations.'], 422);
        }

        $credentials = $this->twilioCompany->getCredentials($integration);
        if (! $credentials) {
            return response()->json(['message' => 'Invalid Twilio credentials.'], 422);
        }

        try {
            $twilio = new TwilioService($credentials['sid'], $credentials['token']);
            $sent = $twilio->sendSms($from, $to, $validated['body'], route('twilio.sms-status'));
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $conversation->our_number = $from;
        $conversation->save();

        $message = SmsMessage::create([
            'company_id' => $conversation->company_id,
            'sms_conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'message_sid' => $sent->sid,
            'direction' => 'outbound',
            'from_number' => $from,
            'to_number' => $to,
            'body' => $validated['body'],
            'status' => $sent->status,
            'sent_at' => now(),
        ]);

        $this->conversations->touch($conversation, $message);

        $this->leadAutoCreate->fromPhoneChannel(
            (int) $conversation->company_id,
            'sms',
            $conversation->peer_phone,
            $conversation->name
        );

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

    protected function assertCompanyConversation(SmsConversation $conversation): void
    {
        if ((int) $conversation->company_id !== (int) Auth::user()->company_id) {
            abort(404);
        }
    }

    protected function constrainConversationsBefore(Builder $query, SmsConversation $before): void
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
            'lead' => $this->crmLookup->matchAssignedLead(
                $this->crmLookup->assignedLeadIndex((int) $c->company_id),
                $c->peer_phone,
                null,
                $c->name
            ),
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
