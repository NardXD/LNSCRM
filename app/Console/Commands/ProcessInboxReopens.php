<?php

namespace App\Console\Commands;

use App\Services\InboxReopenService;
use Illuminate\Console\Command;

class ProcessInboxReopens extends Command
{
    protected $signature = 'inbox:process-reopens {--sync : Reopen inline instead of queueing}';

    protected $description = 'Queue (or run) reopen for inbox conversations whose reopen_at has passed';

    public function handle(InboxReopenService $reopens): int
    {
        if ($this->option('sync')) {
            $count = $reopens->processDue(500);
            $this->info("Reopened {$count} conversation(s).");

            return self::SUCCESS;
        }

        $queued = $reopens->dispatchDue(500);
        $this->info("Queued {$queued} snooze reopen job(s).");

        return self::SUCCESS;
    }
}
