<?php

namespace App\Services;

use App\Models\InboxConversation;
use App\Models\InboxTemplate;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadLabel;
use App\Models\LeadRule;
use App\Models\LeadScheduledEmail;
use App\Models\LeadStatus;
use App\Models\User;
use App\Notifications\LeadRuleNotification;
use App\Support\EmailQuotedHistory;
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

    public const TRIGGER_LEAD_STATUS_CHANGED = 'lead_status_changed';

    public const TRIGGER_LEAD_NOTE_ADDED = 'lead_note_added';

    public const TRIGGER_FOLLOW_UP_DAY_REACHED = 'follow_up_day_reached';

    public const ASSIGN_AVAILABLE = '__available__';

    public const ASSIGN_AVAILABLE_ROUND_ROBIN = '__available_round_robin__';

    public const ASSIGN_ROUND_ROBIN = '__round_robin__';

    public const ASSIGN_ROUND_ROBIN_SELECTED = '__round_robin_selected__';

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
        protected FlexCrmLookupService $crmLookup,
        protected LeadChannelMessageService $channelMessages
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
            self::TRIGGER_LEAD_LABELED => 'Label added',
            self::TRIGGER_LEAD_STATUS_CHANGED => 'Status changed',
            self::TRIGGER_LEAD_NOTE_ADDED => 'Note is added to lead',
            self::TRIGGER_FOLLOW_UP_DAY_REACHED => 'Follow-up day is reached',
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
     * @param  array{company_id?: int, contact_name?: ?string, phone?: ?string, email?: ?string, subject?: ?string, message?: ?string, added_label?: ?string, added_label_id?: int|string|null, inbox_id?: int|string|null, shared_inbox_id?: int|string|null}  $context
     */
    public function apply(?Lead $lead, string $channel, string|array $triggers, array $context = []): ?Lead
    {
        if (self::$running) {
            return $lead;
        }

        $eventTriggers = array_values(array_filter(array_map('strval', (array) $triggers)));
        $companyId = (int) ($lead?->company_id ?: ($context['company_id'] ?? 0));
        if ($companyId < 1 || $eventTriggers === []) {
            return $lead;
        }

        $channel = $channel !== '' ? self::normalizeChannel($channel) : '';
        $lead?->loadMissing(['identities', 'labels', 'assignedUser']);

        $rules = LeadRule::query()
            ->where('company_id', $companyId)
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
                $lead = $this->runActions($lead, $rule->actions ?? [], $channel, $context, $companyId, $rule);
                LeadRule::whereKey($rule->id)->update(['last_applied_at' => now()]);
                if ($rule->stop_processing) {
                    break;
                }
            }
        } finally {
            self::$running = false;
        }

        return $lead;
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
     * @param  array{company_id?: int, contact_name?: ?string, phone?: ?string, email?: ?string, subject?: ?string, message?: ?string, added_label?: ?string, added_label_id?: int|string|null, inbox_id?: int|string|null, shared_inbox_id?: int|string|null}  $context
     */
    public function matches(?Lead $lead, string $channel, array $conditions, array $context): bool
    {
        if ($conditions === []) {
            return false;
        }

        $leadPhones = $lead ? implode(' ', $lead->phoneValues()) : '';
        $leadEmails = $lead ? implode(' ', $lead->emailValues()) : '';

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

            if ($field === 'shared_inbox' || $field === 'inbox') {
                $inboxIds = collect(is_array($value) ? $value : explode(',', (string) $value))
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0)
                    ->values()
                    ->all();
                if ($inboxIds === []) {
                    continue;
                }
                $eventInboxId = (int) ($context['inbox_id'] ?? $context['shared_inbox_id'] ?? 0);
                if ($eventInboxId < 1 || ! in_array($eventInboxId, $inboxIds, true)) {
                    return false;
                }
                continue;
            }

            if ($field === 'lead_status') {
                if (! $lead || ! $this->compare((string) $lead->status, (string) $value, $operator === 'equals' ? 'equals' : 'equals')) {
                    return false;
                }
                continue;
            }

            if ($field === 'lead_label') {
                if (! $lead) {
                    return false;
                }
                $wanted = mb_strtolower(trim((string) $value));
                $has = $lead->labels->contains(
                    fn (LeadLabel $label) => mb_strtolower($label->name) === $wanted
                        || (string) $label->id === (string) $value
                );
                $missing = in_array($operator, ['does_not_have', 'not_equals'], true);
                if ($missing ? $has : ! $has) {
                    return false;
                }
                continue;
            }

            if ($field === 'status_changed') {
                $changed = strtolower(trim((string) ($context['changed_status'] ?? '')));
                if ($changed === '') {
                    continue;
                }
                if ($changed !== strtolower(trim((string) $value))) {
                    return false;
                }
                continue;
            }

            if ($field === 'label_added') {
                $addedName = mb_strtolower(trim((string) ($context['added_label'] ?? '')));
                $addedId = trim((string) ($context['added_label_id'] ?? ''));
                if ($addedName === '' && $addedId === '') {
                    continue;
                }
                $wanted = trim((string) $value);
                $wantedLower = mb_strtolower($wanted);
                $matched = ($addedId !== '' && $addedId === $wanted)
                    || ($addedName !== '' && $addedName === $wantedLower);
                if (! $matched) {
                    return false;
                }
                continue;
            }

            if ($field === 'follow_up_day') {
                $eventDay = (int) ($context['follow_up_day'] ?? 0);
                if ($eventDay < 1 && $lead) {
                    $eventDay = app(LeadFollowUpDayService::class)->dayFor($lead);
                }
                $wanted = trim((string) $value);
                $followUp = app(LeadFollowUpDayService::class);
                $matched = $followUp->isPlusValue($wanted)
                    ? $eventDay >= $followUp->plusMin((int) ($lead?->company_id ?: ($context['company_id'] ?? 0)))
                    : $eventDay === (int) $wanted;
                if (! $matched) {
                    return false;
                }
                continue;
            }

            $haystack = match ($field) {
                'contact_name' => (string) ($context['contact_name'] ?? $lead?->name ?? ''),
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
     * @param  array{company_id?: int, contact_name?: ?string, phone?: ?string, email?: ?string, facebook_name?: ?string, instagram_username?: ?string}  $context
     */
    public function runActions(?Lead $lead, array $actions, string $channel = '', array $context = [], int $companyId = 0, ?LeadRule $rule = null): ?Lead
    {
        $ordered = [];
        foreach ($actions as $action) {
            if (($action['type'] ?? '') === 'create_lead') {
                array_unshift($ordered, $action);
            } else {
                $ordered[] = $action;
            }
        }

        foreach ($ordered as $action) {
            $type = $action['type'] ?? '';
            $value = $action['value'] ?? null;

            try {
                if ($type === 'create_lead') {
                    $lead = $this->createLead($lead, $channel, $context, $companyId, $value);
                    continue;
                }
                if (! $lead) {
                    continue;
                }
                match ($type) {
                    'assign' => $this->assign($lead, $value),
                    'add_label' => $this->addLabel($lead, $value),
                    'set_status' => $this->setStatus($lead, $value),
                    'set_status_after_days' => $this->scheduleStatus($lead, $value),
                    'notify_assignee' => $this->notifyAssignee($lead),
                    'reopen_after_days' => $this->scheduleReopen($lead, $value),
                    'unsnooze' => $this->unsnooze($lead),
                    'send_email' => $this->sendEmail($lead, $value, $rule),
                    'attach_shared_inbox' => $this->attachSharedInbox($lead, $context, $rule),
                    default => null,
                };
            } catch (\Throwable $e) {
                Log::warning('Lead rule action failed', [
                    'lead_id' => $lead?->id,
                    'action' => $type,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($lead) {
            $this->crmLookup->forgetLeadIndexes((int) $lead->company_id);
        }

        return $lead;
    }

    /**
     * @param  array{company_id?: int, contact_name?: ?string, phone?: ?string, email?: ?string, facebook_name?: ?string, instagram_username?: ?string, subject?: ?string, message?: ?string, inbox_conversation_id?: int|string|null}  $context
     */
    private function createLead(?Lead $lead, string $channel, array $context, int $companyId, mixed $keywords = null): ?Lead
    {
        if ($companyId < 1) {
            return $lead;
        }

        $blank = static function (mixed $value): ?string {
            $value = trim((string) $value);

            return $value !== '' ? $value : null;
        };

        $keywordMap = is_array($keywords) ? $keywords : [];
        $extracted = app(MessageContactExtractor::class)->fromKeywords(
            $this->messageHaystack($context, $keywordMap),
            $keywordMap
        );

        $created = app(LeadAutoCreateService::class)->ensure(
            $companyId,
            $channel !== '' ? $channel : 'inbox',
            $blank($extracted['name'] ?? null) ?: $blank($context['contact_name'] ?? null),
            $blank($extracted['phone'] ?? null) ?: $blank($context['phone'] ?? null),
            $blank($extracted['email'] ?? null) ?: $blank($context['email'] ?? null),
            $blank($context['facebook_name'] ?? null),
            $blank($context['instagram_username'] ?? null),
        );

        return $created?->load(['identities', 'labels', 'assignedUser']) ?? $lead;
    }

    /**
     * @param  array{subject?: ?string, message?: ?string, inbox_conversation_id?: int|string|null}  $context
     * @param  array<string, mixed>  $keywords
     */
    private function messageHaystack(array $context, array $keywords): string
    {
        $parts = [
            (string) ($context['subject'] ?? ''),
            (string) ($context['message'] ?? ''),
        ];

        $conversationId = (int) ($context['inbox_conversation_id'] ?? 0);
        if ($conversationId > 0 && $this->hasCreateLeadKeywords($keywords)) {
            $body = $this->inboxBody($conversationId);
            if ($body !== '') {
                $parts[] = $body;
            }
        }

        return trim(implode("\n", array_filter($parts, fn ($part) => trim($part) !== '')));
    }

    /**
     * @param  array<string, mixed>  $keywords
     */
    private function hasCreateLeadKeywords(array $keywords): bool
    {
        foreach (['name', 'phone', 'email', 'name_keyword', 'phone_keyword', 'email_keyword'] as $key) {
            if (trim((string) ($keywords[$key] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    private function inboxBody(int $conversationId): string
    {
        $conversation = InboxConversation::query()->with('inbox.account')->find($conversationId);
        if (! $conversation) {
            return '';
        }

        try {
            if ($conversation->inbox) {
                app(OutlookMailService::class)->hydrateConversationBodies($conversation->inbox, $conversation);
            }
        } catch (\Throwable $e) {
            Log::warning('Lead rule could not load full inbox body', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);
        }

        $message = $conversation->messages()
            ->where('direction', 'inbound')
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->first();

        // body_text is often built with a bare strip_tags() during Outlook sync, which
        // fuses adjacent table cells/divs with no separating whitespace (e.g. a "Name:"
        // label and its value in neighboring <td>s read as one word). Re-deriving from
        // body_html keeps label/value pairs on their own line for keyword matching.
        $html = (string) ($message?->body_html ?? '');
        $text = trim($html) !== ''
            ? EmailQuotedHistory::plainFromHtml($html)
            : (string) ($message?->body_text ?: '');

        return trim($text !== '' ? $text : (string) ($conversation->snippet ?: ''));
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

    private function assign(Lead $lead, mixed $value): void
    {
        $userId = $this->resolveAssigneeId($lead, $value);
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

    private function resolveAssigneeId(Lead $lead, mixed $value): int
    {
        $queue = app(InboundCallQueueService::class);
        $companyId = (int) $lead->company_id;

        if (is_array($value)) {
            $mode = trim((string) ($value['mode'] ?? ''));
            $userIds = is_array($value['user_ids'] ?? null) ? $value['user_ids'] : [];
            if ($mode === self::ASSIGN_ROUND_ROBIN_SELECTED) {
                $agent = $queue->pickNextFromUserIds($companyId, $userIds);

                return $agent instanceof User ? (int) $agent->id : 0;
            }

            $value = $mode !== '' ? $mode : ($value['user_id'] ?? $value['assigned_to'] ?? 0);
        }

        $raw = is_string($value) ? trim($value) : (string) $value;
        if ($raw === self::ASSIGN_AVAILABLE || $raw === self::ASSIGN_AVAILABLE_ROUND_ROBIN || $raw === self::ASSIGN_ROUND_ROBIN) {
            $agent = match ($raw) {
                self::ASSIGN_ROUND_ROBIN => $queue->pickNextLeadTeammate($companyId),
                self::ASSIGN_AVAILABLE_ROUND_ROBIN => $queue->pickNextLeadAgent($companyId),
                default => $queue->availableAgents($companyId)->sortBy('id')->first(),
            };

            return $agent instanceof User ? (int) $agent->id : 0;
        }

        return (int) $raw;
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
            $this->leadActivity->recordLabel($lead, $label->name, true, labelId: $label->id);
            $lead->unsetRelation('labels');
            $lead->load('labels');
        }
    }

    private function setStatus(Lead $lead, mixed $status): void
    {
        $status = strtolower(trim((string) $status));
        $slugs = LeadStatus::slugsForCompany((int) $lead->company_id);
        if ($status === Lead::STATUS_SNOOZED || ! in_array($status, $slugs, true) || $lead->status === $status) {
            return;
        }

        $before = $this->leadActivity->snapshot($lead);
        $lead->status = $status;
        $lead->reopen_at = null;
        $lead->reopen_status = null;
        $lead->save();
        $this->leadActivity->recordDiff($lead, $before);
    }

    private function scheduleStatus(Lead $lead, mixed $value): void
    {
        $days = 0;
        $status = '';
        if (is_array($value)) {
            $days = (int) ($value['days'] ?? 0);
            $status = strtolower(trim((string) ($value['status'] ?? '')));
        } else {
            $status = strtolower(trim((string) $value));
        }
        if ($days < 1) {
            return;
        }
        if ($days > 365) {
            $days = 365;
        }

        $slugs = LeadStatus::slugsForCompany((int) $lead->company_id);
        if ($status === '' || $status === Lead::STATUS_SNOOZED || ! in_array($status, $slugs, true)) {
            return;
        }

        $from = strtolower(trim((string) $lead->status));
        $lead->scheduled_status_from = $from !== '' ? $from : null;
        $lead->scheduled_status = $status;
        $lead->scheduled_status_at = now()->addDays($days);
        $lead->save();

        $until = $lead->scheduled_status_at?->toFormattedDateString() ?: $lead->scheduled_status_at;
        $this->leadActivity->record(
            $lead,
            LeadActivity::STATUS_SCHEDULED,
            'Status will change to '.LeadStatus::nameFor((int) $lead->company_id, $status).' in '.$days.' '.($days === 1 ? 'day' : 'days').' ('.$until.')',
            [
                'source' => 'set_status_after_days',
                'days' => $days,
                'from' => $lead->scheduled_status_from,
                'to' => $status,
                'scheduled_status_at' => $lead->scheduled_status_at?->toIso8601String(),
            ]
        );
    }

    private function scheduleReopen(Lead $lead, mixed $days): void
    {
        $days = (int) $days;
        if ($days < 1) {
            return;
        }
        if ($days > 365) {
            $days = 365;
        }

        if ($lead->status !== Lead::STATUS_SNOOZED) {
            $lead->reopen_status = LeadStatus::fallbackSlug((int) $lead->company_id, $lead->status);
        }

        $lead->status = Lead::STATUS_SNOOZED;
        $lead->reopen_at = now()->addDays($days);
        $lead->save();

        $until = $lead->reopen_at?->toFormattedDateString() ?: $lead->reopen_at;
        $this->leadActivity->record(
            $lead,
            LeadActivity::SNOOZED,
            'Lead snoozed for '.$days.' '.($days === 1 ? 'day' : 'days').'; will reopen '.$until,
            [
                'source' => 'reopen_after_days',
                'days' => $days,
                'reopen_at' => $lead->reopen_at?->toIso8601String(),
                'reopen_status' => $lead->reopen_status,
            ]
        );
    }

    private function unsnooze(Lead $lead): void
    {
        if ($lead->status !== Lead::STATUS_SNOOZED) {
            return;
        }

        $restore = LeadStatus::fallbackSlug((int) $lead->company_id, $lead->reopen_status);
        $lead->status = $restore;
        $lead->reopen_at = null;
        $lead->reopen_status = null;
        $lead->save();
        $this->leadActivity->record(
            $lead,
            LeadActivity::REOPENED,
            'Lead reopened by follow-up rule',
            ['source' => 'unsnooze', 'status' => $restore]
        );
    }

    /**
     * @param  array{inbox_conversation_id?: int|string|null}  $context
     */
    private function attachSharedInbox(Lead $lead, array $context, ?LeadRule $rule): void
    {
        $conversationId = (int) ($context['inbox_conversation_id'] ?? 0);
        if ($conversationId < 1) {
            return;
        }

        $conversation = InboxConversation::query()
            ->where('company_id', $lead->company_id)
            ->find($conversationId);
        if (! $conversation || (int) $conversation->lead_id === (int) $lead->id) {
            return;
        }

        $actor = ($rule?->created_by ? User::find($rule->created_by) : null) ?? $lead->assignedUser;
        if (! $actor) {
            return;
        }

        try {
            app(LeadInboxAttachService::class)->attach($lead, $conversation, $actor, requireMembership: false);
        } catch (\Throwable $e) {
            Log::warning('Lead rule could not attach shared inbox thread', [
                'lead_id' => $lead->id,
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);
        }
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

    private function sendEmail(Lead $lead, mixed $value, ?LeadRule $rule): void
    {
        $templateId = (int) (is_array($value) ? ($value['template_id'] ?? 0) : 0);
        if ($templateId < 1) {
            return;
        }

        $template = InboxTemplate::query()
            ->where('company_id', $lead->company_id)
            ->whereKey($templateId)
            ->first();
        if (! $template) {
            return;
        }

        $days = (int) ($value['days'] ?? 0);
        if ($days > 365) {
            $days = 365;
        }
        $mailboxId = (int) ($value['mailbox_id'] ?? 0) ?: null;

        $actor = ($rule?->created_by ? User::find($rule->created_by) : null) ?? $lead->assignedUser;
        if (! $actor) {
            Log::warning('Lead rule could not send email: no sender available', [
                'lead_id' => $lead->id,
                'rule_id' => $rule?->id,
            ]);

            return;
        }

        if ($days < 1) {
            try {
                $this->channelMessages->send($lead, $actor, 'inbox', $templateId, null, null, $mailboxId, null);
            } catch (\Throwable $e) {
                Log::warning('Lead rule could not send email immediately', [
                    'lead_id' => $lead->id,
                    'template_id' => $templateId,
                    'error' => $e->getMessage(),
                ]);
            }

            return;
        }

        $sendAt = now()->addDays($days);
        LeadScheduledEmail::create([
            'company_id' => $lead->company_id,
            'lead_id' => $lead->id,
            'lead_rule_id' => $rule?->id,
            'inbox_template_id' => $templateId,
            'shared_inbox_id' => $mailboxId,
            'user_id' => $actor->id,
            'send_at' => $sendAt,
            'status' => LeadScheduledEmail::STATUS_PENDING,
        ]);

        $this->leadActivity->record(
            $lead,
            LeadActivity::TEMPLATE_SCHEDULED,
            'Email "'.$template->name.'" will be sent in '.$days.' '.($days === 1 ? 'day' : 'days').' ('.$sendAt->toFormattedDateString().')',
            [
                'source' => 'send_email',
                'template_id' => $templateId,
                'days' => $days,
                'send_at' => $sendAt->toIso8601String(),
            ]
        );
    }
}
