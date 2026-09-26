@extends('layouts.app')

@section('title', 'Employee Monitoring')

@push('styles')
    @vite(['resources/css/pages/employee-monitoring.css'])
@endpush

@push('scripts')
    <script src="{{ asset('js/live-view-signaling.js') }}?v={{ filemtime(public_path('js/live-view-signaling.js')) }}"></script>
    <script src="{{ asset('js/live-view-audio.js') }}?v={{ filemtime(public_path('js/live-view-audio.js')) }}"></script>
    <script src="{{ asset('js/live-view-chat.js') }}?v={{ filemtime(public_path('js/live-view-chat.js')) }}"></script>
    <script src="{{ asset('js/live-view-admin.js') }}?v={{ filemtime(public_path('js/live-view-admin.js')) }}"></script>
<script>
    window.__employeeMonitoringConfig = {
        canViewLiveScreen: @json(auth()->user()->hasPermission('view_live_screen')),
        currentAdminName: @json(auth()->user()->name),
        employeesApiUrl: @json(route('api.employee-monitoring.employees')),
        employeeRecordingsApiUrlTemplate: @json(route('api.employee-monitoring.employee-recordings', ['employeeId' => ':id'])),
        deleteRecordingApiUrlTemplate: @json(route('api.employee-monitoring.delete-recording', ['id' => ':id'])),
        deleteRecordingsApiUrl: @json(route('api.employee-monitoring.delete-recordings')),
        departmentsApiUrl: @json(route('api.user-management.departments')),
    };
</script>
    @vite(['resources/js/pages/employee-monitoring.js'])
@endpush

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
        <div class="employees-monitoring-grid" id="employeesMonitoringGrid" aria-busy="true">
            @include('partials.skeleton-cards', ['count' => 8])
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
