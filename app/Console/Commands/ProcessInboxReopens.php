<?php

namespace App\Console\Commands;

use App\Services\InboxReopenService;
use Illuminate\Console\Command;

class ProcessInboxReopens extends Command
{
    protected $signature = 'inbox:process-reopens';

    protected $description = 'Reopen inbox conversations whose scheduled reopen_at time has passed';

    public function handle(InboxReopenService $reopens): int
    {
        $count = $reopens->processDue(500);
        $this->info("Reopened {$count} conversation(s).");

        return self::SUCCESS;
    }
}
