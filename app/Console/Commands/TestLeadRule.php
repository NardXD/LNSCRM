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
        {--channel=facebook : Channel to simulate (facebook, inbox, viber, whatsapp, sms, phone). Ignored for lead_age_reached.}
        {--trigger= : Trigger to simulate. Default: inbound_message (add --new for new-conversation). Use lead_age_reached for age rules.}
        {--message= : Inbound message text to test against the rule\'s conditions}
        {--subject= : Simulated email subject (useful for inbox rules)}
        {--name=Test Contact : Simulated contact/display name}
        {--phone= : Simulated phone number}
        {--email= : Simulated email address}
        {--inbox-id= : Shared inbox ID for the simulated event (or leave blank to resolve from --lead-id)}
        {--new : Simulate a brand-new conversation (adds the "(new conversation)" trigger)}
        {--lead-id= : Run against an existing lead instead of starting with none (required for lead_age_reached)}
        {--rule-id= : Only evaluate this rule ID}
        {--commit : Actually execute the matched rules and write to the database. Without this flag, nothing is saved.}';

    protected $description = 'Dry-run the Lead Rules engine against a simulated event so a rule can be tested by hand without waiting for a real webhook or the daily age job.';

    public function handle(LeadRuleEngine $engine, MessageContactExtractor $extractor): int
    {
        $companyId = (int) $this->argument('company_id');
        $channel = LeadRuleEngine::normalizeChannel((string) $this->option('channel'));
        $message = (string) ($this->option('message') ?? '');
        $isNew = (bool) $this->option('new');
        $triggerOpt = trim((string) ($this->option('trigger') ?? ''));

        $lead = null;
        if ($leadId = $this->option('lead-id')) {
            $lead = Lead::where('company_id', $companyId)->find((int) $leadId);
            if (! $lead) {
                $this->error("Lead {$leadId} not found for company {$companyId}.");

                return self::FAILURE;
            }
            $lead->loadMissing(['labels', 'inboxConversations']);
        }

        $triggers = $this->resolveTriggers($triggerOpt, $isNew);
        if ($triggers === null) {
            return self::FAILURE;
        }

        if (in_array(LeadRuleEngine::TRIGGER_LEAD_AGE_REACHED, $triggers, true)) {
            if (! $lead) {
                $this->error('lead_age_reached requires --lead-id=…');

                return self::FAILURE;
            }
            $channel = '';
        }

        $context = [
            'company_id' => $companyId,
            'contact_name' => (string) $this->option('name'),
            'phone' => $this->option('phone'),
            'email' => $this->option('email'),
            'message' => $message,
            'subject' => $this->option('subject'),
        ];

        $inboxId = (int) ($this->option('inbox-id') ?: 0);
        if ($inboxId < 1 && $lead) {
            $inboxId = (int) ($lead->inboxConversations
                ->firstWhere(fn ($c) => $c->merged_into_id === null)
                ?->shared_inbox_id ?: 0);
        }
        if ($inboxId > 0) {
            $context['inbox_id'] = $inboxId;
            $context['shared_inbox_id'] = $inboxId;
        }

        $this->info('Simulating: channel='.($channel !== '' ? $channel : '(none — lead lifecycle)').' triggers=['.implode(', ', $triggers).']');
        if ($lead) {
            $ageDays = $lead->created_at ? (int) $lead->created_at->diffInDays(now()) : 0;
            $this->line("Lead: #{$lead->id} \"{$lead->name}\" — age {$ageDays} day(s), source=".($lead->source ?: '(none)').', labels='.($lead->labels->pluck('name')->implode(', ') ?: '(none)'));
            $threads = $lead->inboxConversations->whereNull('merged_into_id')->values();
            $this->line('Linked inbox threads: '.($threads->isEmpty()
                ? '(none)'
                : $threads->map(fn ($c) => "#{$c->id} inbox={$c->shared_inbox_id} status={$c->status}")->implode(', ')));
        }
        $this->line('Message: '.($message !== '' ? $message : '(empty)'));
        if ($this->option('subject')) {
            $this->line('Subject: '.$this->option('subject'));
        }
        $this->newLine();

        $rulesQuery = LeadRule::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('id');
        if ($ruleId = $this->option('rule-id')) {
            $rulesQuery->whereKey((int) $ruleId);
        }
        $rules = $rulesQuery->get();

        if ($rules->isEmpty()) {
            $this->warn('No active lead rules found for this company'.($this->option('rule-id') ? ' / rule-id' : '').'.');

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

            if ($triggerHit && ! $conditionHit) {
                foreach ($this->explainFailedConditions($engine, $lead, $channel, $rule->conditions ?? [], $context) as $reason) {
                    $this->line('    ✗ '.$reason);
                }
            }

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
                    $resolvedName = $extracted['name'] ?: (string) ($context['contact_name'] ?? '');
                    $hasFacebookNameFallback = $channel === 'facebook' && trim($resolvedName) !== '';
                    $hasIdentity = (bool) ($extracted['phone'] || $extracted['email'] || $hasFacebookNameFallback);
                    $this->line('    → create_lead: extracted '.json_encode($extracted));
                    if (! $hasIdentity) {
                        $this->warn('      No phone/email extracted, and this action never receives a facebook_name/instagram_username either — LeadAutoCreateService::ensure() will return null (no lead created) unless a lead already exists upstream.');
                    } elseif ($hasFacebookNameFallback && ! $extracted['phone'] && ! $extracted['email']) {
                        $this->line('      Facebook channel: no phone/email found, falling back to chat name "'.$resolvedName.'" as the lead identity.');
                    }
                }

                if ($type === 'assign') {
                    $assignActions++;
                    $this->line('    → assign: '.json_encode($action['value'] ?? null));
                }

                if ($type === 'add_label') {
                    $this->line('    → add_label: '.json_encode($action['value'] ?? null));
                }

                if (in_array($type, ['notify_assignee', 'reopen_email_thread', 'unsnooze', 'attach_shared_inbox'], true)) {
                    $this->line('    → '.$type);
                }

                if (in_array($type, ['set_status', 'set_status_after_days', 'reopen_after_days', 'send_email'], true)) {
                    $this->line('    → '.$type.': '.json_encode($action['value'] ?? null));
                }
            }

            if ($assignActions > 1) {
                $this->warn("      This rule has {$assignActions} \"assign\" actions — only the LAST one wins, since each runs in order and overwrites the previous assignment.");
            }
        }

        if (! $anyMatched) {
            $this->newLine();
            $this->comment('No rule matched this simulated event — nothing would run.');

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

    /**
     * @return list<string>|null
     */
    private function resolveTriggers(string $triggerOpt, bool $isNew): ?array
    {
        if ($triggerOpt === '') {
            return LeadRuleEngine::inboundTriggers($isNew);
        }

        $allowed = array_keys(LeadRuleEngine::triggerLabels());
        if (! in_array($triggerOpt, $allowed, true)) {
            $this->error('Unknown --trigger. Use one of: '.implode(', ', $allowed));

            return null;
        }

        return [$triggerOpt];
    }

    /**
     * @param  array<int, array{field?: string, operator?: string, value?: mixed}>  $conditions
     * @param  array<string, mixed>  $context
     * @return list<string>
     */
    private function explainFailedConditions(
        LeadRuleEngine $engine,
        ?Lead $lead,
        string $channel,
        array $conditions,
        array $context
    ): array {
        $failed = [];
        foreach ($conditions as $condition) {
            if ($engine->matches($lead, $channel, [$condition], $context)) {
                continue;
            }
            $field = (string) ($condition['field'] ?? '?');
            $operator = (string) ($condition['operator'] ?? '');
            $value = $condition['value'] ?? null;
            $failed[] = $field.($operator !== '' ? " {$operator}" : '').' '.json_encode($value);
        }

        return $failed;
    }
}
