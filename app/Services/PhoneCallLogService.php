<?php

namespace App\Services;

use App\Models\PhoneCallLog;
use App\Models\TwilioPhoneNumber;
use App\Models\User;
use Carbon\Carbon;

class PhoneCallLogService
{
    public function __construct(
        protected TwilioCompanyService $twilioCompany
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function upsertFromWebhook(array $payload): ?PhoneCallLog
    {
        $callSid = $payload['CallSid'] ?? null;
        if (! $callSid) {
            return null;
        }

        $accountSid = $payload['AccountSid'] ?? null;
        $from = $payload['From'] ?? null;
        $to = $payload['To'] ?? null;
        $direction = $payload['Direction'] ?? null;
        $status = $payload['CallStatus'] ?? null;
        $duration = (int) ($payload['CallDuration'] ?? 0);

        $company = $this->twilioCompany->resolveCompanyFromWebhook($accountSid, $to, $from);
        if (! $company) {
            return null;
        }

        $user = $this->twilioCompany->resolveUserFromNumbers($to, $from, (string) $direction);

        $log = PhoneCallLog::query()->firstOrNew(['call_sid' => $callSid]);
        $log->company_id = $company->id;
        $log->user_id = $user?->id ?? $log->user_id;
        $log->direction = $direction ?? $log->direction;
        $log->from_number = $from ?? $log->from_number;
        $log->to_number = $to ?? $log->to_number;
        $log->status = $status ?? $log->status;
        if ($duration > 0) {
            $log->duration = $duration;
        }

        if (! $log->started_at && in_array($status, ['initiated', 'ringing', 'in-progress', 'answered'], true)) {
            $log->started_at = now();
        }

        if (in_array($status, ['completed', 'busy', 'no-answer', 'failed', 'canceled'], true)) {
            $log->ended_at = now();
            if ($duration > 0 && $log->started_at) {
                $log->ended_at = Carbon::parse($log->started_at)->addSeconds($duration);
            }
        }

        $log->save();

        return $log;
    }

    /**
     * Record outbound call initiated from the app.
     */
    public function recordOutbound(int $companyId, int $userId, string $callSid, string $from, string $to): PhoneCallLog
    {
        return PhoneCallLog::query()->updateOrCreate(
            ['call_sid' => $callSid],
            [
                'company_id' => $companyId,
                'user_id' => $userId,
                'direction' => 'outbound-api',
                'from_number' => $from,
                'to_number' => $to,
                'status' => 'initiated',
                'started_at' => now(),
            ]
        );
    }
}
