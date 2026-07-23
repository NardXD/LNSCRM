@extends('layouts.client-portal')

@section('title', 'Employee Monitoring')

@section('content')
    <div class="page-header">
        <h1 class="page-title">Employee Monitoring</h1>
        <p class="page-subtitle">View and monitor employees assigned to your account</p>
    </div>

    <div class="monitoring-container">
        <!-- Filters and Controls -->
        <div class="monitoring-controls">
            <div class="controls-left">
                <div class="search-box">
                    <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="m21 21-4.35-4.35"/>
                    </svg>
                    <input type="text" class="search-input" placeholder="Search employees..." id="employeeSearch">
                </div>
            </div>
            <div class="controls-right">
                <div class="date-range-container">
                    <label class="date-range-label">From</label>
                    <input type="date" class="date-input" id="dateFilterStart">
                </div>
                <div class="date-range-container">
                    <label class="date-range-label">To</label>
                    <input type="date" class="date-input" id="dateFilterEnd">
                </div>
                <button class="btn-primary" onclick="refreshMonitoring()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23 4 23 10 17 10"/>
                        <polyline points="1 20 1 14 7 14"/>
                        <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
                    </svg>
                    Refresh
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid" id="statsGrid">
            <div class="stat-card">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <span class="stat-value" id="totalEmployees">0</span>
                    <span class="stat-label">Assigned Employees</span>
                </div>
            </div>
            <div class="stat-card stat-active">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <span class="stat-value" id="activeEmployees">0</span>
                    <span class="stat-label">Currently Active</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="23 7 16 12 23 17 23 7"/>
                        <rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <span class="stat-value" id="totalVideosToday">0</span>
                    <span class="stat-label">Videos Today</span>
                </div>
            </div>
        </div>

        <!-- Employees List -->
        <div class="employees-monitoring-grid" id="employeesMonitoringGrid">
            <div class="loading-state">
                <div class="spinner"></div>
                <span>Loading employees...</span>
            </div>
        </div>
    </div>

    <!-- Media Viewer Modal -->
    <div class="media-modal" id="mediaModal">
        <div class="media-modal-content">
            <button class="modal-close" onclick="closeMediaViewer()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
            <div class="modal-header">
                <h3 id="modalTitle">Video Recording</h3>
                <div class="modal-meta" id="modalMeta"></div>
            </div>
            <div class="modal-body">
                <div class="media-viewer" id="mediaViewer">
                    <!-- Media will be displayed here -->
                </div>
            </div>
            <div class="modal-footer">
                <div id="modalFooterLeft"></div>
                <div class="modal-footer-right">
                    <button class="btn-secondary" onclick="downloadMedia()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="7 10 12 15 17 10"/>
                            <line x1="12" y1="15" x2="12" y2="3"/>
                        </svg>
                        Download
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Live View Modal -->
    <div class="media-modal" id="liveViewModal">
        <div class="media-modal-content">
            <button class="modal-close" id="liveViewCloseTopBtn" aria-label="Close live view">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
            <div class="modal-header">
                <h3 id="liveViewTitle">Live Screen</h3>
                <div class="modal-meta" id="liveViewStatus">Connecting…</div>
            </div>
            <div class="modal-body">
                <video id="liveWorkerVideo" autoplay muted playsinline controls style="width: 100%; max-height: 70vh; background: #111; border-radius: 8px;"></video>
            </div>
            <div class="modal-footer">
                <div id="liveViewErrorMessage" class="live-view-error"></div>
                <div class="modal-footer-right">
                    <button type="button" class="btn-secondary" id="liveViewCloseBtn">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
    .monitoring-container {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }

    .stat-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.25rem;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .stat-card.stat-active .stat-icon {
        background: #ecfdf5;
        color: #10b981;
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        background: var(--accent-light);
        color: var(--accent);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .stat-icon svg {
        width: 24px;
        height: 24px;
    }

    .stat-content {
        display: flex;
        flex-direction: column;
    }

    .stat-value {
        font-size: 1.5rem;
        font-weight: 600;
        color: var(--text-primary);
    }

    .stat-label {
        font-size: 0.8125rem;
        color: var(--text-secondary);
    }

    /* Controls */
    .monitoring-controls {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.25rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .controls-left,
    .controls-right {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    .search-box {
        position: relative;
        min-width: 250px;
    }

    .search-icon {
        position: absolute;
        left: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
        width: 18px;
        height: 18px;
        pointer-events: none;
    }

    .search-input {
        width: 100%;
        padding: 0.625rem 0.75rem 0.625rem 2.5rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        background: var(--bg-card);
        color: var(--text-primary);
        transition: all 0.15s;
    }

    .search-input:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    }

    .date-input {
        padding: 0.625rem 0.75rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        background: var(--bg-card);
        color: var(--text-primary);
        cursor: pointer;
        transition: all 0.15s;
    }

    .date-input:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    }

    .date-range-container {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .date-range-label {
        font-size: 0.75rem;
        font-weight: 500;
        color: var(--text-secondary);
    }

    /* Employees Grid */
    .employees-monitoring-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
        gap: 1.5rem;
    }

    .loading-state {
        grid-column: 1 / -1;
        text-align: center;
        padding: 3rem;
        color: var(--text-muted);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1rem;
    }

    .spinner {
        width: 32px;
        height: 32px;
        border: 3px solid var(--border);
        border-top-color: var(--accent);
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    .empty-state {
        grid-column: 1 / -1;
        text-align: center;
        padding: 3rem;
        color: var(--text-muted);
    }

    /* Employee Monitor Card */
    .employee-monitor-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.5rem;
        transition: all 0.15s;
    }

    .employee-monitor-card:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    .monitor-card-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 1.25rem;
        padding-bottom: 1.25rem;
        border-bottom: 1px solid var(--border);
    }

    .employee-info {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex: 1;
    }

    .employee-avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: var(--accent);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 1rem;
        flex-shrink: 0;
        overflow: hidden;
    }

    .employee-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .employee-name {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
    }

    .employee-meta {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.8125rem;
        color: var(--text-secondary);
    }

    .status-indicator {
        width: 8px;
        height: 8px;
        border-radius: 50%;
    }

    .status-indicator.active {
        background: #10b981;
    }

    .status-indicator.inactive {
        background: #ef4444;
    }

    .monitor-stats {
        display: flex;
        flex-direction: column;
        gap: 0.375rem;
        text-align: right;
    }

    .monitor-stat-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.8125rem;
        color: var(--text-secondary);
        justify-content: flex-end;
    }

    .monitor-stat-item svg {
        width: 14px;
        height: 14px;
    }

    .monitor-stat-item.time-in {
        color: #10b981;
    }

    /* Time Stats */
    .time-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.75rem;
        margin-bottom: 1.25rem;
        padding-bottom: 1.25rem;
        border-bottom: 1px solid var(--border);
    }

    .time-stat {
        text-align: center;
        padding: 0.75rem;
        background: var(--bg-primary);
        border-radius: 8px;
    }

    .time-stat-value {
        font-size: 0.9375rem;
        font-weight: 600;
        color: var(--text-primary);
        display: block;
    }

    .time-stat-label {
        font-size: 0.6875rem;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.025em;
    }

    /* Monitor Content */
    .monitor-content {
        display: block;
    }

    .section-title {
        font-size: 0.8125rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.75rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .section-title svg {
        width: 16px;
        height: 16px;
        color: var(--accent);
    }

    /* Media Grid */
    .media-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .media-item {
        cursor: pointer;
        transition: transform 0.15s;
    }

    .media-item:hover {
        transform: translateY(-2px);
    }

    .media-thumbnail {
        position: relative;
        width: 100%;
        padding-top: 75%;
        background: var(--bg-primary);
        border-radius: 8px;
        overflow: hidden;
        margin-bottom: 0.5rem;
    }

    .media-thumbnail img {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        z-index: 0;
    }

    .media-overlay {
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.15s;
        z-index: 2;
    }

    .media-item:hover .media-overlay {
        opacity: 1;
    }

    .video-item:hover .media-overlay {
        background: rgba(0, 0, 0, 0.3);
    }

    .video-preview {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        opacity: 0;
        transition: opacity 0.3s;
        z-index: 1;
    }

    .media-item:hover .video-preview {
        opacity: 1;
    }

    .media-overlay-content {
        position: relative;
        z-index: 3;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .play-button {
        width: 40px;
        height: 40px;
        background: rgba(255, 255, 255, 0.9);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .play-button svg {
        width: 16px;
        height: 16px;
        margin-left: 2px;
    }

    .video-duration {
        position: absolute;
        bottom: 0.375rem;
        right: 0.375rem;
        background: rgba(0, 0, 0, 0.75);
        color: white;
        padding: 0.125rem 0.375rem;
        border-radius: 4px;
        font-size: 0.6875rem;
        font-weight: 500;
        z-index: 4;
    }

    .media-info {
        display: flex;
        flex-direction: column;
        gap: 0.125rem;
    }

    .media-time {
        font-size: 0.75rem;
        font-weight: 500;
        color: var(--text-primary);
    }

    .media-date {
        font-size: 0.6875rem;
        color: var(--text-muted);
    }

    .view-more {
        text-align: center;
        margin-top: 0.75rem;
    }

    .no-recordings {
        text-align: center;
        padding: 1.5rem;
        color: var(--text-muted);
        font-size: 0.875rem;
        background: var(--bg-primary);
        border-radius: 8px;
    }

    /* Time Records Section */
    .time-records-section {
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid var(--border);
    }

    .time-records-toggle {
        display: flex;
        align-items: center;
        justify-content: space-between;
        cursor: pointer;
        padding: 0.5rem;
        border-radius: 6px;
        transition: background 0.15s;
    }

    .time-records-toggle:hover {
        background: var(--bg-primary);
    }

    .time-records-toggle .toggle-left {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .time-records-toggle svg {
        width: 16px;
        height: 16px;
        color: var(--accent);
    }

    .time-records-toggle .toggle-text {
        font-size: 0.8125rem;
        font-weight: 600;
        color: var(--text-primary);
    }

    .time-records-toggle .toggle-icon {
        width: 20px;
        height: 20px;
        color: var(--text-muted);
        transition: transform 0.2s;
    }

    .time-records-toggle.expanded .toggle-icon {
        transform: rotate(180deg);
    }

    .time-records-list {
        display: none;
        margin-top: 0.75rem;
    }

    .time-records-list.show {
        display: block;
    }

    .time-record-item {
        display: grid;
        grid-template-columns: 1fr auto auto auto;
        gap: 1rem;
        padding: 0.75rem;
        background: var(--bg-primary);
        border-radius: 8px;
        margin-bottom: 0.5rem;
        align-items: center;
        font-size: 0.8125rem;
    }

    .time-record-item:last-child {
        margin-bottom: 0;
    }

    .time-record-date {
        display: flex;
        flex-direction: column;
        gap: 0.125rem;
    }

    .time-record-date .date {
        font-weight: 500;
        color: var(--text-primary);
    }

    .time-record-date .day {
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    .time-record-in, .time-record-out, .time-record-hours {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.125rem;
    }

    .time-record-in .label, .time-record-out .label, .time-record-hours .label {
        font-size: 0.6875rem;
        color: var(--text-muted);
        text-transform: uppercase;
    }

    .time-record-in .value {
        color: #10b981;
        font-weight: 500;
    }

    .time-record-out .value {
        color: #ef4444;
        font-weight: 500;
    }

    .time-record-hours .value {
        color: var(--accent);
        font-weight: 600;
    }

    .no-time-records {
        text-align: center;
        padding: 1rem;
        color: var(--text-muted);
        font-size: 0.8125rem;
        background: var(--bg-primary);
        border-radius: 8px;
    }

    .time-records-pagination {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        margin-top: 0.75rem;
        padding-top: 0.75rem;
        border-top: 1px solid var(--border);
    }

    .pagination-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border: 1px solid var(--border);
        background: var(--bg-secondary);
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.15s;
    }

    .pagination-btn:hover:not(:disabled) {
        background: var(--bg-primary);
        border-color: var(--accent);
    }

    .pagination-btn:disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }

    .pagination-btn svg {
        width: 16px;
        height: 16px;
        color: var(--text-primary);
    }

    .pagination-info {
        font-size: 0.75rem;
        color: var(--text-muted);
        min-width: 80px;
        text-align: center;
    }

    @media (max-width: 480px) {
        .time-record-item {
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }

        .time-record-date {
            grid-column: 1 / -1;
        }

        .time-record-hours {
            grid-column: 1 / -1;
            align-items: flex-start;
        }
    }

    /* Buttons */
    .btn-primary, .btn-secondary {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.625rem 1.25rem;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.15s;
        border: none;
        -webkit-tap-highlight-color: transparent;
    }

    .btn-primary {
        background: var(--accent);
        color: white;
    }

    .btn-primary:hover {
        background: var(--accent-hover);
    }

    .btn-secondary {
        background: var(--bg-primary);
        color: var(--text-primary);
        border: 1px solid var(--border);
    }

    .btn-secondary:hover {
        background: var(--border);
    }

    .btn-sm {
        padding: 0.375rem 0.75rem;
        font-size: 0.8125rem;
    }

    .btn-primary svg, .btn-secondary svg {
        width: 18px;
        height: 18px;
    }

    /* Watch Live */
    .btn-watch-live {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.3rem 0.6rem;
        border-radius: 8px;
        border: 1px solid rgba(16, 185, 129, 0.35);
        background: rgba(16, 185, 129, 0.08);
        color: var(--accent-hover);
        font-size: 0.75rem;
        font-weight: 600;
        cursor: pointer;
    }

    .btn-watch-live:hover {
        background: rgba(16, 185, 129, 0.16);
    }

    .live-view-error {
        color: #dc2626;
        font-size: 0.8125rem;
    }

    /* Media Modal */
    .media-modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.75);
        z-index: 2000;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        opacity: 0;
        transition: opacity 0.2s;
    }

    .media-modal.active {
        display: flex;
        opacity: 1;
    }

    .media-modal-content {
        background: var(--bg-card);
        border-radius: 16px;
        max-width: 1200px;
        width: 100%;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
        position: relative;
        transform: scale(0.95);
        transition: transform 0.2s;
    }

    .media-modal.active .media-modal-content {
        transform: scale(1);
    }

    .modal-close {
        position: absolute;
        top: 1rem;
        right: 1rem;
        width: 40px;
        height: 40px;
        background: rgba(0, 0, 0, 0.5);
        border: none;
        border-radius: 50%;
        color: white;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10;
        transition: background 0.15s;
    }

    .modal-close:hover {
        background: rgba(0, 0, 0, 0.7);
    }

    .modal-close svg {
        width: 20px;
        height: 20px;
    }

    .modal-header {
        padding: 1.5rem;
        border-bottom: 1px solid var(--border);
    }

    .modal-header h3 {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
    }

    .modal-meta {
        font-size: 0.875rem;
        color: var(--text-secondary);
    }

    .modal-body {
        flex: 1;
        overflow: auto;
        padding: 1.5rem;
    }

    .media-viewer {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 400px;
    }

    .media-viewer video {
        max-width: 100%;
        max-height: 70vh;
        border-radius: 8px;
    }

    .all-videos-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 1.25rem;
        width: 100%;
        padding: 1rem 0;
    }

    .modal-footer {
        padding: 1.5rem;
        border-top: 1px solid var(--border);
        display: flex;
        gap: 0.75rem;
        justify-content: space-between;
        align-items: center;
    }

    #modalFooterLeft {
        display: flex;
        gap: 0.75rem;
    }

    .modal-footer-right {
        display: flex;
        gap: 0.75rem;
    }

    /* Responsive */
    @media (max-width: 1024px) {
        .employees-monitoring-grid {
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        }
    }

    @media (max-width: 768px) {
        .monitoring-controls {
            flex-direction: column;
            align-items: stretch;
        }

        .controls-left,
        .controls-right {
            width: 100%;
        }

        .search-box {
            min-width: 100%;
        }

        .employees-monitoring-grid {
            grid-template-columns: 1fr;
        }

        .monitor-card-header {
            flex-direction: column;
            gap: 1rem;
        }

        .monitor-stats {
            flex-direction: row;
            width: 100%;
            justify-content: space-around;
            text-align: center;
        }

        .monitor-stat-item {
            justify-content: center;
        }

        .time-stats {
            grid-template-columns: 1fr;
        }

        .media-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
        }

        .modal-footer {
            flex-direction: column;
            gap: 1rem;
        }

        #modalFooterLeft,
        .modal-footer-right {
            width: 100%;
            flex-direction: column;
        }

        .modal-footer .btn-primary,
        .modal-footer .btn-secondary {
            width: 100%;
            justify-content: center;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const employeesApiUrl = @json(route('client.portal.employees'));
    const employeeRecordingsApiUrlTemplate = @json(route('client.portal.employee-recordings', ['employeeId' => ':id']));
    let employeesData = [];
    let employeeVideosData = {};
    let currentMediaViewer = null;
    let currentViewAllEmployeeId = null;

    // Get current week's Monday and Sunday
    function getWeekDates() {
        const today = new Date();
        const dayOfWeek = today.getDay();
        // Calculate Monday (day 1) - if Sunday (0), go back 6 days, otherwise go back (dayOfWeek - 1) days
        const mondayOffset = dayOfWeek === 0 ? -6 : 1 - dayOfWeek;
        const monday = new Date(today);
        monday.setDate(today.getDate() + mondayOffset);
        
        // Sunday is Monday + 6 days
        const sunday = new Date(monday);
        sunday.setDate(monday.getDate() + 6);
        
        return {
            start: monday.toISOString().split('T')[0],
            end: sunday.toISOString().split('T')[0]
        };
    }

    // Initialize date range inputs with current week
    function initializeDateRange() {
        const weekDates = getWeekDates();
        const startInput = document.getElementById('dateFilterStart');
        const endInput = document.getElementById('dateFilterEnd');
        
        if (startInput && !startInput.value) {
            startInput.value = weekDates.start;
        }
        if (endInput && !endInput.value) {
            endInput.value = weekDates.end;
        }
    }

    // Load employees on page load
    async function loadEmployees() {
        try {
            const grid = document.getElementById('employeesMonitoringGrid');
            grid.innerHTML = '<div class="loading-state"><div class="spinner"></div><span>Loading employees...</span></div>';

            const dateStart = document.getElementById('dateFilterStart').value;
            const dateEnd = document.getElementById('dateFilterEnd').value;
            const url = `${employeesApiUrl}?date_start=${dateStart}&date_end=${dateEnd}`;
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();
            if (data.success) {
                employeesData = data.employees;
                updateStats(data.employees);
                renderEmployees(data.employees);
            } else {
                grid.innerHTML = '<div class="empty-state">Failed to load employees: ' + (data.message || 'Unknown error') + '</div>';
            }
        } catch (error) {
            console.error('Error loading employees:', error);
            document.getElementById('employeesMonitoringGrid').innerHTML = '<div class="empty-state">Error loading employees. Please try again.</div>';
        }
    }

    // Update stats cards
    function updateStats(employees) {
        document.getElementById('totalEmployees').textContent = employees.length;
        document.getElementById('activeEmployees').textContent = employees.filter(e => e.status === 'active').length;
        document.getElementById('totalVideosToday').textContent = employees.reduce((sum, e) => sum + (e.videos_today || 0), 0);
    }

    // Store time records data and pagination state per employee
    const employeeTimeRecordsData = {};
    const timeRecordsPagination = {};
    const TIME_RECORDS_PER_PAGE = 5;

    // Render time records for an employee with pagination
    function renderTimeRecords(timeRecords, employeeId) {
        if (!timeRecords || timeRecords.length === 0) {
            return '<div class="no-time-records">No time records for selected date range</div>';
        }

        // Store data and initialize pagination
        employeeTimeRecordsData[employeeId] = timeRecords;
        timeRecordsPagination[employeeId] = 1;

        return renderTimeRecordsPage(employeeId);
    }

    // Render a specific page of time records
    function renderTimeRecordsPage(employeeId) {
        const timeRecords = employeeTimeRecordsData[employeeId] || [];
        const currentPage = timeRecordsPagination[employeeId] || 1;
        const totalPages = Math.ceil(timeRecords.length / TIME_RECORDS_PER_PAGE);
        const startIndex = (currentPage - 1) * TIME_RECORDS_PER_PAGE;
        const endIndex = startIndex + TIME_RECORDS_PER_PAGE;
        const pageRecords = timeRecords.slice(startIndex, endIndex);

        let html = '<div class="time-records-items">';
        html += pageRecords.map(record => `
            <div class="time-record-item">
                <div class="time-record-date">
                    <span class="date">${record.date}</span>
                    <span class="day">${record.day}</span>
                </div>
                <div class="time-record-in">
                    <span class="label">In</span>
                    <span class="value">${record.time_in || '--:--'}</span>
                </div>
                <div class="time-record-out">
                    <span class="label">Out</span>
                    <span class="value">${record.time_out || '--:--'}</span>
                </div>
                <div class="time-record-hours">
                    <span class="label">Hours</span>
                    <span class="value">${record.hours.toFixed(1)}h</span>
                </div>
            </div>
        `).join('');
        html += '</div>';

        // Add pagination if more than one page
        if (totalPages > 1) {
            html += `
                <div class="time-records-pagination">
                    <button class="pagination-btn" onclick="changeTimeRecordsPage(${employeeId}, ${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="15 18 9 12 15 6"/>
                        </svg>
                    </button>
                    <span class="pagination-info">Page ${currentPage} of ${totalPages}</span>
                    <button class="pagination-btn" onclick="changeTimeRecordsPage(${employeeId}, ${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="9 18 15 12 9 6"/>
                        </svg>
                    </button>
                </div>
            `;
        }

        return html;
    }

    // Change page for time records
    function changeTimeRecordsPage(employeeId, newPage) {
        const timeRecords = employeeTimeRecordsData[employeeId] || [];
        const totalPages = Math.ceil(timeRecords.length / TIME_RECORDS_PER_PAGE);

        if (newPage < 1 || newPage > totalPages) return;

        timeRecordsPagination[employeeId] = newPage;
        const container = document.getElementById(`time-records-${employeeId}`);
        if (container) {
            container.innerHTML = renderTimeRecordsPage(employeeId);
        }
    }

    // Toggle time records visibility
    function toggleTimeRecords(employeeId) {
        const toggle = document.querySelector(`[onclick="toggleTimeRecords(${employeeId})"]`);
        const list = document.getElementById(`time-records-${employeeId}`);
        
        if (toggle && list) {
            toggle.classList.toggle('expanded');
            list.classList.toggle('show');
        }
    }

    // Render employees
    function renderEmployees(employees) {
        const grid = document.getElementById('employeesMonitoringGrid');

        if (employees.length === 0) {
            grid.innerHTML = '<div class="empty-state">No employees are currently assigned to your account.</div>';
            return;
        }

        grid.innerHTML = employees.map(employee => {
            const initials = employee.name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
            const avatarContent = employee.photo 
                ? `<img src="${employee.photo}" alt="${employee.name}" onerror="this.style.display='none'; this.parentElement.textContent='${initials}';">`
                : initials;

            return `
                <div class="employee-monitor-card" data-employee-id="${employee.id}">
                    <div class="monitor-card-header">
                        <div class="employee-info">
                            <div class="employee-avatar">${avatarContent}</div>
                            <div>
                                <div class="employee-name">${employee.name}</div>
                                <div class="employee-meta">
                                    <span>${employee.department}</span>
                                    <span class="status-indicator ${employee.status}"></span>
                                    <span>${employee.status === 'active' ? 'Active' : 'Inactive'}</span>
                                    ${employee.live_available ? `<button type="button" class="btn-watch-live" onclick="openLiveViewer(${employee.id}, '${employee.name.replace(/'/g, "\\'")}')">Watch Live</button>` : ''}
                                </div>
                            </div>
                        </div>
                        <div class="monitor-stats">
                            <div class="monitor-stat-item">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polygon points="23 7 16 12 23 17 23 7"/>
                                    <rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>
                                </svg>
                                <span>${employee.total_videos} Videos</span>
                            </div>
                            ${employee.time_in ? `
                            <div class="monitor-stat-item time-in">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12 6 12 12 16 14"/>
                                </svg>
                                <span>In: ${employee.time_in}</span>
                            </div>
                            ` : ''}
                        </div>
                    </div>

                    <div class="time-stats">
                        <div class="time-stat">
                            <span class="time-stat-value">${employee.time_in || '--:--'}</span>
                            <span class="time-stat-label">Time In</span>
                        </div>
                        <div class="time-stat">
                            <span class="time-stat-value">${employee.time_out || '--:--'}</span>
                            <span class="time-stat-label">Time Out</span>
                        </div>
                        <div class="time-stat">
                            <span class="time-stat-value">${employee.worked_hours.toFixed(1)}h</span>
                            <span class="time-stat-label">Total Hours</span>
                        </div>
                    </div>

                    <div class="monitor-content">
                        <div class="section-title">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="23 7 16 12 23 17 23 7"/>
                                <rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>
                            </svg>
                            Screen Recordings
                        </div>
                        <div class="media-grid" id="videos-${employee.id}">
                            <div class="loading-state" style="padding: 1rem;">
                                <div class="spinner" style="width: 20px; height: 20px;"></div>
                            </div>
                        </div>
                        <div class="view-more" id="view-more-${employee.id}" style="display: none;">
                            <button class="btn-secondary btn-sm" onclick="viewAllVideos(${employee.id})">View All Videos</button>
                        </div>
                    </div>

                    <div class="time-records-section">
                        <div class="time-records-toggle" onclick="toggleTimeRecords(${employee.id})">
                            <div class="toggle-left">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12 6 12 12 16 14"/>
                                </svg>
                                <span class="toggle-text">Time Records (${employee.time_records?.length || 0})</span>
                            </div>
                            <svg class="toggle-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="6 9 12 15 18 9"/>
                            </svg>
                        </div>
                        <div class="time-records-list" id="time-records-${employee.id}">
                            ${renderTimeRecords(employee.time_records, employee.id)}
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        // Load recordings for each employee
        setTimeout(() => {
            employees.forEach(employee => {
                loadEmployeeRecordings(employee.id);
            });
        }, 100);
    }

    // Load recordings for a specific employee
    async function loadEmployeeRecordings(employeeId) {
        try {
            const dateStart = document.getElementById('dateFilterStart').value;
            const dateEnd = document.getElementById('dateFilterEnd').value;
            const url = employeeRecordingsApiUrlTemplate.replace(':id', employeeId);
            const response = await fetch(`${url}?date_start=${dateStart}&date_end=${dateEnd}`, {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                console.error('Error loading recordings:', response.status);
                return;
            }

            const data = await response.json();
            if (data.success) {
                employeeVideosData[employeeId] = data.recordings || [];
                renderVideos(employeeId, data.recordings || []);
            }
        } catch (error) {
            console.error('Error loading recordings:', error);
        }
    }

    // Render videos
    function renderVideos(employeeId, videos) {
        const container = document.getElementById(`videos-${employeeId}`);
        const viewMoreBtn = document.getElementById(`view-more-${employeeId}`);

        if (!container) return;

        if (!videos || videos.length === 0) {
            container.innerHTML = '<div class="no-recordings">No recordings available</div>';
            if (viewMoreBtn) viewMoreBtn.style.display = 'none';
            return;
        }

        const videosToShow = videos.slice(0, 3);

        container.innerHTML = videosToShow.map((video) => {
            const safeUrl = (video.url || '').replace(/'/g, "\\'");
            const safeDateFull = (video.date_full || '').replace(/'/g, "\\'");

            return `
                <div class="media-item video-item" onclick="openMediaViewer('video', ${video.id}, '${safeUrl}', '${safeDateFull}')" onmouseenter="playVideoPreview(this)" onmouseleave="pauseVideoPreview(this)">
                    <div class="media-thumbnail">
                        <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='300' height='200'%3E%3Crect fill='%23e5e7eb' width='300' height='200'/%3E%3Ccircle cx='150' cy='100' r='25' fill='%2310b981'/%3E%3Cpolygon points='143,90 143,110 158,100' fill='white'/%3E%3C/svg%3E" alt="Video">
                        <video class="video-preview" muted loop preload="metadata">
                            <source src="${safeUrl}" type="video/webm">
                            <source src="${safeUrl}" type="video/mp4">
                        </video>
                        <div class="media-overlay">
                            <div class="media-overlay-content">
                                <div class="play-button">
                                    <svg viewBox="0 0 24 24" fill="#10b981">
                                        <polygon points="9 5 9 19 19 12"/>
                                    </svg>
                                </div>
                            </div>
                        </div>
                        <div class="video-duration">${video.duration_formatted || '00:00'}</div>
                    </div>
                    <div class="media-info">
                        <div class="media-time">${video.time || 'N/A'}</div>
                        <div class="media-date">${video.date || 'N/A'}</div>
                    </div>
                </div>
            `;
        }).join('');

        if (viewMoreBtn) {
            if (videos.length > 0) {
                viewMoreBtn.style.display = 'block';
                viewMoreBtn.querySelector('button').textContent = `View All ${videos.length} Videos`;
            } else {
                viewMoreBtn.style.display = 'none';
            }
        }
    }

    // Media Viewer
    function openMediaViewer(type, id, url, dateTime) {
        const modal = document.getElementById('mediaModal');
        const viewer = document.getElementById('mediaViewer');
        const title = document.getElementById('modalTitle');
        const meta = document.getElementById('modalMeta');
        const footerLeft = document.getElementById('modalFooterLeft');

        title.textContent = 'Video Recording';
        meta.textContent = dateTime || '';
        footerLeft.innerHTML = '';

        currentMediaViewer = { type, id, url };

        viewer.innerHTML = `
            <video controls autoplay style="width: 100%; max-width: 100%;">
                <source src="${url}" type="video/webm">
                <source src="${url}" type="video/mp4">
                Your browser does not support the video tag.
            </video>
        `;

        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeMediaViewer() {
        const modal = document.getElementById('mediaModal');
        const footerLeft = document.getElementById('modalFooterLeft');
        modal.classList.remove('active');
        document.body.style.overflow = '';
        currentViewAllEmployeeId = null;
        footerLeft.innerHTML = '';
    }

    // Close modal on outside click
    document.getElementById('mediaModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeMediaViewer();
        }
    });

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeMediaViewer();
        }
    });

    // View All Videos
    function viewAllVideos(employeeId) {
        const videos = employeeVideosData[employeeId] || [];

        if (videos.length === 0) {
            alert('No videos available for this employee');
            return;
        }

        currentViewAllEmployeeId = employeeId;

        const modal = document.getElementById('mediaModal');
        const viewer = document.getElementById('mediaViewer');
        const title = document.getElementById('modalTitle');
        const meta = document.getElementById('modalMeta');
        const footerLeft = document.getElementById('modalFooterLeft');

        const employee = employeesData.find(emp => emp.id === employeeId);
        const employeeName = employee ? employee.name : 'Employee';

        title.textContent = `All Videos - ${employeeName}`;
        meta.textContent = `${videos.length} video${videos.length !== 1 ? 's' : ''} total`;
        footerLeft.innerHTML = '';

        viewer.innerHTML = `
            <div class="all-videos-grid">
                ${videos.map((video) => {
                    const safeUrl = (video.url || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
                    const safeDateFull = (video.date_full || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');

                    return `
                        <div class="media-item video-item" style="cursor: pointer;" onclick="openVideoViewer(${video.id}, '${safeUrl}', '${safeDateFull}', ${employeeId})" onmouseenter="playVideoPreview(this)" onmouseleave="pauseVideoPreview(this)">
                            <div class="media-thumbnail">
                                <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='300' height='200'%3E%3Crect fill='%23e5e7eb' width='300' height='200'/%3E%3Ccircle cx='150' cy='100' r='25' fill='%2310b981'/%3E%3Cpolygon points='143,90 143,110 158,100' fill='white'/%3E%3C/svg%3E" alt="Video">
                                <video class="video-preview" muted loop preload="metadata">
                                    <source src="${safeUrl}" type="video/webm">
                                    <source src="${safeUrl}" type="video/mp4">
                                </video>
                                <div class="media-overlay">
                                    <div class="media-overlay-content">
                                        <div class="play-button">
                                            <svg viewBox="0 0 24 24" fill="#10b981">
                                                <polygon points="9 5 9 19 19 12"/>
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                                <div class="video-duration">${video.duration_formatted || '00:00'}</div>
                            </div>
                            <div class="media-info">
                                <div class="media-time">${video.time || 'N/A'}</div>
                                <div class="media-date">${video.date || 'N/A'}</div>
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
        `;

        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function openVideoViewer(id, url, dateTime, fromEmployeeId = null) {
        const viewer = document.getElementById('mediaViewer');
        const title = document.getElementById('modalTitle');
        const meta = document.getElementById('modalMeta');
        const footerLeft = document.getElementById('modalFooterLeft');

        title.textContent = 'Video Recording';
        meta.textContent = dateTime || '';

        currentMediaViewer = { type: 'video', id, url, fromEmployeeId };

        if (fromEmployeeId) {
            footerLeft.innerHTML = `
                <button class="btn-secondary" onclick="viewAllVideos(${fromEmployeeId})">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="15 18 9 12 15 6"/>
                    </svg>
                    Back to All Videos
                </button>
            `;
        } else {
            footerLeft.innerHTML = '';
        }

        viewer.innerHTML = `
            <video controls autoplay style="width: 100%; max-width: 100%;">
                <source src="${url}" type="video/webm">
                <source src="${url}" type="video/mp4">
                Your browser does not support the video tag.
            </video>
        `;
    }

    function refreshMonitoring() {
        loadEmployees();
    }

    function downloadMedia() {
        if (currentMediaViewer && currentMediaViewer.url) {
            const link = document.createElement('a');
            link.href = currentMediaViewer.url;
            link.download = `recording-${currentMediaViewer.id}`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    }

    // Video preview functions
    function playVideoPreview(element) {
        const videoPreview = element.querySelector('.video-preview');
        if (videoPreview) {
            videoPreview.currentTime = 0; // Reset to start
            videoPreview.play().catch(error => {
                // Silently handle autoplay errors (browser may block autoplay)
                console.debug('Video preview autoplay prevented:', error);
            });
        }
    }

    function pauseVideoPreview(element) {
        const videoPreview = element.querySelector('.video-preview');
        if (videoPreview) {
            videoPreview.pause();
            videoPreview.currentTime = 0; // Reset to start for next hover
        }
    }

    // Search functionality
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('employeeSearch');
        if (searchInput) {
            searchInput.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                document.querySelectorAll('.employee-monitor-card').forEach(card => {
                    const nameEl = card.querySelector('.employee-name');
                    if (nameEl) {
                        const name = nameEl.textContent.toLowerCase();
                        card.style.display = name.includes(searchTerm) ? 'block' : 'none';
                    }
                });
            });
        }

        // Date range filters
        const dateFilterStart = document.getElementById('dateFilterStart');
        const dateFilterEnd = document.getElementById('dateFilterEnd');
        
        if (dateFilterStart) {
            dateFilterStart.addEventListener('change', function(e) {
                // Ensure end date is not before start date
                if (dateFilterEnd && dateFilterEnd.value && e.target.value > dateFilterEnd.value) {
                    dateFilterEnd.value = e.target.value;
                }
                loadEmployees();
            });
        }
        
        if (dateFilterEnd) {
            dateFilterEnd.addEventListener('change', function(e) {
                // Ensure start date is not after end date
                if (dateFilterStart && dateFilterStart.value && e.target.value < dateFilterStart.value) {
                    dateFilterStart.value = e.target.value;
                }
                loadEmployees();
            });
        }

        // Initialize date range with current week (Monday to Sunday)
        initializeDateRange();
        
        // Load employees on page load
        loadEmployees();
    });
</script>

<script src="{{ asset('js/live-view-signaling.js') }}?v={{ filemtime(public_path('js/live-view-signaling.js')) }}"></script>
<script src="{{ asset('js/live-view-audio.js') }}?v={{ filemtime(public_path('js/live-view-audio.js')) }}"></script>
<script src="{{ asset('js/live-view-chat.js') }}?v={{ filemtime(public_path('js/live-view-chat.js')) }}"></script>
<script src="{{ asset('js/live-view-admin.js') }}?v={{ filemtime(public_path('js/live-view-admin.js')) }}"></script>
<script>
    (function () {
        const startSessionUrl = @json(route('client.portal.live-view.sessions.start'));
        const apiBase = startSessionUrl.replace(/\/sessions$/, '');
        LiveViewSignaling.configureApiBase(apiBase);

        let liveViewIsStreaming = false;
        let liveViewCurrentEmployeeId = null;

        function updateLiveViewUi(status, detail) {
            const statusEl = document.getElementById('liveViewStatus');
            const errorEl = document.getElementById('liveViewErrorMessage');
            if (statusEl) {
                statusEl.textContent = detail || status;
            }
            if (errorEl) {
                errorEl.textContent = status === 'failed' ? (detail || 'Live view unavailable.') : '';
            }
        }

        LiveViewAdmin.configure({
            onStatusChange: (status, detail) => updateLiveViewUi(status, detail),
            onError: (message) => updateLiveViewUi('failed', message),
            onStreamStarted: () => { liveViewIsStreaming = true; },
            enableChat: false,
        });

        async function openLiveViewer(employeeId, employeeName) {
            liveViewCurrentEmployeeId = employeeId;
            liveViewIsStreaming = false;
            const modal = document.getElementById('liveViewModal');
            const video = document.getElementById('liveWorkerVideo');
            const title = document.getElementById('liveViewTitle');

            if (title) title.textContent = `Live Screen — ${employeeName}`;
            if (video) {
                video.srcObject = null;
            }

            updateLiveViewUi('connecting', 'Connecting to employee screen…');
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';

            try {
                await LiveViewAdmin.startWatchingWorker(employeeId, video);
            } catch (error) {
                console.error('Live view failed', error);
                updateLiveViewUi('failed', error.message || 'Live view unavailable.');
            }
        }

        function closeLiveViewer() {
            const modal = document.getElementById('liveViewModal');
            const video = document.getElementById('liveWorkerVideo');

            if (modal) modal.classList.remove('active');
            document.body.style.overflow = '';
            updateLiveViewUi('idle', '');

            if (video) {
                video.srcObject = null;
            }

            liveViewCurrentEmployeeId = null;
            liveViewIsStreaming = false;

            LiveViewAdmin.stopWatching('client_closed').catch((error) => {
                console.warn('Failed to end live view session', error);
            });
        }

        window.openLiveViewer = openLiveViewer;
        window.closeLiveViewer = closeLiveViewer;

        document.getElementById('liveViewCloseBtn')?.addEventListener('click', closeLiveViewer);
        document.getElementById('liveViewCloseTopBtn')?.addEventListener('click', closeLiveViewer);
        document.getElementById('liveViewModal')?.addEventListener('click', function (e) {
            if (e.target === this) {
                closeLiveViewer();
            }
        });
    })();
</script>
@endpush
