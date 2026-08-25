<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Services\LeadActivityService;
use App\Services\LeadFollowUpDayService;
use App\Services\LeadRuleEngine;
use Illuminate\Console\Command;

class ProcessLeadFollowUpDays extends Command
{
    protected $signature = 'leads:process-follow-up-days';

    protected $description = 'Apply follow-up labels and fire rules when a lead’s created-date age advances';

    public function handle(
        LeadFollowUpDayService $followUpDays,
        LeadActivityService $leadActivity,
        LeadRuleEngine $rules
    ): int {
        $processed = 0;

        Company::query()->orderBy('id')->chunkById(50, function ($companies) use (
            $followUpDays,
            $leadActivity,
            $rules,
            &$processed
        ) {
            foreach ($companies as $company) {
                $companyId = (int) $company->id;
                $followUpDays->ensureForCompany($companyId, true, false);
                $today = $followUpDays->today($companyId);

                Lead::query()
                    ->where('company_id', $companyId)
                    ->whereNotIn('status', LeadFollowUpDayService::CLOSED_STATUSES)
                    ->orderBy('id')
                    ->chunkById(100, function ($leads) use (
                        $followUpDays,
                        $leadActivity,
                        $rules,
                        $today,
                        &$processed
                    ) {
                        foreach ($leads as $lead) {
                            $day = $followUpDays->dayFromCreatedAt($lead->created_at, $today);
                            if ($day < 1) {
                                continue;
                            }
                            if ((int) $lead->follow_up_notified_day === $day) {
                                continue;
                            }

                            $claimed = Lead::query()
                                ->whereKey($lead->id)
                                ->where(function ($query) use ($day) {
                                    $query->whereNull('follow_up_notified_day')
                                        ->orWhere('follow_up_notified_day', '!=', $day);
                                })
                                ->update(['follow_up_notified_day' => $day]);

                            if ($claimed < 1) {
                                continue;
                            }

                            $lead->follow_up_notified_day = $day;
                            $dueLabel = $followUpDays->dueLabelToApply($lead, $day);
                            if ($dueLabel) {
                                $lead->labels()->syncWithoutDetaching([$dueLabel->id]);
                                $leadActivity->recordLabel($lead, $dueLabel->name, true, labelId: $dueLabel->id);
                                $lead->unsetRelation('labels');
                                $lead->load('labels');
                            }
                            $leadActivity->record(
                                $lead,
                                LeadActivity::FOLLOW_UP_DAY,
                                'Follow-up day '.$day.' reached',
                                ['follow_up_day' => $day, 'source' => 'created_at'],
                                null
                            );
                            $rules->apply($lead, '', [LeadRuleEngine::TRIGGER_FOLLOW_UP_DAY_REACHED], [
                                'follow_up_day' => $day,
                                'company_id' => (int) $lead->company_id,
                            ]);
                            $processed++;
                        }
                    });
            }
        });

        $this->info("Processed {$processed} lead follow-up day(s).");

        return self::SUCCESS;
    }
}
