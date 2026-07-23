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
                    <button type="button" class="integration-btn" id="googleCalendarBtn" data-connect-url="{{ route('calendar.connect.google') }}" title="Connect Google Calendar">
                        <svg viewBox="0 0 24 24" width="18" height="18">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                        </svg>
                        <span id="googleCalendarStatus">Google</span>
                    </button>
                    <button type="button" class="integration-btn" id="outlookCalendarBtn" data-connect-url="{{ route('calendar.connect.outlook') }}" title="Connect Outlook Calendar">
                        <svg viewBox="0 0 24 24" width="18" height="18">
                            <path fill="#0078D4" d="M7.56 7h8.88c.69 0 1.25.56 1.25 1.25v7.5c0 .69-.56 1.25-1.25 1.25H7.56a1.25 1.25 0 01-1.25-1.25v-7.5C6.31 7.56 6.87 7 7.56 7z"/>
                        </svg>
                        <span id="outlookCalendarStatus">Outlook</span>
                    </button>
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
            <p>To see your calendar events, configure Calendar OAuth Settings in <a href="{{ route('integrations') }}">Integrations</a> and connect your personal Google or Outlook account using the buttons above.</p>
        </div>

        <div class="calendar-main">
        <!-- Calendar Sidebar (left, like Google) -->
        <aside class="calendar-sidebar">
            <div class="sidebar-section">
                <h3 class="sidebar-title">My calendars</h3>
                <div class="calendar-list">
                    <label class="calendar-item">
                        <input type="checkbox" checked onchange="toggleCalendar('personal')">
                        <span class="calendar-dot" style="background: #1a73e8;"></span>
                        <span class="calendar-name">Personal</span>
                    </label>
                    <label class="calendar-item">
                        <input type="checkbox" checked onchange="toggleCalendar('work')">
                        <span class="calendar-dot" style="background: #0b8043;"></span>
                        <span class="calendar-name">Work</span>
                    </label>
                    <label class="calendar-item" id="googleCalendarItem" style="display: none;">
                        <input type="checkbox" checked onchange="toggleCalendar('google')">
                        <span class="calendar-dot" style="background: #4285F4;"></span>
                        <span class="calendar-name">Google Calendar</span>
                    </label>
                    <label class="calendar-item" id="outlookCalendarItem" style="display: none;">
                        <input type="checkbox" checked onchange="toggleCalendar('outlook')">
                        <span class="calendar-dot" style="background: #0078D4;"></span>
                        <span class="calendar-name">Outlook</span>
                    </label>
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
                            <option value="personal">Personal</option>
                            <option value="work">Work</option>
                            <option value="google" id="googleCalendarOption" style="display: none;">Google Calendar</option>
                            <option value="outlook" id="outlookCalendarOption" style="display: none;">Outlook Calendar</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Attendees</label>
                        <input type="text" class="form-input" id="eventAttendees" placeholder="Enter email addresses (comma separated)">
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
                <button class="btn-secondary" onclick="closeEventModal()">Cancel</button>
                <button class="btn-secondary" onclick="deleteEvent()" id="deleteEventBtn" style="display: none;">Delete</button>
                <button class="btn-primary" onclick="document.getElementById('eventForm').requestSubmit()">Save Event</button>
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
    let googleCalendarConnected = false;
    let outlookCalendarConnected = false;
    let currentEditingEvent = null;

    let events = [];

    // Initialize Calendar
    function initCalendar() {
        updateCalendarButtons();
        fetchCalendarStatus();
        updateCalendarTitle();
        renderCalendar();
        renderUpcomingEvents();
    }

    // Fetch calendar connection status and external events
    function fetchCalendarStatus() {
        fetch('{{ route("api.calendar.status") }}', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            googleCalendarConnected = data.google || false;
            outlookCalendarConnected = data.outlook || false;
            updateCalendarButtons();
            const goog = document.getElementById('googleCalendarItem');
            const outl = document.getElementById('outlookCalendarItem');
            const googOpt = document.getElementById('googleCalendarOption');
            const outlOpt = document.getElementById('outlookCalendarOption');
            if (goog) goog.style.display = googleCalendarConnected ? 'flex' : 'none';
            if (outl) outl.style.display = outlookCalendarConnected ? 'flex' : 'none';
            if (googOpt) googOpt.style.display = googleCalendarConnected ? 'block' : 'none';
            if (outlOpt) outlOpt.style.display = outlookCalendarConnected ? 'block' : 'none';
            const setupNote = document.getElementById('calendarSetupNote');
            const hasConnection = googleCalendarConnected || outlookCalendarConnected;
            if (setupNote) setupNote.style.display = hasConnection ? 'none' : 'block';
            if (hasConnection) {
                fetchExternalEvents();
            } else {
                events = events.filter(e => !e.external);
                renderCalendar();
                renderUpcomingEvents();
            }
        })
        .catch(() => {
            document.getElementById('calendarSetupNote').style.display = 'block';
        });
    }

    function updateCalendarButtons() {
        const googleBtn = document.getElementById('googleCalendarBtn');
        const outlookBtn = document.getElementById('outlookCalendarBtn');
        const googleStatus = document.getElementById('googleCalendarStatus');
        const outlookStatus = document.getElementById('outlookCalendarStatus');
        if (googleBtn) {
            googleBtn.classList.toggle('connected', googleCalendarConnected);
            googleStatus.textContent = googleCalendarConnected ? 'Connected' : 'Google';
            googleBtn.onclick = () => googleCalendarConnected ? disconnectCalendar('google') : (window.location.href = googleBtn.dataset.connectUrl || '{{ route("calendar.connect.google") }}');
        }
        if (outlookBtn) {
            outlookBtn.classList.toggle('connected', outlookCalendarConnected);
            outlookStatus.textContent = outlookCalendarConnected ? 'Connected' : 'Outlook';
            outlookBtn.onclick = () => outlookCalendarConnected ? disconnectCalendar('outlook') : (window.location.href = outlookBtn.dataset.connectUrl || '{{ route("calendar.connect.outlook") }}');
        }
    }

    function disconnectCalendar(provider) {
        if (!confirm('Disconnect ' + (provider === 'google' ? 'Google' : 'Outlook') + ' Calendar?')) return;
        fetch('{{ route("api.calendar.disconnect") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ provider })
        })
        .then(r => r.json())
        .then(() => {
            if (provider === 'google') {
                googleCalendarConnected = false;
                document.getElementById('googleCalendarItem').style.display = 'none';
                document.getElementById('googleCalendarOption').style.display = 'none';
            } else {
                outlookCalendarConnected = false;
                document.getElementById('outlookCalendarItem').style.display = 'none';
                document.getElementById('outlookCalendarOption').style.display = 'none';
            }
            events = events.filter(e => !e.external || e.calendar !== provider);
            const hasConnection = googleCalendarConnected || outlookCalendarConnected;
            document.getElementById('calendarSetupNote').style.display = hasConnection ? 'none' : 'block';
            updateCalendarButtons();
            renderCalendar();
            renderUpcomingEvents();
        });
    }

    function fetchExternalEvents() {
        const start = getViewStartDate();
        const end = getViewEndDate();
        const url = `{{ route("api.calendar.events") }}?start=${start.toISOString()}&end=${end.toISOString()}`;
        fetch(url, { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(data => {
            const external = (data.events || []).map(e => ({ ...e, id: 'ext_' + (e.id || Math.random()), external: true }));
            events = events.filter(e => !e.external).concat(external);
            renderCalendar();
            renderUpcomingEvents();
        })
        .catch(() => {});
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
                            <div class="month-event ${event.calendar}" onclick="event.stopPropagation(); viewEvent(${event.id})" title="${event.title}">
                                ${event.allDay ? event.title : formatTime(event.start)}
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
                eventEl.style.top = `${top}%`;
                eventEl.style.height = `${height}%`;
                eventEl.innerHTML = `
                    <div style="font-weight: 600;">${event.title}</div>
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
            const eventDate = new Date(event.start);
            return isSameDay(eventDate, date);
        });
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
    }

    function today() {
        currentDate = new Date();
        updateCalendarTitle();
        renderCalendar();
    }

    function switchView(view) {
        currentView = view;
        document.querySelectorAll('.view-segment').forEach(btn => btn.classList.remove('active'));
        document.querySelector(`.view-segment[data-view="${view}"]`).classList.add('active');
        document.querySelectorAll('.calendar-month-view, .calendar-week-view, .calendar-day-view').forEach(v => v.classList.remove('active'));
        document.getElementById(`${view}View`).classList.add('active');
        updateCalendarTitle();
        renderCalendar();
        if (googleCalendarConnected || outlookCalendarConnected) fetchExternalEvents();
    }

    function selectDate(dateString) {
        const date = new Date(dateString);
        currentDate = date;
        openEventModal(date);
    }

    // Event Management
    function openEventModal(date = null) {
        currentEditingEvent = null;
        document.getElementById('eventModalTitle').textContent = 'New Event';
        document.getElementById('eventForm').reset();
        document.getElementById('deleteEventBtn').style.display = 'none';

        if (date) {
            const dateStr = date.toISOString().split('T')[0];
            document.getElementById('eventStartDate').value = dateStr;
            document.getElementById('eventEndDate').value = dateStr;
        } else {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('eventStartDate').value = today;
            document.getElementById('eventEndDate').value = today;
        }

        document.getElementById('eventModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeEventModal() {
        document.getElementById('eventModal').classList.remove('active');
        document.body.style.overflow = '';
        currentEditingEvent = null;
        const form = document.getElementById('eventForm');
        form.querySelectorAll('input, select, textarea').forEach(el => { el.disabled = false; });
        document.querySelector('.modal-footer .btn-primary').style.display = 'inline-flex';
    }

    function viewEvent(eventId) {
        const event = events.find(e => e.id === eventId);
        if (!event) return;

        currentEditingEvent = event;
        document.getElementById('eventModalTitle').textContent = 'Edit Event';
        document.getElementById('deleteEventBtn').style.display = 'block';

        document.getElementById('eventTitle').value = event.title;
        document.getElementById('eventStartDate').value = event.start.split('T')[0];
        document.getElementById('eventEndDate').value = event.end.split('T')[0];
        document.getElementById('eventAllDay').checked = event.allDay;
        document.getElementById('eventDescription').value = event.description || '';
        document.getElementById('eventLocation').value = event.location || '';
        document.getElementById('eventCalendar').value = event.calendar;

        if (!event.allDay) {
            const start = new Date(event.start);
            const end = new Date(event.end);
            document.getElementById('eventStartTime').value = start.toTimeString().slice(0, 5);
            document.getElementById('eventEndTime').value = end.toTimeString().slice(0, 5);
        }

        document.getElementById('eventModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function saveEvent(e) {
        e.preventDefault();

        const title = document.getElementById('eventTitle').value;
        const startDate = document.getElementById('eventStartDate').value;
        const endDate = document.getElementById('eventEndDate').value;
        const allDay = document.getElementById('eventAllDay').checked;
        const startTime = document.getElementById('eventStartTime').value;
        const endTime = document.getElementById('eventEndTime').value;
        const description = document.getElementById('eventDescription').value;
        const location = document.getElementById('eventLocation').value;
        const calendar = document.getElementById('eventCalendar').value;

        const start = allDay ? startDate : `${startDate}T${startTime}:00`;
        const end = allDay ? endDate : `${endDate}T${endTime}:00`;

        if (currentEditingEvent) {
            // Update existing event
            const index = events.findIndex(e => e.id === currentEditingEvent.id);
            events[index] = {
                ...currentEditingEvent,
                title,
                start,
                end,
                allDay,
                description,
                location,
                calendar
            };
        } else {
            // Create new event
            const newEvent = {
                id: Date.now(),
                title,
                start,
                end,
                allDay,
                description,
                location,
                calendar
            };
            events.push(newEvent);
        }

        closeEventModal();
        renderCalendar();
        renderUpcomingEvents();
    }

    function deleteEvent() {
        if (currentEditingEvent && confirm('Are you sure you want to delete this event?')) {
            events = events.filter(e => e.id !== currentEditingEvent.id);
            closeEventModal();
            renderCalendar();
            renderUpcomingEvents();
        }
    }

    function toggleAllDay() {
        const allDay = document.getElementById('eventAllDay').checked;
        document.getElementById('eventStartTime').disabled = allDay;
        document.getElementById('eventEndTime').disabled = allDay;
    }

    function toggleCalendar(calendarType) {
        // Toggle calendar visibility
        renderCalendar();
    }

    // Render Upcoming Events
    function renderUpcomingEvents() {
        const container = document.getElementById('upcomingEvents');
        const hasConnection = googleCalendarConnected || outlookCalendarConnected;
        const sortedEvents = [...events]
            .filter(e => new Date(e.start) >= new Date())
            .sort((a, b) => new Date(a.start) - new Date(b.start))
            .slice(0, 5);

        if (sortedEvents.length === 0) {
            if (!hasConnection) {
                container.innerHTML = '<div class="upcoming-setup-note">Configure Calendar OAuth in <a href="{{ route('integrations') }}">Integrations</a> and connect your personal Google or Outlook account to see upcoming events.</div>';
            } else {
                container.innerHTML = '<div style="color: var(--text-muted); font-size: 0.875rem;">No upcoming events</div>';
            }
            return;
        }

        container.innerHTML = sortedEvents.map(event => {
            const date = new Date(event.start);
            const timeStr = event.allDay ? 'All day' : formatTime(event.start);
            return `
                <div class="upcoming-event ${event.calendar}" onclick="viewEvent(${event.id})">
                    <div class="upcoming-event-title">${event.title}</div>
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

