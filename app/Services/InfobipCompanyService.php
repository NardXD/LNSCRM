<?php

namespace App\Services;

use App\Models\Company;
use App\Models\InfobipIntegration;
use App\Models\InfobipPhoneNumber;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class InfobipCompanyService
{
    public function getActiveIntegration(Company $company): ?InfobipIntegration
    {
        $integration = InfobipIntegration::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->first();

        if (! $integration || ! app(InfobipIntegrationValidator::class)->isComplete($integration)) {
            return null;
        }

        return $integration;
    }

    /**
     * @return array{base_url: string, api_key: string}|null
     */
    public function getCredentials(InfobipIntegration $integration): ?array
    {
        try {
            $baseUrl = $integration->base_url;
            $apiKey = $integration->api_key ? Crypt::decryptString($integration->api_key) : null;

            if (! $baseUrl || ! $apiKey) {
                return null;
            }

            return ['base_url' => $baseUrl, 'api_key' => $apiKey];
        } catch (\Exception $e) {
            Log::error('Infobip credential decryption failed', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function getServiceForCompany(Company $company): ?InfobipService
    {
        $integration = $this->getActiveIntegration($company);
        if (! $integration) {
            return null;
        }

        return $this->getServiceForIntegration($integration);
    }

    public function getServiceForIntegration(InfobipIntegration $integration): ?InfobipService
    {
        $credentials = $this->getCredentials($integration);
        if (! $credentials) {
            return null;
        }

        return new InfobipService($credentials['base_url'], $credentials['api_key']);
    }

    public function getDecryptedWebhookSecret(InfobipIntegration $integration): ?string
    {
        if (! $integration->webhook_secret) {
            return null;
        }

        try {
            return Crypt::decryptString($integration->webhook_secret);
        } catch (\Exception) {
            return null;
        }
    }

    public function validateWebhookSecret(?string $provided, InfobipIntegration $integration): bool
    {
        $expected = $this->getDecryptedWebhookSecret($integration);
        if ($expected === null || $expected === '') {
            return true;
        }

        return hash_equals($expected, (string) $provided);
    }

    public function resolveCompanyFromNumber(?string $to, ?string $from = null): ?Company
    {
        foreach ([$to, $from] as $number) {
            if (! $number) {
                continue;
            }

            $normalized = $this->normalizePhone($number);
            $inventory = InfobipPhoneNumber::query()
                ->where('phone_number', $normalized)
                ->first();
            if ($inventory) {
                return $inventory->company;
            }

            $user = User::query()->where('phone_system_number', $normalized)->first();
            if ($user?->company) {
                return $user->company;
            }

            $byDefault = InfobipIntegration::query()
                ->where('is_active', true)
                ->where('default_from_number', $normalized)
                ->first();
            if ($byDefault) {
                return $byDefault->company;
            }
        }

        return null;
    }

    public function resolveCompanyFromIntegrationId(?int $integrationId): ?Company
    {
        if (! $integrationId) {
            return null;
        }

        $integration = InfobipIntegration::query()
            ->where('id', $integrationId)
            ->where('is_active', true)
            ->first();

        return $integration?->company;
    }

    public function resolveUserFromNumbers(?string $to, ?string $from, string $direction): ?User
    {
        $companyNumber = strtolower($direction) === 'inbound' ? $to : $from;
        if (! $companyNumber) {
            return null;
        }

        $normalized = $this->normalizePhone($companyNumber);

        $inventory = InfobipPhoneNumber::query()
            ->where('phone_number', $normalized)
            ->whereNotNull('assigned_user_id')
            ->first();
        if ($inventory?->assignedUser) {
            return $inventory->assignedUser;
        }

        return User::query()->where('phone_system_number', $normalized)->first();
    }

    public function webrtcIdentityForUser(User $user): string
    {
        return 'user-'.$user->id;
    }

    public function normalizePhone(string $number): string
    {
        $trimmed = trim($number);
        if (str_starts_with($trimmed, 'client:')) {
            return $trimmed;
        }

        if (str_starts_with(strtolower($trimmed), 'whatsapp:')) {
            $trimmed = substr($trimmed, strlen('whatsapp:'));
        }

        $digits = preg_replace('/[^\d+]/', '', $trimmed) ?? $trimmed;
        if ($digits !== '' && ! str_starts_with($digits, '+')) {
            $digits = '+'.$digits;
        }

        return $digits;
    }
}
