<div class="qbq-toolbar">
    <div class="qbq-search-box">
        <input type="search" id="qbqSearch" class="qbq-search-input" placeholder="Search by quotation # or client…">
    </div>
    <select class="qbq-filter-select" id="qbqStatusFilter">
        <option value="all">All Status</option>
        <option value="draft">Draft</option>
        <option value="sent">Sent</option>
        <option value="accepted">Accepted</option>
        <option value="rejected">Rejected</option>
        <option value="expired">Expired</option>
        <option value="paid">Paid</option>
    </select>
</div>

<div class="qbq-stats-grid">
    <div class="qbq-stat-card">
        <span class="qbq-stat-label">Total Quotes</span>
        <div class="qbq-stat-value" id="qbqStatTotal">0</div>
    </div>
    <div class="qbq-stat-card">
        <span class="qbq-stat-label">Pending</span>
        <div class="qbq-stat-value" id="qbqStatPending">0</div>
    </div>
    <div class="qbq-stat-card">
        <span class="qbq-stat-label">Accepted</span>
        <div class="qbq-stat-value" id="qbqStatAccepted">0</div>
    </div>
    <div class="qbq-stat-card">
        <span class="qbq-stat-label">Total Value</span>
        <div class="qbq-stat-value" id="qbqStatValue">₱0</div>
    </div>
</div>

<div class="qbq-section">
    <div class="qbq-table-wrap">
        <table class="qbq-table" id="qbqTable">
            <thead>
                <tr>
                    <th>Quotation #</th>
                    <th>Client</th>
                    <th>Type</th>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="qbqTableBody">
                <tr><td colspan="7" class="qbq-empty-cell">Loading quotes…</td></tr>
            </tbody>
        </table>
    </div>

    <div class="qbq-cards" id="qbqCards"></div>

    <div class="qbq-pagination">
        <span id="qbqPaginationInfo">Showing 0 results</span>
        <div class="qbq-pagination-controls">
            <button type="button" class="qbq-pagination-btn" id="qbqPrevBtn" disabled>Previous</button>
            <button type="button" class="qbq-pagination-btn" id="qbqNextBtn" disabled>Next</button>
        </div>
    </div>
</div>

{{-- View Quote Modal --}}
<div class="qbq-modal-overlay" id="qbqViewModal" style="display:none;">
    <div class="qbq-modal-container">
        <div class="qbq-modal-header">
            <h3 class="qbq-modal-title">Quotation <span id="qbqViewNumber"></span></h3>
            <button type="button" class="qbq-modal-close" id="qbqCloseViewBtnTop" aria-label="Close">&times;</button>
        </div>
        <div class="qbq-modal-body">
            <div id="qbqViewStatus" style="margin-bottom:1rem;"></div>
            <div class="qbq-view-grid">
                <div class="qbq-view-row"><span class="qbq-view-label">Client</span><span class="qbq-view-value" id="qbqViewClient"></span></div>
                <div class="qbq-view-row"><span class="qbq-view-label">Type</span><span class="qbq-view-value" id="qbqViewType"></span></div>
                <div class="qbq-view-row"><span class="qbq-view-label">Date</span><span class="qbq-view-value" id="qbqViewDate"></span></div>
                <div class="qbq-view-row"><span class="qbq-view-label">Valid Until</span><span class="qbq-view-value" id="qbqViewValidUntil"></span></div>
            </div>

            <table class="qbq-table" style="margin-bottom:1rem;">
                <thead><tr><th>Item</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr></thead>
                <tbody id="qbqViewItemsBody"></tbody>
            </table>

            <div class="qbq-view-totals">
                <div class="qbq-view-totals-row"><span>Subtotal</span><span id="qbqViewSubtotal"></span></div>
                <div class="qbq-view-totals-row"><span>Tax</span><span id="qbqViewTax"></span></div>
                <div class="qbq-view-totals-row"><span>Discount</span><span id="qbqViewDiscount"></span></div>
                <div class="qbq-view-totals-row qbq-view-totals-total"><span>Total</span><span id="qbqViewTotal"></span></div>
            </div>

            <div class="qbq-status-update">
                <label class="qbq-view-label" for="qbqStatusSelect">Change status</label>
                <div class="qbq-status-update-row">
                    <select id="qbqStatusSelect" class="qbq-select"></select>
                    <button type="button" class="qbq-btn" id="qbqUpdateStatusBtn">Update</button>
                </div>
            </div>
        </div>
        <div class="qbq-modal-footer">
            <div class="qbq-modal-footer-left">
                <button type="button" class="qbq-btn qbq-btn-danger" id="qbqDeleteBtn">Delete</button>
            </div>
            <div class="qbq-modal-footer-right">
                <button type="button" class="qbq-btn" id="qbqDownloadPdfBtn">Download Quote PDF</button>
                <button type="button" class="qbq-btn" id="qbqDownloadAgreementBtn" style="display:none;">Download Agreement PDF</button>
                <button type="button" class="qbq-btn" id="qbqSendEmailBtn">Email Quote</button>
                <button type="button" class="qbq-btn qbq-btn-primary" id="qbqCreateContractBtn" style="display:none;">Create Contract</button>
                <a class="qbq-btn qbq-btn-primary" id="qbqViewContractBtn" style="display:none;" href="#">View Contract</a>
                <button type="button" class="qbq-btn qbq-btn-primary" id="qbqCloseViewBtn">Close</button>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .qbq-toolbar { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 1.25rem; }
    .qbq-search-box { flex: 1; min-width: 220px; }
    .qbq-search-input { width: 100%; padding: 0.625rem 0.75rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.875rem; background: var(--bg-card); color: var(--text-primary); }
    .qbq-filter-select { padding: 0.625rem 0.75rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.875rem; background: var(--bg-card); color: var(--text-primary); cursor: pointer; }
    .qbq-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.25rem; }
    .qbq-stat-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 1.1rem 1.25rem; }
    .qbq-stat-label { font-size: 0.8125rem; color: var(--text-secondary); }
    .qbq-stat-value { font-size: 1.5rem; font-weight: 700; color: var(--text-primary); margin-top: 0.35rem; }
    .qbq-section { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 1.25rem; }
    .qbq-table-wrap { overflow-x: auto; }
    .qbq-table { width: 100%; border-collapse: collapse; }
    .qbq-table thead { background: var(--bg-primary); }
    .qbq-table th { padding: 0.75rem 0.9rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 2px solid var(--border); white-space: nowrap; }
    .qbq-table td { padding: 0.85rem 0.9rem; font-size: 0.875rem; color: var(--text-primary); border-bottom: 1px solid var(--border); }
    .qbq-table tbody tr:hover { background: var(--bg-primary); cursor: pointer; }
    .qbq-empty-cell { text-align: center; color: var(--text-muted); padding: 2rem !important; cursor: default !important; }
    .qbq-badge { padding: 0.2rem 0.65rem; border-radius: 100px; font-size: 0.6875rem; font-weight: 600; display: inline-block; text-transform: capitalize; }
    .qbq-badge.draft { background: #e5e7eb; color: #374151; }
    .qbq-badge.sent { background: #dbeafe; color: #2563eb; }
    .qbq-badge.accepted { background: #d1fae5; color: #059669; }
    .qbq-badge.rejected { background: #fee2e2; color: #dc2626; }
    .qbq-badge.expired { background: #fef3c7; color: #d97706; }
    .qbq-badge.paid { background: #ede9fe; color: #7c3aed; }
    .qbq-badge.type-storage { background: #e0f2fe; color: #0369a1; }
    .qbq-badge.type-standard { background: #f1f5f9; color: #475569; }
    .qbq-table-actions { display: flex; gap: 0.4rem; }
    .qbq-icon-btn { width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; background: none; border: 1px solid var(--border); border-radius: 6px; color: var(--text-secondary); cursor: pointer; font-size: 0.9rem; }
    .qbq-icon-btn:hover { background: var(--bg-primary); border-color: var(--accent); color: var(--accent); }
    .qbq-cards { display: none; flex-direction: column; gap: 0.85rem; }
    .qbq-card { background: var(--bg-primary); border: 1px solid var(--border); border-radius: 10px; padding: 1rem; cursor: pointer; }
    .qbq-pagination { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; padding-top: 1.25rem; margin-top: 1rem; border-top: 1px solid var(--border); }
    .qbq-pagination-info { font-size: 0.875rem; color: var(--text-secondary); }
    .qbq-pagination-controls { display: flex; gap: 0.5rem; }
    .qbq-pagination-btn { padding: 0.5rem 0.9rem; border: 1px solid var(--border); background: var(--bg-card); border-radius: 8px; font-size: 0.8125rem; color: var(--text-primary); cursor: pointer; }
    .qbq-pagination-btn:disabled { opacity: 0.5; cursor: not-allowed; }
    .qbq-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; padding: 1rem; }
    .qbq-modal-container { background: var(--bg-card); border-radius: 16px; width: 100%; max-width: 720px; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; }
    .qbq-modal-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
    .qbq-modal-title { font-size: 1.25rem; font-weight: 700; color: var(--text-primary); margin: 0; }
    .qbq-modal-close { width: 34px; height: 34px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-primary); color: var(--text-secondary); cursor: pointer; font-size: 1.1rem; line-height: 1; }
    .qbq-modal-body { padding: 1.5rem; overflow-y: auto; flex: 1; }
    .qbq-view-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.75rem 1.5rem; margin-bottom: 1.25rem; padding: 1rem; background: var(--bg-primary); border: 1px solid var(--border); border-radius: 12px; }
    .qbq-view-row { display: flex; flex-direction: column; gap: 0.2rem; }
    .qbq-view-label { font-size: 0.6875rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em; }
    .qbq-view-value { font-size: 0.9375rem; color: var(--text-primary); font-weight: 500; }
    .qbq-view-totals { margin-bottom: 1.25rem; padding: 0.85rem 1rem; background: var(--bg-primary); border: 1px solid var(--border); border-radius: 12px; }
    .qbq-view-totals-row { display: flex; justify-content: space-between; font-size: 0.875rem; padding: 0.25rem 0; color: var(--text-secondary); }
    .qbq-view-totals-total { font-weight: 700; color: var(--text-primary); border-top: 1px solid var(--border); margin-top: 0.35rem; padding-top: 0.5rem; }
    .qbq-status-update { padding: 0.85rem 1rem; background: var(--bg-primary); border: 1px solid var(--border); border-radius: 12px; }
    .qbq-status-update-row { display: flex; gap: 0.5rem; margin-top: 0.4rem; }
    .qbq-select { padding: 0.5rem 0.65rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.875rem; background: var(--bg-card); color: var(--text-primary); flex: 1; }
    .qbq-modal-footer { padding: 1rem 1.5rem; border-top: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
    .qbq-modal-footer-left, .qbq-modal-footer-right { display: flex; gap: 0.6rem; flex-wrap: wrap; }
    .qbq-btn { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.55rem 1rem; border-radius: 8px; font-size: 0.8125rem; font-weight: 500; cursor: pointer; border: 1px solid var(--border); background: var(--bg-primary); color: var(--text-primary); text-decoration: none; }
    .qbq-btn:hover { background: var(--border); }
    .qbq-btn-primary { background: var(--accent); border-color: var(--accent); color: #fff; }
    .qbq-btn-primary:hover { background: var(--accent-hover); }
    .qbq-btn-danger { color: #dc2626; border-color: #fecaca; }
    .qbq-btn-danger:hover { background: #fee2e2; }
    @media (max-width: 900px) {
        .qbq-cards { display: flex; }
        .qbq-table-wrap { display: none; }
        .qbq-view-grid { grid-template-columns: 1fr; }
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const QUOTATIONS_API = @json(url('/api/quotation-builder/quotations'));
    const STATS_API = @json(route('api.quotation-builder.stats'));
    const CONTRACTS_URL = @json(url('/contracts'));

    let quotesData = [];
    let currentPage = 1;
    let totalPages = 1;
    let totalItems = 0;
    let searchTimeout = null;
    let viewingQuote = null;

    const statusLabels = { draft: 'Draft', sent: 'Sent', accepted: 'Accepted', rejected: 'Rejected', expired: 'Expired', paid: 'Paid' };
    const editableStatuses = ['draft', 'sent', 'accepted', 'rejected', 'expired'];

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str ?? '';
        return d.innerHTML;
    }

    function money(value) {
        return '₱' + Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    async function api(url, options = {}) {
        const res = await fetch(url, {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                ...(options.headers || {}),
            },
            ...options,
        });
        return res.json();
    }

    async function loadStats() {
        const data = await api(STATS_API);
        if (data.success) {
            document.getElementById('qbqStatTotal').textContent = data.data.total_quotations;
            document.getElementById('qbqStatPending').textContent = data.data.pending;
            document.getElementById('qbqStatAccepted').textContent = data.data.accepted;
            document.getElementById('qbqStatValue').textContent = money(data.data.total_value);
        }
    }

    async function loadQuotes(page = 1) {
        currentPage = page;
        const search = document.getElementById('qbqSearch').value;
        const status = document.getElementById('qbqStatusFilter').value;
        const params = new URLSearchParams({ page, per_page: 10, month: 'all' });
        if (search) params.set('search', search);
        if (status !== 'all') params.set('status', status);

        const data = await api(`${QUOTATIONS_API}?${params}`);
        if (!data.success) return;

        quotesData = data.data;
        totalPages = data.pagination.last_page;
        totalItems = data.pagination.total;
        renderTable();
        renderCards();
        renderPagination();
    }

    function typeBadge(q) {
        return q.quote_type === 'storage'
            ? '<span class="qbq-badge type-storage">Storage</span>'
            : '<span class="qbq-badge type-standard">Standard</span>';
    }

    function renderTable() {
        const tbody = document.getElementById('qbqTableBody');
        if (!quotesData.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="qbq-empty-cell">No saved quotes found.</td></tr>';
            return;
        }
        tbody.innerHTML = quotesData.map(q => `
            <tr onclick="window.qbSavedQuotes.openView(${q.id})">
                <td><strong>${escapeHtml(q.quotation_number)}</strong></td>
                <td>${escapeHtml(q.client)}</td>
                <td>${typeBadge(q)}</td>
                <td>${escapeHtml(q.date)}</td>
                <td>${money(q.amount)}</td>
                <td><span class="qbq-badge ${q.status}">${statusLabels[q.status] || q.status}</span></td>
                <td onclick="event.stopPropagation()">
                    <div class="qbq-table-actions">
                        <button class="qbq-icon-btn" title="View" onclick="window.qbSavedQuotes.openView(${q.id})">&#128065;</button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    function renderCards() {
        const container = document.getElementById('qbqCards');
        if (!quotesData.length) {
            container.innerHTML = '<div class="qbq-empty-cell">No saved quotes found.</div>';
            return;
        }
        container.innerHTML = quotesData.map(q => `
            <div class="qbq-card" onclick="window.qbSavedQuotes.openView(${q.id})">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:0.5rem;margin-bottom:0.5rem;">
                    <strong>${escapeHtml(q.quotation_number)}</strong>
                    <span class="qbq-badge ${q.status}">${statusLabels[q.status] || q.status}</span>
                </div>
                <div style="font-size:0.8125rem;color:var(--text-secondary);">${escapeHtml(q.client)} &middot; ${typeBadge(q)}</div>
                <div style="margin-top:0.5rem;font-weight:600;">${money(q.amount)}</div>
            </div>
        `).join('');
    }

    function renderPagination() {
        const info = document.getElementById('qbqPaginationInfo');
        const prevBtn = document.getElementById('qbqPrevBtn');
        const nextBtn = document.getElementById('qbqNextBtn');
        const perPage = 10;
        const start = totalItems ? (currentPage - 1) * perPage + 1 : 0;
        const end = Math.min(currentPage * perPage, totalItems);
        info.textContent = totalItems ? `Showing ${start} to ${end} of ${totalItems} quotes` : 'Showing 0 results';
        prevBtn.disabled = currentPage <= 1;
        nextBtn.disabled = currentPage >= totalPages;
    }

    async function openView(id) {
        const data = await api(`${QUOTATIONS_API}/${id}`);
        if (!data.success) return alert(data.message || 'Could not load quote.');
        const q = data.data;
        viewingQuote = q;

        document.getElementById('qbqViewNumber').textContent = q.quotation_number;
        document.getElementById('qbqViewStatus').innerHTML = `<span class="qbq-badge ${q.status}">${statusLabels[q.status] || q.status}</span>`;
        document.getElementById('qbqViewClient').textContent = q.client || '-';
        document.getElementById('qbqViewType').innerHTML = typeBadge(q);
        document.getElementById('qbqViewDate').textContent = q.quotation_date || '-';
        document.getElementById('qbqViewValidUntil').textContent = q.valid_until || '-';
        document.getElementById('qbqViewItemsBody').innerHTML = (q.items || []).map(item => `
            <tr>
                <td>${escapeHtml(item.item_name)}${item.description ? `<div style="font-size:0.75rem;color:var(--text-muted);">${escapeHtml(item.description)}</div>` : ''}</td>
                <td>${item.quantity}</td>
                <td>${money(item.unit_price)}</td>
                <td>${money(item.total)}</td>
            </tr>
        `).join('') || '<tr><td colspan="4" class="qbq-empty-cell">No line items.</td></tr>';
        document.getElementById('qbqViewSubtotal').textContent = money(q.subtotal);
        document.getElementById('qbqViewTax').textContent = money(q.tax_amount);
        document.getElementById('qbqViewDiscount').textContent = money(q.discount_amount);
        document.getElementById('qbqViewTotal').textContent = money(q.total);

        const statusSelect = document.getElementById('qbqStatusSelect');
        const canEditStatus = !['paid', 'rejected'].includes(q.status);
        statusSelect.innerHTML = editableStatuses.map(s => `<option value="${s}" ${s === q.status ? 'selected' : ''}>${statusLabels[s]}</option>`).join('');
        statusSelect.disabled = !canEditStatus;
        document.getElementById('qbqUpdateStatusBtn').disabled = !canEditStatus;

        document.getElementById('qbqDeleteBtn').style.display = ['paid', 'accepted', 'rejected'].includes(q.status) ? 'none' : 'inline-flex';

        const isStorage = q.quote_type === 'storage';
        document.getElementById('qbqDownloadAgreementBtn').style.display = isStorage ? 'inline-flex' : 'none';
        document.getElementById('qbqCreateContractBtn').style.display = isStorage && !q.contract_id ? 'inline-flex' : 'none';
        const viewContractBtn = document.getElementById('qbqViewContractBtn');
        if (isStorage && q.contract_id) {
            viewContractBtn.style.display = 'inline-flex';
            viewContractBtn.href = `${CONTRACTS_URL}?open=${q.contract_id}`;
        } else {
            viewContractBtn.style.display = 'none';
        }

        document.getElementById('qbqViewModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeView() {
        document.getElementById('qbqViewModal').style.display = 'none';
        document.body.style.overflow = '';
        viewingQuote = null;
    }

    function downloadFile(url, filename) {
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.rel = 'noopener noreferrer';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    document.getElementById('qbqDownloadPdfBtn').addEventListener('click', () => {
        if (!viewingQuote) return;
        downloadFile(`${QUOTATIONS_API}/${viewingQuote.id}/pdf`, `${viewingQuote.quotation_number}.pdf`);
    });

    document.getElementById('qbqDownloadAgreementBtn').addEventListener('click', () => {
        if (!viewingQuote) return;
        downloadFile(`${QUOTATIONS_API}/${viewingQuote.id}/contract-pdf`, `${viewingQuote.quotation_number}-agreement.pdf`);
    });

    document.getElementById('qbqSendEmailBtn').addEventListener('click', async () => {
        if (!viewingQuote || !confirm('Email this quote to the client?')) return;
        const data = await api(`${QUOTATIONS_API}/${viewingQuote.id}/send-email`, { method: 'POST' });
        alert(data.message || (data.success ? 'Quote sent.' : 'Failed to send quote.'));
        if (data.success) {
            closeView();
            loadQuotes(currentPage);
            loadStats();
        }
    });

    document.getElementById('qbqCreateContractBtn').addEventListener('click', async () => {
        if (!viewingQuote || !confirm('Create a signable contract from this quote?')) return;
        const data = await api(`${QUOTATIONS_API}/${viewingQuote.id}/create-contract`, { method: 'POST' });
        if (data.success) {
            window.location.href = `${CONTRACTS_URL}?open=${data.data.id}`;
        } else {
            alert(data.message || 'Failed to create contract.');
        }
    });

    document.getElementById('qbqUpdateStatusBtn').addEventListener('click', async () => {
        if (!viewingQuote) return;
        const status = document.getElementById('qbqStatusSelect').value;
        const data = await api(`${QUOTATIONS_API}/${viewingQuote.id}/status`, {
            method: 'PATCH',
            body: JSON.stringify({ status }),
        });
        alert(data.message || (data.success ? 'Status updated.' : 'Failed to update status.'));
        if (data.success) {
            closeView();
            loadQuotes(currentPage);
            loadStats();
        }
    });

    document.getElementById('qbqDeleteBtn').addEventListener('click', async () => {
        if (!viewingQuote || !confirm('Delete this quote permanently?')) return;
        const data = await api(`${QUOTATIONS_API}/${viewingQuote.id}`, { method: 'DELETE' });
        if (data.success) {
            closeView();
            loadQuotes(currentPage);
            loadStats();
        } else {
            alert(data.message || 'Failed to delete quote.');
        }
    });

    document.getElementById('qbqCloseViewBtn').addEventListener('click', closeView);
    document.getElementById('qbqCloseViewBtnTop').addEventListener('click', closeView);
    document.getElementById('qbqViewModal').addEventListener('click', (e) => { if (e.target.id === 'qbqViewModal') closeView(); });

    document.getElementById('qbqSearch').addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => loadQuotes(1), 300);
    });
    document.getElementById('qbqStatusFilter').addEventListener('change', () => loadQuotes(1));
    document.getElementById('qbqPrevBtn').addEventListener('click', () => { if (currentPage > 1) loadQuotes(currentPage - 1); });
    document.getElementById('qbqNextBtn').addEventListener('click', () => { if (currentPage < totalPages) loadQuotes(currentPage + 1); });

    window.qbSavedQuotes = { openView };

    loadStats();
    loadQuotes();

    const params = new URLSearchParams(window.location.search);
    const openId = params.get('open');
    if (params.get('tab') === 'quotes' && openId) {
        openView(parseInt(openId, 10));
    }
})();
</script>
@endpush
