<?php

namespace App\Http\Controllers\Twilio;

use App\Http\Controllers\Controller;
use App\Models\PhoneCallLog;
use App\Models\PhoneContact;
use App\Models\SmsMessage;
use App\Models\TwilioPhoneNumber;
use App\Models\User;
use App\Models\ViberMessage;
use App\Models\WhatsAppMessage;
use App\Services\PhoneCallLogService;
use App\Services\SmsConversationService;
use App\Services\TwilioCompanyService;
use App\Services\TwilioNumberAssignmentService;
use App\Services\TwilioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class PhoneSystemController extends Controller
{
    public function __construct(
        protected TwilioCompanyService $twilioCompany,
        protected PhoneCallLogService $callLogService,
        protected TwilioNumberAssignmentService $numberAssignment,
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
            'phone' => $this->twilioCompany->normalizePhone($validated['phone']),
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
            'phone' => $this->twilioCompany->normalizePhone($validated['phone']),
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
        $numbers = TwilioPhoneNumber::query()
            ->where('company_id', $user->company_id)
            ->with('assignedUser:id,name,email')
            ->orderBy('phone_number')
            ->get()
            ->map(fn (TwilioPhoneNumber $n) => $this->formatNumber($n));

        return response()->json(['success' => true, 'data' => $numbers]);
    }

    public function searchAvailableNumbers(Request $request): JsonResponse
    {
        $client = $this->clientOrFail();

        $validated = $request->validate([
            'country' => ['nullable', 'string', 'size:2'],
            'area_code' => ['nullable', 'string', 'max:10'],
        ]);

        $twilio = new TwilioService(
            ...array_values($this->credentialsOrFail())
        );

        $numbers = $twilio->searchAvailableNumbers(
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

        $normalized = $this->twilioCompany->normalizePhone($validated['phone_number']);

        $exists = TwilioPhoneNumber::query()
            ->where('company_id', $user->company_id)
            ->where('phone_number', $normalized)
            ->exists();
        if ($exists) {
            return response()->json(['success' => false, 'message' => 'Number already in inventory.'], 422);
        }

        $credentials = $this->credentialsOrFail();
        $twilio = new TwilioService($credentials['sid'], $credentials['token']);

        $purchased = $twilio->purchaseNumber(
            $normalized,
            route('twilio.voice'),
            route('twilio.sms-webhook')
        );

        $record = TwilioPhoneNumber::query()->create([
            'company_id' => $user->company_id,
            'phone_number' => $purchased->phoneNumber,
            'twilio_sid' => $purchased->sid,
            'friendly_name' => $purchased->friendlyName,
            'capabilities' => [
                'voice' => (bool) ($purchased->capabilities->voice ?? true),
                'sms' => (bool) ($purchased->capabilities->sms ?? false),
            ],
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
        $credentials = $this->credentialsOrFail();
        $twilio = new TwilioService($credentials['sid'], $credentials['token']);
        $owned = $twilio->listOwnedNumbers();

        $synced = 0;
        $voiceUrl = route('twilio.voice');
        $smsUrl = route('twilio.sms-webhook');

        foreach ($owned as $item) {
            $normalized = $this->twilioCompany->normalizePhone($item['phone_number']);
            TwilioPhoneNumber::query()->updateOrCreate(
                [
                    'company_id' => $user->company_id,
                    'phone_number' => $normalized,
                ],
                [
                    'twilio_sid' => $item['sid'],
                    'friendly_name' => $item['friendly_name'],
                    'capabilities' => $item['capabilities'],
                ]
            );

            if (! empty($item['sid'])) {
                $twilio->updateNumberWebhooks($item['sid'], $voiceUrl, $smsUrl);
            }

            $synced++;
        }

        return response()->json([
            'success' => true,
            'message' => "Synced {$synced} number(s) from Twilio and updated webhooks.",
        ]);
    }

    public function assignNumber(Request $request, TwilioPhoneNumber $twilioPhoneNumber): JsonResponse
    {
        $user = Auth::user();
        if ((int) $twilioPhoneNumber->company_id !== (int) $user->company_id) {
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
            $this->numberAssignment->assignInventoryRecord($twilioPhoneNumber, $employee);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatNumber($twilioPhoneNumber->fresh('assignedUser')),
        ]);
    }

    public function unassignNumber(TwilioPhoneNumber $twilioPhoneNumber): JsonResponse
    {
        $user = Auth::user();
        if ((int) $twilioPhoneNumber->company_id !== (int) $user->company_id) {
            return response()->json(['success' => false, 'message' => 'Number not found.'], 404);
        }

        $this->numberAssignment->unassignInventoryRecord($twilioPhoneNumber);

        return response()->json([
            'success' => true,
            'data' => $this->formatNumber($twilioPhoneNumber->fresh()),
        ]);
    }

    public function employeesForAssignment(Request $request): JsonResponse
    {
        $user = Auth::user();
        $employees = User::query()
            ->where('company_id', $user->company_id)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'twilio_number'])
            ->map(fn (User $e) => [
                'id' => $e->id,
                'name' => $e->name,
                'email' => $e->email,
                'twilio_number' => $e->twilio_number,
            ]);

        return response()->json(['success' => true, 'data' => $employees]);
    }

    public function smsMessages(Request $request): JsonResponse
    {
        $user = Auth::user();
        $companyId = (int) $user->company_id;
        $myNumber = $user->twilio_number;

        $query = SmsMessage::query()
            ->where('company_id', $companyId)
            ->orderByDesc('created_at');

        if ($request->filled('peer') && $myNumber) {
            $peer = $this->twilioCompany->normalizePhone($request->peer);
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
        $myNumber = $user->twilio_number;
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
        if (! $user->twilio_number) {
            return response()->json([
                'success' => false,
                'message' => 'You need an assigned Twilio number to send SMS.',
            ], 422);
        }

        $validated = $request->validate([
            'to' => ['required', 'string', 'max:32'],
            'body' => ['required', 'string', 'max:1600'],
        ]);

        $to = $this->twilioCompany->normalizePhone($validated['to']);
        $from = $this->twilioCompany->normalizePhone($user->twilio_number);

        $credentials = $this->credentialsOrFail();
        $twilio = new TwilioService($credentials['sid'], $credentials['token']);

        $sent = $twilio->sendSms($from, $to, $validated['body'], route('twilio.sms-status'));

        $conversation = $this->smsConversations->upsert(
            (int) $user->company_id,
            $to,
            $from
        );

        $record = SmsMessage::query()->create([
            'company_id' => $user->company_id,
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

        $this->smsConversations->touch($conversation, $record);

        return response()->json([
            'success' => true,
            'data' => $this->formatSms($record),
        ], 201);
    }

    public function smsWebhook(Request $request)
    {
        $from = $request->input('From');
        $to = $request->input('To');
        $body = $request->input('Body') ?? '';
        $messageSid = $request->input('MessageSid');
        $accountSid = $request->input('AccountSid');

        if (! $messageSid) {
            return response('OK', 200);
        }

        $company = $this->twilioCompany->resolveCompanyFromWebhook($accountSid, $to, $from);
        if (! $company) {
            Log::warning('SMS webhook: company not resolved', ['to' => $to, 'from' => $from]);

            return response('OK', 200);
        }

        $user = $this->twilioCompany->resolveUserFromNumbers($to, $from, 'inbound');

        $conversation = $this->smsConversations->upsert(
            (int) $company->id,
            (string) $from,
            (string) $to
        );

        $record = SmsMessage::query()->updateOrCreate(
            ['message_sid' => $messageSid],
            [
                'company_id' => $company->id,
                'sms_conversation_id' => $conversation->id,
                'user_id' => $user?->id,
                'direction' => 'inbound',
                'from_number' => $from,
                'to_number' => $to,
                'body' => $body,
                'status' => $request->input('SmsStatus', 'received'),
                'sent_at' => now(),
            ]
        );

        $this->smsConversations->touch($conversation, $record, $record->wasRecentlyCreated);

        return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200)
            ->header('Content-Type', 'text/xml');
    }

    public function smsStatus(Request $request)
    {
        $messageSid = $request->input('MessageSid');
        $status = $request->input('MessageStatus');

        if ($messageSid && $status) {
            SmsMessage::query()->where('message_sid', $messageSid)->update(['status' => $status]);
            WhatsAppMessage::query()->where('wamid', $messageSid)->update(['status' => $status]);
            ViberMessage::query()->where('message_token', $messageSid)->update(['status' => $status]);
        }

        return response('OK', 200);
    }

    private function authorizeContact(PhoneContact $contact): void
    {
        $user = Auth::user();
        if ((int) $contact->company_id !== (int) $user->company_id) {
            abort(404);
        }
    }

    /**
     * @return array{sid: string, token: string}
     */
    private function credentialsOrFail(): array
    {
        $user = Auth::user();
        $company = $user->company;
        $integration = $this->twilioCompany->getActiveIntegration($company);
        if (! $integration) {
            abort(response()->json(['success' => false, 'message' => 'Twilio not configured.'], 500));
        }

        $credentials = $this->twilioCompany->getCredentials($integration);
        if (! $credentials) {
            abort(response()->json(['success' => false, 'message' => 'Invalid Twilio credentials.'], 500));
        }

        return $credentials;
    }

    private function clientOrFail(): \Twilio\Rest\Client
    {
        $user = Auth::user();
        $client = $this->twilioCompany->getClientForCompany($user->company);
        if (! $client) {
            abort(response()->json(['success' => false, 'message' => 'Twilio not configured.'], 500));
        }

        return $client;
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

    private function formatNumber(TwilioPhoneNumber $number): array
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
