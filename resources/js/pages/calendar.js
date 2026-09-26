/* Vite page entry — IIFE preserves onclick globals */
(function () {
const CFG = window.__calendarConfig || {};
    const HOUR_HEIGHT = 72;
    let currentDate = new Date();
    let miniMonth = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
    let currentView = 'workweek';
    let inboxConnected = false;
    let inboxNeedsReconnect = false;
    let inboxEmail = '';
    let currentEditingEvent = null;
    let outlookCalendars = [];
    let hiddenCalendarIds = new Set();
    let events = [];
    let didInitialTimeScroll = false;
    let scrollTimedToNow = true;

    function initCalendar() {
        fetchCalendarStatus();
        updateCalendarTitle();
        renderCalendar();
    }

    function hasInboxCalendar() {
        return inboxConnected && !inboxNeedsReconnect;
    }

    function fetchCalendarStatus() {
        fetch((CFG.statusUrl || "/api/calendar/status"), {
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
            action.href = (CFG.inboxUrl || "/inbox");
        } else if (inboxNeedsReconnect) {
            label.textContent = (inboxEmail ? inboxEmail + ' · ' : '') + 'Reconnect for calendar';
            action.textContent = 'Reconnect';
            action.href = (CFG.inboxConnectOutlookUrl || "/inbox/connect/outlook");
        } else {
            label.textContent = inboxEmail || 'Personal Outlook';
            action.textContent = 'Open Inbox';
            action.href = (CFG.inboxUrl || "/inbox");
        }
        if (setupNote) setupNote.hidden = inboxConnected;
        if (reconnectNote) reconnectNote.hidden = !inboxNeedsReconnect;
    }

    function fetchExternalEvents() {
        if (!inboxConnected) return;
        const start = getViewStartDate();
        const end = getViewEndDate();
        const url = `${CFG.eventsUrl || "/api/calendar/events"}?start=${encodeURIComponent(start.toISOString())}&end=${encodeURIComponent(end.toISOString())}`;
        fetch(url, { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(data => {
            inboxNeedsReconnect = !!data.needs_reconnect;
            updateInboxAccountChip();
            outlookCalendars = data.calendars || [];
            renderOutlookCalendars();
            events = (data.events || []).map(e => ({
                ...e,
                id: 'ext_' + (e.id || Math.random()),
                external: true,
                calendar: e.calendar || 'outlook',
            }));
            renderCalendar();
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
            label.innerHTML = `<input type="checkbox" ${checked ? 'checked' : ''}><span class="calendar-dot"></span><span class="calendar-name"></span>`;
            label.querySelector('.calendar-dot').style.background = cal.color || '#0078D4';
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
        el.hidden = !message;
        el.textContent = message || '';
    }

    function setEventFormBusy(busy) {
        const saveBtn = document.getElementById('saveEventBtn');
        const deleteBtn = document.getElementById('deleteEventBtn');
        if (saveBtn) {
            saveBtn.disabled = busy;
            saveBtn.textContent = busy ? 'Saving…' : 'Save';
        }
        if (deleteBtn) deleteBtn.disabled = busy;
    }

    function toApiDateTime(date, time, allDay) {
        if (allDay) return date;
        return new Date(`${date}T${time || '00:00'}:00`).toISOString();
    }

    function ymd(date) {
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    }

    function parseYmd(value) {
        const [year, month, day] = String(value).split('-').map(Number);
        return new Date(year, month - 1, day);
    }

    function startOfDay(date) {
        return new Date(date.getFullYear(), date.getMonth(), date.getDate());
    }

    function addDays(date, n) {
        const d = startOfDay(date);
        d.setDate(d.getDate() + n);
        return d;
    }

    function getSunday(date) {
        const d = startOfDay(date);
        d.setDate(d.getDate() - d.getDay());
        return d;
    }

    function getMonday(date) {
        const d = startOfDay(date);
        const day = d.getDay();
        d.setDate(d.getDate() + (day === 0 ? -6 : 1 - day));
        return d;
    }

    function visibleDays() {
        if (currentView === 'day') return [startOfDay(currentDate)];
        if (currentView === 'workweek') {
            const mon = getMonday(currentDate);
            return [0,1,2,3,4].map(i => addDays(mon, i));
        }
        const sun = getSunday(currentDate);
        return [0,1,2,3,4,5,6].map(i => addDays(sun, i));
    }

    function getViewStartDate() {
        if (currentView === 'month') return getSunday(new Date(currentDate.getFullYear(), currentDate.getMonth(), 1));
        return visibleDays()[0];
    }

    function getViewEndDate() {
        if (currentView === 'month') {
            const d = getViewStartDate();
            d.setDate(d.getDate() + 42);
            return d;
        }
        const days = visibleDays();
        const end = addDays(days[days.length - 1], 1);
        end.setMilliseconds(-1);
        return end;
    }

    function updateCalendarTitle() {
        const title = document.getElementById('calendarTitle');
        if (currentView === 'month') {
            title.textContent = currentDate.toLocaleDateString('en-US', { year: 'numeric', month: 'long' });
            return;
        }
        const days = visibleDays();
        const start = days[0];
        const end = days[days.length - 1];
        if (days.length === 1) {
            title.textContent = start.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
            return;
        }
        title.textContent = `${start.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} – ${end.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}`;
    }

    function renderCalendar() {
        renderMiniCalendar();
        if (currentView === 'month') {
            document.getElementById('monthView').classList.add('active');
            document.getElementById('timedView').classList.remove('active');
            renderMonthView();
        } else {
            document.getElementById('monthView').classList.remove('active');
            document.getElementById('timedView').classList.add('active');
            renderTimedView();
        }
    }

    function renderMiniCalendar() {
        const root = document.getElementById('miniCalendar');
        const year = miniMonth.getFullYear();
        const month = miniMonth.getMonth();
        const label = miniMonth.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
        const first = new Date(year, month, 1);
        const start = getSunday(first);
        let html = `<div class="ms-mini-nav">
            <button type="button" onclick="shiftMini(-1)" aria-label="Previous month">‹</button>
            <div class="ms-mini-label">${label}</div>
            <button type="button" onclick="shiftMini(1)" aria-label="Next month">›</button>
        </div><div class="ms-mini-grid">`;
        ['S','M','T','W','T','F','S'].forEach(d => { html += `<div class="ms-mini-dow">${d}</div>`; });
        const cursor = new Date(start);
        for (let i = 0; i < 42; i++) {
            const muted = cursor.getMonth() !== month;
            const today = isSameDay(cursor, new Date());
            const selected = isSameDay(cursor, currentDate);
            const key = ymd(cursor);
            html += `<button type="button" class="ms-mini-day${muted ? ' muted' : ''}${today ? ' today' : ''}${selected && !today ? ' selected' : ''}" onclick="jumpToDate('${key}')">${cursor.getDate()}</button>`;
            cursor.setDate(cursor.getDate() + 1);
        }
        html += '</div>';
        root.innerHTML = html;
    }

    function shiftMini(delta) {
        miniMonth = new Date(miniMonth.getFullYear(), miniMonth.getMonth() + delta, 1);
        renderMiniCalendar();
    }

    function jumpToDate(key) {
        currentDate = parseYmd(key);
        miniMonth = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
        updateCalendarTitle();
        renderCalendar();
        if (hasInboxCalendar()) fetchExternalEvents();
    }

    function eventColor(event) {
        return event.color || '#0078D4';
    }

    function renderMonthView() {
        const container = document.getElementById('monthDays');
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();
        const startDate = getSunday(new Date(year, month, 1));
        let html = '';
        const cursor = new Date(startDate);
        for (let i = 0; i < 42; i++) {
            const other = cursor.getMonth() !== month;
            const today = isSameDay(cursor, new Date());
            const dayEvents = eventsOnDay(cursor);
            const key = ymd(cursor);
            html += `<div class="ms-month-cell${other ? ' other' : ''}${today ? ' today' : ''}" onclick="createOnDay('${key}')">
                <button type="button" class="ms-month-num" onclick="event.stopPropagation(); goToDay('${key}')">${cursor.getDate()}</button>
                <div class="ms-month-events">
                    ${dayEvents.slice(0, 3).map(event => `
                        <div class="ms-chip${isCanceledEvent(event) ? ' canceled' : ''}" style="border-left-color:${escapeHtml(eventColor(event))};background:${escapeHtml(eventColor(event))}22" onclick='event.stopPropagation(); viewEvent(${JSON.stringify(String(event.id))})' title="${escapeHtml(event.title)}">
                            ${event.allDay ? escapeHtml(event.title) : `${formatTime(event.start)} ${escapeHtml(event.title)}`}
                        </div>
                    `).join('')}
                    ${dayEvents.length > 3 ? `<div class="ms-chip more" onclick="event.stopPropagation(); goToDay('${key}')">+${dayEvents.length - 3} more</div>` : ''}
                </div>
            </div>`;
            cursor.setDate(cursor.getDate() + 1);
        }
        container.innerHTML = html;
    }

    function renderTimedView() {
        const days = visibleDays();
        const cols = `repeat(${days.length}, 1fr)`;
        document.getElementById('timedHeaders').style.gridTemplateColumns = cols;
        document.getElementById('allDayGrid').style.gridTemplateColumns = cols;
        document.getElementById('timedGrid').style.gridTemplateColumns = cols;

        document.getElementById('timedHeaders').innerHTML = days.map(day => {
            const today = isSameDay(day, new Date());
            const selected = isSameDay(day, currentDate);
            const weekend = day.getDay() === 0 || day.getDay() === 6;
            return `<div class="ms-day-head${today ? ' today' : ''}${selected ? ' selected' : ''}${weekend ? ' weekend' : ''}" onclick="goToDay('${ymd(day)}')">
                <div class="ms-day-dow">${day.toLocaleDateString('en-US', { weekday: 'short' })}</div>
                <div class="ms-day-num">${day.getDate()}</div>
            </div>`;
        }).join('');

        let hours = '';
        for (let h = 0; h < 24; h++) hours += `<div class="ms-hour-label">${formatHour(h)}</div>`;
        document.getElementById('timedHours').innerHTML = hours;

        document.getElementById('allDayGrid').innerHTML = days.map((day) => {
            const allDay = eventsOnDay(day).filter(e => e.allDay);
            const today = isSameDay(day, new Date());
            const weekend = day.getDay() === 0 || day.getDay() === 6;
            return `<div class="ms-allday-col${today ? ' today' : ''}${weekend ? ' weekend' : ''}" onclick="createOnDay('${ymd(day)}', true)">
                ${allDay.map(event => `<div class="ms-chip${isCanceledEvent(event) ? ' canceled' : ''}" style="border-left-color:${escapeHtml(eventColor(event))};background:${escapeHtml(eventColor(event))}22" onclick='event.stopPropagation(); viewEvent(${JSON.stringify(String(event.id))})'>${escapeHtml(event.title)}</div>`).join('')}
            </div>`;
        }).join('');

        const grid = document.getElementById('timedGrid');
        grid.innerHTML = '';
        days.forEach((day) => {
            const col = document.createElement('div');
            col.className = 'ms-day-col';
            if (isSameDay(day, new Date())) col.classList.add('today');
            if (day.getDay() === 0 || day.getDay() === 6) col.classList.add('weekend');
            for (let h = 0; h < 24; h++) {
                const slot = document.createElement('div');
                slot.className = 'ms-hour-slot';
                slot.onclick = (e) => {
                    const rect = slot.getBoundingClientRect();
                    const minutes = (e.clientY - rect.top) > rect.height / 2 ? 30 : 0;
                    createAt(day, h, minutes);
                };
                col.appendChild(slot);
            }
            const timed = eventsOnDay(day).filter(e => !e.allDay).map(event => timedPlacement(event, day));
            layoutTimedClusters(timed).forEach(item => {
                const el = document.createElement('div');
                const canceled = isCanceledEvent(item.event);
                el.className = 'ms-timed-event' + (canceled ? ' canceled' : '');
                el.style.background = eventColor(item.event);
                el.style.top = `${item.startH * HOUR_HEIGHT}px`;
                el.style.height = `${Math.max(item.endH - item.startH, 0.5) * HOUR_HEIGHT}px`;
                const geo = timedEventGeometry(item);
                el.style.left = geo.left;
                el.style.width = geo.width;
                el.style.zIndex = String(geo.z);
                const join = !canceled && item.event.joinUrl
                    ? '<button type="button" class="ms-join-chip">Join</button>'
                    : '';
                el.innerHTML = `<strong>${escapeHtml(item.event.title)}</strong>${formatTime(item.event.start)}${item.event.location ? ' · ' + escapeHtml(item.event.location) : ''}${join}`;
                el.onclick = (e) => { e.stopPropagation(); viewEvent(item.event.id); };
                const joinChip = el.querySelector('.ms-join-chip');
                if (joinChip && typeof item.event.joinUrl === 'string' && /^https:\/\//i.test(item.event.joinUrl)) {
                    joinChip.onclick = (e) => {
                        e.stopPropagation();
                        window.open(item.event.joinUrl, '_blank', 'noopener');
                    };
                }
                col.appendChild(el);
            });
            grid.appendChild(col);
        });

        paintNowLine(grid, days);
        scrollTimedGrid(days);
    }

    function isCanceledEvent(event) {
        if (event?.isCancelled || event?.isCanceled) return true;
        return /^\s*canceled:/i.test(String(event?.title || ''));
    }

    function timedPlacement(event, day) {
        const start = new Date(event.start);
        const end = new Date(event.end || event.start);
        let startH = start.getHours() + start.getMinutes() / 60;
        let endH = end.getHours() + end.getMinutes() / 60;
        if (!isSameDay(start, day)) startH = 0;
        if (!isSameDay(end, day)) endH = 24;
        if (endH <= startH) endH = startH + 0.5;
        return { event, startH, endH, col: 0, colCount: 1 };
    }

    function assignOverlapColumns(items) {
        const colEnds = [];
        items.forEach(item => {
            let idx = colEnds.findIndex(end => end <= item.startH + 0.01);
            if (idx < 0) {
                idx = colEnds.length;
                colEnds.push(item.endH);
            } else {
                colEnds[idx] = item.endH;
            }
            item.col = idx;
        });
        const n = Math.max(colEnds.length, 1);
        items.forEach(item => { item.colCount = n; });
    }

    function layoutTimedClusters(items) {
        items.sort((a, b) => a.startH - b.startH || b.endH - a.endH || String(a.event.title).localeCompare(String(b.event.title)));
        let cluster = [];
        let clusterEnd = -1;
        items.forEach(item => {
            if (cluster.length && item.startH >= clusterEnd - 0.01) {
                assignOverlapColumns(cluster);
                cluster = [];
                clusterEnd = -1;
            }
            cluster.push(item);
            clusterEnd = Math.max(clusterEnd, item.endH);
        });
        if (cluster.length) assignOverlapColumns(cluster);
        return items;
    }

    function timedEventGeometry(item) {
        const n = Math.max(item.colCount || 1, 1);
        const col = item.col || 0;
        const gap = 3;
        return {
            left: `calc(${(col / n) * 100}% + ${gap}px)`,
            width: `calc(${(1 / n) * 100}% - ${gap * 2}px)`,
            z: 2 + col,
        };
    }

    function scrollTimedGrid(days) {
        const scroller = document.getElementById('timedScroll');
        if (!scroller) return;
        const now = new Date();
        const inView = days.some(d => isSameDay(d, now));
        if (scrollTimedToNow || !didInitialTimeScroll) {
            if (inView) {
                scroller.scrollTop = Math.max((now.getHours() + now.getMinutes() / 60) * HOUR_HEIGHT - 96, 0);
            } else {
                scroller.scrollTop = 7 * HOUR_HEIGHT - 8;
            }
            didInitialTimeScroll = true;
            scrollTimedToNow = false;
        }
    }

    function paintNowLine(grid, days) {
        const now = new Date();
        const idx = days.findIndex(d => isSameDay(d, now));
        if (idx < 0) return;
        const line = document.createElement('div');
        line.className = 'ms-now-line';
        line.style.top = `${(now.getHours() + now.getMinutes() / 60) * HOUR_HEIGHT}px`;
        line.style.left = `calc(${idx} * 100% / ${days.length})`;
        line.style.width = `calc(100% / ${days.length})`;
        grid.appendChild(line);
    }

    function isSameDay(a, b) {
        return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
    }

    function eventsOnDay(date) {
        const dayKey = ymd(date);
        const dayStart = startOfDay(date);
        const dayEnd = addDays(dayStart, 1);
        return events.filter(event => {
            if (event.calendarId && hiddenCalendarIds.has(event.calendarId)) return false;
            if (event.allDay) {
                const startKey = String(event.start || '').slice(0, 10);
                let endKey = String(event.end || event.start || '').slice(0, 10);
                if (!startKey) return false;
                if (!endKey || endKey <= startKey) {
                    const next = parseYmd(startKey);
                    next.setDate(next.getDate() + 1);
                    endKey = ymd(next);
                }
                return dayKey >= startKey && dayKey < endKey;
            }
            const start = new Date(event.start);
            const end = new Date(event.end || event.start);
            return start < dayEnd && end > dayStart;
        }).sort((a, b) => {
            const canceledDiff = (isCanceledEvent(a) ? 1 : 0) - (isCanceledEvent(b) ? 1 : 0);
            if (canceledDiff) return canceledDiff;
            return new Date(a.start) - new Date(b.start);
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
        return new Date(dateString).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
    }

    function formatHour(hour) {
        if (hour === 0) return '';
        if (hour < 12) return `${hour} AM`;
        if (hour === 12) return '12 PM';
        return `${hour - 12} PM`;
    }

    function previousPeriod() {
        if (currentView === 'month') currentDate.setMonth(currentDate.getMonth() - 1);
        else if (currentView === 'day') currentDate.setDate(currentDate.getDate() - 1);
        else currentDate.setDate(currentDate.getDate() - 7);
        miniMonth = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
        updateCalendarTitle();
        renderCalendar();
        if (hasInboxCalendar()) fetchExternalEvents();
    }

    function nextPeriod() {
        if (currentView === 'month') currentDate.setMonth(currentDate.getMonth() + 1);
        else if (currentView === 'day') currentDate.setDate(currentDate.getDate() + 1);
        else currentDate.setDate(currentDate.getDate() + 7);
        miniMonth = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
        updateCalendarTitle();
        renderCalendar();
        if (hasInboxCalendar()) fetchExternalEvents();
    }

    function today() {
        currentDate = new Date();
        miniMonth = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
        scrollTimedToNow = true;
        updateCalendarTitle();
        renderCalendar();
        if (hasInboxCalendar()) fetchExternalEvents();
    }

    function switchView(view) {
        currentView = view;
        document.querySelectorAll('.ms-view-btn').forEach(btn => btn.classList.toggle('active', btn.dataset.view === view));
        updateCalendarTitle();
        renderCalendar();
        if (hasInboxCalendar()) fetchExternalEvents();
    }

    function goToDay(key) {
        currentDate = parseYmd(key);
        switchView('day');
    }

    function hintConnectInbox() {
        const note = document.getElementById('calendarSetupNote') || document.getElementById('calendarReconnectNote');
        if (!note) return;
        note.hidden = false;
        note.classList.remove('flash');
        void note.offsetWidth;
        note.classList.add('flash');
    }

    function openNewEvent() {
        if (!hasInboxCalendar()) {
            window.location.href = (CFG.inboxUrl || "/inbox");
            return;
        }
        openEventModal();
    }

    function createOnDay(key, allDay = true) {
        openEventModal(parseYmd(key), { allDay });
    }

    function createAt(day, hour, minutes = 0) {
        const date = startOfDay(day);
        date.setHours(hour, minutes, 0, 0);
        openEventModal(date, { hour, minutes });
    }

    function padTime(n) {
        return String(n).padStart(2, '0');
    }

    function formatClock(date) {
        return `${padTime(date.getHours())}:${padTime(date.getMinutes())}`;
    }

    function setJoinButton(url) {
        const btn = document.getElementById('joinMeetingBtn');
        if (!btn) return;
        const safe = typeof url === 'string' && /^https:\/\//i.test(url) ? url : '';
        if (safe) {
            btn.hidden = false;
            btn.href = safe;
        } else {
            btn.hidden = true;
            btn.removeAttribute('href');
        }
    }

    function setEventFormEditable(editable) {
        document.getElementById('eventForm').querySelectorAll('input, select, textarea').forEach(el => {
            el.disabled = !editable;
        });
        const saveBtn = document.getElementById('saveEventBtn');
        const deleteBtn = document.getElementById('deleteEventBtn');
        const discardBtn = document.getElementById('discardEventBtn');
        const note = document.getElementById('eventOrganizerNote');
        if (saveBtn) saveBtn.style.display = editable ? 'inline-flex' : 'none';
        if (deleteBtn) deleteBtn.style.display = editable && currentEditingEvent ? 'inline-flex' : 'none';
        if (discardBtn) discardBtn.textContent = editable ? 'Discard' : 'Close';
        if (note) note.hidden = editable;
        if (editable) toggleAllDay();
    }

    function openEventModal(date = null, options = {}) {
        if (!hasInboxCalendar()) {
            hintConnectInbox();
            return;
        }
        currentEditingEvent = null;
        document.getElementById('eventModalTitle').textContent = 'New event';
        document.getElementById('eventForm').reset();
        setEventFormEditable(true);
        populateEventCalendarSelect();
        setEventFormError('');
        setEventFormBusy(false);
        setJoinButton(null);

        const base = date ? new Date(date) : new Date();
        if (!date) {
            base.setMinutes(base.getMinutes() < 30 ? 30 : 60, 0, 0);
        }
        const start = new Date(base);
        if (Number.isFinite(options.hour)) {
            start.setHours(options.hour, Number.isFinite(options.minutes) ? options.minutes : 0, 0, 0);
        }
        const end = new Date(start.getTime() + 30 * 60000);
        document.getElementById('eventStartDate').value = ymd(start);
        document.getElementById('eventEndDate').value = ymd(options.allDay ? start : end);
        document.getElementById('eventStartTime').value = formatClock(start);
        document.getElementById('eventEndTime').value = formatClock(end);
        document.getElementById('eventAllDay').checked = !!options.allDay;
        document.getElementById('eventReminder').value = '15';
        document.getElementById('eventTeamsMeeting').checked = !options.allDay;
        toggleAllDay();

        document.getElementById('eventModal').classList.add('active');
        document.body.style.overflow = 'hidden';
        setTimeout(() => document.getElementById('eventTitle').focus(), 50);
    }

    function closeEventModal() {
        document.getElementById('eventModal').classList.remove('active');
        document.body.style.overflow = '';
        currentEditingEvent = null;
        setEventFormError('');
        setEventFormBusy(false);
        setJoinButton(null);
        setEventFormEditable(true);
    }

    function viewEvent(eventId) {
        const event = events.find(e => String(e.id) === String(eventId));
        if (!event) return;
        currentEditingEvent = event;
        const canEdit = event.isOrganizer === true;
        document.getElementById('eventModalTitle').textContent = canEdit ? 'Event' : 'Meeting';
        setEventFormError('');
        setEventFormBusy(false);
        document.getElementById('eventTitle').value = event.title;
        if (event.allDay) {
            document.getElementById('eventStartDate').value = String(event.start || '').slice(0, 10);
            const endKey = String(event.end || event.start || '').slice(0, 10);
            const exclusiveEnd = endKey && endKey > String(event.start || '').slice(0, 10)
                ? addDays(parseYmd(endKey), -1)
                : parseYmd(String(event.start || '').slice(0, 10));
            document.getElementById('eventEndDate').value = ymd(exclusiveEnd);
        } else {
            const start = new Date(event.start);
            const end = new Date(event.end || event.start);
            document.getElementById('eventStartDate').value = ymd(start);
            document.getElementById('eventEndDate').value = ymd(end);
            document.getElementById('eventStartTime').value = formatClock(start);
            document.getElementById('eventEndTime').value = formatClock(end);
        }
        document.getElementById('eventAllDay').checked = event.allDay;
        document.getElementById('eventDescription').value = event.description || '';
        document.getElementById('eventLocation').value = event.location || '';
        document.getElementById('eventAttendees').value = Array.isArray(event.attendees) ? event.attendees.join(', ') : (event.attendees || '');
        document.getElementById('eventTeamsMeeting').checked = !!(event.isOnlineMeeting || event.joinUrl);
        populateEventCalendarSelect(event.calendarId || '', true);
        setReminderValue(event.reminder);
        setJoinButton((isCanceledEvent(event) ? null : event.joinUrl) || null);
        setEventFormEditable(canEdit);
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
            teams_meeting: document.getElementById('eventTeamsMeeting').checked && !allDay,
        };
    }

    function saveEvent(e) {
        e.preventDefault();
        if (!hasInboxCalendar()) {
            setEventFormError('Connect your personal Microsoft 365 account in Inbox first.');
            return;
        }
        const payload = collectEventPayload();
        if (!payload.title) { setEventFormError('Add a title.'); return; }
        if (!payload.calendar_id) { setEventFormError('Choose a calendar.'); return; }
        if (currentEditingEvent && currentEditingEvent.isOrganizer !== true) {
            setEventFormError('Only the organizer can change this meeting.');
            return;
        }
        if (!payload.all_day && (!document.getElementById('eventStartTime').value || !document.getElementById('eventEndTime').value)) {
            setEventFormError('Enter a start and end time, or mark this as all day.');
            return;
        }
        const editing = currentEditingEvent;
        const url = editing
            ? `${CFG.eventsBaseUrl || "/api/calendar/events"}/${encodeURIComponent(outlookEventId(editing))}`
            : (CFG.eventsStoreUrl || "/api/calendar/events");
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
                if (data.needs_reconnect) { inboxNeedsReconnect = true; updateInboxAccountChip(); }
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
        if (!currentEditingEvent || currentEditingEvent.isOrganizer !== true) {
            setEventFormError('Only the organizer can change this meeting.');
            return;
        }
        if (!confirm('Delete this event from Outlook?')) return;
        setEventFormBusy(true);
        setEventFormError('');
        fetch(`${CFG.eventsBaseUrl || "/api/calendar/events"}/${encodeURIComponent(outlookEventId(currentEditingEvent))}`, {
            method: 'DELETE',
            headers: calendarApiHeaders(),
        })
        .then(async r => {
            const data = await r.json().catch(() => ({}));
            if (!r.ok) {
                if (data.needs_reconnect) { inboxNeedsReconnect = true; updateInboxAccountChip(); }
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
        const canEdit = !currentEditingEvent || currentEditingEvent.isOrganizer === true;
        document.getElementById('eventStartTime').disabled = allDay || !canEdit;
        document.getElementById('eventEndTime').disabled = allDay || !canEdit;
        const teamsGroup = document.getElementById('teamsMeetingGroup');
        if (teamsGroup) teamsGroup.style.display = allDay ? 'none' : '';
        if (allDay) document.getElementById('eventTeamsMeeting').checked = false;
    }

    function toggleCalendar(calendarId) {
        if (hiddenCalendarIds.has(calendarId)) hiddenCalendarIds.delete(calendarId);
        else hiddenCalendarIds.add(calendarId);
        renderCalendar();
    }

    document.getElementById('eventModal').addEventListener('click', function (e) {
        if (e.target === this) closeEventModal();
    });

    initCalendar();

    if (typeof String === 'function') window.String = String;
    if (typeof closeEventModal === 'function') window.closeEventModal = closeEventModal;
    if (typeof createOnDay === 'function') window.createOnDay = createOnDay;
    if (typeof deleteEvent === 'function') window.deleteEvent = deleteEvent;
    if (typeof getElementById === 'function') window.getElementById = getElementById;
    if (typeof goToDay === 'function') window.goToDay = goToDay;
    if (typeof jumpToDate === 'function') window.jumpToDate = jumpToDate;
    if (typeof nextPeriod === 'function') window.nextPeriod = nextPeriod;
    if (typeof openNewEvent === 'function') window.openNewEvent = openNewEvent;
    if (typeof previousPeriod === 'function') window.previousPeriod = previousPeriod;
    if (typeof shiftMini === 'function') window.shiftMini = shiftMini;
    if (typeof switchView === 'function') window.switchView = switchView;
    if (typeof today === 'function') window.today = today;
    if (typeof toggleAllDay === 'function') window.toggleAllDay = toggleAllDay;
})();
