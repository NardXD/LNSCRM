<?php

namespace App\Console\Commands;

use App\Models\SharedInbox;
use App\Services\InboxReopenService;
use App\Services\InboxReplyService;
use App\Services\OutlookMailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncInboxMail extends Command
{
    protected $signature = 'inbox:sync-mail
                            {--inbox= : Sync only this shared_inbox id}
                            {--full : Walk all folders instead of the recent Inbox/Sent probe}';

    protected $description = 'Background-sync connected personal and shared Outlook inboxes (no /inbox page required)';

    public function handle(
        OutlookMailService $mailService,
        InboxReplyService $replies,
        InboxReopenService $reopens
    ): int {
        @set_time_limit(600);

        // Send-later + snooze reopen even if dedicated schedule entries are missed.
        try {
            $scheduled = $replies->processDue(50);
            if ($scheduled['sent'] > 0 || $scheduled['failed'] > 0) {
                $this->line('Scheduled sends: '.$scheduled['sent'].' sent'
                    .($scheduled['failed'] ? ', '.$scheduled['failed'].' failed' : ''));
            }
        } catch (Throwable $e) {
            Log::warning('Scheduled inbox send processing failed during sync', [
                'message' => $e->getMessage(),
            ]);
            $this->warn('Scheduled send processing failed: '.$e->getMessage());
        }

        try {
            $reopened = $reopens->processDue(200);
            if ($reopened > 0) {
                $this->line("Snooze reopens: {$reopened}");
            }
        } catch (Throwable $e) {
            Log::warning('Inbox snooze reopen processing failed during sync', [
                'message' => $e->getMessage(),
            ]);
            $this->warn('Snooze reopen processing failed: '.$e->getMessage());
        }

        $query = SharedInbox::query()
            ->with('account')
            ->where('is_active', true)
            ->whereNotNull('outlook_mail_account_id')
            ->whereHas('account', fn ($q) => $q->where('is_active', true))
            ->orderBy('id');

        if ($this->option('inbox')) {
            $query->where('id', (int) $this->option('inbox'));
        }

        $full = (bool) $this->option('full');
        $inboxes = $query->get();

        if ($inboxes->isEmpty()) {
            $this->info('No connected personal or shared inboxes to sync.');

            return self::SUCCESS;
        }

        $totalImported = 0;
        $synced = 0;
        $failed = 0;

        foreach ($inboxes as $inbox) {
            try {
                $imported = $full
                    ? $mailService->syncInbox($inbox->fresh(['account']))
                    : $mailService->syncRecent($inbox->fresh(['account']));

                $totalImported += $imported;
                $synced++;

                if ($imported > 0) {
                    $this->line("[{$inbox->type}] {$inbox->name}: +{$imported}");
                }
            } catch (Throwable $e) {
                $failed++;
                Log::warning('Background inbox mail sync failed', [
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
