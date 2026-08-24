@extends('layouts.app')

@section('title', 'Lead Reports')

@section('content')
    @php
        $leadFormOptions = $leadFormOptions ?? \App\Models\Lead::formOptions();
    @endphp
    <div class="page-header leads-header">
        <div>
            <h1 class="page-title">Lead Reports</h1>
            <p class="page-subtitle">Filter leads, review charts, and download separate Excel files for leads, activity logs, or conversations.</p>
        </div>
        <div class="leads-header-actions">
            <a href="{{ route('leads') }}" class="btn btn-secondary">Back to Leads</a>
            <button type="button" class="btn btn-secondary lead-report-export-btn" data-export-type="leads">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Download Leads
            </button>
            <button type="button" class="btn btn-secondary lead-report-export-btn" data-export-type="activities">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Download Activity Log
            </button>
            <button type="button" class="btn btn-secondary lead-report-export-btn" data-export-type="conversations">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Download Conversations
            </button>
        </div>
    </div>

    <div class="leads-toolbar lead-reports-toolbar">
        <input type="search" id="reportSearch" class="leads-search" placeholder="Search name, phone, email, or label…">
        <div class="leads-label-filter" id="reportLabelFilter">
            <div id="reportLabelFilterChips" class="lead-label-filter-chips"></div>
            <select id="reportLabelFilterSelect" aria-label="Filter by labels">
                <option value="">Filter labels…</option>
            </select>
        </div>
        <select id="reportSourceFilter" class="leads-source-filter" aria-label="Filter by source">
            <option value="">All sources</option>
            <option value="__none__">No source</option>
        </select>
        <select id="reportAssigneeFilter" class="leads-assignee-filter" aria-label="Filter by assignee">
            <option value="">All assignees</option>
            <option value="__none__">Unassigned</option>
        </select>
        <select id="reportCustomerTypeFilter" class="leads-assignee-filter" aria-label="Filter by customer type">
            <option value="">All customer types</option>
            @foreach ($leadFormOptions['customer_types'] as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        <input type="date" id="reportDateFrom" class="leads-assignee-filter" aria-label="Created from">
        <input type="date" id="reportDateTo" class="leads-assignee-filter" aria-label="Created to">
        <div class="leads-tabs" role="tablist" id="reportStatusTabs">
            <button type="button" class="leads-tab active" data-status="all">All</button>
        </div>
        <div class="lead-reports-actions">
            <button type="button" class="btn btn-primary btn-sm" id="reportApplyBtn">Apply</button>
            <button type="button" class="btn btn-secondary btn-sm" id="reportResetBtn">Reset</button>
        </div>
    </div>

    <div class="stats-grid lead-report-kpis">
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Total leads</span>
            </div>
            <div class="stat-value" id="kpiTotal">—</div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Converted</span>
            </div>
            <div class="stat-value" id="kpiConverted">—</div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Lost</span>
            </div>
            <div class="stat-value" id="kpiLost">—</div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Conversion rate</span>
            </div>
            <div class="stat-value" id="kpiRate">—</div>
        </div>
    </div>

    <div class="lead-report-charts">
        <div class="leads-card lead-chart-card">
            <h3 class="lead-chart-title">By status</h3>
            <div class="lead-chart-wrap"><canvas id="chartStatus"></canvas></div>
        </div>
        <div class="leads-card lead-chart-card">
            <h3 class="lead-chart-title">By source</h3>
            <div class="lead-chart-wrap"><canvas id="chartSource"></canvas></div>
        </div>
        <div class="leads-card lead-chart-card">
            <h3 class="lead-chart-title">By label</h3>
            <div class="lead-chart-wrap"><canvas id="chartLabel"></canvas></div>
        </div>
        <div class="leads-card lead-chart-card">
            <h3 class="lead-chart-title">By assignee</h3>
            <div class="lead-chart-wrap"><canvas id="chartAssignee"></canvas></div>
        </div>
    </div>

    <div class="leads-card" style="margin-top: 1.25rem;">
        <div class="lead-preview-header">
            <h3 class="lead-chart-title" style="margin: 0;">Preview</h3>
            <span class="lead-meta" id="previewInfo">Showing up to 50 matching leads</span>
        </div>
        <div class="table-container">
            <table class="data-table leads-table">
                <thead>
                    <tr>
                        <th>Lead</th>
                        <th>Status</th>
                        <th>Source</th>
                        <th>Labels</th>
                        <th>Assignee</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody id="reportPreviewBody">
                    <tr><td colspan="6" class="empty-state">Loading report…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('styles')
<style>
.leads-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap; }
.leads-header-actions { display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap; }
.leads-header-actions .btn { display: inline-flex; align-items: center; gap: 0.4rem; }
.leads-toolbar { display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; margin-bottom: 1.25rem; }
.leads-search { flex: 1; min-width: 200px; padding: 0.55rem 0.85rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.9rem; background: var(--bg-card); }
.leads-label-filter { display: flex; flex-wrap: wrap; align-items: center; gap: 0.35rem; min-width: 200px; padding: 0.3rem 0.45rem; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-card); }
.leads-label-filter select { border: none; background: transparent; font-size: 0.9rem; color: var(--text-primary); min-width: 130px; padding: 0.25rem 0.2rem; }
.leads-source-filter, .leads-assignee-filter { min-width: 150px; padding: 0.5rem 0.7rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.9rem; background: var(--bg-card); color: var(--text-primary); }
.lead-label-filter-chips { display: flex; flex-wrap: wrap; gap: 0.3rem; }
.leads-tabs { display: flex; gap: 0.25rem; flex-wrap: wrap; }
.leads-tab { border: 1px solid var(--border); background: var(--bg-card); color: var(--text-secondary); border-radius: 999px; padding: 0.35rem 0.75rem; font-size: 0.8rem; font-weight: 600; cursor: pointer; }
.leads-tab.active { background: var(--accent); border-color: var(--accent); color: #fff; }
.lead-reports-actions { display: flex; gap: 0.4rem; }
.leads-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
.lead-report-charts { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
.lead-chart-card { padding: 1rem 1.1rem 0.75rem; }
.lead-chart-title { font-size: 0.95rem; font-weight: 700; margin: 0 0 0.75rem; color: var(--text-primary); }
.lead-chart-wrap { position: relative; height: 260px; }
.lead-preview-header { display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; padding: 1rem 1.1rem 0.5rem; }
.leads-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.leads-table th { text-align: left; padding: 0.7rem 1rem; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-secondary); border-bottom: 1px solid var(--border); background: var(--bg-primary); }
.leads-table td { padding: 0.75rem 1rem; border-bottom: 1px solid var(--border); vertical-align: top; }
.lead-name { font-weight: 600; }
.lead-company { font-size: 0.78rem; color: var(--text-secondary); }
.lead-meta { font-size: 0.8rem; color: var(--text-secondary); }
.lead-badge { display: inline-block; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; padding: 0.15rem 0.45rem; border-radius: 999px; background: #eef2ff; color: #4338ca; }
.lead-badge.contacted { background: #e0f2fe; color: #0369a1; }
.lead-badge.qualified { background: #dcfce7; color: #166534; }
.lead-badge.converted { background: #d1fae5; color: #065f46; }
.lead-badge.lost { background: #fee2e2; color: #991b1b; }
.lead-badge.snoozed { background: #fef3c7; color: #92400e; }
.lead-badge.archived { background: #e2e8f0; color: #475569; }
.lead-label-chip { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.18rem 0.5rem; border-radius: 999px; font-size: 0.72rem; font-weight: 700; }
.lead-label-chip button { background: none; border: none; color: inherit; cursor: pointer; font-size: 0.9rem; line-height: 1; padding: 0; opacity: 0.8; }
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
    const api = @json(url('/api/leads'));
    const exportUrl = @json(route('api.leads.reports.export'));
    const LEAD_OPTIONS = @json($leadFormOptions);
    const BOOTSTRAP_LABELS = @json($labels ?? []);
    const BOOTSTRAP_ASSIGNEES = @json($assignees ?? []);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

    const state = {
        status: 'all',
        source: '',
        assigned_to: '',
        customer_type: '',
        date_from: '',
        date_to: '',
        search: '',
        labelIds: [],
        companyLabels: Array.isArray(BOOTSTRAP_LABELS) ? BOOTSTRAP_LABELS.map(l => ({
            id: l.id,
            name: l.name,
            color: l.color || '#4338ca',
        })) : [],
        statuses: [],
        assignees: Array.isArray(BOOTSTRAP_ASSIGNEES) ? BOOTSTRAP_ASSIGNEES : [],
        charts: { status: null, source: null, label: null, assignee: null },
    };

    const COLORS = [
        '#4338ca', '#0369a1', '#166534', '#b45309', '#be123c',
        '#7c3aed', '#0f766e', '#c2410c', '#1d4ed8', '#4b5563',
    ];

    function headers() {
        const h = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
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

    function chipText(hex) {
        const c = String(hex || '#4338ca').replace('#', '');
        if (c.length !== 6) return '#fff';
        const r = parseInt(c.slice(0, 2), 16), g = parseInt(c.slice(2, 4), 16), b = parseInt(c.slice(4, 6), 16);
        return (r * 299 + g * 587 + b * 114) / 1000 > 160 ? '#111' : '#fff';
    }

    function statusName(slug) {
        const key = String(slug || '');
        if (!key) return '—';
        const row = (state.statuses || []).find(s => s.slug === key);
        return row?.name || key;
    }

    async function loadStatuses() {
        try {
            const res = await fetch(api + '/statuses', { credentials: 'same-origin', headers: headers() });
            const data = await res.json();
            state.statuses = data.data || [];
        } catch {
            state.statuses = [];
        }
        renderStatusTabs();
    }

    function renderStatusTabs() {
        const wrap = document.getElementById('reportStatusTabs');
        if (!wrap) return;
        const current = state.status || 'all';
        const tabs = [{ slug: 'all', name: 'All' }, ...(state.statuses || [])];
        wrap.innerHTML = tabs.map(status =>
            `<button type="button" class="leads-tab${current === status.slug ? ' active' : ''}" data-status="${esc(status.slug)}">${esc(status.name)}</button>`
        ).join('');
    }

    function buildParams() {
        const q = new URLSearchParams();
        if (state.search) q.set('search', state.search);
        if (state.status) q.set('status', state.status);
        if (state.source) q.set('source', state.source);
        if (state.assigned_to) q.set('assigned_to', state.assigned_to);
        if (state.customer_type) q.set('customer_type', state.customer_type);
        if (state.date_from) q.set('date_from', state.date_from);
        if (state.date_to) q.set('date_to', state.date_to);
        state.labelIds.forEach(id => q.append('label_ids[]', id));
        return q;
    }

    function selectedFilterLabels() {
        return state.companyLabels.filter(label => state.labelIds.includes(String(label.id)));
    }

    function renderLabelFilter() {
        const chips = document.getElementById('reportLabelFilterChips');
        const select = document.getElementById('reportLabelFilterSelect');
        const selected = selectedFilterLabels();
        chips.innerHTML = selected.map(label => `
            <span class="lead-label-chip" style="background:${esc(label.color || '#4338ca')};color:${chipText(label.color)}">
                ${esc(label.name)}
                <button type="button" data-unfilter-label="${label.id}" title="Remove filter">&times;</button>
            </span>
        `).join('');
        const available = state.companyLabels.filter(label => !state.labelIds.includes(String(label.id)));
        select.innerHTML = `<option value="">${selected.length ? 'Add label…' : 'Filter labels…'}</option>` +
            available.map(label => `<option value="${label.id}">${esc(label.name)}</option>`).join('');
    }

    function renderSourceFilter(sources) {
        const select = document.getElementById('reportSourceFilter');
        const fromDb = Array.isArray(sources) ? sources.filter(Boolean).map(String) : [];
        const list = [...new Set([...(LEAD_OPTIONS.sources || []), ...fromDb])];
        const current = state.source || '';
        select.innerHTML = `<option value="">All sources</option><option value="__none__"${current === '__none__' ? ' selected' : ''}>No source</option>` +
            list.map(source => `<option value="${esc(source)}"${current === source ? ' selected' : ''}>${esc(source)}</option>`).join('');
    }

    function renderAssigneeFilter() {
        const select = document.getElementById('reportAssigneeFilter');
        const current = state.assigned_to || '';
        select.innerHTML = `<option value="">All assignees</option><option value="__none__"${current === '__none__' ? ' selected' : ''}>Unassigned</option>` +
            state.assignees.map(user =>
                `<option value="${user.id}"${String(user.id) === current ? ' selected' : ''}>${esc(user.name)}</option>`
            ).join('');
    }

    function labelChips(labels) {
        return (labels || []).map(label =>
            `<span class="lead-label-chip" style="background:${esc(label.color || '#4338ca')};color:${chipText(label.color)}">${esc(label.name)}</span>`
        ).join(' ') || '<span class="lead-meta">—</span>';
    }

    function formatDate(iso) {
        if (!iso) return '—';
        const d = new Date(iso);
        if (Number.isNaN(d.getTime())) return '—';
        return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    }

    function destroyChart(key) {
        if (state.charts[key]) {
            state.charts[key].destroy();
            state.charts[key] = null;
        }
    }

    function upsertChart(key, canvasId, type, labels, values, colors) {
        destroyChart(key);
        const canvas = document.getElementById(canvasId);
        if (!canvas || typeof Chart === 'undefined') return;
        const empty = !labels.length;
        state.charts[key] = new Chart(canvas, {
            type,
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
                    legend: {
                        display: type === 'doughnut',
                        position: 'bottom',
                        labels: { boxWidth: 12, font: { size: 11 } },
                    },
                    tooltip: { enabled: !empty },
                },
                scales: type === 'doughnut' ? {} : {
                    x: {
                        ticks: { maxRotation: 45, minRotation: 0, font: { size: 10 } },
                        grid: { display: false },
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0, font: { size: 10 } },
                        grid: { color: 'rgba(148, 163, 184, 0.25)' },
                    },
                },
            },
        });
    }

    function renderCharts(data) {
        const status = data.by_status || [];
        upsertChart(
            'status',
            'chartStatus',
            'doughnut',
            status.map(r => r.label),
            status.map(r => r.count),
            status.map((_, i) => COLORS[i % COLORS.length])
        );

        const source = data.by_source || [];
        upsertChart(
            'source',
            'chartSource',
            'bar',
            source.map(r => r.label),
            source.map(r => r.count),
            source.map((_, i) => COLORS[i % COLORS.length])
        );

        const labels = data.by_label || [];
        upsertChart(
            'label',
            'chartLabel',
            'bar',
            labels.map(r => r.label),
            labels.map(r => r.count),
            labels.map((r, i) => r.color || COLORS[i % COLORS.length])
        );

        const assignees = data.by_assignee || [];
        upsertChart(
            'assignee',
            'chartAssignee',
            'bar',
            assignees.map(r => r.label),
            assignees.map(r => r.count),
            assignees.map((_, i) => COLORS[i % COLORS.length])
        );
    }

    function renderPreview(rows, total) {
        const body = document.getElementById('reportPreviewBody');
        const info = document.getElementById('previewInfo');
        const list = Array.isArray(rows) ? rows : [];
        info.textContent = list.length
            ? `Showing ${list.length} of ${total} matching leads`
            : `No leads match these filters`;

        if (!list.length) {
            body.innerHTML = `<tr><td colspan="6" class="empty-state">No leads match these filters.</td></tr>`;
            return;
        }

        body.innerHTML = list.map(lead => `
            <tr>
                <td>
                    <div class="lead-name">${esc(lead.name)}</div>
                    ${lead.company_name ? `<div class="lead-company">${esc(lead.company_name)}</div>` : ''}
                    <div class="lead-meta">${esc(lead.email || lead.phone || '')}</div>
                </td>
                <td><span class="lead-badge ${esc(lead.status || '')}">${esc(statusName(lead.status))}</span></td>
                <td>${esc(lead.source || '—')}</td>
                <td>${labelChips(lead.labels)}</td>
                <td>${esc(lead.assigned_user?.name || 'Unassigned')}</td>
                <td>${formatDate(lead.created_at)}</td>
            </tr>
        `).join('');
    }

    function renderKpis(totals) {
        document.getElementById('kpiTotal').textContent = String(totals?.total ?? 0);
        document.getElementById('kpiConverted').textContent = String(totals?.converted ?? 0);
        document.getElementById('kpiLost').textContent = String(totals?.lost ?? 0);
        document.getElementById('kpiRate').textContent = `${Number(totals?.conversion_rate ?? 0).toFixed(1)}%`;
    }

    function syncFiltersFromDom() {
        state.search = document.getElementById('reportSearch').value.trim();
        state.source = document.getElementById('reportSourceFilter').value;
        state.assigned_to = document.getElementById('reportAssigneeFilter').value;
        state.customer_type = document.getElementById('reportCustomerTypeFilter').value;
        state.date_from = document.getElementById('reportDateFrom').value;
        state.date_to = document.getElementById('reportDateTo').value;
    }

    async function loadReport() {
        syncFiltersFromDom();
        const body = document.getElementById('reportPreviewBody');
        body.innerHTML = `<tr><td colspan="6" class="empty-state">Loading report…</td></tr>`;

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
            renderSourceFilter(data.sources || []);
            renderKpis(data.totals || {});
            renderCharts(data);
            renderPreview(data.preview || [], data.totals?.total ?? 0);
        } catch (err) {
            body.innerHTML = `<tr><td colspan="6" class="empty-state">${esc(err.message || 'Failed to load report')}</td></tr>`;
        }
    }

    function resetFilters() {
        state.status = 'all';
        state.source = '';
        state.assigned_to = '';
        state.customer_type = '';
        state.date_from = '';
        state.date_to = '';
        state.search = '';
        state.labelIds = [];
        document.getElementById('reportSearch').value = '';
        document.getElementById('reportCustomerTypeFilter').value = '';
        document.getElementById('reportDateFrom').value = '';
        document.getElementById('reportDateTo').value = '';
        document.querySelectorAll('#reportStatusTabs .leads-tab').forEach(tab => {
            tab.classList.toggle('active', tab.dataset.status === 'all');
        });
        renderLabelFilter();
        renderAssigneeFilter();
        renderSourceFilter([]);
        loadReport();
    }

    document.getElementById('reportStatusTabs')?.addEventListener('click', (e) => {
        const tab = e.target.closest('.leads-tab');
        if (!tab) return;
        state.status = tab.dataset.status || 'all';
        document.querySelectorAll('#reportStatusTabs .leads-tab').forEach(t => t.classList.toggle('active', t === tab));
        loadReport();
    });

    document.getElementById('reportLabelFilterSelect').addEventListener('change', (e) => {
        const id = e.target.value;
        if (!id) return;
        if (!state.labelIds.includes(String(id))) {
            state.labelIds.push(String(id));
        }
        e.target.value = '';
        renderLabelFilter();
        loadReport();
    });

    document.getElementById('reportLabelFilterChips').addEventListener('click', (e) => {
        const btn = e.target.closest('[data-unfilter-label]');
        if (!btn) return;
        const id = String(btn.getAttribute('data-unfilter-label'));
        state.labelIds = state.labelIds.filter(x => x !== id);
        renderLabelFilter();
        loadReport();
    });

    document.getElementById('reportApplyBtn').addEventListener('click', loadReport);
    document.getElementById('reportResetBtn').addEventListener('click', resetFilters);
    document.getElementById('reportSearch').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            loadReport();
        }
    });

    ['reportSourceFilter', 'reportAssigneeFilter', 'reportCustomerTypeFilter', 'reportDateFrom', 'reportDateTo']
        .forEach(id => {
            document.getElementById(id).addEventListener('change', loadReport);
        });

    document.querySelectorAll('.lead-report-export-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            syncFiltersFromDom();
            const params = buildParams();
            params.set('type', btn.dataset.exportType || 'leads');
            window.location.href = exportUrl + '?' + params.toString();
        });
    });

    renderLabelFilter();
    renderAssigneeFilter();
    renderSourceFilter([]);
    loadStatuses().then(() => loadReport());
})();
</script>
@endpush
