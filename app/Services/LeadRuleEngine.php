<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadLabel;
use App\Models\LeadRule;
use App\Models\User;
use App\Notifications\LeadRuleNotification;
use Illuminate\Support\Facades\Log;

class LeadRuleEngine
{
    public const TRIGGER_INBOUND_MESSAGE = 'inbound_message';

    public const TRIGGER_INBOUND_MESSAGE_NEW = 'inbound_message_new';

    public const TRIGGER_OUTBOUND_MESSAGE_NEW = 'outbound_message_new';

    public const TRIGGER_OUTBOUND_REPLY = 'outbound_reply';

    public const TRIGGER_INBOUND_CALL = 'inbound_call';

    public const TRIGGER_OUTBOUND_CALL = 'outbound_call';

    public const TRIGGER_LEAD_ASSIGNED = 'lead_assigned';

    public const TRIGGER_LEAD_LABELED = 'lead_labeled';

    public const TRIGGER_LEAD_NOTE_ADDED = 'lead_note_added';

    public const CHANNELS = [
        'phone' => 'Phone',
        'inbox' => 'Inbox',
        'viber' => 'Viber',
        'whatsapp' => 'WhatsApp',
        'facebook' => 'Facebook',
        'sms' => 'SMS',
    ];

    private static bool $running = false;

    public function __construct(
        protected LeadActivityService $leadActivity,
        protected FlexCrmLookupService $crmLookup
    ) {}

    /**
     * @return array<string, string>
     */
    public static function triggerLabels(): array
    {
        return [
            self::TRIGGER_INBOUND_MESSAGE => 'Inbound message is received',
            self::TRIGGER_INBOUND_MESSAGE_NEW => 'Inbound message is received (new conversation)',
            self::TRIGGER_OUTBOUND_MESSAGE_NEW => 'Outbound message is sent (new conversation)',
            self::TRIGGER_OUTBOUND_REPLY => 'Outbound reply is sent',
            self::TRIGGER_INBOUND_CALL => 'Inbound call is received',
            self::TRIGGER_OUTBOUND_CALL => 'Outbound call is placed',
            self::TRIGGER_LEAD_ASSIGNED => 'Lead is assigned',
            self::TRIGGER_LEAD_LABELED => 'Lead is labeled',
            self::TRIGGER_LEAD_NOTE_ADDED => 'Note is added to lead',
        ];
    }

    /**
     * @return list<string>
     */
    public static function inboundTriggers(bool $isNew): array
    {
        return $isNew
            ? [self::TRIGGER_INBOUND_MESSAGE, self::TRIGGER_INBOUND_MESSAGE_NEW]
            : [self::TRIGGER_INBOUND_MESSAGE];
    }

    /**
     * @return list<string>
     */
    public static function outboundTriggers(bool $isNew): array
    {
        return $isNew
            ? [self::TRIGGER_OUTBOUND_MESSAGE_NEW]
            : [self::TRIGGER_OUTBOUND_REPLY];
    }

    /**
     * @return list<string>
     */
    public static function callTriggers(bool $outbound, bool $isNew): array
    {
        if ($outbound) {
            $triggers = [self::TRIGGER_OUTBOUND_CALL];
            $triggers[] = $isNew ? self::TRIGGER_OUTBOUND_MESSAGE_NEW : self::TRIGGER_OUTBOUND_REPLY;

            return $triggers;
        }

        $triggers = [self::TRIGGER_INBOUND_CALL, self::TRIGGER_INBOUND_MESSAGE];
        if ($isNew) {
            $triggers[] = self::TRIGGER_INBOUND_MESSAGE_NEW;
        }

        return $triggers;
    }

    public static function normalizeChannel(string $channel): string
    {
        return match ($channel) {
            'instagram', 'messenger' => 'facebook',
            'phone_system', 'call', 'voice' => 'phone',
            default => $channel,
        };
    }

    /**
     * @param  string|array<int, string>  $triggers
     * @param  array{contact_name?: ?string, phone?: ?string, email?: ?string, subject?: ?string, message?: ?string}  $context
     */
    public function apply(?Lead $lead, string $channel, string|array $triggers, array $context = []): void
    {
        if (! $lead || self::$running) {
            return;
        }

        $eventTriggers = array_values(array_filter(array_map('strval', (array) $triggers)));
        if ($eventTriggers === []) {
            return;
        }

        $channel = $channel !== '' ? self::normalizeChannel($channel) : '';
        $lead->loadMissing(['identities', 'labels', 'assignedUser']);

        $rules = LeadRule::query()
            ->where('company_id', $lead->company_id)
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        self::$running = true;
        try {
            foreach ($rules as $rule) {
                if (! $this->ruleMatchesTriggers($rule, $eventTriggers)) {
                    continue;
                }
                if (! $this->matches($lead, $channel, $rule->conditions ?? [], $context)) {
                    continue;
                }
                $this->runActions($lead, $rule->actions ?? []);
                if ($rule->stop_processing) {
                    break;
                }
            }
        } finally {
            self::$running = false;
        }
    }

    /**
     * @param  array<int, string>  $eventTriggers
     */
    private function ruleMatchesTriggers(LeadRule $rule, array $eventTriggers): bool
    {
        $ruleTriggers = $rule->triggers;
        if (! is_array($ruleTriggers) || $ruleTriggers === []) {
            $ruleTriggers = [self::TRIGGER_INBOUND_MESSAGE_NEW];
        }

        return count(array_intersect($ruleTriggers, $eventTriggers)) > 0;
    }

    /**
     * @param  array<int, array{field?: string, operator?: string, value?: mixed}>  $conditions
     * @param  array{contact_name?: ?string, phone?: ?string, email?: ?string, subject?: ?string, message?: ?string}  $context
     */
    public function matches(Lead $lead, string $channel, array $conditions, array $context): bool
    {
        if ($conditions === []) {
            return false;
        }

        $leadPhones = implode(' ', $lead->phoneValues());
        $leadEmails = implode(' ', $lead->emailValues());

        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? '';
            $operator = $condition['operator'] ?? 'contains';
            $value = $condition['value'] ?? '';

            if ($field === 'channel') {
                $channels = collect(is_array($value) ? $value : explode(',', (string) $value))
                    ->map(fn ($id) => self::normalizeChannel(trim((string) $id)))
                    ->filter()
                    ->values()
                    ->all();
                if ($channels === []) {
                    continue;
                }
                if ($channel === '' || ! in_array($channel, $channels, true)) {
                    return false;
                }
                continue;
            }

            if ($field === 'lead_status') {
                if (! $this->compare((string) $lead->status, (string) $value, $operator === 'equals' ? 'equals' : 'equals')) {
                    return false;
                }
                continue;
            }

            if ($field === 'lead_label') {
                $wanted = mb_strtolower(trim((string) $value));
                $has = $lead->labels->contains(
                    fn (LeadLabel $label) => mb_strtolower($label->name) === $wanted
                        || (string) $label->id === (string) $value
                );
                if (! $has) {
                    return false;
                }
                continue;
            }

            $haystack = match ($field) {
                'contact_name' => (string) ($context['contact_name'] ?? $lead->name),
                'phone' => (string) ($context['phone'] ?? $leadPhones),
                'email' => (string) ($context['email'] ?? $leadEmails),
                'subject' => (string) ($context['subject'] ?? ''),
                'message' => (string) ($context['message'] ?? ''),
                default => '',
            };

            if (! $this->compare($haystack, (string) $value, $operator)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, array{type?: string, value?: mixed}>  $actions
     */
    public function runActions(Lead $lead, array $actions): void
    {
        foreach ($actions as $action) {
            $type = $action['type'] ?? '';
            $value = $action['value'] ?? null;

            try {
                match ($type) {
                    'assign' => $this->assign($lead, $value),
                    'add_label' => $this->addLabel($lead, $value),
                    'set_status' => $this->setStatus($lead, $value),
                    'notify_assignee' => $this->notifyAssignee($lead),
                    default => null,
                };
            } catch (\Throwable $e) {
                Log::warning('Lead rule action failed', [
                    'lead_id' => $lead->id,
                    'action' => $type,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->crmLookup->forgetLeadIndexes((int) $lead->company_id);
    }

    private function compare(string $haystack, string $compare, string $operator): bool
    {
        return match ($operator) {
            'equals' => strcasecmp(trim($haystack), trim($compare)) === 0,
            'starts_with' => str_starts_with(mb_strtolower($haystack), mb_strtolower($compare)),
            'contains' => str_contains(mb_strtolower($haystack), mb_strtolower($compare)),
            default => false,
        };
    }

    private function assign(Lead $lead, mixed $userId): void
    {
        $userId = (int) $userId;
        if ($userId < 1 || (int) $lead->assigned_to === $userId) {
            return;
        }

        $exists = User::query()
            ->where('company_id', $lead->company_id)
            ->where('id', $userId)
            ->exists();
        if (! $exists) {
            return;
        }

        $fromId = $lead->assigned_to;
        $lead->assigned_to = $userId;
        $lead->save();
        $this->leadActivity->recordAssignment($lead, $fromId, $userId, reason: 'rule');
    }

    private function addLabel(Lead $lead, mixed $labelIdOrName): void
    {
        if ($labelIdOrName === null || $labelIdOrName === '') {
            return;
        }

        $companyId = (int) $lead->company_id;
        $label = null;
        if (is_numeric($labelIdOrName)) {
            $label = LeadLabel::query()
                ->where('company_id', $companyId)
                ->whereKey((int) $labelIdOrName)
                ->first();
        }

        $name = trim((string) $labelIdOrName);
        if (! $label && $name !== '' && ! is_numeric($labelIdOrName)) {
            $label = LeadLabel::query()
                ->where('company_id', $companyId)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->first();
            if (! $label) {
                $label = LeadLabel::create([
                    'company_id' => $companyId,
                    'name' => $name,
                    'color' => '#4338ca',
                ]);
            }
        }

        if (! $label) {
            return;
        }

        $already = $lead->labels()->where('lead_labels.id', $label->id)->exists();
        $lead->labels()->syncWithoutDetaching([$label->id]);
        if (! $already) {
            $this->leadActivity->recordLabel($lead, $label->name, true);
            $lead->unsetRelation('labels');
            $lead->load('labels');
        }
    }

    private function setStatus(Lead $lead, mixed $status): void
    {
        $status = strtolower(trim((string) $status));
        if (! in_array($status, Lead::STATUSES, true) || $lead->status === $status) {
            return;
        }

        $before = $this->leadActivity->snapshot($lead);
        $lead->status = $status;
        $lead->save();
        $this->leadActivity->recordDiff($lead, $before);
    }

    private function notifyAssignee(Lead $lead): void
    {
        $lead->loadMissing('assignedUser');
        $assignee = $lead->assignedUser;
        if (! $assignee instanceof User) {
            return;
        }

        $assignee->notify(new LeadRuleNotification(
            $lead,
            'Rule notification: '.$lead->name.' needs your attention.',
            $lead->source
        ));
    }
}
