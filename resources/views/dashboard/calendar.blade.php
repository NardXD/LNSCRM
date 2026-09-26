@extends('layouts.app')

@section('title', 'Calendar')

@push('styles')
    @vite(['resources/css/pages/calendar.css'])
@endpush

@push('scripts')
<script>
    window.__calendarConfig = {
        statusUrl: @json(route('api.calendar.status')),
        eventsUrl: @json(route('api.calendar.events')),
        eventsStoreUrl: @json(route('api.calendar.events.store')),
        eventsBaseUrl: @json(url('/api/calendar/events')),
        inboxUrl: @json(route('inbox')),
        inboxConnectOutlookUrl: @json(route('inbox.connect.outlook')),
    };
</script>
    @vite(['resources/js/pages/calendar.js'])
@endpush

@section('content')
    <div class="calendar-page-wrapper">
        @if(session('status') === 'google-calendar-connected' || session('status') === 'outlook-calendar-connected')
            <div class="ms-toast success">Calendar connected.</div>
        @endif
        @if(session('error'))
            <div class="ms-toast error">{{ session('error') }}</div>
        @endif

        <div class="ms-cal">
            <aside class="ms-rail">
                <button type="button" class="ms-new-event" onclick="openNewEvent()">
                    <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                        <path fill="currentColor" d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                    </svg>
                    New event
                </button>

                <div class="ms-mini" id="miniCalendar"></div>

                <div class="ms-rail-section">
                    <h3 class="ms-rail-title">My calendars</h3>
                    <div class="calendar-list" id="calendarList">
                        <div class="calendar-list-empty" id="calendarListEmpty">Connect Inbox to load calendars</div>
                    </div>
                </div>

                <div class="ms-account" id="inboxAccountChip">
                    <div class="ms-account-dot" aria-hidden="true"></div>
                    <div class="ms-account-copy">
                        <span class="ms-account-label" id="inboxAccountLabel">Checking inbox…</span>
                        <a class="ms-account-action" id="inboxAccountAction" href="{{ route('inbox') }}">Open Inbox</a>
                    </div>
                </div>
            </aside>

            <div class="ms-stage">
                <header class="ms-toolbar">
                    <div class="ms-toolbar-left">
                        <button type="button" class="ms-icon-btn" onclick="previousPeriod()" title="Previous" aria-label="Previous">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                        </button>
                        <button type="button" class="ms-icon-btn" onclick="nextPeriod()" title="Next" aria-label="Next">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
                        </button>
                        <button type="button" class="ms-today-btn" onclick="today()">Today</button>
                        <h1 class="ms-title" id="calendarTitle">September 2026</h1>
                    </div>
                    <div class="ms-views" role="tablist" aria-label="Calendar view">
                        <button type="button" class="ms-view-btn" data-view="day" onclick="switchView('day')">Day</button>
                        <button type="button" class="ms-view-btn active" data-view="workweek" onclick="switchView('workweek')">Work week</button>
                        <button type="button" class="ms-view-btn" data-view="week" onclick="switchView('week')">Week</button>
                        <button type="button" class="ms-view-btn" data-view="month" onclick="switchView('month')">Month</button>
                    </div>
                </header>

                <div class="ms-banner" id="calendarSetupNote" hidden>
                    Calendar uses the personal Microsoft 365 account connected in <a href="{{ route('inbox') }}">Inbox</a>. Connect Personal MS365 to load Outlook events.
                </div>
                <div class="ms-banner warn" id="calendarReconnectNote" hidden>
                    Mail is connected, but calendar edit access is missing. <a href="{{ route('inbox.connect.outlook') }}">Reconnect Personal MS365</a> to grant Calendars.ReadWrite.
                </div>

                <div class="ms-board" id="calendarView">
                    <div class="ms-month" id="monthView">
                        <div class="ms-month-weekdays">
                            <div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div>
                        </div>
                        <div class="ms-month-days" id="monthDays"></div>
                    </div>

                    <div class="ms-timed active" id="timedView">
                        <div class="ms-timed-header">
                            <div class="ms-gutter"></div>
                            <div class="ms-day-headers" id="timedHeaders"></div>
                        </div>
                        <div class="ms-allday">
                            <div class="ms-gutter"><span>All day</span></div>
                            <div class="ms-allday-grid" id="allDayGrid"></div>
                        </div>
                        <div class="ms-timed-scroll" id="timedScroll">
                            <div class="ms-gutter ms-hour-gutter" id="timedHours"></div>
                            <div class="ms-timed-grid" id="timedGrid"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="event-modal" id="eventModal">
        <div class="event-modal-content">
            <button class="modal-close" onclick="closeEventModal()" aria-label="Close">
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
                        <input type="text" class="form-input form-title-input" id="eventTitle" required placeholder="Add a title">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Start</label>
                            <input type="date" class="form-input" id="eventStartDate" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Time</label>
                            <input type="time" class="form-input" id="eventStartTime">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">End</label>
                            <input type="date" class="form-input" id="eventEndDate" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Time</label>
                            <input type="time" class="form-input" id="eventEndTime">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label form-check">
                            <input type="checkbox" id="eventAllDay" onchange="toggleAllDay()">
                            All day
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Location</label>
                        <input type="text" class="form-input" id="eventLocation" placeholder="Add a location">
                    </div>
                    <div class="form-group" id="teamsMeetingGroup">
                        <label class="form-label form-check">
                            <input type="checkbox" id="eventTeamsMeeting" checked>
                            Teams meeting
                        </label>
                    </div>
                    <p class="form-help" id="eventOrganizerNote" hidden>Only the organizer can edit this meeting.</p>
                    <div class="form-group">
                        <label class="form-label">Calendar</label>
                        <select class="form-input" id="eventCalendar">
                            <option value="">Connect Inbox to choose a calendar</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Invite people</label>
                        <input type="text" class="form-input" id="eventAttendees" placeholder="email@company.com, teammate@company.com">
                        <span class="form-help">Outlook sends invitations when you save.</span>
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
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea class="form-input" id="eventDescription" rows="3" placeholder="Add a description"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <p class="calendar-event-error" id="eventFormError" hidden></p>
                <a class="btn-join" id="joinMeetingBtn" href="#" target="_blank" rel="noopener noreferrer" hidden>Join</a>
                <button type="button" class="btn-secondary" onclick="closeEventModal()" id="discardEventBtn">Discard</button>
                <button type="button" class="btn-secondary danger" onclick="deleteEvent()" id="deleteEventBtn" style="display: none;">Delete</button>
                <button type="button" class="btn-primary" id="saveEventBtn" onclick="document.getElementById('eventForm').requestSubmit()">Save</button>
            </div>
        </div>
    </div>
@endsection
