<?php

namespace App\Jobs;

use App\Models\InboxConversation;
use App\Services\LeadAutoCreateService;
use App\Services\LeadRuleEngine;
use App\Support\InboxQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ApplyInboxLeadRulesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public int $uniqueFor = 120;

    public function __construct(
        public int $conversationId,
        public bool $isNew,
        public string $bodyText = '',
        public ?string $dedupeKey = null,
    ) {
        $this->onQueue(InboxQueue::DEFAULT);
    }

    public function uniqueId(): string
    {
        return $this->dedupeKey
            ?: 'inbox-lead-rules:'.$this->conversationId.':'.($this->isNew ? 'new' : 'reply');
    }

    public function handle(LeadAutoCreateService $leadAutoCreate): void
    {
        $conversation = InboxConversation::query()
            ->with('inbox')
            ->find($this->conversationId);

        if (! $conversation) {
            return;
        }

        $body = trim($this->bodyText) !== ''
            ? $this->bodyText
            : (string) ($conversation->snippet ?? '');

        try {
            $leadAutoCreate->applyRules(
                $leadAutoCreate->fromInboxConversation($conversation),
                'inbox',
                LeadRuleEngine::inboundTriggers($this->isNew),
                [
                    'company_id' => (int) $conversation->company_id,
                    'contact_name' => $conversation->from_name,
                    'email' => $conversation->from_email,
                    'subject' => $conversation->subject,
                    'message' => $body,
                    'inbox_id' => $conversation->shared_inbox_id,
                    'shared_inbox_id' => $conversation->shared_inbox_id,
                    'inbox_conversation_id' => $conversation->id,
                ]
            );
        } catch (Throwable $e) {
            Log::warning('Queued inbox lead rules failed', [
                'conversation_id' => $this->conversationId,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
