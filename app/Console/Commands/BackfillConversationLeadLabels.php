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

    protected $description = 'One-time fix: migrate lead labels stuck on a conversation onto the lead it belongs to, whether or not the conversation is formally attached (lead_id) yet';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('Dry run — no database changes will be made.');
        }

        $migrated = 0;
        $skipped = 0;

        $this->line('=== Pass 1: conversations already attached to a lead (lead_id set) ===');
        $attached = InboxConversation::query()
            ->whereNotNull('lead_id')
            ->whereHas('leadLabels')
            ->with('leadLabels')
            ->get();

        foreach ($attached as $conversation) {
            $lead = Lead::query()
                ->where('company_id', $conversation->company_id)
                ->find($conversation->lead_id);

            if (! $lead) {
                $this->warn("SKIP conversation #{$conversation->id} — lead #{$conversation->lead_id} not found.");
                $skipped++;

                continue;
            }

            $this->migrate($conversation, $lead, $dryRun);
            $migrated++;
        }

        $this->newLine();
        $this->line('=== Pass 2: conversations not formally attached, but matching a lead by email ===');
        $pairs = DB::table('inbox_conversations as c')
            ->join('lead_identities as li', function ($j) {
                $j->on('li.normalized_value', '=', DB::raw('LOWER(TRIM(c.from_email))'))
                    ->where('li.type', '=', 'email');
            })
            ->join('leads as l', function ($j) {
                $j->on('l.id', '=', 'li.lead_id')->on('l.company_id', '=', 'c.company_id');
            })
            ->whereNull('c.lead_id')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('inbox_conversation_lead_label as icll')
                    ->whereColumn('icll.inbox_conversation_id', 'c.id');
            })
            ->select('c.id as conversation_id', 'l.id as lead_id')
            ->distinct()
            ->get();

        foreach ($pairs as $pair) {
            $conversation = InboxConversation::with('leadLabels')->find($pair->conversation_id);
            $lead = Lead::find($pair->lead_id);
            if (! $conversation || ! $lead) {
                $skipped++;

                continue;
            }

            $this->migrate($conversation, $lead, $dryRun);
            $migrated++;
        }

        $this->newLine();
        $this->info(($dryRun ? 'Would migrate' : 'Migrated').' '.$migrated.' conversation(s). Skipped '.$skipped.'.');

        return self::SUCCESS;
    }

    private function migrate(InboxConversation $conversation, Lead $lead, bool $dryRun): void
    {
        $labelIds = $conversation->leadLabels->pluck('id')->map(fn ($id) => (int) $id)->all();
        $labelNames = $conversation->leadLabels->pluck('name')->implode(', ');

        $this->line("conversation #{$conversation->id} -> lead #{$lead->id} ({$lead->name}): {$labelNames}");

        if ($dryRun) {
            return;
        }

        DB::transaction(function () use ($lead, $conversation, $labelIds) {
            $lead->labels()->syncWithoutDetaching($labelIds);
            $conversation->leadLabels()->detach($labelIds);
        });
    }
}
