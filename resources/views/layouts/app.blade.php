<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.favicon')
    <title>@yield('title', 'Dashboard') - CRM</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @stack('styles')
    <style>
        :root {
            --bg-primary: #fafafa;
            --bg-card: #ffffff;
            --accent: #5f61e6;
            --accent-hover: #4f51d6;
            --accent-light: #f0f0ff;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --text-muted: #9ca3af;
            --border: #e5e7eb;
            --sidebar-bg: #ffffff;
            --sidebar-width: 260px;
            --sidebar-collapsed: 80px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            overflow-x: hidden;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s ease;
            min-height: 100vh;
        }

        .sidebar.collapsed ~ .main-content {
            margin-left: var(--sidebar-collapsed);
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
    </style>
    @include('partials.dashboard-styles')
</head>
<body>
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

    @include('partials.dashboard-scripts')
    @include('partials.realtime-scripts')

    {{-- Classic script so browser calling works even if the Vite module is slow or missing on production. --}}
    <script src="{{ asset('vendor/twilio-voice.min.js') }}" defer></script>
    
    @vite(['resources/js/app.js'])
    
    <!-- FALLBACK: Direct script to verify execution -->
    <script>
        // This should execute AFTER Vite bundle loads
        window.addEventListener('load', function() {
            // console.log('📋 Page fully loaded');
            // console.log('🔍 Checking functions after load:');
            // console.log('  - handleIncomingCall:', typeof window.handleIncomingCall);
            // console.log('  - answerIncomingCall:', typeof window.answerIncomingCall);
            // console.log('  - testIncomingCallNotification:', typeof window.testIncomingCallNotification);
            
            // If handleIncomingCall still doesn't exist, define a minimal version
            if (typeof window.handleIncomingCall === 'undefined') {
                console.error('❌ handleIncomingCall still undefined - defining fallback');
                window.handleIncomingCall = function(call) {
                    // console.log('🔔 Fallback handleIncomingCall called');
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
                console.log('✅ Fallback handleIncomingCall defined');
            }
        });
    </script>
    
    <!-- DEBUG: Verify Vite bundle loaded -->
    {{-- <script>
        console.log('📦 Layout: Vite bundle should be loaded now');
        console.log('🔍 Checking if twilio-global functions exist...');
        
        // Check immediately
        console.log('🔍 IMMEDIATE - window.handleIncomingCall exists?', typeof window.handleIncomingCall);
        console.log('🔍 IMMEDIATE - window.answerIncomingCall exists?', typeof window.answerIncomingCall);
        console.log('🔍 IMMEDIATE - testIncomingCallNotification exists?', typeof window.testIncomingCallNotification);
        
        // Check after delays
        setTimeout(() => {
            console.log('🔍 After 500ms - window.handleIncomingCall exists?', typeof window.handleIncomingCall);
            console.log('🔍 After 500ms - window.answerIncomingCall exists?', typeof window.answerIncomingCall);
            console.log('🔍 After 500ms - testIncomingCallNotification exists?', typeof window.testIncomingCallNotification);
        }, 500);
        
        setTimeout(() => {
            console.log('🔍 After 2s - window.handleIncomingCall exists?', typeof window.handleIncomingCall);
            console.log('🔍 After 2s - window.answerIncomingCall exists?', typeof window.answerIncomingCall);
            console.log('🔍 After 2s - testIncomingCallNotification exists?', typeof window.testIncomingCallNotification);
            console.log('🔍 After 2s - Checking if app.js logs appeared...');
        }, 2000);
    </script> --}}
    
    @stack('scripts')
</body>
</html>

