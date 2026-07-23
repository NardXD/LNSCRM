<?php

namespace App\Services;

use App\Models\Company;
use App\Models\TwilioIntegration;
use App\Models\TwilioPhoneNumber;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

class TwilioCompanyService
{
    public function getActiveIntegration(Company $company): ?TwilioIntegration
    {
        $integration = TwilioIntegration::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->first();

        if (! $integration || ! app(TwilioIntegrationValidator::class)->isComplete($integration)) {
            return null;
        }

        return $integration;
    }

    public function getIntegrationByAccountSid(?string $accountSid): ?TwilioIntegration
    {
        if (! $accountSid) {
            return null;
        }

        $integration = TwilioIntegration::query()
            ->where('account_sid', $accountSid)
            ->where('is_active', true)
            ->first();

        if (! $integration || ! app(TwilioIntegrationValidator::class)->isComplete($integration)) {
            return null;
        }

        return $integration;
    }

    /**
     * @return array{sid: string, token: string}|null
     */
    public function getCredentials(TwilioIntegration $integration): ?array
    {
        try {
            $sid = $integration->account_sid;
            $token = $integration->auth_token ? Crypt::decryptString($integration->auth_token) : null;

            if (! $sid || ! $token) {
                return null;
            }

            return ['sid' => $sid, 'token' => $token];
        } catch (\Exception $e) {
            Log::error('Twilio credential decryption failed', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function getClientForCompany(Company $company): ?Client
    {
        $integration = $this->getActiveIntegration($company);
        if (! $integration) {
            return null;
        }

        $credentials = $this->getCredentials($integration);
        if (! $credentials) {
            return null;
        }

        return (new TwilioService($credentials['sid'], $credentials['token']))->getTwilioClient();
    }

    public function getClientForIntegration(TwilioIntegration $integration): ?Client
    {
        $credentials = $this->getCredentials($integration);
        if (! $credentials) {
            return null;
        }

        return (new TwilioService($credentials['sid'], $credentials['token']))->getTwilioClient();
    }

    public function resolveCompanyFromWebhook(?string $accountSid, ?string $to, ?string $from): ?Company
    {
        if ($accountSid) {
            $integration = $this->getIntegrationByAccountSid($accountSid);
            if ($integration) {
                return $integration->company;
            }
        }

        foreach ([$to, $from] as $number) {
            if (! $number) {
                continue;
            }

            $normalized = $this->normalizePhone($number);
            $inventory = TwilioPhoneNumber::query()
                ->where('phone_number', $normalized)
                ->first();
            if ($inventory) {
                return $inventory->company;
            }

            $user = User::query()->where('twilio_number', $normalized)->first();
            if ($user?->company) {
                return $user->company;
            }
        }

        return null;
    }

    public function resolveUserFromNumbers(?string $to, ?string $from, string $direction): ?User
    {
        $companyNumber = strtolower($direction) === 'inbound' ? $to : $from;
        if (! $companyNumber) {
            return null;
        }

        $normalized = $this->normalizePhone($companyNumber);

        $inventory = TwilioPhoneNumber::query()
            ->where('phone_number', $normalized)
            ->whereNotNull('assigned_user_id')
            ->first();
        if ($inventory?->assignedUser) {
            return $inventory->assignedUser;
        }

        return User::query()->where('twilio_number', $normalized)->first();
    }

    public function normalizePhone(string $number): string
    {
        $trimmed = trim($number);
        if (str_starts_with($trimmed, 'client:')) {
            return $trimmed;
        }

        $digits = preg_replace('/[^\d+]/', '', $trimmed) ?? $trimmed;
        if ($digits !== '' && ! str_starts_with($digits, '+')) {
            $digits = '+'.$digits;
        }

        return $digits;
    }
}
