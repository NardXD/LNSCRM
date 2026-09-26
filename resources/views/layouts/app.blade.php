<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.favicon')
    <title>@yield('title', 'Dashboard') - CRM</title>
    @vite(['resources/css/dashboard.css'])
    @stack('styles')
</head>
<body>
    <script>
        window.__crmFlags = {
            phone: @json((bool) auth()->user()?->hasPermission('view_phone_system')),
        };
    </script>
    <div class="dashboard-container">
        <!-- Mobile Overlay -->
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeMobileSidebar()"></div>

        @if(request()->routeIs('admin-control') || request()->routeIs('admin.*'))
            @include('partials.admin-sidebar')
        @else
            @include('partials.sidebar')
        @endif
        
        <div class="main-content">
            @include('partials.header')

            @if(session('impersonator_id'))
                @include('partials.impersonation-banner')
            @endif
            
            <div class="content">
                @if(session('error'))
                    <div class="flash-alert flash-alert-error" role="alert">{{ session('error') }}</div>
                @endif
                @if(session('success'))
                    <div class="flash-alert flash-alert-success" role="alert">{{ session('success') }}</div>
                @endif
                @yield('content')
            </div>
        </div>
    </div>

    @if(auth()->user()?->hasPermission('view_phone_system'))
        @include('partials.inbound-call-banner')
    @endif

    @include('partials.dashboard-scripts')
    @include('partials.realtime-scripts')

    @if(auth()->user()?->hasPermission('view_phone_system'))
        {{-- Classic script so browser calling works even if the Vite module is slow or missing on production. --}}
        <script src="{{ asset('vendor/twilio-voice.min.js') }}" defer></script>
        @include('partials.inbound-call-listener')
    @endif

    @vite(['resources/js/app.js'])

    @if(auth()->user()?->hasPermission('view_phone_system'))
    <script>
        window.addEventListener('load', function() {
            if (typeof window.handleIncomingCall === 'undefined') {
                console.error('handleIncomingCall still undefined - defining fallback');
                window.handleIncomingCall = function(call) {
                    const callerNumber = call.parameters?.From || call.parameters?.Caller || call.from || 'Unknown';
                    window.globalActiveCall = call;
                    const notification = document.getElementById('inboundCallNotification');
                    const numberElement = document.getElementById('incomingCallNumber');
                    if (notification && numberElement) {
                        numberElement.textContent = callerNumber;
                        notification.style.display = 'block';
                        notification.style.visibility = 'visible';
                        notification.style.opacity = '1';
                    } else {
                        alert('Incoming call from: ' + callerNumber);
                    }
                };
            }
        });
    </script>
    @endif
    
    @stack('scripts')
</body>
</html>
