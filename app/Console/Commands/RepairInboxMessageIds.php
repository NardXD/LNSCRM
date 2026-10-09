<?php

namespace App\Console\Commands;

use App\Models\InboxMessage;
use App\Services\OutlookMailService;
use Illuminate\Console\Command;

class RepairInboxMessageIds extends Command
{
    protected $signature = 'inbox:repair-message-ids
        {--inbox= : Restrict to one shared inbox ID}
        {--limit= : Max messages to check (default: all messages with downloadable attachments)}
        {--dry-run : Report what would be repaired without saving anything}';

    protected $description = 'Find inbox messages whose stored Outlook message id no longer exists (so their attachments return "Attachment not found") and re-link them to the current Outlook message.';

    public function handle(OutlookMailService $mail): int
    {
        $inboxId = $this->option('inbox') ? (int) $this->option('inbox') : null;
        $limit = $this->option('limit') !== null ? max(0, (int) $this->option('limit')) : null;
        $dryRun = (bool) $this->option('dry-run');

        $query = InboxMessage::query()
            ->whereNotNull('external_message_id')
            ->where('external_message_id', 'not like', 'local-%')
            ->whereNotNull('attachments')
            ->whereHas('conversation', fn ($q) => $q
                ->when($inboxId, fn ($q) => $q->where('shared_inbox_id', $inboxId)))
            ->with('conversation.inbox.account')
            ->orderBy('id');

        $this->info(sprintf('Checking up to %s message(s)%s.', $limit ?? 'all', $dryRun ? ' (dry run)' : ''));

        $counts = [];
        $checked = 0;

        $query->chunkById(100, function ($messages) use ($mail, $dryRun, $limit, &$counts, &$checked) {
            foreach ($messages as $message) {
                if ($limit !== null && $checked >= $limit) {
                    return false;
                }
                $inbox = $message->conversation?->inbox;
                if (! $inbox) {
                    continue;
                }
                $checked++;

                try {
                    $result = $mail->repairStaleMessageId($inbox, $message, $dryRun);
                } catch (\Throwable $e) {
                    $result = 'error';
                    $this->warn("Message {$message->id}: {$e->getMessage()}");
                }

                $counts[$result] = ($counts[$result] ?? 0) + 1;
                if (in_array($result, ['relocated', 'would_relocate', 'not_found'], true)) {
                    $this->line("Message {$message->id} (conversation {$message->inbox_conversation_id}): {$result}");
                }

                usleep(150000); // stay well under Graph throttling
            }
        });

        $this->info("Done. Checked {$checked}: ".(json_encode($counts) ?: '{}'));

        return self::SUCCESS;
    }
}
