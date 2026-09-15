@extends('layouts.app')

@section('title', 'Calendar')

@section('content')
    @if(session('status') === 'google-calendar-connected')
        <div class="calendar-alert success">Google Calendar connected successfully.</div>
    @endif
    @if(session('status') === 'outlook-calendar-connected')
        <div class="calendar-alert success">Outlook Calendar connected successfully.</div>
    @endif
    @if(session('error'))
        <div class="calendar-alert error">{{ session('error') }}</div>
    @endif
    <div class="calendar-page">
        <!-- Toolbar (Google/Outlook style) -->
        <div class="calendar-toolbar">
            <div class="toolbar-left">
                <div class="toolbar-nav">
                    <button type="button" class="toolbar-btn toolbar-btn-icon" onclick="previousPeriod()" title="Previous" aria-label="Previous">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M15 18l-6-6 6-6"/>
                        </svg>
                    </button>
                    <button type="button" class="toolbar-btn toolbar-btn-icon" onclick="nextPeriod()" title="Next" aria-label="Next">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 18l6-6-6-6"/>
                        </svg>
                    </button>
                    <button type="button" class="toolbar-btn toolbar-today" onclick="today()">Today</button>
                </div>
                <h2 class="toolbar-title" id="calendarTitle">January 2025</h2>
            </div>
            <div class="toolbar-center">
                <div class="view-segments">
                    <button type="button" class="view-segment active" data-view="month" onclick="switchView('month')">Month</button>
                    <button type="button" class="view-segment" data-view="week" onclick="switchView('week')">Week</button>
                    <button type="button" class="view-segment" data-view="day" onclick="switchView('day')">Day</button>
                </div>
            </div>
            <div class="toolbar-right">
                <div class="toolbar-actions">
                    <div class="inbox-account-chip" id="inboxAccountChip">
                        <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
                            <path fill="#0078D4" d="M7.56 7h8.88c.69 0 1.25.56 1.25 1.25v7.5c0 .69-.56 1.25-1.25 1.25H7.56a1.25 1.25 0 01-1.25-1.25v-7.5C6.31 7.56 6.87 7 7.56 7z"/>
                        </svg>
                        <span id="inboxAccountLabel">Checking inbox…</span>
                    </div>
                    <a class="integration-btn" id="inboxAccountAction" href="{{ route('inbox') }}">Open Inbox</a>
                </div>
                <button type="button" class="btn-create" onclick="openEventModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Create
                </button>
            </div>
        </div>

        <div class="calendar-setup-note" id="calendarSetupNote" style="display: none;">
            <p>Calendar shows events from the personal Microsoft 365 account connected in <a href="{{ route('inbox') }}">Inbox</a>. Connect Personal MS365 there to load your Outlook calendar.</p>
        </div>
        <div class="calendar-setup-note calendar-reconnect-note" id="calendarReconnectNote" style="display: none;">
            <p>Your Inbox account is connected for mail, but it does not yet have calendar access. <a href="{{ route('inbox.connect.outlook') }}">Reconnect Personal MS365</a> to grant Calendars.ReadWrite, then return here.</p>
        </div>

        <div class="calendar-main">
        <!-- Calendar Sidebar (left, like Google) -->
        <aside class="calendar-sidebar">
            <div class="sidebar-section">
                <h3 class="sidebar-title">My calendars</h3>
                <div class="calendar-list" id="calendarList">
                    <div class="calendar-list-empty" id="calendarListEmpty">Connect Inbox to load calendars</div>
                </div>
            </div>
            <div class="sidebar-section">
                <h3 class="sidebar-title">Upcoming</h3>
                <div class="upcoming-events" id="upcomingEvents"></div>
            </div>
        </aside>

        <!-- Calendar View -->
        <div class="calendar-view" id="calendarView">
            <!-- Month View -->
            <div class="calendar-month-view active" id="monthView">
                <div class="month-grid">
                    <div class="month-header">
                        <div class="month-weekday">Sun</div>
                        <div class="month-weekday">Mon</div>
                        <div class="month-weekday">Tue</div>
                        <div class="month-weekday">Wed</div>
                        <div class="month-weekday">Thu</div>
                        <div class="month-weekday">Fri</div>
                        <div class="month-weekday">Sat</div>
                    </div>
                    <div class="month-days" id="monthDays"></div>
                </div>
            </div>

            <!-- Week View -->
            <div class="calendar-week-view" id="weekView">
                <div class="week-header">
                    <div class="week-time-col"></div>
                    <div class="week-days-header" id="weekDays"></div>
                </div>
                <div class="week-body">
                    <div class="week-time-col" id="weekTimeSlots"></div>
                    <div class="week-grid" id="weekGrid"></div>
                </div>
            </div>

            <!-- Day View -->
            <div class="calendar-day-view" id="dayView">
                <div class="day-header">
                    <div class="day-time-col"></div>
                    <div class="day-date" id="dayDate"></div>
                </div>
                <div class="day-body">
                    <div class="day-time-col" id="dayTimeSlots"></div>
                    <div class="day-grid" id="dayGrid"></div>
                </div>
            </div>
        </div>
        </div>
    </div>

    <!-- Event Modal -->
    <div class="event-modal" id="eventModal">
        <div class="event-modal-content">
            <button class="modal-close" onclick="closeEventModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>

            <div class="modal-header">
                <h2 class="modal-title" id="eventModalTitle">New Event</h2>
            </div>

            <div class="modal-body">
                <form id="eventForm" onsubmit="saveEvent(event)">
                    <div class="form-group">
                        <label class="form-label">Event Title *</label>
                        <input type="text" class="form-input" id="eventTitle" required placeholder="Enter event title">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Start Date *</label>
                            <input type="date" class="form-input" id="eventStartDate" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Start Time</label>
                            <input type="time" class="form-input" id="eventStartTime">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">End Date *</label>
                            <input type="date" class="form-input" id="eventEndDate" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">End Time</label>
                            <input type="time" class="form-input" id="eventEndTime">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <input type="checkbox" id="eventAllDay" onchange="toggleAllDay()">
                            All Day Event
                        </label>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea class="form-input" id="eventDescription" rows="4" placeholder="Add event description"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Location</label>
                        <input type="text" class="form-input" id="eventLocation" placeholder="Enter location">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Calendar</label>
                        <select class="form-input" id="eventCalendar">
                            <option value="">Connect Inbox to choose a calendar</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Share with</label>
                        <input type="text" class="form-input" id="eventAttendees" placeholder="email@company.com, teammate@company.com">
                        <span class="form-help">Comma-separated emails. Outlook sends invitations when you save.</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Reminder</label>
                        <select class="form-input" id="eventReminder">
                            <option value="none">None</option>
                            <option value="5">5 minutes before</option>
                            <option value="15">15 minutes before</option>
                            <option value="30">30 minutes before</option>
                            <option value="60">1 hour before</option>
                            <option value="1440">1 day before</option>
                        </select>
                    </div>
                </form>
            </div>

            <div class="modal-footer">
                <p class="calendar-event-error" id="eventFormError" hidden></p>
                <button class="btn-secondary" onclick="closeEventModal()">Cancel</button>
                <button class="btn-secondary" onclick="deleteEvent()" id="deleteEventBtn" style="display: none;">Delete</button>
                <button class="btn-primary" id="saveEventBtn" onclick="document.getElementById('eventForm').requestSubmit()">Save Event</button>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
    .calendar-page {
        display: flex;
        flex-direction: column;
        min-height: 0;
    }

    .calendar-alert {
        padding: 0.75rem 1rem;
        border-radius: 8px;
        margin-bottom: 1rem;
        font-size: 0.875rem;
    }

    .calendar-alert.success {
        background: #e6f4ea;
        color: #0b8043;
        border: 1px solid #81c995;
    }

    .calendar-alert.error {
        background: #fce8e6;
        color: #c5221f;
        border: 1px solid #f28b82;
    }

    .calendar-setup-note {
        background: var(--accent-light);
        border: 1px solid var(--accent);
        border-radius: 8px;
        padding: 1rem 1.25rem;
        margin-bottom: 1rem;
    }

    .calendar-setup-note p {
        margin: 0 0 0.5rem;
        font-size: 0.875rem;
        color: var(--text-primary);
    }

    .calendar-setup-note p:last-child {
        margin-bottom: 0;
    }

    .calendar-setup-note .btn-primary {
        margin-top: 0.5rem;
    }

    .calendar-setup-note a {
        color: var(--accent);
        font-weight: 500;
    }

    /* Toolbar - Google/Outlook style */
    .calendar-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.75rem 0;
        margin-bottom: 0.5rem;
        flex-wrap: wrap;
    }

    .toolbar-left {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .toolbar-nav {
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }

    .toolbar-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.5rem 0.75rem;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
        background: transparent;
        border: 1px solid var(--border);
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .toolbar-btn:hover {
        background: var(--bg-primary);
    }

    .toolbar-btn-icon {
        width: 36px;
        padding: 0.5rem;
    }

    .toolbar-btn-icon svg {
        width: 18px;
        height: 18px;
    }

    .toolbar-today {
        color: var(--accent);
        border-color: var(--accent);
    }

    .toolbar-today:hover {
        background: var(--accent-light);
    }

    .toolbar-title {
        font-size: 1.375rem;
        font-weight: 500;
        color: var(--text-primary);
        margin: 0;
        letter-spacing: -0.02em;
    }

    .toolbar-center {
        display: flex;
        align-items: center;
    }

    .view-segments {
        display: flex;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 3px;
    }

    .view-segment {
        padding: 0.375rem 1rem;
        font-size: 0.8125rem;
        font-weight: 500;
        color: var(--text-secondary);
        background: transparent;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.15s;
    }

    .view-segment:hover {
        color: var(--text-primary);
    }

    .view-segment.active {
        background: var(--bg-card);
        color: var(--text-primary);
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    }

    .toolbar-right {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .toolbar-actions {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .integration-btn {
        display: flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.4rem 0.6rem;
        font-size: 0.75rem;
        color: var(--text-secondary);
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.15s;
    }

    .integration-btn:hover {
        background: var(--border);
        color: var(--text-primary);
    }

    .integration-btn.connected {
        background: #e8f5e9;
        color: #2e7d32;
        border-color: transparent;
    }

    .btn-create {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
        font-weight: 500;
        color: white;
        background: var(--accent);
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.15s;
    }

    .btn-create:hover {
        background: var(--accent-hover);
    }

    .btn-create svg {
        width: 18px;
        height: 18px;
    }

    /* Main layout */
    .calendar-main {
        display: grid;
        grid-template-columns: 220px 1fr;
        gap: 1rem;
        flex: 1;
        min-height: 0;
    }

    /* Sidebar */
    .calendar-sidebar {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
        flex-shrink: 0;
    }

    .sidebar-section {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 1rem;
    }

    .sidebar-title {
        font-size: 0.6875rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--text-muted);
        margin: 0 0 0.75rem;
    }

    .calendar-list {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .calendar-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.375rem 0;
        cursor: pointer;
        transition: background 0.15s;
        border-radius: 4px;
        margin: 0 -0.25rem;
        padding-inline: 0.25rem;
    }

    .calendar-item:hover {
        background: var(--bg-primary);
    }

    .calendar-item input[type="checkbox"] {
        width: 16px;
        height: 16px;
        cursor: pointer;
        accent-color: var(--accent);
    }

    .calendar-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    .calendar-name {
        font-size: 0.8125rem;
        color: var(--text-primary);
    }

    .upcoming-events {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .upcoming-event {
        padding: 0.5rem 0.75rem;
        background: var(--bg-primary);
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.15s;
        border-left: 3px solid transparent;
    }

    .upcoming-event:hover {
        background: var(--border);
    }

    .upcoming-event.personal { border-left-color: #1a73e8; }
    .upcoming-event.work { border-left-color: #0b8043; }
    .upcoming-event.google { border-left-color: #4285F4; }
    .upcoming-event.outlook { border-left-color: #0078D4; }
    .upcoming-event.local { border-left-color: #1a73e8; }

    .inbox-account-chip {
        display: flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.4rem 0.7rem;
        font-size: 0.75rem;
        color: var(--text-secondary);
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 6px;
        max-width: 240px;
    }

    .inbox-account-chip.connected {
        background: #e8f5e9;
        color: #2e7d32;
        border-color: transparent;
    }

    .inbox-account-chip.reconnect {
        background: #fff8e1;
        color: #b26a00;
        border-color: #ffe082;
    }

    .inbox-account-chip span {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .calendar-reconnect-note {
        background: #fff8e1;
        border-color: #ffe082;
    }

    .calendar-list-empty {
        font-size: 0.8125rem;
        color: var(--text-muted);
    }

    a.integration-btn {
        text-decoration: none;
    }

    .upcoming-event-title {
        font-size: 0.8125rem;
        font-weight: 500;
        color: var(--text-primary);
        margin-bottom: 0.125rem;
    }

    .upcoming-event-time {
        font-size: 0.6875rem;
        color: var(--text-muted);
    }

    .upcoming-setup-note {
        font-size: 0.8125rem;
        color: var(--text-secondary);
        padding: 0.75rem;
        background: var(--bg-primary);
        border-radius: 6px;
    }

    .upcoming-setup-note a {
        color: var(--accent);
        font-weight: 500;
    }

    /* Calendar view container */
    .calendar-view {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 8px;
        overflow: hidden;
        min-height: 500px;
    }

    .calendar-month-view,
    .calendar-week-view,
    .calendar-day-view {
        display: none;
        height: 100%;
    }

    .calendar-month-view.active,
    .calendar-week-view.active,
    .calendar-day-view.active {
        display: block;
    }

    /* Month view - clean grid */
    .month-grid {
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .month-header {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        background: var(--bg-primary);
        border-bottom: 1px solid var(--border);
    }

    .month-weekday {
        padding: 0.5rem;
        text-align: center;
        font-size: 0.6875rem;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .month-days {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        flex: 1;
        min-height: 400px;
    }

    .month-cell {
        min-height: 90px;
        padding: 0.375rem;
        border-right: 1px solid var(--border);
        border-bottom: 1px solid var(--border);
        background: var(--bg-card);
        cursor: pointer;
        transition: background 0.15s;
    }

    .month-cell:hover {
        background: var(--bg-primary);
    }

    .month-cell.other-month {
        background: #fafafa;
    }

    .month-cell.other-month .month-cell-num {
        color: var(--text-muted);
    }

    .month-cell.today {
        background: var(--accent-light);
    }

    .month-cell.today .month-cell-num {
        color: var(--accent);
        font-weight: 700;
        background: var(--accent);
        color: white;
        width: 1.75rem;
        height: 1.75rem;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .month-cell-num {
        font-size: 0.8125rem;
        font-weight: 500;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
    }

    .month-cell-events {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .month-event {
        font-size: 0.6875rem;
        padding: 0.125rem 0.375rem;
        border-radius: 4px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        cursor: pointer;
        transition: opacity 0.15s;
        border-left: 3px solid transparent;
    }

    .month-event:hover {
        opacity: 0.9;
    }

    .month-event.personal { background: #e8f0fe; color: #1a73e8; border-left-color: #1a73e8; }
    .month-event.work { background: #e6f4ea; color: #0b8043; border-left-color: #0b8043; }
    .month-event.google { background: #e8f0fe; color: #1967d2; border-left-color: #4285F4; }
    .month-event.outlook { background: #e3f2fd; color: #1565c0; border-left-color: #0078D4; }
    .month-event.local { background: #e8f0fe; color: #1a73e8; border-left-color: #1a73e8; }

    .month-event.more {
        color: var(--text-secondary);
        font-weight: 500;
        background: transparent;
        border: none;
    }

    /* Week view */
    .week-header {
        display: grid;
        grid-template-columns: 48px 1fr;
        background: var(--bg-primary);
        border-bottom: 1px solid var(--border);
    }

    .week-time-col, .day-time-col {
        background: var(--bg-primary);
        border-right: 1px solid var(--border);
    }

    .week-days-header {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
    }

    .week-day-header {
        padding: 0.5rem;
        text-align: center;
        border-right: 1px solid var(--border);
    }

    .week-day-header.today {
        background: var(--accent-light);
    }

    .week-day-name {
        font-size: 0.6875rem;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
    }

    .week-day-num {
        font-size: 1.125rem;
        font-weight: 500;
        color: var(--text-primary);
    }

    .week-day-header.today .week-day-num {
        color: var(--accent);
    }

    .week-body {
        display: grid;
        grid-template-columns: 48px 1fr;
        max-height: 480px;
        overflow-y: auto;
    }

    .week-time-slot, .day-time-slot {
        height: 48px;
        padding: 0.25rem 0.5rem;
        font-size: 0.6875rem;
        color: var(--text-muted);
        border-bottom: 1px solid var(--border);
    }

    .week-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
    }

    .week-day-column {
        border-right: 1px solid var(--border);
        position: relative;
    }

    .week-hour-slot {
        height: 48px;
        border-bottom: 1px solid var(--border);
        position: relative;
    }

    .week-event, .day-event-block {
        position: absolute;
        left: 2px;
        right: 2px;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.6875rem;
        color: white;
        cursor: pointer;
        overflow: hidden;
        z-index: 1;
        border-left: 3px solid rgba(0,0,0,0.2);
    }

    .week-event.personal, .day-event-block.personal { background: #1a73e8; }
    .week-event.work, .day-event-block.work { background: #0b8043; }
    .week-event.google, .day-event-block.google { background: #4285F4; }
    .week-event.outlook, .day-event-block.outlook { background: #0078D4; }
    .week-event.local, .day-event-block.local { background: #1a73e8; }

    /* Day view */
    .day-header {
        display: grid;
        grid-template-columns: 48px 1fr;
        background: var(--bg-primary);
        border-bottom: 1px solid var(--border);
    }

    .day-date {
        padding: 0.75rem;
        text-align: center;
    }

    .day-date-name {
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    .day-date-num {
        font-size: 1.5rem;
        font-weight: 600;
        color: var(--text-primary);
    }

    .day-body {
        display: grid;
        grid-template-columns: 48px 1fr;
        max-height: 480px;
        overflow-y: auto;
    }

    .day-grid {
        position: relative;
    }

    .day-hour-slot {
        height: 48px;
        border-bottom: 1px solid var(--border);
        position: relative;
    }

    .day-event-block {
        font-size: 0.8125rem;
    }

    /* Event modal */
    .event-modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.4);
        backdrop-filter: blur(4px);
        z-index: 2000;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        opacity: 0;
        transition: opacity 0.2s;
    }

    .event-modal.active {
        display: flex;
        opacity: 1;
    }

    .event-modal-content {
        background: var(--bg-card);
        border-radius: 12px;
        max-width: 560px;
        width: 100%;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
        box-shadow: 0 12px 48px rgba(0, 0, 0, 0.15);
        transform: scale(0.96);
        transition: transform 0.2s;
        overflow: hidden;
    }

    .event-modal.active .event-modal-content {
        transform: scale(1);
    }

    .modal-close {
        position: absolute;
        top: 1rem;
        right: 1rem;
        width: 36px;
        height: 36px;
        background: var(--bg-primary);
        border: none;
        border-radius: 8px;
        color: var(--text-secondary);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10;
        transition: all 0.15s;
    }

    .modal-close:hover {
        background: var(--border);
        color: var(--text-primary);
    }

    .modal-close svg {
        width: 18px;
        height: 18px;
    }

    .modal-header {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--border);
    }

    .modal-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }

    .modal-body {
        flex: 1;
        overflow-y: auto;
        padding: 1.25rem 1.5rem;
    }

    .form-group {
        margin-bottom: 1rem;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }

    .form-label {
        display: block;
        font-size: 0.8125rem;
        font-weight: 500;
        color: var(--text-primary);
        margin-bottom: 0.375rem;
    }

    .form-input {
        width: 100%;
        padding: 0.5rem 0.75rem;
        border: 1px solid var(--border);
        border-radius: 6px;
        font-size: 0.875rem;
        background: var(--bg-card);
        color: var(--text-primary);
        transition: all 0.15s;
        font-family: inherit;
    }

    .form-input:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 2px rgba(95, 97, 230, 0.2);
    }

    .form-input[type="checkbox"] {
        width: auto;
        margin-right: 0.5rem;
    }

    .form-help {
        display: block;
        margin-top: 0.25rem;
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    .calendar-event-error {
        margin: 0 auto 0 0;
        font-size: 0.8125rem;
        color: #c5221f;
    }

    .form-label:has(input[type="checkbox"]) {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .modal-footer {
        padding: 1rem 1.5rem;
        border-top: 1px solid var(--border);
        display: flex;
        gap: 0.5rem;
        justify-content: flex-end;
        background: var(--bg-primary);
    }

    .btn-primary, .btn-secondary {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        border-radius: 6px;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        border: none;
        transition: all 0.15s;
    }

    .btn-primary {
        background: var(--accent);
        color: white;
    }

    .btn-primary:hover {
        background: var(--accent-hover);
    }

    .btn-secondary {
        background: var(--bg-card);
        color: var(--text-primary);
        border: 1px solid var(--border);
    }

    .btn-secondary:hover {
        background: var(--border);
    }

    /* Responsive */
    @media (max-width: 1024px) {
        .calendar-main {
            grid-template-columns: 1fr;
        }

        .calendar-sidebar {
            flex-direction: row;
            flex-wrap: wrap;
        }
    }

    @media (max-width: 768px) {
        .calendar-toolbar {
            flex-direction: column;
            align-items: stretch;
        }

        .toolbar-left {
            justify-content: space-between;
        }

        .toolbar-center {
            justify-content: center;
        }

        .toolbar-right {
            justify-content: flex-end;
        }

        .month-cell {
            min-height: 70px;
        }

        .form-row {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    // Calendar State
    let currentDate = new Date();
    let currentView = 'month';
    let inboxConnected = false;
    let inboxNeedsReconnect = false;
    let inboxEmail = '';
    let currentEditingEvent = null;
    let outlookCalendars = [];
    let hiddenCalendarIds = new Set();

    let events = [];

    // Initialize Calendar
    function initCalendar() {
        fetchCalendarStatus();
        updateCalendarTitle();
        renderCalendar();
        renderUpcomingEvents();
    }

    function hasInboxCalendar() {
        return inboxConnected && !inboxNeedsReconnect;
    }

    function fetchCalendarStatus() {
        fetch('{{ route("api.calendar.status") }}', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            inboxConnected = !!data.connected;
            inboxEmail = data.email || '';
            inboxNeedsReconnect = !!data.needs_reconnect;
            updateInboxAccountChip();
            if (inboxConnected) {
                fetchExternalEvents();
            } else {
                events = events.filter(e => !e.external);
                outlookCalendars = [];
                renderOutlookCalendars();
                renderCalendar();
                renderUpcomingEvents();
            }
        })
        .catch(() => {
            inboxConnected = false;
            updateInboxAccountChip();
        });
    }

    function updateInboxAccountChip() {
        const chip = document.getElementById('inboxAccountChip');
        const label = document.getElementById('inboxAccountLabel');
        const action = document.getElementById('inboxAccountAction');
        const setupNote = document.getElementById('calendarSetupNote');
        const reconnectNote = document.getElementById('calendarReconnectNote');

        chip.classList.toggle('connected', inboxConnected && !inboxNeedsReconnect);
        chip.classList.toggle('reconnect', inboxConnected && inboxNeedsReconnect);

        if (!inboxConnected) {
            label.textContent = 'Inbox not connected';
            action.textContent = 'Connect in Inbox';
            action.href = '{{ route("inbox") }}';
        } else if (inboxNeedsReconnect) {
            label.textContent = (inboxEmail ? inboxEmail + ' · ' : '') + 'Reconnect for calendar';
            action.textContent = 'Reconnect';
            action.href = '{{ route("inbox.connect.outlook") }}';
        } else {
            label.textContent = inboxEmail || 'Personal Outlook';
            action.textContent = 'Open Inbox';
            action.href = '{{ route("inbox") }}';
        }

        if (setupNote) setupNote.style.display = inboxConnected ? 'none' : 'block';
        if (reconnectNote) reconnectNote.style.display = inboxNeedsReconnect ? 'block' : 'none';
    }

    function fetchExternalEvents() {
        if (!inboxConnected) return;
        const start = getViewStartDate();
        const end = getViewEndDate();
        const url = `{{ route("api.calendar.events") }}?start=${encodeURIComponent(start.toISOString())}&end=${encodeURIComponent(end.toISOString())}`;
        fetch(url, { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(data => {
            inboxNeedsReconnect = !!data.needs_reconnect;
            updateInboxAccountChip();
            outlookCalendars = data.calendars || [];
            renderOutlookCalendars();
            const external = (data.events || []).map(e => ({
                ...e,
                id: 'ext_' + (e.id || Math.random()),
                external: true,
                calendar: e.calendar || 'outlook',
            }));
            events = events.filter(e => !e.external).concat(external);
            renderCalendar();
            renderUpcomingEvents();
        })
        .catch(() => {});
    }

    function renderOutlookCalendars() {
        const list = document.getElementById('calendarList');
        const empty = document.getElementById('calendarListEmpty');
        if (!list) return;

        list.querySelectorAll('.calendar-item').forEach(el => el.remove());
        if (empty) {
            empty.style.display = outlookCalendars.length ? 'none' : 'block';
            empty.textContent = inboxConnected
                ? (inboxNeedsReconnect ? 'Reconnect Inbox to load calendars' : 'No Outlook calendars')
                : 'Connect Inbox to load calendars';
        }

        outlookCalendars.forEach(cal => {
            const id = cal.id;
            const label = document.createElement('label');
            label.className = 'calendar-item';
            const checked = !hiddenCalendarIds.has(id);
            label.innerHTML = `
                <input type="checkbox" ${checked ? 'checked' : ''} data-calendar-id="">
                <span class="calendar-dot" style="background: ${escapeHtml(cal.color || '#0078D4')};"></span>
                <span class="calendar-name"></span>
            `;
            label.querySelector('[data-calendar-id]').dataset.calendarId = id;
            label.querySelector('.calendar-name').textContent = cal.name || 'Calendar';
            label.querySelector('input').addEventListener('change', () => toggleCalendar(id));
            list.appendChild(label);
        });
        populateEventCalendarSelect();
    }

    function calendarApiHeaders() {
        return {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        };
    }

    function outlookEventId(event) {
        const id = String(event?.id || '');
        return id.startsWith('ext_') ? id.slice(4) : id;
    }

    function populateEventCalendarSelect(selectedId = '', lock = false) {
        const select = document.getElementById('eventCalendar');
        if (!select) return;
        select.innerHTML = '';
        if (!outlookCalendars.length) {
            const option = document.createElement('option');
            option.value = '';
            option.textContent = hasInboxCalendar() ? 'No Outlook calendars' : 'Connect Inbox to choose a calendar';
            select.appendChild(option);
            select.disabled = true;
            return;
        }
        outlookCalendars.forEach(cal => {
            const option = document.createElement('option');
            option.value = cal.id;
            option.textContent = cal.name || 'Calendar';
            select.appendChild(option);
        });
        const fallback = outlookCalendars.find(c => c.isDefault)?.id || outlookCalendars[0].id;
        select.value = selectedId && [...select.options].some(o => o.value === selectedId) ? selectedId : fallback;
        select.disabled = lock;
    }

    function setEventFormError(message) {
        const el = document.getElementById('eventFormError');
        if (!el) return;
        if (!message) {
            el.hidden = true;
            el.textContent = '';
            return;
        }
        el.hidden = false;
        el.textContent = message;
    }

    function setEventFormBusy(busy) {
        const saveBtn = document.getElementById('saveEventBtn');
        const deleteBtn = document.getElementById('deleteEventBtn');
        if (saveBtn) {
            saveBtn.disabled = busy;
            saveBtn.textContent = busy ? 'Saving…' : 'Save Event';
        }
        if (deleteBtn) deleteBtn.disabled = busy;
    }

    function toApiDateTime(date, time, allDay) {
        if (allDay) return date;
        const local = new Date(`${date}T${time || '00:00'}:00`);
        return local.toISOString();
    }

    function getViewStartDate() {
        if (currentView === 'month') {
            const d = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
            d.setDate(d.getDate() - d.getDay());
            return d;
        }
        if (currentView === 'week') return getStartOfWeek(currentDate);
        return new Date(currentDate.getFullYear(), currentDate.getMonth(), currentDate.getDate());
    }

    function getViewEndDate() {
        if (currentView === 'month') {
            const d = getViewStartDate();
            d.setDate(d.getDate() + 41);
            return d;
        }
        if (currentView === 'week') {
            const d = getStartOfWeek(currentDate);
            d.setDate(d.getDate() + 7);
            return d;
        }
        const d = new Date(currentDate.getFullYear(), currentDate.getMonth(), currentDate.getDate(), 23, 59, 59);
        return d;
    }

    // Update Calendar Title
    function updateCalendarTitle() {
        const title = document.getElementById('calendarTitle');
        if (currentView === 'month') {
            title.textContent = currentDate.toLocaleDateString('en-US', { year: 'numeric', month: 'long' });
        } else if (currentView === 'week') {
            const start = getStartOfWeek(currentDate);
            const end = new Date(start);
            end.setDate(end.getDate() + 6);
            title.textContent = `${start.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} – ${end.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}`;
        } else {
            title.textContent = currentDate.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
        }
    }

    // Render Calendar Based on View
    function renderCalendar() {
        if (currentView === 'month') {
            renderMonthView();
        } else if (currentView === 'week') {
            renderWeekView();
        } else if (currentView === 'day') {
            renderDayView();
        }
    }

    // Month View
    function renderMonthView() {
        const container = document.getElementById('monthDays');
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();

        // First day of month
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const startDate = new Date(firstDay);
        startDate.setDate(startDate.getDate() - startDate.getDay());

        let html = '';
        let currentDay = new Date(startDate);

        for (let i = 0; i < 42; i++) {
            const isOtherMonth = currentDay.getMonth() !== month;
            const isToday = isSameDay(currentDay, new Date());
            const dayEvents = getEventsForDay(currentDay);

            html += `
                <div class="month-cell ${isOtherMonth ? 'other-month' : ''} ${isToday ? 'today' : ''}" onclick="selectDate('${currentDay.toISOString()}')">
                    <div class="month-cell-num">${currentDay.getDate()}</div>
                    <div class="month-cell-events">
                        ${dayEvents.slice(0, 3).map(event => `
                            <div class="month-event ${event.calendar}" style="${event.color ? `border-left-color: ${escapeHtml(event.color)};` : ''}" onclick='event.stopPropagation(); viewEvent(${JSON.stringify(String(event.id))})' title="${escapeHtml(event.title)}">
                                ${event.allDay ? escapeHtml(event.title) : formatTime(event.start)}
                            </div>
                        `).join('')}
                        ${dayEvents.length > 3 ? `<div class="month-event more">+${dayEvents.length - 3} more</div>` : ''}
                    </div>
                </div>
            `;

            currentDay.setDate(currentDay.getDate() + 1);
        }

        container.innerHTML = html;
    }

    // Week View
    function renderWeekView() {
        const weekDaysContainer = document.getElementById('weekDays');
        const weekGridContainer = document.getElementById('weekGrid');
        const weekTimeSlots = document.getElementById('weekTimeSlots');

        // Populate time column
        let timeHtml = '';
        for (let hour = 0; hour < 24; hour++) {
            timeHtml += `<div class="week-time-slot">${formatHour(hour)}</div>`;
        }
        weekTimeSlots.innerHTML = timeHtml;

        const startOfWeek = getStartOfWeek(currentDate);
        let html = '';
        let gridHtml = '';

        for (let i = 0; i < 7; i++) {
            const day = new Date(startOfWeek);
            day.setDate(day.getDate() + i);
            const isToday = isSameDay(day, new Date());
            const dayName = day.toLocaleDateString('en-US', { weekday: 'short' });
            const dayNumber = day.getDate();

            html += `
                <div class="week-day-header ${isToday ? 'today' : ''}">
                    <div class="week-day-name">${dayName}</div>
                    <div class="week-day-num">${dayNumber}</div>
                </div>
            `;

            gridHtml += `<div class="week-day-column" id="weekDay${i}"></div>`;
        }

        weekDaysContainer.innerHTML = html;
        weekGridContainer.innerHTML = gridHtml;

        // Render events for each day
        for (let i = 0; i < 7; i++) {
            const day = new Date(startOfWeek);
            day.setDate(day.getDate() + i);
            const dayEvents = getEventsForDay(day);
            const dayColumn = document.getElementById(`weekDay${i}`);

            let hourHtml = '';
            for (let hour = 0; hour < 24; hour++) {
                hourHtml += `<div class="week-hour-slot" data-hour="${hour}"></div>`;
            }
            dayColumn.innerHTML = hourHtml;

            // Place events
            dayEvents.forEach(event => {
                if (!event.allDay) {
                    const start = new Date(event.start);
                    const end = new Date(event.end);
                    const startHour = start.getHours() + start.getMinutes() / 60;
                    const endHour = end.getHours() + end.getMinutes() / 60;
                    const duration = endHour - startHour;
                    const top = (startHour / 24) * 100;
                    const height = (duration / 24) * 100;

                    const eventEl = document.createElement('div');
                    eventEl.className = `week-event ${event.calendar}`;
                    if (event.color) eventEl.style.background = event.color;
                    eventEl.style.top = `${top}%`;
                    eventEl.style.height = `${height}%`;
                    eventEl.textContent = event.title;
                    eventEl.onclick = (e) => { e.stopPropagation(); viewEvent(event.id); };
                    dayColumn.appendChild(eventEl);
                }
            });
        }
    }

    // Day View
    function renderDayView() {
        const dayDateContainer = document.getElementById('dayDate');
        const dayGridContainer = document.getElementById('dayGrid');
        const dayTimeSlots = document.getElementById('dayTimeSlots');

        // Populate time column
        let timeHtml = '';
        for (let hour = 0; hour < 24; hour++) {
            timeHtml += `<div class="day-time-slot">${formatHour(hour)}</div>`;
        }
        dayTimeSlots.innerHTML = timeHtml;

        const dayName = currentDate.toLocaleDateString('en-US', { weekday: 'long' });
        const dayNumber = currentDate.getDate();
        const monthName = currentDate.toLocaleDateString('en-US', { month: 'long' });
        const year = currentDate.getFullYear();

        dayDateContainer.innerHTML = `
            <div class="day-date-name">${dayName}</div>
            <div class="day-date-num">${dayNumber}</div>
            <div class="day-date-name">${monthName} ${year}</div>
        `;

        const dayEvents = getEventsForDay(currentDate);
        let html = '';

        for (let hour = 0; hour < 24; hour++) {
            html += `<div class="day-hour-slot" data-hour="${hour}"></div>`;
        }

        dayGridContainer.innerHTML = html;

        // Place events
        dayEvents.forEach(event => {
            if (!event.allDay) {
                const start = new Date(event.start);
                const end = new Date(event.end);
                const startHour = start.getHours() + start.getMinutes() / 60;
                const endHour = end.getHours() + end.getMinutes() / 60;
                const duration = endHour - startHour;
                const top = (startHour / 24) * 100;
                const height = (duration / 24) * 100;

                const eventEl = document.createElement('div');
                eventEl.className = `day-event-block ${event.calendar}`;
                if (event.color) eventEl.style.background = event.color;
                eventEl.style.top = `${top}%`;
                eventEl.style.height = `${height}%`;
                eventEl.innerHTML = `
                    <div style="font-weight: 600;">${escapeHtml(event.title)}</div>
                    <div style="font-size: 0.75rem; opacity: 0.9;">${formatTime(event.start)} - ${formatTime(event.end)}</div>
                `;
                eventEl.onclick = (e) => { e.stopPropagation(); viewEvent(event.id); };
                dayGridContainer.appendChild(eventEl);
            }
        });
    }

    // Helper Functions
    function getStartOfWeek(date) {
        const d = new Date(date);
        const day = d.getDay();
        const diff = d.getDate() - day;
        return new Date(d.setDate(diff));
    }

    function isSameDay(date1, date2) {
        return date1.getFullYear() === date2.getFullYear() &&
               date1.getMonth() === date2.getMonth() &&
               date1.getDate() === date2.getDate();
    }

    function getEventsForDay(date) {
        return events.filter(event => {
            if (event.calendarId && hiddenCalendarIds.has(event.calendarId)) return false;
            const eventDate = new Date(event.start);
            return isSameDay(eventDate, date);
        });
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function formatTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
    }

    function formatHour(hour) {
        if (hour === 0) return '12 AM';
        if (hour < 12) return `${hour} AM`;
        if (hour === 12) return '12 PM';
        return `${hour - 12} PM`;
    }

    // Navigation
    function previousPeriod() {
        if (currentView === 'month') {
            currentDate.setMonth(currentDate.getMonth() - 1);
        } else if (currentView === 'week') {
            currentDate.setDate(currentDate.getDate() - 7);
        } else {
            currentDate.setDate(currentDate.getDate() - 1);
        }
        updateCalendarTitle();
        renderCalendar();
        if (hasInboxCalendar()) fetchExternalEvents();
    }

    function nextPeriod() {
        if (currentView === 'month') {
            currentDate.setMonth(currentDate.getMonth() + 1);
        } else if (currentView === 'week') {
            currentDate.setDate(currentDate.getDate() + 7);
        } else {
            currentDate.setDate(currentDate.getDate() + 1);
        }
        updateCalendarTitle();
        renderCalendar();
        if (hasInboxCalendar()) fetchExternalEvents();
    }

    function today() {
        currentDate = new Date();
        updateCalendarTitle();
        renderCalendar();
        if (hasInboxCalendar()) fetchExternalEvents();
    }

    function switchView(view) {
        currentView = view;
        document.querySelectorAll('.view-segment').forEach(btn => btn.classList.remove('active'));
        document.querySelector(`.view-segment[data-view="${view}"]`).classList.add('active');
        document.querySelectorAll('.calendar-month-view, .calendar-week-view, .calendar-day-view').forEach(v => v.classList.remove('active'));
        document.getElementById(`${view}View`).classList.add('active');
        updateCalendarTitle();
        renderCalendar();
        if (hasInboxCalendar()) fetchExternalEvents();
    }

    function selectDate(dateString) {
        const date = new Date(dateString);
        currentDate = date;
        openEventModal(date);
    }

    // Event Management
    function openEventModal(date = null) {
        if (!hasInboxCalendar()) {
            setEventFormError('');
            alert('Connect your personal Microsoft 365 account in Inbox to add calendar events.');
            return;
        }

        currentEditingEvent = null;
        document.getElementById('eventModalTitle').textContent = 'New Event';
        document.getElementById('eventForm').reset();
        document.getElementById('deleteEventBtn').style.display = 'none';
        document.getElementById('saveEventBtn').style.display = 'inline-flex';
        document.getElementById('eventForm').querySelectorAll('input, select, textarea').forEach(el => { el.disabled = false; });
        populateEventCalendarSelect();
        setEventFormError('');
        setEventFormBusy(false);

        const base = date ? new Date(date) : new Date();
        const dateStr = `${base.getFullYear()}-${String(base.getMonth() + 1).padStart(2, '0')}-${String(base.getDate()).padStart(2, '0')}`;
        document.getElementById('eventStartDate').value = dateStr;
        document.getElementById('eventEndDate').value = dateStr;
        const nextHour = (base.getHours() + 1) % 24;
        document.getElementById('eventStartTime').value = `${String(nextHour).padStart(2, '0')}:00`;
        document.getElementById('eventEndTime').value = `${String((nextHour + 1) % 24).padStart(2, '0')}:00`;
        document.getElementById('eventReminder').value = '15';

        document.getElementById('eventModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeEventModal() {
        document.getElementById('eventModal').classList.remove('active');
        document.body.style.overflow = '';
        currentEditingEvent = null;
        setEventFormError('');
        setEventFormBusy(false);
        const form = document.getElementById('eventForm');
        form.querySelectorAll('input, select, textarea').forEach(el => { el.disabled = false; });
        document.getElementById('saveEventBtn').style.display = 'inline-flex';
    }

    function viewEvent(eventId) {
        const event = events.find(e => String(e.id) === String(eventId));
        if (!event) return;

        currentEditingEvent = event;
        document.getElementById('eventModalTitle').textContent = 'Edit Event';
        document.getElementById('deleteEventBtn').style.display = 'block';
        document.getElementById('saveEventBtn').style.display = 'inline-flex';
        document.getElementById('eventForm').querySelectorAll('input, select, textarea').forEach(el => { el.disabled = false; });
        setEventFormError('');
        setEventFormBusy(false);

        document.getElementById('eventTitle').value = event.title;
        document.getElementById('eventStartDate').value = String(event.start || '').split('T')[0];
        document.getElementById('eventEndDate').value = String(event.end || event.start || '').split('T')[0];
        document.getElementById('eventAllDay').checked = event.allDay;
        document.getElementById('eventDescription').value = event.description || '';
        document.getElementById('eventLocation').value = event.location || '';
        document.getElementById('eventAttendees').value = Array.isArray(event.attendees) ? event.attendees.join(', ') : (event.attendees || '');
        populateEventCalendarSelect(event.calendarId || '', true);
        setReminderValue(event.reminder);

        if (!event.allDay && event.start) {
            const start = new Date(event.start);
            const end = new Date(event.end || event.start);
            document.getElementById('eventStartTime').value = start.toTimeString().slice(0, 5);
            document.getElementById('eventEndTime').value = end.toTimeString().slice(0, 5);
        }
        toggleAllDay();

        document.getElementById('eventModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function setReminderValue(value) {
        const select = document.getElementById('eventReminder');
        const reminder = value == null || value === '' ? 'none' : String(value);
        if (![...select.options].some(o => o.value === reminder)) {
            const option = document.createElement('option');
            option.value = reminder;
            option.textContent = reminder + ' minutes before';
            select.appendChild(option);
        }
        select.value = reminder;
    }

    function collectEventPayload() {
        const title = document.getElementById('eventTitle').value.trim();
        const startDate = document.getElementById('eventStartDate').value;
        const endDate = document.getElementById('eventEndDate').value;
        const allDay = document.getElementById('eventAllDay').checked;
        const startTime = document.getElementById('eventStartTime').value;
        const endTime = document.getElementById('eventEndTime').value;
        const calendarSelect = document.getElementById('eventCalendar');
        const calendarId = calendarSelect.value;
        const calendar = outlookCalendars.find(c => c.id === calendarId);

        return {
            title,
            start: toApiDateTime(startDate, startTime, allDay),
            end: toApiDateTime(endDate, endTime, allDay),
            all_day: allDay,
            description: document.getElementById('eventDescription').value,
            location: document.getElementById('eventLocation').value,
            calendar_id: calendarId,
            calendar_name: calendar?.name || calendarSelect.selectedOptions[0]?.textContent || 'Calendar',
            calendar_color: calendar?.color || '#0078D4',
            attendees: document.getElementById('eventAttendees').value,
            reminder: document.getElementById('eventReminder').value,
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC',
        };
    }

    function saveEvent(e) {
        e.preventDefault();
        if (!hasInboxCalendar()) {
            setEventFormError('Connect your personal Microsoft 365 account in Inbox first.');
            return;
        }

        const payload = collectEventPayload();
        if (!payload.title) {
            setEventFormError('Enter an event title.');
            return;
        }
        if (!payload.calendar_id) {
            setEventFormError('Choose a calendar.');
            return;
        }
        if (!payload.all_day && (!document.getElementById('eventStartTime').value || !document.getElementById('eventEndTime').value)) {
            setEventFormError('Enter a start and end time, or mark this as an all-day event.');
            return;
        }

        const editing = currentEditingEvent;
        const url = editing
            ? `{{ url('/api/calendar/events') }}/${encodeURIComponent(outlookEventId(editing))}`
            : '{{ route("api.calendar.events.store") }}';

        setEventFormBusy(true);
        setEventFormError('');
        fetch(url, {
            method: editing ? 'PATCH' : 'POST',
            headers: calendarApiHeaders(),
            body: JSON.stringify(payload),
        })
        .then(async r => {
            const data = await r.json().catch(() => ({}));
            if (!r.ok) {
                if (data.needs_reconnect) {
                    inboxNeedsReconnect = true;
                    updateInboxAccountChip();
                }
                throw new Error(data.message || 'Could not save this event.');
            }
            closeEventModal();
            fetchExternalEvents();
        })
        .catch(err => {
            setEventFormError(err.message || 'Could not save this event.');
            setEventFormBusy(false);
        });
    }

    function deleteEvent() {
        if (!currentEditingEvent || !confirm('Delete this event from Outlook?')) return;
        if (!hasInboxCalendar()) {
            setEventFormError('Connect your personal Microsoft 365 account in Inbox first.');
            return;
        }

        setEventFormBusy(true);
        setEventFormError('');
        fetch(`{{ url('/api/calendar/events') }}/${encodeURIComponent(outlookEventId(currentEditingEvent))}`, {
            method: 'DELETE',
            headers: calendarApiHeaders(),
        })
        .then(async r => {
            const data = await r.json().catch(() => ({}));
            if (!r.ok) {
                if (data.needs_reconnect) {
                    inboxNeedsReconnect = true;
                    updateInboxAccountChip();
                }
                throw new Error(data.message || 'Could not delete this event.');
            }
            closeEventModal();
            fetchExternalEvents();
        })
        .catch(err => {
            setEventFormError(err.message || 'Could not delete this event.');
            setEventFormBusy(false);
        });
    }

    function toggleAllDay() {
        const allDay = document.getElementById('eventAllDay').checked;
        document.getElementById('eventStartTime').disabled = allDay;
        document.getElementById('eventEndTime').disabled = allDay;
    }

    function toggleCalendar(calendarId) {
        if (hiddenCalendarIds.has(calendarId)) {
            hiddenCalendarIds.delete(calendarId);
        } else {
            hiddenCalendarIds.add(calendarId);
        }
        renderCalendar();
        renderUpcomingEvents();
    }

    // Render Upcoming Events
    function renderUpcomingEvents() {
        const container = document.getElementById('upcomingEvents');
        const sortedEvents = [...events]
            .filter(e => {
                if (e.calendarId && hiddenCalendarIds.has(e.calendarId)) return false;
                return new Date(e.start) >= new Date();
            })
            .sort((a, b) => new Date(a.start) - new Date(b.start))
            .slice(0, 5);

        if (sortedEvents.length === 0) {
            if (!inboxConnected) {
                container.innerHTML = '<div class="upcoming-setup-note">Connect your personal Microsoft 365 account in <a href="{{ route('inbox') }}">Inbox</a> to see upcoming events.</div>';
            } else if (inboxNeedsReconnect) {
                container.innerHTML = '<div class="upcoming-setup-note"><a href="{{ route('inbox.connect.outlook') }}">Reconnect Personal MS365</a> in Inbox to grant calendar access.</div>';
            } else {
                container.innerHTML = '<div style="color: var(--text-muted); font-size: 0.875rem;">No upcoming events</div>';
            }
            return;
        }

        container.innerHTML = sortedEvents.map(event => {
            const date = new Date(event.start);
            const timeStr = event.allDay ? 'All day' : formatTime(event.start);
            const colorStyle = event.color ? `border-left-color: ${escapeHtml(event.color)};` : '';
            return `
                <div class="upcoming-event ${event.calendar}" style="${colorStyle}" onclick='viewEvent(${JSON.stringify(String(event.id))})'>
                    <div class="upcoming-event-title">${escapeHtml(event.title)}</div>
                    <div class="upcoming-event-time">${date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} • ${timeStr}</div>
                </div>
            `;
        }).join('');
    }

    // Close modal on outside click
    document.getElementById('eventModal').addEventListener('click', function(e) {
        if (e.target === this) closeEventModal();
    });

    // Initialize
    initCalendar();
</script>
@endpush

