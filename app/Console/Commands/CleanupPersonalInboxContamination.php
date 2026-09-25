<?php

namespace App\Console\Commands;

use App\Models\InboxConversation;
use App\Models\SharedInbox;
use App\Services\OutlookMailService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class CleanupPersonalInboxContamination extends Command
{
    protected $signature = 'inbox:cleanup-personal-contamination
                            {--apply : Delete imported conversations and clear sync cursors (default is dry-run)}
                            {--disconnect : Also unlink the Outlook account and clear external_mailbox}
                            {--inbox= : Only this personal shared_inbox id}
                            {--user= : Only personal inboxes owned by this user id}
                            {--include-user-email-mismatch : Also treat Personal email ≠ CRM login email as contaminated}
                            {--force : Skip confirmation when applying}';

    protected $description = 'Find (and optionally wipe) personal inboxes that look bound to another mailbox';

    public function handle(OutlookMailService $mail): int
    {
        $suspects = $this->findSuspects();

        if ($suspects->isEmpty()) {
            $this->info('No contaminated personal inboxes found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Inbox ID', 'Owner', 'CRM login', 'Inbox email', 'Account email', 'Threads', 'Reasons'],
            $suspects->map(fn (array $row) => [
                $row['inbox_id'],
                $row['owner_name'],
                $row['owner_email'],
                $row['inbox_email'],
                $row['account_email'],
                $row['thread_count'],
                implode('; ', $row['reasons']),
            ])->all()
        );

        $this->warn($suspects->count().' personal inbox(es) look contaminated.');

        if (! $this->option('apply')) {
            $this->line('Dry-run only. Re-run with --apply to wipe imported mail + sync cursors.');
            $this->line('Add --disconnect to also unlink the Outlook account.');
            if (! $this->option('include-user-email-mismatch')) {
                $this->line('Tip: --include-user-email-mismatch also flags Personal email ≠ CRM login.');
            }

            return self::SUCCESS;
        }

        $threadTotal = (int) $suspects->sum('thread_count');
        if (! $this->option('force')
            && ! $this->confirm("Permanently delete {$threadTotal} conversation(s) across {$suspects->count()} personal inbox(es)?")) {
            $this->info('Aborted.');

            return self::FAILURE;
        }

        $cleared = 0;
        foreach ($suspects as $row) {
            $inbox = SharedInbox::query()->with('account')->find($row['inbox_id']);
            if (! $inbox || $inbox->type !== SharedInbox::TYPE_PERSONAL) {
                continue;
            }

            $mail->resetInboxMailState($inbox);

            $inbox->external_mailbox = null;
            if ($this->option('disconnect')) {
                $inbox->outlook_mail_account_id = null;
            }
            $inbox->save();

            $cleared++;
            $this->line("Cleared personal inbox #{$inbox->id} ({$row['owner_email']})"
                .($this->option('disconnect') ? ' — disconnected' : ''));
        }

        $this->info("Done. Cleaned {$cleared} personal inbox(es). Users should reconnect Outlook Personal with their own account.");

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, array{
     *     inbox_id: int,
     *     owner_name: string,
     *     owner_email: string,
     *     inbox_email: string,
     *     account_email: string,
     *     thread_count: int,
     *     reasons: list<string>
     * }>
     */
    private function findSuspects(): Collection
    {
        $query = SharedInbox::query()
            ->with(['account:id,user_id,email', 'creator:id,name,email'])
            ->where('type', SharedInbox::TYPE_PERSONAL)
            ->where('is_active', true)
            ->orderBy('id');

        if ($this->option('inbox')) {
            $query->where('id', (int) $this->option('inbox'));
        }
        if ($this->option('user')) {
            $query->where('created_by', (int) $this->option('user'));
        }

        $includeUserEmailMismatch = (bool) $this->option('include-user-email-mismatch');

        return $query->get()->map(function (SharedInbox $inbox) use ($includeUserEmailMismatch) {
            $reasons = $this->contaminationReasons($inbox, $includeUserEmailMismatch);
            if ($reasons === []) {
                return null;
            }

            $owner = $inbox->creator;
            $threadCount = InboxConversation::query()
                ->where('shared_inbox_id', $inbox->id)
                ->count();

            return [
                'inbox_id' => (int) $inbox->id,
                'owner_name' => (string) ($owner?->name ?? '—'),
                'owner_email' => (string) ($owner?->email ?? '—'),
                'inbox_email' => (string) ($inbox->email ?: '—'),
                'account_email' => (string) ($inbox->account?->email ?: '—'),
                'thread_count' => $threadCount,
                'reasons' => $reasons,
            ];
        })->filter()->values();
    }

    /**
     * @return list<string>
     */
    private function contaminationReasons(SharedInbox $inbox, bool $includeUserEmailMismatch): array
    {
        $reasons = [];
        $owner = $inbox->creator;
        $account = $inbox->account;
        $inboxEmail = strtolower(trim((string) ($inbox->email ?? '')));
        $accountEmail = strtolower(trim((string) ($account?->email ?? '')));
        $ownerEmail = strtolower(trim((string) ($owner?->email ?? '')));
        $external = trim((string) ($inbox->external_mailbox ?? ''));

        if ($external !== '') {
            $reasons[] = 'external_mailbox set ('.$external.')';
        }

        if ($account && $accountEmail !== '' && $inboxEmail !== '' && strcasecmp($accountEmail, $inboxEmail) !== 0) {
            $reasons[] = 'account email ≠ inbox email';
        }

        if ($account && $owner && (int) $account->user_id !== (int) $inbox->created_by) {
            $reasons[] = 'account owned by another CRM user';
        }

        $syncState = $inbox->folder_sync_state;
        if (is_array($syncState)) {
            foreach ($syncState as $folderState) {
                $next = is_array($folderState) ? ($folderState['next_link'] ?? null) : null;
                if (is_string($next) && str_contains(strtolower($next), '/users/')) {
                    $reasons[] = 'stale Graph nextLink for /users/...';
                    break;
                }
            }
        }

        if ($includeUserEmailMismatch
            && $ownerEmail !== ''
            && $inboxEmail !== ''
            && strcasecmp($ownerEmail, $inboxEmail) !== 0) {
            $reasons[] = 'Personal email ≠ CRM login';
        }

        // Shared/team-looking address bound as Personal while CRM login differs.
        if ($ownerEmail !== ''
            && $inboxEmail !== ''
            && strcasecmp($ownerEmail, $inboxEmail) !== 0
            && $this->looksLikeSharedAddress($inboxEmail)) {
            $reasons[] = 'Personal bound to shared/team-looking address';
        }

        return array_values(array_unique($reasons));
    }

    private function looksLikeSharedAddress(string $email): bool
    {
        $local = strtolower(strtok($email, '@') ?: '');
        if ($local === '') {
            return false;
        }

        foreach (['sales', 'info', 'support', 'hello', 'admin', 'office', 'contact', 'enquiries', 'inquiry', 'team', 'mail', 'noreply', 'no-reply'] as $needle) {
            if ($local === $needle || str_starts_with($local, $needle.'.') || str_starts_with($local, $needle.'-')) {
                return true;
            }
        }

        return false;
    }
}
