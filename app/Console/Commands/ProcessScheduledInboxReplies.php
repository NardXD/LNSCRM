<?php

namespace App\Console\Commands;

use App\Models\ScheduledInboxReply;
use App\Services\InboxReplyService;
use Illuminate\Console\Command;

class ProcessScheduledInboxReplies extends Command
{
    protected $signature = 'inbox:process-scheduled-replies';

    protected $description = 'Send inbox replies whose scheduled send_at time has passed';

    public function handle(InboxReplyService $replies): int
    {
        $count = 0;
        $failed = 0;

        ScheduledInboxReply::query()
            ->where('status', ScheduledInboxReply::STATUS_SENDING)
            ->where('updated_at', '<', now()->subMinutes(10))
            ->update([
                'status' => ScheduledInboxReply::STATUS_PENDING,
                'error_message' => null,
            ]);

        ScheduledInboxReply::query()
            ->where('status', ScheduledInboxReply::STATUS_PENDING)
            ->where('send_at', '<=', now())
            ->orderBy('send_at')
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->each(function (ScheduledInboxReply $scheduled) use ($replies, &$count, &$failed) {
                $result = $replies->dispatchScheduled($scheduled->fresh(['conversation', 'user', 'inbox.account']));
                if ($result) {
                    $count++;
                } else {
                    $failed++;
                }
            });

        $this->info("Sent {$count} scheduled reply(ies)".($failed ? ", {$failed} failed" : '').'.');

        return self::SUCCESS;
    }
}
