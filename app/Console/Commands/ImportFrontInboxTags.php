<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\Front\FrontApiClient;
use App\Services\Front\FrontTagImportService;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class ImportFrontInboxTags extends Command
{
    protected $signature = 'inbox:import-front-tags
                            {--company= : Company id (defaults to Company::current())}
                            {--token= : Front API bearer token (overrides FRONT_API_TOKEN)}
                            {--file= : Import from a JSON export file instead of the Front API}
                            {--inbox-map= : JSON map of Front inbox id => shared_inbox id}
                            {--front-inbox= : Import only this Front inbox id}
                            {--shared-inbox= : Limit matching to this local shared inbox id}
                            {--status=* : Front conversation statuses (default: archived, assigned, unassigned)}
                            {--include-private : Import private Front tags too}
                            {--dry-run : Report matches without writing tags}';

    protected $description = 'One-time import of Front.com conversation tags into /inbox shared inboxes';

    public function handle(FrontTagImportService $importService): int
    {
        @set_time_limit(0);

        $company = $this->resolveCompany();
        if (! $company) {
            $this->error('No company found. Pass --company or set COMPANY_ID.');

            return self::FAILURE;
        }

        $options = [
            'dry_run' => (bool) $this->option('dry-run'),
            'include_private' => (bool) $this->option('include-private'),
            'inbox_map' => $this->decodeJsonOption('inbox-map'),
            'front_inbox_id' => $this->option('front-inbox') ?: null,
            'shared_inbox_id' => $this->option('shared-inbox') ? (int) $this->option('shared-inbox') : null,
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
        $this->info('Front tag import finished.');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Mapped inboxes', (string) ($stats['mapped_inboxes'] ?? 0)],
                ['Front conversations with tags', (string) ($stats['front_conversations_with_tags'] ?? 0)],
                ['Matched local conversations', (string) ($stats['conversations_matched'] ?? 0)],
                ['Unmatched conversations', (string) ($stats['conversations_unmatched'] ?? 0)],
                ['Tags created', (string) ($stats['tags_created'] ?? 0)],
                ['Existing tags reused', (string) ($stats['tags_existing'] ?? 0)],
                ['Tag links applied', (string) ($stats['tags_applied'] ?? 0)],
            ]
        );

        $samples = $stats['unmatched_samples'] ?? [];
        if (is_array($samples) && $samples !== []) {
            $this->newLine();
            $this->warn('Sample unmatched Front conversations:');
            foreach ($samples as $sample) {
                $this->line(' - '.$sample);
            }
            $this->line('Run inbox:sync-mail --full first if mail has not been imported yet.');
        }

        if ((int) ($stats['conversations_unmatched'] ?? 0) > 0) {
            $this->line('Use --inbox-map to map Front inboxes manually, e.g. {"inb_abc": 3}');
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
     * @return array<string, int|string>
     */
    private function decodeJsonOption(string $name): array
    {
        $raw = trim((string) $this->option($name));
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw new RuntimeException("Option --{$name} must be valid JSON.");
        }

        return $decoded;
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
