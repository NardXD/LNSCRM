<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\GmailIntegration;
use App\Models\OpenAIIntegration;
use App\Models\SharedInbox;
use App\Models\StripeIntegration;
use App\Models\StoreganiseIntegration;
use App\Models\TwilioFlexIntegration;
use App\Models\TwilioIntegration;
use App\Models\User;
use App\Models\ViberIntegration;
use App\Models\FacebookIntegration;
use App\Models\FrontIntegration;
use App\Models\WhatsAppIntegration;
use App\Models\WiseIntegration;
use App\Services\FacebookGraphMessagingService;
use App\Services\Front\FrontApiClient;
use App\Services\Front\FrontTagImportService;
use App\Services\OutlookMailService;
use App\Services\StoreganiseService;
use App\Services\TwilioCompanyService;
use App\Services\TwilioService;
use App\Services\TwilioIntegrationValidator;
use App\Services\WiseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class IntegrationController extends Controller
{
    /**
     * Display the Wise recipients & employee assignment page.
     */
    public function wiseRecipientsPage()
    {
        return view('dashboard.wise-recipients');
    }

    /**
     * Get Twilio integration (account credentials) for the current company.
     */
    public function getTwilioIntegration(Request $request): JsonResponse
    {
        $company = $this->getCompany($request);

        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $integration = TwilioFlexIntegration::where('company_id', $company->id)->first();
        $validator = app(TwilioIntegrationValidator::class);

        if ($integration) {
            $isConnected = $integration->is_active && $validator->isComplete($integration);

            return response()->json([
                'integration' => $this->formatTwilioIntegration($integration),
                'status' => $isConnected ? 'connected' : 'disconnected',
                'missing_fields' => $validator->missingFields($integration),
            ]);
        }

        return response()->json([
            'integration' => null,
            'status' => 'disconnected',
        ]);
    }

    /**
     * @deprecated Use Twilio endpoints — kept as aliases for older clients.
     */
    public function getFlexIntegration(Request $request): JsonResponse
    {
        return $this->getTwilioIntegration($request);
    }

    /**
     * @deprecated Use Twilio endpoints — kept as aliases for older clients.
     */
    public function storeFlexIntegration(Request $request): JsonResponse
    {
        return $this->storeTwilioIntegration($request);
    }

    /**
     * @deprecated Use Twilio endpoints — kept as aliases for older clients.
     */
    public function deleteFlexIntegration(Request $request): JsonResponse
    {
        return $this->deleteTwilioIntegration($request);
    }

    /**
     * Store or update Twilio account credentials.
     */
    public function storeTwilioIntegration(Request $request): JsonResponse
    {
        $company = $this->getCompany($request);

        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'account_sid' => ['required', 'string', 'regex:/^AC[a-fA-F0-9]{32}$/'],
            'auth_token' => ['nullable', 'string'],
            'app_sid' => ['nullable', 'string', 'regex:/^AP[a-fA-F0-9]{32}$/'],
            'api_key' => ['nullable', 'string', 'regex:/^SK[a-fA-F0-9]{32}$/'],
            'api_secret' => ['nullable', 'string'],
        ], [
            'account_sid.regex' => 'Account SID must be a valid Twilio SID (starts with AC).',
            'app_sid.regex' => 'App SID must be a valid Twilio SID (starts with AP).',
            'api_key.regex' => 'API Key must be a valid Twilio key (starts with SK).',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $existing = TwilioFlexIntegration::where('company_id', $company->id)->first();
        $twilioValidator = app(TwilioIntegrationValidator::class);

        $plain = $twilioValidator->resolvePlainCredentials(
            $existing,
            (string) $request->input('account_sid'),
            $request->input('auth_token'),
            $request->input('app_sid'),
            $request->input('api_key'),
            $request->input('api_secret')
        );

        $missing = $twilioValidator->missingFieldsFromPlain($plain);
        if ($missing !== []) {
            $errors = [];
            foreach ($missing as $field => $message) {
                $errors[$field] = [$message];
            }

            return response()->json(['errors' => $errors], 422);
        }

        $validation = $twilioValidator->validateWithTwilio($plain);
        if (! $validation['valid']) {
            $errors = [];
            foreach ($validation['errors'] as $field => $message) {
                $errors[$field] = [$message];
            }

            $detail = implode(' ', array_values($validation['errors']));

            return response()->json([
                'error' => 'Twilio credentials could not be verified. '.$detail,
                'errors' => $errors,
            ], 422);
        }

        $webhookKey = $existing?->webhook_key ?: Str::random(40);

        $integration = TwilioFlexIntegration::updateOrCreate(
            ['company_id' => $company->id],
            [
                'account_sid' => $plain['account_sid'],
                'auth_token' => Crypt::encryptString($plain['auth_token']),
                'app_sid' => $plain['app_sid'],
                'api_key' => $plain['api_key'],
                'api_secret' => $plain['api_secret']
                    ? Crypt::encryptString($plain['api_secret'])
                    : null,
                'webhook_key' => $webhookKey,
                'is_active' => true,
            ]
        );

        $synced = 0;
        try {
            $synced = app(TwilioCompanyService::class)->syncOwnedNumbers($company);
        } catch (\Throwable $e) {
            Log::warning('Twilio number sync after saving credentials failed', [
                'company_id' => $company->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => $synced > 0
                ? "Twilio saved and verified successfully. Synced {$synced} phone number(s)."
                : 'Twilio saved and verified successfully',
            'status' => 'connected',
            'synced_numbers' => $synced,
            'integration' => $this->formatTwilioIntegration($integration),
        ]);
    }

    /**
     * Delete Twilio integration for the current company.
     */
    public function deleteTwilioIntegration(Request $request): JsonResponse
    {
        $company = $this->getCompany($request);

        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        TwilioFlexIntegration::where('company_id', $company->id)->delete();

        // Legacy table cleanup if present
        if (class_exists(TwilioIntegration::class)) {
            TwilioIntegration::where('company_id', $company->id)->delete();
        }

        return response()->json(['message' => 'Twilio integration deleted successfully']);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatTwilioIntegration(TwilioFlexIntegration $integration): array
    {
        return [
            'id' => $integration->id,
            'company_id' => $integration->company_id,
            'account_sid' => $integration->account_sid,
            'app_sid' => $integration->app_sid,
            'api_key' => $integration->api_key,
            'api_secret' => $integration->api_secret ? '***hidden***' : null,
            'auth_token' => $integration->auth_token ? '***hidden***' : null,
            'is_active' => $integration->is_active,
            'created_at' => $integration->created_at,
            'updated_at' => $integration->updated_at,
        ];
    }

    /**
     * Get Viber Business integration for the current company.
     */
    public function getViberIntegration(Request $request): JsonResponse
    {
        $company = $this->getCompany($request);

        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $integration = ViberIntegration::where('company_id', $company->id)->first();
        $twilioReady = (bool) app(TwilioCompanyService::class)->getActiveIntegration($company);

        if ($integration) {
            $connected = $integration->is_active && $integration->sender_id && $twilioReady;

            return response()->json([
                'integration' => [
                    'id' => $integration->id,
                    'company_id' => $integration->company_id,
                    'sender_id' => $integration->sender_id,
                    'bot_name' => $integration->bot_name,
                    'welcome_message' => $integration->welcome_message,
                    'webhook_url' => $integration->webhookUrl(),
                    'webhook_set_at' => $integration->webhook_set_at,
                    'is_active' => $integration->is_active,
                    'twilio_connected' => $twilioReady,
                    'created_at' => $integration->created_at,
                    'updated_at' => $integration->updated_at,
                ],
                'status' => $connected ? 'connected' : 'disconnected',
            ]);
        }

        return response()->json([
            'integration' => null,
            'status' => 'disconnected',
            'twilio_connected' => $twilioReady,
        ]);
    }

    /**
     * Store or update Viber (Twilio Messaging) integration for the current company.
     */
    public function storeViberIntegration(Request $request): JsonResponse
    {
        $company = $this->getCompany($request);

        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        if (! app(TwilioCompanyService::class)->getActiveIntegration($company)) {
            return response()->json([
                'error' => 'Connect Twilio first under Integrations, then configure your Viber sender.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'sender_id' => ['required', 'string', 'max:128'],
            'bot_name' => ['nullable', 'string', 'max:255'],
            'welcome_message' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $existing = ViberIntegration::where('company_id', $company->id)->first();
        $webhookKey = $existing?->webhook_key ?: Str::random(40);

        $integration = ViberIntegration::updateOrCreate(
            ['company_id' => $company->id],
            [
                'sender_id' => trim((string) $request->input('sender_id')),
                'webhook_key' => $webhookKey,
                'bot_name' => $request->input('bot_name') ?: ($existing?->bot_name),
                'welcome_message' => $request->has('welcome_message')
                    ? $request->input('welcome_message')
                    : ($existing?->welcome_message),
                'is_active' => true,
            ]
        );

        return response()->json([
            'message' => 'Viber integration saved successfully',
            'integration' => [
                'id' => $integration->id,
                'sender_id' => $integration->sender_id,
                'bot_name' => $integration->bot_name,
                'welcome_message' => $integration->welcome_message,
                'webhook_url' => $integration->webhookUrl(),
                'webhook_set_at' => $integration->webhook_set_at,
                'is_active' => $integration->is_active,
            ],
            'status' => 'connected',
        ]);
    }

    /**
     * Delete Viber integration for the current company.
     */
    public function deleteViberIntegration(Request $request): JsonResponse
    {
        $company = $this->getCompany($request);

        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $integration = ViberIntegration::where('company_id', $company->id)->first();

        if ($integration) {
            $integration->delete();
        }

        return response()->json(['message' => 'Viber integration deleted successfully']);
    }

    /**
     * Get WhatsApp (Twilio Messaging) integration for the current company.
     */
    public function getWhatsAppIntegration(Request $request): JsonResponse
    {
        $company = $this->getCompany($request);

        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $integration = WhatsAppIntegration::where('company_id', $company->id)->first();
        $twilioReady = (bool) app(TwilioCompanyService::class)->getActiveIntegration($company);

        if ($integration) {
            $connected = $integration->is_active && $integration->from_number && $twilioReady;

            return response()->json([
                'integration' => [
                    'id' => $integration->id,
                    'company_id' => $integration->company_id,
                    'from_number' => $integration->from_number,
                    'display_phone_number' => $integration->display_phone_number ?: $integration->from_number,
                    'business_name' => $integration->business_name,
                    'welcome_message' => $integration->welcome_message,
                    'webhook_url' => $integration->webhookUrl(),
                    'webhook_set_at' => $integration->webhook_set_at,
                    'is_active' => $integration->is_active,
                    'twilio_connected' => $twilioReady,
                    'created_at' => $integration->created_at,
                    'updated_at' => $integration->updated_at,
                ],
                'status' => $connected ? 'connected' : 'disconnected',
            ]);
        }

        return response()->json([
            'integration' => null,
            'status' => 'disconnected',
            'twilio_connected' => $twilioReady,
        ]);
    }

    /**
     * Store or update WhatsApp (Twilio Messaging) integration for the current company.
     */
    public function storeWhatsAppIntegration(Request $request): JsonResponse
    {
        $company = $this->getCompany($request);

        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        if (! app(TwilioCompanyService::class)->getActiveIntegration($company)) {
            return response()->json([
                'error' => 'Connect Twilio first under Integrations, then configure your WhatsApp sender.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'from_number' => ['required', 'string', 'max:32'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'welcome_message' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $existing = WhatsAppIntegration::where('company_id', $company->id)->first();
        $webhookKey = $existing?->webhook_key ?: Str::random(40);
        $fromNumber = app(TwilioCompanyService::class)->normalizePhone((string) $request->input('from_number'));

        $integration = WhatsAppIntegration::updateOrCreate(
            ['company_id' => $company->id],
            [
                'from_number' => $fromNumber,
                'display_phone_number' => $fromNumber,
                'webhook_key' => $webhookKey,
                'business_name' => $request->input('business_name') ?: ($existing?->business_name),
                'welcome_message' => $request->has('welcome_message')
                    ? $request->input('welcome_message')
                    : ($existing?->welcome_message),
                'is_active' => true,
            ]
        );

        return response()->json([
            'message' => 'WhatsApp integration saved successfully',
            'integration' => [
                'id' => $integration->id,
                'from_number' => $integration->from_number,
                'display_phone_number' => $integration->display_phone_number,
                'business_name' => $integration->business_name,
                'welcome_message' => $integration->welcome_message,
                'webhook_url' => $integration->webhookUrl(),
                'webhook_set_at' => $integration->webhook_set_at,
                'is_active' => $integration->is_active,
            ],
            'status' => 'connected',
        ]);
    }

    /**
     * Delete WhatsApp integration for the current company.
     */
    public function deleteWhatsAppIntegration(Request $request): JsonResponse
    {
        $company = $this->getCompany($request);

        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $integration = WhatsAppIntegration::where('company_id', $company->id)->first();

        if ($integration) {
            $integration->delete();
        }

        return response()->json(['message' => 'WhatsApp integration deleted successfully']);
    }

    /**
     * Get Facebook / Instagram (Twilio Messaging) integration for the current company.
     */
    public function getFacebookIntegration(Request $request): JsonResponse
    {
        $company = $this->getCompany($request);

        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $integration = FacebookIntegration::where('company_id', $company->id)->first();
        $twilioReady = (bool) app(TwilioCompanyService::class)->getActiveIntegration($company);

        if ($integration) {
            if (! $integration->webhook_verify_token) {
                $integration->webhook_verify_token = Str::random(40);
                $integration->save();
            }

            $connected = $integration->is_active && $integration->page_id && ($twilioReady || $integration->hasInstagramGraph());

            return response()->json([
                'integration' => [
                    'id' => $integration->id,
                    'company_id' => $integration->company_id,
                    'page_id' => $integration->page_id,
                    'page_name' => $integration->page_name,
                    'instagram_business_account_id' => $integration->instagram_business_account_id,
                    'instagram_username' => $integration->instagram_username,
                    'welcome_message' => $integration->welcome_message,
                    'webhook_url' => $integration->webhookUrl(),
                    'webhook_verify_token' => $integration->webhook_verify_token,
                    'webhook_set_at' => $integration->webhook_set_at,
                    'is_active' => $integration->is_active,
                    'twilio_connected' => $twilioReady,
                    'has_page_access_token' => (bool) $integration->getDecryptedPageAccessToken(),
                    'has_app_secret' => (bool) $integration->getDecryptedAppSecret(),
                    'instagram_graph' => $integration->hasInstagramGraph(),
                    'created_at' => $integration->created_at,
                    'updated_at' => $integration->updated_at,
                ],
                'status' => $connected ? 'connected' : 'disconnected',
            ]);
        }

        return response()->json([
            'integration' => null,
            'status' => 'disconnected',
            'twilio_connected' => $twilioReady,
        ]);
    }

    /**
     * Store or update Facebook / Instagram (Twilio Messaging) integration.
     */
    public function storeFacebookIntegration(Request $request): JsonResponse
    {
        $company = $this->getCompany($request);

        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $twilioReady = (bool) app(TwilioCompanyService::class)->getActiveIntegration($company);
        $existing = FacebookIntegration::where('company_id', $company->id)->first();
        $hasPageToken = $request->filled('page_access_token') || (bool) $existing?->getDecryptedPageAccessToken();

        if (! $twilioReady && ! $hasPageToken) {
            return response()->json([
                'error' => 'Connect Twilio for Messenger, or add a Page Access Token to receive Instagram Direct via Meta webhooks.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'page_id' => ['required', 'string', 'max:64'],
            'page_name' => ['nullable', 'string', 'max:255'],
            'page_access_token' => ['nullable', 'string', 'max:4000'],
            'app_secret' => ['nullable', 'string', 'max:255'],
            'instagram_business_account_id' => ['nullable', 'string', 'max:64'],
            'instagram_username' => ['nullable', 'string', 'max:255'],
            'welcome_message' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $webhookKey = $existing?->webhook_key ?: Str::random(40);
        $verifyToken = $existing?->webhook_verify_token ?: Str::random(40);
        $pageId = TwilioService::stripChannelPrefix((string) $request->input('page_id'));
        $instagramSender = $request->filled('instagram_business_account_id')
            ? TwilioService::stripChannelPrefix((string) $request->input('instagram_business_account_id'))
            : ($existing?->instagram_business_account_id);

        $pageName = $request->input('page_name') ?: ($existing?->page_name);
        $instagramUsername = $request->has('instagram_username')
            ? ($request->input('instagram_username') ?: null)
            : ($existing?->instagram_username);
        $pageAccessToken = $request->filled('page_access_token')
            ? trim((string) $request->input('page_access_token'))
            : $existing?->getDecryptedPageAccessToken();

        if ($pageAccessToken) {
            try {
                $graph = app(FacebookGraphMessagingService::class);
                if ($request->filled('page_access_token')) {
                    $actor = $graph->tokenActor($pageAccessToken);
                    if ($graph->isExpiredTokenError($actor['error'] ?? null)) {
                        return response()->json([
                            'error' => $graph->expiredTokenMessage(),
                        ], 422);
                    }
                    if ($actor['ok'] && $actor['id'] !== '' && $actor['id'] !== $pageId) {
                        return response()->json([
                            'error' => $graph->mailboxPermissionMessage(),
                        ], 422);
                    }
                }

                $pageInfo = $graph->pageInfo($pageId, $pageAccessToken);
                $pageName = $pageName ?: ($pageInfo['name'] ?? null);
                $ig = is_array($pageInfo['instagram_business_account'] ?? null)
                    ? $pageInfo['instagram_business_account']
                    : [];
                if (! $instagramSender && ! empty($ig['id'])) {
                    $instagramSender = (string) $ig['id'];
                }
                if (! $instagramUsername && ! empty($ig['username'])) {
                    $instagramUsername = (string) $ig['username'];
                }
            } catch (\Throwable $e) {
                if ($request->filled('page_access_token')) {
                    $graph = app(FacebookGraphMessagingService::class);
                    $raw = $e->getMessage();
                    $message = $graph->isExpiredTokenError($raw)
                        ? $graph->expiredTokenMessage()
                        : ($graph->isMailboxPermissionError($raw)
                            ? $graph->mailboxPermissionMessage()
                            : $raw);

                    return response()->json(['error' => $message], 422);
                }
            }
        }

        $payload = [
            'page_id' => $pageId,
            'webhook_key' => $webhookKey,
            'webhook_verify_token' => $verifyToken,
            'page_name' => $pageName,
            'instagram_business_account_id' => $instagramSender ?: null,
            'instagram_username' => $instagramUsername,
            'welcome_message' => $request->has('welcome_message')
                ? $request->input('welcome_message')
                : ($existing?->welcome_message),
            'is_active' => true,
        ];

        if ($request->filled('page_access_token') && $pageAccessToken) {
            $payload['page_access_token'] = Crypt::encryptString($pageAccessToken);
        }

        if ($request->filled('app_secret')) {
            $payload['app_secret'] = Crypt::encryptString(trim((string) $request->input('app_secret')));
        }

        $integration = FacebookIntegration::updateOrCreate(
            ['company_id' => $company->id],
            $payload
        );
        Cache::forget('facebook-token-status-'.$integration->id);

        if ($pageAccessToken) {
            try {
                app(FacebookGraphMessagingService::class)->subscribePage($pageId, $pageAccessToken);
            } catch (\Throwable $e) {
                Log::warning('Facebook Page subscribe failed', ['error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'message' => 'Facebook & Instagram settings saved.',
            'integration' => [
                'id' => $integration->id,
                'page_id' => $integration->page_id,
                'page_name' => $integration->page_name,
                'instagram_business_account_id' => $integration->instagram_business_account_id,
                'instagram_username' => $integration->instagram_username,
                'welcome_message' => $integration->welcome_message,
                'webhook_url' => $integration->webhookUrl(),
                'webhook_verify_token' => $integration->webhook_verify_token,
                'webhook_set_at' => $integration->webhook_set_at,
                'is_active' => $integration->is_active,
                'has_page_access_token' => (bool) $integration->getDecryptedPageAccessToken(),
                'has_app_secret' => (bool) $integration->getDecryptedAppSecret(),
                'instagram_graph' => $integration->hasInstagramGraph(),
            ],
            'status' => 'connected',
        ]);
    }

    /**
     * Delete Facebook / Instagram messaging integration.
     */
    public function deleteFacebookIntegration(Request $request): JsonResponse
    {
        $company = $this->getCompany($request);

        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $integration = FacebookIntegration::where('company_id', $company->id)->first();

        if ($integration) {
            $integration->delete();
        }

        return response()->json(['message' => 'Facebook integration deleted successfully']);
    }

    /**
     * Get OpenAI integration for the current company.
     */
    public function getOpenAiIntegration(Request $request): JsonResponse
    {
        $company = $this->getCompany($request) ?? ($request->user()?->company_id ? Company::find($request->user()->company_id) : null);

        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $integration = OpenAIIntegration::where('company_id', $company->id)->first();

        if ($integration) {
            return response()->json([
                'integration' => [
                    'id' => $integration->id,
                    'company_id' => $integration->company_id,
                    'api_key' => $integration->api_key ? '***hidden***' : null,
                    'is_active' => $integration->is_active,
                    'created_at' => $integration->created_at,
                    'updated_at' => $integration->updated_at,
                ],
                'status' => ($integration->is_active && $integration->api_key) ? 'connected' : 'disconnected',
            ]);
        }

        return response()->json([
            'integration' => null,
            'status' => 'disconnected',
        ]);
    }

    /**
     * Store or update OpenAI integration for the current company.
     */
    public function storeOpenAiIntegration(Request $request): JsonResponse
    {
        $company = $this->getCompany($request) ?? ($request->user()?->company_id ? Company::find($request->user()->company_id) : null);

        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $existingIntegration = OpenAIIntegration::where('company_id', $company->id)->first();
        $apiKey = $request->input('api_key');

        $validator = Validator::make($request->all(), [
            'api_key' => [($existingIntegration && empty($apiKey)) ? 'nullable' : 'required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = ['is_active' => true];

        if (! empty($apiKey)) {
            $data['api_key'] = Crypt::encryptString($apiKey);
            // A company-provided key takes priority over the platform Main AI.
            $data['uses_main_ai'] = false;
        } elseif ($existingIntegration) {
            $data['api_key'] = $existingIntegration->api_key;
        } else {
            return response()->json(['errors' => ['api_key' => ['API key is required for new integrations.']]], 422);
        }

        $integration = OpenAIIntegration::updateOrCreate(
            ['company_id' => $company->id],
            $data
        );

        return response()->json([
            'message' => 'OpenAI integration saved successfully',
            'integration' => [
                'id' => $integration->id,
                'is_active' => $integration->is_active,
            ],
        ]);
    }

    /**
     * Delete OpenAI integration for the current company.
     */
    public function deleteOpenAiIntegration(Request $request): JsonResponse
    {
        $company = $this->getCompany($request) ?? ($request->user()?->company_id ? Company::find($request->user()->company_id) : null);

        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $integration = OpenAIIntegration::where('company_id', $company->id)->first();

        if ($integration) {
            $integration->delete();
        }

        return response()->json(['message' => 'OpenAI integration deleted successfully']);
    }

    /**
     * Get Gmail integration for the current company.
     */
    public function getGmailIntegration(Request $request): JsonResponse
    {
        $company = $this->getCompany($request);

        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $integration = GmailIntegration::where('company_id', $company->id)->first();

        if ($integration) {
            return response()->json([
                'integration' => [
                    'id' => $integration->id,
                    'company_id' => $integration->company_id,
                    'email' => $integration->email,
                    'app_password' => '***hidden***',
                    'is_active' => $integration->is_active,
                    'created_at' => $integration->created_at,
                    'updated_at' => $integration->updated_at,
                ],
                'status' => $integration->is_active ? 'connected' : 'disconnected',
            ]);
        }

        return response()->json([
            'integration' => null,
            'status' => 'disconnected',
        ]);
    }

    /**
     * Store or update Gmail integration for the current company.
     */
    public function storeGmailIntegration(Request $request): JsonResponse
    {
        $company = $this->getCompany($request);

        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $existingIntegration = GmailIntegration::where('company_id', $company->id)->first();
        $appPassword = $request->input('app_password');

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'app_password' => [($existingIntegration && empty($appPassword)) ? 'nullable' : 'required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = [
            'email' => $request->input('email'),
            'is_active' => true,
        ];

        if (! empty($appPassword)) {
            $data['app_password'] = Crypt::encryptString($appPassword);
        }

        $integration = GmailIntegration::updateOrCreate(
            ['company_id' => $company->id],
            $data
        );

        return response()->json([
            'message' => 'Gmail integration saved successfully',
            'integration' => [
                'id' => $integration->id,
                'email' => $integration->email,
                'is_active' => $integration->is_active,
            ],
        ]);
    }

    /**
     * Delete Gmail integration for the current company.
     */
    public function deleteGmailIntegration(Request $request): JsonResponse
    {
        $company = $this->getCompany($request);

        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $integration = GmailIntegration::where('company_id', $company->id)->first();

        if ($integration) {
            $integration->delete();
        }

        return response()->json(['message' => 'Gmail integration deleted successfully']);
    }

    public function microsoft365MailPage(OutlookMailService $mailService)
    {
        $companyId = auth()->user()?->company_id;
        $creds = $companyId ? $mailService->getMailCredentials($companyId) : [];

        return view('dashboard.microsoft-365-mail', [
            'outlookConfigured' => ! empty($creds['client_id']) && ! empty($creds['client_secret']),
        ]);
    }

    /**
     * Get Microsoft 365 outbound mail connection for quotation builder.
     */
    public function getMicrosoft365MailIntegration(Request $request, OutlookMailService $mailService): JsonResponse
    {
        $company = $this->getCompany($request);

        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $creds = $mailService->getMailCredentials($company->id);
        $inbox = SharedInbox::query()
            ->where('company_id', $company->id)
            ->where('type', SharedInbox::TYPE_QUOTATION)
            ->with('account')
            ->first();

        $connected = $inbox
            && $inbox->is_active
            && $inbox->outlook_mail_account_id
            && $inbox->account;

        if ($connected) {
            return response()->json([
                'integration' => [
                    'email' => $inbox->email ?: $inbox->account->email,
                    'name' => $inbox->name,
                    'connected_at' => $inbox->updated_at,
                ],
                'status' => 'connected',
                'outlook_configured' => ! empty($creds['client_id']) && ! empty($creds['client_secret']),
            ]);
        }

        return response()->json([
            'integration' => null,
            'status' => 'disconnected',
            'outlook_configured' => ! empty($creds['client_id']) && ! empty($creds['client_secret']),
        ]);
    }

    /**
     * Disconnect Microsoft 365 outbound mail for quotation builder.
     */
    public function deleteMicrosoft365MailIntegration(Request $request): JsonResponse
    {
        $company = $this->getCompany($request);

        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        SharedInbox::query()
            ->where('company_id', $company->id)
            ->where('type', SharedInbox::TYPE_QUOTATION)
            ->update([
                'outlook_mail_account_id' => null,
                'is_active' => false,
            ]);

        return response()->json(['message' => 'Microsoft 365 mailbox disconnected successfully']);
    }

    /**
     * Get Stripe integration for the current company.
     */
    public function getStripeIntegration(Request $request): JsonResponse
    {
        $company = $this->getCompany($request) ?? ($request->user()?->company_id ? Company::find($request->user()->company_id) : null);

        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $integration = StripeIntegration::where('company_id', $company->id)->first();

        if ($integration) {
            return response()->json([
                'integration' => [
                    'id' => $integration->id,
                    'company_id' => $integration->company_id,
                    'publishable_key' => $integration->publishable_key,
                    'secret_key' => $integration->secret_key ? '***hidden***' : null,
                    'webhook_secret' => $integration->webhook_secret ? '***hidden***' : null,
                    'is_active' => $integration->is_active,
                    'created_at' => $integration->created_at,
                    'updated_at' => $integration->updated_at,
                ],
                'status' => ($integration->is_active && $integration->secret_key) ? 'connected' : 'disconnected',
            ]);
        }

        return response()->json([
            'integration' => null,
            'status' => 'disconnected',
        ]);
    }

    /**
     * Store or update Stripe integration for the current company.
     */
    public function storeStripeIntegration(Request $request): JsonResponse
    {
        try {
            \Illuminate\Support\Facades\Log::info('Stripe store attempt', [
                'user_id' => $request->user()?->id,
                'company_id' => $request->user()?->company_id,
            ]);

            $company = $this->getCompany($request) ?? ($request->user()?->company_id ? Company::find($request->user()->company_id) : null);

            if (! $company) {
                return response()->json(['error' => 'Company not found. Please ensure you are logged in and have a company assigned.'], 404);
            }

            $existingIntegration = StripeIntegration::where('company_id', $company->id)->first();
            $secretKey = $request->input('secret_key');
            $webhookSecret = $request->input('webhook_secret');

            $validator = Validator::make($request->all(), [
                'publishable_key' => ['nullable', 'string'],
                'secret_key' => [($existingIntegration && empty($secretKey)) ? 'nullable' : 'required', 'string'],
                'webhook_secret' => ['nullable', 'string'],
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => 'Validation failed.', 'errors' => $validator->errors()], 422);
            }

            $data = [
                'publishable_key' => $request->input('publishable_key') ?: $existingIntegration?->publishable_key,
                'is_active' => true,
            ];

            if (! empty($secretKey)) {
                $data['secret_key'] = Crypt::encryptString($secretKey);
            } elseif ($existingIntegration) {
                $data['secret_key'] = $existingIntegration->secret_key;
            }

            if ($request->has('webhook_secret')) {
                $data['webhook_secret'] = $webhookSecret ? Crypt::encryptString($webhookSecret) : null;
            } elseif ($existingIntegration) {
                $data['webhook_secret'] = $existingIntegration->webhook_secret;
            }

            $integration = StripeIntegration::updateOrCreate(
                ['company_id' => $company->id],
                $data
            );

            return response()->json([
                'success' => true,
                'message' => 'Stripe integration saved successfully',
                'integration' => [
                    'id' => $integration->id,
                    'publishable_key' => $integration->publishable_key,
                    'is_active' => $integration->is_active,
                ],
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Stripe integration save failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Server error: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete Stripe integration for the current company.
     */
    public function deleteStripeIntegration(Request $request): JsonResponse
    {
        $company = $this->getCompany($request) ?? ($request->user()?->company_id ? Company::find($request->user()->company_id) : null);

        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $integration = StripeIntegration::where('company_id', $company->id)->first();

        if ($integration) {
            $integration->delete();
        }

        return response()->json(['message' => 'Stripe integration deleted successfully']);
    }

    /**
     * Get Wise integration for the current company.
     */
    public function getWiseIntegration(Request $request): JsonResponse
    {
        $company = $this->getCompany($request) ?? ($request->user()?->company_id ? Company::find($request->user()->company_id) : null);

        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $integration = WiseIntegration::where('company_id', $company->id)->first();

        if ($integration) {
            return response()->json([
                'integration' => [
                    'id' => $integration->id,
                    'company_id' => $integration->company_id,
                    'profile_id' => $integration->profile_id,
                    'api_token' => $integration->api_token ? '***hidden***' : null,
                    'is_sandbox' => $integration->is_sandbox,
                    'is_active' => $integration->is_active,
                    'created_at' => $integration->created_at,
                    'updated_at' => $integration->updated_at,
                ],
                'status' => $integration->is_active ? 'connected' : 'disconnected',
            ]);
        }

        return response()->json([
            'integration' => null,
            'status' => 'disconnected',
        ]);
    }

    /**
     * Store or update Wise integration for the current company.
     */
    public function storeWiseIntegration(Request $request): JsonResponse
    {
        $company = $this->getCompany($request) ?? ($request->user()?->company_id ? Company::find($request->user()->company_id) : null);

        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $existingIntegration = WiseIntegration::where('company_id', $company->id)->first();
        $validator = Validator::make($request->all(), [
            'api_token' => [$existingIntegration ? 'nullable' : 'required', 'string'],
            'profile_id' => ['nullable', 'string'],
            'is_sandbox' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = [
            'profile_id' => $request->input('profile_id') ?: $existingIntegration?->profile_id,
            'is_sandbox' => (bool) $request->input('is_sandbox', $existingIntegration?->is_sandbox ?? false),
            'is_active' => true,
        ];
        if ($request->filled('api_token')) {
            $data['api_token'] = Crypt::encryptString($request->input('api_token'));
        } elseif ($existingIntegration) {
            $data['api_token'] = $existingIntegration->api_token;
        }

        $integration = WiseIntegration::updateOrCreate(
            ['company_id' => $company->id],
            $data
        );

        return response()->json([
            'message' => 'Wise integration saved successfully',
            'integration' => [
                'id' => $integration->id,
                'profile_id' => $integration->profile_id,
                'is_active' => $integration->is_active,
            ],
        ]);
    }

    /**
     * Fetch Wise profiles from API (for Profile ID selection).
     * Accepts optional api_token + is_sandbox in the request body to preview profiles without saving.
     */
    public function getWiseProfiles(Request $request): JsonResponse
    {
        $company = $this->getCompany($request) ?? ($request->user()?->company_id ? Company::find($request->user()->company_id) : null);

        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $inlineToken = $request->input('api_token');
        $resolvedIsSandbox = null;
        $profileWarning = null;
        if ($inlineToken) {
            $isSandbox = (bool) $request->input('is_sandbox', false);
            $wise = new WiseService(null, $inlineToken, $isSandbox);
        } else {
            $wise = new WiseService($company->id);
            if (! $wise->isConfigured()) {
                return response()->json(['error' => 'Wise integration not configured. Save API token first.'], 400);
            }
        }

        $profiles = $wise->getProfiles();
        if (empty($profiles) && $inlineToken) {
            $originalError = \App\Services\WiseService::getLastProfileFetchError();
            $fallbackIsSandbox = ! (bool) $request->input('is_sandbox', false);
            $fallbackWise = new WiseService(null, $inlineToken, $fallbackIsSandbox);
            $fallbackProfiles = $fallbackWise->getProfiles();

            if (! empty($fallbackProfiles)) {
                $profiles = $fallbackProfiles;
                $resolvedIsSandbox = $fallbackIsSandbox;
                $profileWarning = $fallbackIsSandbox
                    ? 'This token is valid for Wise Sandbox, so Sandbox mode was enabled.'
                    : 'This token is valid for Wise Production, so Sandbox mode was disabled.';
            } else {
                // Restore the original error so the message matches the environment the user selected.
                \App\Services\WiseService::$lastProfileFetchError = $originalError;
            }
        }

        if (empty($profiles)) {
            $detail = \App\Services\WiseService::getLastProfileFetchError();
            $message = $detail
                ? "Could not fetch profiles: {$detail}"
                : 'Could not fetch profiles from Wise. Check your API token and that it has profile access.';

            return response()->json([
                'error' => $message,
                'profiles' => [],
            ], 400);
        }

        $list = array_map(function ($p) {
            $details = is_array($p['details'] ?? null) ? $p['details'] : [];
            $name = $p['fullName']
                ?? $p['businessName']
                ?? $details['name']
                ?? trim(($details['firstName'] ?? '').' '.($details['lastName'] ?? ''))
                ?: 'Profile #'.($p['id'] ?? '');
            $type = $p['type'] ?? 'personal';

            return ['id' => (string) ($p['id'] ?? ''), 'type' => $type, 'name' => $name];
        }, $profiles);

        return response()->json([
            'profiles' => $list,
            'resolved_is_sandbox' => $resolvedIsSandbox,
            'warning' => $profileWarning,
        ]);
    }

    /**
     * Fetch Wise recipients (for copying numeric recipient IDs into wise_account).
     */
    public function getWiseRecipients(Request $request): JsonResponse
    {
        $company = $this->getCompany($request) ?? ($request->user()?->company_id ? Company::find($request->user()->company_id) : null);

        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $wise = new WiseService($company->id);
        if (! $wise->isConfigured()) {
            return response()->json(['error' => 'Wise integration not configured. Save API token first.'], 400);
        }

        $recipients = $wise->getRecipients();
        if (empty($recipients)) {
            $detail = \App\Services\WiseService::getLastRecipientsFetchError();
            $message = $detail
                ? "Could not fetch recipients: {$detail}"
                : 'Could not fetch recipients. Ensure Profile ID is set and you have recipients in your Wise account.';

            return response()->json([
                'error' => $message,
                'recipients' => [],
            ], 400);
        }

        return response()->json(['recipients' => $recipients]);
    }

    /**
     * Update an employee's Wise account (recipient ID).
     */
    public function updateEmployeeWiseAccount(Request $request, User $user): JsonResponse
    {
        $company = $this->getCompany($request) ?? ($request->user()?->company_id ? Company::find($request->user()->company_id) : null);

        if (! $company || $user->company_id !== $company->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $wiseAccount = $request->input('wise_account');
        $wiseAccount = $wiseAccount ? trim((string) $wiseAccount) : null;
        $wiseCurrency = $request->input('wise_currency');
        $wiseCurrency = $wiseCurrency ? strtoupper(substr(trim((string) $wiseCurrency), 0, 3)) : null;

        if ($wiseAccount) {
            $taken = User::where('company_id', $company->id)
                ->where('id', '!=', $user->id)
                ->where('wise_account', $wiseAccount)
                ->exists();
            if ($taken) {
                return response()->json(['error' => 'This Wise recipient ID is already assigned to another employee.'], 422);
            }
        }

        $user->wise_account = $wiseAccount ?: null;
        $user->wise_currency = $wiseAccount ? ($wiseCurrency ?: null) : null;
        $user->save();

        return response()->json(['message' => 'Wise account updated.', 'wise_account' => $user->wise_account]);
    }

    /**
     * Get Wise recipient account requirements (dynamic form fields) for a currency.
     * Creates a quote and fetches requirements.
     */
    public function getWiseRecipientRequirements(Request $request): JsonResponse
    {
        $company = $this->getCompany($request) ?? ($request->user()?->company_id ? Company::find($request->user()->company_id) : null);

        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $wise = new WiseService($company->id);
        if (! $wise->isConfigured()) {
            return response()->json(['error' => 'Wise integration not configured.'], 400);
        }

        $currency = strtoupper(substr(trim((string) ($request->input('currency') ?? '')), 0, 3));
        if (strlen($currency) !== 3) {
            return response()->json(['error' => 'Valid 3-letter currency code is required.'], 422);
        }

        $quoteResult = $wise->createQuoteForRecipientRequirements($currency);
        if (! $quoteResult['success']) {
            return response()->json(['error' => $quoteResult['error'] ?? 'Failed to create quote.'], 400);
        }

        $reqResult = $wise->getAccountRequirements($quoteResult['quote_id']);
        if (! $reqResult['success']) {
            return response()->json(['error' => $reqResult['error'] ?? 'Failed to fetch requirements.'], 400);
        }

        return response()->json([
            'quote_id' => $quoteResult['quote_id'],
            'currency' => $currency,
            'requirements' => $reqResult['requirements'] ?? [],
        ]);
    }

    /**
     * Post account requirements to get refreshed fields (for refreshRequirementsOnChange).
     */
    public function postWiseRecipientRequirements(Request $request): JsonResponse
    {
        $company = $this->getCompany($request) ?? ($request->user()?->company_id ? Company::find($request->user()->company_id) : null);

        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $wise = new WiseService($company->id);
        if (! $wise->isConfigured()) {
            return response()->json(['error' => 'Wise integration not configured.'], 400);
        }

        $quoteId = $request->input('quote_id');
        $payload = $request->input('payload', []);

        if (! $quoteId || ! is_string($quoteId)) {
            return response()->json(['error' => 'quote_id is required.'], 422);
        }

        $result = $wise->postAccountRequirements($quoteId, is_array($payload) ? $payload : []);
        if (! $result['success']) {
            return response()->json(['error' => $result['error'] ?? 'Failed to refresh requirements.'], 400);
        }

        return response()->json(['requirements' => $result['requirements'] ?? []]);
    }

    /**
     * Create a Wise recipient.
     */
    public function createWiseRecipient(Request $request): JsonResponse
    {
        $company = $this->getCompany($request) ?? ($request->user()?->company_id ? Company::find($request->user()->company_id) : null);

        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $wise = new WiseService($company->id);
        if (! $wise->isConfigured()) {
            return response()->json(['error' => 'Wise integration not configured.'], 400);
        }

        $payload = $request->all();
        if (empty($payload['currency']) || empty($payload['type']) || empty($payload['accountHolderName'])) {
            return response()->json(['error' => 'currency, type, and accountHolderName are required.'], 422);
        }

        \Illuminate\Support\Facades\Log::info('Wise createRecipient payload', ['payload' => $payload]);

        $result = $wise->createRecipient($payload);
        if (! $result['success']) {
            return response()->json(['error' => $result['error'] ?? 'Failed to create recipient.'], 400);
        }

        $recipient = $result['recipient'] ?? [];
        $name = $recipient['name']['fullName'] ?? $recipient['accountHolderName'] ?? 'Recipient';
        $currency = $recipient['currency'] ?? $payload['currency'];

        return response()->json([
            'message' => 'Recipient created successfully.',
            'recipient' => [
                'id' => $result['recipient_id'],
                'name' => $name,
                'currency' => $currency,
                'accountSummary' => $recipient['accountSummary'] ?? null,
            ],
        ]);
    }

    /**
     * Add a Wise recipient by identifier (Wise @tag, email, or phone number).
     */
    public function createWiseContact(Request $request): JsonResponse
    {
        $company = $this->getCompany($request) ?? ($request->user()?->company_id ? Company::find($request->user()->company_id) : null);

        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $wise = new WiseService($company->id);
        if (! $wise->isConfigured()) {
            return response()->json(['error' => 'Wise integration not configured.'], 400);
        }

        $validated = $request->validate([
            'identifier' => 'required|string|max:255',
            'currency' => 'required|string|size:3',
        ]);

        $result = $wise->createContactByIdentifier($validated['identifier'], $validated['currency']);
        if (! $result['success']) {
            return response()->json(['error' => $result['error'] ?? 'Failed to add recipient.'], 400);
        }

        return response()->json([
            'message' => 'Recipient added successfully.',
            'recipient' => [
                'id' => $result['contact_id'],
                'name' => $result['name'] ?? null,
            ],
        ]);
    }

    /**
     * Delete Wise integration for the current company.
     */
    public function deleteWiseIntegration(Request $request): JsonResponse
    {
        $company = $this->getCompany($request) ?? ($request->user()?->company_id ? Company::find($request->user()->company_id) : null);

        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $integration = WiseIntegration::where('company_id', $company->id)->first();

        if ($integration) {
            $integration->delete();
        }

        return response()->json(['message' => 'Wise integration deleted successfully']);
    }

    /**
     * Get Storeganise integration for the current company.
     */
    public function getStoreganiseIntegration(Request $request): JsonResponse
    {
        $company = $this->getCompany($request);

        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $integration = StoreganiseIntegration::where('company_id', $company->id)->first();

        if ($integration) {
            return response()->json([
                'integration' => [
                    'id' => $integration->id,
                    'company_id' => $integration->company_id,
                    'business_code' => $integration->business_code,
                    'api_key' => $integration->api_key ? '***hidden***' : null,
                    'webhook_url' => $integration->webhookUrl(),
                    'is_active' => $integration->is_active,
                    'created_at' => $integration->created_at,
                    'updated_at' => $integration->updated_at,
                ],
                'status' => ($integration->is_active && $integration->api_key) ? 'connected' : 'disconnected',
            ]);
        }

        return response()->json([
            'integration' => null,
            'status' => 'disconnected',
        ]);
    }

    /**
     * Store or update Storeganise integration for the current company.
     */
    public function storeStoreganiseIntegration(Request $request): JsonResponse
    {
        $company = $this->getCompany($request);

        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $existingIntegration = StoreganiseIntegration::where('company_id', $company->id)->first();
        $apiKey = $request->input('api_key');
        $businessCode = strtolower(trim((string) $request->input('business_code', $existingIntegration?->business_code ?? '')));

        $validator = Validator::make($request->all(), [
            'business_code' => [$existingIntegration ? 'nullable' : 'required', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/'],
            'api_key' => [($existingIntegration && empty($apiKey)) ? 'nullable' : 'required', 'string'],
        ], [
            'business_code.regex' => 'Business code must contain only lowercase letters, numbers, and hyphens.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($businessCode === '') {
            return response()->json(['errors' => ['business_code' => ['Business code is required.']]], 422);
        }

        $plainApiKey = ! empty($apiKey) ? (string) $apiKey : null;
        if (! $plainApiKey && $existingIntegration?->api_key) {
            try {
                $plainApiKey = Crypt::decryptString($existingIntegration->api_key);
            } catch (\Exception $e) {
                return response()->json(['errors' => ['api_key' => ['Stored API key could not be read. Please enter it again.']]], 422);
            }
        }

        if (! $plainApiKey) {
            return response()->json(['errors' => ['api_key' => ['API key is required for new integrations.']]], 422);
        }

        $storeganise = new StoreganiseService(null, $businessCode, $plainApiKey);
        $validation = $storeganise->validateCredentials();
        if (! $validation['valid']) {
            return response()->json([
                'error' => $validation['error'] ?? 'Storeganise credentials could not be verified.',
            ], 422);
        }

        $webhookKey = $existingIntegration?->webhook_key ?: Str::random(40);

        $integration = StoreganiseIntegration::updateOrCreate(
            ['company_id' => $company->id],
            [
                'business_code' => $businessCode,
                'api_key' => Crypt::encryptString($plainApiKey),
                'webhook_key' => $webhookKey,
                'is_active' => true,
            ]
        );

        return response()->json([
            'message' => 'Storeganise integration saved successfully',
            'integration' => [
                'id' => $integration->id,
                'business_code' => $integration->business_code,
                'webhook_url' => $integration->webhookUrl(),
                'is_active' => $integration->is_active,
            ],
            'status' => 'connected',
        ]);
    }

    /**
     * Delete Storeganise integration for the current company.
     */
    public function deleteStoreganiseIntegration(Request $request): JsonResponse
    {
        $company = $this->getCompany($request);

        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $integration = StoreganiseIntegration::where('company_id', $company->id)->first();

        if ($integration) {
            $integration->delete();
        }

        return response()->json(['message' => 'Storeganise integration deleted successfully']);
    }

    /**
     * List Storeganise facilities (sites) for the current company.
     */
    public function getStoreganiseSites(Request $request): JsonResponse
    {
        $company = $this->getCompany($request);

        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $storeganise = new StoreganiseService($company->id);
        if (! $storeganise->isConfigured()) {
            return response()->json(['error' => 'Storeganise is not connected.'], 400);
        }

        $sites = $storeganise->listSites();
        if ($sites === []) {
            return response()->json([
                'error' => 'No facilities were returned from Storeganise. Check API permissions.',
                'sites' => [],
            ], 400);
        }

        return response()->json(['sites' => $sites]);
    }

    public function getFrontIntegration(Request $request): JsonResponse
    {
        $company = $this->getCompany($request);
        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $integration = FrontIntegration::query()->where('company_id', $company->id)->first();
        if (! $integration) {
            return response()->json(['integration' => null, 'status' => 'disconnected']);
        }

        return response()->json([
            'integration' => [
                'id' => $integration->id,
                'api_token' => $integration->api_token ? '***hidden***' : null,
                'is_active' => $integration->is_active,
                'last_import_at' => $integration->last_import_at?->toIso8601String(),
                'last_import_dry_run' => $integration->last_import_dry_run,
                'last_import_stats' => $integration->last_import_stats,
            ],
            'status' => $integration->isConnected() ? 'connected' : 'disconnected',
        ]);
    }

    public function storeFrontIntegration(Request $request): JsonResponse
    {
        $company = $this->getCompany($request);
        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $existing = FrontIntegration::query()->where('company_id', $company->id)->first();
        $apiToken = FrontApiClient::normalizeToken((string) $request->input('api_token', ''));

        $validator = Validator::make(['api_token' => $apiToken !== '' ? $apiToken : null], [
            'api_token' => [($existing && $apiToken === '') ? 'nullable' : 'required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => collect($validator->errors())->flatten()->first() ?: 'Invalid Front API token.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $verifyWarning = null;
        $data = ['is_active' => true];
        if ($apiToken !== '') {
            try {
                (new FrontApiClient($apiToken))->verifyConnection();
            } catch (\Throwable $e) {
                $verifyWarning = $e->getMessage();
            }

            $data['api_token'] = Crypt::encryptString($apiToken);
        } elseif ($existing) {
            $data['api_token'] = $existing->api_token;
        } else {
            return response()->json(['errors' => ['api_token' => ['API token is required for new integrations.']]], 422);
        }

        try {
            $integration = FrontIntegration::query()->updateOrCreate(
                ['company_id' => $company->id],
                $data
            );
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'front_integrations')) {
                return response()->json([
                    'error' => 'Front integration table is missing. Run php artisan migrate on the server.',
                ], 500);
            }

            throw $e;
        }

        return response()->json([
            'message' => 'Front integration saved successfully',
            'integration' => [
                'id' => $integration->id,
                'is_active' => $integration->is_active,
            ],
            'status' => $integration->isConnected() ? 'connected' : 'disconnected',
            'verify_warning' => $verifyWarning,
        ]);
    }

    public function deleteFrontIntegration(Request $request): JsonResponse
    {
        $company = $this->getCompany($request);
        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        FrontIntegration::query()->where('company_id', $company->id)->delete();

        return response()->json(['message' => 'Front integration deleted successfully']);
    }

    public function getFrontMappingOptions(Request $request, FrontTagImportService $importService): JsonResponse
    {
        $company = $this->getCompany($request);
        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $integration = FrontIntegration::query()->where('company_id', $company->id)->first();
        if (! $integration?->isConnected()) {
            return response()->json(['error' => 'Front is not connected.'], 400);
        }

        $token = $integration->getDecryptedApiToken();
        if (! $token) {
            return response()->json([
                'error' => 'Front token could not be read. Disconnect and save your API token again.',
            ], 400);
        }

        $preview = $importService->mappingPreview($company, new FrontApiClient($token));

        return response()->json($preview);
    }

    public function runFrontTagImport(Request $request, FrontTagImportService $importService): JsonResponse
    {
        @set_time_limit(0);

        $company = $this->getCompany($request);
        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $integration = FrontIntegration::query()->where('company_id', $company->id)->first();
        if (! $integration?->isConnected()) {
            return response()->json(['error' => 'Front is not connected. Save your API token first.'], 400);
        }

        $token = $integration->getDecryptedApiToken();
        if (! $token) {
            return response()->json([
                'error' => 'Front token could not be read. Disconnect and save your API token again.',
            ], 400);
        }

        $validated = $request->validate([
            'dry_run' => ['sometimes', 'boolean'],
            'include_private' => ['sometimes', 'boolean'],
            'inbox_map' => ['sometimes', 'array'],
            'front_inbox_id' => ['nullable', 'string', 'max:120'],
            'shared_inbox_id' => ['nullable', 'integer'],
            'persist_results' => ['sometimes', 'boolean'],
        ]);

        $options = [
            'dry_run' => (bool) ($validated['dry_run'] ?? false),
            'include_private' => (bool) ($validated['include_private'] ?? false),
            'inbox_map' => collect($validated['inbox_map'] ?? [])
                ->mapWithKeys(fn ($sharedId, $frontId) => [trim((string) $frontId) => (int) $sharedId])
                ->filter(fn ($sharedId, $frontId) => $frontId !== '' && $sharedId > 0)
                ->all(),
            'front_inbox_id' => isset($validated['front_inbox_id']) ? trim((string) $validated['front_inbox_id']) : null,
            'shared_inbox_id' => isset($validated['shared_inbox_id']) ? (int) $validated['shared_inbox_id'] : null,
            'persist_results' => (bool) ($validated['persist_results'] ?? true),
        ];

        try {
            $client = new FrontApiClient($token);
            $stats = $importService->importFromApi($company, $client, $options);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        if ($options['persist_results']) {
            $integration->forceFill([
                'last_import_stats' => $stats,
                'last_import_at' => now(),
                'last_import_dry_run' => (bool) $options['dry_run'],
            ])->save();
        }

        return response()->json([
            'message' => $options['dry_run'] ? 'Dry run completed.' : 'Front tag import completed.',
            'dry_run' => $options['dry_run'],
            'stats' => $stats,
            'last_import_at' => $options['persist_results']
                ? $integration->last_import_at?->toIso8601String()
                : now()->toIso8601String(),
        ]);
    }

    /**
     * Get company from the current request or the authenticated user.
     */
    private function getCompany(Request $request): ?Company
    {
        $company = $request->get('company');

        if ($company instanceof Company) {
            return $company;
        }

        if (app()->bound('company')) {
            try {
                $bound = app('company');
                if ($bound instanceof Company) {
                    return $bound;
                }
            } catch (\Exception $e) {
                // fall through to user company
            }
        }

        $user = $request->user();
        if ($user?->company_id) {
            return Company::find($user->company_id);
        }

        return null;
    }
}
