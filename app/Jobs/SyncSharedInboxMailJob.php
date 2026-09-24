<?php

namespace App\Jobs;

use App\Models\SharedInbox;
use App\Services\OutlookMailService;
use App\Support\InboxQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncSharedInboxMailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(
        public int $inboxId,
        public bool $full = false,
    ) {
        $this->onQueue(InboxQueue::DEFAULT);
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        $key = 'inbox-sync:'.$this->inboxId.':'.($this->full ? 'full' : 'recent');

        return [
            (new WithoutOverlapping($key))
                ->releaseAfter(90)
                ->expireAfter(600),
        ];
    }

    public function handle(OutlookMailService $mailService): void
    {
        $inbox = SharedInbox::query()
            ->with('account')
            ->where('id', $this->inboxId)
            ->where('is_active', true)
            ->where('type', '!=', SharedInbox::TYPE_BROADCAST)
            ->whereNotNull('outlook_mail_account_id')
            ->whereHas('account', fn ($q) => $q->where('is_active', true))
            ->first();

        if (! $inbox) {
            return;
        }

        try {
            if ($this->full) {
                $mailService->syncInbox($inbox);
            } else {
                $mailService->syncRecent($inbox);
            }
        } catch (Throwable $e) {
            Log::warning('Queued inbox mail sync failed', [
                'inbox_id' => $this->inboxId,
                'full' => $this->full,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
