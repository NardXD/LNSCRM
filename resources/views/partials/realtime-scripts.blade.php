@auth
@php
    $reverbEnabled = config('broadcasting.default') === 'reverb'
        && filled(config('broadcasting.connections.reverb.key'));
    $clientConfig = config('broadcasting.connections.reverb.client', []);
    $customClientHost = filled($clientConfig['host'] ?? null);
@endphp
@if ($reverbEnabled)
<script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.19.0/dist/echo.iife.js"></script>
<script src="{{ asset('js/realtime.js') }}?v={{ filemtime(public_path('js/realtime.js')) }}"></script>
<script>
    window.__liveViewUserId = @json(auth()->id());
    window.__companyId = @json(auth()->user()->company_id);
    LogonRealtime.init({
        key: @json(config('broadcasting.connections.reverb.key')),
        authEndpoint: '/broadcasting/auth',
        useCustomHost: @json($customClientHost),
        @if ($customClientHost)
        host: @json($clientConfig['host']),
        @endif
        @if (filled($clientConfig['port'] ?? null))
        port: @json((int) $clientConfig['port']),
        @endif
        @if (filled($clientConfig['scheme'] ?? null))
        scheme: @json($clientConfig['scheme']),
        @endif
    });
</script>
@endif
@endauth
