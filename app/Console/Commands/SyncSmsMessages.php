<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\TwilioFlexIntegration;
use App\Services\SmsMessageSyncService;
use App\Services\TwilioCompanyService;
use App\Services\TwilioIntegrationValidator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncSmsMessages extends Command
{
    protected $signature = 'sms:sync-messages
                            {--company= : Sync only this company id}
                            {--minutes=45 : How far back to poll Twilio for missed SMS}';

    protected $description = 'Background-sync SMS from Twilio (catch-up for missed webhooks)';

    public function handle(
        SmsMessageSyncService $smsSync,
        TwilioCompanyService $twilioCompany,
        TwilioIntegrationValidator $validator
    ): int {
        @set_time_limit(300);

        $query = TwilioFlexIntegration::query()
            ->with('company')
            ->where('is_active', true)
            ->orderBy('id');

        if ($this->option('company')) {
            $query->where('company_id', (int) $this->option('company'));
        }

        $integrations = $query->get()->filter(fn (TwilioFlexIntegration $i) => $validator->isComplete($i));
        if ($integrations->isEmpty()) {
            $this->info('No active Twilio integrations to sync SMS for.');

            return self::SUCCESS;
        }

        $minutes = max(5, (int) $this->option('minutes'));
        $totalImported = 0;
        $synced = 0;
        $failed = 0;

        foreach ($integrations as $integration) {
            /** @var Company|null $company */
            $company = $integration->company;
            if (! $company) {
                continue;
            }

            try {
                // Ensure credentials resolve (same gate as getActiveIntegration).
                if (! $twilioCompany->getActiveIntegration($company)) {
                    continue;
                }

                $imported = $smsSync->ingestRecent($company, $minutes, 150);
                $totalImported += $imported;
                $synced++;

                if ($imported > 0) {
                    $this->line("[sms] {$company->name}: +{$imported}");
                }
            } catch (Throwable $e) {
                $failed++;
                Log::warning('Background SMS sync failed', [
                    'company_id' => $company->id,
                    'message' => $e->getMessage(),
                ]);
                $this->warn('[sms] '.$company->name.': '.$e->getMessage());
            }
        }

        $this->info("Synced SMS for {$synced} company(ies), imported {$totalImported} message(s)"
            .($failed ? ", {$failed} failed" : '').'.');

        return $failed > 0 && $synced === 0 ? self::FAILURE : self::SUCCESS;
    }
}
