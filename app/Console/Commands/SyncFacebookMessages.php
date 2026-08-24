<?php

namespace App\Console\Commands;

use App\Models\FacebookIntegration;
use App\Services\FacebookMessageSyncService;
use App\Services\TwilioCompanyService;
use App\Services\TwilioService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncFacebookMessages extends Command
{
    protected $signature = 'facebook:sync-messages
                            {--company= : Sync only this company id}
                            {--minutes=45 : How far back to poll Twilio for missed messages}
                            {--full : Run a heavier Graph + Twilio history sync}
                            {--days=7 : Days of history when using --full}';

    protected $description = 'Background-sync Facebook Messenger / Instagram messages (no /facebook page required)';

    public function handle(
        FacebookMessageSyncService $facebookSync,
        TwilioCompanyService $twilioCompany
    ): int {
        @set_time_limit(600);

        $query = FacebookIntegration::query()
            ->with('company')
            ->where('is_active', true)
            ->whereNotNull('page_id')
            ->orderBy('id');

        if ($this->option('company')) {
            $query->where('company_id', (int) $this->option('company'));
        }

        $integrations = $query->get();
        if ($integrations->isEmpty()) {
            $this->info('No active Facebook integrations to sync.');

            return self::SUCCESS;
        }

        $full = (bool) $this->option('full');
        $minutes = max(5, (int) $this->option('minutes'));
        $days = max(1, (int) $this->option('days'));
        $totalImported = 0;
        $synced = 0;
        $failed = 0;

        foreach ($integrations as $integration) {
            try {
                $twilio = $this->optionalTwilio($twilioCompany, $integration->company);
                if ($full) {
                    $result = $facebookSync->sync($integration, $twilio, $days, 1500);
                    $imported = (int) ($result['imported'] ?? 0);
                    $hint = $result['hint'] ?? null;
                } else {
                    $result = $facebookSync->ingestRecent($integration, $twilio, $minutes, 120);
                    $imported = (int) ($result['imported'] ?? 0);
                    $hint = $result['hint'] ?? null;
                }

                $totalImported += $imported;
                $synced++;

                $label = $integration->page_name ?: $integration->page_id;
                if ($imported > 0) {
                    $this->line("[facebook] {$label}: +{$imported}");
                } elseif (is_string($hint) && $hint !== '') {
                    $this->warn('[facebook] '.$label.': '.$hint);
                }
            } catch (Throwable $e) {
                $failed++;
                Log::warning('Background Facebook message sync failed', [
                    'integration_id' => $integration->id,
                    'company_id' => $integration->company_id,
                    'message' => $e->getMessage(),
                ]);
                $this->warn('[facebook] '.$integration->page_id.': '.$e->getMessage());
            }
        }

        $this->info("Synced {$synced} Facebook integration(s), imported {$totalImported} message(s)"
            .($failed ? ", {$failed} failed" : '').'.');

        return $failed > 0 && $synced === 0 ? self::FAILURE : self::SUCCESS;
    }

    private function optionalTwilio(TwilioCompanyService $twilioCompany, $company): ?TwilioService
    {
        if (! $company) {
            return null;
        }

        $integration = $twilioCompany->getActiveIntegration($company);
        if (! $integration) {
            return null;
        }

        $credentials = $twilioCompany->getCredentials($integration);
        if (! $credentials) {
            return null;
        }

        return new TwilioService($credentials['sid'], $credentials['token']);
    }
}
