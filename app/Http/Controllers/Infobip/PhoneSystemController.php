<?php

namespace App\Http\Controllers\Infobip;

use App\Http\Controllers\Controller;
use App\Models\InfobipPhoneNumber;
use App\Models\PhoneCallLog;
use App\Models\PhoneContact;
use App\Models\SmsMessage;
use App\Models\User;
use App\Models\ViberMessage;
use App\Models\WhatsAppMessage;
use App\Services\InfobipCompanyService;
use App\Services\InfobipNumberAssignmentService;
use App\Services\InfobipService;
use App\Services\PhoneCallLogService;
use App\Services\SmsConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class PhoneSystemController extends Controller
{
    public function __construct(
        protected InfobipCompanyService $infobipCompany,
        protected PhoneCallLogService $callLogService,
        protected InfobipNumberAssignmentService $numberAssignment,
        protected SmsConversationService $smsConversations
    ) {}

    public function callHistory(Request $request): JsonResponse
    {
        $user = Auth::user();
        $companyId = (int) $user->company_id;

        $query = PhoneCallLog::query()
            ->where('company_id', $companyId)
            ->orderByDesc('created_at');

        if (! $user->hasPermission('manage_twilio_numbers')) {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('direction') && $request->direction !== 'all') {
            $query->where('direction', 'like', '%'.$request->direction.'%');
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $logs = $query->limit(100)->get()->map(fn (PhoneCallLog $log) => $this->formatCallLog($log));

        return response()->json(['success' => true, 'data' => $logs]);
    }

    public function contacts(Request $request): JsonResponse
    {
        $user = Auth::user();
        $contacts = PhoneContact::query()
            ->where('company_id', $user->company_id)
            ->orderBy('name')
            ->get()
            ->map(fn (PhoneContact $c) => $this->formatContact($c));

        return response()->json(['success' => true, 'data' => $contacts]);
    }

    public function storeContact(Request $request): JsonResponse
    {
        $user = Auth::user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $contact = PhoneContact::query()->create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'name' => $validated['name'],
            'phone' => $this->infobipCompany->normalizePhone($validated['phone']),
            'email' => $validated['email'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->formatContact($contact),
        ], 201);
    }

    public function updateContact(Request $request, PhoneContact $phoneContact): JsonResponse
    {
        $this->authorizeContact($phoneContact);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $phoneContact->update([
            'name' => $validated['name'],
            'phone' => $this->infobipCompany->normalizePhone($validated['phone']),
            'email' => $validated['email'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->formatContact($phoneContact->fresh()),
        ]);
    }

    public function destroyContact(PhoneContact $phoneContact): JsonResponse
    {
        $this->authorizeContact($phoneContact);
        $phoneContact->delete();

        return response()->json(['success' => true]);
    }

    public function numbers(Request $request): JsonResponse
    {
        $user = Auth::user();
        $numbers = InfobipPhoneNumber::query()
            ->where('company_id', $user->company_id)
            ->with('assignedUser:id,name,email')
            ->orderBy('phone_number')
            ->get()
            ->map(fn (InfobipPhoneNumber $n) => $this->formatNumber($n));

        return response()->json(['success' => true, 'data' => $numbers]);
    }

    public function searchAvailableNumbers(Request $request): JsonResponse
    {
        $infobip = $this->serviceOrFail();

        $validated = $request->validate([
            'country' => ['nullable', 'string', 'size:2'],
            'area_code' => ['nullable', 'string', 'max:10'],
        ]);

        $numbers = $infobip->searchAvailableNumbers(
            $validated['country'] ?? 'US',
            $validated['area_code'] ?? null,
            15
        );

        return response()->json(['success' => true, 'data' => $numbers]);
    }

    public function purchaseNumber(Request $request): JsonResponse
    {
        $user = Auth::user();
        $validated = $request->validate([
            'phone_number' => ['required', 'string', 'max:20'],
        ]);

        $normalized = $this->infobipCompany->normalizePhone($validated['phone_number']);

        $exists = InfobipPhoneNumber::query()
            ->where('company_id', $user->company_id)
            ->where('phone_number', $normalized)
            ->exists();
        if ($exists) {
            return response()->json(['success' => false, 'message' => 'Number already in inventory.'], 422);
        }

        $infobip = $this->serviceOrFail();
        $purchased = $infobip->purchaseNumber($normalized);

        $record = InfobipPhoneNumber::query()->create([
            'company_id' => $user->company_id,
            'phone_number' => $purchased['phone_number'],
            'infobip_number_id' => $purchased['sid'],
            'friendly_name' => $purchased['friendly_name'],
            'capabilities' => $purchased['capabilities'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Phone number purchased successfully.',
            'data' => $this->formatNumber($record),
        ], 201);
    }

    public function syncNumbers(Request $request): JsonResponse
    {
        $user = Auth::user();
        $infobip = $this->serviceOrFail();
        $owned = $infobip->listOwnedNumbers();

        $synced = 0;

        foreach ($owned as $item) {
            $normalized = $this->infobipCompany->normalizePhone($item['phone_number']);
            InfobipPhoneNumber::query()->updateOrCreate(
                [
                    'company_id' => $user->company_id,
                    'phone_number' => $normalized,
                ],
                [
                    'infobip_number_id' => $item['sid'],
                    'friendly_name' => $item['friendly_name'],
                    'capabilities' => $item['capabilities'],
                ]
            );

            $synced++;
        }

        return response()->json([
            'success' => true,
            'message' => "Synced {$synced} number(s) from Infobip.",
        ]);
    }

    public function assignNumber(Request $request, InfobipPhoneNumber $infobipPhoneNumber): JsonResponse
    {
        $user = Auth::user();
        if ((int) $infobipPhoneNumber->company_id !== (int) $user->company_id) {
            return response()->json(['success' => false, 'message' => 'Number not found.'], 404);
        }

        $validated = $request->validate([
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('company_id', $user->company_id),
            ],
        ]);

        $employee = User::query()
            ->where('company_id', $user->company_id)
            ->where('id', $validated['user_id'])
            ->first();

        if (! $employee) {
            return response()->json(['success' => false, 'message' => 'Employee not found.'], 404);
        }

        try {
            $this->numberAssignment->assignInventoryRecord($infobipPhoneNumber, $employee);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatNumber($infobipPhoneNumber->fresh('assignedUser')),
        ]);
    }

    public function unassignNumber(InfobipPhoneNumber $infobipPhoneNumber): JsonResponse
    {
        $user = Auth::user();
        if ((int) $infobipPhoneNumber->company_id !== (int) $user->company_id) {
            return response()->json(['success' => false, 'message' => 'Number not found.'], 404);
        }

        $this->numberAssignment->unassignInventoryRecord($infobipPhoneNumber);

        return response()->json([
            'success' => true,
            'data' => $this->formatNumber($infobipPhoneNumber->fresh()),
        ]);
    }

    public function employeesForAssignment(Request $request): JsonResponse
    {
        $user = Auth::user();
        $employees = User::query()
            ->where('company_id', $user->company_id)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone_system_number'])
            ->map(fn (User $e) => [
                'id' => $e->id,
                'name' => $e->name,
                'email' => $e->email,
                'phone_system_number' => $e->phone_system_number,
                // Keep legacy key so existing phone-panel UI keeps working
                'twilio_number' => $e->phone_system_number,
            ]);

        return response()->json(['success' => true, 'data' => $employees]);
    }

    public function smsMessages(Request $request): JsonResponse
    {
        $user = Auth::user();
        $companyId = (int) $user->company_id;
        $myNumber = $user->phone_system_number;

        $query = SmsMessage::query()
            ->where('company_id', $companyId)
            ->orderByDesc('created_at');

        if ($request->filled('peer') && $myNumber) {
            $peer = $this->infobipCompany->normalizePhone($request->peer);
            $query->where(function ($q) use ($myNumber, $peer) {
                $q->where(function ($inner) use ($myNumber, $peer) {
                    $inner->where('from_number', $myNumber)->where('to_number', $peer);
                })->orWhere(function ($inner) use ($myNumber, $peer) {
                    $inner->where('from_number', $peer)->where('to_number', $myNumber);
                });
            });
        }

        $messages = $query->limit(200)->get()->map(fn (SmsMessage $m) => $this->formatSms($m));

        return response()->json(['success' => true, 'data' => $messages]);
    }

    public function smsThreads(Request $request): JsonResponse
    {
        $user = Auth::user();
        $myNumber = $user->phone_system_number;
        if (! $myNumber) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $messages = SmsMessage::query()
            ->where('company_id', $user->company_id)
            ->where(function ($q) use ($myNumber) {
                $q->where('from_number', $myNumber)->orWhere('to_number', $myNumber);
            })
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();

        $threads = [];
        foreach ($messages as $message) {
            $peer = $message->from_number === $myNumber ? $message->to_number : $message->from_number;
            if (! isset($threads[$peer])) {
                $threads[$peer] = [
                    'peer' => $peer,
                    'last_message' => $message->body,
                    'last_at' => $message->created_at?->toIso8601String(),
                    'direction' => $message->direction,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => array_values($threads),
        ]);
    }

    public function sendSms(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user->phone_system_number) {
            return response()->json([
                'success' => false,
                'message' => 'You need an assigned phone number to send SMS.',
            ], 422);
        }

        $validated = $request->validate([
            'to' => ['required', 'string', 'max:32'],
            'body' => ['required', 'string', 'max:1600'],
        ]);

        $to = $this->infobipCompany->normalizePhone($validated['to']);
        $from = $this->infobipCompany->normalizePhone($user->phone_system_number);

        $infobip = $this->serviceOrFail();
        $sent = $infobip->sendSms($from, $to, $validated['body'], route('infobip.sms-status'));

        $conversation = $this->smsConversations->upsert(
            (int) $user->company_id,
            $to,
            $from
        );

        $record = SmsMessage::query()->create([
            'company_id' => $user->company_id,
            'sms_conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'message_sid' => $sent['messageId'],
            'direction' => 'outbound',
            'from_number' => $from,
            'to_number' => $to,
            'body' => $validated['body'],
            'status' => $sent['status'] ?? 'pending',
            'sent_at' => now(),
        ]);

        $this->smsConversations->touch($conversation, $record);

        return response()->json([
            'success' => true,
            'data' => $this->formatSms($record),
        ], 201);
    }

    public function smsWebhook(Request $request)
    {
        $results = $request->input('results');

        if (is_array($results) && count($results) > 0) {
            foreach ($results as $result) {
                if (! is_array($result)) {
                    continue;
                }

                $this->storeInboundSms(
                    messageId: $result['messageId'] ?? $result['message_id'] ?? null,
                    from: $result['from'] ?? null,
                    to: $result['to'] ?? null,
                    body: (string) ($result['text'] ?? $result['cleanText'] ?? ''),
                    status: is_array($result['status'] ?? null)
                        ? strtolower((string) ($result['status']['name'] ?? $result['status']['groupName'] ?? 'received'))
                        : (string) ($result['status'] ?? 'received')
                );
            }

            return response('OK', 200);
        }

        // Form-field / Twilio-shaped fallback
        $this->storeInboundSms(
            messageId: $request->input('messageId')
                ?? $request->input('MessageSid')
                ?? $request->input('message_id'),
            from: $request->input('from') ?? $request->input('From'),
            to: $request->input('to') ?? $request->input('To'),
            body: (string) ($request->input('text') ?? $request->input('Body') ?? ''),
            status: (string) ($request->input('status') ?? $request->input('SmsStatus') ?? 'received')
        );

        return response('OK', 200);
    }

    public function smsStatus(Request $request)
    {
        $results = $request->input('results');

        if (is_array($results) && count($results) > 0) {
            foreach ($results as $result) {
                if (! is_array($result)) {
                    continue;
                }

                $messageId = $result['messageId'] ?? $result['message_id'] ?? null;
                $status = null;
                if (is_array($result['status'] ?? null)) {
                    $status = $result['status']['name'] ?? $result['status']['groupName'] ?? null;
                } else {
                    $status = $result['status'] ?? null;
                }

                $this->applyDeliveryStatus($messageId, $status);
            }

            return response('OK', 200);
        }

        $messageId = $request->input('messageId')
            ?? $request->input('MessageSid')
            ?? $request->input('message_id');
        $status = $request->input('status')
            ?? $request->input('MessageStatus');

        if (is_array($status)) {
            $status = $status['name'] ?? $status['groupName'] ?? null;
        }

        $this->applyDeliveryStatus($messageId, $status);

        return response('OK', 200);
    }

    private function storeInboundSms(?string $messageId, ?string $from, ?string $to, string $body, string $status): void
    {
        if (! $messageId) {
            return;
        }

        $fromNormalized = $from ? $this->infobipCompany->normalizePhone($from) : null;
        $toNormalized = $to ? $this->infobipCompany->normalizePhone($to) : null;

        $company = $this->infobipCompany->resolveCompanyFromNumber($toNormalized, $fromNormalized);
        if (! $company) {
            Log::warning('SMS webhook: company not resolved', ['to' => $to, 'from' => $from]);

            return;
        }

        $user = $this->infobipCompany->resolveUserFromNumbers($toNormalized, $fromNormalized, 'inbound');

        $conversation = $this->smsConversations->upsert(
            (int) $company->id,
            (string) ($fromNormalized ?? $from),
            (string) ($toNormalized ?? $to)
        );

        $record = SmsMessage::query()->updateOrCreate(
            ['message_sid' => $messageId],
            [
                'company_id' => $company->id,
                'sms_conversation_id' => $conversation->id,
                'user_id' => $user?->id,
                'direction' => 'inbound',
                'from_number' => $fromNormalized ?? $from,
                'to_number' => $toNormalized ?? $to,
                'body' => $body,
                'status' => strtolower($status) ?: 'received',
                'sent_at' => now(),
            ]
        );

        $this->smsConversations->touch($conversation, $record, $record->wasRecentlyCreated);
    }

    private function applyDeliveryStatus(?string $messageId, mixed $status): void
    {
        if (! $messageId || ! $status) {
            return;
        }

        $normalized = strtolower((string) $status);

        SmsMessage::query()->where('message_sid', $messageId)->update(['status' => $normalized]);
        WhatsAppMessage::query()->where('wamid', $messageId)->update(['status' => $normalized]);
        ViberMessage::query()->where('message_token', $messageId)->update(['status' => $normalized]);
    }

    private function authorizeContact(PhoneContact $contact): void
    {
        $user = Auth::user();
        if ((int) $contact->company_id !== (int) $user->company_id) {
            abort(404);
        }
    }

    private function serviceOrFail(): InfobipService
    {
        $user = Auth::user();
        $service = $this->infobipCompany->getServiceForCompany($user->company);
        if (! $service) {
            abort(response()->json(['success' => false, 'message' => 'Infobip not configured.'], 500));
        }

        return $service;
    }

    private function formatCallLog(PhoneCallLog $log): array
    {
        return [
            'id' => $log->id,
            'call_sid' => $log->call_sid,
            'direction' => $log->direction,
            'from' => $log->from_number,
            'to' => $log->to_number,
            'status' => $log->status,
            'duration' => $log->duration,
            'started_at' => $log->started_at?->toIso8601String(),
            'ended_at' => $log->ended_at?->toIso8601String(),
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }

    private function formatContact(PhoneContact $contact): array
    {
        return [
            'id' => $contact->id,
            'name' => $contact->name,
            'phone' => $contact->phone,
            'email' => $contact->email,
            'notes' => $contact->notes,
        ];
    }

    private function formatNumber(InfobipPhoneNumber $number): array
    {
        return [
            'id' => $number->id,
            'phone_number' => $number->phone_number,
            'friendly_name' => $number->friendly_name,
            'capabilities' => $number->capabilities,
            'assigned_user_id' => $number->assigned_user_id,
            'assigned_user_name' => $number->assignedUser?->name,
        ];
    }

    private function formatSms(SmsMessage $message): array
    {
        return [
            'id' => $message->id,
            'message_sid' => $message->message_sid,
            'direction' => $message->direction,
            'from' => $message->from_number,
            'to' => $message->to_number,
            'body' => $message->body,
            'status' => $message->status,
            'sent_at' => $message->sent_at?->toIso8601String(),
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }
}
