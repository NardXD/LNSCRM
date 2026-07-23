<?php

namespace App\Services;

use App\Models\SystemSetting;

class CalendarOauthSettingsService
{
    /**
     * Get OAuth credentials for a provider. Checks company settings first, then config.
     *
     * @return array{client_id: string|null, client_secret: string|null, redirect: string}
     */
    public function getCredentials(string $provider, ?int $companyId): array
    {
        $group = $companyId ? 'calendar_oauth_'.$companyId : 'calendar_oauth';
        $redirect = rtrim(config('app.url', 'http://localhost:8000'), '/');

        if ($provider === 'google') {
            $clientId = SystemSetting::getValue('google_client_id', null, $group)
                ?? config('services.google.client_id');
            $clientSecret = SystemSetting::getValue('google_client_secret', null, $group)
                ?? config('services.google.client_secret');
            $redirect .= '/calendar/connect/google/callback';

            return [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect' => $redirect,
            ];
        }

        if ($provider === 'outlook') {
            $clientId = SystemSetting::getValue('microsoft_client_id', null, $group)
                ?? config('services.microsoft.client_id');
            $clientSecret = SystemSetting::getValue('microsoft_client_secret', null, $group)
                ?? config('services.microsoft.client_secret');
            $redirect .= '/calendar/connect/outlook/callback';

            return [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect' => $redirect,
            ];
        }

        return ['client_id' => null, 'client_secret' => null, 'redirect' => $redirect];
    }

    /**
     * Check if a provider is configured.
     */
    public function isConfigured(string $provider, ?int $companyId): bool
    {
        $creds = $this->getCredentials($provider, $companyId);

        return ! empty($creds['client_id']) && ! empty($creds['client_secret']);
    }

    /**
     * Store OAuth credentials for a company.
     */
    public function storeCredentials(string $provider, ?int $companyId, array $data): void
    {
        $group = $companyId ? 'calendar_oauth_'.$companyId : 'calendar_oauth';

        if ($provider === 'google') {
            if (! empty($data['google_client_id'])) {
                SystemSetting::setValue('google_client_id', $data['google_client_id'], 'string', $group);
            }
            if (! empty($data['google_client_secret'])) {
                SystemSetting::setValue('google_client_secret', $data['google_client_secret'], 'string', $group);
            }
        }

        if ($provider === 'outlook') {
            if (! empty($data['microsoft_client_id'])) {
                SystemSetting::setValue('microsoft_client_id', $data['microsoft_client_id'], 'string', $group);
            }
            if (! empty($data['microsoft_client_secret'])) {
                SystemSetting::setValue('microsoft_client_secret', $data['microsoft_client_secret'], 'string', $group);
            }
        }
    }
}
