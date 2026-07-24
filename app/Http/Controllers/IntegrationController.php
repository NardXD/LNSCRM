<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\GmailIntegration;
use App\Models\OpenAIIntegration;
use App\Models\StripeIntegration;
use App\Models\TwilioIntegration;
use App\Models\User;
use App\Models\ViberIntegration;
use App\Models\WiseIntegration;
use App\Services\TwilioIntegrationValidator;
use App\Services\ViberBotService;
use App\Services\WiseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
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
     * Get Twilio integration for the current company.
     */
    public function getTwilioIntegration(Request $request): JsonResponse
    {
        $company = $this->getCompany($request);

        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $integration = TwilioIntegration::where('company_id', $company->id)->first();

        if ($integration) {
            $validator = app(TwilioIntegrationValidator::class);
            $isConnected = $integration->is_active && $validator->isComplete($integration);

            return response()->json([
                'integration' => [
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
                ],
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
     * Store or update Twilio integration for the current company.
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

        $existingIntegration = TwilioIntegration::where('company_id', $company->id)->first();
        $twilioValidator = app(TwilioIntegrationValidator::class);

        $plain = $twilioValidator->resolvePlainCredentials(
            $existingIntegration,
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

            return response()->json([
                'error' => 'Twilio credentials could not be verified.',
                'errors' => $errors,
            ], 422);
        }

        $integration = TwilioIntegration::updateOrCreate(
            ['company_id' => $company->id],
            [
                'account_sid' => $plain['account_sid'],
                'auth_token' => Crypt::encryptString($plain['auth_token']),
                'app_sid' => $plain['app_sid'],
                'api_key' => $plain['api_key'],
                'api_secret' => Crypt::encryptString($plain['api_secret']),
                'is_active' => true,
            ]
        );

        return response()->json([
            'message' => 'Twilio integration saved and verified successfully',
            'status' => 'connected',
            'integration' => [
                'id' => $integration->id,
                'account_sid' => $integration->account_sid,
                'app_sid' => $integration->app_sid,
                'api_key' => $integration->api_key,
                'is_active' => $integration->is_active,
            ],
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

        $integration = TwilioIntegration::where('company_id', $company->id)->first();

        if ($integration) {
            $integration->delete();
        }

        return response()->json(['message' => 'Twilio integration deleted successfully']);
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

        if ($integration) {
            return response()->json([
                'integration' => [
                    'id' => $integration->id,
                    'company_id' => $integration->company_id,
                    'auth_token' => $integration->auth_token ? '***hidden***' : null,
                    'bot_name' => $integration->bot_name,
                    'bot_uri' => $integration->bot_uri,
                    'bot_avatar' => $integration->bot_avatar,
                    'welcome_message' => $integration->welcome_message,
                    'webhook_url' => $integration->webhookUrl(),
                    'webhook_set_at' => $integration->webhook_set_at,
                    'is_active' => $integration->is_active,
                    'created_at' => $integration->created_at,
                    'updated_at' => $integration->updated_at,
                ],
                'status' => ($integration->is_active && $integration->auth_token) ? 'connected' : 'disconnected',
            ]);
        }

        return response()->json([
            'integration' => null,
            'status' => 'disconnected',
        ]);
    }

    /**
     * Store or update Viber Business integration for the current company.
     */
    public function storeViberIntegration(Request $request): JsonResponse
    {
        $company = $this->getCompany($request);

        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'auth_token' => ['nullable', 'string', 'min:10'],
            'welcome_message' => ['nullable', 'string', 'max:1000'],
            'set_webhook' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $existing = ViberIntegration::where('company_id', $company->id)->first();
        $plainToken = $request->input('auth_token');

        if (! $plainToken && $existing) {
            $plainToken = $existing->getDecryptedAuthToken();
        }

        if (! $plainToken) {
            return response()->json(['error' => 'Viber authentication token is required.'], 422);
        }

        try {
            $service = new ViberBotService($plainToken);
            $account = $service->getAccountInfo();
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Viber credentials could not be verified.',
                'details' => $e->getMessage(),
            ], 422);
        }

        $webhookKey = $existing?->webhook_key ?: Str::random(40);
        $integration = ViberIntegration::updateOrCreate(
            ['company_id' => $company->id],
            [
                'auth_token' => Crypt::encryptString($plainToken),
                'webhook_key' => $webhookKey,
                'bot_name' => $account['name'] ?? ($existing?->bot_name),
                'bot_uri' => $account['uri'] ?? ($existing?->bot_uri),
                'bot_avatar' => $account['icon'] ?? ($existing?->bot_avatar),
                'welcome_message' => $request->has('welcome_message')
                    ? $request->input('welcome_message')
                    : ($existing?->welcome_message),
                'is_active' => true,
            ]
        );

        $webhookSet = false;
        $webhookError = null;
        if ($request->boolean('set_webhook', true)) {
            try {
                $service->setWebhook($integration->webhookUrl(), [
                    'delivered',
                    'seen',
                    'failed',
                    'subscribed',
                    'unsubscribed',
                    'conversation_started',
                ]);
                $integration->webhook_set_at = now();
                $integration->save();
                $webhookSet = true;
            } catch (\Throwable $e) {
                $webhookError = $e->getMessage();
            }
        }

        return response()->json([
            'message' => 'Viber integration saved successfully',
            'integration' => [
                'id' => $integration->id,
                'bot_name' => $integration->bot_name,
                'bot_uri' => $integration->bot_uri,
                'bot_avatar' => $integration->bot_avatar,
                'welcome_message' => $integration->welcome_message,
                'webhook_url' => $integration->webhookUrl(),
                'webhook_set_at' => $integration->webhook_set_at,
                'is_active' => $integration->is_active,
            ],
            'status' => 'connected',
            'webhook_set' => $webhookSet,
            'webhook_error' => $webhookError,
        ]);
    }

    /**
     * Delete Viber Business integration for the current company.
     */
    public function deleteViberIntegration(Request $request): JsonResponse
    {
        $company = $this->getCompany($request);

        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $integration = ViberIntegration::where('company_id', $company->id)->first();

        if ($integration) {
            try {
                ViberBotService::forIntegration($integration)->removeWebhook();
            } catch (\Throwable) {
                // Best-effort webhook cleanup
            }
            $integration->delete();
        }

        return response()->json(['message' => 'Viber integration deleted successfully']);
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
                return null;
            }
        }

        return null;
    }
}
