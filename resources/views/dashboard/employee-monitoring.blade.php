@extends('layouts.app')

@section('title', 'Employee Monitoring')

@section('content')
    <div class="page-header">
        <h1 class="page-title">Employee Monitoring</h1>
        <p class="page-subtitle">Monitor video captures per user</p>
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
                <select class="filter-select" id="departmentFilter">
                    <option value="">All Departments</option>
                    <!-- Departments will be loaded from database -->
                </select>
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

        <!-- Employees List -->
        <div class="employees-monitoring-grid" id="employeesMonitoringGrid">
            <!-- Employees will be loaded dynamically here -->
            <div style="grid-column: 1 / -1; text-align: center; padding: 2rem; color: var(--text-muted);">
                Loading employees...
            </div>
        </div>

        @if(auth()->user()->hasPermission('view_live_screen'))
        <div class="live-view-history-panel" id="liveViewHistoryPanel">
            <div class="live-view-history-header">
                <h3>Live View History</h3>
                <button type="button" class="btn-secondary btn-sm" id="refreshLiveViewHistoryBtn">Refresh</button>
            </div>
            <div class="live-view-history-table-wrap">
                <table class="live-view-history-table">
                    <thead>
                        <tr>
                            <th>Admin</th>
                            <th>Employee</th>
                            <th>Status</th>
                            <th>Started</th>
                            <th>Ended</th>
                        </tr>
                    </thead>
                    <tbody id="liveViewHistoryBody">
                        <tr><td colspan="5" class="live-view-history-empty">Loading…</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="pagination-container live-view-history-pagination" id="liveViewHistoryPagination">
                <div class="pagination-info" id="liveViewHistoryPaginationInfo">Loading…</div>
                <div class="pagination" id="liveViewHistoryPaginationButtons"></div>
            </div>
        </div>
        @endif
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
                <div>
                    <h3 id="modalTitle">Screenshot</h3>
                    <div class="modal-meta" id="modalMeta">Dec 31, 2025 at 10:30 AM</div>
                </div>
                <div id="bulkSelectControls" style="display: none;">
                    <label class="select-all-checkbox">
                        <input type="checkbox" id="selectAllVideos" onchange="toggleSelectAll()">
                        <span>Select All</span>
                    </label>
                    <span id="selectedCount" class="selected-count">0 selected</span>
                </div>
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
                    <button class="btn-secondary" id="deleteMediaBtn" onclick="deleteMedia()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"/>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        </svg>
                        <span id="deleteBtnText">Delete</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Live Screen Viewer Modal -->
    <div class="media-modal" id="liveViewModal">
        <div class="media-modal-content live-view-modal-content">
            <button type="button" class="modal-close" id="liveViewCloseTopBtn" aria-label="Close live view">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
            <div class="modal-header">
                <div>
                    <h3 id="liveViewTitle">Live Screen</h3>
                    <div class="modal-meta" id="liveViewMeta">Connecting...</div>
                </div>
                <span class="live-badge" id="liveViewBadge" style="display: none;">Live</span>
            </div>
            <div class="modal-body">
                <div class="live-view-main">
                    <div class="live-viewer-wrap">
                        <video id="liveWorkerVideo" autoplay muted playsinline controls style="width: 100%; max-height: 70vh; background: #111; border-radius: 8px;"></video>
                        <div class="live-view-loader" id="liveViewLoader">
                            <div class="live-view-loader-inner">
                                <div class="live-view-spinner" aria-hidden="true"></div>
                                <p id="liveViewLoaderText">Connecting to employee screen…</p>
                                <small id="liveViewLoaderHint">Connection usually completes within a few seconds.</small>
                            </div>
                        </div>
                        <div class="live-view-fallback" id="liveViewFallback" style="display: none;">
                            Live view unavailable. Latest recording clip will still be saved.
                        </div>
                    </div>
                    <div class="live-view-chat-panel" id="liveViewChatPanel">
                        <div class="live-view-chat-header">
                            <h4>Chat with worker</h4>
                            <button type="button" class="btn-secondary btn-sm" id="liveViewChatToggleBtn" aria-label="Toggle chat panel">Hide</button>
                        </div>
                        <div class="live-view-chat-messages" id="liveViewChatMessages">
                            <div class="live-view-chat-empty">Messages appear here during a live session.</div>
                        </div>
                        <form class="live-view-chat-form" id="liveViewChatForm">
                            <input type="text" id="liveViewChatInput" class="live-view-chat-input" placeholder="Type a message…" maxlength="2000" autocomplete="off">
                            <button type="submit" class="btn-primary btn-sm" id="liveViewChatSendBtn">Send</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="live-view-footer-left">
                    <span class="live-view-audio-status" id="liveViewAudioStatus"></span>
                </div>
                <div class="modal-footer-right">
                    <button type="button" class="btn-secondary" id="liveViewAudioBtn">Audio Chat</button>
                    <button type="button" class="btn-secondary" id="liveViewChatOpenBtn">Chat with worker</button>
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
        box-shadow: 0 0 0 3px rgba(95, 97, 230, 0.1);
    }

    .filter-select,
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

    .filter-select:focus,
    .date-input:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(95, 97, 230, 0.1);
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

    .live-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.25rem 0.6rem;
        border-radius: 999px;
        background: rgba(16, 185, 129, 0.15);
        color: #059669;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .live-badge::before {
        content: '';
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #10b981;
        animation: livePulse 1.5s infinite;
    }

    @keyframes livePulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.35; }
    }

    .monitor-card-actions {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .btn-watch-live {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.45rem 0.75rem;
        border-radius: 8px;
        border: 1px solid rgba(16, 185, 129, 0.35);
        background: rgba(16, 185, 129, 0.08);
        color: #047857;
        font-size: 0.8125rem;
        font-weight: 600;
        cursor: pointer;
    }

    .btn-watch-live:hover {
        background: rgba(16, 185, 129, 0.16);
    }

    .live-view-fallback {
        margin-top: 0.75rem;
        padding: 0.75rem 1rem;
        border-radius: 8px;
        background: rgba(245, 158, 11, 0.12);
        color: #b45309;
        font-size: 0.875rem;
    }

    .live-viewer-wrap {
        position: relative;
    }

    .live-view-loader {
        position: absolute;
        inset: 0;
        display: none;
        align-items: center;
        justify-content: center;
        background: rgba(17, 17, 17, 0.82);
        border-radius: 8px;
        z-index: 2;
        text-align: center;
        padding: 1.5rem;
    }

    .live-view-loader-inner {
        max-width: 320px;
        color: #f9fafb;
    }

    .live-view-loader-inner p {
        margin: 0.75rem 0 0.35rem;
        font-size: 0.95rem;
        font-weight: 600;
    }

    .live-view-loader-inner small {
        display: block;
        color: #d1d5db;
        font-size: 0.8rem;
        line-height: 1.4;
    }

    .live-view-spinner {
        width: 40px;
        height: 40px;
        margin: 0 auto;
        border: 3px solid rgba(255, 255, 255, 0.2);
        border-top-color: #10b981;
        border-radius: 50%;
        animation: live-view-spin 0.8s linear infinite;
    }

    @keyframes live-view-spin {
        to { transform: rotate(360deg); }
    }

    .live-view-modal-content .modal-close {
        z-index: 20;
    }

    .live-view-history-panel {
        margin-top: 0.5rem;
        padding: 1.25rem;
        border: 1px solid var(--border);
        border-radius: 12px;
        background: var(--bg-card);
    }

    .live-view-history-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .live-view-history-header h3 {
        font-size: 1rem;
        font-weight: 600;
        margin: 0;
    }

    .live-view-history-table-wrap {
        overflow-x: auto;
    }

    .live-view-history-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
    }

    .live-view-history-table th,
    .live-view-history-table td {
        padding: 0.65rem 0.75rem;
        border-bottom: 1px solid var(--border);
        text-align: left;
    }

    .live-view-history-table th {
        color: var(--text-secondary);
        font-weight: 600;
    }

    .live-view-history-empty {
        text-align: center;
        color: var(--text-muted);
    }

    .live-view-history-pagination {
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid var(--border);
    }

    .live-view-main {
        display: grid;
        grid-template-columns: 1fr 320px;
        gap: 1rem;
        align-items: stretch;
    }

    .live-view-chat-panel {
        display: flex;
        flex-direction: column;
        border: 1px solid var(--border);
        border-radius: 12px;
        background: var(--bg-primary);
        min-height: 360px;
        max-height: 70vh;
    }

    .live-view-chat-panel.collapsed {
        display: none;
    }

    .live-view-chat-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.75rem 1rem;
        border-bottom: 1px solid var(--border);
    }

    .live-view-chat-header h4 {
        margin: 0;
        font-size: 0.9rem;
        font-weight: 600;
    }

    .live-view-chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 0.75rem 1rem;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .live-view-chat-empty {
        color: var(--text-muted);
        font-size: 0.8125rem;
        text-align: center;
        margin: auto 0;
    }

    .live-view-chat-bubble {
        max-width: 90%;
        padding: 0.5rem 0.75rem;
        border-radius: 10px;
        font-size: 0.8125rem;
        line-height: 1.4;
        word-break: break-word;
    }

    .live-view-chat-bubble.mine {
        align-self: flex-end;
        background: rgba(95, 97, 230, 0.15);
        color: var(--text-primary);
    }

    .live-view-chat-bubble.theirs {
        align-self: flex-start;
        background: var(--bg-card);
        border: 1px solid var(--border);
    }

    .live-view-chat-bubble.pending {
        opacity: 0.7;
    }

    .live-view-chat-bubble.failed {
        opacity: 0.85;
        border: 1px solid #ef4444;
    }

    .live-view-chat-bubble-meta {
        display: block;
        margin-top: 0.25rem;
        font-size: 0.7rem;
        color: var(--text-muted);
    }

    .live-view-chat-form {
        display: flex;
        gap: 0.5rem;
        padding: 0.75rem;
        border-top: 1px solid var(--border);
    }

    .live-view-chat-input {
        flex: 1;
        padding: 0.5rem 0.75rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        background: var(--bg-card);
        color: var(--text-primary);
    }

    .live-view-audio-status {
        font-size: 0.8125rem;
        color: var(--text-secondary);
    }

    .live-view-audio-status.active {
        color: #059669;
        font-weight: 600;
    }

    .btn-sm {
        padding: 0.45rem 0.75rem;
        font-size: 0.8125rem;
    }

    @media (max-width: 900px) {
        .live-view-main {
            grid-template-columns: 1fr;
        }

        .live-view-chat-panel {
            max-height: 280px;
        }
    }

    .pagination-container {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .pagination-info {
        font-size: 0.875rem;
        color: var(--text-secondary);
    }

    .pagination {
        display: flex;
        align-items: center;
        gap: 0.375rem;
    }

    .pagination-btn {
        min-width: 36px;
        height: 36px;
        padding: 0 0.5rem;
        border: 1px solid var(--border);
        background: var(--bg-card);
        border-radius: 6px;
        font-size: 0.875rem;
        color: var(--text-secondary);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .pagination-btn.active {
        background: var(--accent);
        border-color: var(--accent);
        color: white;
    }

    .pagination-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .pagination-ellipsis {
        padding: 0 0.25rem;
        color: var(--text-muted);
    }

    .monitor-stats {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .stat-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.8125rem;
        color: var(--text-secondary);
    }

    .stat-item svg {
        width: 16px;
        height: 16px;
    }

    /* Monitor Tabs */
    .monitor-tabs {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 1.25rem;
        border-bottom: 1px solid var(--border);
    }

    .monitor-tab {
        padding: 0.625rem 1rem;
        background: none;
        border: none;
        border-bottom: 2px solid transparent;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.15s;
        -webkit-tap-highlight-color: transparent;
    }

    .monitor-tab:hover {
        color: var(--accent);
    }

    .monitor-tab.active {
        color: var(--accent);
        border-bottom-color: var(--accent);
    }

    /* Monitor Content */
    .monitor-content {
        display: none;
    }

    .monitor-content.active {
        display: block;
    }

    /* Media Grid */
    .media-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 1rem;
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

    .media-overlay svg {
        width: 32px;
        height: 32px;
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
        width: 48px;
        height: 48px;
        background: rgba(255, 255, 255, 0.9);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .play-button svg {
        width: 20px;
        height: 20px;
        margin-left: 2px;
    }

    .video-duration {
        position: absolute;
        bottom: 0.5rem;
        right: 0.5rem;
        background: rgba(0, 0, 0, 0.75);
        color: white;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 500;
        z-index: 4;
    }

    .media-info {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .media-time {
        font-size: 0.8125rem;
        font-weight: 500;
        color: var(--text-primary);
    }

    .media-date {
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    .view-more {
        text-align: center;
        margin-top: 1rem;
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

    .btn-primary svg, .btn-secondary svg {
        width: 18px;
        height: 18px;
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
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
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

    #bulkSelectControls {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 0.5rem;
    }

    .select-all-checkbox {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        cursor: pointer;
        font-size: 0.875rem;
        color: var(--text-primary);
        user-select: none;
    }

    .select-all-checkbox input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
        accent-color: var(--accent);
    }

    .selected-count {
        font-size: 0.8125rem;
        color: var(--text-secondary);
        font-weight: 500;
    }

    .media-item.selected {
        border: 2px solid var(--accent);
        border-radius: 8px;
    }

    .video-checkbox {
        position: absolute;
        top: 0.5rem;
        left: 0.5rem;
        width: 24px;
        height: 24px;
        z-index: 10;
        cursor: pointer;
        accent-color: var(--accent);
        background: rgba(255, 255, 255, 0.9);
        border-radius: 4px;
        padding: 2px;
    }

    .media-item {
        position: relative;
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

    .media-viewer img {
        max-width: 100%;
        max-height: 70vh;
        border-radius: 8px;
    }

    .media-viewer video {
        max-width: 100%;
        max-height: 70vh;
        border-radius: 8px;
    }

    .all-videos-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 1.5rem;
        width: 100%;
        padding: 1rem 0;
    }

    .all-videos-grid .media-item {
        transition: transform 0.15s;
    }

    .all-videos-grid .media-item:hover {
        transform: translateY(-4px);
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
        }

        .media-grid {
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 0.75rem;
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

    @media (max-width: 480px) {
        .monitoring-controls {
            padding: 1rem;
        }

        .employee-monitor-card {
            padding: 1.25rem;
        }

        .media-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
        }

        .modal-header,
        .modal-body,
        .modal-footer {
            padding: 1rem;
        }
    }
</style>
@endpush

@push('scripts')
<script src="{{ asset('js/live-view-signaling.js') }}?v={{ filemtime(public_path('js/live-view-signaling.js')) }}"></script>
<script src="{{ asset('js/live-view-audio.js') }}?v={{ filemtime(public_path('js/live-view-audio.js')) }}"></script>
<script src="{{ asset('js/live-view-chat.js') }}?v={{ filemtime(public_path('js/live-view-chat.js')) }}"></script>
<script src="{{ asset('js/live-view-admin.js') }}?v={{ filemtime(public_path('js/live-view-admin.js')) }}"></script>
<script>
    // CSRF Token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const canViewLiveScreen = @json(auth()->user()->hasPermission('view_live_screen'));
    const currentAdminName = @json(auth()->user()->name);
    const employeesApiUrl = @json(route('api.employee-monitoring.employees'));
    const employeeRecordingsApiUrlTemplate = @json(route('api.employee-monitoring.employee-recordings', ['employeeId' => ':id']));
    const deleteRecordingApiUrlTemplate = @json(route('api.employee-monitoring.delete-recording', ['id' => ':id']));
    const deleteRecordingsApiUrl = @json(route('api.employee-monitoring.delete-recordings'));
    const departmentsApiUrl = @json(route('api.user-management.departments'));
    let employeesData = [];
    let employeeVideosData = {}; // Store all videos for each employee
    let employeeVideosLoading = {}; // Track in-flight recording loads per employee
    let currentMediaViewer = null;
    let currentViewAllEmployeeId = null; // Track which employee's "View All" is currently open
    let selectedVideos = new Set(); // Track selected video IDs for bulk deletion

    function updateEmployeeCardLiveStatus(employee) {
        const card = document.querySelector(`.employee-monitor-card[data-employee-id="${employee.id}"]`);
        if (!card) {
            return;
        }

        const statusIndicator = card.querySelector('.status-indicator');
        const statusLabel = card.querySelector('.employee-meta span:last-child');
        if (statusIndicator) {
            statusIndicator.classList.remove('active', 'inactive');
            statusIndicator.classList.add(employee.status === 'active' ? 'active' : 'inactive');
        }
        if (statusLabel) {
            statusLabel.textContent = employee.status === 'active' ? 'Active' : 'Inactive';
        }

        const actions = card.querySelector('.monitor-card-actions');
        if (!actions) {
            return;
        }

        const existingBadge = actions.querySelector('.live-badge');
        const existingButton = actions.querySelector('.btn-watch-live');

        if (employee.live_available && canViewLiveScreen) {
            if (!existingBadge) {
                actions.insertAdjacentHTML('afterbegin', '<span class="live-badge">Live</span>');
            }
            if (!existingButton) {
                actions.insertAdjacentHTML('beforeend',
                    `<button type="button" class="btn-watch-live" onclick="openLiveViewer(${employee.id}, '${escapeForJsString(employee.name)}')">Watch Live</button>`
                );
            }
        } else {
            existingBadge?.remove();
            existingButton?.remove();
        }
    }

    function mergeEmployeeStatus(employees) {
        employees.forEach((employee) => {
            const index = employeesData.findIndex((item) => item.id === employee.id);
            if (index === -1) {
                employeesData.push(employee);
                return;
            }

            employeesData[index] = {
                ...employeesData[index],
                status: employee.status,
                is_clocked_in: employee.is_clocked_in,
                is_recording_session: employee.is_recording_session,
                live_available: employee.live_available,
            };
            updateEmployeeCardLiveStatus(employeesData[index]);
        });
    }

    function startEmployeeStatusRealtime() {
        if (!canViewLiveScreen) {
            return;
        }

        const companyId = window.__companyId;
        const echo = window.LogonRealtime?.getEcho?.();

        if (!echo || !companyId) {
            console.warn('Realtime not available; employee status will not auto-update.');
            return;
        }

        echo.private(`company.${companyId}.monitoring`)
            .listen('.employee.status', (event) => {
                if (event?.employee) {
                    mergeEmployeeStatus([event.employee]);
                }
            });
    }

    // Load departments from database
    async function loadDepartments() {
        try {
            const response = await fetch(departmentsApiUrl, {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();
            if (data.success && data.data) {
                const select = document.getElementById('departmentFilter');
                if (select) {
                    // Keep the "All Departments" option
                    select.innerHTML = '<option value="">All Departments</option>';
                    
                    // Add departments from database
                    data.data.forEach(dept => {
                        const option = document.createElement('option');
                        option.value = dept.name;
                        option.textContent = dept.name;
                        select.appendChild(option);
                    });
                }
            }
        } catch (error) {
            console.error('Error loading departments:', error);
        }
    }

    // Get current date for both from and to (single-day range) in local timezone
    function getDefaultDates() {
        const today = new Date();
        const year = today.getFullYear();
        const month = String(today.getMonth() + 1).padStart(2, '0');
        const day = String(today.getDate()).padStart(2, '0');

        return {
            start: `${year}-${month}-${day}`,
            end: `${year}-${month}-${day}`
        };
    }

    function escapeForJsString(value) {
        return String(value ?? '')
            .replace(/\\/g, '\\\\')
            .replace(/'/g, "\\'")
            .replace(/\r/g, '\\r')
            .replace(/\n/g, '\\n');
    }

    function showVideosMessage(employeeId, message, isError = false) {
        const container = document.getElementById(`videos-${employeeId}`);
        if (!container) {
            return;
        }

        const color = isError ? 'var(--danger, #dc2626)' : 'var(--text-muted)';
        container.innerHTML = `<div style="grid-column: 1 / -1; text-align: center; padding: 2rem; color: ${color};">${message}</div>`;
    }

    function setViewMoreVisibility(employeeId, visible, count = 0) {
        const container = document.getElementById(`videos-${employeeId}`);
        if (!container) {
            return;
        }

        const viewMoreContainer = container.closest('.monitor-content')?.querySelector('.view-more');
        if (!viewMoreContainer) {
            return;
        }

        viewMoreContainer.style.display = visible ? 'block' : 'none';

        const viewAllButton = viewMoreContainer.querySelector('button');
        if (viewAllButton && visible) {
            viewAllButton.textContent = `View All ${count} Video${count === 1 ? '' : 's'}`;
        }
    }

    // Initialize date range inputs with current date
    function initializeDateRange() {
        const defaultDates = getDefaultDates();
        const startInput = document.getElementById('dateFilterStart');
        const endInput = document.getElementById('dateFilterEnd');
        
        if (startInput && !startInput.value) {
            startInput.value = defaultDates.start;
        }
        if (endInput && !endInput.value) {
            endInput.value = defaultDates.end;
        }
    }

    // Load employees on page load
    async function loadEmployees(options = {}) {
        const { refreshOnly = false } = options;

        try {
            const dateStart = document.getElementById('dateFilterStart').value;
            const dateEnd = document.getElementById('dateFilterEnd').value;
            const statusParam = refreshOnly ? '&status_only=1' : '';
            const url = `${employeesApiUrl}?date_start=${dateStart}&date_end=${dateEnd}${statusParam}`;
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();
            if (data.success) {
                if (refreshOnly) {
                    mergeEmployeeStatus(data.employees);
                    return;
                }

                employeesData = data.employees;
                employeeVideosData = {};
                renderEmployees(data.employees);
            } else {
                console.error('Error loading employees:', data.message);
            }
        } catch (error) {
            console.error('Error loading employees:', error);
        }
    }

    // Render employees
    function renderEmployees(employees) {
        const grid = document.getElementById('employeesMonitoringGrid');
        if (!grid) return;

        if (employees.length === 0) {
            grid.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; padding: 2rem; color: var(--text-muted);">No employees found</div>';
            return;
        }

        grid.innerHTML = employees.map(employee => {
            const initials = employee.name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
            return `
                <div class="employee-monitor-card" data-employee-id="${employee.id}">
                    <div class="monitor-card-header">
                        <div class="employee-info">
                            <div class="employee-avatar">${initials}</div>
                            <div>
                                <div class="employee-name">${employee.name}</div>
                                <div class="employee-meta">
                                    <span>${employee.department || 'General'}</span>
                                    <span class="status-indicator ${employee.status}"></span>
                                    <span>${employee.status === 'active' ? 'Active' : 'Inactive'}</span>
                                </div>
                            </div>
                        </div>
                        <div class="monitor-stats">
                            <div class="stat-item">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polygon points="23 7 16 12 23 17 23 7"/>
                                    <rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>
                                </svg>
                                <span>${employee.total_videos} Videos</span>
                            </div>
                            <div class="monitor-card-actions">
                                ${employee.live_available && canViewLiveScreen ? '<span class="live-badge">Live</span><button type="button" class="btn-watch-live" onclick="openLiveViewer(' + employee.id + ', \'' + escapeForJsString(employee.name) + '\')">Watch Live</button>' : ''}
                            </div>
                        </div>
                    </div>

                    <div class="monitor-content active" data-content="videos-${employee.id}">
                        <div class="media-grid" id="videos-${employee.id}">
                            <div style="grid-column: 1 / -1; text-align: center; padding: 2rem; color: var(--text-muted);">Loading videos...</div>
                        </div>
                        <div class="view-more" style="display: ${employee.total_videos > 0 ? 'block' : 'none'};">
                            <button class="btn-secondary" onclick="viewAllVideos(${employee.id})">View All ${employee.total_videos} Video${employee.total_videos === 1 ? '' : 's'}</button>
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        // Load recordings for each employee (with a small delay to ensure DOM is ready)
        setTimeout(() => {
            employees.forEach(employee => {
                loadEmployeeRecordings(employee.id);
            });
        }, 100);
    }

    // Load recordings for a specific employee
    async function loadEmployeeRecordings(employeeId) {
        if (employeeVideosLoading[employeeId]) {
            return employeeVideosLoading[employeeId];
        }

        const loadPromise = (async () => {
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

                const contentType = response.headers.get('content-type') || '';
                const isJson = contentType.includes('application/json');
                const data = isJson ? await response.json() : null;

                if (!response.ok || !data?.success) {
                    const message = data?.message || `Unable to load recordings (${response.status})`;
                    console.error('Error loading recordings:', message);
                    employeeVideosData[employeeId] = [];
                    showVideosMessage(employeeId, message, true);
                    setViewMoreVisibility(employeeId, false);
                    return;
                }

                employeeVideosData[employeeId] = data.recordings || [];
                renderVideos(employeeId, data.recordings || []);
            } catch (error) {
                console.error('Error loading recordings:', error);
                employeeVideosData[employeeId] = [];
                showVideosMessage(employeeId, 'Unable to load recordings. Please try again.', true);
                setViewMoreVisibility(employeeId, false);
            } finally {
                delete employeeVideosLoading[employeeId];
            }
        })();

        employeeVideosLoading[employeeId] = loadPromise;
        return loadPromise;
    }

    // Render videos
    function renderVideos(employeeId, videos) {
        // console.log('Rendering videos for employee', employeeId, ':', videos);
        const container = document.getElementById(`videos-${employeeId}`);
        if (!container) {
            console.error('Video container not found for employee:', employeeId, 'Container ID:', `videos-${employeeId}`);
            return;
        }

        if (!videos || videos.length === 0) {
            container.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; padding: 2rem; color: var(--text-muted);">No videos available</div>';
            return;
        }

        // Limit to first 2 videos for display
        const videosToShow = videos.slice(0, 2);

        container.innerHTML = videosToShow.map((video) => {
            const safeUrl = escapeForJsString(video.url);
            const safeDateFull = escapeForJsString(video.date_full);
            
            return `
                <div class="media-item video-item" onclick="openMediaViewer('video', ${video.id}, '${safeUrl}', '${safeDateFull}')" onmouseenter="playVideoPreview(this)" onmouseleave="pauseVideoPreview(this)">
                    <div class="media-thumbnail">
                        <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='300' height='200'%3E%3Crect fill='%23e5e7eb' width='300' height='200'/%3E%3Ccircle cx='150' cy='100' r='30' fill='%235f61e6'/%3E%3Cpolygon points='140,90 140,110 160,100' fill='white'/%3E%3C/svg%3E" alt="Video">
                        <video class="video-preview" muted loop preload="none" playsinline>
                            <source src="${video.url || ''}" type="video/webm">
                            <source src="${video.url || ''}" type="video/mp4">
                        </video>
                        <div class="media-overlay">
                            <div class="media-overlay-content">
                                <div class="play-button">
                                    <svg viewBox="0 0 24 24" fill="white">
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
        
        setViewMoreVisibility(employeeId, videos.length > 0, videos.length);
        
        // console.log('Videos rendered successfully for employee', employeeId);
    }

    // Media Viewer
    function openMediaViewer(type, id, url, dateTime) {
        const modal = document.getElementById('mediaModal');
        const viewer = document.getElementById('mediaViewer');
        const title = document.getElementById('modalTitle');
        const meta = document.getElementById('modalMeta');
        const footerLeft = document.getElementById('modalFooterLeft');
        
        title.textContent = type === 'screenshot' ? 'Screenshot' : 'Video Recording';
        meta.textContent = dateTime || 'Loading...';
        
        currentMediaViewer = { type, id, url };
        
        // Clear back button in footer
        footerLeft.innerHTML = '';
        
        if (type === 'screenshot') {
            viewer.innerHTML = `
                <img src="${url}" alt="Screenshot" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\\'http://www.w3.org/2000/svg\\' width=\\'1200\\' height=\\'800\\'%3E%3Crect fill=\\'%23f3f4f6\\' width=\\'1200\\' height=\\'800\\'/%3E%3Ctext x=\\'50%25\\' y=\\'50%25\\' text-anchor=\\'middle\\' dy=\\'.3em\\' fill=\\'%239ca3af\\' font-size=\\'24\\'%3EImage not available%3C/text%3E%3C/svg%3E'">
            `;
        } else {
            viewer.innerHTML = `
                <video controls preload="metadata" playsinline style="width: 100%; max-width: 100%;">
                    <source src="${url}" type="video/webm">
                    <source src="${url}" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            `;
        }
        
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeMediaViewer() {
        const modal = document.getElementById('mediaModal');
        const footerLeft = document.getElementById('modalFooterLeft');
        const bulkSelectControls = document.getElementById('bulkSelectControls');
        modal.classList.remove('active');
        document.body.style.overflow = '';
        currentViewAllEmployeeId = null; // Reset view all state
        footerLeft.innerHTML = ''; // Clear back button
        bulkSelectControls.style.display = 'none'; // Hide bulk select controls
        selectedVideos.clear(); // Clear selections
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
            const liveModal = document.getElementById('liveViewModal');
            if (liveModal?.classList.contains('active')) {
                closeLiveViewer();
                return;
            }
            closeMediaViewer();
        }
    });

    // View All functions
    async function viewAllVideos(employeeId) {
        if (!employeeVideosData[employeeId]?.length) {
            showVideosMessage(employeeId, 'Loading videos...');
            await loadEmployeeRecordings(employeeId);
        }

        const videos = employeeVideosData[employeeId] || [];
        
        if (videos.length === 0) {
            alert('No videos available for this employee');
            return;
        }

        currentViewAllEmployeeId = employeeId; // Track which employee we're viewing
        selectedVideos.clear(); // Clear previous selections

        const modal = document.getElementById('mediaModal');
        const viewer = document.getElementById('mediaViewer');
        const title = document.getElementById('modalTitle');
        const meta = document.getElementById('modalMeta');
        const footerLeft = document.getElementById('modalFooterLeft');
        const bulkSelectControls = document.getElementById('bulkSelectControls');
        const selectAllCheckbox = document.getElementById('selectAllVideos');
        const deleteBtn = document.getElementById('deleteMediaBtn');
        const deleteBtnText = document.getElementById('deleteBtnText');
        
        // Find employee name
        const employee = employeesData.find(emp => emp.id === employeeId);
        const employeeName = employee ? employee.name : 'Employee';
        
        title.textContent = `All Videos - ${employeeName}`;
        meta.textContent = `${videos.length} video${videos.length !== 1 ? 's' : ''} total`;
        
        // Show bulk select controls
        bulkSelectControls.style.display = 'flex';
        selectAllCheckbox.checked = false;
        updateSelectedCount();
        
        // Clear back button in footer when viewing all videos grid
        footerLeft.innerHTML = '';
        
        // Create grid of all videos with checkboxes
        viewer.innerHTML = `
            <div class="all-videos-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1.5rem; width: 100%;">
                ${videos.map((video) => {
                    const safeUrl = escapeForJsString(video.url);
                    const safeDateFull = escapeForJsString(video.date_full);
                    const isSelected = selectedVideos.has(video.id);
                    
                    return `
                        <div class="media-item video-item ${isSelected ? 'selected' : ''}" style="cursor: pointer;" onclick="handleVideoItemClick(event, ${video.id}, '${safeUrl}', '${safeDateFull}', ${employeeId})" onmouseenter="playVideoPreview(this)" onmouseleave="pauseVideoPreview(this)">
                            <input type="checkbox" class="video-checkbox" data-video-id="${video.id}" ${isSelected ? 'checked' : ''} onclick="event.stopPropagation(); toggleVideoSelection(${video.id})">
                            <div class="media-thumbnail">
                                <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='300' height='200'%3E%3Crect fill='%23e5e7eb' width='300' height='200'/%3E%3Ccircle cx='150' cy='100' r='30' fill='%235f61e6'/%3E%3Cpolygon points='140,90 140,110 160,100' fill='white'/%3E%3C/svg%3E" alt="Video" style="width: 100%; height: 150px; object-fit: cover;">
                                <video class="video-preview" muted loop preload="none" playsinline>
                                    <source src="${video.url || ''}" type="video/webm">
                                    <source src="${video.url || ''}" type="video/mp4">
                                </video>
                                <div class="media-overlay">
                                    <div class="media-overlay-content">
                                        <div class="play-button">
                                            <svg viewBox="0 0 24 24" fill="white">
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
        const bulkSelectControls = document.getElementById('bulkSelectControls');
        
        // Hide bulk select controls when viewing single video
        bulkSelectControls.style.display = 'none';
        selectedVideos.clear();
        
        title.textContent = 'Video Recording';
        meta.textContent = dateTime || 'Loading...';
        
        currentMediaViewer = { type: 'video', id, url, fromEmployeeId };
        
        // If opened from "View All" grid, show back button in footer
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
            <video controls preload="metadata" playsinline style="width: 100%; max-width: 100%;">
                <source src="${url}" type="video/webm">
                <source src="${url}" type="video/mp4">
                Your browser does not support the video tag.
            </video>
        `;
    }

    // Handle video item click (prevent opening if clicking checkbox)
    function handleVideoItemClick(event, id, url, dateTime, employeeId) {
        // Don't open video if clicking on checkbox
        if (event.target.type === 'checkbox' || event.target.closest('.video-checkbox')) {
            return;
        }
        openVideoViewer(id, url, dateTime, employeeId);
    }

    // Toggle video selection
    function toggleVideoSelection(videoId) {
        const checkbox = document.querySelector(`.video-checkbox[data-video-id="${videoId}"]`);
        const mediaItem = checkbox ? checkbox.closest('.media-item') : null;
        
        if (selectedVideos.has(videoId)) {
            selectedVideos.delete(videoId);
            if (mediaItem) {
                mediaItem.classList.remove('selected');
            }
        } else {
            selectedVideos.add(videoId);
            if (mediaItem) {
                mediaItem.classList.add('selected');
            }
        }
        
        updateSelectedCount();
        updateSelectAllCheckbox();
    }

    // Toggle select all
    function toggleSelectAll() {
        const selectAllCheckbox = document.getElementById('selectAllVideos');
        const checkboxes = document.querySelectorAll('.video-checkbox');
        const allSelected = selectAllCheckbox.checked;
        
        checkboxes.forEach(checkbox => {
            const videoId = parseInt(checkbox.getAttribute('data-video-id'));
            const mediaItem = checkbox.closest('.media-item');
            
            checkbox.checked = allSelected;
            
            if (allSelected) {
                selectedVideos.add(videoId);
                if (mediaItem) {
                    mediaItem.classList.add('selected');
                }
            } else {
                selectedVideos.delete(videoId);
                if (mediaItem) {
                    mediaItem.classList.remove('selected');
                }
            }
        });
        
        updateSelectedCount();
    }

    // Update select all checkbox state
    function updateSelectAllCheckbox() {
        const selectAllCheckbox = document.getElementById('selectAllVideos');
        const checkboxes = document.querySelectorAll('.video-checkbox');
        
        if (checkboxes.length === 0) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
            return;
        }
        
        const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
        
        if (checkedCount === 0) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        } else if (checkedCount === checkboxes.length) {
            selectAllCheckbox.checked = true;
            selectAllCheckbox.indeterminate = false;
        } else {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = true;
        }
    }

    // Update selected count display
    function updateSelectedCount() {
        const selectedCountEl = document.getElementById('selectedCount');
        const deleteBtnText = document.getElementById('deleteBtnText');
        const count = selectedVideos.size;
        
        if (selectedCountEl) {
            selectedCountEl.textContent = `${count} selected`;
        }
        
        if (deleteBtnText) {
            if (count > 0) {
                deleteBtnText.textContent = `Delete ${count} Video${count !== 1 ? 's' : ''}`;
            } else {
                deleteBtnText.textContent = 'Delete';
            }
        }
    }

    function refreshMonitoring() {
        loadEmployees();
    }

    function downloadMedia() {
        if (currentMediaViewer && currentMediaViewer.url) {
            const link = document.createElement('a');
            link.href = currentMediaViewer.url;
            link.download = `${currentMediaViewer.type}-${currentMediaViewer.id}`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    }

    async function deleteMedia() {
        // Check if we're in bulk delete mode (viewing all videos grid)
        if (selectedVideos.size > 0 && currentViewAllEmployeeId !== null) {
            const count = selectedVideos.size;
            if (confirm(`Are you sure you want to delete ${count} video${count !== 1 ? 's' : ''}? This action cannot be undone.`)) {
                const videoIds = Array.from(selectedVideos);
                
                try {
                    const response = await fetch(deleteRecordingsApiUrl, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ ids: videoIds })
                    });

                    const data = await response.json();

                    if (data.success) {
                        // Remove deleted videos from local data
                        if (employeeVideosData[currentViewAllEmployeeId]) {
                            employeeVideosData[currentViewAllEmployeeId] = employeeVideosData[currentViewAllEmployeeId].filter(
                                video => !videoIds.includes(video.id)
                            );
                        }
                        
                        // Refresh the view
                        viewAllVideos(currentViewAllEmployeeId);
                        
                        // Show success message
                        alert(`Successfully deleted ${data.deleted_count} video${data.deleted_count !== 1 ? 's' : ''}.`);
                    } else {
                        alert(`Error: ${data.message || 'Failed to delete videos'}`);
                    }
                } catch (error) {
                    console.error('Error deleting videos:', error);
                    alert('An error occurred while deleting the videos. Please try again.');
                }
            }
            return;
        }
        
        // Single video delete
        if (!currentMediaViewer) return;
        
        if (confirm('Are you sure you want to delete this media? This action cannot be undone.')) {
            try {
                const deleteUrl = deleteRecordingApiUrlTemplate.replace(':id', currentMediaViewer.id);
                const response = await fetch(deleteUrl, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    }
                });

                const data = await response.json();

                if (data.success) {
                    // Remove from local data if viewing all videos
                    if (currentMediaViewer.fromEmployeeId && employeeVideosData[currentMediaViewer.fromEmployeeId]) {
                        employeeVideosData[currentMediaViewer.fromEmployeeId] = employeeVideosData[currentMediaViewer.fromEmployeeId].filter(
                            video => video.id !== currentMediaViewer.id
                        );
                        // Refresh the view
                        viewAllVideos(currentMediaViewer.fromEmployeeId);
                    } else {
                        // Just close the modal
                        closeMediaViewer();
                        // Refresh employees list to update counts
                        loadEmployees();
                    }
                    
                    alert('Recording deleted successfully.');
                } else {
                    alert(`Error: ${data.message || 'Failed to delete recording'}`);
                }
            } catch (error) {
                console.error('Error deleting recording:', error);
                alert('An error occurred while deleting the recording. Please try again.');
            }
        }
    }

    // Video preview functions
    function playVideoPreview(element) {
        const videoPreview = element.querySelector('.video-preview');
        if (!videoPreview) {
            return;
        }

        if (!videoPreview.getAttribute('src') && videoPreview.querySelector('source')?.src) {
            videoPreview.src = videoPreview.querySelector('source').src;
            videoPreview.load();
        }

        videoPreview.currentTime = 0;
        videoPreview.play().catch(error => {
            console.debug('Video preview autoplay prevented:', error);
        });
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

        // Filter functionality
        const deptFilter = document.getElementById('departmentFilter');
        if (deptFilter) {
            deptFilter.addEventListener('change', function(e) {
                const dept = e.target.value.toLowerCase();
                document.querySelectorAll('.employee-monitor-card').forEach(card => {
                    const deptEl = card.querySelector('.employee-meta span');
                    if (deptEl) {
                        const cardDept = deptEl.textContent.toLowerCase();
                        card.style.display = !dept || cardDept.includes(dept) ? 'block' : 'none';
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

        // Initialize date range with current date
        initializeDateRange();
        
        // Load departments and employees on page load
        loadDepartments();
        loadEmployees();

        if (canViewLiveScreen) {
            startEmployeeStatusRealtime();
            LiveViewSignaling.loadIceConfig();
            loadLiveViewHistory();
        }
    });

    let currentLiveWorkerId = null;
    let liveViewIsStreaming = false;

    function updateLiveViewUi(status, detail = '') {
        const meta = document.getElementById('liveViewMeta');
        const loader = document.getElementById('liveViewLoader');
        const loaderText = document.getElementById('liveViewLoaderText');
        const loaderHint = document.getElementById('liveViewLoaderHint');
        const fallback = document.getElementById('liveViewFallback');
        const badge = document.getElementById('liveViewBadge');

        if (status === 'connected') {
            liveViewIsStreaming = true;
        } else if (status === 'idle' || status === 'failed' || status === 'ended') {
            liveViewIsStreaming = false;
        }

        if (fallback) {
            fallback.style.display = status === 'failed' ? 'block' : 'none';
        }

        if (loader) {
            loader.style.display = status === 'connecting' ? 'flex' : 'none';
        }

        if (loaderText && status === 'connecting' && detail) {
            loaderText.textContent = detail;
        }

        if (loaderHint) {
            loaderHint.textContent = status === 'connecting'
                ? 'Connection usually completes within a few seconds.'
                : '';
        }

        if (badge) {
            badge.style.display = liveViewIsStreaming ? 'inline-flex' : 'none';
        }

        if (meta) {
            if (status === 'connected') {
                meta.textContent = 'Streaming live';
            } else if (status === 'connecting') {
                meta.textContent = 'Connecting…';
            } else if (status === 'failed') {
                meta.textContent = 'Connection failed';
            } else if (status === 'ended') {
                meta.textContent = 'Live view ended';
            } else {
                meta.textContent = detail || '';
            }
        }
    }

    LiveViewAdmin.configure({
        onStatusChange(status, detail) {
            if (liveViewIsStreaming && status === 'connecting') {
                return;
            }
            updateLiveViewUi(status, detail);
        },
        onError(message) {
            const fallback = document.getElementById('liveViewFallback');
            if (fallback) {
                fallback.textContent = message;
            }
            updateLiveViewUi('failed', message);
        },
        onStreamStarted() {
            updateLiveViewUi('connected', 'Streaming live');
        },
        onAudioStateChange(status, detail) {
            updateLiveViewAudioUi(status, detail);
        },
        onChatMessage(message) {
            appendLiveViewChatMessage(message);
        },
        onReplaceChatMessage(tempId, message) {
            replaceLiveViewChatMessage(tempId, message);
        },
        onRemoveChatMessage(tempId) {
            removeLiveViewChatMessage(tempId);
        },
        onChatSendingChange(sending) {
            setLiveViewChatSending(sending);
        },
    });

    let liveViewHistoryPage = 1;
    let liveViewAudioActive = false;
    let liveViewChatOpen = true;

    function updateLiveViewAudioUi(status, detail = '') {
        const btn = document.getElementById('liveViewAudioBtn');
        const statusEl = document.getElementById('liveViewAudioStatus');
        if (!btn || !statusEl) return;

        if (status === 'active') {
            liveViewAudioActive = true;
            btn.textContent = 'End Audio';
            statusEl.textContent = 'Audio chat active';
            statusEl.classList.add('active');
        } else if (status === 'declined') {
            liveViewAudioActive = false;
            btn.textContent = 'Audio Chat';
            statusEl.textContent = detail || 'Employee declined audio chat.';
            statusEl.classList.remove('active');
        } else if (status === 'error') {
            liveViewAudioActive = false;
            btn.textContent = 'Audio Chat';
            statusEl.textContent = detail || 'Audio chat failed.';
            statusEl.classList.remove('active');
        } else {
            liveViewAudioActive = false;
            btn.textContent = 'Audio Chat';
            statusEl.textContent = '';
            statusEl.classList.remove('active');
        }
    }

    function appendLiveViewChatMessage(message) {
        const container = document.getElementById('liveViewChatMessages');
        if (!container || !message) return;

        const empty = container.querySelector('.live-view-chat-empty');
        if (empty) empty.remove();

        const existing = container.querySelector(`[data-message-id="${message.id}"]`);
        if (existing) return;

        const bubble = document.createElement('div');
        const isMine = message.is_mine === true
            || Number(message.sender_id) === Number(window.__liveViewUserId);
        bubble.className = `live-view-chat-bubble ${isMine ? 'mine' : 'theirs'}${message.pending ? ' pending' : ''}${message.failed ? ' failed' : ''}`;
        bubble.dataset.messageId = message.id;
        const time = message.sent_at ? new Date(message.sent_at).toLocaleTimeString() : '';
        bubble.innerHTML = `
            <div>${escapeHtml(message.body || '')}</div>
            <span class="live-view-chat-bubble-meta">${escapeHtml(message.sender_name || 'User')}${time ? ` · ${time}` : ''}</span>
        `;

        const messageId = Number(message.id);
        const existingNodes = container.querySelectorAll('[data-message-id]');
        let inserted = false;
        for (const node of existingNodes) {
            const existingId = Number(node.dataset.messageId);
            if (existingId > messageId) {
                container.insertBefore(bubble, node);
                inserted = true;
                break;
            }
        }

        if (!inserted) {
            container.appendChild(bubble);
        }

        container.scrollTop = container.scrollHeight;
    }

    function replaceLiveViewChatMessage(tempId, message) {
        const container = document.getElementById('liveViewChatMessages');
        const pending = container?.querySelector(`[data-message-id="${tempId}"]`);
        if (pending) {
            pending.remove();
        }
        appendLiveViewChatMessage(message);
    }

    function removeLiveViewChatMessage(tempId) {
        const container = document.getElementById('liveViewChatMessages');
        const pending = container?.querySelector(`[data-message-id="${tempId}"]`);
        if (!pending) {
            return;
        }

        pending.classList.add('failed');
        const meta = pending.querySelector('.live-view-chat-bubble-meta');
        if (meta) {
            meta.textContent = 'Failed to send';
        }
    }

    function setLiveViewChatSending(sending) {
        const input = document.getElementById('liveViewChatInput');
        const sendButton = document.getElementById('liveViewChatSendBtn');
        if (input) {
            input.disabled = sending;
        }
        if (sendButton) {
            sendButton.disabled = sending;
            sendButton.textContent = sending ? 'Sending…' : 'Send';
        }
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    async function loadLiveViewChatHistory() {
        try {
            const data = await LiveViewAdmin.loadChatMessages();
            const container = document.getElementById('liveViewChatMessages');
            if (!container) return;

            container.innerHTML = '';
            const messages = [...(data.messages || [])].sort((a, b) => a.id - b.id);
            if (!messages.length) {
                container.innerHTML = '<div class="live-view-chat-empty">No messages yet. Say hello to the employee.</div>';
                return;
            }
            messages.forEach(appendLiveViewChatMessage);
        } catch (error) {
            console.warn('Failed to load live view chat', error);
        }
    }

    function setLiveViewChatPanelOpen(open) {
        liveViewChatOpen = open;
        const panel = document.getElementById('liveViewChatPanel');
        const toggleBtn = document.getElementById('liveViewChatToggleBtn');
        if (panel) {
            panel.classList.toggle('collapsed', !open);
        }
        if (toggleBtn) {
            toggleBtn.textContent = open ? 'Hide' : 'Show';
        }
    }

    async function openLiveViewer(workerId, workerName) {
        currentLiveWorkerId = workerId;
        liveViewIsStreaming = false;
        const modal = document.getElementById('liveViewModal');
        const video = document.getElementById('liveWorkerVideo');
        const title = document.getElementById('liveViewTitle');

        if (title) title.textContent = `Live Screen — ${workerName}`;
        if (video) {
            video.srcObject = null;
            video.onplaying = () => {
                updateLiveViewUi('connected', 'Streaming live');
            };
        }

        updateLiveViewUi('connecting', 'Connecting to employee screen…');
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';

        try {
            await LiveViewAdmin.startWatchingWorker(workerId, video);
            await loadLiveViewChatHistory();
            setLiveViewChatPanelOpen(true);
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
            video.onplaying = null;
            video.srcObject = null;
        }

        currentLiveWorkerId = null;
        liveViewIsStreaming = false;
        liveViewAudioActive = false;
        updateLiveViewAudioUi('ended');

        LiveViewAdmin.stopWatching('admin_closed').catch((error) => {
            console.warn('Failed to end live view session', error);
        });

        if (canViewLiveScreen) {
            loadLiveViewHistory();
        }
    }

    window.openLiveViewer = openLiveViewer;
    window.closeLiveViewer = closeLiveViewer;

    document.getElementById('liveViewCloseBtn')?.addEventListener('click', closeLiveViewer);
    document.getElementById('liveViewCloseTopBtn')?.addEventListener('click', closeLiveViewer);

    document.getElementById('liveViewAudioBtn')?.addEventListener('click', async () => {
        const btn = document.getElementById('liveViewAudioBtn');
        if (!btn || (!liveViewIsStreaming && !LiveViewAdmin.isReadyForAudio())) {
            return;
        }

        try {
            btn.disabled = true;
            if (liveViewAudioActive) {
                await LiveViewAdmin.endAudioChat();
            } else {
                await LiveViewAdmin.startAudioChat(currentAdminName);
            }
        } catch (error) {
            console.error('Audio chat failed', error);
            updateLiveViewAudioUi('error', error.message || 'Audio chat failed.');
        } finally {
            btn.disabled = false;
        }
    });

    document.getElementById('liveViewChatOpenBtn')?.addEventListener('click', () => {
        setLiveViewChatPanelOpen(true);
        document.getElementById('liveViewChatInput')?.focus();
    });

    document.getElementById('liveViewChatToggleBtn')?.addEventListener('click', () => {
        setLiveViewChatPanelOpen(!liveViewChatOpen);
    });

    document.getElementById('liveViewChatForm')?.addEventListener('submit', async (event) => {
        event.preventDefault();

        const input = document.getElementById('liveViewChatInput');
        if (!input || !LiveViewAdmin.getCurrentSession()?.id || LiveViewAdmin.isChatSending()) {
            return;
        }

        const body = input.value.trim();
        if (!body) return;

        input.value = '';

        try {
            await LiveViewAdmin.sendChatMessage(body);
        } catch (error) {
            console.error('Failed to send chat message', error);
            input.value = body;
            alert(error.message || 'Failed to send message.');
        }
    });

    document.getElementById('liveViewModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeLiveViewer();
        }
    });

    function renderLiveViewHistoryPagination(pagination) {
        const infoEl = document.getElementById('liveViewHistoryPaginationInfo');
        const buttonsEl = document.getElementById('liveViewHistoryPaginationButtons');
        if (!infoEl || !buttonsEl || !pagination) return;

        if (pagination.total > 0) {
            const start = ((pagination.current_page - 1) * pagination.per_page) + 1;
            const end = Math.min(pagination.current_page * pagination.per_page, pagination.total);
            infoEl.textContent = `Showing ${start} to ${end} of ${pagination.total} sessions`;
        } else {
            infoEl.textContent = 'No sessions found';
        }

        let html = `
            <button class="pagination-btn" ${pagination.current_page === 1 ? 'disabled' : ''} data-page="${pagination.current_page - 1}">‹</button>
        `;

        for (let i = 1; i <= pagination.last_page; i++) {
            html += `<button class="pagination-btn ${i === pagination.current_page ? 'active' : ''}" data-page="${i}">${i}</button>`;
        }

        html += `
            <button class="pagination-btn" ${pagination.current_page === pagination.last_page ? 'disabled' : ''} data-page="${pagination.current_page + 1}">›</button>
        `;

        buttonsEl.innerHTML = html;
        buttonsEl.querySelectorAll('.pagination-btn:not([disabled])').forEach((btn) => {
            btn.addEventListener('click', () => {
                const page = parseInt(btn.dataset.page, 10);
                if (!Number.isNaN(page)) {
                    loadLiveViewHistory(page);
                }
            });
        });
    }

    async function loadLiveViewHistory(page = 1) {
        if (!canViewLiveScreen) return;

        const body = document.getElementById('liveViewHistoryBody');
        if (!body) return;

        liveViewHistoryPage = page;

        try {
            const response = await fetch(`/api/live-view/sessions?page=${page}&per_page=10`, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
            });
            const data = await response.json();
            const sessions = data.sessions || [];

            if (!sessions.length) {
                body.innerHTML = '<tr><td colspan="5" class="live-view-history-empty">No live view sessions yet.</td></tr>';
                renderLiveViewHistoryPagination(data.pagination || { current_page: 1, last_page: 1, per_page: 10, total: 0 });
                return;
            }

            body.innerHTML = sessions.map((session) => {
                const started = session.started_at ? new Date(session.started_at).toLocaleString() : '—';
                const ended = session.ended_at ? new Date(session.ended_at).toLocaleString() : '—';
                return `<tr>
                    <td>${escapeHtml(session.admin?.name || '—')}</td>
                    <td>${escapeHtml(session.worker?.name || '—')}</td>
                    <td>${escapeHtml(session.status || '—')}</td>
                    <td>${started}</td>
                    <td>${ended}</td>
                </tr>`;
            }).join('');

            renderLiveViewHistoryPagination(data.pagination);
        } catch (error) {
            console.error('Failed to load live view history', error);
            body.innerHTML = '<tr><td colspan="5" class="live-view-history-empty">Failed to load history.</td></tr>';
        }
    }

    document.getElementById('refreshLiveViewHistoryBtn')?.addEventListener('click', loadLiveViewHistory);
</script>
@endpush

