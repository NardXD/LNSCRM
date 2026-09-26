@extends('layouts.app')

@section('title', 'Time Tracking')

@push('styles')
    @vite(['resources/css/pages/time-tracking.css'])
@endpush

@push('scripts')
    <script src="{{ asset('js/live-view-signaling.js') }}?v={{ filemtime(public_path('js/live-view-signaling.js')) }}"></script>
    <script src="{{ asset('js/live-view-notify.js') }}?v={{ filemtime(public_path('js/live-view-notify.js')) }}"></script>
    <script src="{{ asset('js/live-view-audio.js') }}?v={{ filemtime(public_path('js/live-view-audio.js')) }}"></script>
    <script src="{{ asset('js/live-view-chat.js') }}?v={{ filemtime(public_path('js/live-view-chat.js')) }}"></script>
    <script src="{{ asset('js/live-view-worker.js') }}?v={{ filemtime(public_path('js/live-view-worker.js')) }}"></script>
<script>
    window.__timeTrackingConfig = {
        recordingDurationMinutes: @json((float) (auth()->user()->recording_duration_minutes ?? 0.5)),
        companyTimezone: @json($companySettings['timezone'] ?? 'America/New_York'),
    };
</script>
    @vite(['resources/js/pages/time-tracking.js'])
@endpush

@section('content')
    <div class="page-header time-tracking-header">
        <h1 class="page-title">Time Tracking</h1>
    </div>

    <div id="liveViewWatchedBanner" class="live-view-watched-banner" role="alert" aria-live="polite" style="display: none;">
        <div class="live-view-watched-banner-inner">
            <strong>Your screen is being viewed live</strong>
            <span id="liveViewWatcherNames"> by an administrator.</span>
        </div>
    </div>

    <div id="liveViewAudioRequestBanner" class="live-view-audio-request-banner" role="alert" aria-live="polite" style="display: none;">
        <div class="live-view-audio-request-inner">
            <strong id="liveViewAudioRequestTitle">Audio chat requested</strong>
            <p id="liveViewAudioRequestText">Please enable Audio access as an administrator wants to talk with you.</p>
            <div class="live-view-audio-request-actions">
                <button type="button" class="btn-primary btn-sm" id="liveViewEnableAudioBtn">Enable Audio</button>
                <button type="button" class="btn-secondary btn-sm" id="liveViewDeclineAudioBtn">Decline</button>
            </div>
        </div>
    </div>

    <div id="liveViewWorkerChat" class="live-view-worker-chat" style="display: none;">
        <button type="button" class="live-view-worker-chat-toggle" id="liveViewWorkerChatToggle">
            <span>Admin Chat</span>
            <span class="live-view-worker-chat-badge" id="liveViewWorkerChatBadge" style="display: none;">0</span>
        </button>
        <div class="live-view-worker-chat-panel" id="liveViewWorkerChatPanel">
            <div class="live-view-worker-chat-header">
                <strong>Live View Chat</strong>
                <button type="button" class="btn-secondary btn-sm" id="liveViewWorkerChatCloseBtn">Close</button>
            </div>
            <div class="live-view-worker-chat-messages" id="liveViewWorkerChatMessages">
                <div class="live-view-chat-empty">Messages from your administrator appear here.</div>
            </div>
            <form class="live-view-worker-chat-form" id="liveViewWorkerChatForm">
                <input type="text" id="liveViewWorkerChatInput" class="live-view-chat-input" placeholder="Reply to admin…" maxlength="2000" autocomplete="off">
                <button type="submit" class="btn-primary btn-sm" id="liveViewWorkerChatSendBtn">Send</button>
            </form>
        </div>
    </div>
    
    <div class="time-tracking-container">
        <!-- Top Row: Tracker Header/Card and Check Ins -->
        <div class="time-tracking-top-row">
            <!-- Left Panel: Online Time Tracker -->
            <div class="tracker-panel">
                <div class="tracker-header">
                    <h2 class="tracker-title">Online Time Tracker</h2>
                    <button class="btn-start-recording" id="startRecordingBtn">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <circle cx="12" cy="12" r="10"/>
                        </svg>
                        Start Recording
                    </button>
                </div>

                <div class="tracker-card">
                    <div class="current-time">
                        <span class="time-label">Today:</span>
                        <span class="time-value" id="currentDateTime">December-31-2025 12:04:40</span>
                    </div>

                    <div class="form-group">
                        <label for="date" class="form-label">Date:</label>
                        <input type="text" id="date" name="date" class="form-input" value="12-31-2025" readonly>
                    </div>

                    <div class="form-group">
                        <label for="timeIn" class="form-label">Time In:</label>
                        <input type="text" id="timeIn" name="time_in" class="form-input" placeholder="--:--:--" readonly>
                    </div>

                    <div class="form-group">
                        <label for="timeOut" class="form-label">Time Out:</label>
                        <input type="text" id="timeOut" name="time_out" class="form-input" placeholder="--:--:--" readonly>
                    </div>

                    <button class="btn-time-in" id="timeInBtn">
                        Time In
                    </button>
                </div>
            </div>

            <!-- Right Panel: Check In & Outs -->
            <div class="checkins-panel">
                <h2 class="checkins-title">Check In & Outs</h2>
                
                <div class="table-container">
                    <table class="checkins-table">
                        <thead>
                            <tr>
                                <th>DATE</th>
                                <th>TIME IN</th>
                                <th>TIME OUT</th>
                                <th>HOURS</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="pagination-container" id="timeTrackingPagination">
                    <div class="pagination-info" id="timeTrackingPaginationInfo">
                        Loading...
                    </div>
                    <div class="pagination" id="timeTrackingPaginationButtons">
                        <!-- Pagination buttons will be generated dynamically -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom Row: Recordings Card (Full Width) -->
        <div class="recordings-card">
            <div class="recordings-header">
                <div>
                    <h3 class="recordings-title">Recordings</h3>
                    <div class="recordings-meta" id="recordingsMeta">Today's recordings</div>
                </div>
                <div class="recordings-header-right">
                    <div class="recordings-date-filter">
                        <label class="date-filter-label">Filter by Date:</label>
                        <input type="date" class="date-filter-input" id="recordingsDateFilter" value="">
                    </div>
                    <div class="recordings-status" id="recordingsStatus">
                        <div id="recordingStatusText">Not recording</div>
                        <div id="liveViewStatusText" class="live-view-status-note">Live viewing is available while you are clocked in and your recording session is active.</div>
                        <div id="nextRecordingCountdown"></div>
                    </div>
                </div>
            </div>
            <div class="recordings-body">
                <div class="recordings-grid" id="recordingsContent">
                    <!-- Recordings will be displayed here -->
                </div>
            </div>
        </div>
    </div>
@endsection
