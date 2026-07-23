<?php

use App\Models\LiveViewSession;
use App\Models\User;
use App\Services\LiveViewService;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('company.{companyId}.monitoring', function (User $user, int $companyId) {
    return (int) $user->company_id === (int) $companyId
        && $user->hasPermission('view_employee_monitoring');
});

Broadcast::channel('user.{userId}.live-view', function (User $user, int $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('live-view-session.{sessionId}', function (User $user, int $sessionId) {
    $session = LiveViewSession::query()->find($sessionId);

    if (! $session) {
        return false;
    }

    return app(LiveViewService::class)->userCanAccessSession($user, $session);
});
