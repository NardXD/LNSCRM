<?php

namespace App\Services;

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

        try {
            $this->cloud->registerWebhooks(
                $token,
                $integration->webhookUrl(),
                (string) ($integration->webhook_verify_token ?: ''),
                $wabaId !== '' ? $wabaId : null,
                $phoneNumberId,
                $integration->getDecryptedAppSecret()
            );
        } catch (\Throwable $e) {
            $message = (string) WhatsAppCloudApiService::sanitizeGraphError($e->getMessage());
            Log::warning('WhatsApp webhook registration failed', [
                'company_id' => $integration->company_id,
                'error' => $message,
            ]);

            throw new \RuntimeException($message);
        }
    }

    /**
     * Live messages arrive via Meta webhooks; there is nothing to poll.
     */
    public function ingestRecent(WhatsAppIntegration $integration, int $minutes = 45, int $limit = 150): int
    {
        return 0;
    }
}
