@auth
@php
    $reverbEnabled = config('broadcasting.default') === 'reverb'
        && filled(config('broadcasting.connections.reverb.key'));
    $clientConfig = config('broadcasting.connections.reverb.client', []);
    $customClientHost = filled($clientConfig['host'] ?? null);
@endphp
@if ($reverbEnabled)
    <script>
        window.__liveViewUserId = @json(auth()->id());
        window.__companyId = @json(auth()->user()->company_id);
        window.__reverbConfig = {
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
        };
    </script>
    @vite(['resources/js/realtime.js'])
@endif
@endauth
