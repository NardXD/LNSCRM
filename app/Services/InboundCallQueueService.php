<?php

namespace App\Services;

use App\Models\CallAgentPresence;
use App\Models\CallRoundRobinState;
use App\Models\Company;
use App\Models\LeadRoundRobinPool;
use App\Models\LeadRoundRobinState;
use App\Models\PhoneCallLog;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InboundCallQueueService
{
    public function heartbeatTtlSeconds(): int
    {
        return (int) config('services.twilio.call_queue_heartbeat_ttl', 45);
    }

    public function getOrCreatePresence(User $user): CallAgentPresence
    {
        return CallAgentPresence::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'company_id' => $user->company_id,
                'status' => CallAgentPresence::STATUS_OFFLINE,
            ]
        );
    }

    public function setAvailable(User $user): CallAgentPresence
    {
        $presence = $this->getOrCreatePresence($user);

        // Don't interrupt an in-progress queued call; just keep the heartbeat fresh.
        if (
            $presence->status === CallAgentPresence::STATUS_BUSY
            && ! empty($presence->current_call_sid)
        ) {
            $presence->fill([
                'company_id' => $user->company_id,
                'last_heartbeat_at' => now(),
            ])->save();

            return $presence->fresh();
        }

        $presence->fill([
            'company_id' => $user->company_id,
            'status' => CallAgentPresence::STATUS_AVAILABLE,
            'last_heartbeat_at' => now(),
            'current_call_sid' => null,
        ])->save();

        return $presence->fresh();
    }

    public function setOffline(User $user): CallAgentPresence
    {
        $presence = $this->getOrCreatePresence($user);
        $presence->fill([
            'company_id' => $user->company_id,
            'status' => CallAgentPresence::STATUS_OFFLINE,
            'last_heartbeat_at' => now(),
            'current_call_sid' => null,
        ])->save();

        return $presence->fresh();
    }

    public function heartbeat(User $user): CallAgentPresence
    {
        $presence = $this->getOrCreatePresence($user);

        if ($presence->status === CallAgentPresence::STATUS_OFFLINE) {
            return $presence;
        }

        $presence->fill([
            'company_id' => $user->company_id,
            'last_heartbeat_at' => now(),
        ])->save();

        return $presence->fresh();
    }

    public function markBusy(User $user, string $callSid): CallAgentPresence
    {
        $presence = $this->getOrCreatePresence($user);
        $presence->fill([
            'company_id' => $user->company_id,
            'status' => CallAgentPresence::STATUS_BUSY,
            'last_heartbeat_at' => now(),
            'current_call_sid' => $callSid,
        ])->save();

        return $presence->fresh();
    }

    public function releaseFromCall(?string $callSid = null, ?User $user = null): void
    {
        $query = CallAgentPresence::query()->where('status', CallAgentPresence::STATUS_BUSY);

        if ($callSid) {
            $query->where('current_call_sid', $callSid);
        } elseif ($user) {
            $query->where('user_id', $user->id);
        } else {
            return;
        }

        $presences = $query->get();
        foreach ($presences as $presence) {
            $fresh = $presence->last_heartbeat_at
                && $presence->last_heartbeat_at->gte(now()->subSeconds($this->heartbeatTtlSeconds()));

            $presence->fill([
                'status' => $fresh ? CallAgentPresence::STATUS_AVAILABLE : CallAgentPresence::STATUS_OFFLINE,
                'current_call_sid' => null,
            ])->save();
        }
    }

    /**
     * @param  array<int>  $excludeUserIds
     */
    public function pickNextAgent(int $companyId, array $excludeUserIds = []): ?User
    {
        return $this->pickNextFromPool(
            $companyId,
            $this->availableAgents($companyId, $excludeUserIds),
            CallRoundRobinState::class,
            'last_assigned_user_id',
            $excludeUserIds,
            'call'
        );
    }

    /**
     * Round-robin among agents currently available for inbound calls, without
     * advancing the phone-system call queue pointer.
     *
     * @param  array<int>  $excludeUserIds
     */
    public function pickNextLeadAgent(int $companyId, array $excludeUserIds = []): ?User
    {
        return $this->pickNextFromPool(
            $companyId,
            $this->availableAgents($companyId, $excludeUserIds),
            LeadRoundRobinState::class,
            'last_assigned_user_id',
            $excludeUserIds,
            'lead-available'
        );
    }

    /**
     * Round-robin among every active teammate, whether or not they are
     * available for inbound calls.
     *
     * @param  array<int>  $excludeUserIds
     */
    public function pickNextLeadTeammate(int $companyId, array $excludeUserIds = []): ?User
    {
        return $this->pickNextFromPool(
            $companyId,
            $this->companyTeammates($companyId, $excludeUserIds),
            LeadRoundRobinState::class,
            'last_assigned_all_user_id',
            $excludeUserIds,
            'lead-all'
        );
    }

    /**
     * Round-robin among a chosen set of teammates.
     *
     * @param  array<int>  $userIds
     * @param  array<int>  $excludeUserIds
     */
    public function pickNextFromUserIds(int $companyId, array $userIds, array $excludeUserIds = []): ?User
    {
        $wanted = collect($userIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->sort()
            ->values();
        if ($wanted->isEmpty()) {
            return null;
        }

        $agents = $this->companyTeammates($companyId, $excludeUserIds)
            ->filter(fn (User $user) => $wanted->contains((int) $user->id))
            ->values();

        return $this->pickNextFromKeyedPool(
            $companyId,
            $agents,
            sha1($wanted->implode(',')),
            $excludeUserIds
        );
    }

    /**
     * @param  Collection<int, User>  $agents
     * @param  array<int>  $excludeUserIds
     */
    protected function pickNextFromKeyedPool(
        int $companyId,
        Collection $agents,
        string $poolKey,
        array $excludeUserIds = []
    ): ?User {
        return DB::transaction(function () use ($companyId, $agents, $poolKey, $excludeUserIds) {
            $state = LeadRoundRobinPool::query()
                ->where('company_id', $companyId)
                ->where('pool_key', $poolKey)
                ->lockForUpdate()
                ->first();

            if (! $state) {
                LeadRoundRobinPool::query()->create([
                    'company_id' => $companyId,
                    'pool_key' => $poolKey,
                    'last_assigned_user_id' => null,
                ]);
                $state = LeadRoundRobinPool::query()
                    ->where('company_id', $companyId)
                    ->where('pool_key', $poolKey)
                    ->lockForUpdate()
                    ->first();
            }

            if ($agents->isEmpty()) {
                return null;
            }

            $sorted = $agents->sortBy('id')->values();
            $startIndex = 0;
            $lastId = (int) ($state->last_assigned_user_id ?? 0);

            if ($lastId > 0) {
                $idx = $sorted->search(fn (User $user) => (int) $user->id === $lastId);
                if ($idx !== false) {
                    $startIndex = ((int) $idx + 1) % $sorted->count();
                }
            }

            /** @var User $picked */
            $picked = $sorted[$startIndex];
            $state->last_assigned_user_id = $picked->id;
            $state->save();

            Log::info('Round-robin pool picked user', [
                'purpose' => 'lead-selected',
                'company_id' => $companyId,
                'pool_key' => $poolKey,
                'user_id' => $picked->id,
                'excluded' => $excludeUserIds,
                'pool_count' => $sorted->count(),
            ]);

            return $picked;
        });
    }

    /**
     * @param  Collection<int, User>  $agents
     * @param  class-string<CallRoundRobinState|LeadRoundRobinState>  $stateClass
     * @param  array<int>  $excludeUserIds
     */
    protected function pickNextFromPool(
        int $companyId,
        Collection $agents,
        string $stateClass,
        string $pointerColumn,
        array $excludeUserIds = [],
        string $purpose = 'call'
    ): ?User {
        return DB::transaction(function () use ($companyId, $agents, $stateClass, $pointerColumn, $excludeUserIds, $purpose) {
            $state = $stateClass::query()
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->first();

            if (! $state) {
                $stateClass::query()->create([
                    'company_id' => $companyId,
                    'last_assigned_user_id' => null,
                ]);
                $state = $stateClass::query()
                    ->where('company_id', $companyId)
                    ->lockForUpdate()
                    ->first();
            }

            if ($agents->isEmpty()) {
                return null;
            }

            $sorted = $agents->sortBy('id')->values();
            $startIndex = 0;
            $lastId = (int) ($state->{$pointerColumn} ?? 0);

            if ($lastId > 0) {
                $idx = $sorted->search(fn (User $user) => (int) $user->id === $lastId);
                if ($idx !== false) {
                    $startIndex = ((int) $idx + 1) % $sorted->count();
                }
            }

            /** @var User $picked */
            $picked = $sorted[$startIndex];
            $state->{$pointerColumn} = $picked->id;
            $state->save();

            Log::info('Round-robin queue picked user', [
                'purpose' => $purpose,
                'company_id' => $companyId,
                'user_id' => $picked->id,
                'excluded' => $excludeUserIds,
                'pool_count' => $sorted->count(),
            ]);

            return $picked;
        });
    }

    /**
     * @param  array<int>  $excludeUserIds
     * @return Collection<int, User>
     */
    public function companyTeammates(int $companyId, array $excludeUserIds = []): Collection
    {
        return User::query()
            ->where('company_id', $companyId)
            ->where(function ($q) {
                $q->where('status', 'active')->orWhereNull('status');
            })
            ->when($excludeUserIds !== [], fn ($q) => $q->whereNotIn('id', $excludeUserIds))
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<int>  $excludeUserIds
     * @return Collection<int, User>
     */
    public function availableAgents(int $companyId, array $excludeUserIds = []): Collection
    {
        $cutoff = now()->subSeconds($this->heartbeatTtlSeconds());

        $presences = CallAgentPresence::query()
            ->where('company_id', $companyId)
            ->where('status', CallAgentPresence::STATUS_AVAILABLE)
            ->where('last_heartbeat_at', '>=', $cutoff)
            ->when($excludeUserIds !== [], fn ($q) => $q->whereNotIn('user_id', $excludeUserIds))
            ->with('user')
            ->get();

        return $presences
            ->map(fn (CallAgentPresence $presence) => $presence->user)
            ->filter(fn (?User $user) => $this->isEligibleAgent($user))
            ->values();
    }

    /**
     * Full queue view for the Phone System UI.
     *
     * @return array{
     *     counts: array{available: int, busy: int, in_queue: int},
     *     available_agents: array<int, array{id: int, name: string, email: string, status: string}>,
     *     busy_agents: array<int, array{id: int, name: string, email: string, status: string}>,
     *     queue_order: array<int, array{id: int, name: string, email: string, status: string, position: int, is_next: bool}>,
     *     next_agent: array{id: int, name: string, email: string}|null,
     *     last_assigned_user_id: int|null
     * }
     */
    public function queueSnapshot(int $companyId): array
    {
        $cutoff = now()->subSeconds($this->heartbeatTtlSeconds());

        $presences = CallAgentPresence::query()
            ->where('company_id', $companyId)
            ->whereIn('status', [
                CallAgentPresence::STATUS_AVAILABLE,
                CallAgentPresence::STATUS_BUSY,
            ])
            ->where(function ($q) use ($cutoff) {
                $q->where('last_heartbeat_at', '>=', $cutoff)
                    ->orWhere('status', CallAgentPresence::STATUS_BUSY);
            })
            ->with('user')
            ->get()
            ->filter(fn (CallAgentPresence $p) => $this->isEligibleAgent($p->user))
            ->values();

        $available = $presences
            ->filter(fn (CallAgentPresence $p) => $p->status === CallAgentPresence::STATUS_AVAILABLE)
            ->sortBy(fn (CallAgentPresence $p) => $p->user_id)
            ->values();

        $busy = $presences
            ->filter(fn (CallAgentPresence $p) => $p->status === CallAgentPresence::STATUS_BUSY)
            ->sortBy(fn (CallAgentPresence $p) => $p->user_id)
            ->values();

        $lastAssignedId = CallRoundRobinState::query()
            ->where('company_id', $companyId)
            ->value('last_assigned_user_id');
        $lastAssignedId = $lastAssignedId ? (int) $lastAssignedId : null;

        $nextIndex = 0;
        if ($lastAssignedId && $available->isNotEmpty()) {
            $idx = $available->search(fn (CallAgentPresence $p) => (int) $p->user_id === $lastAssignedId);
            if ($idx !== false) {
                $nextIndex = ((int) $idx + 1) % $available->count();
            }
        }

        $queueOrder = $available->values()->map(function (CallAgentPresence $presence, int $index) use ($nextIndex) {
            $user = $presence->user;

            return [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'email' => (string) $user->email,
                'status' => CallAgentPresence::STATUS_AVAILABLE,
                'position' => $index + 1,
                'is_next' => $index === $nextIndex,
            ];
        })->all();

        $nextAgent = null;
        if ($available->isNotEmpty()) {
            $next = $available[$nextIndex]->user;
            $nextAgent = [
                'id' => (int) $next->id,
                'name' => (string) $next->name,
                'email' => (string) $next->email,
            ];
        }

        $mapAgent = fn (CallAgentPresence $presence) => [
            'id' => (int) $presence->user->id,
            'name' => (string) $presence->user->name,
            'email' => (string) $presence->user->email,
            'status' => (string) $presence->status,
        ];

        return [
            'counts' => [
                'available' => $available->count(),
                'busy' => $busy->count(),
                'in_queue' => $available->count() + $busy->count(),
            ],
            'available_agents' => $available->map($mapAgent)->values()->all(),
            'busy_agents' => $busy->map($mapAgent)->values()->all(),
            'queue_order' => $queueOrder,
            'next_agent' => $nextAgent,
            'last_assigned_user_id' => $lastAssignedId,
        ];
    }

    protected function isEligibleAgent(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if (($user->status ?? null) !== 'active') {
            return false;
        }

        return $user->hasPermission('view_phone_system');
    }

    public function resolveCompanyForInbound(?string $accountSid, ?string $called, ?string $caller): ?Company
    {
        return app(TwilioCompanyService::class)
            ->resolveCompanyFromWebhook($accountSid, $called, $caller);
    }

    /**
     * @param  array<int>  $attemptedUserIds
     */
    public function rememberAssignment(string $callSid, int $companyId, int $userId, array $attemptedUserIds = [], int $clientRetries = 0, ?string $from = null, ?string $to = null): void
    {
        $attempted = array_values(array_unique(array_map('intval', array_merge($attemptedUserIds, [$userId]))));

        $existing = $this->getAssignment($callSid) ?? [];
        $from = $from ?: ($existing['from'] ?? null);
        $to = $to ?: ($existing['to'] ?? null);

        Cache::put($this->assignmentCacheKey($callSid), [
            'company_id' => $companyId,
            'current_user_id' => $userId,
            'attempted_user_ids' => $attempted,
            'client_retries' => max(0, $clientRetries),
            'ended_by_agent' => false,
            'from' => $from,
            'to' => $to,
        ], now()->addHours(2));

        $log = PhoneCallLog::query()->firstOrNew(['call_sid' => $callSid]);
        $log->company_id = $companyId;
        $log->user_id = $userId;
        if ($from) {
            $log->from_number = $from;
        }
        if ($to) {
            $log->to_number = $to;
        }
        if (! $log->exists) {
            $log->direction = 'inbound';
            $log->status = 'ringing';
            $log->started_at = now();
        }
        $log->save();

        app(LeadAutoCreateService::class)->fromCallLegs(
            $companyId,
            $log->from_number,
            $log->to_number,
            $log->direction
        );
    }

    /**
     * @return array{company_id: int, current_user_id: int|null, attempted_user_ids: array<int>, client_retries: int, ended_by_agent: bool, from?: ?string, to?: ?string}|null
     */
    public function getAssignment(string $callSid): ?array
    {
        $data = Cache::get($this->assignmentCacheKey($callSid));

        return is_array($data) ? $data : null;
    }

    public function markEndedByAgent(?string $callSid, ?User $user = null): void
    {
        $sids = array_values(array_filter(array_unique(array_map('strval', array_filter([
            $callSid,
            $user?->id ? $this->getOrCreatePresence($user)->current_call_sid : null,
        ])))));

        foreach ($sids as $sid) {
            $data = $this->getAssignment($sid) ?? [];
            $data['ended_by_agent'] = true;
            Cache::put($this->assignmentCacheKey($sid), $data, now()->addHours(2));
        }
    }

    public function forgetAssignment(string $callSid): void
    {
        Cache::forget($this->assignmentCacheKey($callSid));
    }

    protected function assignmentCacheKey(string $callSid): string
    {
        return 'inbound_call_queue:'.$callSid;
    }
}
