<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Services\LeadRuleEngine;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs "Label added" lead rules outside the request that added the label.
 * Dispatch with dispatchAfterResponse() so the label UI is not blocked by rule actions.
 */
class ApplyLeadLabeledRulesJob
{
    use Dispatchable;

    public function __construct(
        public int $leadId,
        public string $labelName,
        public ?int $labelId = null,
    ) {}

    public function handle(LeadRuleEngine $engine): void
    {
        $lead = Lead::query()->find($this->leadId);
        if (! $lead) {
            return;
        }

        try {
            $engine->apply($lead, '', [LeadRuleEngine::TRIGGER_LEAD_LABELED], [
                'added_label' => $this->labelName,
                'added_label_id' => $this->labelId,
            ]);
        } catch (Throwable $e) {
            Log::warning('Lead labeled rules failed', [
                'lead_id' => $this->leadId,
                'label_id' => $this->labelId,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
