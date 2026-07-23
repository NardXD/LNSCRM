<!-- Admin Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <div class="logo-icon" style="background: linear-gradient(135deg, #5f61e6 0%, #7c3aed 100%);">
                <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
            </div>
            <span class="logo-text">Admin Panel</span>
        </div>
        <button class="sidebar-toggle" onclick="toggleSidebar()" id="sidebarToggleBtn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" id="toggleIcon">
                <line x1="18" y1="6" x2="6" y2="18"/>
                <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>
    </div>

    <nav class="nav">
        <div class="nav-section">
            <div class="nav-label">Admin</div>
            <a href="{{ route('admin-control') }}" class="nav-item {{ request()->routeIs('admin-control') && !request()->routeIs('admin.company-management') ? 'active' : '' }}">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="7" height="7"/>
                    <rect x="14" y="3" width="7" height="7"/>
                    <rect x="14" y="14" width="7" height="7"/>
                    <rect x="3" y="14" width="7" height="7"/>
                </svg>
                <span class="nav-text">Admin Dashboard</span>
            </a>
            <a href="{{ route('admin.company-management') }}" class="nav-item {{ request()->routeIs('admin.company-management') ? 'active' : '' }}">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
                <span class="nav-text">Company Management</span>
            </a>
            <a href="{{ route('admin.screen-recording-management') }}" class="nav-item {{ request()->routeIs('admin.screen-recording-management') ? 'active' : '' }}">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="23 7 16 12 23 17 23 7"/>
                    <rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>
                </svg>
                <span class="nav-text">Screen Recordings</span>
            </a>
            <a href="{{ route('admin.api-key-management') }}" class="nav-item {{ request()->routeIs('admin.api-key-management') ? 'active' : '' }}">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>
                </svg>
                <span class="nav-text">API Keys</span>
            </a>
            <a href="{{ route('admin.smtp-settings') }}" class="nav-item {{ request()->routeIs('admin.smtp-settings') ? 'active' : '' }}">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                    <polyline points="22,6 12,13 2,6"/>
                </svg>
                <span class="nav-text">SMTP Settings</span>
            </a>
            <a href="{{ route('admin.ai-settings') }}" class="nav-item {{ request()->routeIs('admin.ai-settings') ? 'active' : '' }}">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="4" y="4" width="16" height="16" rx="2"/>
                    <rect x="9" y="9" width="6" height="6"/>
                    <line x1="9" y1="1" x2="9" y2="4"/>
                    <line x1="15" y1="1" x2="15" y2="4"/>
                    <line x1="9" y1="20" x2="9" y2="23"/>
                    <line x1="15" y1="20" x2="15" y2="23"/>
                    <line x1="20" y1="9" x2="23" y2="9"/>
                    <line x1="20" y1="14" x2="23" y2="14"/>
                    <line x1="1" y1="9" x2="4" y2="9"/>
                    <line x1="1" y1="14" x2="4" y2="14"/>
                </svg>
                <span class="nav-text">Main AI</span>
            </a>
        </div>
    </nav>
</aside>
