<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Notifications\LeadRuleNotification;
use App\Services\LeadActivityService;
use App\Services\LeadRuleEngine;
use Illuminate\Console\Command;

class ProcessLeadReopens extends Command
{
    protected $signature = 'leads:process-reopens';

    protected $description = 'Restore snoozed leads whose scheduled reopen_at time has passed';

    public function handle(LeadActivityService $leadActivity): int
    {
        $count = 0;

        Lead::query()
            ->whereNotNull('reopen_at')
            ->where('reopen_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($leads) use (&$count, $leadActivity) {
                foreach ($leads as $lead) {
                    $restore = (string) ($lead->reopen_status ?: 'new');
                    if (! in_array($restore, Lead::STATUSES, true) || $restore === Lead::STATUS_SNOOZED) {
                        $restore = 'new';
                    }

                    $lead->status = $restore;
                    $lead->reopen_at = null;
                    $lead->reopen_status = null;
                    $lead->save();
                    $leadActivity->record(
                        $lead,
                        LeadActivity::REOPENED,
                        'Lead reopened automatically by rule schedule',
                        ['source' => 'reopen_after_days', 'status' => $restore]
                    );
                    app(LeadRuleEngine::class)->apply($lead, '', [
                        LeadRuleEngine::TRIGGER_LEAD_STATUS_CHANGED,
                    ], [
                        'changed_status' => $restore,
                        'previous_status' => Lead::STATUS_SNOOZED,
                    ]);

                    $lead->loadMissing('assignedUser');
                    if ($lead->assignedUser) {
                        $lead->assignedUser->notify(new LeadRuleNotification(
                            $lead,
                            'Snoozed lead reopened: '.$lead->name,
                            'This lead was scheduled to come back today.'
                        ));
                    }

                    $count++;
                }
            });

        $this->info("Reopened {$count} lead(s).");

        return self::SUCCESS;
    }
}
