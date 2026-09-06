<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ClearLogs extends Command
{
    protected $signature = 'logs:clear';

    protected $description = 'Empty laravel.log and lead-rules.log, including any dated/rotated variants, so they don\'t grow unbounded';

    public function handle(): int
    {
        $patterns = ['laravel*.log', 'lead-rules*.log'];
        $cleared = 0;

        foreach ($patterns as $pattern) {
            foreach (File::glob(storage_path('logs/'.$pattern)) as $path) {
                File::put($path, '');
                $this->info("Cleared {$path}");
                $cleared++;
            }
        }

        if ($cleared === 0) {
            $this->comment('No matching log files found.');
        }

        return self::SUCCESS;
    }
}
