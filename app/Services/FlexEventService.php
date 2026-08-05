<?php

namespace App\Services;

use App\Models\PhoneCallLog;
use App\Models\TwilioFlexIntegration;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class FlexEventService
{
    public function __construct(
        protected TwilioCompanyService $twilioCompany
    ) {}

    /**
     * Ingest TaskRouter / Flex event callbacks and upsert phone call logs when voice SIDs are present.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handle(TwilioFlexIntegration $integration, array $payload): ?PhoneCallLog
    {
        $eventType = (string) ($payload['EventType'] ?? $payload['event_type'] ?? '');
        $taskAttributes = $this->decodeAttributes($payload['TaskAttributes'] ?? $payload['task_attributes'] ?? null);

        $callSid = $payload['CallSid']
            ?? $payload['call_sid']
            ?? ($taskAttributes['call_sid'] ?? null)
            ?? ($taskAttributes['conference']['sid'] ?? null)
            ?? ($taskAttributes['conversations']['conversation_id'] ?? null);

        $from = $payload['From']
            ?? $payload['Caller']
            ?? ($taskAttributes['from'] ?? null)
            ?? ($taskAttributes['caller'] ?? null)
            ?? ($taskAttributes['outbound_to'] ?? null);

        $to = $payload['To']
            ?? $payload['Called']
            ?? ($taskAttributes['to'] ?? null)
            ?? ($taskAttributes['called'] ?? null)
            ?? ($taskAttributes['outbound_to'] ?? null);

        $direction = $payload['Direction']
            ?? ($taskAttributes['direction'] ?? null)
            ?? $this->inferDirection($eventType, $taskAttributes);

        $status = $this->mapStatus($eventType, $payload);

        $workerEmail = $payload['WorkerName']
            ?? $payload['WorkerSid']
            ?? ($taskAttributes['worker_email'] ?? null);

        $user = $this->resolveWorkerUser($integration->company_id, $workerEmail, $payload);

        // Voice call SID required for phone_call_logs uniqueness; skip pure chat tasks without a call.
        if (! $callSid || ! is_string($callSid) || ! str_starts_with($callSid, 'CA')) {
            Log::info('Flex event skipped (no voice CallSid)', [
                'company_id' => $integration->company_id,
                'event_type' => $eventType,
                'task_sid' => $payload['TaskSid'] ?? null,
            ]);

            return null;
        }

        $log = PhoneCallLog::query()->firstOrNew(['call_sid' => $callSid]);
        $log->company_id = $integration->company_id;
        if ($user) {
            $log->user_id = $user->id;
        }
        if ($direction) {
            $log->direction = $direction;
        }
        if ($from) {
            $log->from_number = $this->twilioCompany->normalizePhone((string) $from);
        }
        if ($to) {
            $log->to_number = $this->twilioCompany->normalizePhone((string) $to);
        }
        if ($status) {
            $log->status = $status;
        }

        $duration = (int) ($payload['CallDuration'] ?? $payload['TaskAge'] ?? 0);
        if ($duration > 0) {
            $log->duration = $duration;
        }

        if (! $log->started_at && in_array($status, ['initiated', 'ringing', 'in-progress', 'answered', 'reserved'], true)) {
            $log->started_at = now();
        }

        if (in_array($status, ['completed', 'canceled', 'cancelled', 'wrapping', 'timeout'], true)) {
            $log->ended_at = now();
            if ($duration > 0 && $log->started_at) {
                $log->ended_at = Carbon::parse($log->started_at)->addSeconds($duration);
            }
        }

        $log->save();

        return $log;
    }

    /**
     * @param  mixed  $raw
     * @return array<string, mixed>
     */
    protected function decodeAttributes(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    protected function inferDirection(string $eventType, array $attrs): ?string
    {
        if (isset($attrs['direction']) && is_string($attrs['direction'])) {
            return $attrs['direction'];
        }

        if (! empty($attrs['outbound_to'])) {
            return 'outbound';
        }

        if (str_contains(strtolower($eventType), 'outbound')) {
            return 'outbound';
        }

        return 'inbound';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function mapStatus(string $eventType, array $payload): ?string
    {
        $direct = $payload['CallStatus'] ?? $payload['TaskAssignmentStatus'] ?? null;
        if (is_string($direct) && $direct !== '') {
            return strtolower($direct);
        }

        return match (true) {
            str_contains($eventType, 'reservation.created') => 'ringing',
            str_contains($eventType, 'reservation.accepted') => 'in-progress',
            str_contains($eventType, 'reservation.completed') => 'completed',
            str_contains($eventType, 'reservation.canceled'),
            str_contains($eventType, 'reservation.cancelled'),
            str_contains($eventType, 'reservation.rescinded'),
            str_contains($eventType, 'reservation.timeout') => 'canceled',
            str_contains($eventType, 'task.completed') => 'completed',
            str_contains($eventType, 'task.canceled'),
            str_contains($eventType, 'task.cancelled') => 'canceled',
            default => $eventType !== '' ? strtolower($eventType) : null,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveWorkerUser(int $companyId, mixed $workerHint, array $payload): ?User
    {
        $candidates = [];
        foreach ([$workerHint, $payload['WorkerAttributes'] ?? null] as $raw) {
            if (is_string($raw) && str_contains($raw, '@')) {
                $candidates[] = $raw;
            }
            if (is_string($raw) && str_starts_with($raw, '{')) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    foreach (['email', 'contact_uri', 'full_name'] as $k) {
                        if (! empty($decoded[$k]) && is_string($decoded[$k])) {
                            $candidates[] = $decoded[$k];
                        }
                    }
                }
            }
        }

        foreach ($candidates as $candidate) {
            if (str_contains($candidate, '@')) {
                $user = User::query()
                    ->where('company_id', $companyId)
                    ->where('email', $candidate)
                    ->first();
                if ($user) {
                    return $user;
                }
            }
        }

        return null;
    }
}
