<?php

namespace App\Http\Controllers\Twilio;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PhoneCallLog;
use App\Models\TwilioFlexIntegration;
use App\Models\User;
use App\Services\InboundCallQueueService;
use App\Services\PhoneCallLogService;
use App\Services\TwilioService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class CallController extends Controller
{
    public function index(Request $request)
    {
        // Check if the user is already authenticated
        if (Auth::check()) {
            $user = Auth::user();
            $twilioNumber = $user->twilio_number ?? null;

            // Check if Twilio integration exists
            $company = $this->getCompany($request);
            $hasIntegration = false;
            $integrationError = null;

            if ($company) {
                $integration = TwilioFlexIntegration::where('company_id', $company->id)
                    ->where('is_active', true)
                    ->first();

                if ($integration) {
                    $hasIntegration = true;
                } else {
                    // Check if there's an inactive integration
                    $anyIntegration = TwilioFlexIntegration::where('company_id', $company->id)->first();
                    if ($anyIntegration) {
                        $integrationError = 'Twilio integration is inactive. Please activate it in the Integrations page.';
                    } else {
                        $integrationError = 'Twilio not configured. Please connect Twilio under Integrations.';
                    }
                }
            } else {
                        $integrationError = 'Company not found.';
            }

            return view('twilio.call', [
                'twilioNumber' => $twilioNumber,
                'hasIntegration' => $hasIntegration,
                'integrationError' => $integrationError,
                'canManageNumbers' => $user->hasPermission('manage_twilio_numbers'),
                'canSendSms' => $user->hasPermission('send_sms'),
                'canViewSms' => $user->hasPermission('view_sms'),
                'canManageContacts' => $user->hasPermission('manage_phone_contacts'),
            ]);
        }

        // Show the login form if not logged in
        return redirect()->route('login');
    }

    public function call(Request $request)
    {
        try {
            // Ensure we always return JSON for AJAX requests
            if ($request->wantsJson() || $request->ajax() || $request->expectsJson()) {
                // Request is expecting JSON
            }

            $phoneNumber = $request->input('phone', '+639957802471'); // Default or from request

            Log::info('Initiating Twilio call', [
                'phone_number' => $phoneNumber,
                'user_id' => Auth::id(),
                'request_method' => $request->method(),
                'request_url' => $request->fullUrl(),
                'accept_header' => $request->header('Accept'),
            ]);

            // Get user's Twilio number from database
            $user = Auth::user();
            $twilioFrom = $user->twilio_number ?? null;

            // Get company and Twilio integration from database
            $company = $this->getCompany($request);

            if (! $company) {
                Log::error('Twilio call failed: Company not found in database', [
                    'user_id' => $user->id,
                    'user_email' => $user->email ?? 'unknown',
                    'phone_number' => $phoneNumber,
                    'request_url' => $request->fullUrl(),
                    'subdomain' => $request->getHost(),
                    'error_type' => 'missing_company_record',
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Company not found.',
                    'call_sid' => null,
                ], 500);
            }

            $integration = TwilioFlexIntegration::where('company_id', $company->id)
                ->where('is_active', true)
                ->first();

            if (! $integration) {
                // Check if there's any integration record (inactive or active)
                $anyIntegration = TwilioFlexIntegration::where('company_id', $company->id)->first();

                Log::error('Twilio call failed: No active integration record found in database', [
                    'company_id' => $company->id,
                    'company_name' => $company->name ?? 'unknown',
                    'user_id' => $user->id,
                    'user_email' => $user->email ?? 'unknown',
                    'phone_number' => $phoneNumber,
                    'has_inactive_integration' => $anyIntegration ? true : false,
                    'integration_status' => $anyIntegration ? ($anyIntegration->is_active ? 'active' : 'inactive') : 'none',
                    'request_url' => $request->fullUrl(),
                    'error_type' => 'missing_twilio_integration_record',
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Twilio not configured. Please connect Twilio under Integrations.',
                    'call_sid' => null,
                ], 500);
            }

            // Decrypt auth token
            try {
                $twilioSid = $integration->account_sid;
                $twilioToken = $integration->auth_token ? Crypt::decryptString($integration->auth_token) : null;
            } catch (\Exception $e) {
                Log::error('Twilio call failed: Failed to decrypt auth token from database', [
                    'company_id' => $company->id,
                    'company_name' => $company->name ?? 'unknown',
                    'user_id' => $user->id,
                    'user_email' => $user->email ?? 'unknown',
                    'phone_number' => $phoneNumber,
                    'integration_id' => $integration->id,
                    'has_auth_token' => ! empty($integration->auth_token),
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'request_url' => $request->fullUrl(),
                    'error_type' => 'decryption_error',
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to decrypt Twilio credentials. Please reconfigure your integration.',
                    'call_sid' => null,
                ], 500);
            }

            if (! $twilioSid || ! $twilioToken) {
                Log::error('Twilio call failed: Missing credentials in database record', [
                    'company_id' => $company->id,
                    'company_name' => $company->name ?? 'unknown',
                    'user_id' => $user->id,
                    'user_email' => $user->email ?? 'unknown',
                    'phone_number' => $phoneNumber,
                    'integration_id' => $integration->id,
                    'has_sid' => ! empty($twilioSid),
                    'has_token' => ! empty($twilioToken),
                    'has_account_sid' => ! empty($integration->account_sid),
                    'has_auth_token_field' => ! empty($integration->auth_token),
                    'request_url' => $request->fullUrl(),
                    'error_type' => 'missing_credentials_in_record',
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Twilio credentials not configured. Please configure your Twilio credentials in the Integrations page.',
                    'call_sid' => null,
                ], 500);
            }

            // Validate and clean twilio number
            if (! $twilioFrom || empty(trim($twilioFrom))) {
                Log::error('Twilio number not configured for user', [
                    'user_id' => $user->id,
                    'has_user_twilio_number' => ! empty($user->twilio_number),
                    'twilio_number_value' => $user->twilio_number ?? 'null',
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Twilio number is required. Please set your Twilio number in your profile to make calls.',
                    'call_sid' => null,
                ], 500);
            }

            // Clean the twilio number (remove whitespace, ensure it starts with +)
            $twilioFrom = trim($twilioFrom);
            if (! str_starts_with($twilioFrom, '+')) {
                // If it doesn't start with +, add it
                $twilioFrom = '+'.$twilioFrom;
            }

            // Validate phone number format (basic validation)
            if (! preg_match('/^\+[1-9]\d{1,14}$/', $twilioFrom)) {
                Log::error('Invalid Twilio number format', [
                    'user_id' => $user->id,
                    'twilio_number' => $twilioFrom,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Twilio number format. Please ensure your Twilio number is in E.164 format (e.g., +1234567890).',
                    'call_sid' => null,
                ], 500);
            }

            // Initialize TwilioService with database credentials
            $twilio = new TwilioService($twilioSid, $twilioToken);
            $voiceUrl = route('twilio.voice', ['agent' => $user->id]);

            Log::info('Making Twilio API call', [
                'to' => $phoneNumber,
                'from' => $twilioFrom,
                'voice_url' => $voiceUrl,
            ]);

            $call = $twilio->makeCall($phoneNumber, $voiceUrl, null, $twilioFrom);

            app(PhoneCallLogService::class)->recordOutbound(
                (int) $company->id,
                (int) $user->id,
                $call->sid,
                $twilioFrom,
                $phoneNumber
            );

            Log::info('Call initiated successfully', [
                'call_sid' => $call->sid,
                'phone_number' => $phoneNumber,
                'status' => $call->status,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Call started!',
                'call_sid' => $call->sid,
                'status' => $call->status,
                'phone_number' => $phoneNumber,
            ]);
        } catch (\Twilio\Exceptions\RestException $e) {
            Log::error('Twilio REST API error', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'phone_number' => $request->input('phone'),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Twilio API error: '.$e->getMessage(),
                'call_sid' => null,
            ], 500);
        } catch (\Exception $e) {
            Log::error('Twilio call error', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'phone_number' => $request->input('phone'),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to make call: '.$e->getMessage(),
                'call_sid' => null,
            ], 500);
        }
    }

    public function statusCallback(Request $request, PhoneCallLogService $callLogService, InboundCallQueueService $queue)
    {
        $callSid = $request->input('CallSid');
        $callStatus = $request->input('CallStatus');
        $from = $request->input('From');
        $to = $request->input('To');
        $direction = $request->input('Direction');
        $duration = $request->input('CallDuration') ?? '0';

        $statusMessages = [
            'initiated' => 'Call initiated',
            'ringing' => 'Call ringing',
            'answered' => 'Call answered',
            'completed' => 'Call ended',
            'busy' => 'Line busy',
            'no-answer' => 'No answer',
            'failed' => 'Call failed',
            'canceled' => 'Call canceled',
        ];

        $message = $statusMessages[$callStatus] ?? "Call status: {$callStatus}";

        Log::info($message, [
            'call_sid' => $callSid,
            'status' => $callStatus,
            'from' => $from,
            'to' => $to,
            'direction' => $direction,
            'duration' => $duration,
            'timestamp' => now()->toDateTimeString(),
        ]);

        $assignment = $callSid ? $queue->getAssignment($callSid) : null;
        $payload = $request->all();
        if ($assignment && ! empty($assignment['current_user_id'])) {
            $payload['QueueAssignedUserId'] = (int) $assignment['current_user_id'];
        }

        $callLogService->upsertFromWebhook($payload);

        // Only end queue assignment when the caller actually hangs up or the call fully completes.
        // busy/no-answer/failed during Dial are handled by dialAction so a page refresh can retry the same agent.
        if ($callSid && in_array($callStatus, ['completed', 'canceled'], true)) {
            $queue->releaseFromCall($callSid);
            $queue->forgetAssignment($callSid);
        }

        return response('OK', 200);
    }

    /**
     * Twilio voice webhook — inbound calls use round-robin among available agents.
     *
     * REST API outbound already rang the destination; when they answer we must
     * bridge to the agent (Client), not Dial that same number again.
     * Browser SDK outbound is a client-originated call that should Dial the PSTN number.
     */
    public function voiceWebhook(Request $request, InboundCallQueueService $queue): Response
    {
        Log::info('=== TWILIO VOICE WEBHOOK HIT ===', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'all_params' => $request->all(),
        ]);

        $called = $request->input('Called') ?: $request->input('To');
        $caller = $request->input('Caller') ?: $request->input('From');
        $direction = (string) $request->input('Direction', 'outbound-api');
        $callSid = (string) ($request->input('CallSid') ?? '');
        $accountSid = $request->input('AccountSid');

        $isClientOrigin = $this->isClientOriginated($request);
        $isOutboundApi = $this->isOutboundApi($request);
        $isInboundPstn = $direction === 'inbound' && ! $isClientOrigin;
        $destination = $this->resolveE164Destination($request);

        if (($isClientOrigin || $isOutboundApi) && $destination) {
            $company = app(\App\Services\TwilioCompanyService::class)
                ->resolveCompanyFromWebhook($accountSid, $destination, $caller);
            if ($company) {
                app(\App\Services\LeadAutoCreateService::class)
                    ->fromPhoneChannel((int) $company->id, 'phone', $destination);
            }
        }

        $recordingCallback = htmlspecialchars(route('twilio.recording-callback'), ENT_XML1);
        $dialRecordAttrs = 'record="record-from-answer" recordingStatusCallback="'.$recordingCallback.'" recordingStatusCallbackEvent="completed" recordingTrack="both"';

        $twiml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $twiml .= '<Response>'."\n";

        if ($isClientOrigin) {
            $twiml .= $this->buildBrowserOutboundTwiml($request, $dialRecordAttrs);
        } elseif ($isOutboundApi) {
            $twiml .= $this->buildRestOutboundTwiml($request, $callSid, $dialRecordAttrs);
        } elseif ($isInboundPstn) {
            $twiml .= $this->buildInboundRoundRobinTwiml(
                $queue,
                $accountSid,
                $called,
                $caller,
                $callSid,
                $dialRecordAttrs
            );
        } else {
            $twiml .= '    <Say voice="alice">Call connected.</Say>'."\n";
        }

        $twiml .= '</Response>';

        Log::info('Twilio voice webhook response', [
            'twiml' => $twiml,
            'called' => $called,
            'caller' => $caller,
            'direction' => $direction,
            'is_client_origin' => $isClientOrigin,
            'is_outbound_api' => $isOutboundApi,
            'is_inbound_pstn' => $isInboundPstn,
            'destination' => $destination,
        ]);

        return response($twiml, 200)->header('Content-Type', 'text/xml');
    }

    /**
     * Dial action callback — retry next available agent on no-answer / busy / failed.
     */
    public function dialAction(Request $request, InboundCallQueueService $queue): Response
    {
        $callSid = (string) ($request->input('CallSid') ?? '');
        $dialStatus = (string) ($request->input('DialCallStatus') ?? '');
        $dialDuration = (int) ($request->input('DialCallDuration') ?? 0);
        $called = $request->input('Called') ?: $request->input('To');
        $caller = $request->input('Caller') ?: $request->input('From');
        $accountSid = $request->input('AccountSid');

        Log::info('Twilio dial action callback', [
            'call_sid' => $callSid,
            'dial_status' => $dialStatus,
            'dial_duration' => $dialDuration,
            'params' => $request->all(),
        ]);

        $recordingCallback = htmlspecialchars(route('twilio.recording-callback'), ENT_XML1);
        $dialRecordAttrs = 'record="record-from-answer" recordingStatusCallback="'.$recordingCallback.'" recordingStatusCallbackEvent="completed" recordingTrack="both"';

        $twiml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $twiml .= '<Response>'."\n";

        if (in_array($dialStatus, ['completed', 'answered'], true)) {
            $queue->releaseFromCall($callSid);
            $queue->forgetAssignment($callSid);
            $twiml .= '</Response>';

            return response($twiml, 200)->header('Content-Type', 'text/xml');
        }

        // Caller hung up while we were still ringing the agent.
        if ($dialStatus === 'canceled') {
            $queue->releaseFromCall($callSid);
            $queue->forgetAssignment($callSid);
            $twiml .= '</Response>';

            return response($twiml, 200)->header('Content-Type', 'text/xml');
        }

        $assignment = $callSid ? $queue->getAssignment($callSid) : null;
        $attempted = array_map('intval', $assignment['attempted_user_ids'] ?? []);
        $companyId = (int) ($assignment['company_id'] ?? 0);
        $currentUserId = (int) ($assignment['current_user_id'] ?? 0);
        $clientRetries = (int) ($assignment['client_retries'] ?? 0);

        if ($companyId <= 0) {
            $company = $queue->resolveCompanyForInbound($accountSid, $called, $caller);
            $companyId = (int) ($company?->id ?? 0);
        }

        // Page refresh unregisters the browser Device and Twilio reports busy/failed (or a very short no-answer).
        // Ring the same agent again instead of sending the caller to someone else.
        if (
            $companyId > 0
            && $callSid !== ''
            && $currentUserId > 0
            && $this->shouldRetrySameClient($dialStatus, $dialDuration, $clientRetries)
        ) {
            $sameAgent = User::query()->find($currentUserId);
            if ($sameAgent) {
                $queue->markBusy($sameAgent, $callSid);
                $queue->rememberAssignment(
                    $callSid,
                    $companyId,
                    (int) $sameAgent->id,
                    $attempted,
                    $clientRetries + 1
                );
                $twiml .= $this->dialClientTwiml($sameAgent, $dialRecordAttrs, 20);
                $twiml .= '</Response>';

                Log::info('Retrying same queued agent after client disconnect', [
                    'call_sid' => $callSid,
                    'user_id' => $sameAgent->id,
                    'dial_status' => $dialStatus,
                    'dial_duration' => $dialDuration,
                    'client_retries' => $clientRetries + 1,
                ]);

                return response($twiml, 200)->header('Content-Type', 'text/xml');
            }
        }

        $queue->releaseFromCall($callSid);

        if ($companyId > 0 && $callSid !== '') {
            $next = $queue->pickNextAgent($companyId, $attempted);
            if ($next) {
                $queue->markBusy($next, $callSid);
                $queue->rememberAssignment($callSid, $companyId, (int) $next->id, $attempted);
                $twiml .= $this->dialClientTwiml($next, $dialRecordAttrs);

                $twiml .= '</Response>';

                return response($twiml, 200)->header('Content-Type', 'text/xml');
            }
        }

        $queue->forgetAssignment($callSid);
        $twiml .= '    <Say voice="alice">All agents are currently unavailable. Please try again later.</Say>'."\n";
        $twiml .= '</Response>';

        return response($twiml, 200)->header('Content-Type', 'text/xml');
    }

    protected function buildInboundRoundRobinTwiml(
        InboundCallQueueService $queue,
        ?string $accountSid,
        ?string $called,
        ?string $caller,
        string $callSid,
        string $dialRecordAttrs
    ): string {
        try {
            $company = $queue->resolveCompanyForInbound($accountSid, $called, $caller);

            if (! $company) {
                Log::warning('Inbound call - company not found for round-robin', [
                    'called' => $called,
                    'caller' => $caller,
                    'account_sid' => $accountSid,
                ]);

                return '    <Say voice="alice">No agent is available for this number. Please try again later.</Say>'."\n";
            }

            $agent = $queue->pickNextAgent((int) $company->id);

            if (! $agent) {
                Log::warning('Inbound call - no available agents in queue', [
                    'company_id' => $company->id,
                    'called' => $called,
                ]);

                return '    <Say voice="alice">No agents are available right now. Please try again later.</Say>'."\n";
            }

            if ($callSid !== '') {
                $queue->markBusy($agent, $callSid);
                $queue->rememberAssignment($callSid, (int) $company->id, (int) $agent->id);
            }

            $xml = '    <Say voice="alice">This call may be recorded.</Say>'."\n";
            $xml .= $this->dialClientTwiml($agent, $dialRecordAttrs);

            return $xml;
        } catch (\Throwable $e) {
            Log::error('Error handling inbound round-robin call', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return '    <Say voice="alice">An error occurred. Please try again later.</Say>'."\n";
        }
    }

    protected function shouldRetrySameClient(string $dialStatus, int $dialDuration, int $clientRetries): bool
    {
        if ($clientRetries >= 4) {
            return false;
        }

        if (in_array($dialStatus, ['busy', 'failed'], true)) {
            return true;
        }

        // A refresh during ring often comes back as no-answer with a very short Dial.
        return $dialStatus === 'no-answer' && $dialDuration < 8;
    }

    protected function dialClientTwiml(User $user, string $dialRecordAttrs, int $timeout = 30): string
    {
        $timeout = max(10, min(60, $timeout));
        $action = htmlspecialchars(route('twilio.dial-action'), ENT_XML1);
        $xml = '    <Dial timeout="'.$timeout.'" answerOnMedia="true" '.$dialRecordAttrs.' action="'.$action.'">'."\n";
        $xml .= '        <Client>'.htmlspecialchars((string) $user->id, ENT_XML1).'</Client>'."\n";
        $xml .= '    </Dial>'."\n";

        Log::info('Dialing queued client', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'timeout' => $timeout,
        ]);

        return $xml;
    }

    protected function isClientOriginated(Request $request): bool
    {
        if ($request->filled('FromClient')) {
            return true;
        }

        foreach (['From', 'Caller'] as $key) {
            $value = strtolower((string) $request->input($key, ''));
            if (str_starts_with($value, 'client:')) {
                return true;
            }
        }

        return false;
    }

    protected function isOutboundApi(Request $request): bool
    {
        $direction = strtolower((string) $request->input('Direction', ''));

        return $direction === 'outbound-api';
    }

    protected function isE164(?string $value): bool
    {
        return is_string($value) && preg_match('/^\+[1-9]\d{1,14}$/', $value) === 1;
    }

    protected function normalizeE164(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (! str_starts_with($value, '+')) {
            $value = '+'.$value;
        }

        return $this->isE164($value) ? $value : null;
    }

    protected function resolveE164Destination(Request $request): ?string
    {
        foreach (['phone', 'To', 'Called'] as $key) {
            $normalized = $this->normalizeE164($request->input($key));
            if ($normalized) {
                return $normalized;
            }
        }

        return null;
    }

    protected function resolveClientIdentity(Request $request): ?string
    {
        $fromClient = trim((string) $request->input('FromClient', ''));
        if ($fromClient !== '') {
            return $fromClient;
        }

        foreach (['From', 'Caller'] as $key) {
            $value = (string) $request->input($key, '');
            if (str_starts_with(strtolower($value), 'client:')) {
                $identity = substr($value, 7);

                return $identity !== '' ? $identity : null;
            }
        }

        $userId = trim((string) $request->input('user_id', ''));
        if ($userId !== '' && ctype_digit($userId)) {
            return $userId;
        }

        return null;
    }

    protected function resolveOutboundAgentId(Request $request, string $callSid): ?string
    {
        foreach (['agent', 'user_id'] as $key) {
            $value = trim((string) $request->input($key, ''));
            if ($value !== '' && ctype_digit($value)) {
                return $value;
            }
        }

        if ($callSid !== '') {
            $log = PhoneCallLog::query()->where('call_sid', $callSid)->first();
            if ($log?->user_id) {
                return (string) $log->user_id;
            }
        }

        return $this->resolveClientIdentity($request);
    }

    protected function buildBrowserOutboundTwiml(Request $request, string $dialRecordAttrs): string
    {
        $destination = $this->resolveE164Destination($request);
        $identity = $this->resolveClientIdentity($request);
        $agent = $identity ? User::query()->find($identity) : null;
        $callerId = $this->normalizeE164($agent?->twilio_number);

        if (! $destination) {
            return '    <Say voice="alice">No destination number was provided.</Say>'."\n";
        }

        if (! $callerId) {
            return '    <Say voice="alice">Your phone system number is not assigned. You cannot place outbound calls.</Say>'."\n";
        }

        $xml = '    <Dial timeout="60" '.$dialRecordAttrs.' callerId="'.htmlspecialchars($callerId, ENT_XML1).'">'."\n";
        $xml .= '        <Number>'.htmlspecialchars($destination, ENT_XML1).'</Number>'."\n";
        $xml .= '    </Dial>'."\n";

        return $xml;
    }

    protected function buildRestOutboundTwiml(Request $request, string $callSid, string $dialRecordAttrs): string
    {
        $agentId = $this->resolveOutboundAgentId($request, $callSid);
        $callerId = $this->resolveE164Destination($request);

        if (! $agentId) {
            return '    <Say voice="alice">This outbound call cannot be connected to an agent.</Say>'."\n";
        }

        $callerIdAttr = $callerId ? ' callerId="'.htmlspecialchars($callerId, ENT_XML1).'"' : '';
        $xml = '    <Dial timeout="30" '.$dialRecordAttrs.$callerIdAttr.'>'."\n";
        $xml .= '        <Client>'.htmlspecialchars($agentId, ENT_XML1).'</Client>'."\n";
        $xml .= '    </Dial>'."\n";
        $xml .= '    <Say voice="alice">The agent could not be connected. Configure browser calling under Integrations.</Say>'."\n";

        return $xml;
    }

    /**
     * Twilio recordingStatusCallback — stores recording metadata on the call log.
     */
    public function recordingStatusCallback(Request $request, PhoneCallLogService $callLogService)
    {
        Log::info('Twilio recording status callback', [
            'call_sid' => $request->input('CallSid'),
            'recording_sid' => $request->input('RecordingSid'),
            'recording_status' => $request->input('RecordingStatus'),
            'recording_duration' => $request->input('RecordingDuration'),
            'recording_url' => $request->input('RecordingUrl'),
        ]);

        $callLogService->applyRecordingFromWebhook($request->all());

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
            Log::info('Hanging up call', [
                'call_sid' => $callSid,
                'user_id' => Auth::id(),
            ]);

            // Get company and Twilio integration from database
            $company = $this->getCompany($request);

            if (! $company) {
                Log::error('Twilio hangup failed: Company not found in database', [
                    'user_id' => Auth::id(),
                    'call_sid' => $callSid,
                    'request_url' => $request->fullUrl(),
                    'subdomain' => $request->getHost(),
                    'error_type' => 'missing_company_record',
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Company not found',
                ], 500);
            }

            $integration = TwilioFlexIntegration::where('company_id', $company->id)
                ->where('is_active', true)
                ->first();

            if (! $integration) {
                Log::error('Twilio hangup failed: No active integration record found in database', [
                    'company_id' => $company->id,
                    'company_name' => $company->name ?? 'unknown',
                    'user_id' => Auth::id(),
                    'call_sid' => $callSid,
                    'request_url' => $request->fullUrl(),
                    'error_type' => 'missing_twilio_integration_record',
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Twilio integration not configured',
                ], 500);
            }

            // Decrypt auth token
            try {
                $twilioSid = $integration->account_sid;
                $twilioToken = $integration->auth_token ? Crypt::decryptString($integration->auth_token) : null;
            } catch (\Exception $e) {
                Log::error('Twilio hangup failed: Failed to decrypt auth token from database', [
                    'company_id' => $company->id,
                    'user_id' => Auth::id(),
                    'call_sid' => $callSid,
                    'error' => $e->getMessage(),
                    'error_type' => 'decryption_error',
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to decrypt Twilio credentials',
                ], 500);
            }

            if (! $twilioSid || ! $twilioToken) {
                Log::error('Twilio hangup failed: Missing credentials in database record', [
                    'company_id' => $company->id,
                    'user_id' => Auth::id(),
                    'call_sid' => $callSid,
                    'error_type' => 'missing_credentials_in_record',
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Twilio credentials not configured',
                ], 500);
            }

            // Initialize TwilioService and terminate the call
            $twilio = new TwilioService($twilioSid, $twilioToken);
            $call = $twilio->getTwilioClient()->calls($callSid)->update(['status' => 'completed']);

            Log::info('Call terminated successfully', [
                'call_sid' => $callSid,
                'status' => $call->status,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Call ended successfully',
                'call_sid' => $callSid,
                'status' => $call->status,
            ]);
        } catch (\Twilio\Exceptions\RestException $e) {
            Log::error('Twilio REST API error during hangup', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'call_sid' => $callSid,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Twilio API error: '.$e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            Log::error('Error hanging up call', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'call_sid' => $callSid,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to hangup: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send DTMF digits during an active call (e.g., "press 1 to connect to department").
     * Uses TwiML Play verb to send digits to the far end for API-initiated calls.
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

        // Validate digits: 0-9, *, #, A-D, w (0.5s pause), W (1s pause), max 32 chars
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

            $integration = TwilioFlexIntegration::where('company_id', $company->id)
                ->where('is_active', true)
                ->first();

            if (! $integration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Twilio integration not configured',
                ], 500);
            }

            try {
                $twilioSid = $integration->account_sid;
                $twilioToken = $integration->auth_token ? Crypt::decryptString($integration->auth_token) : null;
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to decrypt Twilio credentials',
                ], 500);
            }

            if (! $twilioSid || ! $twilioToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Twilio credentials not configured',
                ], 500);
            }

            // Escape digits for XML (e.g. # becomes &#35;)
            $escapedDigits = htmlspecialchars($digits, ENT_XML1, 'UTF-8');
            $twiml = '<Response><Play digits="'.$escapedDigits.'"></Play></Response>';

            $twilio = new TwilioService($twilioSid, $twilioToken);
            $twilio->getTwilioClient()->calls($callSid)->update(['twiml' => $twiml]);

            return response()->json([
                'success' => true,
                'message' => 'Digits sent',
            ]);
        } catch (\Twilio\Exceptions\RestException $e) {
            Log::error('Twilio REST API error sending digits', [
                'error' => $e->getMessage(),
                'call_sid' => $callSid,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Twilio API error: '.$e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            Log::error('Error sending digits', [
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
            // Get company and Twilio integration from database
            $company = $this->getCompany($request);

            if (! $company) {
                Log::error('Twilio call status failed: Company not found in database', [
                    'user_id' => Auth::id(),
                    'call_sid' => $callSid,
                    'request_url' => $request->fullUrl(),
                    'subdomain' => $request->getHost(),
                    'error_type' => 'missing_company_record',
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Company not found',
                ], 500);
            }

            $integration = TwilioFlexIntegration::where('company_id', $company->id)
                ->where('is_active', true)
                ->first();

            if (! $integration) {
                // Check if there's any integration record (inactive or active)
                $anyIntegration = TwilioFlexIntegration::where('company_id', $company->id)->first();

                Log::error('Twilio call status failed: No active integration record found in database', [
                    'company_id' => $company->id,
                    'company_name' => $company->name ?? 'unknown',
                    'user_id' => Auth::id(),
                    'call_sid' => $callSid,
                    'has_inactive_integration' => $anyIntegration ? true : false,
                    'integration_status' => $anyIntegration ? ($anyIntegration->is_active ? 'active' : 'inactive') : 'none',
                    'request_url' => $request->fullUrl(),
                    'error_type' => 'missing_twilio_integration_record',
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Twilio integration not configured',
                ], 500);
            }

            // Decrypt auth token
            try {
                $twilioSid = $integration->account_sid;
                $twilioToken = $integration->auth_token ? Crypt::decryptString($integration->auth_token) : null;
            } catch (\Exception $e) {
                Log::error('Twilio call status failed: Failed to decrypt auth token from database', [
                    'company_id' => $company->id,
                    'company_name' => $company->name ?? 'unknown',
                    'user_id' => Auth::id(),
                    'call_sid' => $callSid,
                    'integration_id' => $integration->id,
                    'has_auth_token' => ! empty($integration->auth_token),
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'request_url' => $request->fullUrl(),
                    'error_type' => 'decryption_error',
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to decrypt Twilio credentials',
                ], 500);
            }

            if (! $twilioSid || ! $twilioToken) {
                Log::error('Twilio call status failed: Missing credentials in database record', [
                    'company_id' => $company->id,
                    'company_name' => $company->name ?? 'unknown',
                    'user_id' => Auth::id(),
                    'call_sid' => $callSid,
                    'integration_id' => $integration->id,
                    'has_sid' => ! empty($twilioSid),
                    'has_token' => ! empty($twilioToken),
                    'has_account_sid' => ! empty($integration->account_sid),
                    'has_auth_token_field' => ! empty($integration->auth_token),
                    'request_url' => $request->fullUrl(),
                    'error_type' => 'missing_credentials_in_record',
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Twilio credentials not configured',
                ], 500);
            }

            $twilio = new TwilioService($twilioSid, $twilioToken);
            $call = $twilio->getTwilioClient()->calls($callSid)->fetch();

            return response()->json([
                'success' => true,
                'status' => $call->status,
                'duration' => $call->duration,
                'direction' => $call->direction,
                'from' => $call->from,
                'to' => $call->to,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching call status', [
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
     * Create a TwiML App and API key in Twilio when they were never saved in the CRM.
     * Browser calling cannot put audio in /twilio/call without these.
     */
    private function ensureVoiceSdkCredentials(
        TwilioFlexIntegration $integration,
        string $accountSid,
        string $authToken,
        int $companyId
    ): void {
        Cache::lock('twilio-voice-sdk-provision-'.$companyId, 30)->block(20, function () use ($integration, $accountSid, $authToken, $companyId) {
            $integration->refresh();

            $needsApp = empty($integration->app_sid);
            $needsKey = empty($integration->api_key) || empty($integration->api_secret);

            if (! $needsApp && ! $needsKey) {
                return;
            }

            $twilio = new TwilioService($accountSid, $authToken);
            $changed = false;

            if ($needsApp) {
                $integration->app_sid = $twilio->createVoiceApplication(
                    'LNSCRM Voice '.$companyId,
                    route('twilio.voice'),
                    route('twilio.status-callback')
                );
                $changed = true;

                Log::info('Auto-created Twilio TwiML App for browser calling', [
                    'company_id' => $companyId,
                    'app_sid' => $integration->app_sid,
                ]);
            }

            if ($needsKey) {
                $key = $twilio->createApiKey('LNSCRM Voice SDK '.$companyId);
                $integration->api_key = $key['sid'];
                $integration->api_secret = Crypt::encryptString($key['secret']);
                $changed = true;

                Log::info('Auto-created Twilio API key for browser calling', [
                    'company_id' => $companyId,
                    'api_key' => $integration->api_key,
                ]);
            }

            if ($changed) {
                $integration->save();
            }
        });
    }

    /**
     * Get company from request.
     */
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
                // Fallback to user's company
                if (Auth::check()) {
                    return Auth::user()->company;
                }

                return null;
            }
        }

        // Fallback to user's company
        if (Auth::check()) {
            return Auth::user()->company;
        }

        return null;
    }

    /**
     * Generate a capability token for browser-based calling
     *
     * Note: twilio_number is NOT required for incoming calls.
     * Incoming calls work via user identity (user ID), not phone number.
     * twilio_number is only required for making outbound calls.
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

            $integration = TwilioFlexIntegration::where('company_id', $company->id)
                ->where('is_active', true)
                ->first();

            if (! $integration) {
                Log::warning('Capability token request: No active Twilio integration found', [
                    'company_id' => $company->id,
                    'user_id' => $user->id,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Twilio integration not configured or inactive. Please configure your Twilio integration in the Integrations page.',
                ], 400);
            }

            // Decrypt auth token
            try {
                $twilioSid = $integration->account_sid;
                $twilioToken = $integration->auth_token ? Crypt::decryptString($integration->auth_token) : null;
            } catch (\Exception $e) {
                Log::error('Failed to decrypt auth token for capability token', [
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to decrypt Twilio credentials',
                ], 500);
            }

            if (! $twilioSid || ! $twilioToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Twilio credentials not configured. Please configure your Twilio credentials in the Integrations page.',
                ], 400);
            }

            try {
                $this->ensureVoiceSdkCredentials($integration, $twilioSid, $twilioToken, (int) $company->id);
            } catch (\Throwable $e) {
                Log::warning('Could not auto-provision Twilio Voice SDK credentials', [
                    'company_id' => $company->id,
                    'error' => $e->getMessage(),
                ]);
            }

            if (! $integration->app_sid) {
                Log::warning('Capability token request: App SID missing', [
                    'company_id' => $company->id,
                    'user_id' => $user->id,
                    'integration_id' => $integration->id,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'App SID is missing. Please add your Twilio App SID in the Integrations page.',
                ], 400);
            }

            // Generate capability token for browser-based calling
            // Using AccessToken (recommended for Twilio SDK 8.x)
            if (! $integration->api_key || ! $integration->api_secret) {
                Log::warning('Capability token request: API Key or Secret missing', [
                    'company_id' => $company->id,
                    'user_id' => $user->id,
                    'has_api_key' => ! empty($integration->api_key),
                    'has_api_secret' => ! empty($integration->api_secret),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'API Key and API Secret are required for browser-based calling. Please add them in your Twilio integration settings (App SID: '.($integration->app_sid ?: 'not set').').',
                ], 400);
            }

            try {
                // Decrypt API secret
                $apiSecret = null;
                if ($integration->api_secret) {
                    try {
                        $apiSecret = Crypt::decryptString($integration->api_secret);
                    } catch (\Exception $e) {
                        Log::error('Failed to decrypt API Secret', [
                            'error' => $e->getMessage(),
                            'user_id' => $user->id,
                        ]);

                        return response()->json([
                            'success' => false,
                            'message' => 'Failed to decrypt API Secret. Please reconfigure your Twilio integration.',
                        ], 500);
                    }
                }

                if (! $apiSecret) {
                    return response()->json([
                        'success' => false,
                        'message' => 'API Secret is empty or invalid.',
                    ], 500);
                }

                // Validate that we have all required values
                if (empty($twilioSid) || empty($integration->api_key) || empty($apiSecret) || empty($integration->app_sid)) {
                    Log::error('Missing required values for capability token', [
                        'has_account_sid' => ! empty($twilioSid),
                        'has_api_key' => ! empty($integration->api_key),
                        'has_api_secret' => ! empty($apiSecret),
                        'has_app_sid' => ! empty($integration->app_sid),
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Missing required Twilio credentials. Please check your integration settings.',
                    ], 500);
                }

                // Create AccessToken with API Key/Secret
                // Identity is set to user ID - this is what Twilio uses to route incoming calls
                // Note: twilio_number is NOT used for incoming call routing
                $token = new \Twilio\Jwt\AccessToken(
                    $twilioSid,           // Account SID
                    $integration->api_key, // API Key
                    $apiSecret,            // API Secret
                    3600,                  // Token expiration (1 hour)
                    (string) $user->id    // Identity (user ID as string) - used for incoming call routing
                );

                // Add Voice Grant for outgoing and incoming calls
                // Incoming calls are routed to this device based on the identity (user ID) above
                $voiceGrant = new \Twilio\Jwt\Grants\VoiceGrant;
                $voiceGrant->setOutgoingApplicationSid($integration->app_sid);
                $voiceGrant->setIncomingAllow(true); // Allow incoming calls to this identity
                $token->addGrant($voiceGrant);

                $tokenString = $token->toJWT();

                Log::info('Capability token generated successfully', [
                    'user_id' => $user->id,
                    'app_sid' => $integration->app_sid,
                ]);
            } catch (\Exception $e) {
                Log::error('Error generating capability token', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to generate capability token: '.$e->getMessage(),
                ], 500);
            }

            return response()->json([
                'success' => true,
                'token' => $tokenString,
                'app_sid' => $integration->app_sid,
            ]);
        } catch (\Exception $e) {
            Log::error('Error generating capability token', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate capability token: '.$e->getMessage(),
            ], 500);
        }
    }
}
