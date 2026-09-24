<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\DiscussionRule;
use App\Models\InboxTag;
use App\Models\User;
use App\Notifications\DiscussionUpdateNotification;
use Illuminate\Support\Facades\Log;

class DiscussionRuleEngine
{
    public const TRIGGER_DISCUSSION_CREATED = 'discussion_created';

    public const TRIGGER_COMMENT_ADDED = 'comment_added';

    public const TRIGGER_DISCUSSION_ASSIGNED = 'discussion_assigned';

    public const TRIGGER_DISCUSSION_TAGGED = 'discussion_tagged';

    public const TRIGGER_DISCUSSION_ARCHIVED = 'discussion_archived';

    public const TRIGGER_DISCUSSION_MOVED = 'discussion_moved';

    /**
     * @return array<string, string>
     */
    public static function triggerLabels(): array
    {
        return [
            self::TRIGGER_DISCUSSION_CREATED => 'Discussion is created',
            self::TRIGGER_COMMENT_ADDED => 'Comment is added',
            self::TRIGGER_DISCUSSION_ASSIGNED => 'Discussion is assigned',
            self::TRIGGER_DISCUSSION_TAGGED => 'Discussion is tagged',
            self::TRIGGER_DISCUSSION_ARCHIVED => 'Discussion is archived',
            self::TRIGGER_DISCUSSION_MOVED => 'Discussion is moved',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function actionLabels(): array
    {
        return [
            'assign' => 'Assign to teammate',
            'tag' => 'Add tag',
            'archive' => 'Archive',
            'reopen' => 'Reopen',
            'move' => 'Move to shared inbox',
            'notify_assignee' => 'Notify assignee',
        ];
    }

    /**
     * @param  string|array<int, string>  $triggers
     */
    public function apply(Conversation $conversation, string|array $triggers = self::TRIGGER_DISCUSSION_CREATED): void
    {
        if (! $conversation->isDiscussion()) {
            return;
        }

        $eventTriggers = array_values(array_filter(array_map('strval', (array) $triggers)));
        if ($eventTriggers === []) {
            return;
        }

        $rules = DiscussionRule::where('company_id', $conversation->company_id)
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
    private function ruleMatchesTriggers(DiscussionRule $rule, array $eventTriggers): bool
    {
        $ruleTriggers = $rule->triggers;
        if (! is_array($ruleTriggers) || $ruleTriggers === []) {
            $ruleTriggers = [self::TRIGGER_DISCUSSION_CREATED];
        }

        return count(array_intersect($ruleTriggers, $eventTriggers)) > 0;
    }

    /**
     * @param  array<int, array{field?: string, operator?: string, value?: mixed}>  $conditions
     */
    public function matches(Conversation $conversation, array $conditions): bool
    {
        if ($conditions === []) {
            return true;
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
                if ($inboxIds === []) {
                    continue;
                }
                if (! in_array((int) $conversation->shared_inbox_id, $inboxIds, true)) {
                    return false;
                }
                continue;
            }

            $haystack = match ($field) {
                'subject' => (string) $conversation->name,
                'assignee' => (string) ($conversation->assigned_to ?? ''),
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
    public function runActions(Conversation $conversation, array $actions): void
    {
        foreach ($actions as $action) {
            $type = $action['type'] ?? '';
            $value = $action['value'] ?? null;

            try {
                match ($type) {
                    'assign' => $this->assign($conversation, $value),
                    'tag' => $this->addTag($conversation, $value),
                    'archive' => $this->setStatus($conversation, Conversation::STATUS_ARCHIVED),
                    'reopen' => $this->setStatus($conversation, Conversation::STATUS_OPEN),
                    'move' => $this->move($conversation, $value),
                    'notify_assignee' => $this->notifyAssignee($conversation),
                    default => null,
                };
            } catch (\Throwable $e) {
                Log::warning('Discussion rule action failed', [
                    'conversation_id' => $conversation->id,
                    'action' => $type,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function assign(Conversation $conversation, mixed $userId): void
    {
        if (! $userId) {
            return;
        }

        $user = User::query()
            ->where('company_id', $conversation->company_id)
            ->where('id', (int) $userId)
            ->first();
        if (! $user) {
            return;
        }

        $conversation->assigned_to = $user->id;
        $conversation->save();

        if (! $conversation->participants()->where('users.id', $user->id)->exists()) {
            $conversation->participants()->attach($user->id, ['last_read_at' => now()]);
        }
    }

    private function addTag(Conversation $conversation, mixed $tagIdOrName): void
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

    private function setStatus(Conversation $conversation, string $status): void
    {
        $conversation->status = $status;
        if ($status === Conversation::STATUS_OPEN) {
            $conversation->reopen_at = null;
        }
        $conversation->save();
    }

    private function move(Conversation $conversation, mixed $inboxId): void
    {
        $inboxId = (int) $inboxId;
        if ($inboxId < 1) {
            return;
        }

        $inbox = \App\Models\SharedInbox::query()
            ->where('company_id', $conversation->company_id)
            ->where('id', $inboxId)
            ->where('type', \App\Models\SharedInbox::TYPE_SHARED)
            ->where('is_active', true)
            ->first();

        if (! $inbox) {
            return;
        }

        if ($conversation->moved_to_shared_at && ! $conversation->shared_inbox_id) {
            // Already locked to shared-only moves conceptually; still allow shared→shared.
        }

        $conversation->shared_inbox_id = $inbox->id;
        $conversation->moved_to_shared_at = $conversation->moved_to_shared_at ?? now();
        $conversation->save();
    }

    private function notifyAssignee(Conversation $conversation): void
    {
        $conversation->loadMissing('assignee');
        $assignee = $conversation->assignee;
        if (! $assignee instanceof User) {
            return;
        }

        $subject = $conversation->name ?: '(No subject)';
        $assignee->notify(new DiscussionUpdateNotification(
            $conversation,
            'Rule notification: "'.$subject.'" needs your attention.',
        ));
    }
}
