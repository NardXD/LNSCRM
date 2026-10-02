<?php

namespace App\Console\Commands;

use App\Models\InboxConversation;
use App\Models\InboxMessage;
use App\Models\SharedInbox;
use App\Services\InboxContactThreadService;
use Illuminate\Console\Command;

class GroupInboxThreadsByContact extends Command
{
    protected $signature = 'inbox:group-by-contact
        {--inbox= : Restrict to one shared inbox ID}
        {--dry-run : Show what would be grouped without changing anything}';

    protected $description = 'Front-style backfill: merge every existing thread with the same email address (per inbox) into one conversation.';

    public function handle(InboxContactThreadService $contactThreads): int
    {
        if (! $contactThreads->enabled()) {
            $this->warn('Contact grouping is disabled (INBOX_GROUP_BY_CONTACT=false).');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $inboxes = SharedInbox::query()
            ->with('account')
            ->when($this->option('inbox'), fn ($q, $id) => $q->whereKey((int) $id))
            ->orderBy('id')
            ->get();

        if ($inboxes->isEmpty()) {
            $this->error('No matching inbox.');

            return self::FAILURE;
        }

        $totalContacts = 0;
        $totalMerged = 0;

        foreach ($inboxes as $inbox) {
            $filled = $this->fillSentContacts($inbox, $contactThreads, $dryRun);

            $groups = InboxConversation::query()
                ->notMerged()
                ->where('shared_inbox_id', $inbox->id)
                ->whereNotNull('contact_email')
                ->where('auto_group_disabled', false)
                ->whereIn('folder', ['inbox', 'sent'])
                ->whereNotIn('status', ['trashed', 'spam', 'drafts'])
                ->groupBy('contact_email')
                ->havingRaw('COUNT(*) > 1')
                ->selectRaw('contact_email, COUNT(*) as threads')
                ->orderByDesc('threads')
                ->get();

            $max = $contactThreads->maxThreads();
            $groupable = $groups->filter(fn ($g) => $contactThreads->isGroupable($inbox, $g->contact_email));
            $grouped = $groupable->filter(fn ($g) => (int) $g->threads <= $max)->values();
            $bulk = $groupable->filter(fn ($g) => (int) $g->threads > $max)->values();
            $excluded = $groups->reject(fn ($g) => $contactThreads->isGroupable($inbox, $g->contact_email))->values();

            $merges = (int) $grouped->sum(fn ($g) => $g->threads - 1);
            $this->line('');
            $this->info("Inbox #{$inbox->id} {$inbox->name}: {$grouped->count()} contacts, {$merges} threads to merge"
                .($filled ? ", {$filled} sent threads given a contact" : ''));

            if ($grouped->isNotEmpty()) {
                $this->table(['Contact', 'Threads'], $grouped->take(15)->map(fn ($g) => [$g->contact_email, $g->threads])->all());
            }
            if ($bulk->isNotEmpty()) {
                $this->line("Kept separate (more than {$max} threads, {$bulk->count()} addresses): "
                    .$bulk->take(10)->map(fn ($g) => "{$g->contact_email} ({$g->threads})")->implode(', '));
            }
            if ($excluded->isNotEmpty()) {
                $this->line('Kept separate (internal, automated or excluded): '
                    .$excluded->take(10)->map(fn ($g) => "{$g->contact_email} ({$g->threads})")->implode(', '));
            }

            $totalContacts += $grouped->count();
            $totalMerged += $merges;

            if ($dryRun) {
                continue;
            }

            $bar = $this->output->createProgressBar($grouped->count());
            foreach ($grouped as $group) {
                $threads = $contactThreads->eligible($inbox->id, $group->contact_email)->get();
                if ($threads->count() > 1) {
                    $contactThreads->mergeThreads($threads);
                }
                $bar->advance();
            }
            $bar->finish();
            $this->line('');
        }

        $this->line('');
        $this->info(($dryRun ? '[dry run] Would merge ' : 'Merged ')."{$totalMerged} threads across {$totalContacts} contacts.");
        if ($dryRun) {
            $this->line('If a web-form or notification sender is listed above, add it to INBOX_GROUP_BY_CONTACT_EXCLUDE before running for real.');
        }

        return self::SUCCESS;
    }

    /**
     * Sent-only threads have no sender to key on; use their first external recipient.
     */
    private function fillSentContacts(SharedInbox $inbox, InboxContactThreadService $contactThreads, bool $dryRun): int
    {
        $filled = 0;

        InboxConversation::query()
            ->notMerged()
            ->where('shared_inbox_id', $inbox->id)
            ->where('folder', 'sent')
            ->whereNull('contact_email')
            ->select(['id'])
            ->chunkById(500, function ($rows) use ($inbox, $contactThreads, $dryRun, &$filled) {
                foreach ($rows as $row) {
                    $to = InboxMessage::query()
                        ->where('inbox_conversation_id', $row->id)
                        ->where('direction', 'outbound')
                        ->whereNotNull('to_emails')
                        ->orderBy('sent_at')
                        ->value('to_emails');
                    $contact = $contactThreads->contactEmailFor($inbox, 'outbound', null, $to);
                    if (! $contact) {
                        continue;
                    }
                    $filled++;
                    if (! $dryRun) {
                        InboxConversation::query()->whereKey($row->id)->update(['contact_email' => $contact]);
                    }
                }
            });

        return $filled;
    }
}
