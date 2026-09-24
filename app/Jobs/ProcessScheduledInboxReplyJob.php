<?php

namespace App\Jobs;

use App\Models\ScheduledInboxReply;
use App\Services\InboxReplyService;
use App\Support\InboxQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessScheduledInboxReplyJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public int $uniqueFor = 600;

    public function __construct(public int $scheduledReplyId)
    {
        $this->onQueue(InboxQueue::MAIL);
    }

    public function uniqueId(): string
    {
        return 'scheduled-inbox-reply:'.$this->scheduledReplyId;
    }

    public function handle(InboxReplyService $replies): void
    {
        $scheduled = ScheduledInboxReply::query()->find($this->scheduledReplyId);
        if (! $scheduled || $scheduled->status !== ScheduledInboxReply::STATUS_PENDING) {
            return;
        }

        if ($scheduled->send_at && $scheduled->send_at->isFuture()) {
            return;
        }

        try {
            $replies->dispatchScheduled($scheduled->fresh(['conversation', 'user', 'inbox.account']) ?? $scheduled);
        } catch (Throwable $e) {
            Log::warning('Queued scheduled inbox send failed', [
                'scheduled_id' => $this->scheduledReplyId,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
