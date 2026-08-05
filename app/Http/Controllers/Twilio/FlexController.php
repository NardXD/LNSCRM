<?php

namespace App\Http\Controllers\Twilio;

use App\Http\Controllers\Controller;
use App\Models\TwilioFlexIntegration;
use App\Services\FlexCrmLookupService;
use App\Services\FlexEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class FlexController extends Controller
{
    public function __construct(
        protected FlexCrmLookupService $lookup,
        protected FlexEventService $events
    ) {}

    /**
     * JSON CRM lookup for Flex plugins (X-API-Key auth).
     */
    public function lookup(Request $request): JsonResponse
    {
        $companyId = (int) $request->attributes->get('flex_company_id');
        $phone = (string) ($request->query('phone') ?? $request->input('phone') ?? '');

        if ($phone === '') {
            return response()->json(['error' => 'phone is required'], 422);
        }

        return response()->json($this->lookup->lookup($companyId, $phone));
    }

    /**
     * HTML screen-pop for Flex CRMContainer iframe (webhook_key in path).
     */
    public function screenPop(Request $request, string $webhookKey): View|Response
    {
        $integration = TwilioFlexIntegration::query()
            ->where('webhook_key', $webhookKey)
            ->where('is_active', true)
            ->first();

        if (! $integration) {
            return response('Flex integration not found', 404);
        }

        $phone = (string) ($request->query('phone') ?? '');
        $data = $phone !== ''
            ? $this->lookup->lookup($integration->company_id, $phone)
            : [
                'phone' => null,
                'found' => false,
                'client' => null,
                'phone_contact' => null,
                'recent_calls' => [],
                'display_name' => 'No active task',
            ];

        return view('twilio.flex-screen-pop', [
            'data' => $data,
            'company' => $integration->company,
        ]);
    }

    /**
     * TaskRouter / Flex event callback webhook.
     */
    public function events(Request $request, string $webhookKey): JsonResponse
    {
        $integration = TwilioFlexIntegration::query()
            ->where('webhook_key', $webhookKey)
            ->where('is_active', true)
            ->first();

        if (! $integration) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $payload = $request->all();

        // Best-effort Twilio signature validation when AccountSid maps to stored Twilio credentials.
        $this->validateTwilioSignatureIfPossible($request, $integration);

        try {
            $log = $this->events->handle($integration, $payload);
        } catch (\Throwable $e) {
            Log::error('Flex event handling failed', [
                'company_id' => $integration->company_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to process event'], 500);
        }

        return response()->json([
            'ok' => true,
            'call_log_id' => $log?->id,
        ]);
    }

    protected function validateTwilioSignatureIfPossible(Request $request, TwilioFlexIntegration $integration): void
    {
        $signature = $request->header('X-Twilio-Signature');
        if (! $signature) {
            return;
        }

        $twilio = $integration->company?->twilioIntegration;
        if (! $twilio?->auth_token) {
            return;
        }

        try {
            $authToken = \Illuminate\Support\Facades\Crypt::decryptString($twilio->auth_token);
        } catch (\Throwable) {
            return;
        }

        $validator = new \Twilio\Security\RequestValidator($authToken);
        $url = $request->fullUrl();
        $params = $request->request->all();

        if (! $validator->validate($signature, $url, $params)) {
            Log::warning('Flex webhook signature mismatch', [
                'company_id' => $integration->company_id,
                'url' => $url,
            ]);
            // Do not hard-fail: Flex/TaskRouter event URLs and proxies can alter the signed URL.
        }
    }
}
