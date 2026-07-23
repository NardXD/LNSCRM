<?php

namespace App\Services;

use App\Events\EmployeeMonitoringStatusChanged;
use App\Events\LiveViewChatMessageReceived;
use App\Events\LiveViewSignalReceived;
use App\Models\ClientUser;
use App\Models\Company;
use App\Models\CompanyHistory;
use App\Models\LiveViewChatMessage;
use App\Models\LiveViewSession;
use App\Models\ScreenRecording;
use App\Models\TimeTracking;
use App\Models\User;
use App\Models\WebrtcSignal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class LiveViewService
{
    public function heartbeatTtlSeconds(): int
    {
        return (int) config('live-view.heartbeat_ttl_seconds', 45);
    }

    public function heartbeat(User $worker, ?string $ip = null, bool $streamActive = true): void
    {
        if (! $streamActive) {
            $this->clearHeartbeat($worker->id);

            return;
        }

        $this->safeCachePut(
            $this->heartbeatKey($worker->id),
            [
                'at' => now()->toIso8601String(),
                'ip' => $ip,
                'stream_active' => true,
            ],
            $this->heartbeatTtlSeconds()
        );

        $this->broadcastEmployeeMonitoringStatus($worker->id);
    }

    public function clearHeartbeat(int $workerId): void
    {
        $this->safeCacheForget($this->heartbeatKey($workerId));
        $this->broadcastEmployeeMonitoringStatus($workerId);
    }

    public function isWorkerLiveAvailable(User $worker): bool
    {
        $maps = $this->employeeLiveStatusMaps([$worker->id]);

        return isset($maps['live'][$worker->id]);
    }

    /**
     * Batch live-view status for many employees (avoids N+1 queries in monitoring).
     *
     * @param  array<int>  $employeeIds
     * @return array{clocked_in: array<int, true>, recording: array<int, true>, live: array<int, true>}
     */
    public function employeeLiveStatusMaps(array $employeeIds): array
    {
        $employeeIds = array_values(array_unique(array_map('intval', $employeeIds)));

        if ($employeeIds === []) {
            return ['clocked_in' => [], 'recording' => [], 'live' => []];
        }

        $clockedIn = TimeTracking::query()
            ->whereIn('user_id', $employeeIds)
            ->where('status', 'active')
            ->whereNotNull('time_in')
            ->whereNull('time_out')
            ->pluck('user_id')
            ->flip()
            ->all();

        $recording = ScreenRecording::query()
            ->whereIn('user_id', $employeeIds)
            ->where('status', 'recording')
            ->whereJsonContains('metadata->recording_session_active', true)
            ->pluck('user_id')
            ->unique()
            ->flip()
            ->all();

        $live = [];
        foreach ($employeeIds as $employeeId) {
            if (! isset($clockedIn[$employeeId], $recording[$employeeId])) {
                continue;
            }

            $heartbeat = $this->safeCacheGet($this->heartbeatKey($employeeId));
            if (is_array($heartbeat) && ($heartbeat['stream_active'] ?? false) === true) {
                $live[$employeeId] = true;
            }
        }

        return [
            'clocked_in' => $clockedIn,
            'recording' => $recording,
            'live' => $live,
        ];
    }

    public function isWorkerClockedIn(int $workerId): bool
    {
        return TimeTracking::query()
            ->where('user_id', $workerId)
            ->where('status', 'active')
            ->whereNotNull('time_in')
            ->whereNull('time_out')
            ->exists();
    }

    public function hasActiveRecordingSession(int $workerId): bool
    {
        return ScreenRecording::query()
            ->where('user_id', $workerId)
            ->where('status', 'recording')
            ->whereJsonContains('metadata->recording_session_active', true)
            ->exists();
    }

    public function isWorkerEligibleForLiveView(int $workerId): bool
    {
        return $this->safeCacheRemember(
            $this->eligibilityKey($workerId),
            30,
            fn () => $this->isWorkerClockedIn($workerId) && $this->hasActiveRecordingSession($workerId)
        );
    }

    public function userCanParticipateInSignalingCached(User $user): bool
    {
        $cacheKey = 'live_view:can_signal:'.$user->id;

        return (bool) $this->safeCacheRemember($cacheKey, 30, function () use ($user) {
            return $this->userCanParticipateInSignaling($user);
        });
    }

    public function createSession(User $admin, User $worker, ?string $adminIp, ?string $adminUserAgent): LiveViewSession
    {
        if ((int) $admin->company_id !== (int) $worker->company_id) {
            throw new \InvalidArgumentException('Worker not found in your company.');
        }

        if (! $this->isWorkerLiveAvailable($worker)) {
            throw new \InvalidArgumentException('Worker is not available for live viewing. They must be clocked in with an active recording session and screen share.');
        }

        return DB::transaction(function () use ($admin, $worker, $adminIp, $adminUserAgent) {
            $this->endActiveSessionsForPair('user', $admin->id, $worker->id, 'replaced');
            $this->consumePendingSignalsForPair('user', $admin->id, $worker->id);

            $session = LiveViewSession::create([
                'company_id' => $admin->company_id,
                'admin_id' => $admin->id,
                'admin_type' => 'user',
                'worker_id' => $worker->id,
                'status' => LiveViewSession::STATUS_PENDING,
                'admin_ip' => $adminIp,
                'admin_user_agent' => $adminUserAgent,
            ]);

            $this->queueSignal(
                companyId: (int) $admin->company_id,
                fromUserId: (int) $admin->id,
                toUserId: (int) $worker->id,
                signalType: 'live-view-request',
                payload: [
                    'session_id' => $session->id,
                    'admin_id' => $admin->id,
                    'admin_name' => $admin->name,
                ],
                sessionId: $session->id
            );

            $this->logAudit(
                companyId: (int) $admin->company_id,
                action: CompanyHistory::ACTION_LIVE_VIEW_STARTED,
                description: "{$admin->name} started live viewing {$worker->name}'s screen.",
                changedBy: (int) $admin->id,
                context: [
                    'session_id' => $session->id,
                    'admin_id' => $admin->id,
                    'admin_name' => $admin->name,
                    'worker_id' => $worker->id,
                    'worker_name' => $worker->name,
                ]
            );

            return $session;
        });
    }

    public function createSessionForClient(ClientUser $clientUser, User $worker, ?string $clientIp, ?string $clientUserAgent): LiveViewSession
    {
        $client = $clientUser->client;

        if (! $client || (int) $client->company_id !== (int) $worker->company_id) {
            throw new \InvalidArgumentException('Worker not found in your company.');
        }

        if (! $client->employees()->where('users.id', $worker->id)->exists()) {
            throw new \InvalidArgumentException('Employee is not assigned to your account.');
        }

        if (! $this->isWorkerLiveAvailable($worker)) {
            throw new \InvalidArgumentException('Worker is not available for live viewing. They must be clocked in with an active recording session and screen share.');
        }

        return DB::transaction(function () use ($clientUser, $worker, $clientIp, $clientUserAgent) {
            $this->endActiveSessionsForPair('client', $clientUser->id, $worker->id, 'replaced');
            $this->consumePendingSignalsForPair('client', $clientUser->id, $worker->id);

            $session = LiveViewSession::create([
                'company_id' => $worker->company_id,
                'admin_id' => $clientUser->id,
                'admin_type' => 'client',
                'worker_id' => $worker->id,
                'status' => LiveViewSession::STATUS_PENDING,
                'admin_ip' => $clientIp,
                'admin_user_agent' => $clientUserAgent,
            ]);

            $this->queueSignal(
                companyId: (int) $worker->company_id,
                fromUserId: (int) $clientUser->id,
                toUserId: (int) $worker->id,
                signalType: 'live-view-request',
                payload: [
                    'session_id' => $session->id,
                    'admin_id' => $clientUser->id,
                    'admin_name' => $clientUser->name,
                    'admin_type' => 'client',
                ],
                sessionId: $session->id,
                fromType: 'client',
                toType: 'user'
            );

            $this->logAudit(
                companyId: (int) $worker->company_id,
                action: CompanyHistory::ACTION_LIVE_VIEW_STARTED,
                description: "Client {$clientUser->name} started live viewing {$worker->name}'s screen.",
                changedBy: null,
                context: [
                    'session_id' => $session->id,
                    'admin_id' => $clientUser->id,
                    'admin_name' => $clientUser->name,
                    'admin_type' => 'client',
                    'worker_id' => $worker->id,
                    'worker_name' => $worker->name,
                ]
            );

            return $session;
        });
    }

    public function markSessionConnecting(LiveViewSession $session, ?string $workerIp = null): LiveViewSession
    {
        $session->update([
            'status' => LiveViewSession::STATUS_CONNECTING,
            'started_at' => $session->started_at ?? now(),
            'worker_ip' => $workerIp ?? $session->worker_ip,
        ]);

        return $session->fresh();
    }

    public function markSessionActive(LiveViewSession $session): LiveViewSession
    {
        $session->update([
            'status' => LiveViewSession::STATUS_ACTIVE,
            'started_at' => $session->started_at ?? now(),
        ]);

        return $session->fresh();
    }

    public function endSession(LiveViewSession $session, ?string $reason = null, ?string $failureReason = null): LiveViewSession
    {
        if (in_array($session->status, [LiveViewSession::STATUS_ENDED, LiveViewSession::STATUS_FAILED], true)) {
            return $session;
        }

        $status = $failureReason
            ? LiveViewSession::STATUS_FAILED
            : LiveViewSession::STATUS_ENDED;

        $session->update([
            'status' => $status,
            'ended_at' => now(),
            'failure_reason' => $failureReason,
            'metadata' => array_merge($session->metadata ?? [], array_filter([
                'end_reason' => $reason,
            ])),
        ]);

        $this->queueSignal(
            companyId: (int) $session->company_id,
            fromUserId: (int) $session->admin_id,
            toUserId: (int) $session->worker_id,
            signalType: 'live-view-end',
            payload: [
                'session_id' => $session->id,
                'reason' => $reason,
            ],
            sessionId: $session->id,
            fromType: $session->admin_type,
            toType: 'user'
        );

        $this->queueSignal(
            companyId: (int) $session->company_id,
            fromUserId: (int) $session->worker_id,
            toUserId: (int) $session->admin_id,
            signalType: 'live-view-end',
            payload: [
                'session_id' => $session->id,
                'reason' => $reason,
            ],
            sessionId: $session->id,
            fromType: 'user',
            toType: $session->admin_type
        );

        if ($session->admin_type === 'client') {
            $session->loadMissing(['adminClientUser:id,name', 'worker:id,name']);
        } else {
            $session->loadMissing(['admin:id,name', 'worker:id,name']);
        }

        $this->logAudit(
            companyId: (int) $session->company_id,
            action: CompanyHistory::ACTION_LIVE_VIEW_ENDED,
            description: ($session->adminDisplayName() ?? 'Admin').' ended live viewing '.($session->worker?->name ?? 'employee').' ('.($reason ?? 'ended').').',
            changedBy: $session->admin_type === 'client' ? null : (int) $session->admin_id,
            context: [
                'session_id' => $session->id,
                'admin_id' => $session->admin_id,
                'admin_type' => $session->admin_type,
                'worker_id' => $session->worker_id,
                'reason' => $reason,
                'failure_reason' => $failureReason,
            ]
        );

        return $session->fresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function queueSignal(
        int $companyId,
        int $fromUserId,
        int $toUserId,
        string $signalType,
        array $payload,
        ?int $sessionId = null,
        string $fromType = 'user',
        string $toType = 'user'
    ): WebrtcSignal {
        $signal = WebrtcSignal::create([
            'company_id' => $companyId,
            'live_view_session_id' => $sessionId,
            'from_user_id' => $fromUserId,
            'from_type' => $fromType,
            'to_user_id' => $toUserId,
            'to_type' => $toType,
            'signal_type' => $signalType,
            'payload' => $payload,
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->dispatchRealtimeSignal($signal);

        return $signal;
    }

    /**
     * @return array<string, mixed>
     */
    public function employeeMonitoringStatusPayload(int $employeeId): array
    {
        $maps = $this->employeeLiveStatusMaps([$employeeId]);
        $isClockedIn = isset($maps['clocked_in'][$employeeId]);
        $isRecordingSession = isset($maps['recording'][$employeeId]);
        $liveAvailable = isset($maps['live'][$employeeId]);

        return [
            'id' => $employeeId,
            'status' => $isClockedIn ? 'active' : 'inactive',
            'is_clocked_in' => $isClockedIn,
            'is_recording_session' => $isRecordingSession,
            'live_available' => $liveAvailable,
        ];
    }

    public function broadcastEmployeeMonitoringStatus(int $employeeId): void
    {
        if (! $this->realtimeEnabled()) {
            return;
        }

        $employee = User::query()->find($employeeId);
        if (! $employee?->company_id) {
            return;
        }

        try {
            broadcast(new EmployeeMonitoringStatusChanged(
                (int) $employee->company_id,
                $this->employeeMonitoringStatusPayload($employeeId)
            ));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    protected function realtimeEnabled(): bool
    {
        return config('broadcasting.default') === 'reverb'
            && filled(config('broadcasting.connections.reverb.key'));
    }

    protected function dispatchRealtimeSignal(WebrtcSignal $signal): void
    {
        if (! $this->realtimeEnabled()) {
            return;
        }

        $notification = [
            'id' => $signal->id,
            'session_id' => $signal->live_view_session_id,
            'from_user_id' => $signal->from_user_id,
            'signal_type' => $signal->signal_type,
        ];

        if ($this->shouldDeferSignalPayloadForBroadcast($signal)) {
            $notification['payload_deferred'] = true;
        } else {
            $notification['payload'] = $signal->payload;
        }

        try {
            broadcast(new LiveViewSignalReceived(
                (int) $signal->to_user_id,
                $notification
            ));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    protected function shouldDeferSignalPayloadForBroadcast(WebrtcSignal $signal): bool
    {
        return in_array($signal->signal_type, ['offer', 'answer', 'ice-candidate'], true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function pullSignals(User $user, ?int $sessionId = null, ?int $signalId = null): array
    {
        return $this->pullSignalsForPrincipal('user', (int) $user->id, $sessionId, $signalId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function pullSignalsForClient(ClientUser $clientUser, ?int $sessionId = null, ?int $signalId = null): array
    {
        return $this->pullSignalsForPrincipal('client', (int) $clientUser->id, $sessionId, $signalId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pullSignalsForPrincipal(string $type, int $id, ?int $sessionId = null, ?int $signalId = null): array
    {
        $query = WebrtcSignal::query()
            ->where('to_user_id', $id)
            ->where('to_type', $type)
            ->whereNull('consumed_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderBy('id');

        if ($sessionId) {
            $query->where('live_view_session_id', $sessionId);
        }

        if ($signalId !== null) {
            $query->where('id', $signalId);
        }

        $signals = $query->limit($signalId !== null ? 1 : 50)->get();

        if ($signals->isEmpty()) {
            return [];
        }

        WebrtcSignal::query()
            ->whereIn('id', $signals->pluck('id'))
            ->update(['consumed_at' => now()]);

        return $signals->map(fn (WebrtcSignal $signal) => [
            'id' => $signal->id,
            'session_id' => $signal->live_view_session_id,
            'from_user_id' => $signal->from_user_id,
            'from_type' => $signal->from_type,
            'signal_type' => $signal->signal_type,
            'payload' => $signal->payload,
        ])->all();
    }

    public function userCanAccessSession(User $user, LiveViewSession $session): bool
    {
        if ((int) $session->company_id !== (int) $user->company_id) {
            return false;
        }

        if ((int) $user->id === (int) $session->worker_id) {
            return true;
        }

        return $session->admin_type === 'user' && (int) $user->id === (int) $session->admin_id;
    }

    public function clientCanAccessSession(ClientUser $clientUser, LiveViewSession $session): bool
    {
        if ($session->admin_type !== 'client') {
            return false;
        }

        if ((int) $session->admin_id !== (int) $clientUser->id) {
            return false;
        }

        return (int) $session->company_id === (int) ($clientUser->client?->company_id ?? 0);
    }

    public function userCanParticipateInSignaling(User $user, ?LiveViewSession $session = null): bool
    {
        if ($session) {
            return $this->userCanAccessSession($user, $session);
        }

        return $user->hasPermission('view_live_screen')
            || $this->hasActiveRecordingSession($user->id)
            || $this->isWorkerClockedIn($user->id);
    }

    public function listSessionsForCompany(int $companyId, int $perPage = 10, int $page = 1): LengthAwarePaginator
    {
        return LiveViewSession::query()
            ->where('company_id', $companyId)
            ->with([
                'admin:id,name,email',
                'worker:id,name,email',
            ])
            ->latest('id')
            ->paginate(perPage: $perPage, page: $page);
    }

    public function sendChatMessage(LiveViewSession $session, User $sender, string $body): LiveViewChatMessage
    {
        $message = LiveViewChatMessage::create([
            'live_view_session_id' => $session->id,
            'company_id' => $session->company_id,
            'sender_id' => $sender->id,
            'body' => $body,
        ]);

        $recipientId = (int) $sender->id === (int) $session->admin_id
            ? (int) $session->worker_id
            : (int) $session->admin_id;

        $message->loadMissing('sender:id,name');

        $this->queueSignal(
            companyId: (int) $session->company_id,
            fromUserId: (int) $sender->id,
            toUserId: $recipientId,
            signalType: 'chat-message',
            payload: [
                'session_id' => $session->id,
                'message_id' => $message->id,
                'body' => $message->body,
                'sender_id' => $message->sender_id,
                'sender_name' => $message->sender?->name,
                'sent_at' => $message->created_at?->toIso8601String(),
            ],
            sessionId: $session->id
        );

        if ($this->realtimeEnabled()) {
            broadcast(new LiveViewChatMessageReceived(
                (int) $session->id,
                [
                    'id' => $message->id,
                    'body' => $message->body,
                    'sender_id' => $message->sender_id,
                    'sender_name' => $message->sender?->name,
                    'sent_at' => $message->created_at?->toIso8601String(),
                ]
            ));
        }

        return $message;
    }

    /**
     * @return LengthAwarePaginator<int, LiveViewChatMessage>
     */
    public function listChatMessages(LiveViewSession $session, int $perPage = 50, int $page = 1, ?int $sinceId = null): LengthAwarePaginator
    {
        $query = LiveViewChatMessage::query()
            ->where('live_view_session_id', $session->id)
            ->with('sender:id,name')
            ->orderBy('id');

        if ($sinceId !== null && $sinceId > 0) {
            $query->where('id', '>', $sinceId);
        }

        return $query->paginate(perPage: $perPage, page: $page);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logAudit(int $companyId, string $action, string $description, ?int $changedBy, array $context): void
    {
        $company = Company::query()->find($companyId);
        if (! $company) {
            return;
        }

        CompanyHistory::log(
            company: $company,
            action: $action,
            oldValue: null,
            newValue: $context,
            description: $description,
            changedBy: $changedBy
        );
    }

    private function endActiveSessionsForPair(string $adminType, int $adminId, int $workerId, string $reason): void
    {
        LiveViewSession::query()
            ->where('admin_id', $adminId)
            ->where('admin_type', $adminType)
            ->where('worker_id', $workerId)
            ->whereIn('status', [
                LiveViewSession::STATUS_PENDING,
                LiveViewSession::STATUS_CONNECTING,
                LiveViewSession::STATUS_ACTIVE,
            ])
            ->get()
            ->each(fn (LiveViewSession $session) => $this->endSession($session, $reason));
    }

    private function consumePendingSignalsForPair(string $adminType, int $adminId, int $workerId): void
    {
        WebrtcSignal::query()
            ->whereNull('consumed_at')
            ->where(function ($query) use ($adminType, $adminId, $workerId) {
                $query->where(function ($query) use ($adminType, $adminId, $workerId) {
                    $query->where('from_user_id', $adminId)
                        ->where('from_type', $adminType)
                        ->where('to_user_id', $workerId)
                        ->where('to_type', 'user');
                })->orWhere(function ($query) use ($adminType, $adminId, $workerId) {
                    $query->where('from_user_id', $workerId)
                        ->where('from_type', 'user')
                        ->where('to_user_id', $adminId)
                        ->where('to_type', $adminType);
                });
            })
            ->update(['consumed_at' => now()]);
    }

    private function heartbeatKey(int $workerId): string
    {
        return "live_view:heartbeat:{$workerId}";
    }

    private function eligibilityKey(int $workerId): string
    {
        return "live_view:eligible:{$workerId}";
    }

    private function safeCacheGet(string $key, mixed $default = null): mixed
    {
        try {
            return Cache::get($key, $default);
        } catch (\Throwable $exception) {
            report($exception);

            return $default;
        }
    }

    private function safeCachePut(string $key, mixed $value, int $ttlSeconds): void
    {
        try {
            Cache::put($key, $value, $ttlSeconds);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function safeCacheForget(string $key): void
    {
        try {
            Cache::forget($key);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function safeCacheRemember(string $key, int $ttlSeconds, callable $callback): mixed
    {
        try {
            return Cache::remember($key, $ttlSeconds, $callback);
        } catch (\Throwable $exception) {
            report($exception);

            return $callback();
        }
    }
}
