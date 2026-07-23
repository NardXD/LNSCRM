<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ClientUser;
use App\Models\LiveViewSession;
use App\Models\User;
use App\Services\LiveViewIceConfigService;
use App\Services\LiveViewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ClientLiveViewController extends Controller
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

    public function startSession(Request $request): JsonResponse
    {
        /** @var ClientUser $clientUser */
        $clientUser = Auth::guard('client')->user();
        $client = $clientUser->client;

        if (! $client) {
            return response()->json(['success' => false, 'message' => 'Client not found.'], 404);
        }

        $validated = $request->validate([
            'worker_id' => ['required', 'integer'],
        ]);

        $worker = User::query()
            ->where('company_id', $client->company_id)
            ->where('id', $validated['worker_id'])
            ->first();

        if (! $worker) {
            return response()->json(['success' => false, 'message' => 'Employee not found.'], 404);
        }

        try {
            $session = $this->liveView->createSessionForClient(
                $clientUser,
                $worker,
                $request->ip(),
                $request->userAgent()
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'session' => [
                'id' => $session->id,
                'admin_id' => $session->admin_id,
                'worker_id' => $session->worker_id,
                'status' => $session->status,
            ],
            'turn_configured' => $this->iceConfig->turnConfigured(),
        ]);
    }

    public function endSession(Request $request, LiveViewSession $liveViewSession): JsonResponse
    {
        /** @var ClientUser $clientUser */
        $clientUser = Auth::guard('client')->user();

        if (! $this->liveView->clientCanAccessSession($clientUser, $liveViewSession)) {
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
            'session' => [
                'id' => $session->id,
                'status' => $session->status,
            ],
        ]);
    }

    public function pullSignals(Request $request): JsonResponse
    {
        /** @var ClientUser $clientUser */
        $clientUser = Auth::guard('client')->user();

        $validated = $request->validate([
            'session_id' => ['nullable', 'integer'],
            'signal_id' => ['nullable', 'integer', 'min:1'],
        ]);

        if (empty($validated['session_id'])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $session = LiveViewSession::query()->find((int) $validated['session_id']);
        if (! $session || ! $this->liveView->clientCanAccessSession($clientUser, $session)) {
            return response()->json(['success' => false, 'message' => 'Live view session not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'signals' => $this->liveView->pullSignalsForClient(
                $clientUser,
                (int) $validated['session_id'],
                isset($validated['signal_id']) ? (int) $validated['signal_id'] : null
            ),
        ]);
    }

    public function sendSignal(Request $request): JsonResponse
    {
        /** @var ClientUser $clientUser */
        $clientUser = Auth::guard('client')->user();

        $validated = $request->validate([
            'to_user_id' => ['required', 'integer'],
            'session_id' => ['required', 'integer', 'exists:live_view_sessions,id'],
            'signal_type' => ['required', 'string', Rule::in([
                'answer',
                'ice-candidate',
            ])],
            'payload' => ['required', 'array'],
        ]);

        $session = LiveViewSession::query()->find((int) $validated['session_id']);
        if (! $session || ! $this->liveView->clientCanAccessSession($clientUser, $session)) {
            return response()->json(['success' => false, 'message' => 'Live view session not found.'], 404);
        }

        $toUserId = (int) $validated['to_user_id'];
        if ($toUserId !== (int) $session->worker_id) {
            return response()->json(['success' => false, 'message' => 'Recipient is not part of this live view session.'], 422);
        }

        $signal = $this->liveView->queueSignal(
            companyId: (int) $session->company_id,
            fromUserId: (int) $clientUser->id,
            toUserId: $toUserId,
            signalType: $validated['signal_type'],
            payload: $validated['payload'],
            sessionId: $session->id,
            fromType: 'client',
            toType: 'user'
        );

        return response()->json([
            'success' => true,
            'signal_id' => $signal->id,
        ]);
    }
}
