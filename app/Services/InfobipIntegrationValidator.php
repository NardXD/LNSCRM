<?php

namespace App\Services;

use App\Models\InfobipIntegration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InfobipIntegrationValidator
{
    public const REQUIRED_FIELDS = [
        'base_url' => 'Base URL',
        'api_key' => 'API Key',
    ];

    public const VOICE_FIELDS = [
        'application_id' => 'Application ID',
    ];

    public function isComplete(InfobipIntegration $integration): bool
    {
        return $this->missingFields($integration) === [];
    }

    public function isVoiceReady(InfobipIntegration $integration): bool
    {
        return $this->isComplete($integration) && $this->missingVoiceFields($integration) === [];
    }

    /**
     * @return array<int, string>
     */
    public function missingFields(InfobipIntegration $integration): array
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
    public function missingVoiceFields(InfobipIntegration $integration): array
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
     *     base_url: string,
     *     api_key: ?string,
     *     application_id: ?string,
     *     default_from_number: ?string,
     *     webhook_secret: ?string
     * }
     */
    public function resolvePlainCredentials(
        ?InfobipIntegration $existing,
        string $baseUrl,
        ?string $apiKeyInput,
        ?string $applicationIdInput,
        ?string $defaultFromNumberInput,
        ?string $webhookSecretInput
    ): array {
        $applicationId = trim((string) ($applicationIdInput ?: ($existing?->application_id ?? '')));
        $defaultFrom = trim((string) ($defaultFromNumberInput ?: ($existing?->default_from_number ?? '')));
        $webhookSecret = $this->resolveSecret($webhookSecretInput, $existing?->webhook_secret);

        return [
            'base_url' => rtrim(trim($baseUrl), '/'),
            'api_key' => $this->resolveSecret($apiKeyInput, $existing?->api_key),
            'application_id' => $applicationId !== '' ? $applicationId : null,
            'default_from_number' => $defaultFrom !== '' ? $defaultFrom : null,
            'webhook_secret' => $webhookSecret,
        ];
    }

    /**
     * @param array{
     *     base_url: string,
     *     api_key: string,
     *     application_id: ?string,
     *     default_from_number: ?string,
     *     webhook_secret: ?string
     * } $plain
     * @return array{valid: bool, errors: array<string, string>}
     */
    public function validateWithInfobip(array $plain): array
    {
        $errors = [];

        if (! filter_var($plain['base_url'], FILTER_VALIDATE_URL)) {
            $errors['base_url'] = 'Base URL must be a valid URL (e.g. https://xxxxx.api.infobip.com).';
        }

        if ($errors !== []) {
            return ['valid' => false, 'errors' => $errors];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'App '.$plain['api_key'],
                'Accept' => 'application/json',
            ])
                ->timeout(20)
                ->get($plain['base_url'].'/account/1/balance');

            if ($response->status() === 401 || $response->status() === 403) {
                $errors['api_key'] = 'Invalid Infobip API key or base URL.';

                return ['valid' => false, 'errors' => $errors];
            }

            // Some accounts may not expose balance; accept any non-auth failure as reachable.
            if ($response->status() >= 500) {
                $errors['base_url'] = 'Infobip API is currently unavailable. Please try again.';

                return ['valid' => false, 'errors' => $errors];
            }
        } catch (\Throwable $e) {
            Log::warning('Infobip account verification threw', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            $detail = trim($e->getMessage());
            $errors['api_key'] = $detail !== ''
                ? 'Could not reach Infobip: '.$detail
                : 'Could not verify credentials with Infobip. Please try again.';

            return ['valid' => false, 'errors' => $errors];
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
}
