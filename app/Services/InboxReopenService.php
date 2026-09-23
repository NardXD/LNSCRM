<?php

namespace App\Services;

use App\Models\InboxConversation;
use App\Models\InboxConversationActivity;
use App\Models\InboxConversationUserRead;
use App\Models\User;
use App\Notifications\InboxThreadUpdateNotification;
use Illuminate\Support\Facades\Log;

class InboxReopenService
{
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

                $count++;
            });

        return $count;
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
