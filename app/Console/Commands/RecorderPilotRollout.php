<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\ScreenRecording;
use Illuminate\Console\Command;

class RecorderPilotRollout extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recorder:pilot-rollout {company_id : Company ID for pilot rollout} {--dry-run : Validate without writing data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run pilot rollout checks for recorder feature on one company';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $companyId = (int) $this->argument('company_id');
        $dryRun = (bool) $this->option('dry-run');

        $company = Company::find($companyId);
        if (! $company) {
            $this->error("Company #{$companyId} not found.");

            return self::FAILURE;
        }

        $this->info("Pilot company: {$company->name} (#{$company->id})");

        $companyUsers = $company->users()->count();
        $recordingCount = ScreenRecording::where('company_id', $company->id)->count();
        $failedUploads = ScreenRecording::where('company_id', $company->id)
            ->where('sync_status', 'failed')
            ->count();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Users', (string) $companyUsers],
                ['Total recordings', (string) $recordingCount],
                ['Failed uploads', (string) $failedUploads],
                ['Dry run', $dryRun ? 'yes' : 'no'],
            ]
        );

        if ($dryRun) {
            $this->line('Dry run completed. No rollout state was changed.');

            return self::SUCCESS;
        }

        $this->info('Pilot rollout checks completed. Use recorder clients with this company to proceed.');

        return self::SUCCESS;
    }
}
