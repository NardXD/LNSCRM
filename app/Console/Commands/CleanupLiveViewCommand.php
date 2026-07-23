<?php

namespace App\Console\Commands;

use App\Models\LiveViewSession;
use App\Models\WebrtcSignal;
use Illuminate\Console\Command;

class CleanupLiveViewCommand extends Command
{
    protected $signature = 'live-view:cleanup';

    protected $description = 'Expire stale live view sessions and purge old WebRTC signaling rows';

    public function handle(): int
    {
        $staleMinutes = (int) config('live-view.stale_session_minutes', 10);
        $retentionDays = (int) config('live-view.signal_retention_days', 7);

        $staleCutoff = now()->subMinutes($staleMinutes);
        $retentionCutoff = now()->subDays($retentionDays);

        $staleSessions = LiveViewSession::query()
            ->whereIn('status', [
                LiveViewSession::STATUS_PENDING,
                LiveViewSession::STATUS_CONNECTING,
            ])
            ->where('created_at', '<', $staleCutoff)
            ->update([
                'status' => LiveViewSession::STATUS_FAILED,
                'ended_at' => now(),
                'failure_reason' => 'Session timed out during setup.',
            ]);

        $expiredSignals = WebrtcSignal::query()
            ->where(function ($query) {
                $query->whereNotNull('expires_at')
                    ->where('expires_at', '<', now());
            })
            ->orWhere('created_at', '<', $retentionCutoff)
            ->delete();

        $this->info("Marked {$staleSessions} stale session(s) as failed.");
        $this->info("Deleted {$expiredSignals} old signaling row(s).");

        return self::SUCCESS;
    }
}
