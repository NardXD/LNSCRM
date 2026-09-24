<?php

namespace App\Console\Commands;

use App\Jobs\SyncSharedInboxMailJob;
use App\Models\SharedInbox;
use App\Services\InboxReopenService;
use App\Services\InboxReplyService;
use Illuminate\Console\Command;

class SyncInboxMail extends Command
{
    protected $signature = 'inbox:sync-mail
                            {--inbox= : Sync only this shared_inbox id}
                            {--full : Walk all folders instead of the recent Inbox/Sent probe}
                            {--sync : Run sync inline instead of queueing jobs}';

    protected $description = 'Queue (or run) background sync for connected personal and shared Outlook inboxes';

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
        $inboxes = $query->get(['id', 'name', 'type']);

        if ($inboxes->isEmpty()) {
            $this->info('No connected personal or shared inboxes to sync.');

            return self::SUCCESS;
        }

        if ($inline) {
            return $this->runInline($inboxes, $full);
        }

        foreach ($inboxes as $inbox) {
            SyncSharedInboxMailJob::dispatch((int) $inbox->id, $full);
        }

        $this->info('Queued sync for '.$inboxes->count().' inbox(es)'
            .($full ? ' (full)' : ' (recent)').'.');

        return self::SUCCESS;
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
