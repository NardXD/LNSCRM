<?php

namespace App\Http\Controllers\Infobip;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\InfobipIntegration;
use App\Services\InfobipCompanyService;
use App\Services\PhoneCallLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CallController extends Controller
{
    public function __construct(
        protected InfobipCompanyService $infobipCompany,
        protected PhoneCallLogService $callLogService
    ) {}

    public function index(Request $request)
    {
        if (Auth::check()) {
            $user = Auth::user();
            $phoneNumber = $user->phone_system_number ?? null;

            $company = $this->getCompany($request);
            $hasIntegration = false;
            $integrationError = null;

            if ($company) {
                $integration = $this->infobipCompany->getActiveIntegration($company);

                if ($integration) {
                    $hasIntegration = true;
                } else {
                    $anyIntegration = InfobipIntegration::query()
                        ->where('company_id', $company->id)
                        ->first();
                    if ($anyIntegration) {
                        $integrationError = 'Infobip integration is inactive or incomplete. Please configure it in the Integrations page.';
                    } else {
                        $integrationError = 'Infobip integration not configured. Please configure your Infobip credentials in the Integrations page.';
                    }
                }
            } else {
                $integrationError = 'Company not found. Please ensure you are accessing from the correct subdomain.';
            }

            return view('twilio.call', [
                'twilioNumber' => $phoneNumber,
                'hasIntegration' => $hasIntegration,
                'integrationError' => $integrationError,
                'canManageNumbers' => $user->hasPermission('manage_twilio_numbers'),
                'canSendSms' => $user->hasPermission('send_sms'),
                'canViewSms' => $user->hasPermission('view_sms'),
                'canManageContacts' => $user->hasPermission('manage_phone_contacts'),
            ]);
        }

        return redirect()->route('login');
    }

    public function call(Request $request)
    {
        try {
            $phoneNumber = $request->input('phone', '+639957802471');

            Log::info('Initiating Infobip call', [
                'phone_number' => $phoneNumber,
                'user_id' => Auth::id(),
            ]);

            $user = Auth::user();
            $fromNumber = $user->phone_system_number ?? null;

            $company = $this->getCompany($request);
            if (! $company) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company not found. Please ensure you are accessing from the correct subdomain.',
                    'call_sid' => null,
                ], 500);
            }

            $integration = $this->infobipCompany->getActiveIntegration($company);
            if (! $integration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Infobip integration not configured. Please configure your Infobip credentials in the Integrations page.',
                    'call_sid' => null,
                ], 500);
            }

            if (! $integration->application_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Application ID is required for voice calls. Please add it in the Integrations page.',
                    'call_sid' => null,
                ], 500);
            }

            $service = $this->infobipCompany->getServiceForIntegration($integration);
            if (! $service) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to load Infobip credentials. Please reconfigure your integration.',
                    'call_sid' => null,
                ], 500);
            }

            if (! $fromNumber || empty(trim($fromNumber))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phone number is required. Please set your phone system number in your profile to make calls.',
                    'call_sid' => null,
                ], 500);
            }

            $fromNumber = $this->infobipCompany->normalizePhone(trim($fromNumber));
            if (! preg_match('/^\+[1-9]\d{1,14}$/', $fromNumber)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid phone number format. Please ensure your number is in E.164 format (e.g., +1234567890).',
                    'call_sid' => null,
                ], 500);
            }

            $toNumber = $this->infobipCompany->normalizePhone($phoneNumber);
            $webhookUrl = route('infobip.status-callback');

            $result = $service->makeCall(
                $fromNumber,
                $toNumber,
                $integration->application_id,
                $webhookUrl
            );

            $callId = $result['callId'] ?? null;
            if (! $callId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Infobip did not return a call ID.',
                    'call_sid' => null,
                ], 500);
            }

            $this->callLogService->recordOutbound(
                (int) $company->id,
                (int) $user->id,
                $callId,
                $fromNumber,
                $toNumber
            );

            Log::info('Infobip call initiated successfully', [
                'call_id' => $callId,
                'phone_number' => $toNumber,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Call started!',
                'call_sid' => $callId,
                'status' => $result['raw']['status'] ?? 'initiated',
                'phone_number' => $toNumber,
            ]);
        } catch (\Exception $e) {
            Log::error('Infobip call error', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'phone_number' => $request->input('phone'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to make call: '.$e->getMessage(),
                'call_sid' => null,
            ], 500);
        }
    }

    public function statusCallback(Request $request, PhoneCallLogService $callLogService)
    {
        $events = $this->extractCallEvents($request);

        foreach ($events as $event) {
            $mapped = $this->mapInfobipCallEventToLogPayload($event);
            if (! $mapped) {
                continue;
            }

            Log::info('Infobip call status', [
                'call_sid' => $mapped['CallSid'],
                'status' => $mapped['CallStatus'],
                'from' => $mapped['From'],
                'to' => $mapped['To'],
                'direction' => $mapped['Direction'],
                'duration' => $mapped['CallDuration'],
            ]);

            $callLogService->upsertFromWebhook($mapped);
        }

        return response('OK', 200);
    }

    public function hangup(Request $request)
    {
        $callSid = $request->input('call_sid');

        if (! $callSid) {
            return response()->json([
                'success' => false,
                'message' => 'Call SID is required',
            ], 400);
        }

        try {
            $company = $this->getCompany($request);
            if (! $company) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company not found',
                ], 500);
            }

            $service = $this->infobipCompany->getServiceForCompany($company);
            if (! $service) {
                return response()->json([
                    'success' => false,
                    'message' => 'Infobip integration not configured',
                ], 500);
            }

            $service->hangupCall($callSid);

            Log::info('Infobip call terminated', ['call_sid' => $callSid]);

            return response()->json([
                'success' => true,
                'message' => 'Call ended successfully',
                'call_sid' => $callSid,
                'status' => 'completed',
            ]);
        } catch (\Exception $e) {
            Log::error('Error hanging up Infobip call', [
                'error' => $e->getMessage(),
                'call_sid' => $callSid,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to hangup: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send DTMF digits during an active call.
     */
    public function sendDigits(Request $request)
    {
        $callSid = $request->input('call_sid');
        $digits = $request->input('digits');

        if (! $callSid) {
            return response()->json([
                'success' => false,
                'message' => 'Call SID is required',
            ], 400);
        }

        if (! $digits || $digits === '') {
            return response()->json([
                'success' => false,
                'message' => 'Digits are required',
            ], 400);
        }

        if (! preg_match('/^[0-9*#ABCDabcdwW]{1,32}$/', $digits)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid digits. Use 0-9, *, #, A-D, or w/W for pause.',
            ], 400);
        }

        try {
            $company = $this->getCompany($request);
            if (! $company) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company not found',
                ], 500);
            }

            $service = $this->infobipCompany->getServiceForCompany($company);
            if (! $service) {
                return response()->json([
                    'success' => false,
                    'message' => 'Infobip integration not configured',
                ], 500);
            }

            $service->sendDtmf($callSid, $digits);

            return response()->json([
                'success' => true,
                'message' => 'Digits sent',
            ]);
        } catch (\Exception $e) {
            Log::error('Error sending Infobip DTMF', [
                'error' => $e->getMessage(),
                'call_sid' => $callSid,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send digits: '.$e->getMessage(),
            ], 500);
        }
    }

    public function callStatus(Request $request)
    {
        $callSid = $request->input('call_sid');

        if (! $callSid) {
            return response()->json([
                'success' => false,
                'message' => 'Call SID is required',
            ], 400);
        }

        try {
            $company = $this->getCompany($request);
            if (! $company) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company not found',
                ], 500);
            }

            $service = $this->infobipCompany->getServiceForCompany($company);
            if (! $service) {
                return response()->json([
                    'success' => false,
                    'message' => 'Infobip integration not configured',
                ], 500);
            }

            $call = $service->getCall($callSid);

            $from = $call['from']
                ?? $call['ourEndpoint']['phoneNumber']
                ?? $call['endpoint']['phoneNumber']
                ?? null;
            $to = $call['to']
                ?? $call['endpoint']['phoneNumber']
                ?? $call['ourEndpoint']['phoneNumber']
                ?? null;
            $direction = strtolower((string) ($call['direction'] ?? ''));
            $status = $this->mapInfobipCallStatus(
                $call['status'] ?? $call['state'] ?? $call['type'] ?? null
            );

            return response()->json([
                'success' => true,
                'status' => $status,
                'duration' => $call['duration'] ?? $call['durationSeconds'] ?? 0,
                'direction' => $direction ?: null,
                'from' => $from,
                'to' => $to,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching Infobip call status', [
                'error' => $e->getMessage(),
                'call_sid' => $callSid,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch call status: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Infobip Calls webhook — connect inbound PSTN to assigned WebRTC identity.
     */
    public function voiceWebhook(Request $request)
    {
        return $this->handleCallsEvent($request);
    }

    /**
     * Alias for Infobip Calls event subscription URL.
     */
    public function callsEvent(Request $request)
    {
        return $this->handleCallsEvent($request);
    }

    /**
     * Generate a WebRTC token for browser-based calling.
     */
    public function getCapabilityToken(Request $request)
    {
        try {
            $user = Auth::user();
            $company = $this->getCompany($request);

            if (! $company) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company not found',
                ], 404);
            }

            $integration = $this->infobipCompany->getActiveIntegration($company);
            if (! $integration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Infobip integration not configured or inactive. Please configure your Infobip integration in the Integrations page.',
                ], 400);
            }

            if (! $integration->application_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Application ID is missing. Please add your Infobip Application ID in the Integrations page.',
                ], 400);
            }

            $service = $this->infobipCompany->getServiceForIntegration($integration);
            if (! $service) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to load Infobip credentials. Please reconfigure your integration.',
                ], 500);
            }

            $identity = $this->infobipCompany->webrtcIdentityForUser($user);
            $tokenData = $service->createWebrtcToken($identity, $user->name);

            Log::info('Infobip WebRTC token generated', [
                'user_id' => $user->id,
                'identity' => $identity,
                'application_id' => $integration->application_id,
            ]);

            return response()->json([
                'success' => true,
                'token' => $tokenData['token'],
                'application_id' => $integration->application_id,
                // Legacy key for existing softphone clients
                'app_sid' => $integration->application_id,
                'identity' => $identity,
                'expiration_time' => $tokenData['expirationTime'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::error('Error generating Infobip WebRTC token', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate capability token: '.$e->getMessage(),
            ], 500);
        }
    }

    private function handleCallsEvent(Request $request)
    {
        $events = $this->extractCallEvents($request);

        foreach ($events as $event) {
            $mapped = $this->mapInfobipCallEventToLogPayload($event);
            if ($mapped) {
                $this->callLogService->upsertFromWebhook($mapped);
            }

            $type = strtoupper((string) ($event['type'] ?? $event['status'] ?? ''));
            $direction = strtoupper((string) ($event['direction'] ?? $event['properties']['direction'] ?? ''));
            $callId = $event['callId'] ?? $event['id'] ?? $event['call']['id'] ?? null;

            $isInboundReceived = $callId && (
                $type === 'CALL_RECEIVED'
                || ($direction === 'INBOUND' && in_array($type, ['CALL_RECEIVED', 'CALL_STARTED', 'RINGING', ''], true))
            );

            if (! $isInboundReceived) {
                continue;
            }

            // Prefer company DID as "to" for inbound
            $to = $event['to']
                ?? $event['ourEndpoint']['phoneNumber']
                ?? $event['destination']
                ?? null;
            $from = $event['from']
                ?? $event['endpoint']['phoneNumber']
                ?? $event['caller']
                ?? null;

            if (! $to && $direction === 'INBOUND') {
                $to = $event['ourEndpoint']['phoneNumber'] ?? null;
            }

            $toNormalized = $to ? $this->infobipCompany->normalizePhone((string) $to) : null;
            $fromNormalized = $from ? $this->infobipCompany->normalizePhone((string) $from) : null;

            $company = $this->infobipCompany->resolveCompanyFromNumber($toNormalized, $fromNormalized);
            if (! $company) {
                Log::warning('Infobip inbound call: company not resolved', [
                    'call_id' => $callId,
                    'to' => $to,
                    'from' => $from,
                ]);

                continue;
            }

            $integration = $this->infobipCompany->getActiveIntegration($company);
            if ($integration) {
                $providedSecret = $request->header('X-Infobip-Secret')
                    ?? $request->header('Authorization')
                    ?? $request->query('secret');
                if (is_string($providedSecret) && str_starts_with($providedSecret, 'App ')) {
                    $providedSecret = substr($providedSecret, 4);
                }
                if (! $this->infobipCompany->validateWebhookSecret(
                    is_string($providedSecret) ? $providedSecret : null,
                    $integration
                )) {
                    Log::warning('Infobip calls webhook: invalid secret', [
                        'company_id' => $company->id,
                        'call_id' => $callId,
                    ]);

                    continue;
                }
            }

            $assignedUser = $this->infobipCompany->resolveUserFromNumbers($toNormalized, $fromNormalized, 'inbound');
            if (! $assignedUser) {
                Log::warning('Infobip inbound call: no assigned user', [
                    'call_id' => $callId,
                    'to' => $toNormalized,
                ]);

                continue;
            }

            $service = $this->infobipCompany->getServiceForCompany($company);
            if (! $service) {
                Log::warning('Infobip inbound call: service unavailable', [
                    'company_id' => $company->id,
                    'call_id' => $callId,
                ]);

                continue;
            }

            $identity = $this->infobipCompany->webrtcIdentityForUser($assignedUser);

            try {
                $service->connectCallToWebrtc($callId, $identity);
                Log::info('Infobip inbound call connected to WebRTC', [
                    'call_id' => $callId,
                    'identity' => $identity,
                    'user_id' => $assignedUser->id,
                ]);
            } catch (\Exception $e) {
                Log::error('Infobip connectCallToWebrtc failed', [
                    'call_id' => $callId,
                    'identity' => $identity,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response('OK', 200);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractCallEvents(Request $request): array
    {
        $payload = $request->all();

        if (isset($payload['calls']) && is_array($payload['calls'])) {
            return array_values(array_filter($payload['calls'], 'is_array'));
        }

        if (isset($payload['results']) && is_array($payload['results'])) {
            return array_values(array_filter($payload['results'], 'is_array'));
        }

        if (isset($payload['call']) && is_array($payload['call'])) {
            return [array_merge($payload['call'], [
                'type' => $payload['type'] ?? $payload['call']['type'] ?? null,
            ])];
        }

        // Single event object or Twilio-shaped form fields
        if (! empty($payload['callId']) || ! empty($payload['CallSid']) || ! empty($payload['id']) || ! empty($payload['type'])) {
            return [$payload];
        }

        return [];
    }

    /**
     * Map Infobip (or Twilio-shaped) call event to PhoneCallLogService webhook fields.
     *
     * @param  array<string, mixed>  $event
     * @return array{CallSid: string, From: ?string, To: ?string, Direction: ?string, CallStatus: ?string, CallDuration: int}|null
     */
    private function mapInfobipCallEventToLogPayload(array $event): ?array
    {
        $callSid = $event['CallSid']
            ?? $event['callId']
            ?? $event['id']
            ?? $event['call']['id']
            ?? null;

        if (! $callSid) {
            return null;
        }

        $from = $event['From']
            ?? $event['from']
            ?? $event['endpoint']['phoneNumber']
            ?? $event['caller']
            ?? null;

        $to = $event['To']
            ?? $event['to']
            ?? $event['ourEndpoint']['phoneNumber']
            ?? $event['destination']
            ?? null;

        $rawDirection = $event['Direction']
            ?? $event['direction']
            ?? $event['properties']['direction']
            ?? null;
        $direction = $this->mapInfobipDirection($rawDirection);

        $rawStatus = $event['CallStatus']
            ?? $event['status']
            ?? $event['state']
            ?? $event['type']
            ?? null;
        $status = $this->mapInfobipCallStatus($rawStatus);

        $duration = (int) ($event['CallDuration']
            ?? $event['duration']
            ?? $event['durationSeconds']
            ?? $event['properties']['duration']
            ?? 0);

        if ($from) {
            $from = $this->infobipCompany->normalizePhone((string) $from);
        }
        if ($to) {
            $to = $this->infobipCompany->normalizePhone((string) $to);
        }

        return [
            'CallSid' => (string) $callSid,
            'From' => $from,
            'To' => $to,
            'Direction' => $direction,
            'CallStatus' => $status,
            'CallDuration' => $duration,
        ];
    }

    private function mapInfobipDirection(mixed $direction): ?string
    {
        if ($direction === null || $direction === '') {
            return null;
        }

        $upper = strtoupper((string) $direction);

        return match ($upper) {
            'INBOUND', 'INCOMING' => 'inbound',
            'OUTBOUND', 'OUTGOING' => 'outbound-api',
            default => strtolower((string) $direction),
        };
    }

    private function mapInfobipCallStatus(mixed $status): ?string
    {
        if ($status === null || $status === '') {
            return null;
        }

        if (is_array($status)) {
            $status = $status['name'] ?? $status['groupName'] ?? $status['status'] ?? null;
            if ($status === null) {
                return null;
            }
        }

        $upper = strtoupper((string) $status);

        return match ($upper) {
            'CALL_RECEIVED', 'CALL_STARTED', 'CALL_RINGING', 'RINGING' => 'ringing',
            'CALL_ESTABLISHED', 'ESTABLISHED', 'ANSWERED', 'IN_PROGRESS', 'IN-PROGRESS' => 'in-progress',
            'CALL_FINISHED', 'FINISHED', 'COMPLETED', 'HANGUP', 'NORMAL_HANGUP' => 'completed',
            'CALL_FAILED', 'FAILED' => 'failed',
            'CALL_BUSY', 'BUSY' => 'busy',
            'CALL_NO_ANSWER', 'NO_ANSWER', 'NO-ANSWER' => 'no-answer',
            'CALL_CANCELED', 'CALL_CANCELLED', 'CANCELED', 'CANCELLED' => 'canceled',
            'INITIATED', 'CALL_PRE_ESTABLISHED' => 'initiated',
            default => strtolower(str_replace('_', '-', (string) $status)),
        };
    }

    private function getCompany(Request $request): ?Company
    {
        $company = $request->get('company');

        if ($company instanceof Company) {
            return $company;
        }

        if (app()->bound('company')) {
            try {
                return app('company');
            } catch (\Exception $e) {
                if (Auth::check()) {
                    return Auth::user()->company;
                }

                return null;
            }
        }

        if (Auth::check()) {
            return Auth::user()->company;
        }

        return null;
    }
}
