<?php

namespace App\Console\Commands;

use App\Models\InboxConversation;
use App\Models\InboxConversationActivity;
use Illuminate\Console\Command;

class ProcessInboxReopens extends Command
{
    protected $signature = 'inbox:process-reopens';

    protected $description = 'Reopen inbox conversations whose scheduled reopen_at time has passed';

    public function handle(): int
    {
        $count = 0;

        InboxConversation::query()
            ->whereNotNull('reopen_at')
            ->where('reopen_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($conversations) use (&$count) {
                foreach ($conversations as $conversation) {
                    $conversation->status = 'open';
                    $conversation->folder = 'inbox';
                    $conversation->is_read = false;
                    $conversation->reopen_at = null;
                    $conversation->save();

                    InboxConversationActivity::create([
                        'inbox_conversation_id' => $conversation->id,
                        'user_id' => null,
                        'action' => 'reopened',
                        'summary' => 'Conversation reopened automatically by rule schedule',
                        'meta' => ['source' => 'reopen_after_days'],
                    ]);

                    $count++;
                }
            });

        $this->info("Reopened {$count} conversation(s).");

        return self::SUCCESS;
    }
}
