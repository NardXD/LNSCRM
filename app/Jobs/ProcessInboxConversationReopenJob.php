<?php

namespace App\Jobs;

use App\Models\InboxConversation;
use App\Services\InboxReopenService;
use App\Support\InboxQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessInboxConversationReopenJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 60;

    public int $uniqueFor = 300;

    public function __construct(public int $conversationId)
    {
        $this->onQueue(InboxQueue::MAIL);
    }

    public function uniqueId(): string
    {
        return 'inbox-reopen:'.$this->conversationId;
    }

    public function handle(InboxReopenService $reopens): void
    {
        $conversation = InboxConversation::query()->find($this->conversationId);
        if (! $conversation || $conversation->merged_into_id) {
            return;
        }

        if (! $conversation->reopen_at || $conversation->reopen_at->isFuture()) {
            return;
        }

        try {
            $reopens->reopenConversation($conversation);
        } catch (Throwable $e) {
            Log::warning('Queued inbox snooze reopen failed', [
                'conversation_id' => $this->conversationId,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
