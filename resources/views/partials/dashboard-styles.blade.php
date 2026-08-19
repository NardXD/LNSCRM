<style>
    /* Flash alerts (session error/success) */
    .flash-alert {
        padding: 0.75rem 1rem;
        border-radius: 8px;
        margin-bottom: 1rem;
        font-size: 0.875rem;
    }
    .flash-alert-error {
        background: #fef2f2;
        border: 1px solid #ef4444;
        color: #991b1b;
    }
    .flash-alert-success {
        background: #ecfdf5;
        border: 1px solid #10b981;
        color: #065f46;
    }

    /* Sidebar Styles */
    .sidebar {
        width: var(--sidebar-width);
        background: var(--sidebar-bg);
        border-right: 1px solid var(--border);
        position: fixed;
        height: 100vh;
        left: 0;
        top: 0;
        transition: width 0.3s ease;
        z-index: 1000;
        overflow-y: auto;
        overflow-x: hidden;
    }

    .sidebar.collapsed {
        width: var(--sidebar-collapsed);
    }

    .sidebar-header {
        padding: 1.25rem;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .logo {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        white-space: nowrap;
    }

    .logo-icon {
        width: 36px;
        height: 36px;
        background: var(--accent);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        padding: 4px;
    }

    .logo-icon svg {
        width: 20px;
        height: 20px;
        color: white;
    }

    .logo-icon img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        border-radius: 8px;
    }

    .logo-text {
        font-size: 1.125rem;
        font-weight: 700;
        color: var(--text-primary);
        transition: opacity 0.3s;
    }

    .sidebar.collapsed .logo-text {
        opacity: 0;
        width: 0;
        overflow: hidden;
    }

    .sidebar-toggle {
        background: none;
        border: none;
        color: var(--text-secondary);
        cursor: pointer;
        padding: 0.375rem;
        border-radius: 6px;
        transition: all 0.15s;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .sidebar-toggle:hover {
        background: var(--bg-primary);
        color: var(--text-primary);
    }

    .sidebar-toggle svg {
        width: 20px;
        height: 20px;
    }

    /* Hide sidebar toggle on mobile (use header button instead) */
    @media (max-width: 768px) {
        .sidebar-toggle {
            display: none;
        }
    }

    /* Navigation Styles */
    .nav-section {
        padding: 1rem 0;
    }

    .nav-label {
        padding: 0.5rem 1.25rem;
        font-size: 0.6875rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
        transition: opacity 0.3s;
    }

    .sidebar.collapsed .nav-label {
        opacity: 0;
        height: 0;
        padding: 0;
        overflow: hidden;
    }

    .nav-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.625rem 1.25rem;
        color: var(--text-secondary);
        text-decoration: none;
        transition: all 0.15s;
        position: relative;
        white-space: nowrap;
        -webkit-tap-highlight-color: transparent;
    }

    .nav-item:hover {
        background: var(--bg-primary);
        color: var(--accent);
    }

    .nav-item.active {
        background: var(--accent-light);
        color: var(--accent);
    }

    .nav-item.active::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 3px;
        background: var(--accent);
    }

    .nav-icon {
        width: 20px;
        height: 20px;
        flex-shrink: 0;
    }

    .nav-text {
        transition: opacity 0.3s;
    }

    .sidebar.collapsed .nav-text {
        opacity: 0;
        width: 0;
        overflow: hidden;
    }

    /* Submenu Styles */
    .nav-item-parent {
        position: relative;
    }

    .nav-item-parent.active > .nav-item-toggle {
        background: var(--accent-light);
        color: var(--accent);
    }

    .nav-item-parent.active > .nav-item-toggle::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 3px;
        background: var(--accent);
    }

    .nav-item-toggle {
        cursor: pointer;
        user-select: none;
    }

    .nav-arrow {
        width: 16px;
        height: 16px;
        margin-left: auto;
        transition: transform 0.3s ease;
        flex-shrink: 0;
    }

    .nav-item-parent.active > .nav-item-toggle .nav-arrow {
        transform: rotate(180deg);
    }

    .sidebar.collapsed .nav-arrow {
        display: none;
    }

    .nav-submenu {
        background: var(--bg-primary);
        overflow: hidden;
        transition: max-height 0.3s ease, opacity 0.3s ease;
        max-height: 0;
        opacity: 0;
    }

    .nav-item-parent.active > .nav-submenu {
        max-height: 500px;
        opacity: 1;
    }

    .nav-item-parent.active > .nav-submenu[style*="display: block"] {
        max-height: 500px;
        opacity: 1;
    }

    .nav-subitem {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.5rem 1.25rem 0.5rem 3rem;
        color: var(--text-secondary);
        text-decoration: none;
        transition: all 0.15s;
        position: relative;
        white-space: nowrap;
        font-size: 0.875rem;
        -webkit-tap-highlight-color: transparent;
    }

    .nav-subitem:hover {
        background: var(--bg-primary);
        color: var(--accent);
        padding-left: 3.25rem;
    }

    .nav-subitem.active {
        background: var(--accent-light);
        color: var(--accent);
        font-weight: 500;
    }

    .nav-subitem.active::before {
        content: '';
        position: absolute;
        left: 2.5rem;
        top: 50%;
        transform: translateY(-50%);
        width: 4px;
        height: 4px;
        border-radius: 50%;
        background: var(--accent);
    }

    .sidebar.collapsed .nav-submenu {
        display: none !important;
    }

    .sidebar.collapsed .nav-subitem {
        display: none;
    }

    /* Header Styles */
    .header {
        background: var(--bg-card);
        border-bottom: 1px solid var(--border);
        padding: 1rem 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: sticky;
        top: 0;
        z-index: 100;
    }

    .header-left {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .search-box {
        position: relative;
        max-width: 400px;
        width: 100%;
    }

    .search-input {
        width: 100%;
        padding: 0.625rem 0.75rem 0.625rem 2.5rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        background: var(--bg-primary);
        transition: all 0.15s;
    }

    .search-input:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(95, 97, 230, 0.1);
    }

    .search-icon {
        position: absolute;
        left: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
        pointer-events: none;
    }

    .search-icon svg {
        width: 18px;
        height: 18px;
    }

    .header-right {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .header-agent-queue {
        display: flex;
        align-items: center;
        position: relative;
        z-index: 6;
        flex-shrink: 0;
    }

    .header-agent-queue-toggle {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        cursor: pointer;
        user-select: none;
        flex-shrink: 0;
        border: none;
        background: transparent;
        padding: 0.3rem 0.4rem;
        border-radius: 999px;
        font: inherit;
        color: inherit;
    }

    .header-agent-queue-toggle:hover {
        background: var(--bg-primary);
    }

    .header-agent-queue-toggle .agent-queue-toggle-ui {
        pointer-events: none;
    }

    .header-agent-queue-toggle[aria-checked="true"] .agent-queue-toggle-ui {
        background: #16a34a;
    }

    .header-agent-queue-toggle[aria-checked="true"] .agent-queue-toggle-ui::after {
        transform: translateX(18px);
    }

    .header-agent-queue-toggle[aria-checked="true"] .agent-queue-toggle-label {
        color: #166534;
    }

    .header-agent-queue-meta {
        font-size: 0.72rem;
        font-weight: 600;
        color: var(--text-secondary);
        white-space: nowrap;
        line-height: 1.2;
        margin-left: 0.15rem;
        max-width: 22rem;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .header-agent-queue-toggle[aria-checked="true"] ~ .header-agent-queue-meta {
        color: #166534;
    }

    .agent-queue-toggle {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        cursor: pointer;
        user-select: none;
        flex-shrink: 0;
        position: relative;
    }

    .agent-queue-toggle input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .agent-queue-toggle-ui {
        width: 40px;
        height: 22px;
        border-radius: 999px;
        background: #cbd5e1;
        position: relative;
        transition: background 0.15s ease;
        flex-shrink: 0;
    }

    .agent-queue-toggle-ui::after {
        content: '';
        position: absolute;
        top: 2px;
        left: 2px;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: #fff;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.2);
        transition: transform 0.15s ease;
    }

    .agent-queue-toggle input:checked + .agent-queue-toggle-ui {
        background: #16a34a;
    }

    .agent-queue-toggle input:checked + .agent-queue-toggle-ui::after {
        transform: translateX(18px);
    }

    .agent-queue-toggle-label {
        font-size: 0.78rem;
        font-weight: 600;
        min-width: 4.5rem;
        color: var(--text-secondary);
    }

    .agent-queue-toggle input:checked ~ .agent-queue-toggle-label {
        color: #166534;
    }

    @media (max-width: 768px) {
        .header-agent-queue .agent-queue-toggle-label {
            display: none;
        }
    }

    /* Inbound Call Notification — fixed overlay so it is visible on every CRM page */
    .inbound-call-banner-layer {
        position: fixed;
        top: 12px;
        right: 12px;
        z-index: 10050;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 0.75rem;
        pointer-events: none;
    }

    .inbound-call-notification,
    .ongoing-call-notification {
        pointer-events: auto;
    }

    .inbound-call-notification {
        background: linear-gradient(135deg, #5f61e6 0%, #4f51d6 100%);
        border-radius: 12px;
        padding: 0.875rem 1.25rem;
        box-shadow: 0 4px 12px rgba(95, 97, 230, 0.3);
        animation: slideInRight 0.3s ease-out;
        min-width: 320px;
        max-width: 400px;
    }

    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    .call-notification-content {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .call-notification-icon {
        width: 40px;
        height: 40px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0%, 100% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.1);
        }
    }

    .call-notification-icon svg {
        width: 20px;
        height: 20px;
        color: white;
    }

    .call-notification-info {
        flex: 1;
        min-width: 0;
    }

    .call-notification-title {
        font-size: 0.75rem;
        font-weight: 500;
        color: rgba(255, 255, 255, 0.9);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.25rem;
    }

    .call-notification-number {
        font-size: 1rem;
        font-weight: 600;
        color: white;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .call-notification-lead {
        display: none;
        font-size: 0.75rem;
        font-weight: 600;
        color: rgba(255, 255, 255, 0.92);
        margin-top: 0.15rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .channel-assigned {
        font-size: 0.72rem;
        font-weight: 600;
        color: #0b5cab;
        margin-top: 0.15rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .channel-label-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 0.25rem;
        margin-top: 0.25rem;
    }

    .channel-label-chip {
        display: inline-flex;
        align-items: center;
        padding: 0.1rem 0.4rem;
        border-radius: 999px;
        font-size: 0.65rem;
        font-weight: 700;
        line-height: 1.2;
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .call-notification-actions {
        display: flex;
        gap: 0.5rem;
        flex-shrink: 0;
    }

    .call-btn {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        flex-shrink: 0;
    }

    .call-btn svg {
        width: 18px;
        height: 18px;
    }

    .call-btn-answer {
        background: #10b981;
        color: white;
    }

    .call-btn-answer:hover {
        background: #059669;
        transform: scale(1.1);
    }

    .call-btn-decline {
        background: #ef4444;
        color: white;
    }

    .call-btn-decline:hover {
        background: #dc2626;
        transform: scale(1.1);
    }

    /* Ongoing Call Notification */
    .ongoing-call-notification {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        border-radius: 12px;
        padding: 0.875rem 1.25rem;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        animation: slideInRight 0.3s ease-out;
        min-width: 320px;
        max-width: 400px;
    }

    .call-notification-icon.ongoing {
        background: rgba(255, 255, 255, 0.3);
        animation: none;
    }

    .call-duration {
        font-size: 0.875rem;
        font-weight: 500;
        color: rgba(255, 255, 255, 0.9);
        margin-top: 0.25rem;
        font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', 'Consolas', 'source-code-pro', monospace;
    }

    .call-btn-hangup {
        background: #ef4444;
        color: white;
    }

    .call-btn-hangup:hover {
        background: #dc2626;
        transform: scale(1.1);
    }

    .header-icon-btn {
        background: none;
        border: none;
        color: var(--text-secondary);
        cursor: pointer;
        padding: 0.5rem;
        border-radius: 8px;
        transition: all 0.15s;
        position: relative;
    }

    .header-icon-btn:hover {
        background: var(--bg-primary);
        color: var(--text-primary);
    }

    .header-icon-btn svg {
        width: 20px;
        height: 20px;
    }

    .notification-badge {
        position: absolute;
        top: 0.25rem;
        right: 0.25rem;
        min-width: 18px;
        height: 18px;
        padding: 0 5px;
        font-size: 0.7rem;
        font-weight: 600;
        line-height: 18px;
        color: #fff;
        text-align: center;
        background: #ef4444;
        border-radius: 9px;
        border: 2px solid var(--bg-card);
    }

    .notification-badge:empty {
        min-width: 8px;
        width: 8px;
        height: 8px;
        padding: 0;
        font-size: 0;
        border-radius: 50%;
    }

    .header-notifications {
        position: relative;
    }
    .header-notifications-dropdown {
        position: absolute;
        top: calc(100% + 0.45rem);
        right: 0;
        width: min(360px, calc(100vw - 1.5rem));
        background: var(--bg-card, #fff);
        border: 1px solid var(--border-color, #e5e7eb);
        border-radius: 12px;
        box-shadow: 0 16px 40px rgba(15, 23, 42, 0.14);
        z-index: 120;
        overflow: hidden;
    }
    .header-notifications-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.75rem 0.9rem;
        border-bottom: 1px solid var(--border-color, #e5e7eb);
        font-size: 0.84rem;
    }
    .header-notifications-markall {
        border: none;
        background: transparent;
        color: var(--accent, #2563eb);
        font-size: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        padding: 0;
    }
    .header-notifications-list {
        max-height: 360px;
        overflow: auto;
    }
    .header-notifications-empty {
        padding: 1.25rem 1rem;
        text-align: center;
        color: #94a3b8;
        font-size: 0.84rem;
    }
    .header-notification-item {
        display: grid;
        gap: 0.15rem;
        width: 100%;
        text-align: left;
        border: none;
        border-bottom: 1px solid var(--border-color, #eef2f7);
        background: #fff;
        padding: 0.75rem 0.9rem;
        cursor: pointer;
        font: inherit;
        color: inherit;
    }
    .header-notification-item:hover { background: #f8fafc; }
    .header-notification-item.unread { background: #eff6ff; }
    .header-notification-title {
        font-size: 0.82rem;
        font-weight: 600;
        color: #0f172a;
    }
    .header-notification-snippet {
        font-size: 0.76rem;
        color: #64748b;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .header-notification-time {
        font-size: 0.7rem;
        color: #94a3b8;
    }

    /* User Dropdown Styles */
    .user-menu {
        position: relative;
    }

    .user-trigger {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.375rem 0.75rem;
        background: none;
        border: 1px solid var(--border);
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.15s;
        -webkit-tap-highlight-color: transparent;
        touch-action: manipulation;
    }

    .user-trigger:hover {
        background: var(--bg-primary);
        border-color: #d1d5db;
    }

    .user-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: var(--accent);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 0.875rem;
        flex-shrink: 0;
        overflow: hidden;
        position: relative;
    }
    
    .user-avatar[style*="background-image"] {
        background-color: var(--bg-primary);
    }
    
    .user-avatar[style*="background-image"]::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        border-radius: 50%;
        border: 2px solid var(--border);
    }

    .user-info {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
    }

    .user-name {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
    }

    .user-role {
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    .user-arrow {
        color: var(--text-muted);
        transition: transform 0.2s;
    }

    .user-menu.open .user-arrow {
        transform: rotate(180deg);
    }

    .dropdown-menu {
        position: absolute;
        top: calc(100% + 0.5rem);
        right: 0;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        min-width: 220px;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: all 0.2s;
        z-index: 1000;
    }

    .user-menu.open .dropdown-menu {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .dropdown-header {
        padding: 1rem;
        border-bottom: 1px solid var(--border);
    }

    .dropdown-header-name {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
    }

    .dropdown-header-email {
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    .dropdown-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem 1rem;
        color: var(--text-secondary);
        text-decoration: none;
        transition: all 0.15s;
        font-size: 0.875rem;
    }

    .dropdown-item:hover {
        background: var(--bg-primary);
        color: var(--accent);
    }

    .dropdown-item svg {
        width: 18px;
        height: 18px;
        color: var(--text-muted);
    }

    .dropdown-item:hover svg {
        color: var(--accent);
    }

    .dropdown-divider {
        height: 1px;
        background: var(--border);
        margin: 0.5rem 0;
    }

    /* Content Styles */
    .content {
        padding: 2rem;
    }

    .page-header {
        margin-bottom: 2rem;
    }

    .page-title {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }

    .page-subtitle {
        font-size: 0.9375rem;
        color: var(--text-secondary);
    }

    /* Button Template - shared across dashboard pages */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.625rem 1.25rem;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.15s;
        border: 1px solid transparent;
        white-space: nowrap;
        -webkit-tap-highlight-color: transparent;
        line-height: 1.5;
        font-family: inherit;
    }

    .btn-primary {
        background: var(--accent);
        color: white;
        border-color: var(--accent);
    }

    .btn-primary:hover {
        background: var(--accent-hover);
    }

    .btn-secondary,
    .btn-outline {
        background: transparent;
        color: var(--text-secondary);
        border-color: var(--border);
    }

    .btn-secondary:hover,
    .btn-outline:hover {
        background: var(--bg-primary);
        color: var(--text-primary);
    }

    .btn svg {
        width: 18px;
        height: 18px;
        flex-shrink: 0;
    }

    .btn-sm {
        padding: 0.5rem 0.875rem;
        font-size: 0.8125rem;
        border-radius: 6px;
        gap: 0.375rem;
    }

    .btn-sm svg {
        width: 16px;
        height: 16px;
    }

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.5rem;
    }

    .stat-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
    }

    .stat-label {
        font-size: 0.875rem;
        color: var(--text-secondary);
    }

    .stat-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .stat-icon.blue {
        background: #dbeafe;
        color: #2563eb;
    }

    .stat-icon.green {
        background: #d1fae5;
        color: #059669;
    }

    .stat-icon.purple {
        background: #ede9fe;
        color: #7c3aed;
    }

    .stat-icon.orange {
        background: #fed7aa;
        color: #ea580c;
    }

    .stat-value {
        font-size: 1.875rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
    }

    .stat-change {
        font-size: 0.8125rem;
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }

    .stat-change.positive {
        color: #059669;
    }

    .stat-change.negative {
        color: #dc2626;
    }

    /* Mobile Overlay */
    .sidebar-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 999;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.3s, visibility 0.3s;
    }

    .sidebar-overlay.active {
        opacity: 1;
        visibility: visible;
    }

    /* Mobile Menu Button */
    .mobile-menu-btn {
        display: none;
        background: none;
        border: none;
        color: var(--text-secondary);
        cursor: pointer;
        padding: 0.5rem;
        border-radius: 8px;
        transition: all 0.15s;
        -webkit-tap-highlight-color: transparent;
        touch-action: manipulation;
    }

    .mobile-menu-btn:hover {
        background: var(--bg-primary);
        color: var(--text-primary);
    }

    .mobile-menu-btn svg {
        width: 24px;
        height: 24px;
    }

    /* Responsive - Tablet */
    @media (max-width: 1024px) {
        .content {
            padding: 1.5rem;
        }

        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .search-box {
            max-width: 300px;
        }
    }

    /* Responsive - Mobile */
    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
            width: var(--sidebar-width);
            box-shadow: 2px 0 8px rgba(0, 0, 0, 0.1);
        }

        .sidebar.open {
            transform: translateX(0);
        }

        .sidebar.collapsed {
            width: var(--sidebar-width);
        }

        .sidebar-overlay {
            display: block;
        }

        .main-content {
            margin-left: 0 !important;
        }

        .header {
            padding: 0.75rem 1rem;
        }

        .header-left {
            gap: 0.75rem;
        }

        .mobile-menu-btn {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .search-box {
            display: none;
        }

        .user-info {
            display: none;
        }

        .user-trigger {
            padding: 0.375rem;
        }

        .user-arrow {
            display: none;
        }

        .user-menu {
            position: static;
        }

        .user-menu .dropdown-menu {
            position: fixed;
            left: 0.75rem;
            right: 0.75rem;
            width: auto;
            top: 4rem;
            max-height: calc(100vh - 4.5rem);
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            -webkit-overflow-scrolling: touch;
        }

        .user-menu.open .dropdown-menu {
            transform: translateY(0);
        }

        .user-menu .user-trigger {
            min-width: 44px;
            min-height: 44px;
            justify-content: center;
        }

        .user-menu .dropdown-item {
            min-height: 44px;
            padding: 0.875rem 1rem;
        }

        .content {
            padding: 1rem;
        }

        .page-title {
            font-size: 1.5rem;
        }

        .page-subtitle {
            font-size: 0.875rem;
        }

        .stats-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .stat-card {
            padding: 1.25rem;
        }

        .stat-value {
            font-size: 1.5rem;
        }

        .dropdown-menu {
            right: 0.5rem;
            left: 0.5rem;
            min-width: auto;
            width: auto;
        }

        .nav-item {
            padding: 0.75rem 1rem;
            font-size: 0.9375rem;
        }

        .nav-icon {
            width: 22px;
            height: 22px;
        }
    }

    /* Responsive - Small Mobile */
    @media (max-width: 480px) {
        .header {
            padding: 0.625rem 0.75rem;
        }

        .header-right {
            gap: 0.5rem;
        }

        .header-agent-queue .agent-queue-toggle-label {
            display: none;
        }

        .inbound-call-banner-layer {
            top: 8px;
            right: 8px;
            left: 8px;
            align-items: stretch;
        }

        .inbound-call-notification,
        .ongoing-call-notification {
            min-width: 0;
            max-width: none;
            width: 100%;
            padding: 0.75rem 1rem;
        }

        .call-notification-content {
            gap: 0.75rem;
        }

        .call-notification-icon {
            width: 36px;
            height: 36px;
        }

        .call-notification-icon svg {
            width: 18px;
            height: 18px;
        }

        .call-notification-number {
            font-size: 0.875rem;
        }

        .call-btn {
            width: 36px;
            height: 36px;
        }

        .call-btn svg {
            width: 16px;
            height: 16px;
        }

        .ongoing-call-notification {
            min-width: 280px;
            max-width: calc(100vw - 2rem);
            padding: 0.75rem 1rem;
        }

        .call-duration {
            font-size: 0.75rem;
        }

        .header-icon-btn {
            padding: 0.375rem;
        }

        .header-icon-btn svg {
            width: 18px;
            height: 18px;
        }

        .user-avatar {
            width: 28px;
            height: 28px;
            font-size: 0.75rem;
        }

        .user-menu .dropdown-menu {
            left: 0.5rem;
            right: 0.5rem;
            top: 3.75rem;
        }

        .user-menu .dropdown-header {
            padding: 1rem;
        }

        .user-menu .dropdown-header-name {
            font-size: 0.8125rem;
        }

        .user-menu .dropdown-header-email {
            font-size: 0.6875rem;
            word-break: break-all;
        }

        .user-menu .dropdown-item {
            padding: 0.875rem 1rem;
            font-size: 0.9375rem;
        }

        .user-menu .dropdown-item svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        .content {
            padding: 0.75rem;
        }

        .page-header {
            margin-bottom: 1.5rem;
        }

        .page-title {
            font-size: 1.25rem;
        }

        .stat-card {
            padding: 1rem;
        }

        .stat-header {
            margin-bottom: 0.75rem;
        }

        .stat-icon {
            width: 36px;
            height: 36px;
        }

        .stat-icon svg {
            width: 18px;
            height: 18px;
        }

        .stat-value {
            font-size: 1.375rem;
        }

        .stat-change {
            font-size: 0.75rem;
        }

        .sidebar-header {
            padding: 1rem;
        }

        .logo-icon {
            width: 32px;
            height: 32px;
        }

        .logo-icon svg {
            width: 18px;
            height: 18px;
        }

        .logo-text {
            font-size: 1rem;
        }

        .nav-item {
            padding: 0.625rem 1rem;
        }
    }

    /* Responsive - Large Screens */
    @media (min-width: 1400px) {
        .content {
            max-width: 1400px;
            margin: 0 auto;
        }

        .stats-grid {
            grid-template-columns: repeat(4, 1fr);
        }
    }
</style>

