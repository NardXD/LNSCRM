<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\Front\FrontKnowledgeBaseImportService;
use Illuminate\Console\Command;
use Throwable;

class ImportFrontKnowledgeBase extends Command
{
    protected $signature = 'knowledge-base:import-front
                            {--company= : Company id (defaults to Company::current())}
                            {--token= : Front API bearer token (overrides saved Front integration / FRONT_API_TOKEN)}
                            {--knowledge-base= : Only import this Front knowledge base id (e.g. knb_123)}
                            {--user= : CRM user id to set as author when the Front editor has no matching CRM user}
                            {--refresh : Re-import articles that were already imported, overwriting CRM edits}
                            {--dry-run : Report what would be imported without writing anything}';

    protected $description = 'Copy Front.com knowledge base categories and articles into the CRM knowledge base';

    public function handle(FrontKnowledgeBaseImportService $importService): int
    {
        @set_time_limit(0);

        $company = $this->option('company')
            ? Company::query()->find((int) $this->option('company'))
            : Company::current();
        if (! $company) {
            $this->error('No company found. Pass --company or set COMPANY_ID.');

            return self::FAILURE;
        }

        $options = [
            'dry_run' => (bool) $this->option('dry-run'),
            'refresh' => (bool) $this->option('refresh'),
            'knowledge_base' => $this->option('knowledge-base') ?: null,
            'user_id' => $this->option('user') ?: null,
        ];

        $this->line('Company: '.$company->name.' (#'.$company->id.')');
        if ($options['dry_run']) {
            $this->warn('Dry run — no database changes will be made.');
        }

        try {
            $stats = $importService->importFromApi(
                $company,
                FrontKnowledgeBaseImportService::clientForCompany($company, $this->option('token')),
                $options
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Front knowledge base import finished.');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Knowledge bases scanned', (string) $stats['knowledge_bases_scanned']],
                ['Categories scanned', (string) $stats['categories_scanned']],
                ['Categories created', (string) $stats['categories_created']],
                ['Articles scanned', (string) $stats['articles_scanned']],
                ['Articles created', (string) $stats['articles_created']],
                ['Articles updated', (string) $stats['articles_updated']],
                ['Articles already imported (skipped)', (string) $stats['articles_existing']],
                ['Articles failed', (string) $stats['articles_failed']],
                ['Files downloaded', (string) $stats['files_downloaded']],
            ]
        );

        foreach ($stats['warnings'] as $warning) {
            $this->warn($warning);
        }

        return $stats['articles_failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
