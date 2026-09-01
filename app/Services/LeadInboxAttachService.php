<?php

namespace App\Services;

use App\Models\InboxConversation;
use App\Models\Lead;
use App\Models\SharedInbox;
use App\Models\User;
use Illuminate\Support\Collection;

class LeadInboxAttachService
{
    public function __construct(
        protected LeadActivityService $leadActivity
    ) {}

    /**
     * Shared mailboxes this user can attach email from.
     *
     * @return Collection<int, int>
     */
    public function accessibleSharedInboxIds(User $user): Collection
    {
        return SharedInbox::query()
            ->where('company_id', $user->company_id)
            ->where('type', SharedInbox::TYPE_SHARED)
            ->where('is_active', true)
            ->whereHas('members', fn ($members) => $members->where('users.id', $user->id))
            ->pluck('id');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function search(User $user, string $query, ?int $exceptLeadId = null, int $limit = 20): array
    {
        $inboxIds = $this->accessibleSharedInboxIds($user);
        if ($inboxIds->isEmpty()) {
            return [];
        }

        $search = trim($query);
        $builder = InboxConversation::query()
            ->with(['inbox:id,name,email,type', 'leadLabels'])
            ->notMerged()
            ->where('company_id', $user->company_id)
            ->whereIn('shared_inbox_id', $inboxIds)
            ->orderByDesc('last_message_at');

        if ($exceptLeadId) {
            $builder->where(function ($q) use ($exceptLeadId) {
                $q->whereNull('lead_id')->orWhere('lead_id', '!=', $exceptLeadId);
            });
        }

        if ($search !== '') {
            $builder->where(function ($q) use ($search) {
                $q->where('subject', 'like', '%'.$search.'%')
                    ->orWhere('from_email', 'like', '%'.$search.'%')
                    ->orWhere('from_name', 'like', '%'.$search.'%')
                    ->orWhere('snippet', 'like', '%'.$search.'%');
            });
        }

        return $builder->limit($limit)->get()->map(fn (InboxConversation $c) => $this->serialize($c))->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function attached(Lead $lead): array
    {
        return InboxConversation::query()
            ->with(['inbox:id,name,email,type', 'leadLabels'])
            ->notMerged()
            ->where('lead_id', $lead->id)
            ->orderByDesc('last_message_at')
            ->get()
            ->map(fn (InboxConversation $c) => $this->serialize($c))
            ->all();
    }

    /**
     * @return array{conversation: array<string, mixed>, previous_lead_id: ?int}
     */
    public function attach(Lead $lead, InboxConversation $conversation, User $user): array
    {
        $conversation = $conversation->mergeRoot();
        $this->assertCanAttach($user, $conversation, $lead);

        $previousLeadId = $conversation->lead_id ? (int) $conversation->lead_id : null;
        if ($previousLeadId && $previousLeadId !== (int) $lead->id) {
            $previous = Lead::query()->where('company_id', $lead->company_id)->find($previousLeadId);
            if ($previous) {
                $this->recordDetach($previous, $conversation, $user);
            }
        }

        $conversation->lead_id = $lead->id;
        $conversation->save();
        $this->graduateConversationLabels($lead, $conversation);
        $this->leadActivity->recordInboxAttached($lead, $conversation);

        return [
            'conversation' => $this->serialize($conversation->fresh(['inbox:id,name,email,type']) ?? $conversation),
            'previous_lead_id' => $previousLeadId && $previousLeadId !== (int) $lead->id ? $previousLeadId : null,
        ];
    }

    /**
     * Labels applied while this conversation had no matching lead (e.g. Front
     * import) graduate onto the lead itself once the conversation is attached,
     * and no longer need to live directly on the conversation.
     */
    private function graduateConversationLabels(Lead $lead, InboxConversation $conversation): void
    {
        $conversationLabelIds = $conversation->leadLabels()->pluck('lead_labels.id')->map(fn ($id) => (int) $id)->all();
        if ($conversationLabelIds === []) {
            return;
        }

        $lead->labels()->syncWithoutDetaching($conversationLabelIds);
        $conversation->leadLabels()->detach($conversationLabelIds);
    }

    public function detach(Lead $lead, InboxConversation $conversation, User $user): void
    {
        $conversation = $conversation->mergeRoot();
        if ((int) $conversation->company_id !== (int) $lead->company_id) {
            abort(404);
        }
        if ((int) $conversation->lead_id !== (int) $lead->id) {
            abort(422, 'That email is not attached to this lead.');
        }

        $this->assertInboxAccess($user, $conversation);
        $conversation->lead_id = null;
        $conversation->save();
        $this->recordDetach($lead, $conversation, $user);
    }

    public function attachMany(Lead $lead, array $conversationIds, User $user): void
    {
        $ids = collect($conversationIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        foreach ($ids as $id) {
            $conversation = InboxConversation::query()->find($id);
            if (! $conversation) {
                continue;
            }
            try {
                $this->attach($lead, $conversation, $user);
            } catch (\Throwable) {
                continue;
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(InboxConversation $conversation): array
    {
        $conversation->loadMissing(['inbox:id,name,email,type', 'leadLabels']);

        return [
            'id' => $conversation->id,
            'subject' => $conversation->subject,
            'snippet' => $conversation->snippet,
            'from_name' => $conversation->from_name,
            'from_email' => $conversation->from_email,
            'folder' => $conversation->folder ?: 'inbox',
            'lead_id' => $conversation->lead_id ? (int) $conversation->lead_id : null,
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'deep_link' => url('/inbox').'?conversation='.$conversation->id,
            'inbox' => $conversation->inbox ? [
                'id' => $conversation->inbox->id,
                'name' => $conversation->inbox->name,
                'email' => $conversation->inbox->email,
            ] : null,
            // Front.com labels still attached directly to this conversation
            // (not yet graduated onto the lead's own labels).
            'lead_labels' => $conversation->leadLabels->map(fn ($label) => [
                'id' => $label->id,
                'name' => $label->name,
                'color' => $label->color,
            ])->all(),
        ];
    }

    public function authorizeConversation(User $user, InboxConversation $conversation): void
    {
        $this->assertInboxAccess($user, $conversation);
    }

    public function conversationForLead(Lead $lead, int $conversationId): InboxConversation
    {
        $conversation = InboxConversation::query()
            ->where('company_id', $lead->company_id)
            ->find($conversationId);

        if (! $conversation) {
            abort(404);
        }

        return $conversation->mergeRoot();
    }

    private function assertCanAttach(User $user, InboxConversation $conversation, Lead $lead): void
    {
        if ((int) $conversation->company_id !== (int) $lead->company_id) {
            abort(404);
        }
        $this->assertInboxAccess($user, $conversation);
        $inbox = $conversation->inbox;
        if (! $inbox || $inbox->type !== SharedInbox::TYPE_SHARED) {
            abort(422, 'Only shared mailbox emails can be attached to a lead.');
        }
    }

    private function assertInboxAccess(User $user, InboxConversation $conversation): void
    {
        $inbox = $conversation->inbox ?? $conversation->loadMissing('inbox')->inbox;
        if (! $inbox || ! $inbox->userCanAccess($user)) {
            abort(403, 'You do not have access to that mailbox.');
        }
    }

    private function recordDetach(Lead $lead, InboxConversation $conversation, User $user): void
    {
        $this->leadActivity->recordInboxDetached($lead, $conversation, $user->id);
    }
}
