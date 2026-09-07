<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\LeadRule;
use App\Services\LeadRuleEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class CreateFacebookFollowUpLeadRules extends Command
{
    protected $signature = 'leads:create-facebook-follow-up-rules
        {company_id? : Company ID to create the rules for; omit to create them for every company with the Facebook module enabled}
        {--keywords=quote,price,cost,how much,available,size : Comma-separated keywords that flag a pricing/availability inquiry (blank to skip that rule)}
        {--label=Pricing inquiry : Label applied when a message matches one of the keywords}
        {--priority=110 : Rule priority (lower runs first) - kept after the create-lead/round-robin rule\'s default 100 so a new lead is already assigned before these run}
        {--inactive : Create the rules disabled instead of active}
        {--force : Update existing rules with the same name instead of skipping them}';

    protected $description = 'Provision follow-up Facebook automation rules: notify the assignee on every reply, wake up a snoozed lead who messages back, and label pricing/availability inquiries.';

    public function handle(): int
    {
        $priority = max(1, min(9999, (int) $this->option('priority')));
        $isActive = ! $this->option('inactive');
        $force = (bool) $this->option('force');
        $label = trim((string) $this->option('label')) ?: 'Pricing inquiry';
        $keywords = collect(explode(',', (string) $this->option('keywords')))
            ->map(fn ($keyword) => trim($keyword))
            ->filter()
            ->values()
            ->all();

        $companies = $this->resolveCompanies();
        if ($companies->isEmpty()) {
            $this->error('No matching companies with the Facebook module enabled were found.');

            return self::FAILURE;
        }

        $channelCondition = ['field' => 'channel', 'operator' => 'in', 'value' => ['facebook']];

        $rules = [
            'Facebook message - notify assignee' => [
                'triggers' => [LeadRuleEngine::TRIGGER_INBOUND_MESSAGE],
                'conditions' => [$channelCondition],
                'actions' => [['type' => 'notify_assignee', 'value' => null]],
            ],
            'Facebook message - wake up snoozed lead' => [
                'triggers' => [LeadRuleEngine::TRIGGER_INBOUND_MESSAGE],
                'conditions' => [$channelCondition],
                'actions' => [['type' => 'unsnooze', 'value' => null]],
            ],
        ];

        if ($keywords !== []) {
            $rules['Facebook message mentions pricing/availability'] = [
                'triggers' => [LeadRuleEngine::TRIGGER_INBOUND_MESSAGE],
                'conditions' => [
                    $channelCondition,
                    ['field' => 'message', 'operator' => 'contains_any', 'value' => $keywords],
                ],
                'actions' => [['type' => 'add_label', 'value' => $label]],
            ];
        }

        foreach ($companies as $company) {
            foreach ($rules as $name => $definition) {
                $this->createOrUpdate($company, $name, $definition, $priority, $isActive, $force);
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  array{triggers: list<string>, conditions: list<array<string, mixed>>, actions: list<array<string, mixed>>}  $definition
     */
    private function createOrUpdate(Company $company, string $name, array $definition, int $priority, bool $isActive, bool $force): void
    {
        $existing = LeadRule::query()
            ->where('company_id', $company->id)
            ->where('name', $name)
            ->first();

        if ($existing && ! $force) {
            $this->line("Company {$company->id} ({$company->name}): \"{$name}\" already exists (id {$existing->id}) — skipped. Pass --force to update it.");

            return;
        }

        $payload = [
            'company_id' => $company->id,
            'name' => $name,
            'priority' => $priority,
            'is_active' => $isActive,
            'stop_processing' => false,
            'triggers' => $definition['triggers'],
            'conditions' => $definition['conditions'],
            'actions' => $definition['actions'],
        ];

        if ($existing) {
            $existing->update($payload);
            $this->info("Company {$company->id} ({$company->name}): updated \"{$name}\" (id {$existing->id}).");
        } else {
            $rule = LeadRule::create($payload);
            $this->info("Company {$company->id} ({$company->name}): created \"{$name}\" (id {$rule->id}).");
        }
    }

    /**
     * @return Collection<int, Company>
     */
    protected function resolveCompanies()
    {
        $companyId = $this->argument('company_id');
        if ($companyId !== null) {
            $company = Company::find((int) $companyId);
            if (! $company) {
                $this->error("Company {$companyId} not found.");

                return collect();
            }

            return collect([$company]);
        }

        return Company::all()->filter(fn (Company $company) => $company->hasModuleAccess('facebook'))->values();
    }
}
