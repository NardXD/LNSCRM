<?php

namespace App\Services;

use App\Models\InboxConversation;
use App\Models\Lead;
use App\Models\LeadIdentity;
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
            ->with('inbox:id,name,email,type')
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
            ->with('inbox:id,name,email,type')
            ->notMerged()
            ->where('lead_id', $lead->id)
            ->orderByDesc('last_message_at')
            ->get()
            ->map(fn (InboxConversation $c) => $this->serialize($c))
            ->all();
    }

    /**
     * @return array{conversation: array<string, mixed>, email_added: ?string, previous_lead_id: ?int}
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

        $emailAdded = $this->addContactEmailIfAvailable($lead, $conversation);
        $this->leadActivity->recordInboxAttached($lead, $conversation);

        return [
            'conversation' => $this->serialize($conversation->fresh(['inbox:id,name,email,type']) ?? $conversation),
            'email_added' => $emailAdded,
            'previous_lead_id' => $previousLeadId && $previousLeadId !== (int) $lead->id ? $previousLeadId : null,
        ];
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
        $conversation->loadMissing('inbox:id,name,email,type');

        return [
            'id' => $conversation->id,
            'subject' => $conversation->subject,
            'snippet' => $conversation->snippet,
            'from_name' => $conversation->from_name,
            'from_email' => $conversation->from_email,
            'contact_email' => $this->contactEmail($conversation),
            'folder' => $conversation->folder ?: 'inbox',
            'lead_id' => $conversation->lead_id ? (int) $conversation->lead_id : null,
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'deep_link' => url('/inbox').'?conversation='.$conversation->id,
            'inbox' => $conversation->inbox ? [
                'id' => $conversation->inbox->id,
                'name' => $conversation->inbox->name,
                'email' => $conversation->inbox->email,
            ] : null,
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

    private function addContactEmailIfAvailable(Lead $lead, InboxConversation $conversation): ?string
    {
        $email = $this->contactEmail($conversation);
        if (! $email) {
            return null;
        }

        $mailboxEmails = collect([
            $conversation->inbox?->email,
            $conversation->inbox?->external_mailbox,
        ])->filter()->map(fn ($value) => strtolower(trim((string) $value)))->all();
        if (in_array($email, $mailboxEmails, true)) {
            return null;
        }

        $normalized = LeadIdentity::normalize(LeadIdentity::TYPE_EMAIL, $email);
        if ($normalized === '') {
            return null;
        }

        $ownedElsewhere = LeadIdentity::query()
            ->where('type', LeadIdentity::TYPE_EMAIL)
            ->where('normalized_value', $normalized)
            ->where('lead_id', '!=', $lead->id)
            ->whereHas('lead', fn ($q) => $q->where('company_id', $lead->company_id))
            ->exists();
        if ($ownedElsewhere) {
            return null;
        }

        $existing = $lead->identities()
            ->where('type', LeadIdentity::TYPE_EMAIL)
            ->where('normalized_value', $normalized)
            ->first();
        if ($existing) {
            return null;
        }

        $lead->addIdentity(LeadIdentity::TYPE_EMAIL, $email, 'Inbox');

        return $email;
    }

    private function contactEmail(InboxConversation $conversation): ?string
    {
        $candidates = [$conversation->from_email];
        if (($conversation->folder ?: 'inbox') === 'sent') {
            $conversation->loadMissing('messages');
            foreach ($conversation->messages as $message) {
                $candidates[] = $message->to_emails;
            }
        }

        $mailboxEmails = collect([
            $conversation->inbox?->email,
            $conversation->inbox?->external_mailbox,
        ])->filter()->map(fn ($value) => strtolower(trim((string) $value)))->all();

        foreach ($candidates as $raw) {
            foreach ($this->extractEmails((string) $raw) as $email) {
                if (! in_array($email, $mailboxEmails, true)) {
                    return $email;
                }
            }
        }

        return $this->extractEmails((string) $conversation->from_email)[0] ?? null;
    }

    /**
     * @return list<string>
     */
    private function extractEmails(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value, $matches);

        return collect($matches[0] ?? [])
            ->map(fn ($email) => strtolower(trim($email)))
            ->unique()
            ->values()
            ->all();
    }
}
