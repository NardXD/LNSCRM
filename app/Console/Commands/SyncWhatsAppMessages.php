<?php

namespace App\Console\Commands;

use App\Models\WhatsAppIntegration;
use App\Services\WhatsAppCloudApiService;
use App\Services\WhatsAppMessageSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncWhatsAppMessages extends Command
{
    protected $signature = 'whatsapp:sync-messages
                            {--company= : Check only this company id}';

    protected $description = 'Verify WhatsApp Cloud API tokens and re-register inbound Meta webhooks';

    public function handle(WhatsAppCloudApiService $cloud, WhatsAppMessageSyncService $sync): int
    {
        $query = WhatsAppIntegration::query()
            ->where('is_active', true)
            ->whereNotNull('phone_number_id')
            ->orderBy('id');

        if ($this->option('company')) {
            $query->where('company_id', (int) $this->option('company'));
        }

        $integrations = $query->get();
        if ($integrations->isEmpty()) {
            $this->info('No active WhatsApp Cloud API integrations to verify.');

            return self::SUCCESS;
        }

        $ok = 0;
        $failed = 0;

        foreach ($integrations as $integration) {
            $token = $integration->getDecryptedAccessToken();
            if (! $token) {
                $failed++;
                continue;
            }

            try {
                $info = $cloud->phoneNumberInfo((string) $integration->phone_number_id, $token);
                $sync->ensureWebhooks($integration, $token);
                $ok++;
                $label = $info['verified_name'] ?? $info['display_phone_number'] ?? $integration->phone_number_id;
                $this->line('[whatsapp] company '.$integration->company_id.': '.$label);
            } catch (Throwable $e) {
                $failed++;
                Log::warning('WhatsApp Cloud API token check failed', [
                    'company_id' => $integration->company_id,
                    'message' => WhatsAppCloudApiService::sanitizeGraphError($e->getMessage()),
                ]);
                $this->warn('[whatsapp] company '.$integration->company_id.': '.$e->getMessage());
            }
        }

        $this->info("Checked {$ok} WhatsApp Cloud API integration(s)".($failed ? ", {$failed} failed" : '').'.');

        return $failed > 0 && $ok === 0 ? self::FAILURE : self::SUCCESS;
    }
}
