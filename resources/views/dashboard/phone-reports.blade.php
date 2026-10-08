@extends('layouts.app')

@section('title', 'Phone Reports')

@section('content')
    <div class="page-header pr-header">
        <div>
            <h1 class="page-title">Phone Reports</h1>
            <p class="page-subtitle">
                Call volume, call durations and recordings
                {{ $canViewAllUsers ? 'per user' : 'for your calls' }} over a date range.
            </p>
        </div>
        <div class="pr-header-actions">
            @if(auth()->user()->hasPermission('view_phone_system'))
                <a href="{{ route('twilio.call') }}" class="btn btn-secondary">Back to Phone System</a>
            @endif
        </div>
    </div>

    <div class="pr-toolbar">
        <div class="pr-field">
            <label for="reportDateFrom">From</label>
            <input type="date" id="reportDateFrom" class="pr-input" value="{{ $reportDefaultDateFrom }}">
        </div>
        <div class="pr-field">
            <label for="reportDateTo">To</label>
            <input type="date" id="reportDateTo" class="pr-input" value="{{ $reportDefaultDateTo }}">
        </div>
        <div class="pr-field">
            <label for="reportPreset">Quick range</label>
            <select id="reportPreset" class="pr-input">
                <option value="">Custom</option>
                <option value="month" selected>This month</option>
                <option value="today">Today</option>
                <option value="yesterday">Yesterday</option>
                <option value="last7">Last 7 days</option>
                <option value="last30">Last 30 days</option>
                <option value="lastMonth">Last month</option>
            </select>
        </div>
        @if($canViewAllUsers)
            <div class="pr-field">
                <label for="reportUser">User</label>
                <select id="reportUser" class="pr-input">
                    <option value="">All users</option>
                    <option value="unassigned">Unassigned calls</option>
                    @foreach($reportUsers as $reportUser)
                        <option value="{{ $reportUser['id'] }}">{{ $reportUser['name'] }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="pr-toolbar-actions">
            <button type="button" class="btn btn-primary btn-sm" id="reportApplyBtn">Apply</button>
            <button type="button" class="btn btn-secondary btn-sm" id="reportResetBtn">Reset</button>
        </div>
    </div>

    <div class="pr-error" id="reportError" role="alert" hidden></div>

    <div class="pr-hero">
        <div class="pr-hero-main">
            <span class="pr-hero-label">Total call duration</span>
            <span class="pr-hero-value" id="kpiTotalDuration">—</span>
            <span class="pr-hero-sub" id="kpiTotalDurationSub">&nbsp;</span>
        </div>
        <div class="pr-hero-side">
            <div>
                <span class="pr-hero-side-label">Total calls</span>
                <span class="pr-hero-side-value" id="kpiCalls">—</span>
            </div>
            <div>
                <span class="pr-hero-side-label">Avg call duration</span>
                <span class="pr-hero-side-value" id="kpiAvgDuration">—</span>
            </div>
            <div>
                <span class="pr-hero-side-label">Longest call</span>
                <span class="pr-hero-side-value" id="kpiLongest">—</span>
            </div>
        </div>
    </div>

    <div class="stats-grid pr-kpis">
        <div class="stat-card">
            <div class="stat-header"><span class="stat-label">Recordings</span></div>
            <div class="stat-value" id="kpiRecordings">—</div>
            <div class="pr-kpi-sub" id="kpiRecordedDuration">&nbsp;</div>
        </div>
        <div class="stat-card">
            <div class="stat-header"><span class="stat-label">Inbound calls</span></div>
            <div class="stat-value" id="kpiInbound">—</div>
        </div>
        <div class="stat-card">
            <div class="stat-header"><span class="stat-label">Outbound calls</span></div>
            <div class="stat-value" id="kpiOutbound">—</div>
        </div>
        <div class="stat-card">
            <div class="stat-header"><span class="stat-label">Completed calls</span></div>
            <div class="stat-value" id="kpiCompleted">—</div>
        </div>
        <div class="stat-card">
            <div class="stat-header"><span class="stat-label">Missed calls</span></div>
            <div class="stat-value" id="kpiMissed">—</div>
            <div class="pr-kpi-sub" id="kpiMissedRate">&nbsp;</div>
        </div>
    </div>

    <div class="pr-charts">
        <div class="pr-card pr-chart-card">
            <h3 class="pr-card-title">Calls per day</h3>
            <div class="pr-chart-wrap"><canvas id="chartDailyCalls" aria-label="Calls per day"></canvas></div>
        </div>
        <div class="pr-card pr-chart-card">
            <h3 class="pr-card-title">Call duration per day <span class="pr-card-unit">(minutes)</span></h3>
            <div class="pr-chart-wrap"><canvas id="chartDailyDuration" aria-label="Call duration per day in minutes"></canvas></div>
        </div>
        @if($canViewAllUsers)
            <div class="pr-card pr-chart-card pr-chart-wide">
                <h3 class="pr-card-title">Total call duration per user <span class="pr-card-unit">(minutes, top 15)</span></h3>
                <div class="pr-chart-wrap" id="chartUsersWrap"><canvas id="chartUsers" aria-label="Total call duration per user"></canvas></div>
            </div>
        @endif
        <div class="pr-card pr-chart-card">
            <h3 class="pr-card-title">Inbound vs outbound</h3>
            <div class="pr-chart-wrap"><canvas id="chartDirection" aria-label="Inbound versus outbound calls"></canvas></div>
        </div>
        <div class="pr-card pr-chart-card">
            <h3 class="pr-card-title">Call outcome</h3>
            <div class="pr-chart-wrap"><canvas id="chartStatus" aria-label="Calls by status"></canvas></div>
        </div>
        <div class="pr-card pr-chart-card">
            <h3 class="pr-card-title">Busiest hours</h3>
            <div class="pr-chart-wrap"><canvas id="chartHourly" aria-label="Calls by hour of day"></canvas></div>
        </div>
        <div class="pr-card pr-chart-card">
            <h3 class="pr-card-title">Call length distribution <span class="pr-card-unit">(connected calls)</span></h3>
            <div class="pr-chart-wrap"><canvas id="chartLengths" aria-label="Call length distribution"></canvas></div>
        </div>
    </div>

    <div class="pr-card pr-section">
        <div class="pr-section-header">
            <h3 class="pr-card-title" style="margin: 0;">{{ $canViewAllUsers ? 'Per-user summary' : 'Your summary' }}</h3>
            <span class="pr-meta" id="userSummaryInfo">&nbsp;</span>
        </div>
        <div class="table-container">
            <table class="data-table pr-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th class="num">Calls</th>
                        <th class="num">Inbound</th>
                        <th class="num">Outbound</th>
                        <th class="num">Completed</th>
                        <th class="num">Missed</th>
                        <th class="pr-col-highlight">Total duration</th>
                        <th class="num">Avg duration</th>
                        <th class="num">Longest</th>
                        <th class="num">Recordings</th>
                        <th class="num">Recorded time</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="userSummaryBody" aria-busy="true">
                    @include('partials.skeleton-table-rows', ['rows' => 4, 'cols' => 12])
                </tbody>
                <tfoot id="userSummaryFoot"></tfoot>
            </table>
        </div>
    </div>

    <div class="pr-card pr-section" id="callsSection">
        <div class="pr-section-header">
            <h3 class="pr-card-title" style="margin: 0;">Calls &amp; recordings</h3>
            <div class="pr-list-total" id="callsTotals">&nbsp;</div>
        </div>
        <div class="pr-list-filters">
            @if($canViewAllUsers)
                <select id="callsUser" class="pr-input" aria-label="Filter calls by user">
                    <option value="">All users</option>
                    <option value="unassigned">Unassigned calls</option>
                    @foreach($reportUsers as $reportUser)
                        <option value="{{ $reportUser['id'] }}">{{ $reportUser['name'] }}</option>
                    @endforeach
                </select>
            @endif
            <select id="callsDirection" class="pr-input" aria-label="Filter by direction">
                <option value="all">All directions</option>
                <option value="inbound">Inbound</option>
                <option value="outbound">Outbound</option>
            </select>
            <select id="callsOutcome" class="pr-input" aria-label="Filter by outcome">
                <option value="all">All outcomes</option>
                <option value="completed">Completed</option>
                <option value="missed">Missed</option>
            </select>
            <select id="callsSort" class="pr-input" aria-label="Sort calls">
                <option value="newest">Newest first</option>
                <option value="oldest">Oldest first</option>
                <option value="longest">Longest first</option>
            </select>
            <input type="search" id="callsSearch" class="pr-input pr-search" placeholder="Search number or user name" maxlength="100" aria-label="Search calls">
            <label class="pr-checkbox">
                <input type="checkbox" id="callsRecordingsOnly"> Recordings only
            </label>
            <a href="#" class="btn btn-secondary btn-sm pr-export" id="callsExportBtn">Export CSV</a>
        </div>
        <div class="table-container">
            <table class="data-table pr-table">
                <thead>
                    <tr>
                        <th>Date / time</th>
                        <th>User</th>
                        <th>Direction</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th class="pr-col-highlight">Duration</th>
                        <th>Recording</th>
                    </tr>
                </thead>
                <tbody id="callsBody" aria-busy="true">
                    @include('partials.skeleton-table-rows', ['rows' => 6, 'cols' => 7])
                </tbody>
            </table>
        </div>
        <div class="pr-pagination">
            <span class="pr-meta" id="callsPageInfo">&nbsp;</span>
            <div class="pr-pagination-controls">
                <select id="callsPerPage" class="pr-input" aria-label="Rows per page">
                    <option value="25">25 / page</option>
                    <option value="50">50 / page</option>
                    <option value="100">100 / page</option>
                </select>
                <button type="button" class="btn btn-secondary btn-sm" id="callsPrevBtn" disabled>Previous</button>
                <button type="button" class="btn btn-secondary btn-sm" id="callsNextBtn" disabled>Next</button>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
.pr-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap; }
.pr-header-actions { display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap; }
.pr-toolbar { display: flex; gap: 0.75rem; align-items: flex-end; flex-wrap: wrap; margin-bottom: 1.25rem; }
.pr-field { display: flex; flex-direction: column; gap: 0.25rem; }
.pr-field label { font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-secondary); }
.pr-input { min-width: 140px; padding: 0.5rem 0.7rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.9rem; background: var(--bg-card); color: var(--text-primary); }
.pr-search { min-width: 220px; }
.pr-toolbar-actions { display: flex; gap: 0.4rem; }
.pr-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 10px; padding: 0.75rem 1rem; margin-bottom: 1rem; font-size: 0.9rem; }

.pr-hero { display: flex; justify-content: space-between; align-items: stretch; gap: 1.5rem; flex-wrap: wrap; background: linear-gradient(135deg, var(--accent) 0%, var(--accent-hover) 100%); color: #fff; border-radius: 14px; padding: 1.25rem 1.5rem; margin-bottom: 1rem; }
.pr-hero-main { display: flex; flex-direction: column; gap: 0.2rem; }
.pr-hero-label { font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; opacity: 0.85; }
.pr-hero-value { font-size: 2.4rem; font-weight: 800; line-height: 1.1; font-variant-numeric: tabular-nums; }
.pr-hero-sub { font-size: 0.85rem; opacity: 0.9; }
.pr-hero-side { display: flex; gap: 2rem; align-items: center; flex-wrap: wrap; }
.pr-hero-side > div { display: flex; flex-direction: column; gap: 0.15rem; }
.pr-hero-side-label { font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.85; }
.pr-hero-side-value { font-size: 1.35rem; font-weight: 700; font-variant-numeric: tabular-nums; }

.pr-kpis { margin-bottom: 1.25rem; }
.pr-kpi-sub { font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.25rem; }

.pr-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
.pr-charts { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; margin-bottom: 1.25rem; }
.pr-chart-card { padding: 1rem 1.1rem 0.75rem; }
.pr-chart-wide { grid-column: 1 / -1; }
.pr-card-title { font-size: 0.95rem; font-weight: 700; margin: 0 0 0.75rem; color: var(--text-primary); }
.pr-card-unit { font-weight: 500; color: var(--text-secondary); font-size: 0.8rem; }
.pr-chart-wrap { position: relative; height: 260px; }

.pr-section { margin-top: 1.25rem; }
.pr-section-header { display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; padding: 1rem 1.1rem 0.5rem; flex-wrap: wrap; }
.pr-meta { font-size: 0.8rem; color: var(--text-secondary); }
.pr-list-total { font-size: 0.85rem; color: var(--text-secondary); }
.pr-list-total strong { color: var(--accent); font-size: 1rem; font-variant-numeric: tabular-nums; }
.pr-list-filters { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; padding: 0 1.1rem 0.85rem; }
.pr-list-filters .pr-input { min-width: 0; }
.pr-checkbox { display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.85rem; color: var(--text-primary); cursor: pointer; }
.pr-export { margin-left: auto; }

.pr-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.pr-table th { text-align: left; padding: 0.7rem 1rem; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-secondary); border-bottom: 1px solid var(--border); background: var(--bg-primary); white-space: nowrap; }
.pr-table td { padding: 0.7rem 1rem; border-bottom: 1px solid var(--border); vertical-align: middle; }
.pr-table .num { text-align: right; font-variant-numeric: tabular-nums; }
.pr-table th.pr-col-highlight { color: var(--accent); }
.pr-table td.pr-col-highlight { background: var(--accent-light); font-weight: 700; font-variant-numeric: tabular-nums; white-space: nowrap; }
.pr-table tfoot td { font-weight: 700; background: var(--bg-primary); border-top: 2px solid var(--border); }
.pr-share { display: block; height: 4px; border-radius: 999px; background: rgba(95, 97, 230, 0.15); margin-top: 0.35rem; min-width: 80px; }
.pr-share > span { display: block; height: 100%; border-radius: 999px; background: var(--accent); }
.pr-user-name { font-weight: 600; }
.pr-sub { display: block; font-size: 0.78rem; color: var(--text-secondary); margin-top: 0.1rem; }
.pr-link { color: var(--accent); text-decoration: none; }
.pr-link:hover { text-decoration: underline; }

.pr-badge { display: inline-block; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; padding: 0.15rem 0.5rem; border-radius: 999px; background: #e2e8f0; color: #475569; white-space: nowrap; }
.pr-badge.inbound { background: #e0ecfb; color: #1d4f91; }
.pr-badge.outbound { background: #fdebe3; color: #9a3a12; }
.pr-badge.completed { background: #dcfce7; color: #166534; }
.pr-badge.missed { background: #fee2e2; color: #991b1b; }

.pr-duration { display: inline-block; padding: 0.2rem 0.55rem; border-radius: 6px; font-weight: 700; font-variant-numeric: tabular-nums; background: #f1f5f9; color: #334155; }
.pr-duration.tier-1 { background: #e0e7ff; color: #3730a3; }
.pr-duration.tier-2 { background: #c7d2fe; color: #312e81; }
.pr-duration.tier-3 { background: var(--accent); color: #fff; }
.pr-duration.zero { background: transparent; color: var(--text-muted); font-weight: 500; }

.pr-recording { display: flex; align-items: center; gap: 0.5rem; }
.pr-recording audio { height: 32px; width: 240px; max-width: 100%; }
.pr-no-recording { color: var(--text-muted); font-size: 0.8rem; }

.pr-pagination { display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; padding: 0.75rem 1.1rem; flex-wrap: wrap; }
.pr-pagination-controls { display: flex; gap: 0.4rem; align-items: center; }
.empty-state { text-align: center; color: var(--text-secondary); padding: 2rem 1rem !important; }

@media (max-width: 960px) {
    .pr-charts { grid-template-columns: 1fr; }
    .pr-export { margin-left: 0; }
    .pr-hero-value { font-size: 2rem; }
}
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    const SUMMARY_URL = @json(route('api.phone-reports.summary'));
    const CALLS_URL = @json(route('api.phone-reports.calls'));
    const EXPORT_URL = @json(route('api.phone-reports.export'));
    const DEFAULT_DATE_FROM = @json($reportDefaultDateFrom);
    const DEFAULT_DATE_TO = @json($reportDefaultDateTo);
    const CAN_VIEW_ALL = @json($canViewAllUsers);

    // Validated categorical order (fixed per entity, never by rank).
    const SERIES = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#4a3aa7'];
    const PRIMARY = SERIES[0];
    const STATUS_COLORS = {
        completed: SERIES[0],
        'no-answer': SERIES[1],
        busy: SERIES[2],
        canceled: SERIES[3],
        failed: SERIES[4],
    };
    const OTHER_COLOR = '#94a3b8';
    const GRID_COLOR = 'rgba(148, 163, 184, 0.25)';

    const el = (id) => document.getElementById(id);

    const state = {
        date_from: DEFAULT_DATE_FROM,
        date_to: DEFAULT_DATE_TO,
        user_id: '',
        calls: { user_id: '', direction: 'all', outcome: 'all', sort: 'newest', search: '', recordings_only: false, page: 1, per_page: 25, last_page: 1 },
        charts: {},
        summarySeq: 0,
        callsSeq: 0,
    };

    function esc(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function num(value) {
        return Number(value ?? 0).toLocaleString();
    }

    // "1:02:03" / "4:05"
    function fmtClock(seconds) {
        const s = Math.max(0, Math.round(Number(seconds) || 0));
        const h = Math.floor(s / 3600);
        const m = Math.floor((s % 3600) / 60);
        const sec = s % 60;
        const pad = (n) => String(n).padStart(2, '0');
        return h > 0 ? `${h}:${pad(m)}:${pad(sec)}` : `${m}:${pad(sec)}`;
    }

    // "3h 12m 5s" / "12m 5s" / "45s"
    function fmtHuman(seconds) {
        const s = Math.max(0, Math.round(Number(seconds) || 0));
        const h = Math.floor(s / 3600);
        const m = Math.floor((s % 3600) / 60);
        const sec = s % 60;
        if (h > 0) return `${h}h ${m}m ${sec}s`;
        if (m > 0) return `${m}m ${sec}s`;
        return `${sec}s`;
    }

    function toMinutes(seconds) {
        return Math.round((Number(seconds) || 0) / 6) / 10;
    }

    function parseYmd(value) {
        const [y, m, d] = String(value).split('-').map(Number);
        return new Date(y, (m || 1) - 1, d || 1);
    }

    function toYmd(date) {
        const pad = (n) => String(n).padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
    }

    function fmtDay(ymd, withYear) {
        const opts = withYear ? { year: 'numeric', month: 'short', day: 'numeric' } : { month: 'short', day: 'numeric' };
        return parseYmd(ymd).toLocaleDateString(undefined, opts);
    }

    function fmtHour(hour) {
        const suffix = hour < 12 ? 'AM' : 'PM';
        const h = hour % 12 === 0 ? 12 : hour % 12;
        return `${h} ${suffix}`;
    }

    function statusLabel(status) {
        if (!status) return 'Unknown';
        return status.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
    }

    function showError(message) {
        const box = el('reportError');
        if (!message) {
            box.hidden = true;
            box.textContent = '';
            return;
        }
        box.hidden = false;
        box.textContent = message;
    }

    async function getJson(url, params) {
        const res = await fetch(url + '?' + params.toString(), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        let json = null;
        try {
            json = await res.json();
        } catch (e) {
            json = null;
        }
        if (!res.ok || !json || json.success === false) {
            const firstError = json && json.errors ? Object.values(json.errors)[0] : null;
            throw new Error((Array.isArray(firstError) ? firstError[0] : null) || json?.message || 'Failed to load the report.');
        }
        return json.data || {};
    }

    function baseParams(userId) {
        const q = new URLSearchParams();
        q.set('date_from', state.date_from);
        q.set('date_to', state.date_to);
        if (CAN_VIEW_ALL && userId) q.set('user_id', userId);
        return q;
    }

    function callsParams() {
        const c = state.calls;
        const q = baseParams(c.user_id);
        if (c.direction !== 'all') q.set('direction', c.direction);
        if (c.outcome !== 'all') q.set('outcome', c.outcome);
        if (c.sort !== 'newest') q.set('sort', c.sort);
        if (c.search) q.set('search', c.search);
        if (c.recordings_only) q.set('recordings_only', '1');
        return q;
    }

    /* ---------- Charts ---------- */

    function renderChart(key, canvasId, config) {
        if (state.charts[key]) {
            state.charts[key].destroy();
            state.charts[key] = null;
        }
        const canvas = el(canvasId);
        if (!canvas || typeof Chart === 'undefined') return;
        state.charts[key] = new Chart(canvas, config);
    }

    function barConfig(labels, data, opts) {
        const horizontal = !!opts.horizontal;
        const valueAxis = {
            beginAtZero: true,
            ticks: { precision: opts.precision ?? 0, font: { size: 10 } },
            grid: { color: GRID_COLOR },
            border: { display: false },
        };
        const categoryAxis = { grid: { display: false }, ticks: { font: { size: 10 }, autoSkip: !horizontal, maxRotation: 45 } };
        return {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: opts.label,
                    data,
                    backgroundColor: opts.color || PRIMARY,
                    borderRadius: 4,
                    borderSkipped: 'start',
                    maxBarThickness: 28,
                }],
            },
            options: {
                indexAxis: horizontal ? 'y' : 'x',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: opts.tooltip } },
                },
                scales: horizontal ? { x: valueAxis, y: categoryAxis } : { x: categoryAxis, y: valueAxis },
            },
        };
    }

    function doughnutConfig(labels, values, colors) {
        const total = values.reduce((a, b) => a + b, 0);
        const empty = total === 0;
        return {
            type: 'doughnut',
            data: {
                // Counts in the legend labels so identity never relies on color alone.
                labels: empty ? ['No calls'] : labels.map((label, i) => `${label} (${num(values[i])})`),
                datasets: [{
                    data: empty ? [1] : values,
                    backgroundColor: empty ? ['#e2e8f0'] : colors,
                    borderColor: '#ffffff',
                    borderWidth: 2,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                    tooltip: {
                        enabled: !empty,
                        callbacks: {
                            label: (ctx) => {
                                const pct = total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : '0.0';
                                return ` ${labels[ctx.dataIndex]}: ${num(ctx.parsed)} (${pct}%)`;
                            },
                        },
                    },
                },
            },
        };
    }

    function renderCharts(data) {
        const daily = Array.isArray(data.daily) ? data.daily : [];
        const dayLabels = daily.map((d) => fmtDay(d.date, false));

        renderChart('dailyCalls', 'chartDailyCalls', barConfig(dayLabels, daily.map((d) => d.calls), {
            label: 'Calls',
            tooltip: (ctx) => {
                const d = daily[ctx.dataIndex];
                return [` Calls: ${num(d.calls)}`, ` Missed: ${num(d.missed)}`, ` Recordings: ${num(d.recordings)}`];
            },
        }));

        renderChart('dailyDuration', 'chartDailyDuration', barConfig(dayLabels, daily.map((d) => toMinutes(d.duration)), {
            label: 'Minutes',
            precision: 1,
            tooltip: (ctx) => ` Duration: ${fmtHuman(daily[ctx.dataIndex].duration)}`,
        }));

        if (CAN_VIEW_ALL && el('chartUsers')) {
            const users = (Array.isArray(data.per_user) ? data.per_user : []).slice(0, 15);
            el('chartUsersWrap').style.height = Math.max(200, users.length * 30 + 40) + 'px';
            renderChart('users', 'chartUsers', barConfig(users.map((u) => u.name), users.map((u) => toMinutes(u.total_duration)), {
                label: 'Minutes',
                precision: 1,
                horizontal: true,
                tooltip: (ctx) => {
                    const u = users[ctx.dataIndex];
                    return [` Total duration: ${fmtHuman(u.total_duration)}`, ` Calls: ${num(u.calls)}`, ` Share: ${Number(u.duration_share).toFixed(1)}%`];
                },
            }));
        }

        const totals = data.totals || {};
        const otherDirection = Math.max(0, (totals.calls || 0) - (totals.inbound || 0) - (totals.outbound || 0));
        const dirLabels = ['Inbound', 'Outbound'];
        const dirValues = [totals.inbound || 0, totals.outbound || 0];
        const dirColors = [SERIES[0], SERIES[1]];
        if (otherDirection > 0) {
            dirLabels.push('Other');
            dirValues.push(otherDirection);
            dirColors.push(OTHER_COLOR);
        }
        renderChart('direction', 'chartDirection', doughnutConfig(dirLabels, dirValues, dirColors));

        // Known statuses keep a fixed color; anything else folds into "Other".
        const statusRows = Array.isArray(data.by_status) ? data.by_status : [];
        const statusLabels = [];
        const statusValues = [];
        const statusColors = [];
        let otherStatuses = 0;
        Object.keys(STATUS_COLORS).forEach((status) => {
            const row = statusRows.find((r) => r.status === status);
            if (row && row.count > 0) {
                statusLabels.push(statusLabel(status));
                statusValues.push(row.count);
                statusColors.push(STATUS_COLORS[status]);
            }
        });
        statusRows.forEach((r) => {
            if (!STATUS_COLORS[r.status]) otherStatuses += r.count;
        });
        if (otherStatuses > 0) {
            statusLabels.push('Other / in progress');
            statusValues.push(otherStatuses);
            statusColors.push(OTHER_COLOR);
        }
        renderChart('status', 'chartStatus', doughnutConfig(statusLabels, statusValues, statusColors));

        const hourly = Array.isArray(data.hourly) ? data.hourly : [];
        renderChart('hourly', 'chartHourly', barConfig(hourly.map((h) => fmtHour(h.hour)), hourly.map((h) => h.calls), {
            label: 'Calls',
            tooltip: (ctx) => {
                const h = hourly[ctx.dataIndex];
                return [` Calls: ${num(h.calls)}`, ` Duration: ${fmtHuman(h.duration)}`];
            },
        }));

        const buckets = Array.isArray(data.length_buckets) ? data.length_buckets : [];
        renderChart('lengths', 'chartLengths', barConfig(buckets.map((b) => b.label), buckets.map((b) => b.count), {
            label: 'Calls',
            tooltip: (ctx) => ` Calls: ${num(buckets[ctx.dataIndex].count)}`,
        }));
    }

    /* ---------- KPIs & per-user table ---------- */

    function renderKpis(data) {
        const t = data.totals || {};
        el('kpiTotalDuration').textContent = fmtHuman(t.total_duration);
        el('kpiTotalDurationSub').textContent =
            `${num(t.calls)} call(s) · ${fmtDay(data.date_from, true)} – ${fmtDay(data.date_to, true)}`;
        el('kpiCalls').textContent = num(t.calls);
        el('kpiAvgDuration').textContent = fmtHuman(t.avg_duration);
        el('kpiLongest').textContent = fmtHuman(t.longest_duration);
        el('kpiRecordings').textContent = num(t.recordings);
        el('kpiRecordedDuration').textContent = `${fmtHuman(t.recorded_duration)} recorded`;
        el('kpiInbound').textContent = num(t.inbound);
        el('kpiOutbound').textContent = num(t.outbound);
        el('kpiCompleted').textContent = num(t.completed);
        el('kpiMissed').textContent = num(t.missed);
        el('kpiMissedRate').textContent = `${Number(t.missed_rate || 0).toFixed(1)}% of calls`;
    }

    function renderUserSummary(data) {
        const body = el('userSummaryBody');
        const foot = el('userSummaryFoot');
        const rows = Array.isArray(data.per_user) ? data.per_user : [];
        body.removeAttribute('aria-busy');
        el('userSummaryInfo').textContent = rows.length ? `${rows.length} user(s) with calls` : '';

        if (!rows.length) {
            body.innerHTML = '<tr><td colspan="12" class="empty-state">No calls in this date range.</td></tr>';
            foot.innerHTML = '';
            return;
        }

        body.innerHTML = rows.map((u) => {
            const filterValue = u.user_id ? String(u.user_id) : 'unassigned';
            const share = Math.min(100, Math.max(0, Number(u.duration_share) || 0));
            return `
                <tr>
                    <td class="pr-user-name">${esc(u.name)}</td>
                    <td class="num">${num(u.calls)}</td>
                    <td class="num">${num(u.inbound)}</td>
                    <td class="num">${num(u.outbound)}</td>
                    <td class="num">${num(u.completed)}</td>
                    <td class="num">${num(u.missed)}</td>
                    <td class="pr-col-highlight" title="${esc(fmtClock(u.total_duration))}">
                        ${esc(fmtHuman(u.total_duration))}
                        ${CAN_VIEW_ALL ? `<span class="pr-share" title="${share.toFixed(1)}% of total duration"><span style="width: ${share}%"></span></span>` : ''}
                    </td>
                    <td class="num">${esc(fmtHuman(u.avg_duration))}</td>
                    <td class="num">${esc(fmtHuman(u.longest_duration))}</td>
                    <td class="num">${num(u.recordings)}</td>
                    <td class="num">${esc(fmtHuman(u.recorded_duration))}</td>
                    <td>${u.recordings > 0
                        ? `<button type="button" class="btn btn-secondary btn-sm" data-view-recordings="${esc(filterValue)}">View recordings</button>`
                        : ''}</td>
                </tr>`;
        }).join('');

        if (rows.length > 1) {
            const t = data.totals || {};
            foot.innerHTML = `
                <tr>
                    <td>Total</td>
                    <td class="num">${num(t.calls)}</td>
                    <td class="num">${num(t.inbound)}</td>
                    <td class="num">${num(t.outbound)}</td>
                    <td class="num">${num(t.completed)}</td>
                    <td class="num">${num(t.missed)}</td>
                    <td class="pr-col-highlight">${esc(fmtHuman(t.total_duration))}</td>
                    <td class="num">${esc(fmtHuman(t.avg_duration))}</td>
                    <td class="num">${esc(fmtHuman(t.longest_duration))}</td>
                    <td class="num">${num(t.recordings)}</td>
                    <td class="num">${esc(fmtHuman(t.recorded_duration))}</td>
                    <td></td>
                </tr>`;
        } else {
            foot.innerHTML = '';
        }
    }

    /* ---------- Calls & recordings list ---------- */

    function durationTier(seconds) {
        if (seconds <= 0) return 'zero';
        if (seconds >= 900) return 'tier-3';
        if (seconds >= 300) return 'tier-2';
        if (seconds >= 60) return 'tier-1';
        return '';
    }

    function renderCalls(data) {
        const body = el('callsBody');
        const rows = Array.isArray(data.data) ? data.data : [];
        const t = data.totals || {};
        const p = data.pagination || {};
        body.removeAttribute('aria-busy');

        el('callsTotals').innerHTML =
            `Total duration: <strong>${esc(fmtHuman(t.total_duration))}</strong> across ${num(t.calls)} call(s)` +
            ` · ${num(t.recordings)} recording(s), ${esc(fmtHuman(t.recorded_duration))} recorded`;

        state.calls.last_page = Math.max(1, Number(p.last_page) || 1);
        el('callsPageInfo').textContent = p.total ? `Showing ${num(p.from)}–${num(p.to)} of ${num(p.total)}` : '';
        el('callsPrevBtn').disabled = state.calls.page <= 1;
        el('callsNextBtn').disabled = state.calls.page >= state.calls.last_page;

        if (!rows.length) {
            body.innerHTML = '<tr><td colspan="7" class="empty-state">No calls match these filters.</td></tr>';
            return;
        }

        body.innerHTML = rows.map((c) => {
            const outcomeClass = c.outcome === 'completed' ? 'completed' : (c.outcome === 'missed' ? 'missed' : '');
            const contactName = c.lead && c.lead.name
                ? (c.lead.crm_url
                    ? `<a class="pr-link" href="${esc(c.lead.crm_url)}">${esc(c.lead.name)}</a>`
                    : esc(c.lead.name))
                : '';
            const numbers = `${esc(c.from || '—')} → ${esc(c.to || '—')}`;
            const recording = c.has_recording
                ? `<div class="pr-recording">
                        <audio controls preload="none" src="${esc(c.recording_url)}"></audio>
                        <a class="pr-link" href="${esc(c.recording_download_url)}" title="Download recording">Download</a>
                   </div>
                   ${c.recording_duration ? `<span class="pr-sub">Recorded ${esc(fmtClock(c.recording_duration))}</span>` : ''}`
                : '<span class="pr-no-recording">No recording</span>';

            return `
                <tr>
                    <td>${esc(c.occurred_at_label || '—')}</td>
                    <td>${esc(c.user ? c.user.name : 'Unassigned')}</td>
                    <td><span class="pr-badge ${esc(c.direction)}">${esc(c.direction)}</span></td>
                    <td>
                        ${contactName || esc(c.contact_number || '—')}
                        <span class="pr-sub">${numbers}</span>
                    </td>
                    <td><span class="pr-badge ${outcomeClass}">${esc(statusLabel(c.status))}</span></td>
                    <td class="pr-col-highlight"><span class="pr-duration ${durationTier(c.duration)}" title="${esc(fmtHuman(c.duration))}">${esc(fmtClock(c.duration))}</span></td>
                    <td>${recording}</td>
                </tr>`;
        }).join('');
    }

    function updateExportLink() {
        el('callsExportBtn').href = EXPORT_URL + '?' + callsParams().toString();
    }

    async function loadCalls() {
        const seq = ++state.callsSeq;
        const q = callsParams();
        q.set('page', String(state.calls.page));
        q.set('per_page', String(state.calls.per_page));
        updateExportLink();
        el('callsBody').setAttribute('aria-busy', 'true');
        try {
            const data = await getJson(CALLS_URL, q);
            if (seq !== state.callsSeq) return;
            // A narrower filter can leave us past the last page; step back once.
            const lastPage = Math.max(1, Number(data.pagination?.last_page) || 1);
            if (state.calls.page > lastPage) {
                state.calls.page = lastPage;
                return loadCalls();
            }
            renderCalls(data);
        } catch (err) {
            if (seq !== state.callsSeq) return;
            console.error(err);
            el('callsBody').removeAttribute('aria-busy');
            el('callsBody').innerHTML = `<tr><td colspan="7" class="empty-state">${esc(err.message)}</td></tr>`;
        }
    }

    async function loadSummary() {
        const seq = ++state.summarySeq;
        try {
            const data = await getJson(SUMMARY_URL, baseParams(state.user_id));
            if (seq !== state.summarySeq) return;
            showError('');
            renderKpis(data);
            renderCharts(data);
            renderUserSummary(data);
        } catch (err) {
            if (seq !== state.summarySeq) return;
            console.error(err);
            showError(err.message);
        }
    }

    /* ---------- Filters ---------- */

    function presetRange(preset) {
        const today = parseYmd(DEFAULT_DATE_TO);
        const from = new Date(today);
        const to = new Date(today);
        switch (preset) {
            case 'today':
                break;
            case 'yesterday':
                from.setDate(from.getDate() - 1);
                to.setDate(to.getDate() - 1);
                break;
            case 'last7':
                from.setDate(from.getDate() - 6);
                break;
            case 'last30':
                from.setDate(from.getDate() - 29);
                break;
            case 'lastMonth':
                from.setFullYear(today.getFullYear(), today.getMonth() - 1, 1);
                to.setFullYear(today.getFullYear(), today.getMonth(), 0);
                break;
            case 'month':
            default:
                from.setDate(1);
                break;
        }
        return [toYmd(from), toYmd(to)];
    }

    function applyFilters() {
        let from = el('reportDateFrom').value || DEFAULT_DATE_FROM;
        let to = el('reportDateTo').value || DEFAULT_DATE_TO;
        if (from > to) [from, to] = [to, from];
        el('reportDateFrom').value = from;
        el('reportDateTo').value = to;

        state.date_from = from;
        state.date_to = to;
        if (CAN_VIEW_ALL) {
            state.user_id = el('reportUser').value;
            state.calls.user_id = state.user_id;
            el('callsUser').value = state.user_id;
        }
        state.calls.page = 1;
        loadSummary();
        loadCalls();
    }

    function resetFilters() {
        el('reportDateFrom').value = DEFAULT_DATE_FROM;
        el('reportDateTo').value = DEFAULT_DATE_TO;
        el('reportPreset').value = 'month';
        if (CAN_VIEW_ALL) el('reportUser').value = '';
        el('callsDirection').value = 'all';
        el('callsOutcome').value = 'all';
        el('callsSort').value = 'newest';
        el('callsSearch').value = '';
        el('callsRecordingsOnly').checked = false;
        Object.assign(state.calls, { direction: 'all', outcome: 'all', sort: 'newest', search: '', recordings_only: false });
        applyFilters();
    }

    function onListFilterChange() {
        if (CAN_VIEW_ALL) state.calls.user_id = el('callsUser').value;
        state.calls.direction = el('callsDirection').value;
        state.calls.outcome = el('callsOutcome').value;
        state.calls.sort = el('callsSort').value;
        state.calls.recordings_only = el('callsRecordingsOnly').checked;
        state.calls.search = el('callsSearch').value.trim();
        state.calls.page = 1;
        loadCalls();
    }

    el('reportPreset').addEventListener('change', (e) => {
        if (!e.target.value) return;
        const [from, to] = presetRange(e.target.value);
        el('reportDateFrom').value = from;
        el('reportDateTo').value = to;
        applyFilters();
    });
    ['reportDateFrom', 'reportDateTo'].forEach((id) => {
        el(id).addEventListener('change', () => { el('reportPreset').value = ''; });
    });
    el('reportApplyBtn').addEventListener('click', applyFilters);
    el('reportResetBtn').addEventListener('click', resetFilters);

    ['callsDirection', 'callsOutcome', 'callsSort', 'callsRecordingsOnly'].concat(CAN_VIEW_ALL ? ['callsUser'] : [])
        .forEach((id) => el(id).addEventListener('change', onListFilterChange));

    let searchTimer = null;
    el('callsSearch').addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(onListFilterChange, 350);
    });

    el('callsPerPage').addEventListener('change', (e) => {
        state.calls.per_page = Number(e.target.value) || 25;
        state.calls.page = 1;
        loadCalls();
    });
    el('callsPrevBtn').addEventListener('click', () => {
        if (state.calls.page > 1) {
            state.calls.page--;
            loadCalls();
        }
    });
    el('callsNextBtn').addEventListener('click', () => {
        if (state.calls.page < state.calls.last_page) {
            state.calls.page++;
            loadCalls();
        }
    });

    el('userSummaryBody').addEventListener('click', (e) => {
        const btn = e.target.closest('[data-view-recordings]');
        if (!btn) return;
        if (CAN_VIEW_ALL) el('callsUser').value = btn.getAttribute('data-view-recordings');
        el('callsRecordingsOnly').checked = true;
        onListFilterChange();
        el('callsSection').scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    // Only one recording plays at a time.
    el('callsBody').addEventListener('play', (e) => {
        if (e.target.tagName !== 'AUDIO') return;
        el('callsBody').querySelectorAll('audio').forEach((audio) => {
            if (audio !== e.target) audio.pause();
        });
    }, true);

    loadSummary();
    loadCalls();
})();
</script>
@endpush
