<?php

namespace App\Services;

use App\Models\TwilioIntegration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Twilio\Exceptions\RestException;
use Twilio\Rest\Client;

class TwilioIntegrationValidator
{
    /** Required for SMS / WhatsApp / Viber messaging. */
    public const REQUIRED_FIELDS = [
        'account_sid' => 'Account SID',
        'auth_token' => 'Auth Token',
    ];

    /** Optional — only needed for browser-based calling. */
    public const VOICE_FIELDS = [
        'app_sid' => 'App SID',
        'api_key' => 'API Key',
        'api_secret' => 'API Secret',
    ];

    public function isComplete(TwilioIntegration $integration): bool
    {
        return $this->missingFields($integration) === [];
    }

    public function isVoiceReady(TwilioIntegration $integration): bool
    {
        return $this->missingVoiceFields($integration) === [];
    }

    /**
     * @return array<int, string>
     */
    public function missingFields(TwilioIntegration $integration): array
    {
        $missing = [];

        foreach (array_keys(self::REQUIRED_FIELDS) as $field) {
            if (empty($integration->{$field})) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * @return array<int, string>
     */
    public function missingVoiceFields(TwilioIntegration $integration): array
    {
        $missing = [];

        foreach (array_keys(self::VOICE_FIELDS) as $field) {
            if (empty($integration->{$field})) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * @return array<string, string>
     */
    public function missingFieldsFromPlain(array $plain): array
    {
        $missing = [];

        foreach (self::REQUIRED_FIELDS as $field => $label) {
            if (empty($plain[$field])) {
                $missing[$field] = "{$label} is required.";
            }
        }

        return $missing;
    }

    /**
     * @return array{
     *     account_sid: string,
     *     auth_token: ?string,
     *     app_sid: ?string,
     *     api_key: ?string,
     *     api_secret: ?string
     * }
     */
    public function resolvePlainCredentials(
        ?TwilioIntegration $existing,
        string $accountSid,
        ?string $authTokenInput,
        ?string $appSidInput,
        ?string $apiKeyInput,
        ?string $apiSecretInput
    ): array {
        $appSid = trim((string) ($appSidInput ?: ($existing?->app_sid ?? '')));
        $apiKey = trim((string) ($apiKeyInput ?: ($existing?->api_key ?? '')));

        return [
            'account_sid' => trim($accountSid),
            'auth_token' => $this->resolveSecret($authTokenInput, $existing?->auth_token),
            'app_sid' => $appSid !== '' ? $appSid : null,
            'api_key' => $apiKey !== '' ? $apiKey : null,
            'api_secret' => $this->resolveSecret($apiSecretInput, $existing?->api_secret),
        ];
    }

    /**
     * @param array{
     *     account_sid: string,
     *     auth_token: string,
     *     app_sid: ?string,
     *     api_key: ?string,
     *     api_secret: ?string
     * } $plain
     * @return array{valid: bool, errors: array<string, string>}
     */
    public function validateWithTwilio(array $plain): array
    {
        $errors = [];

        if (! str_starts_with($plain['account_sid'], 'AC')) {
            $errors['account_sid'] = 'Account SID must start with AC.';
        }

        if (! empty($plain['app_sid']) && ! str_starts_with($plain['app_sid'], 'AP')) {
            $errors['app_sid'] = 'App SID must start with AP.';
        }

        if (! empty($plain['api_key']) && ! str_starts_with($plain['api_key'], 'SK')) {
            $errors['api_key'] = 'API Key must start with SK.';
        }

        if ($errors !== []) {
            return ['valid' => false, 'errors' => $errors];
        }

        try {
            $client = new Client($plain['account_sid'], $plain['auth_token']);
            $account = $client->api->v2010->accounts($plain['account_sid'])->fetch();

            if ($account->sid !== $plain['account_sid']) {
                $errors['account_sid'] = 'Account SID does not match the authenticated Twilio account.';
            }
        } catch (RestException $e) {
            Log::warning('Twilio account verification failed', [
                'status' => $e->getStatusCode(),
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
            ]);

            // Twilio Test Credentials (Account → API keys → Test Credentials) cannot call live APIs.
            if ((int) $e->getCode() === 20008 || str_contains($e->getMessage(), 'Test Account Credentials')) {
                $errors['auth_token'] = 'Those look like Twilio Test Credentials. Use your live Account SID + Auth Token from Account → API keys & tokens (not the Test Credentials section). WhatsApp Sandbox still uses live credentials.';

                return ['valid' => false, 'errors' => $errors];
            }

            $errors['auth_token'] = $this->friendlyTwilioError($e, 'Invalid Account SID or Auth Token.');

            return ['valid' => false, 'errors' => $errors];
        } catch (\Throwable $e) {
            Log::warning('Twilio account verification threw', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
            $detail = trim($e->getMessage());
            $errors['auth_token'] = $detail !== ''
                ? 'Could not reach Twilio: '.$detail
                : 'Could not verify credentials with Twilio. Please try again.';

            return ['valid' => false, 'errors' => $errors];
        }

        if (! empty($plain['app_sid'])) {
            try {
                $client->applications($plain['app_sid'])->fetch();
            } catch (RestException $e) {
                $errors['app_sid'] = $this->friendlyTwilioError($e, 'App SID was not found in this Twilio account.');

                return ['valid' => false, 'errors' => $errors];
            }
        }

        if (! empty($plain['api_key']) || ! empty($plain['api_secret'])) {
            if (empty($plain['api_key']) || empty($plain['api_secret'])) {
                $errors['api_secret'] = 'API Key and API Secret must both be provided together.';

                return ['valid' => false, 'errors' => $errors];
            }

            try {
                $apiClient = new Client($plain['api_key'], $plain['api_secret'], $plain['account_sid']);
                $apiClient->api->v2010->accounts($plain['account_sid'])->fetch();
            } catch (RestException $e) {
                $errors['api_secret'] = $this->friendlyTwilioError($e, 'Invalid API Key or API Secret for this account.');

                return ['valid' => false, 'errors' => $errors];
            }
        }

        return ['valid' => true, 'errors' => []];
    }

    private function resolveSecret(?string $input, ?string $encryptedExisting): ?string
    {
        if ($input !== null && trim($input) !== '') {
            return trim($input);
        }

        if (! $encryptedExisting) {
            return null;
        }

        try {
            return Crypt::decryptString($encryptedExisting);
        } catch (\Exception) {
            return null;
        }
    }

    private function friendlyTwilioError(RestException $e, string $fallback): string
    {
        if (in_array($e->getStatusCode(), [401, 403, 404], true)) {
            return $fallback;
        }

        $message = trim($e->getMessage());

        return $message !== '' ? $message : $fallback;
    }
}
