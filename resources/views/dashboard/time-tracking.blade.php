@extends('layouts.app')

@section('title', 'Time Tracking')

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

@push('styles')
<style>
    .time-tracking-header {
        margin-bottom: 1.5rem;
    }

    .time-tracking-container {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    /* Top Row: Tracker and Check Ins side by side */
    .time-tracking-top-row {
        display: grid;
        grid-template-columns: 1fr 1.5fr;
        gap: 1.5rem;
    }

    /* Left Panel: Tracker */
    .tracker-panel {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .tracker-header {
        background: var(--accent);
        color: white;
        padding: 1.25rem 1.5rem;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .tracker-title {
        font-size: 1.25rem;
        font-weight: 600;
        margin: 0;
    }

    .btn-start-recording {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.625rem 1.25rem;
        background: #ef4444;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.15s;
    }

    .btn-start-recording:hover {
        background: #dc2626;
    }

    .btn-start-recording.recording {
        background: #059669;
        animation: pulse 2s infinite;
    }

    .btn-start-recording svg {
        width: 12px;
        height: 12px;
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }

    .tracker-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.5rem;
    }

    .current-time {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1.5rem;
        padding-bottom: 1.5rem;
        border-bottom: 1px solid var(--border);
    }

    .time-label {
        font-size: 0.875rem;
        color: var(--text-secondary);
    }

    .time-value {
        font-size: 0.9375rem;
        font-weight: 600;
        color: var(--text-primary);
    }

    .form-group {
        margin-bottom: 1rem;
    }

    .form-label {
        display: block;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }

    .form-input {
        width: 100%;
        padding: 0.625rem 0.75rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        background: #f9fafb;
        color: var(--text-primary);
        transition: all 0.15s;
    }

    .form-input:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(95, 97, 230, 0.1);
        background: white;
    }

    .form-input[readonly] {
        background: #f3f4f6;
        cursor: not-allowed;
    }

    .btn-time-in {
        width: 100%;
        padding: 0.75rem;
        background: #10b981;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.15s;
        margin-top: 0.5rem;
    }

    .btn-time-in:hover {
        background: #059669;
    }

    .btn-time-in.out {
        background: #ef4444;
    }

    .btn-time-in.out:hover {
        background: #dc2626;
    }

    .recordings-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .recordings-header {
        padding: 1.5rem;
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
    }

    .recordings-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
    }

    .recordings-meta {
        font-size: 0.875rem;
        color: var(--text-secondary);
    }

    .recordings-status {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 0.25rem;
        padding: 0.75rem;
        background: #f3f4f6;
        border-radius: 8px;
        font-size: 0.875rem;
    }

    .recordings-status #recordingStatusText {
        color: var(--text-secondary);
        font-weight: 500;
    }

    .live-view-status-note {
        color: var(--text-muted);
        font-size: 0.75rem;
        margin-top: 0.25rem;
        max-width: 280px;
    }

    .live-view-watched-banner {
        margin-bottom: 1rem;
        padding: 0.85rem 1rem;
        border-radius: 10px;
        border: 1px solid rgba(220, 38, 38, 0.35);
        background: rgba(254, 226, 226, 0.85);
        color: #991b1b;
        font-size: 0.9rem;
    }

    .live-view-watched-banner-inner strong {
        display: block;
        margin-bottom: 0.15rem;
    }

    .live-view-audio-request-banner {
        margin-bottom: 1rem;
        padding: 0.85rem 1rem;
        border-radius: 10px;
        border: 1px solid rgba(245, 158, 11, 0.45);
        background: rgba(254, 243, 199, 0.9);
        color: #92400e;
        font-size: 0.9rem;
    }

    .live-view-audio-request-inner p {
        margin: 0.35rem 0 0.75rem;
    }

    .live-view-audio-request-actions {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .live-view-worker-chat {
        position: fixed;
        right: 1.25rem;
        bottom: 1.25rem;
        z-index: 1500;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 0.5rem;
    }

    .live-view-worker-chat-toggle {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.65rem 1rem;
        border-radius: 999px;
        border: 1px solid var(--border);
        background: var(--bg-card);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        cursor: pointer;
        font-weight: 600;
        color: var(--text-primary);
    }

    .live-view-worker-chat-badge {
        min-width: 20px;
        height: 20px;
        padding: 0 0.35rem;
        border-radius: 999px;
        background: #ef4444;
        color: white;
        font-size: 0.75rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .live-view-worker-chat-panel {
        width: min(360px, calc(100vw - 2rem));
        max-height: 420px;
        display: none;
        flex-direction: column;
        border: 1px solid var(--border);
        border-radius: 12px;
        background: var(--bg-card);
        box-shadow: 0 12px 32px rgba(0, 0, 0, 0.15);
        overflow: hidden;
    }

    .live-view-worker-chat-panel.open {
        display: flex;
    }

    .live-view-worker-chat-header,
    .live-view-worker-chat-form {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        padding: 0.75rem;
        border-bottom: 1px solid var(--border);
    }

    .live-view-worker-chat-form {
        border-bottom: none;
        border-top: 1px solid var(--border);
    }

    .live-view-worker-chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 0.75rem;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        min-height: 220px;
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
    }

    .live-view-chat-bubble.theirs {
        align-self: flex-start;
        background: var(--bg-primary);
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

    .live-view-chat-input {
        flex: 1;
        padding: 0.5rem 0.75rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        background: var(--bg-card);
        color: var(--text-primary);
    }

    .btn-sm {
        padding: 0.45rem 0.75rem;
        font-size: 0.8125rem;
    }

    .recordings-status #nextRecordingCountdown {
        color: var(--text-muted);
        font-size: 0.8125rem;
    }

    .recordings-header-right {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 1rem;
    }

    .recordings-date-filter {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 0.25rem;
    }

    .date-filter-label {
        font-size: 0.75rem;
        font-weight: 500;
        color: var(--text-secondary);
    }

    .date-filter-input {
        padding: 0.5rem 0.75rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        background: var(--bg-card);
        color: var(--text-primary);
        cursor: pointer;
        transition: all 0.15s;
    }

    .date-filter-input:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(95, 97, 230, 0.1);
    }

    .recordings-body {
        flex: 1;
        overflow: auto;
        padding: 1.5rem;
    }

    .recordings-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 1.5rem;
        width: 100%;
    }

    .recording-item {
        cursor: pointer;
        transition: transform 0.15s;
    }

    .recording-item:hover {
        transform: translateY(-4px);
    }

    .recording-thumbnail {
        position: relative;
        width: 100%;
        padding-top: 75%;
        background: var(--bg-primary);
        border-radius: 8px;
        overflow: hidden;
        margin-bottom: 0.5rem;
    }

    .recording-thumbnail img,
    .recording-thumbnail video {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .recording-overlay {
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

    .recording-item:hover .recording-overlay {
        opacity: 1;
    }

    .recording-overlay svg {
        width: 32px;
        height: 32px;
        color: white;
    }

    .recording-duration {
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

    .recording-info {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .recording-time {
        font-size: 0.8125rem;
        font-weight: 500;
        color: var(--text-primary);
    }

    .recording-date {
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    .recording-status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 500;
        margin-top: 0.25rem;
    }

    .recording-status-badge.saved {
        background: #d1fae5;
        color: #065f46;
    }

    .recording-status-badge.processing {
        background: #fef3c7;
        color: #92400e;
    }

    /* Right Panel: Check Ins */
    .checkins-panel {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.5rem;
    }

    .checkins-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 1.5rem;
    }

    .table-container {
        overflow-x: auto;
        margin-bottom: 1.5rem;
    }

    .checkins-table {
        width: 100%;
        border-collapse: collapse;
    }

    .checkins-table thead {
        background: var(--bg-primary);
    }

    .checkins-table th {
        padding: 0.75rem 1rem;
        text-align: left;
        font-size: 0.8125rem;
        font-weight: 600;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 2px solid var(--border);
    }

    .checkins-table td {
        padding: 0.875rem 1rem;
        font-size: 0.875rem;
        color: var(--text-primary);
        border-bottom: 1px solid var(--border);
    }

    .checkins-table tbody tr:hover {
        background: var(--bg-primary);
    }

    .checkins-table tbody tr:last-child td {
        border-bottom: none;
    }

    /* Pagination */
    .pagination-container {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
        padding-top: 1rem;
        border-top: 1px solid var(--border);
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
        background: white;
        border-radius: 6px;
        font-size: 0.875rem;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.15s;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .pagination-btn:hover:not(:disabled):not(.active) {
        border-color: var(--accent);
        color: var(--accent);
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

    .pagination-btn svg {
        width: 18px;
        height: 18px;
    }

    .pagination-ellipsis {
        padding: 0 0.25rem;
        color: var(--text-muted);
    }

    /* Responsive - Tablet */
    @media (max-width: 1024px) {
        .time-tracking-top-row {
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .tracker-panel {
            order: 1;
        }

        .checkins-panel {
            order: 2;
        }
    }

    /* Responsive - Mobile */
    @media (max-width: 768px) {
        .time-tracking-header {
            margin-bottom: 1rem;
        }

        .time-tracking-header .page-title {
            font-size: 1.25rem;
        }

        .time-tracking-container {
            gap: 1rem;
        }

        .time-tracking-top-row {
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .tracker-header {
            flex-direction: column;
            align-items: stretch;
            gap: 1rem;
            padding: 1rem;
        }

        .tracker-title {
            font-size: 1.125rem;
        }

        .btn-start-recording {
            width: 100%;
            justify-content: center;
            padding: 0.75rem 1rem;
        }

        .tracker-card {
            padding: 1.25rem;
        }

        .current-time {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
            padding-bottom: 1rem;
            margin-bottom: 1rem;
        }

        .time-value {
            font-size: 0.875rem;
            word-break: break-word;
        }

        .form-group {
            margin-bottom: 0.875rem;
        }

        .form-label {
            font-size: 0.8125rem;
            margin-bottom: 0.375rem;
        }

        .form-input {
            padding: 0.625rem;
            font-size: 0.8125rem;
        }

        .btn-time-in {
            padding: 0.875rem;
            font-size: 0.9375rem;
            margin-top: 0.75rem;
        }

        .recordings-header {
            padding: 1rem;
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }

        .recordings-header-right {
            width: 100%;
            align-items: flex-start;
        }

        .recordings-date-filter {
            width: 100%;
            align-items: flex-start;
        }

        .recordings-title {
            font-size: 1.125rem;
        }

        .recordings-body {
            padding: 1rem;
        }

        .recordings-grid {
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
        }

        .checkins-panel {
            padding: 1.25rem;
        }

        .checkins-title {
            font-size: 1.125rem;
            margin-bottom: 1rem;
        }

        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin: 0 -1.25rem 1rem;
            padding: 0 1.25rem;
        }

        .checkins-table {
            min-width: 600px;
        }

        .checkins-table th {
            padding: 0.625rem 0.75rem;
            font-size: 0.75rem;
        }

        .checkins-table td {
            padding: 0.75rem;
            font-size: 0.8125rem;
        }

        .pagination-container {
            flex-direction: column;
            align-items: stretch;
            gap: 0.75rem;
            padding-top: 0.75rem;
        }

        .pagination-info {
            text-align: center;
            font-size: 0.8125rem;
        }

        .pagination {
            justify-content: center;
            flex-wrap: wrap;
            gap: 0.25rem;
        }

        .pagination-btn {
            min-width: 36px;
            height: 36px;
            font-size: 0.8125rem;
        }

        .pagination-ellipsis {
            display: none;
        }
    }

    /* Responsive - Small Mobile */
    @media (max-width: 480px) {
        .time-tracking-header {
            margin-bottom: 0.75rem;
        }

        .time-tracking-header .page-title {
            font-size: 1.125rem;
        }

        .time-tracking-container {
            gap: 0.75rem;
        }

        .tracker-header {
            padding: 0.875rem;
            border-radius: 8px;
        }

        .tracker-title {
            font-size: 1rem;
        }

        .btn-start-recording {
            padding: 0.625rem 0.875rem;
            font-size: 0.8125rem;
        }

        .btn-start-recording svg {
            width: 10px;
            height: 10px;
        }

        .tracker-card,
        .recordings-card,
        .checkins-panel {
            padding: 1rem;
            border-radius: 8px;
        }

        .current-time {
            margin-bottom: 0.875rem;
            padding-bottom: 0.875rem;
        }

        .time-label {
            font-size: 0.8125rem;
        }

        .time-value {
            font-size: 0.8125rem;
        }

        .form-group {
            margin-bottom: 0.75rem;
        }

        .form-label {
            font-size: 0.75rem;
        }

        .form-input {
            padding: 0.5rem 0.625rem;
            font-size: 0.75rem;
        }

        .btn-time-in {
            padding: 0.75rem;
            font-size: 0.875rem;
        }

        .recordings-header {
            padding: 0.875rem;
        }

        .recordings-header-right {
            width: 100%;
            align-items: flex-start;
        }

        .recordings-date-filter {
            width: 100%;
            align-items: flex-start;
        }

        .recordings-title {
            font-size: 1rem;
        }

        .recordings-body {
            padding: 0.875rem;
        }

        .recordings-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
        }

        .checkins-panel {
            padding: 1rem;
        }

        .checkins-title {
            font-size: 1rem;
            margin-bottom: 0.875rem;
        }

        .table-container {
            margin: 0 -1rem 1rem;
            padding: 0 1rem;
        }

        .checkins-table {
            min-width: 500px;
        }

        .checkins-table th {
            padding: 0.5rem 0.625rem;
            font-size: 0.6875rem;
        }

        .checkins-table td {
            padding: 0.625rem;
            font-size: 0.75rem;
        }

        .pagination-container {
            padding-top: 0.625rem;
        }

        .pagination-info {
            font-size: 0.75rem;
        }

        .pagination {
            gap: 0.25rem;
        }

        .pagination-btn {
            min-width: 32px;
            height: 32px;
            padding: 0 0.375rem;
            font-size: 0.75rem;
        }

        .pagination-btn svg {
            width: 16px;
            height: 16px;
        }

        /* Hide some pagination buttons on very small screens */
        .pagination-btn:not(.active):not(:first-child):not(:last-child):not(:nth-child(2)):not(:nth-child(3)):not(:nth-last-child(2)):not(:nth-last-child(3)) {
            display: none;
        }
    }

    /* Responsive - Extra Small Mobile */
    @media (max-width: 360px) {
        .tracker-header {
            padding: 0.75rem;
        }

        .tracker-card,
        .recordings-card,
        .checkins-panel {
            padding: 0.875rem;
        }

        .checkins-table {
            min-width: 450px;
        }

        .pagination-btn {
            min-width: 28px;
            height: 28px;
            font-size: 0.6875rem;
        }
    }
</style>
@endpush

@push('scripts')
<script src="{{ asset('js/live-view-signaling.js') }}?v={{ filemtime(public_path('js/live-view-signaling.js')) }}"></script>
<script src="{{ asset('js/live-view-notify.js') }}?v={{ filemtime(public_path('js/live-view-notify.js')) }}"></script>
<script src="{{ asset('js/live-view-audio.js') }}?v={{ filemtime(public_path('js/live-view-audio.js')) }}"></script>
<script src="{{ asset('js/live-view-chat.js') }}?v={{ filemtime(public_path('js/live-view-chat.js')) }}"></script>
<script src="{{ asset('js/live-view-worker.js') }}?v={{ filemtime(public_path('js/live-view-worker.js')) }}"></script>
<script>
    // Global variables
    let isRecordingActive = false; // Master recording session active
    let isCurrentlyRecording = false; // Currently recording a 30-second clip
    let mediaRecorder = null;
    let currentStream = null; // Store the current stream to properly stop it
    let recordedChunks = [];
    let recordingStartTime = null;
    let recordingTimeout = null; // For 30-second recording timer
    let intervalTimer = null; // For 1-hour interval timer
    let nextRecordingTimer = null; // Countdown to next recording
    let currentRecordId = null;
    let currentPage = 1;
    let totalPages = 1;
    let recordingCount = 0; // Number of recordings today
    
    // Constants
    // Per-employee recording clip length (set in User Management). Stored in
    // minutes; fall back to 0.5 min (30 seconds) when not configured.
    const RECORDING_DURATION_MINUTES = Number(@json((float) (auth()->user()->recording_duration_minutes ?? 0.5))) || 0.5;
    const RECORDING_DURATION = Math.max(1, Math.round(RECORDING_DURATION_MINUTES * 60)); // seconds
    const RECORDING_INTERVAL = 60 * 60; // 1 hour in seconds

    function isWorkerClockedIn() {
        const timeInBtn = document.getElementById('timeInBtn');
        return !!(timeInBtn && timeInBtn.classList.contains('out'));
    }

    function updateLiveViewStatusNote() {
        const note = document.getElementById('liveViewStatusText');
        if (!note) return;

        if (isRecordingActive && isWorkerClockedIn()) {
            note.textContent = 'Live viewing is available for admins while this recording session is active.';
            note.style.color = '#059669';
        } else if (isRecordingActive) {
            note.textContent = 'Clock in to make live viewing available to admins during this recording session.';
            note.style.color = '#b45309';
        } else {
            note.textContent = 'Live viewing is available while you are clocked in and your recording session is active.';
            note.style.color = 'var(--text-muted)';
        }
    }

    function updateLiveViewWatchedBanner(watcherNames) {
        const banner = document.getElementById('liveViewWatchedBanner');
        const namesEl = document.getElementById('liveViewWatcherNames');
        const chatRoot = document.getElementById('liveViewWorkerChat');
        if (!banner || !namesEl) return;

        if (!watcherNames || watcherNames.length === 0) {
            banner.style.display = 'none';
            if (chatRoot) chatRoot.style.display = 'none';
            hideLiveViewAudioRequest();
            return;
        }

        const label = watcherNames.length === 1
            ? ` by ${watcherNames[0]}.`
            : ` by ${watcherNames.join(', ')}.`;
        namesEl.textContent = label;
        banner.style.display = 'block';
        if (chatRoot) chatRoot.style.display = 'flex';
    }

    let pendingAudioAdminId = null;
    let workerChatUnread = 0;
    let workerChatOpen = false;

    function showLiveViewAudioRequest(details) {
        const banner = document.getElementById('liveViewAudioRequestBanner');
        const text = document.getElementById('liveViewAudioRequestText');
        if (!banner || !text) return;

        const wasVisible = banner.style.display !== 'none' && banner.style.display !== '';

        pendingAudioAdminId = details.peerId || null;
        const adminName = details.adminName || 'Company Admin';
        text.textContent = `Please enable Audio access as ${adminName} wants to talk with you.`;
        banner.style.display = 'block';

        if (!wasVisible && window.LiveViewNotify) {
            LiveViewNotify.playAudioRequestSound();
        }
    }

    function hideLiveViewAudioRequest() {
        const banner = document.getElementById('liveViewAudioRequestBanner');
        if (banner) banner.style.display = 'none';
        pendingAudioAdminId = null;
    }

    function escapeLiveViewHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function appendWorkerChatMessage(message) {
        const container = document.getElementById('liveViewWorkerChatMessages');
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
            <div>${escapeLiveViewHtml(message.body || '')}</div>
            <span class="live-view-chat-bubble-meta">${escapeLiveViewHtml(message.sender_name || 'User')}${time ? ` · ${time}` : ''}</span>
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

        if (!isMine && !workerChatOpen) {
            workerChatUnread += 1;
            const badge = document.getElementById('liveViewWorkerChatBadge');
            if (badge) {
                badge.style.display = 'inline-flex';
                badge.textContent = String(workerChatUnread);
            }
        }
    }

    function replaceWorkerChatMessage(tempId, message) {
        const container = document.getElementById('liveViewWorkerChatMessages');
        const pending = container?.querySelector(`[data-message-id="${tempId}"]`);
        if (pending) {
            pending.remove();
        }
        appendWorkerChatMessage(message);
    }

    function removeWorkerChatMessage(tempId) {
        const container = document.getElementById('liveViewWorkerChatMessages');
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

    function setWorkerChatSending(sending) {
        const input = document.getElementById('liveViewWorkerChatInput');
        const sendButton = document.getElementById('liveViewWorkerChatSendBtn');
        if (input) {
            input.disabled = sending;
        }
        if (sendButton) {
            sendButton.disabled = sending;
            sendButton.textContent = sending ? 'Sending…' : 'Send';
        }
    }

    async function loadWorkerChatHistory() {
        try {
            const data = await LiveViewWorker.loadChatMessages();
            const container = document.getElementById('liveViewWorkerChatMessages');
            if (!container) return;

            container.innerHTML = '';
            const messages = [...(data.messages || [])].sort((a, b) => a.id - b.id);
            if (!messages.length) {
                container.innerHTML = '<div class="live-view-chat-empty">Messages from your administrator appear here.</div>';
                return;
            }
            messages.forEach(appendWorkerChatMessage);
        } catch (error) {
            console.warn('Failed to load worker chat', error);
        }
    }

    function openWorkerChatPanel() {
        workerChatOpen = true;
        workerChatUnread = 0;
        const panel = document.getElementById('liveViewWorkerChatPanel');
        const badge = document.getElementById('liveViewWorkerChatBadge');
        if (panel) panel.classList.add('open');
        if (badge) badge.style.display = 'none';
        loadWorkerChatHistory();
    }

    function closeWorkerChatPanel() {
        workerChatOpen = false;
        const panel = document.getElementById('liveViewWorkerChatPanel');
        if (panel) panel.classList.remove('open');
    }

    function syncLiveViewWorker() {
        LiveViewWorker.configure({
            getStream: () => currentStream,
            isClockedIn: isWorkerClockedIn,
            isRecordingSessionActive: () => isRecordingActive,
            onWatchStarted: (names) => {
                updateLiveViewWatchedBanner(names);
                loadWorkerChatHistory();
            },
            onWatchEnded: () => {
                updateLiveViewWatchedBanner([]);
                closeWorkerChatPanel();
                hideLiveViewAudioRequest();
            },
            onAudioRequest: (details) => showLiveViewAudioRequest(details),
            onAudioStateChange: (status) => {
                if (status === 'active' || status === 'declined' || status === 'ended') {
                    hideLiveViewAudioRequest();
                }
            },
            onChatMessage: (message) => appendWorkerChatMessage(message),
            onReplaceChatMessage: (tempId, message) => replaceWorkerChatMessage(tempId, message),
            onRemoveChatMessage: (tempId) => removeWorkerChatMessage(tempId),
            onChatSendingChange: (sending) => setWorkerChatSending(sending),
        });

        if (isRecordingActive && isWorkerClockedIn()) {
            window.LiveViewNotify?.prime();
            LiveViewWorker.start();
        } else {
            LiveViewWorker.stop();
        }

        updateLiveViewStatusNote();
    }

    // Human-friendly label for the configured clip length (e.g. "30s", "2 min", "1.5 min")
    function recordingDurationLabel() {
        if (RECORDING_DURATION < 60) {
            return `${RECORDING_DURATION}s`;
        }
        const mins = RECORDING_DURATION_MINUTES % 1 === 0
            ? RECORDING_DURATION_MINUTES
            : RECORDING_DURATION_MINUTES.toFixed(1);
        return `${mins} min`;
    }

    // CSRF Token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    
    // Check if CSRF token exists
    if (!csrfToken) {
        console.error('CSRF token not found! Make sure the meta tag exists in the layout.');
    }
    
    // Company timezone (from server)
    const companyTimezone = @json($companySettings['timezone'] ?? 'America/New_York');

    // Update current date/time (using company timezone)
    function updateDateTime() {
        // Get current time in company timezone
        const now = new Date();
        const dateStr = now.toLocaleDateString('en-US', {
            timeZone: companyTimezone,
            month: 'long',
            day: '2-digit',
            year: 'numeric'
        });
        const timeStr = now.toLocaleTimeString('en-US', {
            timeZone: companyTimezone,
            hour12: false,
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
        document.getElementById('currentDateTime').textContent = `${dateStr} ${timeStr}`;
    }

    // Update date input (using company timezone)
    function updateDate() {
        const companyDateTime = getCompanyDateTime();
        document.getElementById('date').value = companyDateTime.date;
    }

    // Format date for API (Y-m-d)
    function formatDateForAPI(dateStr) {
        const parts = dateStr.split('-');
        return `${parts[2]}-${parts[0]}-${parts[1]}`;
    }

    // Convert browser local time to company timezone
    function getCompanyDateTime() {
        const now = new Date();
        // Format date/time in company timezone
        const dateStr = now.toLocaleDateString('en-US', {
            timeZone: companyTimezone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        });
        const timeStr = now.toLocaleTimeString('en-US', {
            timeZone: companyTimezone,
            hour12: false,
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
        
        // Parse date string (format: MM/DD/YYYY) and convert to MM-DD-YYYY
        const dateParts = dateStr.split('/');
        const formattedDate = `${dateParts[0]}-${dateParts[1]}-${dateParts[2]}`;
        
        return {
            date: formattedDate,
            time: timeStr,
            dateForAPI: `${dateParts[2]}-${dateParts[0]}-${dateParts[1]}` // Y-m-d format
        };
    }

    // Start Screen Recording Session (Master Control)
    async function startRecordingSession() {
        isRecordingActive = true;
        updateRecordingStatus('Recording session started');
        
        // Update button
        const btn = document.getElementById('startRecordingBtn');
            btn.classList.add('recording');
            btn.innerHTML = `
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <rect x="6" y="6" width="12" height="12" rx="2"/>
                </svg>
                Stop Recording
            `;

        // Start first recording immediately
        await startSingleRecording();
        syncLiveViewWorker();
    }

    // Stop Screen Recording Session
    async function stopRecordingSession() {
        isRecordingActive = false;
        isCurrentlyRecording = false;
        
        // Clear all timers
        if (recordingTimeout) {
            clearTimeout(recordingTimeout);
            recordingTimeout = null;
        }
        if (intervalTimer) {
            clearTimeout(intervalTimer);
            intervalTimer = null;
        }
        if (nextRecordingTimer) {
            clearInterval(nextRecordingTimer);
            nextRecordingTimer = null;
        }

        // Stop current recording if active
        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            mediaRecorder.stop();
        }

        // Stop stream tracks
        if (currentStream) {
            currentStream.getTracks().forEach(track => track.stop());
            currentStream = null;
        }

        updateRecordingStatus('Recording session stopped');
        resetRecordingButton();
        document.getElementById('nextRecordingCountdown').textContent = '';
        
        // Stop recording on backend if there's an active record
        if (currentRecordId) {
            const duration = isCurrentlyRecording ? Math.floor((Date.now() - (recordingStartTime || Date.now())) / 1000) : RECORDING_DURATION;
            const nextRecordingTime = new Date(Date.now() + (RECORDING_INTERVAL * 1000));
            await stopRecordingOnBackend(duration, currentRecordId, nextRecordingTime.toISOString());
            currentRecordId = null;
        }
        
        // Stop the recording session on backend
        try {
            const date = document.getElementById('date').value;
            await fetch('/api/time-tracking/stop-recording-session', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    date: formatDateForAPI(date)
                })
            });
        } catch (error) {
            console.error('Error stopping recording session:', error);
        }
        
        // Clear sessionStorage
        sessionStorage.removeItem('recordingSessionActive');
        sessionStorage.removeItem('nextRecordingTime');
        
        isCurrentlyRecording = false;
        await LiveViewWorker.stop();
        updateLiveViewStatusNote();
    }

    // Start a single 30-second recording
    async function startSingleRecording() {
        if (!isRecordingActive) return;
        
        // CRITICAL: Prevent starting if already recording
        if (isCurrentlyRecording) {
            console.warn('Already recording, skipping startSingleRecording');
            return;
        }
        
        // CRITICAL: Prevent starting if countdown timer is still active
        // This ensures we wait for the full 1-hour interval before next recording
        if (nextRecordingTimer) {
            console.warn('Countdown timer still active, skipping startSingleRecording. Wait for countdown to finish.');
            return;
        }
        
        // CRITICAL: Ensure no active MediaRecorder before starting
        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            console.warn('MediaRecorder still active, stopping before starting new recording');
            try {
                mediaRecorder.stop();
            } catch (e) {
                console.error('Error stopping existing MediaRecorder:', e);
            }
            // Wait a bit for the stop to complete
            await new Promise(resolve => setTimeout(resolve, 500));
        }

        try {
            // Request screen sharing permission (only first time or if stream ended)
            if (!currentStream || currentStream.getVideoTracks().some(track => track.readyState === 'ended')) {
                const stream = await navigator.mediaDevices.getDisplayMedia({
                    video: {
                        mediaSource: 'screen',
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    },
                    audio: true
                });
                
                currentStream = stream; // Store stream reference

                // Handle stream ended (user stops sharing)
                stream.getVideoTracks()[0].addEventListener('ended', async () => {
                    if (isRecordingActive) {
                        // User stopped sharing - stop current recording
                        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                            mediaRecorder.stop();
                        }
                        currentStream = null;
                        isCurrentlyRecording = false;
                        await LiveViewWorker.clearHeartbeat();
                        syncLiveViewWorker();
                        updateLiveViewWatchedBanner([]);
                        
                        // Try to restart after a short delay
                        setTimeout(() => {
                            if (isRecordingActive) {
                                startSingleRecording();
                            }
                        }, 5000);
                    }
                });
            }

            // Check if MediaRecorder supports the mimeType
            let mimeType = 'video/webm;codecs=vp9';
            if (!MediaRecorder.isTypeSupported(mimeType)) {
                mimeType = 'video/webm;codecs=vp8';
                if (!MediaRecorder.isTypeSupported(mimeType)) {
                    mimeType = 'video/webm';
                }
            }

            // Create new MediaRecorder for this 30-second clip (reuse stream)
            // Use lower bitrate to reduce file size
            const recorderOptions = {
                mimeType: mimeType,
                videoBitsPerSecond: 2500000 // 2.5 Mbps (reduced from default ~8-10 Mbps)
            };
            mediaRecorder = new MediaRecorder(currentStream, recorderOptions);

            recordedChunks = [];
            recordingStartTime = Date.now();

            mediaRecorder.ondataavailable = (event) => {
                if (event.data && event.data.size > 0) {
                    recordedChunks.push(event.data);
                    console.log('Data chunk received. Size:', event.data.size, 'bytes. Total chunks:', recordedChunks.length);
                }
            };

            mediaRecorder.onstop = async () => {
                console.log('MediaRecorder stopped. Chunks:', recordedChunks.length, 'Total size:', recordedChunks.reduce((sum, chunk) => sum + chunk.size, 0));
                
                // CRITICAL: Set recording flag to false immediately when stopped
                isCurrentlyRecording = false;
                
                const blob = new Blob(recordedChunks, { type: mimeType.split(';')[0] });
                const duration = RECORDING_DURATION;
                
                console.log('Blob created. Size:', blob.size, 'bytes, Type:', blob.type);
                console.log('Current Record ID:', currentRecordId);

                // Save record ID before it gets cleared
                const recordIdToUpload = currentRecordId;

                // Upload recording if we have a record ID and blob has data
                if (recordIdToUpload) {
                    if (blob.size > 0) {
                        console.log('Uploading recording...');
                        await uploadRecording(blob, duration, recordIdToUpload);
        } else {
                        console.warn('Blob is empty, skipping upload. This might indicate MediaRecorder did not capture any data.');
                    }
                    
                    // Stop recording on backend
                    console.log('Stopping recording on backend...');
                    await stopRecordingOnBackend(duration, recordIdToUpload);
                } else {
                    console.warn('No record ID available, cannot upload or stop recording');
                }

                // Clear the record ID after processing (but keep session active)
                if (currentRecordId === recordIdToUpload) {
                    currentRecordId = null;
                }
                
                // Clear sessionStorage entry for this recording
                sessionStorage.removeItem('nextRecordingTime');
                
                // IMPORTANT: Do NOT stop the stream here.
                // We keep the existing screen-capture stream alive so the browser
                // does not prompt "Choose what to share" again for each clip.
                // Only the MediaRecorder is cleared; the next clip will reuse the same stream.
                mediaRecorder = null;

                // Load today's recordings
                loadTodayRecordings();
                loadRecords(currentPage);

                // Stop stream tracks if recording session is not active
                if (!isRecordingActive && currentStream) {
                    currentStream.getTracks().forEach(track => track.stop());
                    currentStream = null;
                }
                
                // CRITICAL: Schedule next recording AFTER everything is cleaned up
                // This ensures we're in a clean state before starting countdown
                if (isRecordingActive && !isCurrentlyRecording) {
                    scheduleNextRecording();
                }
            };

            // Start recording on backend
            const date = document.getElementById('date').value;
            const response = await fetch('/api/time-tracking/start-recording', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    date: formatDateForAPI(date)
                })
            });

            // Check if response is OK and JSON
            if (!response.ok) {
                const text = await response.text();
                console.error('Server error:', response.status, text);
                throw new Error(`Server error: ${response.status}. ${text.substring(0, 100)}`);
            }

            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Non-JSON response:', text.substring(0, 500));
                throw new Error('Server returned non-JSON response. Check console for details.');
            }

            const data = await response.json();
            if (data.success) {
                currentRecordId = data.record.id;
                isCurrentlyRecording = true;
                recordingCount++;
                updateRecordingStatus(`Recording clip #${recordingCount} (${recordingDurationLabel()})`);
                
                // Start recording
                mediaRecorder.start(1000); // Collect data every second

                // Set timer to stop after 30 seconds
                recordingTimeout = setTimeout(async () => {
                    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                        // Don't clear currentRecordId here - let onstop handler do it after upload
                        console.log('30-second timer expired, stopping MediaRecorder');
                        mediaRecorder.stop();
                    }
                    // Note: isCurrentlyRecording will be set to false in onstop handler
                    // Don't set it here to avoid race conditions

                    // Schedule next recording in 1 hour (after upload completes)
                    // This will be called after onstop handler completes
                    // We'll call it from onstop handler instead to ensure proper sequencing
                }, RECORDING_DURATION * 1000);

            } else {
                alert(data.message || 'Failed to start recording');
                isCurrentlyRecording = false;
            }

        } catch (error) {
            console.error('Error starting recording:', error);
            if (error.name === 'NotAllowedError' || error.name === 'PermissionDeniedError') {
                alert('Screen recording permission denied. Please allow screen sharing to continue.');
                stopRecordingSession();
            } else {
                updateRecordingStatus('Error: ' + error.message);
                // Try again after 5 seconds
                if (isRecordingActive) {
                    setTimeout(() => startSingleRecording(), 5000);
                }
            }
        }
    }

    // Schedule next recording after 1-hour interval
    function scheduleNextRecording() {
        // Clear any existing timers first to prevent overlapping timers
        if (nextRecordingTimer) {
            clearInterval(nextRecordingTimer);
            nextRecordingTimer = null;
        }
        if (intervalTimer) {
            clearTimeout(intervalTimer);
            intervalTimer = null;
        }
        
        // CRITICAL: Stop MediaRecorder if it's still recording
        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            console.log('Stopping MediaRecorder before countdown');
            try {
                mediaRecorder.stop();
            } catch (e) {
                console.error('Error stopping MediaRecorder:', e);
            }
        }
        
        // We keep the screen-capture stream alive between clips so that
        // the browser does not ask "Choose what to share" again.
        // Just make sure we are not currently recording.
        
        // Ensure recording flag is false
        isCurrentlyRecording = false;
        mediaRecorder = null;
        
        updateRecordingStatus(`Waiting for next recording... (${recordingDurationLabel()} clips)`);
        const nextRecordingTime = Date.now() + (RECORDING_INTERVAL * 1000);
        
        // Store next recording time in sessionStorage for page reload persistence
        sessionStorage.setItem('nextRecordingTime', nextRecordingTime.toString());
        sessionStorage.setItem('recordingSessionActive', 'true');
        
        let countdown = RECORDING_INTERVAL;

        // Update countdown display
        nextRecordingTimer = setInterval(() => {
            const minutes = Math.floor(countdown / 60);
            const seconds = countdown % 60;
            document.getElementById('nextRecordingCountdown').textContent = 
                `Next recording in: ${minutes}m ${seconds}s`;

            // When countdown reaches zero, immediately start the next recording
            if (countdown <= 0) {
                clearInterval(nextRecordingTimer);
                nextRecordingTimer = null;
                document.getElementById('nextRecordingCountdown').textContent = '';
                if (isRecordingActive && !isCurrentlyRecording) {
                    startSingleRecording();
                }
                return;
            }

            countdown--;
        }, 1000);

        // Fallback timer
        intervalTimer = setTimeout(() => {
            if (isRecordingActive && !isCurrentlyRecording) {
                // Clear the countdown timer if it's still running
                if (nextRecordingTimer) {
                    clearInterval(nextRecordingTimer);
                    nextRecordingTimer = null;
                }
                document.getElementById('nextRecordingCountdown').textContent = '';
                startSingleRecording();
            }
        }, RECORDING_INTERVAL * 1000);
    }

    // Update recording status display
    function updateRecordingStatus(status) {
        const statusEl = document.getElementById('recordingStatusText');
        if (statusEl) {
            statusEl.textContent = status;
            statusEl.style.color = isRecordingActive ? '#059669' : 'var(--text-secondary)';
        }
    }

    // Reset recording button UI
    function resetRecordingButton() {
        const btn = document.getElementById('startRecordingBtn');
            btn.classList.remove('recording');
            btn.innerHTML = `
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <circle cx="12" cy="12" r="10"/>
                </svg>
                Start Recording
            `;
    }

    // Stop recording on backend
    async function stopRecordingOnBackend(duration, recordId = null, nextRecordingAt = null) {
        try {
            const recordIdToUse = recordId || currentRecordId;
            if (!recordIdToUse) {
                console.warn('No record ID provided for stopRecordingOnBackend');
                return;
            }

            const body = {
                record_id: recordIdToUse,
                duration: duration
            };
            
            if (nextRecordingAt) {
                body.next_recording_at = nextRecordingAt;
            }

            const response = await fetch('/api/time-tracking/stop-recording', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(body)
            });

            if (!response.ok) {
                const text = await response.text();
                console.error('Server error stopping recording:', response.status, text);
                return;
            }

            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Non-JSON response when stopping recording:', text.substring(0, 500));
                return;
            }

            const data = await response.json();
            if (data.success) {
                console.log('Recording stopped successfully on backend');
                loadRecords(currentPage); // Refresh records
                loadTodayRecordings(); // Refresh today's recordings
            } else {
                console.error('Failed to stop recording:', data.message);
            }
        } catch (error) {
            console.error('Error stopping recording:', error);
        }
    }

    // Upload recording file
    async function uploadRecording(blob, duration, recordId = null) {
        try {
            const recordIdToUse = recordId || currentRecordId;
            if (!recordIdToUse) {
                console.error('No record ID provided for uploadRecording');
                return;
            }

            if (blob.size === 0) {
                console.error('Cannot upload empty blob');
                return;
            }

            const formData = new FormData();
            formData.append('recording', blob, `recording-${Date.now()}.webm`);
            formData.append('record_id', recordIdToUse);

            console.log('Uploading recording file. Size:', blob.size, 'bytes, Record ID:', recordIdToUse);

            const response = await fetch('/api/time-tracking/upload-recording', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: formData
            });

            if (!response.ok) {
                const text = await response.text();
                console.error('Server error uploading recording:', response.status, text);
                return;
            }

            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Non-JSON response when uploading recording:', text.substring(0, 500));
                return;
            }

            const data = await response.json();
            if (data.success) {
                console.log('Recording uploaded successfully:', data.message);
            } else {
                console.error('Failed to upload recording:', data.message);
            }
        } catch (error) {
            console.error('Error uploading recording:', error);
        }
    }

    // Start/Stop Recording Button Click
    document.getElementById('startRecordingBtn').addEventListener('click', async function() {
        if (!isRecordingActive) {
            await startRecordingSession();
        } else {
            await stopRecordingSession();
        }
    });

    // Time In/Out Button
    document.getElementById('timeInBtn').addEventListener('click', async function() {
        const btn = this;
        const timeInInput = document.getElementById('timeIn');
        const timeOutInput = document.getElementById('timeOut');
        const dateInput = document.getElementById('date');
        // Get current date/time in company timezone
        const companyDateTime = getCompanyDateTime();
        const timeStr = companyDateTime.time;

        // Check current state
        const hasTimeIn = timeInInput.value && timeInInput.value !== '--:--:--';
        const hasTimeOut = timeOutInput.value && timeOutInput.value !== '--:--:--';

        try {
            if (!hasTimeIn) {
            // Time In
                const response = await fetch('/api/time-tracking/time-in', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        date: companyDateTime.dateForAPI,
                        time: timeStr
                    })
                });

                // Check if response is OK and JSON
                if (!response.ok) {
                    const text = await response.text();
                    console.error('Server error:', response.status, text);
                    throw new Error(`Server error: ${response.status}. ${text.substring(0, 100)}`);
                }

                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    console.error('Non-JSON response:', text.substring(0, 500));
                    throw new Error('Server returned non-JSON response. Check console for details.');
                }

                const data = await response.json();
                if (data.success) {
            timeInInput.value = timeStr;
            btn.textContent = 'Time Out';
            btn.classList.add('out');
                    syncLiveViewWorker();
                    loadRecords(currentPage); // Refresh records
                } else {
                    alert(data.message || 'Failed to record time in');
                }
            } else if (!hasTimeOut) {
            // Time Out
                const response = await fetch('/api/time-tracking/time-out', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        date: companyDateTime.dateForAPI,
                        time: timeStr
                    })
                });

                // Check if response is OK and JSON
                if (!response.ok) {
                    const text = await response.text();
                    console.error('Server error:', response.status, text);
                    throw new Error(`Server error: ${response.status}. ${text.substring(0, 100)}`);
                }

                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    console.error('Non-JSON response:', text.substring(0, 500));
                    throw new Error('Server returned non-JSON response. Check console for details.');
                }

                const data = await response.json();
                if (data.success) {
            timeOutInput.value = timeStr;
            btn.textContent = 'Time In';
            btn.classList.remove('out');
                    await LiveViewWorker.stop();
                    updateLiveViewStatusNote();
                    loadRecords(currentPage); // Refresh records
                    // Update hours if available
                    if (data.hours_worked) {
                        // Could update a display element if needed
                    }
                } else {
                    alert(data.message || 'Failed to record time out');
                }
        } else {
            // Reset for new entry
            timeInInput.value = timeStr;
            timeOutInput.value = '--:--:--';
            btn.textContent = 'Time Out';
            btn.classList.add('out');
                
                // Time In again
                const response = await fetch('/api/time-tracking/time-in', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        date: companyDateTime.dateForAPI,
                        time: timeStr
                    })
                });

                // Check if response is OK and JSON
                if (!response.ok) {
                    const text = await response.text();
                    console.error('Server error:', response.status, text);
                    throw new Error(`Server error: ${response.status}. ${text.substring(0, 100)}`);
                }

                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    console.error('Non-JSON response:', text.substring(0, 500));
                    throw new Error('Server returned non-JSON response. Check console for details.');
                }

                const data = await response.json();
                if (data.success) {
                    syncLiveViewWorker();
                    loadRecords(currentPage);
                }
            }
        } catch (error) {
            console.error('Error with time in/out:', error);
            alert('An error occurred. Please try again.');
        }
    });

    // Load records from backend
    async function loadRecords(page = 1) {
        try {
            const response = await fetch(`/api/time-tracking/records?page=${page}&per_page=10`, {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                const text = await response.text();
                console.error('Server error loading records:', response.status, text);
                return;
            }

            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Non-JSON response when loading records:', text.substring(0, 500));
                return;
            }

            const data = await response.json();
            console.log('Records API response:', data);
            if (data.success) {
                renderRecords(data.data || []);
                if (data.pagination) {
                    console.log('Pagination data received:', data.pagination);
                    updatePagination(data.pagination);
                    currentPage = page;
                    totalPages = data.pagination.last_page;
                } else {
                    console.warn('No pagination data in response, using fallback');
                    // Fallback if pagination is missing
                    updatePagination({
                        current_page: page,
                        per_page: 10,
                        total: data.data ? data.data.length : 0,
                        last_page: 1
                    });
                }
            } else {
                console.error('Failed to load records:', data.message);
            }
        } catch (error) {
            console.error('Error loading records:', error);
        }
    }

    // Render records in table
    function renderRecords(records) {
        const tbody = document.querySelector('.checkins-table tbody');
        if (!tbody) return;

        if (records.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 2rem;">No records found</td></tr>';
            return;
        }

        tbody.innerHTML = records.map(record => `
            <tr>
                <td>${record.date}</td>
                <td>${record.time_in}</td>
                <td>${record.time_out}</td>
                <td>${record.hours}</td>
                
            </tr>
        `).join('');
    }

    // Update pagination
    function updatePagination(pagination) {
        // Use specific IDs to avoid conflicts
        const infoEl = document.getElementById('timeTrackingPaginationInfo');
        const paginationEl = document.getElementById('timeTrackingPaginationButtons');

        if (!pagination) {
            console.error('Pagination data is missing');
            if (infoEl) infoEl.textContent = 'No pagination data';
            return;
        }

        if (!infoEl) {
            console.error('Pagination info element not found');
            return;
        }

        if (!paginationEl) {
            console.error('Pagination buttons element not found');
            return;
        }

        console.log('Updating pagination:', pagination);

        // Update pagination info
        if (pagination.total > 0) {
            const start = ((pagination.current_page - 1) * pagination.per_page) + 1;
            const end = Math.min(pagination.current_page * pagination.per_page, pagination.total);
            infoEl.textContent = `Showing ${start} to ${end} of ${pagination.total} results`;
        } else {
            infoEl.textContent = 'No results found';
        }


        let html = '';

        // Previous button
        html += `
            <button class="pagination-btn" ${pagination.current_page === 1 ? 'disabled' : ''} data-page="${pagination.current_page - 1}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
            </button>
        `;

        // Calculate which page numbers to show
        const maxVisible = 7; // Show max 7 page numbers
        let startPage = Math.max(1, pagination.current_page - Math.floor(maxVisible / 2));
        let endPage = Math.min(pagination.last_page, startPage + maxVisible - 1);

        // Adjust startPage if we're near the end
        if (endPage - startPage < maxVisible - 1) {
            startPage = Math.max(1, endPage - maxVisible + 1);
        }

        // Show first page if not in visible range
        if (startPage > 1) {
            html += `<button class="pagination-btn" data-page="1">1</button>`;
            if (startPage > 2) {
                html += `<span class="pagination-ellipsis">...</span>`;
            }
        }

        // Show page numbers in range
        for (let i = startPage; i <= endPage; i++) {
            html += `
                <button class="pagination-btn ${i === pagination.current_page ? 'active' : ''}" data-page="${i}">
                    ${i}
                </button>
            `;
        }

        // Show last page if not in visible range
        if (endPage < pagination.last_page) {
            if (endPage < pagination.last_page - 1) {
                html += `<span class="pagination-ellipsis">...</span>`;
            }
            html += `<button class="pagination-btn" data-page="${pagination.last_page}">${pagination.last_page}</button>`;
        }

        // Next button
        html += `
            <button class="pagination-btn" ${pagination.current_page === pagination.last_page ? 'disabled' : ''} data-page="${pagination.current_page + 1}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"/>
                </svg>
            </button>
        `;

        paginationEl.innerHTML = html;

        // Add event listeners to pagination buttons
        paginationEl.querySelectorAll('.pagination-btn:not([disabled])').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const page = parseInt(btn.getAttribute('data-page'));
                if (page && page !== currentPage && page >= 1 && page <= pagination.last_page) {
                    loadRecords(page);
                }
            });
        });
    }

    // Load active record for today
    async function loadActiveRecord() {
        try {
            const dateInput = document.getElementById('date');
            const response = await fetch(`/api/time-tracking/active-record?date=${formatDateForAPI(dateInput.value)}`, {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                }
            });

            const data = await response.json();
            
            // Handle auto-timeout message
            if (data.success && data.auto_timeout && data.auto_timeout_message) {
                alert(data.auto_timeout_message);
                // Reload records to show the completed record
                loadRecords(currentPage);
            }
            
            if (data.success && data.record) {
                const record = data.record;
                const timeInInput = document.getElementById('timeIn');
                const timeOutInput = document.getElementById('timeOut');
                const timeInBtn = document.getElementById('timeInBtn');
                const dateInput = document.getElementById('date');

                if (record.time_in) {
                    // Update date input to show the date from the record (for cross-day scenarios)
                    if (record.date) {
                        // Convert MM-DD-YYYY to MM-DD-YYYY format (already correct)
                        dateInput.value = record.date;
                    }
                    
                    timeInInput.value = record.time_in;
                    if (record.time_out) {
                        timeOutInput.value = record.time_out;
                        timeInBtn.textContent = 'Time In';
                        timeInBtn.classList.remove('out');
                    } else {
                        timeOutInput.value = '--:--:--';
                        timeInBtn.textContent = 'Time Out';
                        timeInBtn.classList.add('out');
                        
                        // Check if approaching 12 hours and show warning
                        checkTimeLimit(record.date, record.time_in);
                    }
                }

                // Resume recording session if it was active
                if (data.session_active || record.is_recording) {
                    isRecordingActive = true;
                    const btn = document.getElementById('startRecordingBtn');
                    btn.classList.add('recording');
                    btn.innerHTML = `
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <rect x="6" y="6" width="12" height="12" rx="2"/>
                        </svg>
                        Stop Recording
                    `;
                    
                    // Check if we need to resume recording cycle
                    const nextRecordingTime = data.next_recording_at ? new Date(data.next_recording_at).getTime() : null;
                    const now = Date.now();
                    
                    if (nextRecordingTime && nextRecordingTime > now) {
                        // Clear any existing timers first to prevent overlapping timers
                        if (nextRecordingTimer) {
                            clearInterval(nextRecordingTimer);
                            nextRecordingTimer = null;
                        }
                        if (intervalTimer) {
                            clearTimeout(intervalTimer);
                            intervalTimer = null;
                        }
                        
                        // Ensure we are not mid-recording during countdown.
                        // Keep the existing screen-capture stream alive so the browser
                        // does not ask "Choose what to share" again when the next clip starts.
                        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                            console.log('Stopping MediaRecorder during resume countdown');
                            try {
                                mediaRecorder.stop();
                            } catch (e) {
                                console.error('Error stopping MediaRecorder:', e);
                            }
                        }
                        isCurrentlyRecording = false;
                        mediaRecorder = null;
                        
                        // Calculate time until next recording
                        const timeUntilNext = Math.floor((nextRecordingTime - now) / 1000);
                        updateRecordingStatus(`Recording session active - waiting for next recording... (${recordingDurationLabel()} clips)`);
                        
                        // Schedule next recording
                        let countdown = timeUntilNext;
                        nextRecordingTimer = setInterval(() => {
                            const minutes = Math.floor(countdown / 60);
                            const seconds = countdown % 60;
                            document.getElementById('nextRecordingCountdown').textContent = 
                                `Next recording in: ${minutes}m ${seconds}s`;
                            
                            // When countdown reaches zero, immediately start the next recording
                            if (countdown <= 0) {
                                clearInterval(nextRecordingTimer);
                                nextRecordingTimer = null;
                                document.getElementById('nextRecordingCountdown').textContent = '';
                                if (isRecordingActive && !isCurrentlyRecording) {
                                    startSingleRecording();
                                }
                                return;
                            }

                            countdown--;
                        }, 1000);
                        
                        // Fallback timer
                        intervalTimer = setTimeout(() => {
                            if (isRecordingActive && !isCurrentlyRecording) {
                                // Clear the countdown timer if it's still running
                                if (nextRecordingTimer) {
                                    clearInterval(nextRecordingTimer);
                                    nextRecordingTimer = null;
                                }
                                document.getElementById('nextRecordingCountdown').textContent = '';
                                startSingleRecording();
                            }
                        }, timeUntilNext * 1000);
                    } else if (!data.next_recording_at || nextRecordingTime <= now) {
                        // Start recording immediately if it's time or no next time set
                        // But ensure we're not already recording
                        if (!isCurrentlyRecording) {
                            updateRecordingStatus('Recording session active - starting recording...');
                            setTimeout(() => {
                                if (isRecordingActive && !isCurrentlyRecording) {
                                    startSingleRecording();
                                }
                            }, 1000);
                        }
                    } else {
                        updateRecordingStatus('Recording session active');
                    }

                    syncLiveViewWorker();
                }
            }
        } catch (error) {
            console.error('Error loading active record:', error);
        }
    }

    // Load today's recordings
    async function loadTodayRecordings() {
        try {
            // Use date filter if available, otherwise use the date input
            const dateFilterInput = document.getElementById('recordingsDateFilter');
            const dateInput = document.getElementById('date');
            const selectedDate = dateFilterInput && dateFilterInput.value 
                ? dateFilterInput.value 
                : formatDateForAPI(dateInput.value);
            
            const response = await fetch(`/api/time-tracking/today-recordings?date=${selectedDate}`, {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                }
            });

            const data = await response.json();
            if (data.success) {
                renderTodayRecordings(data.recordings);
                recordingCount = data.recordings.length;
            }
        } catch (error) {
            console.error('Error loading today recordings:', error);
        }
    }

    // Convert date from YYYY-MM-DD (date input) to Y-m-d format for API
    function formatDateForAPIFromDateInput(dateStr) {
        if (!dateStr) return '';
        // dateStr is in YYYY-MM-DD format from date input
        return dateStr; // API expects Y-m-d format which is the same
    }

    // Render today's recordings
    function renderTodayRecordings(recordings) {
        const contentEl = document.getElementById('recordingsContent');
        const metaEl = document.getElementById('recordingsMeta');
        const dateFilterInput = document.getElementById('recordingsDateFilter');
        if (!contentEl) return;

        // Update meta with selected date
        if (metaEl) {
            const selectedDate = dateFilterInput && dateFilterInput.value 
                ? new Date(dateFilterInput.value).toLocaleDateString('en-US', { 
                    month: 'long', 
                    day: 'numeric', 
                    year: 'numeric' 
                })
                : 'today';
            metaEl.textContent = `${recordings.length} recording${recordings.length !== 1 ? 's' : ''} ${selectedDate === 'today' ? 'today' : `on ${selectedDate}`}`;
        }

        if (recordings.length === 0) {
            contentEl.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; padding: 2rem; color: var(--text-muted);">No recordings today</div>';
            return;
        }

        contentEl.innerHTML = recordings.map((rec, index) => {
            const recordingNumber = recordings.length - index;
            const recordingUrl = rec.has_recording ? `/api/time-tracking/recording/${rec.id}/view` : '#';
            
            return `
                <div class="recording-item" ${rec.has_recording ? `onclick="viewRecording(${rec.id})"` : ''}>
                    <div class="recording-thumbnail">
                        ${rec.has_recording ? `
                            <video muted loop preload="metadata">
                                <source src="${recordingUrl}" type="video/webm">
                                <source src="${recordingUrl}" type="video/mp4">
                            </video>
                        ` : `
                            <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='300' height='200'%3E%3Crect fill='%23e5e7eb' width='300' height='200'/%3E%3Ccircle cx='150' cy='100' r='30' fill='%235f61e6'/%3E%3Cpolygon points='140,90 140,110 160,100' fill='white'/%3E%3C/svg%3E" alt="Video">
                        `}
                        <div class="recording-overlay">
                            <svg viewBox="0 0 24 24" fill="white">
                                <polygon points="9 5 9 19 19 12"/>
                            </svg>
                        </div>
                        <div class="recording-duration">${rec.duration_formatted || '00:00'}</div>
                    </div>
                    <div class="recording-info">
                        <div class="recording-time">Recording #${recordingNumber}</div>
                        <div class="recording-date">${rec.date || 'N/A'} • ${rec.time || 'N/A'}</div>
                        <div class="recording-status-badge ${rec.has_recording ? 'saved' : 'processing'}">
                            ${rec.has_recording ? '✓ Saved' : '⏳ Processing'}
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    // View recording video
    function viewRecording(recordId) {
        const url = `/api/time-tracking/recording/${recordId}/view`;
        window.open(url, '_blank');
    }

    // Check if approaching 12-hour time limit
    function checkTimeLimit(dateStr, timeInStr) {
        if (!dateStr || !timeInStr) return;
        
        try {
            // Parse date and time (dateStr format: MM-DD-YYYY, timeInStr format: HH:MM:SS)
            const dateParts = dateStr.split('-');
            const timeParts = timeInStr.split(':');
            
            // Create date object for time in
            const timeInDate = new Date(
                parseInt(dateParts[2]), // year
                parseInt(dateParts[0]) - 1, // month (0-indexed)
                parseInt(dateParts[1]), // day
                parseInt(timeParts[0]), // hour
                parseInt(timeParts[1]), // minute
                parseInt(timeParts[2]) // second
            );
            
            const now = new Date();
            const hoursElapsed = (now - timeInDate) / (1000 * 60 * 60); // Convert to hours
            const hoursRemaining = 12 - hoursElapsed;
            
            // Show warning if less than 1 hour remaining
            if (hoursRemaining > 0 && hoursRemaining <= 1) {
                const minutesRemaining = Math.floor(hoursRemaining * 60);
                console.warn(`Warning: Auto-timeout in approximately ${minutesRemaining} minutes`);
            }
            
            // Auto-timeout check happens on the backend, but we can refresh periodically
            // Set up a periodic check (every 5 minutes) to detect auto-timeout
            if (hoursRemaining > 0 && hoursRemaining <= 0.5) {
                // Check every minute when close to limit
                setTimeout(() => {
                    loadActiveRecord();
                }, 60000); // Check again in 1 minute
            }
        } catch (error) {
            console.error('Error checking time limit:', error);
        }
    }

    // Get today's date in company timezone formatted for date input (YYYY-MM-DD)
    function getCompanyDateForInput() {
        const now = new Date();
        // Use Intl.DateTimeFormat to get date parts in company timezone
        const formatter = new Intl.DateTimeFormat('en-US', {
            timeZone: companyTimezone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        });
        
        // Format the date
        const parts = formatter.formatToParts(now);
        const year = parts.find(p => p.type === 'year').value;
        const month = parts.find(p => p.type === 'month').value;
        const day = parts.find(p => p.type === 'day').value;
        
        // Format as YYYY-MM-DD
        return `${year}-${month}-${day}`;
    }

    // Initialize date filter with today's date (in company timezone)
    function initializeRecordingsDateFilter() {
        const dateFilterInput = document.getElementById('recordingsDateFilter');
        if (dateFilterInput) {
            // Get today's date in company timezone in YYYY-MM-DD format
            const todayStr = getCompanyDateForInput();
            dateFilterInput.value = todayStr;
            
            // Add event listener for date changes
            dateFilterInput.addEventListener('change', function(e) {
                loadTodayRecordings();
            });
        }
    }

    document.getElementById('liveViewEnableAudioBtn')?.addEventListener('click', async () => {
        if (!pendingAudioAdminId) return;
        try {
            await LiveViewWorker.enableAudioForAdmin(pendingAudioAdminId);
            hideLiveViewAudioRequest();
        } catch (error) {
            console.error('Failed to enable audio', error);
            alert(error.message || 'Unable to enable microphone.');
        }
    });

    document.getElementById('liveViewDeclineAudioBtn')?.addEventListener('click', async () => {
        if (!pendingAudioAdminId) {
            hideLiveViewAudioRequest();
            return;
        }
        try {
            await LiveViewWorker.declineAudioForAdmin(pendingAudioAdminId);
        } catch (error) {
            console.warn('Failed to decline audio', error);
        } finally {
            hideLiveViewAudioRequest();
        }
    });

    document.getElementById('liveViewWorkerChatToggle')?.addEventListener('click', () => {
        const panel = document.getElementById('liveViewWorkerChatPanel');
        if (panel?.classList.contains('open')) {
            closeWorkerChatPanel();
        } else {
            openWorkerChatPanel();
        }
    });

    document.getElementById('liveViewWorkerChatCloseBtn')?.addEventListener('click', closeWorkerChatPanel);

    document.getElementById('liveViewWorkerChatForm')?.addEventListener('submit', async (event) => {
        event.preventDefault();

        const input = document.getElementById('liveViewWorkerChatInput');
        if (!input || LiveViewWorker.isChatSending()) {
            return;
        }

        const body = input.value.trim();
        if (!body) return;

        input.value = '';

        try {
            await LiveViewWorker.sendChatMessage(body);
        } catch (error) {
            console.error('Failed to send worker chat message', error);
            input.value = body;
            alert(error.message || 'Failed to send message.');
        }
    });

    // Initialize
    updateDateTime();
    updateDate();
    setInterval(updateDateTime, 1000);
    initializeRecordingsDateFilter();
    loadRecords(1);
    loadActiveRecord();
    loadTodayRecordings();
    updateLiveViewStatusNote();
    
    // Periodically check for auto-timeout (every 5 minutes)
    setInterval(() => {
        const timeInBtn = document.getElementById('timeInBtn');
        if (timeInBtn && timeInBtn.classList.contains('out')) {
            // User is currently timed in, check for auto-timeout
            loadActiveRecord();
        }
    }, 5 * 60 * 1000); // Check every 5 minutes
</script>
@endpush

