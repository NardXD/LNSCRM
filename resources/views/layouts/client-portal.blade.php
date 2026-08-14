<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.favicon')
    <title>@yield('title', 'Client Portal') - CRM</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #fafafa;
            --bg-card: #ffffff;
            --accent: #10b981;
            --accent-hover: #059669;
            --accent-light: #ecfdf5;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --text-muted: #9ca3af;
            --border: #e5e7eb;
            --header-height: 64px;
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

        .portal-container {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Header */
        .portal-header {
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
            height: var(--header-height);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .logo-icon {
            width: 36px;
            height: 36px;
            background: var(--accent);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .logo-icon svg {
            width: 20px;
            height: 20px;
        }

        .logo-text {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .portal-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            background: var(--accent-light);
            color: var(--accent);
            font-size: 0.6875rem;
            font-weight: 500;
            padding: 0.1875rem 0.5rem;
            border-radius: 9999px;
        }

        .nav-tabs {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            margin-left: 2rem;
        }

        .nav-tab {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.15s;
        }

        .nav-tab:hover {
            background: var(--bg-primary);
            color: var(--text-primary);
        }

        .nav-tab.active {
            background: var(--accent-light);
            color: var(--accent);
        }

        .nav-tab svg {
            width: 18px;
            height: 18px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.375rem 0.75rem;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.15s;
        }

        .user-menu:hover {
            background: var(--bg-primary);
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            background: var(--accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--text-primary);
        }

        .user-client {
            font-size: 0.6875rem;
            color: var(--text-muted);
        }

        .logout-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.5rem 0.875rem;
            background: var(--bg-primary);
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.15s;
            text-decoration: none;
        }

        .logout-btn:hover {
            background: #fef2f2;
            border-color: #fecaca;
            color: #dc2626;
        }

        .logout-btn svg {
            width: 16px;
            height: 16px;
        }

        /* Main Content */
        .portal-content {
            flex: 1;
            padding: 1.5rem;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }

        .page-header {
            margin-bottom: 1.5rem;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .page-subtitle {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .portal-header {
                padding: 0 1rem;
                flex-wrap: wrap;
                height: auto;
                padding-top: 0.75rem;
                padding-bottom: 0.75rem;
                gap: 0.75rem;
            }

            .header-left {
                flex-wrap: wrap;
                width: 100%;
                justify-content: space-between;
            }

            .nav-tabs {
                margin-left: 0;
                width: 100%;
                order: 3;
            }

            .nav-tab span {
                display: none;
            }

            .portal-content {
                padding: 1rem;
            }

            .user-info {
                display: none;
            }

            .logo-text {
                display: none;
            }
        }
    </style>
    @stack('styles')
</head>
<body>
    @php
        $clientUser = Auth::guard('client')->user();
        $company = $clientUser?->client?->company;
        $companyLogo = $company ? public_media_url($company->logo) : null;
        $companyName = $company ? $company->name : 'Client Portal';
    @endphp

    <div class="portal-container">
        <!-- Header -->
        <header class="portal-header">
            <div class="header-left">
                <div class="logo-container">
                    <div class="logo-icon">
                        @if($companyLogo)
                            <img src="{{ $companyLogo }}" alt="{{ $companyName }}" style="width: 36px; height: 36px; object-fit: contain;">
                        @else
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                        @endif
                    </div>
                    <span class="logo-text">{{ $companyName }}</span>
                    <span class="portal-badge">Client Portal</span>
                </div>

                <nav class="nav-tabs">
                    <a href="{{ route('client.portal.dashboard') }}" class="nav-tab {{ request()->routeIs('client.portal.dashboard') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                        <span>Employees</span>
                    </a>
                    <a href="{{ route('client.portal.projects') }}" class="nav-tab {{ request()->routeIs('client.portal.projects') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                        </svg>
                        <span>Projects</span>
                    </a>
                    <a href="{{ route('client.portal.billing') }}" class="nav-tab {{ request()->routeIs('client.portal.billing') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                            <line x1="1" y1="10" x2="23" y2="10"/>
                        </svg>
                        <span>Billing & Payments</span>
                    </a>
                    <a href="{{ route('client.portal.documents') }}" class="nav-tab {{ request()->routeIs('client.portal.documents') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                        </svg>
                        <span>Documents</span>
                    </a>
                </nav>
            </div>
            <div class="header-right">
                <div class="user-menu">
                    <div class="user-avatar">
                        {{ strtoupper(substr($clientUser?->name ?? 'U', 0, 1)) }}
                    </div>
                    <div class="user-info">
                        <span class="user-name">{{ $clientUser?->name ?? 'User' }}</span>
                        <span class="user-client">{{ $clientUser?->client?->name ?? 'Client' }}</span>
                    </div>
                </div>
                <form action="{{ route('client.logout') }}" method="POST" style="display: inline;">
                    @csrf
                    <button type="submit" class="logout-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                            <polyline points="16 17 21 12 16 7"/>
                            <line x1="21" y1="12" x2="9" y2="12"/>
                        </svg>
                        Logout
                    </button>
                </form>
            </div>
        </header>

        <!-- Main Content -->
        <main class="portal-content">
            @yield('content')
        </main>
    </div>

    @stack('scripts')
</body>
</html>
