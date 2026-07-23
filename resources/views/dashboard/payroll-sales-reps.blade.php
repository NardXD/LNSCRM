@extends('layouts.app')

@section('title', 'Payroll Report')

@push('styles')
<style>
    .sales-reps-page {
        max-width: 1280px;
        margin: 0 auto;
    }

    .sales-reps-page .page-header {
        margin-bottom: 1rem;
    }

    .sales-reps-page .module-card {
        background: var(--bg-card, #fff);
        border: 1px solid var(--border, #e5e7eb);
        border-radius: 12px;
        padding: 1rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
    }

    .sales-reps-page .module-header {
        margin-bottom: 0.75rem;
    }

    .sales-reps-page .module-title {
        color: var(--text-primary);
        font-size: 1.125rem;
        font-weight: 700;
    }

    .sales-reps-page .date-input {
        border: 1px solid var(--border, #d1d5db);
        border-radius: 8px;
        padding: 0.45rem 0.6rem;
        background: var(--bg-primary, #fff);
        color: var(--text-primary, #111827);
        font-size: 0.875rem;
    }

    .sales-reps-page .date-range-separator {
        color: var(--text-secondary, #6b7280);
        font-size: 0.875rem;
    }

    .sales-reps-page .report-stat-card {
        background: var(--bg-primary, #f9fafb);
        border: 1px solid var(--border, #e5e7eb);
        border-radius: 10px;
        padding: 0.85rem 0.95rem;
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .sales-reps-page .report-stat-card.highlight {
        border-color: rgba(59, 130, 246, 0.35);
        background: linear-gradient(180deg, rgba(59,130,246,0.08) 0%, rgba(59,130,246,0.03) 100%);
    }

    .sales-reps-page .stat-label {
        color: var(--text-secondary, #6b7280);
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.02em;
    }

    .sales-reps-page .stat-amount {
        color: var(--text-primary, #111827);
        font-size: 1.05rem;
        font-weight: 700;
    }

    .sales-reps-page .table-container {
        border: 1px solid var(--border, #e5e7eb);
        border-radius: 10px;
        overflow: auto;
        background: var(--bg-card, #fff);
    }

    .sales-reps-page .data-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 760px;
        font-size: 0.875rem;
    }

    .sales-reps-page .data-table thead {
        background: var(--bg-primary, #f9fafb);
    }

    .sales-reps-page .data-table th {
        text-align: left;
        padding: 0.65rem 0.75rem;
        border-bottom: 1px solid var(--border, #e5e7eb);
        color: var(--text-secondary, #6b7280);
        font-weight: 600;
    }

    .sales-reps-page .data-table td {
        padding: 0.65rem 0.75rem;
        border-bottom: 1px solid var(--border, #e5e7eb);
        color: var(--text-primary, #111827);
        vertical-align: middle;
    }

    .sales-reps-page .data-table tbody tr:hover {
        background: rgba(59, 130, 246, 0.05);
    }

    .sales-reps-filter-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .sales-reps-filter-controls {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .sales-reps-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 0.75rem;
        margin: 1rem 0;
    }

    .sales-reps-module + .sales-reps-module {
        margin-top: 1rem;
    }

    .sales-reps-table td,
    .sales-reps-table th {
        white-space: nowrap;
    }

    .sales-reps-report-details-btn {
        padding: 0.35rem 0.65rem;
        font-size: 0.8125rem;
    }

    .sales-reps-modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(17, 24, 39, 0.6);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        padding: 1rem;
    }

    .sales-reps-modal-card {
        width: min(1200px, 96vw);
        max-height: 90vh;
        overflow: auto;
        background: var(--bg-card, #fff);
        border: 1px solid var(--border);
        border-radius: 12px;
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.22);
    }

    .sales-reps-modal-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.875rem 1rem;
        border-bottom: 1px solid var(--border);
        position: sticky;
        top: 0;
        background: var(--bg-card, #fff);
        z-index: 2;
    }

    .sales-reps-modal-body {
        padding: 0.75rem 1rem 1rem;
    }

    @media (max-width: 720px) {
        .sales-reps-page .module-card {
            padding: 0.75rem;
        }

        .sales-reps-filter-controls .date-input {
            min-width: 130px;
        }

        .sales-reps-table td,
        .sales-reps-table th {
            font-size: 0.8125rem;
        }
    }
</style>
@endpush

@section('content')
<div class="sales-reps-page">
<div class="page-header">
    <h1 class="page-title">Payroll Report</h1>
    <p class="page-subtitle">Generated payroll and commission totals grouped by sales rep</p>
</div>

<div class="module-card payroll-sales-reps-card sales-reps-module">
    <div class="module-header sales-reps-filter-row">
        <h2 class="module-title" style="margin:0;">Payroll Report by Sales Rep</h2>
        <div class="sales-reps-filter-controls">
            <input type="date" id="salesRepStartDate" class="date-input" value="{{ date('Y-m-01') }}">
            <span class="date-range-separator">to</span>
            <input type="date" id="salesRepEndDate" class="date-input" value="{{ date('Y-m-t') }}">
            <button type="button" class="btn-primary" id="loadSalesRepSummaryBtn">Load</button>
        </div>
    </div>

    <div class="sales-reps-stats">
        <div class="report-stat-card">
            <span class="stat-label">Sales Reps</span>
            <span class="stat-amount" id="salesRepTotalCount">0</span>
        </div>
        <div class="report-stat-card">
            <span class="stat-label">Bill Amount</span>
            <span class="stat-amount" id="salesRepTotalPayroll">$0.00</span>
        </div>
        <div class="report-stat-card highlight">
            <span class="stat-label">Commission</span>
            <span class="stat-amount" id="salesRepTotalCommission">$0.00</span>
        </div>
    </div>

    <div class="table-container sales-reps-table-wrap">
        <table class="data-table sales-reps-table">
            <thead>
                <tr>
                    <th>Sales Rep</th>
                    <th>Employees</th>
                    <th>Bill Amount</th>
                    <th>Commission</th>
                </tr>
            </thead>
            <tbody id="salesRepSummaryBody">
                <tr>
                    <td colspan="4" style="text-align:center; padding:2rem; color:var(--text-secondary);">Choose a date range and click Load.</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="module-card payroll-sales-reps-card sales-reps-module">
    <div class="module-header">
        <h2 class="module-title" style="margin:0;">Payroll Reports List</h2>
    </div>
    <div class="table-container sales-reps-table-wrap">
        <table class="data-table sales-reps-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Period</th>
                    <th>Employees</th>
                    <th>Total Amount</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th>Created At</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="payrollReportsListBody">
                <tr>
                    <td colspan="8" style="text-align:center; padding:2rem; color:var(--text-secondary);">Choose a date range and click Load.</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div id="reportDetailsModalOverlay" class="sales-reps-modal-overlay">
    <div class="sales-reps-modal-card">
        <div class="sales-reps-modal-head">
            <h2 class="module-title" id="selectedReportTitle" style="margin:0; font-size:1rem;">Generated Report Data</h2>
            <button type="button" class="btn-secondary" id="closeReportDetailsModalBtn">Close</button>
        </div>
        <div class="sales-reps-modal-body">
            <div class="table-container">
                <table class="data-table sales-reps-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Sales Rep</th>
                            <th>Bill Amount</th>
                            <th>Commission</th>
                            <th>Hours Worked</th>
                            <th>Net Pay</th>
                            <th>Invoice IDs</th>
                        </tr>
                    </thead>
                    <tbody id="payrollReportItemsBody">
                        <tr>
                            <td colspan="7" style="text-align:center; padding:2rem; color:var(--text-secondary);">Select a payroll report from the list above.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    function money(v) {
        const n = parseFloat(v) || 0;
        return '$' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function setTotals(totals) {
        document.getElementById('salesRepTotalCount').textContent = String(totals.sales_reps || 0);
        document.getElementById('salesRepTotalPayroll').textContent = money(totals.bill_amount || totals.generated_payroll || 0);
        document.getElementById('salesRepTotalCommission').textContent = money(totals.commission || 0);
    }

    function renderRows(rows) {
        const tbody = document.getElementById('salesRepSummaryBody');
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:2rem; color:var(--text-secondary);">No records for this date range.</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(function (row) {
            return '<tr>'
                + '<td>' + (row.sales_rep_name || 'Unassigned') + '</td>'
                + '<td>' + (row.employee_count || 0) + '</td>'
                + '<td>' + money(row.bill_amount || row.generated_payroll || 0) + '</td>'
                + '<td>' + money(row.commission || 0) + '</td>'
                + '</tr>';
        }).join('');
    }

    function renderReports(rows) {
        const tbody = document.getElementById('payrollReportsListBody');
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:2rem; color:var(--text-secondary);">No payroll reports found for this date range.</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(function (r) {
            const period = (r.period_start_date || '—') + ' to ' + (r.period_end_date || '—');
            return '<tr>'
                + '<td>#' + r.id + '</td>'
                + '<td>' + period + '</td>'
                + '<td>' + (r.employee_count || 0) + '</td>'
                + '<td>' + money(r.total_amount || 0) + '</td>'
                + '<td>' + (r.status || '—') + '</td>'
                + '<td>' + (r.created_by || '—') + '</td>'
                + '<td>' + (r.created_at || '—') + '</td>'
                + '<td><button type="button" class="btn-secondary sales-reps-report-details-btn view-report-details-btn" data-report-id="' + r.id + '">View</button></td>'
                + '</tr>';
        }).join('');

        tbody.querySelectorAll('.view-report-details-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const id = btn.getAttribute('data-report-id');
                if (id) loadReportDetails(id);
            });
        });
    }

    function renderReportItems(report, items) {
        const titleEl = document.getElementById('selectedReportTitle');
        const tbody = document.getElementById('payrollReportItemsBody');
        const period = (report.period_start_date || '—') + ' to ' + (report.period_end_date || '—');
        titleEl.textContent = 'Generated Report Data · #' + report.id + ' · ' + period;

        if (!items.length) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:2rem; color:var(--text-secondary);">No rows found for this payroll report.</td></tr>';
            return;
        }

        tbody.innerHTML = items.map(function (i) {
            const invoiceIds = Array.isArray(i.invoice_ids) && i.invoice_ids.length ? i.invoice_ids.join(', ') : '—';
            return '<tr>'
                + '<td>' + (i.employee_name || '—') + '</td>'
                + '<td>' + (i.sales_rep_name || 'Unassigned') + '</td>'
                + '<td>' + money(i.bill_amount || 0) + '</td>'
                + '<td>' + money(i.commission || 0) + '</td>'
                + '<td>' + (parseFloat(i.hours_worked || 0).toFixed(2)) + '</td>'
                + '<td>' + money(i.generated_payroll || 0) + '</td>'
                + '<td>' + invoiceIds + '</td>'
                + '</tr>';
        }).join('');
    }

    async function loadReportDetails(reportId) {
        const tbody = document.getElementById('payrollReportItemsBody');
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:2rem;">Loading report data...</td></tr>';
        openReportModal();

        try {
            const response = await fetch('/api/payroll/payroll-report/sales-reps/' + reportId);
            if (!response.ok) throw new Error('HTTP ' + response.status);
            const result = await response.json();
            if (!result.success) throw new Error(result.message || 'Failed to load report details');
            renderReportItems(result.report || {}, result.items || []);
        } catch (err) {
            console.error(err);
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:2rem; color:var(--danger, #dc3545);">Error loading report data. Please try again.</td></tr>';
        }
    }

    function openReportModal() {
        const overlay = document.getElementById('reportDetailsModalOverlay');
        if (!overlay) return;
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeReportModal() {
        const overlay = document.getElementById('reportDetailsModalOverlay');
        if (!overlay) return;
        overlay.style.display = 'none';
        overlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    async function loadSummary() {
        const startDate = document.getElementById('salesRepStartDate').value;
        const endDate = document.getElementById('salesRepEndDate').value;
        if (!startDate || !endDate) {
            alert('Please select both start and end dates.');
            return;
        }

        const tbody = document.getElementById('salesRepSummaryBody');
        const reportsBody = document.getElementById('payrollReportsListBody');
        const itemsBody = document.getElementById('payrollReportItemsBody');
        const titleEl = document.getElementById('selectedReportTitle');
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:2rem;">Loading...</td></tr>';
        reportsBody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:2rem;">Loading...</td></tr>';
        titleEl.textContent = 'Generated Report Data';
        itemsBody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:2rem; color:var(--text-secondary);">Select a payroll report from the list above.</td></tr>';
        setTotals({ sales_reps: 0, bill_amount: 0, generated_payroll: 0, commission: 0 });

        try {
            const params = new URLSearchParams({ start_date: startDate, end_date: endDate });
            const response = await fetch('/api/payroll/payroll-report/sales-reps?' + params.toString());
            if (!response.ok) throw new Error('HTTP ' + response.status);
            const result = await response.json();

            if (!result.success) {
                throw new Error(result.message || 'Failed to load data');
            }

            renderRows(result.data || []);
            renderReports(result.reports || []);
            setTotals(result.totals || {});
        } catch (err) {
            console.error(err);
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:2rem; color:var(--danger, #dc3545);">Error loading summary. Please try again.</td></tr>';
            reportsBody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:2rem; color:var(--danger, #dc3545);">Error loading reports list. Please try again.</td></tr>';
        }
    }

    document.getElementById('closeReportDetailsModalBtn')?.addEventListener('click', closeReportModal);
    document.getElementById('reportDetailsModalOverlay')?.addEventListener('click', function (ev) {
        if (ev.target === ev.currentTarget) closeReportModal();
    });
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') {
            closeReportModal();
        }
    });
    document.getElementById('loadSalesRepSummaryBtn')?.addEventListener('click', loadSummary);
    loadSummary();
})();
</script>
@endpush
