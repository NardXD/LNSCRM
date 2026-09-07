<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\LeadRule;
use App\Services\LeadRuleEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class CreateFacebookLeadRoundRobinRule extends Command
{
    protected $signature = 'leads:create-facebook-round-robin-rule
        {company_id? : Company ID to create the rule for; omit to create it for every company with the Facebook module enabled}
        {--name=Facebook message - create lead + round robin : Name to give the rule}
        {--priority=100 : Rule priority (lower runs first)}
        {--inactive : Create the rule disabled instead of active}
        {--force : Update the existing rule with this name instead of skipping it}';

    protected $description = 'Provision a Lead Rule that turns every new Facebook/Instagram conversation into a lead (using the message content) and round-robins it to a teammate.';

    public function handle(): int
    {
        $name = (string) $this->option('name');
        $priority = max(1, min(9999, (int) $this->option('priority')));
        $isActive = ! $this->option('inactive');
        $force = (bool) $this->option('force');

        $companies = $this->resolveCompanies();
        if ($companies->isEmpty()) {
            $this->error('No matching companies with the Facebook module enabled were found.');

            return self::FAILURE;
        }

        $actions = [
            ['type' => 'create_lead', 'value' => ['name' => '', 'phone' => '', 'email' => '']],
            ['type' => 'assign', 'value' => LeadRuleEngine::ASSIGN_ROUND_ROBIN],
        ];
        $conditions = [
            ['field' => 'channel', 'operator' => 'in', 'value' => ['facebook']],
        ];
        $triggers = [LeadRuleEngine::TRIGGER_INBOUND_MESSAGE_NEW];

        foreach ($companies as $company) {
            $existing = LeadRule::query()
                ->where('company_id', $company->id)
                ->where('name', $name)
                ->first();

            if ($existing && ! $force) {
                $this->line("Company {$company->id} ({$company->name}): rule already exists (id {$existing->id}) — skipped. Pass --force to update it.");

                continue;
            }

            $payload = [
                'company_id' => $company->id,
                'name' => $name,
                'priority' => $priority,
                'is_active' => $isActive,
                'stop_processing' => false,
                'triggers' => $triggers,
                'conditions' => $conditions,
                'actions' => $actions,
            ];

            if ($existing) {
                $existing->update($payload);
                $this->info("Company {$company->id} ({$company->name}): updated rule id {$existing->id}.");
            } else {
                $rule = LeadRule::create($payload);
                $this->info("Company {$company->id} ({$company->name}): created rule id {$rule->id}.");
            }
        }

        return self::SUCCESS;
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
