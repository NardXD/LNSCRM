<?php

namespace App\Console\Commands;

use App\Services\InboxReplyService;
use Illuminate\Console\Command;

class ProcessScheduledInboxReplies extends Command
{
    protected $signature = 'inbox:process-scheduled-replies';

    protected $description = 'Send inbox replies whose scheduled send_at time has passed';

    public function handle(InboxReplyService $replies): int
    {
        $result = $replies->processDue(50);

        $this->info('Sent '.$result['sent'].' scheduled reply(ies)'
            .($result['failed'] ? ', '.$result['failed'].' failed' : '').'.');

        return self::SUCCESS;
    }
}
