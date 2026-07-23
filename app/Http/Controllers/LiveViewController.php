<?php

namespace App\Http\Controllers;

use App\Models\LiveViewSession;
use App\Models\User;
use App\Services\LiveViewIceConfigService;
use App\Services\LiveViewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class LiveViewController extends Controller
{
    public function __construct(
        protected LiveViewService $liveView,
        protected LiveViewIceConfigService $iceConfig
    ) {}

    public function iceConfig(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'ice_servers' => $this->iceConfig->iceServers(),
            'turn_configured' => $this->iceConfig->turnConfigured(),
            'ice_gathering_timeout_ms' => (int) config('live-view.ice_gathering_timeout_ms', 500),
            'signal_poll_active_interval_ms' => (int) config('live-view.signal_poll_active_interval_ms', 1500),
            'signal_poll_idle_interval_ms' => (int) config('live-view.signal_poll_idle_interval_ms', 5000),
            'signal_poll_connect_interval_ms' => (int) config('live-view.signal_poll_connect_interval_ms', 200),
            'heartbeat_interval_ms' => (int) config('live-view.heartbeat_interval_ms', 30000),
        ]);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'stream_active' => ['nullable', 'boolean'],
        ]);

        $streamActive = (bool) ($validated['stream_active'] ?? true);

        if (! $this->liveView->isWorkerEligibleForLiveView($user->id)) {
            $this->liveView->clearHeartbeat($user->id);

            return response()->json([
                'success' => false,
                'message' => 'You must be clocked in with an active recording session to enable live viewing.',
            ], 422);
        }

        if (! $streamActive) {
            $this->liveView->clearHeartbeat($user->id);

            return response()->json(['success' => true, 'live_available' => false]);
        }

        $this->liveView->heartbeat($user, $request->ip(), true);

        return response()->json(['success' => true, 'live_available' => true]);
    }

    public function clearHeartbeat(Request $request): JsonResponse
    {
        $this->liveView->clearHeartbeat((int) Auth::id());

        return response()->json(['success' => true]);
    }

    public function pullSignals(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'session_id' => ['nullable', 'integer'],
            'signal_id' => ['nullable', 'integer', 'min:1'],
        ]);

        if (! empty($validated['session_id'])) {
            $session = $this->findAccessibleSession($user, (int) $validated['session_id']);
            if (! $session) {
                return response()->json(['success' => false, 'message' => 'Live view session not found.'], 404);
            }
        } elseif (! $this->liveView->userCanParticipateInSignalingCached($user)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        return response()->json([
            'success' => true,
            'signals' => $this->liveView->pullSignals(
                $user,
                $validated['session_id'] ?? null,
                isset($validated['signal_id']) ? (int) $validated['signal_id'] : null
            ),
        ]);
    }

    public function sendSignal(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'to_user_id' => ['required', 'integer'],
            'session_id' => ['required', 'integer', 'exists:live_view_sessions,id'],
            'signal_type' => ['required', 'string', Rule::in([
                'offer',
                'answer',
                'ice-candidate',
                'live-view-ready',
                'live-view-audio-request',
                'live-view-audio-decline',
                'live-view-audio-end',
                'chat-message',
            ])],
            'payload' => ['required', 'array'],
        ]);

        $session = $this->findAccessibleSession($user, (int) $validated['session_id']);
        if (! $session) {
            return response()->json(['success' => false, 'message' => 'Live view session not found.'], 404);
        }

        $toUserId = (int) $validated['to_user_id'];
        if (! in_array($toUserId, [(int) $session->admin_id, (int) $session->worker_id], true)) {
            return response()->json(['success' => false, 'message' => 'Recipient is not part of this live view session.'], 422);
        }

        // Derive the recipient's type from who is sending, not from matching to_user_id against
        // worker_id/admin_id — those can collide numerically (client_users vs. users id spaces).
        $toType = (int) $user->id === (int) $session->worker_id ? $session->admin_type : 'user';

        if ($validated['signal_type'] === 'live-view-ready' && (int) $session->worker_id === (int) $user->id) {
            $this->liveView->markSessionConnecting($session, $request->ip());
        }

        if ($validated['signal_type'] === 'answer' && (int) $session->admin_id === (int) $user->id) {
            $this->liveView->markSessionActive($session);
        }

        $signal = $this->liveView->queueSignal(
            companyId: (int) $user->company_id,
            fromUserId: (int) $user->id,
            toUserId: $toUserId,
            signalType: $validated['signal_type'],
            payload: $validated['payload'],
            sessionId: $session->id,
            fromType: 'user',
            toType: $toType
        );

        return response()->json([
            'success' => true,
            'signal_id' => $signal->id,
        ]);
    }

    public function startSession(Request $request): JsonResponse
    {
        $admin = Auth::user();

        $validated = $request->validate([
            'worker_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('company_id', $admin->company_id),
            ],
        ]);

        $worker = User::query()
            ->where('company_id', $admin->company_id)
            ->where('id', $validated['worker_id'])
            ->firstOrFail();

        try {
            $session = $this->liveView->createSession(
                $admin,
                $worker,
                $request->ip(),
                $request->userAgent()
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'session' => $this->formatSession($session),
            'turn_configured' => $this->iceConfig->turnConfigured(),
        ]);
    }

    public function listSessions(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 10);
        $page = (int) ($validated['page'] ?? 1);

        $sessions = $this->liveView->listSessionsForCompany((int) $user->company_id, $perPage, $page);

        return response()->json([
            'success' => true,
            'sessions' => $sessions->getCollection()
                ->map(fn (LiveViewSession $session) => $this->formatSessionWithUsers($session))
                ->values(),
            'pagination' => [
                'current_page' => $sessions->currentPage(),
                'last_page' => $sessions->lastPage(),
                'per_page' => $sessions->perPage(),
                'total' => $sessions->total(),
            ],
        ]);
    }

    public function listMessages(Request $request, LiveViewSession $liveViewSession): JsonResponse
    {
        $user = Auth::user();

        if (! $this->liveView->userCanAccessSession($user, $liveViewSession)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'since_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $messages = $this->liveView->listChatMessages(
            $liveViewSession,
            (int) ($validated['per_page'] ?? 50),
            (int) ($validated['page'] ?? 1),
            isset($validated['since_id']) ? (int) $validated['since_id'] : null
        );

        return response()->json([
            'success' => true,
            'messages' => $messages->getCollection()->map(fn ($message) => [
                'id' => $message->id,
                'body' => $message->body,
                'sender_id' => $message->sender_id,
                'sender_name' => $message->sender?->name,
                'sent_at' => $message->created_at?->toIso8601String(),
                'is_mine' => (int) $message->sender_id === (int) $user->id,
            ])->values(),
            'pagination' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
        ]);
    }

    public function sendMessage(Request $request, LiveViewSession $liveViewSession): JsonResponse
    {
        $user = Auth::user();

        if (! $this->liveView->userCanAccessSession($user, $liveViewSession)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        if (! in_array($liveViewSession->status, [
            LiveViewSession::STATUS_PENDING,
            LiveViewSession::STATUS_CONNECTING,
            LiveViewSession::STATUS_ACTIVE,
        ], true)) {
            return response()->json(['success' => false, 'message' => 'Live view session is not active.'], 422);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $message = $this->liveView->sendChatMessage(
            $liveViewSession,
            $user,
            trim($validated['body'])
        );

        $message->loadMissing('sender:id,name');

        return response()->json([
            'success' => true,
            'message' => [
                'id' => $message->id,
                'body' => $message->body,
                'sender_id' => $message->sender_id,
                'sender_name' => $message->sender?->name,
                'sent_at' => $message->created_at?->toIso8601String(),
                'is_mine' => true,
            ],
        ]);
    }

    public function endSession(Request $request, LiveViewSession $liveViewSession): JsonResponse
    {
        $user = Auth::user();

        if (! $this->liveView->userCanAccessSession($user, $liveViewSession)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
            'failure_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $session = $this->liveView->endSession(
            $liveViewSession,
            $validated['reason'] ?? 'ended',
            $validated['failure_reason'] ?? null
        );

        return response()->json([
            'success' => true,
            'session' => $this->formatSession($session),
        ]);
    }

    public function getSession(LiveViewSession $liveViewSession): JsonResponse
    {
        $user = Auth::user();

        if (! $this->liveView->userCanAccessSession($user, $liveViewSession)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        return response()->json([
            'success' => true,
            'session' => $this->formatSessionWithUsers($liveViewSession),
        ]);
    }

    private function findAccessibleSession(User $user, int $sessionId): ?LiveViewSession
    {
        $session = LiveViewSession::query()
            ->where('id', $sessionId)
            ->where('company_id', $user->company_id)
            ->first();

        if (! $session || ! $this->liveView->userCanAccessSession($user, $session)) {
            return null;
        }

        return $session;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatSession(LiveViewSession $session): array
    {
        return [
            'id' => $session->id,
            'admin_id' => $session->admin_id,
            'admin_type' => $session->admin_type,
            'worker_id' => $session->worker_id,
            'status' => $session->status,
            'started_at' => $session->started_at?->toIso8601String(),
            'ended_at' => $session->ended_at?->toIso8601String(),
            'failure_reason' => $session->failure_reason,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatSessionWithUsers(LiveViewSession $session): array
    {
        $session->loadMissing(['worker:id,name,email']);

        if ($session->admin_type === 'client') {
            $session->loadMissing(['adminClientUser:id,name,email']);
            $admin = $session->adminClientUser ? [
                'id' => $session->adminClientUser->id,
                'name' => $session->adminClientUser->name,
                'email' => $session->adminClientUser->email,
                'type' => 'client',
            ] : null;
        } else {
            $session->loadMissing(['admin:id,name,email']);
            $admin = $session->admin ? [
                'id' => $session->admin->id,
                'name' => $session->admin->name,
                'email' => $session->admin->email,
                'type' => 'user',
            ] : null;
        }

        return array_merge($this->formatSession($session), [
            'admin' => $admin,
            'worker' => $session->worker ? [
                'id' => $session->worker->id,
                'name' => $session->worker->name,
                'email' => $session->worker->email,
            ] : null,
        ]);
    }
}
