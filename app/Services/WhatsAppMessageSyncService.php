<?php

namespace App\Services;

use App\Models\WhatsAppIntegration;

class WhatsAppMessageSyncService
{
    public function __construct(protected WhatsAppCloudApiService $cloud) {}

    /**
     * Cloud API has no inbox history endpoint. Manual sync verifies the Meta token.
     *
     * @return array{scanned: int, imported: int, skipped: int, mode: string}
     */
    public function sync(WhatsAppIntegration $integration, int $days = 30, int $limit = 500): array
    {
        $token = $integration->getDecryptedAccessToken();
        $phoneNumberId = (string) $integration->phone_number_id;
        if (! $token || $phoneNumberId === '') {
            throw new \RuntimeException('WhatsApp Cloud API is not connected. Configure it under Integrations.');
        }

        $this->cloud->phoneNumberInfo($phoneNumberId, $token);

        return [
            'scanned' => 0,
            'imported' => 0,
            'skipped' => 0,
            'mode' => 'webhook',
            'days' => $days,
            'limit' => $limit,
        ];
    }

    /**
     * Live messages arrive via Meta webhooks; there is nothing to poll.
     */
    public function ingestRecent(WhatsAppIntegration $integration, int $minutes = 45, int $limit = 150): int
    {
        return 0;
    }
}
