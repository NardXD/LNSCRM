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
            <p class="page-subtitle">New Messenger and Instagram messages, and leads generated from them, for a date range.</p>
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
                <span class="stat-label">Via Messenger</span>
            </div>
            <div class="stat-value" id="kpiMessenger">—</div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Via Instagram</span>
            </div>
            <div class="stat-value" id="kpiInstagram">—</div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Leads generated</span>
            </div>
            <div class="stat-value" id="kpiLeadsCreated">—</div>
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
.empty-state { text-align: center; color: var(--text-secondary); padding: 2rem 1rem !important; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const api = @json(url('/api/facebook'));
    const DEFAULT_DATE_FROM = @json($reportDefaultDateFrom);
    const DEFAULT_DATE_TO = @json($reportDefaultDateTo);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

    const state = {
        date_from: DEFAULT_DATE_FROM,
        date_to: DEFAULT_DATE_TO,
    };

    function headers() {
        const h = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        if (csrf) h['X-CSRF-TOKEN'] = csrf;
        return h;
    }

    function buildParams() {
        const q = new URLSearchParams();
        if (state.date_from) q.set('date_from', state.date_from);
        if (state.date_to) q.set('date_to', state.date_to);
        return q;
    }

    function renderKpis(data) {
        document.getElementById('kpiMessagesReceived').textContent = String(data?.messages_received ?? 0);
        document.getElementById('kpiMessenger').textContent = String(data?.messages_by_channel?.messenger ?? 0);
        document.getElementById('kpiInstagram').textContent = String(data?.messages_by_channel?.instagram ?? 0);
        document.getElementById('kpiLeadsCreated').textContent = String(data?.leads_created ?? 0);
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
            renderKpis(json.data || {});
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
