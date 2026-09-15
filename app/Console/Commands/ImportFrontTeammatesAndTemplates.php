<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\Front\FrontTeammatesAndTemplatesImportService;
use Illuminate\Console\Command;
use Throwable;

class ImportFrontTeammatesAndTemplates extends Command
{
    protected $signature = 'inbox:import-front-teammates-templates
                            {--company= : Company id (defaults to Company::current())}
                            {--token= : Front API bearer token (overrides saved Front integration / FRONT_API_TOKEN)}
                            {--file= : Import from a JSON export file instead of the Front API}
                            {--teammates-only : Import teammates only}
                            {--templates-only : Import message templates only}
                            {--role= : Role id or slug for newly created teammates}
                            {--send-welcome : Email new teammates their temporary password}
                            {--dry-run : Report matches without writing users or templates}';

    protected $description = 'Import Front.com teammates as CRM users and Front message templates into Inbox templates';

    public function handle(FrontTeammatesAndTemplatesImportService $importService): int
    {
        @set_time_limit(0);

        $company = $this->resolveCompany();
        if (! $company) {
            $this->error('No company found. Pass --company or set COMPANY_ID.');

            return self::FAILURE;
        }

        if ($this->option('teammates-only') && $this->option('templates-only')) {
            $this->error('Use only one of --teammates-only or --templates-only.');

            return self::FAILURE;
        }

        $options = [
            'dry_run' => (bool) $this->option('dry-run'),
            'import_teammates' => ! $this->option('templates-only'),
            'import_templates' => ! $this->option('teammates-only'),
            'role' => $this->option('role') ?: null,
            'send_welcome' => (bool) $this->option('send-welcome'),
        ];

        $this->line('Company: '.$company->name.' (#'.$company->id.')');
        if ($options['dry_run']) {
            $this->warn('Dry run — no database changes will be made.');
        }

        try {
            $stats = $this->option('file')
                ? $importService->importFromFile($company, (string) $this->option('file'), $options)
                : $importService->importFromApi(
                    $company,
                    FrontTeammatesAndTemplatesImportService::clientForCompany($company, $this->option('token')),
                    $options
                );
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Front teammates and templates import finished.');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Teammates scanned', (string) ($stats['teammates_scanned'] ?? 0)],
                ['Teammates created', (string) ($stats['teammates_created'] ?? 0)],
                ['Teammates already in CRM', (string) ($stats['teammates_existing'] ?? 0)],
                ['Teammates skipped (other company)', (string) ($stats['teammates_conflict'] ?? 0)],
                ['Teammates skipped (blocked)', (string) ($stats['teammates_skipped_blocked'] ?? 0)],
                ['Teammates skipped (no email)', (string) ($stats['teammates_skipped_no_email'] ?? 0)],
                ['Templates scanned', (string) ($stats['templates_scanned'] ?? 0)],
                ['Templates created', (string) ($stats['templates_created'] ?? 0)],
                ['Templates already imported', (string) ($stats['templates_existing'] ?? 0)],
            ]
        );

        $created = $stats['created_teammates'] ?? [];
        if (is_array($created) && $created !== [] && ! ($options['dry_run'] ?? false)) {
            $this->newLine();
            $this->warn('New teammate logins (shown once — save these passwords):');
            $this->table(
                ['Name', 'Email', 'Temporary password'],
                collect($created)->map(fn (array $row) => [
                    (string) ($row['name'] ?? ''),
                    (string) ($row['email'] ?? ''),
                    (string) ($row['password'] ?? ''),
                ])->all()
            );
        }

        $conflicts = $stats['teammate_conflict_samples'] ?? [];
        if (is_array($conflicts) && $conflicts !== []) {
            $this->newLine();
            $this->warn('Emails that already belong to another company:');
            foreach ($conflicts as $sample) {
                $this->line(' - '.$sample);
            }
        }

        $warnings = $stats['template_warnings'] ?? [];
        if (is_array($warnings) && $warnings !== []) {
            $this->newLine();
            foreach ($warnings as $warning) {
                $this->warn($warning);
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
}
