@extends('layouts.client-portal')

@section('title', 'Billing & Payments')

@section('content')
    <div class="page-header">
        <h1 class="page-title">Billing & Payments</h1>
        <p class="page-subtitle">View your invoices and payment history</p>
    </div>

    <div class="billing-container">

        <!-- Stats Cards -->
        <div class="stats-grid" id="statsGrid">
            <div class="stat-card">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                        <line x1="1" y1="10" x2="23" y2="10"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <span class="stat-value" id="statTotal">0</span>
                    <span class="stat-label">Total Invoices</span>
                </div>
            </div>
            <div class="stat-card stat-sent">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="22" y1="2" x2="11" y2="13"/>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <span class="stat-value" id="statSentAmount">$0</span>
                    <span class="stat-label">Sent / Pending</span>
                </div>
            </div>
            <div class="stat-card stat-paid">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <span class="stat-value" id="statPaidAmount">$0</span>
                    <span class="stat-label">Total Paid</span>
                </div>
            </div>
            <div class="stat-card stat-overdue">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <span class="stat-value" id="statOverdueAmount">$0</span>
                    <span class="stat-label">Overdue</span>
                </div>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <button class="filter-tab active" data-status="all" onclick="setFilter('all')">All</button>
            <button class="filter-tab" data-status="sent" onclick="setFilter('sent')">
                <span class="tab-dot dot-sent"></span>Sent
            </button>
            <button class="filter-tab" data-status="paid" onclick="setFilter('paid')">
                <span class="tab-dot dot-paid"></span>Paid
            </button>
            <button class="filter-tab" data-status="overdue" onclick="setFilter('overdue')">
                <span class="tab-dot dot-overdue"></span>Overdue
            </button>
        </div>

        <!-- Invoice List -->
        <div class="invoices-list" id="invoicesList">
            <div class="loading-state">
                <div class="spinner"></div>
                <span>Loading invoices...</span>
            </div>
        </div>

    </div>

    <!-- Invoice Detail Modal -->
    <div class="modal" id="invoiceModal">
        <div class="modal-content" style="max-width: 680px;">
            <button class="modal-close" onclick="closeInvoiceModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>

            <div class="modal-header">
                <div style="display:flex; align-items:center; gap:1rem; flex-wrap:wrap;">
                    <div>
                        <h2 class="inv-modal-number" id="invModalNumber">INV-0000</h2>
                        <p class="inv-modal-dates" id="invModalDates"></p>
                    </div>
                    <span class="invoice-badge" id="invModalBadge"></span>
                </div>
            </div>

            <div class="modal-body">
                <!-- Items Table -->
                <div class="inv-items-wrap">
                    <table class="inv-items-table">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th class="text-right">Qty</th>
                                <th class="text-right">Unit Price</th>
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody id="invModalItems"></tbody>
                    </table>
                </div>

                <!-- Totals -->
                <div class="inv-totals">
                    <div class="inv-total-row">
                        <span>Subtotal</span>
                        <span id="invModalSubtotal">$0.00</span>
                    </div>
                    <div class="inv-total-row" id="invModalTaxRow">
                        <span id="invModalTaxLabel">Tax (0%)</span>
                        <span id="invModalTax">$0.00</span>
                    </div>
                    <div class="inv-total-row inv-total-final">
                        <span>Total</span>
                        <span id="invModalTotal">$0.00</span>
                    </div>
                </div>

                <!-- Notes -->
                <div class="inv-notes" id="invModalNotesWrap" style="display:none;">
                    <p class="inv-notes-label">Notes</p>
                    <p class="inv-notes-text" id="invModalNotes"></p>
                </div>

                <!-- Pay Now button (sent invoices with Stripe URL only) -->
                <div id="invModalActions" style="display:none; margin-top:1.5rem; gap:0.75rem; flex-wrap:wrap;">
                    <a id="invModalPayBtn" href="#" target="_blank" class="btn-pay" style="display:none;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;">
                            <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                            <line x1="1" y1="10" x2="23" y2="10"/>
                        </svg>
                        Pay Now
                    </a>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('styles')
<style>
    .billing-container {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    /* Stats */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }

    .stat-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.25rem;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        background: var(--accent-light);
        color: var(--accent);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .stat-icon svg { width: 22px; height: 22px; }

    .stat-card.stat-sent .stat-icon { background: #eff6ff; color: #3b82f6; }
    .stat-card.stat-paid .stat-icon { background: #ecfdf5; color: #10b981; }
    .stat-card.stat-overdue .stat-icon { background: #fef2f2; color: #ef4444; }

    .stat-content { display: flex; flex-direction: column; }
    .stat-value { font-size: 1.375rem; font-weight: 700; color: var(--text-primary); }
    .stat-label { font-size: 0.8125rem; color: var(--text-secondary); margin-top: 0.125rem; }

    /* Filter Tabs */
    .filter-tabs {
        display: flex;
        gap: 0.5rem;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 0.5rem;
        flex-wrap: wrap;
    }

    .filter-tab {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.5rem 1.125rem;
        border: none;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-secondary);
        background: transparent;
        cursor: pointer;
        transition: all 0.15s;
    }

    .filter-tab:hover { background: var(--bg-primary); color: var(--text-primary); }
    .filter-tab.active { background: var(--accent); color: white; }
    .filter-tab.active .tab-dot { border-color: rgba(255,255,255,0.6); background: white; }

    .tab-dot {
        width: 8px; height: 8px;
        border-radius: 50%;
        border: 2px solid transparent;
    }
    .dot-sent { background: #3b82f6; }
    .dot-paid { background: #10b981; }
    .dot-overdue { background: #ef4444; }

    /* Invoice List */
    .invoices-list {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .invoice-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.125rem 1.25rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        cursor: pointer;
        transition: all 0.15s;
    }

    .invoice-card:hover {
        border-color: var(--accent);
        box-shadow: 0 2px 10px rgba(16,185,129,0.08);
    }

    .invoice-icon {
        width: 42px; height: 42px;
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }
    .invoice-icon svg { width: 20px; height: 20px; }
    .invoice-icon.sent { background: #eff6ff; color: #3b82f6; }
    .invoice-icon.paid { background: #ecfdf5; color: #10b981; }
    .invoice-icon.overdue { background: #fef2f2; color: #ef4444; }

    .invoice-info { flex: 1; min-width: 0; }
    .invoice-number { font-size: 0.9375rem; font-weight: 600; color: var(--text-primary); }
    .invoice-meta { font-size: 0.8125rem; color: var(--text-secondary); margin-top: 0.2rem; }

    .invoice-right { display: flex; flex-direction: column; align-items: flex-end; gap: 0.4rem; flex-shrink: 0; }
    .invoice-amount { font-size: 1rem; font-weight: 700; color: var(--text-primary); }

    .invoice-badge {
        display: inline-block;
        padding: 0.2rem 0.625rem;
        border-radius: 9999px;
        font-size: 0.6875rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }
    .invoice-badge.sent { background: #eff6ff; color: #2563eb; }
    .invoice-badge.paid { background: #ecfdf5; color: #059669; }
    .invoice-badge.overdue { background: #fef2f2; color: #dc2626; }

    .invoice-due {
        font-size: 0.75rem;
        color: var(--text-secondary);
    }
    .invoice-due.overdue-text { color: #ef4444; font-weight: 500; }

    /* Modal */
    .modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }
    .modal.active { display: flex; }

    .modal-content {
        background: var(--bg-card);
        border-radius: 16px;
        max-width: 680px;
        width: 100%;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
        position: relative;
    }

    .modal-close {
        position: absolute;
        top: 1rem; right: 1rem;
        width: 36px; height: 36px;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 8px;
        color: var(--text-secondary);
        cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        z-index: 10;
    }
    .modal-close:hover { background: var(--border); color: var(--text-primary); }
    .modal-close svg { width: 18px; height: 18px; }

    .modal-header {
        padding: 1.5rem 1.5rem 1rem;
        border-bottom: 1px solid var(--border);
        padding-right: 3.5rem;
    }

    .inv-modal-number { font-size: 1.125rem; font-weight: 700; color: var(--text-primary); }
    .inv-modal-dates { font-size: 0.8125rem; color: var(--text-secondary); margin-top: 0.25rem; }

    .modal-body {
        flex: 1;
        overflow-y: auto;
        padding: 1.5rem;
    }

    /* Items Table */
    .inv-items-wrap { overflow-x: auto; margin-bottom: 1.25rem; }

    .inv-items-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
    }
    .inv-items-table th {
        text-align: left;
        padding: 0.625rem 0.75rem;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid var(--border);
    }
    .inv-items-table td {
        padding: 0.75rem;
        color: var(--text-primary);
        border-bottom: 1px solid var(--border);
    }
    .inv-items-table tbody tr:last-child td { border-bottom: none; }
    .text-right { text-align: right !important; }

    /* Totals */
    .inv-totals {
        border-top: 2px solid var(--border);
        padding-top: 1rem;
        margin-left: auto;
        width: 260px;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .inv-total-row {
        display: flex;
        justify-content: space-between;
        font-size: 0.875rem;
        color: var(--text-secondary);
    }
    .inv-total-row span:last-child { font-weight: 500; color: var(--text-primary); }

    .inv-total-final {
        padding-top: 0.625rem;
        margin-top: 0.25rem;
        border-top: 1px solid var(--border);
        font-size: 1rem;
        font-weight: 700;
    }
    .inv-total-final span { color: var(--text-primary) !important; font-weight: 700 !important; }

    /* Notes */
    .inv-notes { margin-top: 1.25rem; padding: 1rem; background: var(--bg-primary); border-radius: 8px; }
    .inv-notes-label { font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.375rem; }
    .inv-notes-text { font-size: 0.875rem; color: var(--text-primary); line-height: 1.6; white-space: pre-wrap; }

    /* Pay button */
    .btn-pay {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        background: var(--accent);
        color: white;
        border-radius: 10px;
        font-size: 0.9375rem;
        font-weight: 600;
        text-decoration: none;
        transition: opacity 0.15s;
    }
    .btn-pay:hover { opacity: 0.9; }

    /* Form helpers */
    .form-group { margin-bottom: 1rem; }
    .form-label { display: block; font-size: 0.875rem; font-weight: 500; color: var(--text-primary); margin-bottom: 0.375rem; }
    .form-input {
        width: 100%;
        padding: 0.625rem 0.75rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        background: var(--bg-primary);
        color: var(--text-primary);
        box-sizing: border-box;
    }
    .form-input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(16,185,129,0.1); }

    .btn-secondary {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.625rem 1.25rem;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
        cursor: pointer;
        transition: all 0.15s;
    }
    .btn-secondary:hover { background: var(--border); }

    /* Shared */
    .loading-state, .empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 3rem;
        color: var(--text-muted);
        text-align: center;
        gap: 0.75rem;
    }

    .spinner {
        width: 32px; height: 32px;
        border: 3px solid var(--border);
        border-top-color: var(--accent);
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    .empty-state svg { width: 48px; height: 48px; opacity: 0.35; }

    @media (max-width: 640px) {
        .invoice-card { flex-wrap: wrap; }
        .invoice-right { flex-direction: row; align-items: center; width: 100%; justify-content: space-between; }
        .inv-totals { width: 100%; }
    }
</style>
@endpush

@push('scripts')
<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const apiBase = '/client/api';
    let allInvoices = [];
    let activeFilter = 'all';
    let activeInvoice = null;

    document.addEventListener('DOMContentLoaded', () => {
        loadStats();
        loadInvoices();
    });

    async function loadStats() {
        try {
            const res = await fetch(`${apiBase}/billing/stats`, {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
            });
            const data = await res.json();
            if (data.success) {
                const s = data.stats;
                document.getElementById('statTotal').textContent = s.total;
                document.getElementById('statSentAmount').textContent = formatCurrency(s.sent_amount);
                document.getElementById('statPaidAmount').textContent = formatCurrency(s.paid_amount);
                document.getElementById('statOverdueAmount').textContent = formatCurrency(s.overdue_amount);
            }
        } catch (e) { console.error('Stats error', e); }
    }

    async function loadInvoices() {
        const list = document.getElementById('invoicesList');
        list.innerHTML = '<div class="loading-state"><div class="spinner"></div><span>Loading invoices...</span></div>';

        try {
            const url = activeFilter === 'all'
                ? `${apiBase}/billing/invoices`
                : `${apiBase}/billing/invoices?status=${activeFilter}`;

            const res = await fetch(url, {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
            });
            const data = await res.json();

            if (data.success) {
                allInvoices = data.invoices;
                renderInvoices(allInvoices);
            } else {
                list.innerHTML = '<div class="empty-state">Failed to load invoices.</div>';
            }
        } catch (e) {
            console.error('Invoices error', e);
            list.innerHTML = '<div class="empty-state">Error loading invoices. Please try again.</div>';
        }
    }

    function setFilter(status) {
        activeFilter = status;
        document.querySelectorAll('.filter-tab').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.status === status);
        });
        loadInvoices();
    }

    function renderInvoices(invoices) {
        const list = document.getElementById('invoicesList');

        if (!invoices.length) {
            const labels = { all: 'No invoices found.', sent: 'No pending invoices.', paid: 'No paid invoices.', overdue: 'No overdue invoices.' };
            list.innerHTML = `
                <div class="empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                        <line x1="1" y1="10" x2="23" y2="10"/>
                    </svg>
                    <p>${labels[activeFilter] || 'No invoices found.'}</p>
                </div>`;
            return;
        }

        list.innerHTML = invoices.map(inv => {
            const isOverdue = inv.status === 'overdue';
            const dueLabel = isOverdue
                ? `<span class="invoice-due overdue-text">Due ${inv.due_date}</span>`
                : `<span class="invoice-due">Due ${inv.due_date}</span>`;

            return `
                <div class="invoice-card" onclick="openInvoice(${inv.id})">
                    <div class="invoice-icon ${inv.status}">
                        ${iconFor(inv.status)}
                    </div>
                    <div class="invoice-info">
                        <div class="invoice-number">${inv.invoice_number}</div>
                        <div class="invoice-meta">Issued ${inv.date}</div>
                    </div>
                    <div class="invoice-right">
                        <span class="invoice-amount">${formatCurrency(inv.amount)}</span>
                        <span class="invoice-badge ${inv.status}">${badgeLabel(inv.status)}</span>
                        ${dueLabel}
                    </div>
                </div>`;
        }).join('');
    }

    function iconFor(status) {
        if (status === 'paid') return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>`;
        if (status === 'overdue') return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`;
        return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>`;
    }

    function badgeLabel(status) {
        return { sent: 'Sent', paid: 'Paid', overdue: 'Overdue' }[status] ?? status;
    }

    function formatCurrency(amount) {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(amount ?? 0);
    }

    // Invoice Detail Modal
    function openInvoice(id) {
        const inv = allInvoices.find(i => i.id === id);
        if (!inv) return;
        activeInvoice = inv;

        document.getElementById('invModalNumber').textContent = inv.invoice_number;
        document.getElementById('invModalDates').textContent = `Issued ${inv.date}  ·  Due ${inv.due_date}`;

        const badge = document.getElementById('invModalBadge');
        badge.textContent = badgeLabel(inv.status);
        badge.className = `invoice-badge ${inv.status}`;

        // Items
        const tbody = document.getElementById('invModalItems');
        if (inv.items && inv.items.length) {
            tbody.innerHTML = inv.items.map(item => `
                <tr>
                    <td>${escapeHtml(item.description)}</td>
                    <td class="text-right">${item.quantity}</td>
                    <td class="text-right">${formatCurrency(item.unit_price)}</td>
                    <td class="text-right">${formatCurrency(item.total)}</td>
                </tr>`).join('');
        } else {
            tbody.innerHTML = `<tr><td colspan="4" style="color:var(--text-muted);text-align:center;padding:1rem;">No line items</td></tr>`;
        }

        // Totals
        document.getElementById('invModalSubtotal').textContent = formatCurrency(inv.subtotal);
        document.getElementById('invModalTaxLabel').textContent = `Tax (${inv.tax_rate}%)`;
        document.getElementById('invModalTax').textContent = formatCurrency(inv.tax_amount);
        document.getElementById('invModalTotal').textContent = formatCurrency(inv.amount);

        const taxRow = document.getElementById('invModalTaxRow');
        taxRow.style.display = inv.tax_rate > 0 ? 'flex' : 'none';

        // Notes
        const notesWrap = document.getElementById('invModalNotesWrap');
        if (inv.notes && inv.notes.trim()) {
            document.getElementById('invModalNotes').textContent = inv.notes;
            notesWrap.style.display = 'block';
        } else {
            notesWrap.style.display = 'none';
        }

        // Pay Now button — only for sent invoices with a Stripe URL
        const actionsWrap = document.getElementById('invModalActions');
        const payBtn = document.getElementById('invModalPayBtn');

        if (inv.status === 'sent' && inv.stripe_payment_url) {
            payBtn.href = inv.stripe_payment_url;
            payBtn.style.display = 'inline-flex';
            actionsWrap.style.display = 'flex';
        } else {
            actionsWrap.style.display = 'none';
            payBtn.style.display = 'none';
        }

        document.getElementById('invoiceModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeInvoiceModal() {
        document.getElementById('invoiceModal').classList.remove('active');
        document.body.style.overflow = '';
        activeInvoice = null;
    }

    document.getElementById('invoiceModal').addEventListener('click', function(e) {
        if (e.target === this) closeInvoiceModal();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.getElementById('invoiceModal').classList.contains('active')) {
            closeInvoiceModal();
        }
    });

    function escapeHtml(text) {
        const d = document.createElement('div');
        d.textContent = text;
        return d.innerHTML;
    }
</script>
@endpush
