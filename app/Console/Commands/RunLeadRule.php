<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\LeadRule;
use App\Services\LeadRuleEngine;
use Illuminate\Console\Command;

class RunLeadRule extends Command
{
    protected $signature = 'leads:run-rule
        {rule_id : ID of the LeadRule whose actions should be run}
        {lead_id : ID of the lead to run the rule\'s actions against}
        {--commit : Actually execute the rule\'s actions and write to the database. Without this flag, only the actions are listed.}';

    protected $description = "Force-run a single lead rule's actions against one lead right now, skipping its trigger and condition matching entirely.";

    public function handle(LeadRuleEngine $engine): int
    {
        $rule = LeadRule::find((int) $this->argument('rule_id'));
        if (! $rule) {
            $this->error("Lead rule #{$this->argument('rule_id')} not found.");

            return self::FAILURE;
        }

        $lead = Lead::where('company_id', $rule->company_id)->find((int) $this->argument('lead_id'));
        if (! $lead) {
            $this->error("Lead #{$this->argument('lead_id')} not found for company {$rule->company_id}.");

            return self::FAILURE;
        }

        $actions = $rule->actions ?? [];
        if ($actions === []) {
            $this->warn("Rule #{$rule->id} \"{$rule->name}\" has no actions configured.");

            return self::SUCCESS;
        }

        $this->info("Rule #{$rule->id} \"{$rule->name}\" — lead #{$lead->id} \"{$lead->name}\" — triggers and conditions are NOT checked, actions run unconditionally.");
        foreach ($actions as $action) {
            $this->line('  → '.($action['type'] ?? '?').': '.json_encode($action['value'] ?? null));
        }

        if (! $this->option('commit')) {
            $this->newLine();
            $this->comment('Dry run only — pass --commit to actually execute these actions and write to the database.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Executing for real...');
        $lead = $engine->runActions($lead, $actions, '', ['company_id' => (int) $rule->company_id], (int) $rule->company_id, $rule);
        LeadRule::whereKey($rule->id)->update(['last_applied_at' => now()]);

        if (! $lead) {
            $this->error('Result: no lead. The engine returned null.');

            return self::SUCCESS;
        }

        $lead->loadMissing(['labels', 'assignedUser']);
        $this->info("Result lead: #{$lead->id} \"{$lead->name}\"");
        $this->line('Status: '.$lead->status);
        $this->line('Assigned to: '.($lead->assignedUser?->name ?? '(unassigned)'));
        $this->line('Labels: '.($lead->labels->pluck('name')->implode(', ') ?: '(none)'));

        return self::SUCCESS;
    }
}
