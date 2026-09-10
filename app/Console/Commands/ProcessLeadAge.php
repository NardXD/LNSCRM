<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Services\LeadRuleEngine;
use Illuminate\Console\Command;

class ProcessLeadAge extends Command
{
    protected $signature = 'leads:process-lead-age';

    protected $description = "Fire the lead rule engine's \"Lead age is reached\" trigger once per day as each lead's age advances";

    public function handle(LeadRuleEngine $rules): int
    {
        $processed = 0;

        Lead::query()
            ->whereNotIn('status', ['converted', 'lost', Lead::STATUS_ARCHIVED])
            ->orderBy('id')
            ->chunkById(100, function ($leads) use ($rules, &$processed) {
                foreach ($leads as $lead) {
                    $day = (int) $lead->created_at->diffInDays(now());
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

                    $rules->apply($lead, '', [LeadRuleEngine::TRIGGER_LEAD_AGE_REACHED], [
                        'company_id' => (int) $lead->company_id,
                    ]);
                    $processed++;
                }
            });

        $this->info("Processed {$processed} lead age check(s).");

        return self::SUCCESS;
    }
}
