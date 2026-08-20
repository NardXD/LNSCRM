<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadIdentity;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LeadActivityService
{
    public function record(
        Lead $lead,
        string $action,
        string $summary,
        ?array $meta = null,
        ?int $userId = null
    ): LeadActivity {
        try {
            return LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => $userId ?? Auth::id(),
                'action' => $action,
                'summary' => mb_substr($summary, 0, 500),
                'meta' => $meta,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to record lead activity', [
                'lead_id' => $lead->id,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);

            return new LeadActivity;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(Lead $lead): array
    {
        $lead->loadMissing(['identities', 'assignedUser:id,name']);

        return [
            'name' => (string) $lead->name,
            'company_name' => (string) ($lead->company_name ?? ''),
            'status' => (string) $lead->status,
            'source' => (string) ($lead->source ?? ''),
            'address' => (string) ($lead->address ?? ''),
            'city' => (string) ($lead->city ?? ''),
            'customer_type' => (string) ($lead->customer_type ?? ''),
            'residential_type' => (string) ($lead->residential_type ?? ''),
            'business_industry' => (string) ($lead->business_industry_other ?: $lead->business_industry ?? ''),
            'storage_reason' => (string) ($lead->storage_reason_other ?: $lead->storage_reason ?? ''),
            'assigned_to' => $lead->assigned_to ? (int) $lead->assigned_to : null,
            'assigned_name' => $lead->assignedUser?->name,
            'identities' => $lead->identities->map(fn (LeadIdentity $identity) => [
                'type' => $identity->type,
                'value' => $identity->value,
                'key' => $identity->type.':'.$identity->normalized_value,
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     */
    public function recordDiff(Lead $lead, array $before, ?int $userId = null): void
    {
        $lead->unsetRelation('identities');
        $lead->unsetRelation('assignedUser');
        $after = $this->snapshot($lead);
        $actor = $this->actorName($userId ?? Auth::id());

        if (($before['assigned_to'] ?? null) !== ($after['assigned_to'] ?? null)) {
            $this->recordAssignment(
                $lead,
                $before['assigned_to'] ?? null,
                $after['assigned_to'] ?? null,
                $before['assigned_name'] ?? null,
                $after['assigned_name'] ?? null,
                $userId
            );
        }

        if (($before['status'] ?? '') !== ($after['status'] ?? '')) {
            $from = $this->statusLabel((string) ($before['status'] ?? ''));
            $to = $this->statusLabel((string) ($after['status'] ?? ''));
            $this->record(
                $lead,
                LeadActivity::STATUS_CHANGED,
                $actor.' changed status from '.$from.' to '.$to,
                ['from' => $before['status'] ?? null, 'to' => $after['status'] ?? null],
                $userId
            );
            app(LeadRuleEngine::class)->apply($lead, '', [LeadRuleEngine::TRIGGER_LEAD_STATUS_CHANGED], [
                'changed_status' => (string) ($after['status'] ?? ''),
                'previous_status' => (string) ($before['status'] ?? ''),
            ]);
        }

        foreach ([
            'name' => 'name',
            'company_name' => 'company',
            'source' => 'source',
            'address' => 'address',
            'city' => 'city',
            'customer_type' => 'customer type',
            'residential_type' => 'residential type',
            'business_industry' => 'business industry',
            'storage_reason' => 'storage reason',
        ] as $field => $label) {
            $old = trim((string) ($before[$field] ?? ''));
            $new = trim((string) ($after[$field] ?? ''));
            if ($old === $new) {
                continue;
            }

            $this->record(
                $lead,
                LeadActivity::UPDATED,
                $this->fieldChangeSummary($actor, $label, $old, $new),
                ['field' => $field, 'from' => $old !== '' ? $old : null, 'to' => $new !== '' ? $new : null],
                $userId
            );
        }

        $beforeKeys = collect($before['identities'] ?? [])->keyBy('key');
        $afterKeys = collect($after['identities'] ?? [])->keyBy('key');

        foreach ($afterKeys as $key => $identity) {
            if ($beforeKeys->has($key)) {
                continue;
            }
            $this->record(
                $lead,
                LeadActivity::IDENTITY_ADDED,
                $actor.' added '.$this->identityLabel((string) $identity['type']).' '.$identity['value'],
                $identity,
                $userId
            );
        }

        foreach ($beforeKeys as $key => $identity) {
            if ($afterKeys->has($key)) {
                continue;
            }
            $this->record(
                $lead,
                LeadActivity::IDENTITY_REMOVED,
                $actor.' removed '.$this->identityLabel((string) $identity['type']).' '.$identity['value'],
                $identity,
                $userId
            );
        }
    }

    public function recordCreated(Lead $lead, ?string $source = null, ?int $userId = null): void
    {
        $actor = $this->actorName($userId ?? Auth::id());
        $source = trim((string) $source);
        $summary = $source !== ''
            ? $actor.' created this lead from '.$source
            : $actor.' created this lead';

        $this->record($lead, LeadActivity::CREATED, $summary, [
            'source' => $source !== '' ? $source : null,
            'assigned_to' => $lead->assigned_to,
        ], $userId);
    }

    public function recordAssignment(
        Lead $lead,
        mixed $fromId,
        mixed $toId,
        ?string $fromName = null,
        ?string $toName = null,
        ?int $userId = null,
        ?string $reason = null
    ): void {
        $fromId = $fromId ? (int) $fromId : null;
        $toId = $toId ? (int) $toId : null;
        if ($fromId === $toId) {
            return;
        }

        $fromName = $fromName ?: $this->userName($fromId);
        $toName = $toName ?: $this->userName($toId);
        $actor = $this->actorName($userId ?? Auth::id());
        $reasonSuffix = $reason ? ' ('.$reason.')' : '';

        if ($fromId && $toId) {
            $action = LeadActivity::REASSIGNED;
            $summary = $actor.' reassigned this lead from '.$fromName.' to '.$toName.$reasonSuffix;
        } elseif ($toId) {
            $action = LeadActivity::ASSIGNED;
            $summary = $actor.' assigned this lead to '.$toName.$reasonSuffix;
        } else {
            $action = LeadActivity::UNASSIGNED;
            $summary = $actor.' unassigned this lead'.($fromName ? ' (was '.$fromName.')' : '').$reasonSuffix;
        }

        $this->record($lead, $action, $summary, [
            'from_user_id' => $fromId,
            'from_user_name' => $fromName,
            'to_user_id' => $toId,
            'to_user_name' => $toName,
            'reason' => $reason,
        ], $userId);

        if ($toId) {
            app(LeadRuleEngine::class)->apply($lead, '', [LeadRuleEngine::TRIGGER_LEAD_ASSIGNED]);
        }
    }

    public function recordLabel(Lead $lead, string $labelName, bool $added, ?int $userId = null, ?int $labelId = null): void
    {
        $actor = $this->actorName($userId ?? Auth::id());
        $this->record(
            $lead,
            $added ? LeadActivity::LABEL_ADDED : LeadActivity::LABEL_REMOVED,
            $actor.' '.($added ? 'added' : 'removed').' label '.$labelName,
            ['label' => $labelName, 'label_id' => $labelId],
            $userId
        );

        if ($added) {
            app(LeadRuleEngine::class)->apply($lead, '', [LeadRuleEngine::TRIGGER_LEAD_LABELED], [
                'added_label' => $labelName,
                'added_label_id' => $labelId,
            ]);
        }
    }

    public function recordNote(Lead $lead, bool $added, ?int $userId = null, ?string $note = null): void
    {
        $actor = $this->actorName($userId ?? Auth::id());
        $this->record(
            $lead,
            $added ? LeadActivity::NOTE_ADDED : LeadActivity::NOTE_REMOVED,
            $actor.' '.($added ? 'added a note' : 'deleted a note'),
            $note !== null && $note !== '' ? ['note' => mb_substr($note, 0, 200)] : null,
            $userId
        );

        if ($added) {
            app(LeadRuleEngine::class)->apply($lead, '', [LeadRuleEngine::TRIGGER_LEAD_NOTE_ADDED], [
                'message' => $note,
            ]);
        }
    }

    protected function actorName(?int $userId): string
    {
        return $this->userName($userId) ?: 'System';
    }

    protected function userName(?int $userId): ?string
    {
        if (! $userId) {
            return null;
        }

        $name = User::query()->where('id', $userId)->value('name');

        return $name ? (string) $name : null;
    }

    protected function statusLabel(string $status): string
    {
        $status = trim($status);

        return $status !== '' ? ucfirst($status) : 'none';
    }

    protected function identityLabel(string $type): string
    {
        return match ($type) {
            LeadIdentity::TYPE_PHONE => 'phone',
            LeadIdentity::TYPE_EMAIL => 'email',
            LeadIdentity::TYPE_FACEBOOK => 'Facebook name',
            LeadIdentity::TYPE_INSTAGRAM => 'Instagram username',
            default => $type,
        };
    }

    protected function fieldChangeSummary(string $actor, string $label, string $old, string $new): string
    {
        if ($old === '') {
            return $actor.' set '.$label.' to '.$new;
        }
        if ($new === '') {
            return $actor.' cleared '.$label;
        }

        return $actor.' changed '.$label.' from '.$old.' to '.$new;
    }
}
