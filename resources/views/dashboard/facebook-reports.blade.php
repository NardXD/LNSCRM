@extends('layouts.app')

@section('title', 'Facebook Reports')

@section('content')
    @php
        $reportDefaultDateFrom = now()->startOfMonth()->toDateString();
        $reportDefaultDateTo = now()->endOfMonth()->toDateString();
    @endphp
    <div class="page-header leads-header">
        <div>
            <h1 class="page-title">Facebook Reports</h1>
            <p class="page-subtitle">People messaging via Messenger and Instagram, and how many turn into leads, for a date range.</p>
        </div>
        <div class="leads-header-actions">
            <a href="{{ route('facebook') }}" class="btn btn-secondary">Back to Facebook</a>
        </div>
    </div>

    <div class="leads-toolbar lead-reports-toolbar">
        <input type="date" id="reportDateFrom" class="leads-assignee-filter" aria-label="From date" value="{{ $reportDefaultDateFrom }}">
        <input type="date" id="reportDateTo" class="leads-assignee-filter" aria-label="To date" value="{{ $reportDefaultDateTo }}">
        <div class="lead-reports-actions">
            <button type="button" class="btn btn-primary btn-sm" id="reportApplyBtn">Apply</button>
            <button type="button" class="btn btn-secondary btn-sm" id="reportResetBtn">Reset</button>
        </div>
    </div>

    <div class="stats-grid lead-report-kpis">
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Messages received</span>
            </div>
            <div class="stat-value" id="kpiMessagesReceived">—</div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">People via Messenger</span>
            </div>
            <div class="stat-value" id="kpiMessenger">—</div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">People via Instagram</span>
            </div>
            <div class="stat-value" id="kpiInstagram">—</div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Total people messaged</span>
            </div>
            <div class="stat-value" id="kpiPeopleMessaged">—</div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Leads generated</span>
            </div>
            <div class="stat-value" id="kpiLeadsCreated">—</div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Conversion rate</span>
            </div>
            <div class="stat-value" id="kpiConversionRate">—</div>
        </div>
    </div>

    <div class="lead-report-charts">
        <div class="leads-card lead-chart-card">
            <h3 class="lead-chart-title">People messaged by channel</h3>
            <div class="lead-chart-wrap"><canvas id="chartPeople"></canvas></div>
        </div>
        <div class="leads-card lead-chart-card">
            <h3 class="lead-chart-title">Messages by channel</h3>
            <div class="lead-chart-wrap"><canvas id="chartMessages"></canvas></div>
        </div>
        <div class="leads-card lead-chart-card">
            <h3 class="lead-chart-title">People messaged vs. leads generated</h3>
            <div class="lead-chart-wrap"><canvas id="chartCompare"></canvas></div>
            <p class="lead-chart-footnote" id="compareFootnote"></p>
        </div>
        <div class="leads-card lead-chart-card">
            <h3 class="lead-chart-title">Daily trend</h3>
            <div class="lead-chart-wrap"><canvas id="chartTrend"></canvas></div>
        </div>
    </div>

    <div class="leads-card" style="margin-top: 1.25rem;">
        <div class="lead-preview-header">
            <h3 class="lead-chart-title" style="margin: 0;">Daily breakdown</h3>
        </div>
        <div class="table-container">
            <table class="data-table leads-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Messenger messages</th>
                        <th>Instagram messages</th>
                        <th>Total messages</th>
                        <th>Leads created</th>
                    </tr>
                </thead>
                <tbody id="dailyTableBody">
                    <tr><td colspan="5" class="empty-state">Loading report…</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="leads-card" style="margin-top: 1.25rem;">
        <div class="lead-preview-header">
            <h3 class="lead-chart-title" style="margin: 0;">Recent leads from Facebook &amp; Instagram</h3>
            <span class="lead-meta" id="leadsPreviewInfo">Showing up to 50 most recent leads</span>
        </div>
        <div class="table-container">
            <table class="data-table leads-table">
                <thead>
                    <tr>
                        <th>Lead</th>
                        <th>Channel</th>
                        <th>Status</th>
                        <th>Assignee</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody id="leadsPreviewBody">
                    <tr><td colspan="5" class="empty-state">Loading report…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('styles')
<style>
.leads-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap; }
.leads-header-actions { display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap; }
.leads-toolbar { display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; margin-bottom: 1.25rem; }
.leads-assignee-filter { min-width: 150px; padding: 0.5rem 0.7rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.9rem; background: var(--bg-card); color: var(--text-primary); }
.lead-reports-actions { display: flex; gap: 0.4rem; }
.leads-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
.lead-report-charts { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; margin-bottom: 1.25rem; }
.lead-chart-card { padding: 1rem 1.1rem 0.75rem; }
.lead-chart-title { font-size: 0.95rem; font-weight: 700; margin: 0 0 0.75rem; color: var(--text-primary); }
.lead-chart-wrap { position: relative; height: 260px; }
.lead-chart-footnote { font-size: 0.75rem; color: var(--text-secondary); margin: 0.5rem 0 0; min-height: 1em; }
.lead-preview-header { display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; padding: 1rem 1.1rem 0.5rem; flex-wrap: wrap; }
.leads-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.leads-table th { text-align: left; padding: 0.7rem 1rem; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-secondary); border-bottom: 1px solid var(--border); background: var(--bg-primary); }
.leads-table td { padding: 0.75rem 1rem; border-bottom: 1px solid var(--border); vertical-align: top; }
.lead-name { font-weight: 600; }
.lead-meta { font-size: 0.8rem; color: var(--text-secondary); }
.lead-badge { display: inline-block; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; padding: 0.15rem 0.45rem; border-radius: 999px; background: #eef2ff; color: #4338ca; }
.lead-badge.contacted { background: #e0f2fe; color: #0369a1; }
.lead-badge.qualified { background: #dcfce7; color: #166534; }
.lead-badge.converted { background: #d1fae5; color: #065f46; }
.lead-badge.lost { background: #fee2e2; color: #991b1b; }
.lead-badge.snoozed { background: #fef3c7; color: #92400e; }
.lead-badge.archived { background: #e2e8f0; color: #475569; }
.channel-badge { display: inline-block; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; padding: 0.15rem 0.5rem; border-radius: 999px; background: #e2e8f0; color: #475569; }
.channel-badge.messenger { background: #e0e7ff; color: #4338ca; }
.channel-badge.instagram { background: #ffe4e6; color: #be123c; }
.empty-state { text-align: center; color: var(--text-secondary); padding: 2rem 1rem !important; }
@media (max-width: 960px) {
    .lead-report-charts { grid-template-columns: 1fr; }
}
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    const api = @json(url('/api/facebook'));
    const DEFAULT_DATE_FROM = @json($reportDefaultDateFrom);
    const DEFAULT_DATE_TO = @json($reportDefaultDateTo);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

    const state = {
        date_from: DEFAULT_DATE_FROM,
        date_to: DEFAULT_DATE_TO,
        charts: { people: null, messages: null, compare: null, trend: null },
    };

    const CHANNEL_COLORS = { messenger: '#4338ca', instagram: '#be123c', leads: '#166534' };

    function headers() {
        const h = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        if (csrf) h['X-CSRF-TOKEN'] = csrf;
        return h;
    }

    function esc(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function channelLabel(channel) {
        if (channel === 'messenger') return 'Messenger';
        if (channel === 'instagram') return 'Instagram';
        return 'Unknown';
    }

    function formatShortDate(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr + 'T00:00:00');
        if (Number.isNaN(d.getTime())) return dateStr;
        return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
    }

    function formatLongDate(dateStr) {
        if (!dateStr) return '—';
        const d = new Date(dateStr + 'T00:00:00');
        if (Number.isNaN(d.getTime())) return dateStr;
        return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    }

    function formatCreatedDate(iso) {
        if (!iso) return '—';
        const d = new Date(iso);
        if (Number.isNaN(d.getTime())) return '—';
        return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    }

    function buildParams() {
        const q = new URLSearchParams();
        if (state.date_from) q.set('date_from', state.date_from);
        if (state.date_to) q.set('date_to', state.date_to);
        return q;
    }

    function destroyChart(key) {
        if (state.charts[key]) {
            state.charts[key].destroy();
            state.charts[key] = null;
        }
    }

    function renderChart(key, canvasId, config) {
        destroyChart(key);
        const canvas = document.getElementById(canvasId);
        if (!canvas || typeof Chart === 'undefined') return;
        state.charts[key] = new Chart(canvas, config);
    }

    function doughnutConfig(labels, values, colors) {
        const empty = !values.some(v => v > 0);
        return {
            type: 'doughnut',
            data: {
                labels: empty ? ['No data'] : labels,
                datasets: [{
                    data: empty ? [1] : values,
                    backgroundColor: empty ? ['#e2e8f0'] : colors,
                    borderWidth: 0,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                    tooltip: { enabled: !empty },
                },
            },
        };
    }

    function barConfig(labels, datasets) {
        return {
            type: 'bar',
            data: { labels, datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                    y: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 } }, grid: { color: 'rgba(148, 163, 184, 0.25)' } },
                },
            },
        };
    }

    function lineConfig(labels, datasets) {
        return {
            type: 'line',
            data: { labels, datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                },
                scales: {
                    x: { grid: { display: false }, ticks: { maxRotation: 45, minRotation: 0, font: { size: 10 } } },
                    y: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 } }, grid: { color: 'rgba(148, 163, 184, 0.25)' } },
                },
            },
        };
    }

    function renderKpis(data) {
        document.getElementById('kpiMessagesReceived').textContent = String(data?.messages_received ?? 0);
        document.getElementById('kpiMessenger').textContent = String(data?.people_by_channel?.messenger ?? 0);
        document.getElementById('kpiInstagram').textContent = String(data?.people_by_channel?.instagram ?? 0);
        document.getElementById('kpiPeopleMessaged').textContent = String(data?.people_messaged ?? 0);
        document.getElementById('kpiLeadsCreated').textContent = String(data?.leads_created ?? 0);
        document.getElementById('kpiConversionRate').textContent = `${Number(data?.conversion_rate ?? 0).toFixed(1)}%`;
    }

    function renderCharts(data) {
        const peopleByChannel = data.people_by_channel || {};
        renderChart('people', 'chartPeople', doughnutConfig(
            ['Messenger', 'Instagram'],
            [peopleByChannel.messenger || 0, peopleByChannel.instagram || 0],
            [CHANNEL_COLORS.messenger, CHANNEL_COLORS.instagram]
        ));

        const messagesByChannel = data.messages_by_channel || {};
        renderChart('messages', 'chartMessages', doughnutConfig(
            ['Messenger', 'Instagram'],
            [messagesByChannel.messenger || 0, messagesByChannel.instagram || 0],
            [CHANNEL_COLORS.messenger, CHANNEL_COLORS.instagram]
        ));

        const leadsByChannel = data.leads_by_channel || {};
        renderChart('compare', 'chartCompare', barConfig(
            ['Messenger', 'Instagram'],
            [
                { label: 'People messaged', data: [peopleByChannel.messenger || 0, peopleByChannel.instagram || 0], backgroundColor: CHANNEL_COLORS.messenger, borderRadius: 4 },
                { label: 'Leads generated', data: [leadsByChannel.messenger || 0, leadsByChannel.instagram || 0], backgroundColor: CHANNEL_COLORS.leads, borderRadius: 4 },
            ]
        ));

        const footnote = document.getElementById('compareFootnote');
        const unknown = leadsByChannel.unknown || 0;
        footnote.textContent = unknown > 0
            ? `${unknown} lead(s) generated with an unresolved channel are excluded from this comparison.`
            : '';

        const daily = data.daily || [];
        renderChart('trend', 'chartTrend', lineConfig(
            daily.map(d => formatShortDate(d.date)),
            [
                { label: 'Messenger messages', data: daily.map(d => d.messenger_messages), borderColor: CHANNEL_COLORS.messenger, backgroundColor: CHANNEL_COLORS.messenger, tension: 0.3, fill: false },
                { label: 'Instagram messages', data: daily.map(d => d.instagram_messages), borderColor: CHANNEL_COLORS.instagram, backgroundColor: CHANNEL_COLORS.instagram, tension: 0.3, fill: false },
                { label: 'Leads created', data: daily.map(d => d.leads_created), borderColor: CHANNEL_COLORS.leads, backgroundColor: CHANNEL_COLORS.leads, tension: 0.3, fill: false, borderDash: [4, 3] },
            ]
        ));
    }

    function renderDailyTable(daily) {
        const body = document.getElementById('dailyTableBody');
        const list = Array.isArray(daily) ? daily : [];
        if (!list.length) {
            body.innerHTML = `<tr><td colspan="5" class="empty-state">No activity in this date range.</td></tr>`;
            return;
        }
        body.innerHTML = list.map(d => `
            <tr>
                <td>${esc(formatLongDate(d.date))}</td>
                <td>${Number(d.messenger_messages ?? 0)}</td>
                <td>${Number(d.instagram_messages ?? 0)}</td>
                <td>${Number(d.messenger_messages ?? 0) + Number(d.instagram_messages ?? 0)}</td>
                <td>${Number(d.leads_created ?? 0)}</td>
            </tr>
        `).join('');
    }

    function renderLeadsPreview(rows) {
        const body = document.getElementById('leadsPreviewBody');
        const info = document.getElementById('leadsPreviewInfo');
        const list = Array.isArray(rows) ? rows : [];
        info.textContent = list.length
            ? `Showing ${list.length} most recent lead(s)`
            : 'No leads match these filters';

        if (!list.length) {
            body.innerHTML = `<tr><td colspan="5" class="empty-state">No Facebook or Instagram leads in this date range.</td></tr>`;
            return;
        }

        body.innerHTML = list.map(lead => `
            <tr>
                <td class="lead-name">${esc(lead.name)}</td>
                <td><span class="channel-badge ${esc(lead.channel)}">${esc(channelLabel(lead.channel))}</span></td>
                <td><span class="lead-badge ${esc(lead.status || '')}">${esc(lead.status || '—')}</span></td>
                <td>${esc(lead.assigned_user?.name || 'Unassigned')}</td>
                <td>${formatCreatedDate(lead.created_at)}</td>
            </tr>
        `).join('');
    }

    function syncFiltersFromDom() {
        state.date_from = document.getElementById('reportDateFrom').value;
        state.date_to = document.getElementById('reportDateTo').value;
    }

    async function loadReport() {
        syncFiltersFromDom();
        try {
            const res = await fetch(api + '/reports?' + buildParams().toString(), {
                credentials: 'same-origin',
                headers: headers(),
            });
            const json = await res.json();
            if (!res.ok || !json.success) {
                throw new Error(json.message || 'Failed to load report');
            }
            const data = json.data || {};
            renderKpis(data);
            renderCharts(data);
            renderDailyTable(data.daily || []);
            renderLeadsPreview(data.leads_preview || []);
        } catch (err) {
            console.error(err);
        }
    }

    function resetFilters() {
        state.date_from = DEFAULT_DATE_FROM;
        state.date_to = DEFAULT_DATE_TO;
        document.getElementById('reportDateFrom').value = DEFAULT_DATE_FROM;
        document.getElementById('reportDateTo').value = DEFAULT_DATE_TO;
        loadReport();
    }

    document.getElementById('reportApplyBtn').addEventListener('click', loadReport);
    document.getElementById('reportResetBtn').addEventListener('click', resetFilters);

    loadReport();
})();
</script>
@endpush
