<?php

namespace App\Services;

use App\Models\InboxConversation;
use App\Models\InboxConversationActivity;
use App\Models\InboxConversationUserRead;
use App\Models\InboxMessage;
use App\Models\SharedInbox;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Front-style threading: every conversation with the same external address in
 * one inbox is merged into a single "home" thread (built on InboxThreadMergeService,
 * so Unmerge still splits them apart).
 */
class InboxContactThreadService
{
    /** Local parts of automated senders that should never be grouped. */
    /** Matched anywhere in the local part (googlemybusiness-noreply@, bizlinknotification@). */
    private const AUTOMATED_FRAGMENTS = [
        'noreply', 'no-reply', 'no_reply', 'donotreply', 'do-not-reply', 'do_not_reply',
        'notification', 'mailer-daemon', 'postmaster',
    ];

    /** Short words matched only as a whole word (otp@, sms.alerts@), so names like "scottp" don't trip them. */
    private const AUTOMATED_WORDS = [
        'otp', 'alert', 'alerts', 'notify', 'bounce', 'bounces', 'daemon',
    ];

    /** Public mail providers: sharing one with our mailbox doesn't make an address internal. */
    private const PUBLIC_DOMAINS = [
        'gmail.com', 'googlemail.com', 'outlook.com', 'hotmail.com', 'live.com', 'msn.com',
        'yahoo.com', 'ymail.com', 'icloud.com', 'me.com', 'mac.com', 'aol.com',
        'protonmail.com', 'proton.me', 'gmx.com', 'zoho.com',
    ];

    /** Folders whose rows can be grouped (trash, spam and drafts stay separate). */
    private const GROUPABLE_FOLDERS = ['inbox', 'sent'];

    /** Only mail this recent reopens an archived/snoozed thread (old backfill mail never does). */
    private const REOPEN_WINDOW_DAYS = 3;

    public function __construct(private InboxThreadMergeService $merger) {}

    public function enabled(): bool
    {
        return (bool) config('inbox.group_by_contact', true);
    }

    public static function normalize(?string $email): ?string
    {
        $email = strtolower(trim((string) $email));

        return $email !== '' ? $email : null;
    }

    public function isGroupable(SharedInbox $inbox, ?string $email): bool
    {
        $email = self::normalize($email);
        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        [$local, $domain] = explode('@', $email, 2);

        // Our own mailbox and colleagues on the same domain (internal forwards) stay separate.
        foreach ($this->mailboxAddresses($inbox) as $own) {
            if ($own === $email || (str_ends_with($own, '@'.$domain) && ! in_array($domain, self::PUBLIC_DOMAINS, true))) {
                return false;
            }
        }

        if ($this->isAutomatedLocalPart($local)) {
            return false;
        }

        foreach ((array) config('inbox.group_by_contact_exclude', []) as $excluded) {
            $excluded = self::normalize($excluded);
            if ($excluded && ($excluded === $email || $excluded === '@'.$domain)) {
                return false;
            }
        }

        return true;
    }

    private function isAutomatedLocalPart(string $local): bool
    {
        foreach (self::AUTOMATED_FRAGMENTS as $fragment) {
            if (str_contains($local, $fragment)) {
                return true;
            }
        }

        $words = preg_split('/[^a-z0-9]+/', $local, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_intersect($words, self::AUTOMATED_WORDS) !== [];
    }

    /**
     * The external party of a message: the sender for received mail, otherwise
     * the first recipient that isn't this mailbox.
     */
    public function contactEmailFor(SharedInbox $inbox, string $direction, ?string $fromEmail, ?string $toEmails): ?string
    {
        if ($direction === 'inbound') {
            return self::normalize($fromEmail);
        }

        $own = $this->mailboxAddresses($inbox);
        foreach (preg_split('/[,;]/', (string) $toEmails) ?: [] as $candidate) {
            $candidate = self::normalize($candidate);
            if ($candidate && ! in_array($candidate, $own, true)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Existing thread a freshly synced conversation should join, or null when it
     * should stay on its own row. A new received thread never joins a sent-only
     * thread here; groupConversation() pulls the sent rows into it afterwards.
     */
    public function homeForSyncedConversation(
        SharedInbox $inbox,
        ?string $contactEmail,
        string $folder,
        string $graphConversationId
    ): ?InboxConversation {
        if (! $this->enabled() || ! in_array($folder, self::GROUPABLE_FOLDERS, true)) {
            return null;
        }
        if (! $this->isGroupable($inbox, $contactEmail)) {
            return null;
        }

        // Sent mail that belongs to an inbox thread already lands there via messageHomeConversation().
        if ($folder === 'sent' && InboxConversation::query()
            ->where('shared_inbox_id', $inbox->id)
            ->where('folder', 'inbox')
            ->where('external_conversation_id', $graphConversationId)
            ->exists()) {
            return null;
        }

        if ($this->exceedsThreadLimit($inbox->id, (string) $contactEmail)) {
            return null;
        }

        $home = $this->eligible($inbox->id, (string) $contactEmail)->first();
        if (! $home || ($folder === 'inbox' && $home->folder !== 'inbox')) {
            return null;
        }

        return $home;
    }

    /**
     * Merge every other eligible thread with the same contact into one home
     * thread and return it. Returns $conversation unchanged when there is nothing to group.
     */
    public function groupConversation(InboxConversation $conversation): InboxConversation
    {
        $conversation = $conversation->mergeRoot();
        if (! $this->enabled() || $conversation->auto_group_disabled) {
            return $conversation;
        }

        $inbox = $conversation->inbox ?? SharedInbox::query()->find($conversation->shared_inbox_id);
        if (! $inbox || ! $this->isGroupable($inbox, $conversation->contact_email)) {
            return $conversation;
        }
        if (! in_array($conversation->folder, self::GROUPABLE_FOLDERS, true)
            || in_array($conversation->status, ['trashed', 'spam', 'drafts'], true)) {
            return $conversation;
        }

        $threads = $this->eligible($inbox->id, (string) $conversation->contact_email)->get();
        if ($threads->count() < 2 || $threads->count() > $this->maxThreads()) {
            return $conversation;
        }

        return $this->mergeThreads($threads);
    }

    /**
     * @param  Collection<int, InboxConversation>  $threads  ordered best home first
     */
    public function mergeThreads(Collection $threads): InboxConversation
    {
        $home = $threads->first();
        $others = $threads->slice(1);

        if (! $home->assigned_to) {
            $assigned = $others->first(fn (InboxConversation $c) => $c->assigned_to);
            if ($assigned) {
                $home->assigned_to = $assigned->assigned_to;
                $home->save();
            }
        }

        foreach ($others as $other) {
            try {
                $home = $this->merger->merge($home, $other);
            } catch (\Throwable $e) {
                Log::warning('Contact thread grouping skipped a conversation', [
                    'home_id' => $home->id,
                    'conversation_id' => $other->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->syncLatestSubject($home);

        return $home->fresh() ?? $home;
    }

    public function maxThreads(): int
    {
        return max(2, (int) config('inbox.group_by_contact_max_threads', 40));
    }

    /**
     * Bulk senders keep many separate threads; once past the limit they are never grouped.
     */
    public function exceedsThreadLimit(int $inboxId, string $contactEmail): bool
    {
        return $this->eligible($inboxId, $contactEmail)->reorder()->count() > $this->maxThreads();
    }

    /**
     * Front behaviour: a new message from the contact brings an archived or snoozed thread back to Open.
     */
    public function reopenForNewMessage(InboxConversation $home, Carbon $receivedAt, ?Carbon $previousLastMessageAt): void
    {
        if ($home->folder !== 'inbox' || $home->status !== 'archived') {
            return;
        }
        if ($receivedAt->lt(now()->subDays(self::REOPEN_WINDOW_DAYS))) {
            return;
        }
        if ($previousLastMessageAt && $receivedAt->lte($previousLastMessageAt)) {
            return;
        }

        $home->applyOpenFromHold();
        $home->is_read = false;
        $home->save();

        InboxConversationUserRead::query()
            ->where('inbox_conversation_id', $home->id)
            ->where('is_read', true)
            ->update(['is_read' => false]);

        InboxConversationActivity::create([
            'inbox_conversation_id' => $home->id,
            'user_id' => null,
            'action' => 'reopened',
            'summary' => 'Conversation reopened by a new email from '.$home->contact_email,
            'meta' => ['source' => 'contact_thread'],
        ]);
    }

    /**
     * Thread subject follows the newest message so replies answer the latest email.
     */
    private function syncLatestSubject(InboxConversation $home): void
    {
        $latest = InboxMessage::query()
            ->where('inbox_conversation_id', $home->id)
            ->where('is_draft', false)
            ->whereNotNull('subject')
            ->where('subject', '!=', '')
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->first();

        if ($latest && $latest->subject !== $home->subject) {
            $home->subject = $latest->subject;
            $home->save();
        }
    }

    /**
     * Groupable threads for a contact, best home first: open inbox thread, then
     * any inbox thread, then sent-only threads; newest activity wins ties.
     *
     * @return Builder<InboxConversation>
     */
    public function eligible(int $inboxId, string $contactEmail): Builder
    {
        return InboxConversation::query()
            ->notMerged()
            ->where('shared_inbox_id', $inboxId)
            ->where('contact_email', $contactEmail)
            ->where('auto_group_disabled', false)
            ->whereIn('folder', self::GROUPABLE_FOLDERS)
            ->whereNotIn('status', ['trashed', 'spam', 'drafts'])
            ->orderByRaw("CASE WHEN folder = 'inbox' AND status = 'open' THEN 0 WHEN folder = 'inbox' THEN 1 ELSE 2 END")
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');
    }

    /**
     * @return list<string>
     */
    private function mailboxAddresses(SharedInbox $inbox): array
    {
        return collect([$inbox->email, $inbox->external_mailbox, $inbox->account?->email])
            ->map(fn ($email) => self::normalize($email))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
