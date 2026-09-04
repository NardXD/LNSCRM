<?php

namespace App\Console\Commands;

use App\Services\LeadChannelMessageService;
use Illuminate\Console\Command;

class ProcessLeadScheduledEmails extends Command
{
    protected $signature = 'leads:process-scheduled-emails';

    protected $description = 'Send lead rule emails whose scheduled send_at time has passed';

    public function handle(LeadChannelMessageService $channelMessages): int
    {
        $result = $channelMessages->processDueScheduledEmails(50);

        $this->info('Sent '.$result['sent'].' scheduled email(s)'
            .($result['failed'] ? ', '.$result['failed'].' failed' : '').'.');

        return self::SUCCESS;
    }
}
