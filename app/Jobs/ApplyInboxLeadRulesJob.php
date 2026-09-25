<?php

namespace App\Jobs;

use App\Models\InboxConversation;
use App\Models\Lead;
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

    /**
     * @param  list<string>|null  $triggers  When null, uses inboundTriggers($isNew).
     */
    public function __construct(
        public int $conversationId,
        public bool $isNew,
        public string $bodyText = '',
        public ?string $dedupeKey = null,
        public ?array $triggers = null,
        public ?string $contactEmail = null,
        public ?string $subject = null,
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
            ->with(['inbox', 'lead'])
            ->find($this->conversationId);

        if (! $conversation) {
            return;
        }

        $body = trim($this->bodyText) !== ''
            ? $this->bodyText
            : (string) ($conversation->snippet ?? '');

        $contactEmail = $this->resolveContactEmail($conversation);
        $lead = $this->resolveLead($leadAutoCreate, $conversation, $contactEmail);
        $triggers = $this->triggers ?? LeadRuleEngine::inboundTriggers($this->isNew);
        $subject = trim((string) ($this->subject ?? '')) !== ''
            ? $this->subject
            : $conversation->subject;

        try {
            $leadAutoCreate->applyRules(
                $lead,
                'inbox',
                $triggers,
                [
                    'company_id' => (int) $conversation->company_id,
                    'contact_name' => $conversation->from_name,
                    'email' => $contactEmail,
                    'subject' => $subject,
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

    private function resolveContactEmail(InboxConversation $conversation): ?string
    {
        $override = trim((string) ($this->contactEmail ?? ''));
        if ($override !== '') {
            return $override;
        }

        $from = trim((string) ($conversation->from_email ?? ''));

        return $from !== '' ? $from : null;
    }

    private function resolveLead(
        LeadAutoCreateService $leadAutoCreate,
        InboxConversation $conversation,
        ?string $contactEmail
    ): ?Lead {
        if ($conversation->lead_id && $conversation->lead) {
            return $conversation->lead;
        }

        if (! $conversation->inbox) {
            return null;
        }

        if ($contactEmail) {
            return $leadAutoCreate->fromSharedInbox(
                $conversation->inbox,
                $conversation->from_name,
                $contactEmail
            );
        }

        return $leadAutoCreate->fromInboxConversation($conversation);
    }
}
