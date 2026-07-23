<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Live view heartbeat
    |--------------------------------------------------------------------------
    */
    'heartbeat_ttl_seconds' => (int) env('LIVE_VIEW_HEARTBEAT_TTL', 45),

    /*
    |--------------------------------------------------------------------------
    | Stale session / signal cleanup
    |--------------------------------------------------------------------------
    */
    'stale_session_minutes' => (int) env('LIVE_VIEW_STALE_SESSION_MINUTES', 10),
    'signal_retention_days' => (int) env('LIVE_VIEW_SIGNAL_RETENTION_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | WebRTC ICE servers (STUN + TURN)
    |--------------------------------------------------------------------------
    | TURN is required for admins and employees on different networks.
    | Use a provider (Metered, Twilio NTS, Xirsys) or self-host coturn.
    */
    'stun_urls' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('LIVE_VIEW_STUN_URLS', 'stun:stun.l.google.com:19302'))
    ))),

    'turn_urls' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('LIVE_VIEW_TURN_URLS', ''))
    ))),

    'turn_username' => env('LIVE_VIEW_TURN_USERNAME'),
    'turn_credential' => env('LIVE_VIEW_TURN_CREDENTIAL'),

    /*
    |--------------------------------------------------------------------------
    | Connection timing (milliseconds, client-side hints)
    |--------------------------------------------------------------------------
    | ICE gathering uses trickle ICE; only a short wait collects host candidates.
    */
    'ice_gathering_timeout_ms' => (int) env('LIVE_VIEW_ICE_GATHERING_TIMEOUT_MS', 500),

    'signal_poll_connect_interval_ms' => (int) env('LIVE_VIEW_SIGNAL_POLL_CONNECT_INTERVAL_MS', 200),

    /*
    |--------------------------------------------------------------------------
    | Client polling intervals (milliseconds)
    |--------------------------------------------------------------------------
    | Workers poll for WebRTC signals while clocked in with an active recording.
    | Use slower idle intervals when no admin is watching to reduce server load.
    | Admin connect polling uses signal_poll_connect_interval_ms briefly, then active.
    */
    'signal_poll_active_interval_ms' => (int) env('LIVE_VIEW_SIGNAL_POLL_ACTIVE_MS', 1500),
    'signal_poll_idle_interval_ms' => (int) env('LIVE_VIEW_SIGNAL_POLL_IDLE_MS', 10000),
    'heartbeat_interval_ms' => (int) env('LIVE_VIEW_HEARTBEAT_INTERVAL_MS', 30000),
    'employee_monitoring_poll_interval_ms' => (int) env('LIVE_VIEW_EMPLOYEE_MONITORING_POLL_MS', 30000),

];
