<?php

namespace App\Services;

use App\Jobs\ProcessInboxConversationReopenJob;
use App\Models\InboxConversation;
use App\Models\InboxConversationActivity;
use App\Models\InboxConversationUserRead;
use App\Models\User;
use App\Notifications\InboxThreadUpdateNotification;
use Illuminate\Support\Facades\Log;

class InboxReopenService
{
    /**
     * Queue reopen jobs for snoozed conversations whose reopen_at has passed.
     */
    public function dispatchDue(int $limit = 200): int
    {
        $ids = InboxConversation::query()
            ->whereNull('merged_into_id')
            ->whereNotNull('reopen_at')
            ->where('reopen_at', '<=', now())
            ->orderBy('reopen_at')
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->pluck('id');

        foreach ($ids as $id) {
            ProcessInboxConversationReopenJob::dispatch((int) $id);
        }

        return $ids->count();
    }

    /**
     * Reopen snoozed / scheduled conversations whose reopen_at has passed.
     */
    public function processDue(int $limit = 200): int
    {
        $count = 0;

        InboxConversation::query()
            ->whereNull('merged_into_id')
            ->whereNotNull('reopen_at')
            ->where('reopen_at', '<=', now())
            ->orderBy('reopen_at')
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get()
            ->each(function (InboxConversation $conversation) use (&$count) {
                if ($this->reopenConversation($conversation)) {
                    $count++;
                }
            });

        return $count;
    }

    /**
     * Apply snooze reopen for a single conversation (idempotent if reopen_at cleared).
     */
    public function reopenConversation(InboxConversation $conversation): bool
    {
        $conversation->refresh();

        if ($conversation->merged_into_id) {
            return false;
        }

        if (! $conversation->reopen_at || $conversation->reopen_at->isFuture()) {
            return false;
        }

        $conversation->applyOpenFromHold();
        $conversation->is_read = false;
        $conversation->save();

        InboxConversationUserRead::query()
            ->where('inbox_conversation_id', $conversation->id)
            ->where('is_read', true)
            ->update(['is_read' => false]);

        InboxConversationActivity::create([
            'inbox_conversation_id' => $conversation->id,
            'user_id' => null,
            'action' => 'reopened',
            'summary' => 'Conversation reopened after snooze',
            'meta' => ['source' => 'snooze_reopen'],
        ]);

        $this->notifyAssigneeOfReopen($conversation);

        return true;
    }

    private function notifyAssigneeOfReopen(InboxConversation $conversation): void
    {
        $assignee = User::query()->find($conversation->assigned_to);
        if (! $assignee instanceof User) {
            return;
        }

        $subject = $conversation->subject ?: 'a conversation';

        try {
            $assignee->notify(new InboxThreadUpdateNotification(
                conversation: $conversation,
                action: 'reopened',
                summary: 'A snoozed conversation was reopened: "'.$subject.'"',
                snippet: $conversation->snippet,
                involves: 'reopen',
            ));
        } catch (\Throwable $e) {
            Log::warning('Failed to notify assignee of snooze reopen', [
                'conversation_id' => $conversation->id,
                'user_id' => $assignee->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
