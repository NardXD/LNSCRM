<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\WhatsAppIntegration;
use App\Services\TwilioCompanyService;
use App\Services\TwilioService;
use App\Services\WhatsAppMessageSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncWhatsAppMessages extends Command
{
    protected $signature = 'whatsapp:sync-messages
                            {--company= : Sync only this company id}
                            {--minutes=45 : How far back to poll Twilio for missed WhatsApp messages}';

    protected $description = 'Background-sync WhatsApp messages from Twilio (catch-up for missed webhooks)';

    public function handle(WhatsAppMessageSyncService $whatsappSync, TwilioCompanyService $twilioCompany): int
    {
        @set_time_limit(300);

        $query = WhatsAppIntegration::query()
            ->where('is_active', true)
            ->whereNotNull('from_number')
            ->orderBy('id');

        if ($this->option('company')) {
            $query->where('company_id', (int) $this->option('company'));
        }

        $integrations = $query->get();
        if ($integrations->isEmpty()) {
            $this->info('No active WhatsApp integrations to sync.');

            return self::SUCCESS;
        }

        $minutes = max(5, (int) $this->option('minutes'));
        $totalImported = 0;
        $synced = 0;
        $failed = 0;

        foreach ($integrations as $integration) {
            $company = Company::find($integration->company_id);
            if (! $company) {
                continue;
            }

            try {
                $twilioIntegration = $twilioCompany->getActiveIntegration($company);
                if (! $twilioIntegration) {
                    continue;
                }

                $credentials = $twilioCompany->getCredentials($twilioIntegration);
                if (! $credentials) {
                    continue;
                }

                $twilio = new TwilioService($credentials['sid'], $credentials['token']);
                $imported = $whatsappSync->ingestRecent($integration, $twilio, $minutes, 150);
                $totalImported += $imported;
                $synced++;

                if ($imported > 0) {
                    $this->line("[whatsapp] {$company->name}: +{$imported}");
                }
            } catch (Throwable $e) {
                $failed++;
                Log::warning('Background WhatsApp sync failed', [
                    'company_id' => $company->id,
                    'message' => $e->getMessage(),
                ]);
                $this->warn('[whatsapp] '.$company->name.': '.$e->getMessage());
            }
        }

        $this->info("Synced WhatsApp for {$synced} company(ies), imported {$totalImported} message(s)"
            .($failed ? ", {$failed} failed" : '').'.');

        return $failed > 0 && $synced === 0 ? self::FAILURE : self::SUCCESS;
    }
}
