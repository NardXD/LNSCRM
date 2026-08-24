<?php

namespace App\Services;

use App\Models\InboxConversation;
use App\Models\InboxConversationActivity;
use App\Models\InboxConversationUserRead;

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
                $conversation->status = 'open';
                $conversation->folder = 'inbox';
                $conversation->is_read = false;
                $conversation->reopen_at = null;
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

                $count++;
            });

        return $count;
    }
}
