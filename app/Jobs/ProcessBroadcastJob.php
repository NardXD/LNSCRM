<?php

namespace App\Jobs;

use App\Models\BroadcastCampaign;
use App\Models\BroadcastCampaignRecipient;
use App\Services\BroadcastMessagingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessBroadcastJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public int $broadcastId) {}

    public function handle(BroadcastMessagingService $broadcasts): void
    {
        $campaign = BroadcastCampaign::query()->find($this->broadcastId);
        if (! $campaign) {
            return;
        }

        $messageLimit = max(1, (int) config('broadcast.job_message_limit', 100));
        $maxSeconds = max(1, (int) config('broadcast.job_max_seconds', 90));
        $delaySeconds = max(0, (int) config('broadcast.batch_delay_seconds', 1));

        $started = microtime(true);
        $processed = 0;

        do {
            $batchCount = $broadcasts->processBatch($campaign, dispatchRemainder: false);
            $processed += $batchCount;
            $campaign->refresh();
            $hasPending = $campaign->recipients()
                ->where('status', BroadcastCampaignRecipient::STATUS_PENDING)
                ->exists();
        } while (
            $batchCount > 0
            && $hasPending
            && $processed < $messageLimit
            && (microtime(true) - $started) < $maxSeconds
        );

        if ($hasPending) {
            self::dispatch($campaign->id)->delay(now()->addSeconds($delaySeconds));
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Broadcast job failed', [
            'broadcast_id' => $this->broadcastId,
            'error' => $exception->getMessage(),
        ]);

        $campaign = BroadcastCampaign::query()->find($this->broadcastId);
        if (! $campaign) {
            return;
        }

        $campaign->recipients()
            ->whereIn('status', [
                BroadcastCampaignRecipient::STATUS_PENDING,
                BroadcastCampaignRecipient::STATUS_SENDING,
            ])
            ->update([
                'status' => BroadcastCampaignRecipient::STATUS_FAILED,
                'error_message' => mb_substr($exception->getMessage(), 0, 500),
            ]);

        $campaign->refreshCounts();
    }
}
