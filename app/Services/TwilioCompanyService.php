<?php

namespace App\Services;

use App\Models\Company;
use App\Models\TwilioFlexIntegration;
use App\Models\TwilioPhoneNumber;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

class TwilioCompanyService
{
    public function getActiveIntegration(Company $company): ?TwilioFlexIntegration
    {
        $integration = TwilioFlexIntegration::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->first();

        if (! $integration || ! app(TwilioIntegrationValidator::class)->isComplete($integration)) {
            return null;
        }

        return $integration;
    }

    public function getIntegrationByAccountSid(?string $accountSid): ?TwilioFlexIntegration
    {
        if (! $accountSid) {
            return null;
        }

        $integration = TwilioFlexIntegration::query()
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
    public function getCredentials(TwilioFlexIntegration $integration): ?array
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

    public function getClientForIntegration(TwilioFlexIntegration $integration): ?Client
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

            $user = User::query()
                ->where(function ($query) use ($normalized) {
                    $query->where('twilio_number', $normalized)
                        ->orWhere('twilio_sms_number', $normalized);
                })
                ->first();
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
            ->whereNotNull('sms_assigned_user_id')
            ->with('smsAssignedUser')
            ->first();
        if ($inventory?->smsAssignedUser) {
            return $inventory->smsAssignedUser;
        }

        return User::query()->where('twilio_sms_number', $normalized)->first();
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

    /**
     * Import numbers owned by the company's Twilio account into local inventory.
     */
    public function syncOwnedNumbers(Company $company): int
    {
        $integration = $this->getActiveIntegration($company);
        if (! $integration) {
            return 0;
        }

        $credentials = $this->getCredentials($integration);
        if (! $credentials) {
            return 0;
        }

        $twilio = new TwilioService($credentials['sid'], $credentials['token']);
        $owned = $twilio->listOwnedNumbers();
        $voiceUrl = route('twilio.voice');
        $smsUrl = route('twilio.sms-webhook');
        $synced = 0;

        foreach ($owned as $item) {
            $normalized = $this->normalizePhone($item['phone_number']);
            TwilioPhoneNumber::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'phone_number' => $normalized,
                ],
                [
                    'twilio_sid' => $item['sid'],
                    'friendly_name' => $item['friendly_name'],
                    'capabilities' => $item['capabilities'],
                ]
            );

            if (! empty($item['sid']) && $this->canPushTwilioWebhooks($voiceUrl)) {
                try {
                    $twilio->updateNumberWebhooks($item['sid'], $voiceUrl, $smsUrl);
                } catch (\Throwable $e) {
                    Log::warning('Twilio webhook update skipped during number sync', [
                        'company_id' => $company->id,
                        'phone_number' => $normalized,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $synced++;
        }

        return $synced;
    }

    protected function canPushTwilioWebhooks(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return $host && ! in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }
}
