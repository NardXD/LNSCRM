<?php

namespace App\Services;

use App\Models\InboxConversation;
use App\Models\InboxRule;
use App\Models\InboxTag;
use App\Models\User;
use App\Notifications\InboxThreadUpdateNotification;
use Illuminate\Support\Facades\Log;

class InboxRuleEngine
{
    public const TRIGGER_INBOUND_MESSAGE = 'inbound_message';

    public const TRIGGER_INBOUND_MESSAGE_NEW = 'inbound_message_new';

    public const TRIGGER_OUTBOUND_MESSAGE_NEW = 'outbound_message_new';

    public const TRIGGER_OUTBOUND_REPLY = 'outbound_reply';

    public const TRIGGER_CONVERSATION_ASSIGNED = 'conversation_assigned';

    public const TRIGGER_CONVERSATION_TAGGED = 'conversation_tagged';

    public const TRIGGER_CONVERSATION_ARCHIVED = 'conversation_archived';

    public const TRIGGER_CONVERSATION_MOVED = 'conversation_moved';

    public const TRIGGER_COMMENT_ADDED = 'comment_added';

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
            self::TRIGGER_CONVERSATION_ASSIGNED => 'Conversation is assigned',
            self::TRIGGER_CONVERSATION_TAGGED => 'Conversation is tagged',
            self::TRIGGER_CONVERSATION_ARCHIVED => 'Conversation is archived',
            self::TRIGGER_CONVERSATION_MOVED => 'Conversation is moved',
            self::TRIGGER_COMMENT_ADDED => 'Internal comment is added',
        ];
    }

    /**
     * @param  string|array<int, string>  $triggers
     */
    public function apply(InboxConversation $conversation, string|array $triggers = self::TRIGGER_INBOUND_MESSAGE_NEW): void
    {
        $eventTriggers = array_values(array_filter(array_map('strval', (array) $triggers)));
        if ($eventTriggers === []) {
            return;
        }

        $rules = InboxRule::where('company_id', $conversation->company_id)
            ->where('is_active', true)
            ->where(function ($q) use ($conversation) {
                $q->whereNull('shared_inbox_id')
                    ->orWhere('shared_inbox_id', $conversation->shared_inbox_id);
            })
            ->orderBy('priority')
            ->get();

        foreach ($rules as $rule) {
            if (! $this->ruleMatchesTriggers($rule, $eventTriggers)) {
                continue;
            }
            if ($this->matches($conversation, $rule->conditions ?? [])) {
                $this->runActions($conversation, $rule->actions ?? []);
                if ($rule->stop_processing) {
                    break;
                }
            }
        }
    }

    /**
     * @param  array<int, string>  $eventTriggers
     */
    private function ruleMatchesTriggers(InboxRule $rule, array $eventTriggers): bool
    {
        $ruleTriggers = $rule->triggers;
        if (! is_array($ruleTriggers) || $ruleTriggers === []) {
            // Legacy rules (before triggers column) behaved like new-conversation inbound.
            $ruleTriggers = [self::TRIGGER_INBOUND_MESSAGE_NEW];
        }

        return count(array_intersect($ruleTriggers, $eventTriggers)) > 0;
    }

    /**
     * @param  array<int, array{field?: string, operator?: string, value?: mixed}>  $conditions
     */
    public function matches(InboxConversation $conversation, array $conditions): bool
    {
        if ($conditions === []) {
            return false;
        }

        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? '';
            $operator = $condition['operator'] ?? 'contains';
            $value = $condition['value'] ?? '';

            if ($field === 'inbox') {
                $inboxIds = collect(is_array($value) ? $value : explode(',', (string) $value))
                    ->map(fn ($id) => (int) $id)
                    ->filter()
                    ->values()
                    ->all();
                // Empty inbox list = all inboxes.
                if ($inboxIds === []) {
                    continue;
                }
                if (! in_array((int) $conversation->shared_inbox_id, $inboxIds, true)) {
                    return false;
                }
                continue;
            }

            $haystack = match ($field) {
                'from_email' => (string) $conversation->from_email,
                'from_name' => (string) $conversation->from_name,
                'subject' => (string) $conversation->subject,
                'snippet' => (string) $conversation->snippet,
                default => '',
            };

            $compare = (string) $value;
            $ok = match ($operator) {
                'equals' => strcasecmp($haystack, $compare) === 0,
                'starts_with' => str_starts_with(mb_strtolower($haystack), mb_strtolower($compare)),
                'contains' => str_contains(mb_strtolower($haystack), mb_strtolower($compare)),
                default => false,
            };

            if (! $ok) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, array{type?: string, value?: mixed}>  $actions
     */
    public function runActions(InboxConversation $conversation, array $actions): void
    {
        foreach ($actions as $action) {
            $type = $action['type'] ?? '';
            $value = $action['value'] ?? null;

            try {
                match ($type) {
                    'assign' => $this->assign($conversation, $value),
                    'tag' => $this->addTag($conversation, $value),
                    'archive' => $this->setStatus($conversation, 'archived'),
                    'reopen' => $this->reopenNow($conversation),
                    'reopen_after_days' => $this->scheduleReopen($conversation, $value),
                    'notify_assignee' => $this->notifyAssignee($conversation),
                    'mark_read' => tap($conversation)->update(['is_read' => true]),
                    'mark_unread' => tap($conversation)->update(['is_read' => false]),
                    default => null,
                };
            } catch (\Throwable $e) {
                Log::warning('Inbox rule action failed', [
                    'conversation_id' => $conversation->id,
                    'action' => $type,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function assign(InboxConversation $conversation, mixed $userId): void
    {
        if (! $userId) {
            return;
        }

        $conversation->assigned_to = (int) $userId;
        $conversation->save();
    }

    private function notifyAssignee(InboxConversation $conversation): void
    {
        $conversation->loadMissing('assignee');
        $assignee = $conversation->assignee;
        if (! $assignee instanceof User) {
            return;
        }

        $subject = $conversation->subject ?: '(No subject)';
        $assignee->notify(new InboxThreadUpdateNotification(
            $conversation,
            'rule_notify_assignee',
            'Rule notification: "'.$subject.'" needs your attention.',
            null,
            $conversation->snippet,
            false
        ));
    }
    private function addTag(InboxConversation $conversation, mixed $tagIdOrName): void
    {
        if (! $tagIdOrName) {
            return;
        }

        if (is_numeric($tagIdOrName)) {
            $tag = InboxTag::where('company_id', $conversation->company_id)
                ->where('id', (int) $tagIdOrName)
                ->first();
        } else {
            $tag = InboxTag::firstOrCreate(
                [
                    'company_id' => $conversation->company_id,
                    'name' => (string) $tagIdOrName,
                ],
                ['color' => '#64748b']
            );
        }

        if ($tag) {
            $conversation->tags()->syncWithoutDetaching([$tag->id]);
        }
    }

    private function setStatus(InboxConversation $conversation, string $status): void
    {
        $conversation->status = $status;
        if ($status === 'open') {
            $conversation->folder = 'inbox';
            $conversation->reopen_at = null;
        }
        $conversation->save();
    }

    private function reopenNow(InboxConversation $conversation): void
    {
        $conversation->status = 'open';
        $conversation->folder = 'inbox';
        $conversation->reopen_at = null;
        $conversation->save();
    }

    private function scheduleReopen(InboxConversation $conversation, mixed $days): void
    {
        $days = (int) $days;
        if ($days < 1) {
            return;
        }
        if ($days > 365) {
            $days = 365;
        }

        // Move out of Open now, then bring back after N days.
        $conversation->status = 'archived';
        $conversation->folder = 'inbox';
        $conversation->reopen_at = now()->addDays($days);
        $conversation->save();
    }
}
