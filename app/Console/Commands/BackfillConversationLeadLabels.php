<?php

namespace App\Console\Commands;

use App\Models\InboxConversation;
use App\Models\Lead;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillConversationLeadLabels extends Command
{
    protected $signature = 'leads:backfill-conversation-labels
                            {--dry-run : Report what would change without writing anything}';

    protected $description = 'One-time fix: migrate lead labels stuck on a conversation onto the lead it is already attached to';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('Dry run — no database changes will be made.');
        }

        $conversations = InboxConversation::query()
            ->whereNotNull('lead_id')
            ->whereHas('leadLabels')
            ->with('leadLabels')
            ->get();

        $this->line('Found '.$conversations->count().' conversation(s) with labels stuck on the conversation instead of the lead.');
        $this->newLine();

        $migrated = 0;
        $skipped = 0;

        foreach ($conversations as $conversation) {
            $lead = Lead::query()
                ->where('company_id', $conversation->company_id)
                ->find($conversation->lead_id);

            if (! $lead) {
                $this->warn("SKIP conversation #{$conversation->id} — lead #{$conversation->lead_id} not found.");
                $skipped++;

                continue;
            }

            $labelIds = $conversation->leadLabels->pluck('id')->map(fn ($id) => (int) $id)->all();
            $labelNames = $conversation->leadLabels->pluck('name')->implode(', ');

            $this->line("conversation #{$conversation->id} -> lead #{$lead->id} ({$lead->name}): {$labelNames}");

            if (! $dryRun) {
                DB::transaction(function () use ($lead, $conversation, $labelIds) {
                    $lead->labels()->syncWithoutDetaching($labelIds);
                    $conversation->leadLabels()->detach($labelIds);
                });
            }

            $migrated++;
        }

        $this->newLine();
        $this->info(($dryRun ? 'Would migrate' : 'Migrated').' '.$migrated.' conversation(s). Skipped '.$skipped.'.');

        return self::SUCCESS;
    }
}
