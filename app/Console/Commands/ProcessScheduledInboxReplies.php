<?php

namespace App\Console\Commands;

use App\Services\InboxReplyService;
use Illuminate\Console\Command;

class ProcessScheduledInboxReplies extends Command
{
    protected $signature = 'inbox:process-scheduled-replies {--sync : Send inline instead of queueing}';

    protected $description = 'Queue (or send) inbox replies whose scheduled send_at time has passed';

    public function handle(InboxReplyService $replies): int
    {
        if ($this->option('sync')) {
            $result = $replies->processDue(50);
            $this->info('Sent '.$result['sent'].' scheduled reply(ies)'
                .($result['failed'] ? ', '.$result['failed'].' failed' : '').'.');

            return self::SUCCESS;
        }

        $queued = $replies->dispatchDue(50);
        $this->info("Queued {$queued} scheduled reply job(s).");

        return self::SUCCESS;
    }
}
