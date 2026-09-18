<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\Front\FrontApiClient;
use App\Services\Front\FrontCommentImportService;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class ImportFrontInboxComments extends Command
{
    protected $signature = 'inbox:import-front-comments
                            {--company= : Company id (defaults to Company::current())}
                            {--token= : Front API bearer token (overrides FRONT_API_TOKEN)}
                            {--file= : Import from a JSON export file instead of the Front API}
                            {--inbox-map= : JSON map of Front inbox id => shared_inbox id}
                            {--front-inbox= : Import only this Front inbox id}
                            {--shared-inbox= : Limit matching to this local shared inbox id}
                            {--status=* : Front conversation statuses (default: archived, assigned, unassigned)}
                            {--dry-run : Report matches without writing comments}';

    protected $description = 'One-time import of Front.com internal comments into /inbox conversations';

    public function handle(FrontCommentImportService $importService): int
    {
        @set_time_limit(0);

        $company = $this->resolveCompany();
        if (! $company) {
            $this->error('No company found. Pass --company or set COMPANY_ID.');

            return self::FAILURE;
        }

        $options = [
            'dry_run' => (bool) $this->option('dry-run'),
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
        $this->info('Front comment import finished.');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Mapped inboxes', (string) ($stats['mapped_inboxes'] ?? 0)],
                ['Conversations scanned', (string) ($stats['conversations_scanned'] ?? 0)],
                ['Matched local conversations', (string) ($stats['conversations_matched'] ?? 0)],
                ['Unmatched conversations', (string) ($stats['conversations_unmatched'] ?? 0)],
                ['Conversations with comments', (string) ($stats['conversations_with_comments'] ?? 0)],
                ['Comments imported', (string) ($stats['comments_imported'] ?? 0)],
                ['Attachments imported', (string) ($stats['attachments_imported'] ?? 0)],
                ['Attachments skipped', (string) ($stats['attachments_failed'] ?? 0)],
                ['Existing comments skipped', (string) ($stats['comments_existing'] ?? 0)],
                ['Unmatched authors', (string) ($stats['comments_unmatched_author'] ?? 0)],
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
