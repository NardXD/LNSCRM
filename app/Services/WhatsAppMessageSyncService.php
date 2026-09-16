<?php

namespace App\Services;

use App\Models\FacebookIntegration;
use App\Models\WhatsAppIntegration;
use Illuminate\Support\Facades\Log;

class WhatsAppMessageSyncService
{
    public function __construct(protected WhatsAppCloudApiService $cloud) {}

    /**
     * Cloud API has no inbox history endpoint. Manual sync verifies the Meta token
     * and re-registers the inbound webhook callback.
     *
     * @return array{scanned: int, imported: int, skipped: int, mode: string, webhook_registered: bool}
     */
    public function sync(WhatsAppIntegration $integration, int $days = 30, int $limit = 500): array
    {
        $token = $integration->getDecryptedAccessToken();
        $phoneNumberId = (string) $integration->phone_number_id;
        if (! $token || $phoneNumberId === '') {
            throw new \RuntimeException('WhatsApp Cloud API is not connected. Configure it under Integrations.');
        }

        $this->cloud->phoneNumberInfo($phoneNumberId, $token);
        $this->ensureWebhooks($integration, $token);

        return [
            'scanned' => 0,
            'imported' => 0,
            'skipped' => 0,
            'mode' => 'webhook',
            'webhook_registered' => true,
            'days' => $days,
            'limit' => $limit,
        ];
    }

    public function ensureWebhooks(WhatsAppIntegration $integration, ?string $token = null): void
    {
        $token = $token ?: $integration->getDecryptedAccessToken();
        $phoneNumberId = (string) $integration->phone_number_id;
        if (! $token || $phoneNumberId === '') {
            throw new \RuntimeException('WhatsApp Cloud API is not connected. Configure it under Integrations.');
        }

        $wabaId = (string) ($integration->waba_id ?: '');
        if ($wabaId === '') {
            $wabaId = (string) ($this->cloud->wabaIdForPhoneNumber($phoneNumberId, $token) ?: '');
            if ($wabaId !== '' && $integration->waba_id !== $wabaId) {
                $integration->waba_id = $wabaId;
                $integration->save();
            }
        }

        $facebook = FacebookIntegration::query()
            ->where('company_id', $integration->company_id)
            ->where('is_active', true)
            ->first();

        $appSecret = $integration->getDecryptedAppSecret() ?: $facebook?->getDecryptedAppSecret();
        $appId = is_string($facebook?->app_id ?? null) && $facebook->app_id !== ''
            ? (string) $facebook->app_id
            : null;

        $attempts = [[
            $integration->webhookUrl(),
            (string) ($integration->webhook_verify_token ?: ''),
        ]];

        // Same Meta app already delivers Facebook/Instagram to this callback.
        // Register WhatsApp there too so inbound chats are not dropped.
        if ($facebook) {
            $facebookUrl = $facebook->webhookUrl();
            $facebookToken = (string) ($facebook->webhook_verify_token ?: '');
            if ($facebookUrl !== '' && $facebookToken !== '' && $facebookUrl !== $attempts[0][0]) {
                $attempts[] = [$facebookUrl, $facebookToken];
            }
        }

        $lastError = null;
        foreach ($attempts as [$callbackUrl, $verifyToken]) {
            try {
                $this->cloud->registerWebhooks(
                    $token,
                    $callbackUrl,
                    $verifyToken,
                    $wabaId !== '' ? $wabaId : null,
                    $phoneNumberId,
                    $appSecret,
                    $appId
                );

                return;
            } catch (\Throwable $e) {
                $lastError = (string) WhatsAppCloudApiService::sanitizeGraphError($e->getMessage());
                Log::warning('WhatsApp webhook registration failed', [
                    'company_id' => $integration->company_id,
                    'callback_url' => $callbackUrl,
                    'error' => $lastError,
                ]);
            }
        }

        throw new \RuntimeException($lastError ?: 'Could not register the WhatsApp webhook with Meta.');
    }

    /**
     * Live messages arrive via Meta webhooks; there is nothing to poll.
     */
    public function ingestRecent(WhatsAppIntegration $integration, int $minutes = 45, int $limit = 150): int
    {
        return 0;
    }
}
