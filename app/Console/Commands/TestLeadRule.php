<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\LeadRule;
use App\Services\LeadRuleEngine;
use App\Services\MessageContactExtractor;
use Illuminate\Console\Command;

class TestLeadRule extends Command
{
    protected $signature = 'leads:test-rule
        {company_id : Company ID that owns the rule(s) to test}
        {--channel=facebook : Channel to simulate (facebook, inbox, viber, whatsapp, sms, phone)}
        {--message= : Inbound message text to test against the rule\'s conditions}
        {--name=Test Contact : Simulated contact/display name}
        {--phone= : Simulated phone number}
        {--email= : Simulated email address}
        {--new : Simulate a brand-new conversation (adds the "(new conversation)" trigger)}
        {--lead-id= : Run against an existing lead instead of starting with none}
        {--commit : Actually execute the matched rules and write to the database. Without this flag, nothing is saved.}';

    protected $description = 'Dry-run the Lead Rules engine against a simulated inbound message so a rule can be tested by hand without waiting for a real webhook event.';

    public function handle(LeadRuleEngine $engine, MessageContactExtractor $extractor): int
    {
        $companyId = (int) $this->argument('company_id');
        $channel = LeadRuleEngine::normalizeChannel((string) $this->option('channel'));
        $message = (string) ($this->option('message') ?? '');
        $isNew = (bool) $this->option('new');

        $lead = null;
        if ($leadId = $this->option('lead-id')) {
            $lead = Lead::where('company_id', $companyId)->find((int) $leadId);
            if (! $lead) {
                $this->error("Lead {$leadId} not found for company {$companyId}.");

                return self::FAILURE;
            }
        }

        $context = [
            'company_id' => $companyId,
            'contact_name' => (string) $this->option('name'),
            'phone' => $this->option('phone'),
            'email' => $this->option('email'),
            'message' => $message,
        ];

        $triggers = LeadRuleEngine::inboundTriggers($isNew);

        $this->info("Simulating: channel={$channel} triggers=[".implode(', ', $triggers).']');
        $this->line('Message: '.($message !== '' ? $message : '(empty)'));
        $this->newLine();

        $rules = LeadRule::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        if ($rules->isEmpty()) {
            $this->warn('No active lead rules found for this company.');

            return self::SUCCESS;
        }

        $anyMatched = false;

        foreach ($rules as $rule) {
            $ruleTriggers = is_array($rule->triggers) && $rule->triggers !== []
                ? $rule->triggers
                : [LeadRuleEngine::TRIGGER_INBOUND_MESSAGE_NEW];
            $triggerHit = count(array_intersect($ruleTriggers, $triggers)) > 0;
            $conditionHit = $triggerHit && $engine->matches($lead, $channel, $rule->conditions ?? [], $context);

            $status = $conditionHit ? '<info>WOULD RUN</info>' : '<comment>skip</comment>';
            $this->line(sprintf(
                '%s Rule #%d "%s" — trigger match: %s, condition match: %s',
                $status,
                $rule->id,
                $rule->name,
                $triggerHit ? 'yes' : 'no',
                $triggerHit ? ($conditionHit ? 'yes' : 'no') : 'n/a'
            ));

            if (! $conditionHit) {
                continue;
            }
            $anyMatched = true;

            $assignActions = 0;
            foreach ($rule->actions ?? [] as $action) {
                $type = $action['type'] ?? '';

                if ($type === 'create_lead') {
                    $keywords = is_array($action['value'] ?? null) ? $action['value'] : [];
                    $extracted = $extractor->fromKeywords($message, $keywords);
                    $hasIdentity = (bool) ($extracted['phone'] || $extracted['email']);
                    $this->line('    → create_lead: extracted '.json_encode($extracted));
                    if (! $hasIdentity) {
                        $this->warn('      No phone/email extracted, and this action never receives a facebook_name/instagram_username either — LeadAutoCreateService::ensure() will return null (no lead created) unless a lead already exists upstream.');
                    }
                }

                if ($type === 'assign') {
                    $assignActions++;
                    $this->line('    → assign: '.json_encode($action['value'] ?? null));
                }

                if ($type === 'add_label') {
                    $this->line('    → add_label: '.json_encode($action['value'] ?? null));
                }
            }

            if ($assignActions > 1) {
                $this->warn("      This rule has {$assignActions} \"assign\" actions — only the LAST one wins, since each runs in order and overwrites the previous assignment.");
            }
        }

        if (! $anyMatched) {
            $this->newLine();
            $this->comment('No rule matched this simulated message — nothing would run.');

            return self::SUCCESS;
        }

        if (! $this->option('commit')) {
            $this->newLine();
            $this->comment('Dry run only — pass --commit to actually execute the matched rule(s) and write to the database.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Executing for real...');
        $result = $engine->apply($lead, $channel, $triggers, $context);

        if (! $result) {
            $this->error('Result: no lead. The engine returned null (no lead was created and none was passed in).');

            return self::SUCCESS;
        }

        $result->loadMissing(['labels', 'assignedUser', 'identities']);
        $this->info("Result lead: #{$result->id} \"{$result->name}\"");
        $this->line('Assigned to: '.($result->assignedUser?->name ?? '(unassigned)'));
        $this->line('Labels: '.($result->labels->pluck('name')->implode(', ') ?: '(none)'));
        $this->line('Identities: '.$result->identities->map(fn ($i) => "{$i->type}:{$i->value}")->implode(', '));

        return self::SUCCESS;
    }
}
