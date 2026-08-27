<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadStatus;
use App\Services\LeadActivityService;
use App\Services\LeadRuleEngine;
use Illuminate\Console\Command;

class ProcessLeadScheduledStatuses extends Command
{
    protected $signature = 'leads:process-scheduled-statuses';

    protected $description = 'Apply lead statuses scheduled by rules after a delay from the trigger';

    public function handle(LeadActivityService $leadActivity): int
    {
        $count = 0;

        Lead::query()
            ->whereNotNull('scheduled_status_at')
            ->where('scheduled_status_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($leads) use (&$count, $leadActivity) {
                foreach ($leads as $lead) {
                    $from = strtolower(trim((string) $lead->scheduled_status_from));
                    $target = strtolower(trim((string) $lead->scheduled_status));
                    $current = strtolower(trim((string) $lead->status));
                    $slugs = LeadStatus::slugsForCompany((int) $lead->company_id);

                    if ($from === '' || $current !== $from || $target === '' || $target === Lead::STATUS_SNOOZED || ! in_array($target, $slugs, true)) {
                        $lead->clearScheduledStatusChange();

                        continue;
                    }

                    if ($current === $target) {
                        $lead->clearScheduledStatusChange();

                        continue;
                    }

                    $claimed = Lead::query()
                        ->whereKey($lead->id)
                        ->whereNotNull('scheduled_status_at')
                        ->where('scheduled_status_at', '<=', now())
                        ->where('status', $from)
                        ->update([
                            'status' => $target,
                            'scheduled_status_at' => null,
                            'scheduled_status' => null,
                            'scheduled_status_from' => null,
                        ]);

                    if ($claimed < 1) {
                        continue;
                    }

                    $lead->status = $target;
                    $lead->scheduled_status_at = null;
                    $lead->scheduled_status = null;
                    $lead->scheduled_status_from = null;

                    $fromLabel = LeadStatus::nameFor((int) $lead->company_id, $from);
                    $toLabel = LeadStatus::nameFor((int) $lead->company_id, $target);
                    $leadActivity->record(
                        $lead,
                        LeadActivity::STATUS_CHANGED,
                        'System changed status from '.$fromLabel.' to '.$toLabel.' after the rule delay',
                        [
                            'from' => $from,
                            'to' => $target,
                            'source' => 'set_status_after_days',
                        ]
                    );
                    app(LeadRuleEngine::class)->apply($lead, '', [
                        LeadRuleEngine::TRIGGER_LEAD_STATUS_CHANGED,
                    ], [
                        'changed_status' => $target,
                        'previous_status' => $from,
                    ]);

                    $count++;
                }
            });

        $this->info("Updated {$count} scheduled lead status(es).");

        return self::SUCCESS;
    }
}
