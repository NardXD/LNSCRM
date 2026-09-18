<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\Front\FrontApiClient;
use App\Services\Front\FrontDiscussionImportService;
use Illuminate\Console\Command;
use Throwable;

class ImportFrontDiscussions extends Command
{
    protected $signature = 'messaging:import-front-discussions
                            {--company= : Company id (defaults to Company::current())}
                            {--token= : Front API bearer token (overrides FRONT_API_TOKEN)}
                            {--file= : Import from a JSON export file instead of the Front API}
                            {--status=* : Front conversation statuses (default: archived, assigned, unassigned)}
                            {--dry-run : Report matches without writing chats}';

    protected $description = 'One-time import of Front.com discussion threads into /messaging group chats';

    public function handle(FrontDiscussionImportService $importService): int
    {
        @set_time_limit(0);

        $company = $this->resolveCompany();
        if (! $company) {
            $this->error('No company found. Pass --company or set COMPANY_ID.');

            return self::FAILURE;
        }

        $options = [
            'dry_run' => (bool) $this->option('dry-run'),
            'statuses' => $this->statuses(),
        ];

        $this->line('Company: '.$company->name.' (#'.$company->id.')');
        if ($options['dry_run']) {
            $this->warn('Dry run — no database changes will be made.');
        }

        try {
            $stats = $this->option('file')
                ? $importService->importFromFile($company, (string) $this->option('file'), $options)
                : $importService->importFromApi($company, FrontApiClient::fromConfig($this->option('token')), $options);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Front discussion import finished.');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Conversations scanned', (string) ($stats['conversations_scanned'] ?? 0)],
                ['Skipped (not discussions)', (string) ($stats['conversations_skipped'] ?? 0)],
                ['Discussions found', (string) ($stats['discussions_found'] ?? 0)],
                ['Discussions imported', (string) ($stats['discussions_imported'] ?? 0)],
                ['Already synced (skipped)', (string) ($stats['discussions_already_synced'] ?? 0)],
                ['Messages imported', (string) ($stats['messages_imported'] ?? 0)],
                ['Existing messages skipped', (string) ($stats['messages_existing'] ?? 0)],
                ['Unmatched authors', (string) ($stats['messages_unmatched_author'] ?? 0)],
            ]
        );

        $samples = $stats['unmatched_samples'] ?? [];
        if (is_array($samples) && $samples !== []) {
            $this->newLine();
            $this->warn('Sample discussions that could not be imported:');
            foreach ($samples as $sample) {
                $this->line(' - '.$sample);
            }
        }

        return self::SUCCESS;
    }

    private function resolveCompany(): ?Company
    {
        if ($this->option('company')) {
            return Company::query()->find((int) $this->option('company'));
        }

        return Company::current();
    }

    /**
     * @return list<string>
     */
    private function statuses(): array
    {
        $statuses = collect($this->option('status'))
            ->filter(fn ($status) => is_string($status) && trim($status) !== '')
            ->map(fn ($status) => trim((string) $status))
            ->values()
            ->all();

        return $statuses !== [] ? $statuses : ['archived', 'assigned', 'unassigned'];
    }
}
