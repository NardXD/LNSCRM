<?php

namespace App\Console\Commands;

use App\Jobs\SyncSharedInboxMailJob;
use App\Models\SharedInbox;
use App\Services\InboxReopenService;
use App\Services\InboxReplyService;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;

class SyncInboxMail extends Command
{
    protected $signature = 'inbox:sync-mail
                            {--inbox= : Sync only this shared_inbox id}
                            {--full : Walk all folders instead of the recent Inbox/Sent probe}
                            {--sync : Run sync inline instead of queueing jobs}';

    protected $description = 'Queue (or run) background sync for connected personal and shared Outlook inboxes';

    /** Recent sync: shared / quotation / contract must be older than this. */
    private const RECENT_SHARED_SECONDS = 60;

    /** Recent sync: personal must be older than this (same freshness as shared). */
    private const RECENT_PERSONAL_SECONDS = 60;

    /** Full sync: shared-family must be older than this (unless backfill incomplete). */
    private const FULL_SHARED_SECONDS = 900;

    /** Full sync: personal must be older than this (unless backfill incomplete). */
    private const FULL_PERSONAL_SECONDS = 1800;

    public function handle(
        InboxReplyService $replies,
        InboxReopenService $reopens
    ): int {
        // Keep send-later + snooze reopen moving even if dedicated schedule entries are missed.
        $queuedSends = $replies->dispatchDue(50);
        $queuedReopens = $reopens->dispatchDue(200);
        if ($queuedSends > 0 || $queuedReopens > 0) {
            $this->line("Queued scheduled sends: {$queuedSends}, snooze reopens: {$queuedReopens}");
        }

        $query = SharedInbox::query()
            ->where('is_active', true)
            ->where('type', '!=', SharedInbox::TYPE_BROADCAST)
            ->whereNotNull('outlook_mail_account_id')
            ->whereHas('account', fn ($q) => $q->where('is_active', true))
            ->orderBy('id');

        if ($this->option('inbox')) {
            $query->where('id', (int) $this->option('inbox'));
        }

        $full = (bool) $this->option('full');
        $inline = (bool) $this->option('sync');
        $forceDue = $this->option('inbox') || $inline;
        $inboxes = $query->get(['id', 'name', 'type', 'last_synced_at', 'folder_sync_state']);

        if ($inboxes->isEmpty()) {
            $this->info('No connected personal or shared inboxes to sync.');

            return self::SUCCESS;
        }

        $due = $forceDue
            ? $inboxes
            : $inboxes->filter(fn (SharedInbox $inbox) => $this->isDue($inbox, $full))->values();

        if ($due->isEmpty()) {
            $this->info('No inboxes due for '.($full ? 'full' : 'recent').' sync.');

            return self::SUCCESS;
        }

        if ($inline) {
            return $this->runInline($due, $full);
        }

        foreach ($due as $inbox) {
            SyncSharedInboxMailJob::dispatch((int) $inbox->id, $full);
        }

        $skipped = $inboxes->count() - $due->count();
        $this->info('Queued sync for '.$due->count().' inbox(es)'
            .($full ? ' (full)' : ' (recent)')
            .($skipped > 0 ? ", skipped {$skipped} still fresh" : '').'.');

        return self::SUCCESS;
    }

    private function isDue(SharedInbox $inbox, bool $full): bool
    {
        if ($full && $this->hasIncompleteBackfill($inbox)) {
            return true;
        }

        $thresholdSeconds = $inbox->type === SharedInbox::TYPE_PERSONAL
            ? ($full ? self::FULL_PERSONAL_SECONDS : self::RECENT_PERSONAL_SECONDS)
            : ($full ? self::FULL_SHARED_SECONDS : self::RECENT_SHARED_SECONDS);

        $lastSynced = $inbox->last_synced_at;
        if (! $lastSynced instanceof CarbonInterface) {
            return true;
        }

        return $lastSynced->lte(now()->subSeconds($thresholdSeconds));
    }

    private function hasIncompleteBackfill(SharedInbox $inbox): bool
    {
        $state = $inbox->folder_sync_state;
        if (! is_array($state) || $state === []) {
            // Never backfilled — full sync should run.
            return true;
        }

        foreach (array_keys(\App\Services\OutlookMailService::FOLDERS) as $folder) {
            $folderState = $state[$folder] ?? null;
            if (! is_array($folderState) || ! ($folderState['backfill_done'] ?? false)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, SharedInbox>  $inboxes
     */
    private function runInline($inboxes, bool $full): int
    {
        @set_time_limit(600);

        $mailService = app(\App\Services\OutlookMailService::class);
        $totalImported = 0;
        $synced = 0;
        $failed = 0;

        foreach ($inboxes as $inbox) {
            try {
                $model = SharedInbox::with('account')->find($inbox->id);
                if (! $model) {
                    continue;
                }

                $imported = $full
                    ? $mailService->syncInbox($model)
                    : $mailService->syncRecent($model);

                $totalImported += $imported;
                $synced++;

                if ($imported > 0) {
                    $this->line("[{$model->type}] {$model->name}: +{$imported}");
                }
            } catch (\Throwable $e) {
                $failed++;
                \Illuminate\Support\Facades\Log::warning('Background inbox mail sync failed', [
                    'inbox_id' => $inbox->id,
                    'type' => $inbox->type,
                    'message' => $e->getMessage(),
                ]);
                $this->warn("[{$inbox->type}] {$inbox->name}: {$e->getMessage()}");
            }
        }

        $this->info("Synced {$synced} inbox(es), imported {$totalImported} message(s)"
            .($failed ? ", {$failed} failed" : '').'.');

        return $failed > 0 && $synced === 0 ? self::FAILURE : self::SUCCESS;
    }
}
