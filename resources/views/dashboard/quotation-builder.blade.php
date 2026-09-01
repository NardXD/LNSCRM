@extends('layouts.app')

@section('title', 'Quotation Builder')

@section('content')
    <div class="page-header">
        <h1 class="page-title">Quotation Builder</h1>
        <p class="page-subtitle">Select a lead to build a storage quote with unit pricing, fee schedule, and contract PDF</p>
    </div>

    <div class="quotation-container">
        <div class="quotation-header">
            <div class="header-left">
                <div class="header-filters-stack">
                    <div class="search-box">
                        <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/>
                            <path d="m21 21-4.35-4.35"/>
                        </svg>
                        <input type="search" class="search-input" placeholder="Search leads by name, email, phone, or label…" id="leadSearch">
                    </div>
                    <div class="header-filter-row">
                        <div class="qb-label-filter" id="qbLabelFilter">
                            <div id="qbLabelFilterChips" class="qb-label-filter-chips"></div>
                            <select id="qbLabelFilterSelect" class="qb-label-filter-select" aria-label="Filter by labels">
                                <option value="">Filter labels…</option>
                            </select>
                        </div>
                        <select id="qbAssigneeFilter" class="qb-assignee-filter" aria-label="Filter by assignee">
                            <option value="">All assignees</option>
                            <option value="__none__">Unassigned</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="header-right">
                <a href="{{ route('leads') }}" class="btn-secondary">View all leads</a>
            </div>
        </div>

        <div class="quotations-section">
            <div class="table-container">
                <table class="data-table" id="leadsTable">
                    <thead>
                        <tr>
                            <th>Lead</th>
                            <th>Email</th>
                            <th>Labels</th>
                            <th>Assigned</th>
                            <th>Status</th>
                            <th>Facility</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="leadsTableBody">
                        <tr>
                            <td colspan="7" class="empty-cell">
                                <div class="qb-loading">
                                    <span class="spinner" aria-hidden="true"></span>
                                    Loading leads…
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="quotations-cards" id="leadsCards"></div>

            <div class="table-pagination">
                <div class="pagination-info">
                    <span id="paginationInfo">Loading…</span>
                </div>
                <div class="pagination-controls">
                    <button type="button" class="pagination-btn" id="prevBtn" disabled>Previous</button>
                    <button type="button" class="pagination-btn" id="nextBtn" disabled>Next</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
    .quotation-container { display: flex; flex-direction: column; gap: 1.5rem; }
    .quotation-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.25rem;
    }
    .header-left, .header-right { display: flex; align-items: flex-start; gap: 0.75rem; flex-wrap: wrap; }
    .header-left { flex: 1; min-width: min(100%, 320px); }
    .header-filters-stack {
        display: flex;
        flex-direction: column;
        gap: 0.625rem;
        width: 100%;
        max-width: 640px;
    }
    .header-filter-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        gap: 0.625rem;
        align-items: stretch;
    }
    .search-box { position: relative; width: 100%; }
    .search-icon {
        position: absolute;
        left: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
        width: 18px;
        height: 18px;
        pointer-events: none;
    }
    .search-input {
        width: 100%;
        padding: 0.625rem 0.75rem 0.625rem 2.5rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        background: var(--bg-card);
        color: var(--text-primary);
        transition: border-color 0.15s, box-shadow 0.15s;
    }
    .search-input:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(95, 97, 230, 0.1);
    }
    .qb-label-filter {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.35rem;
        min-width: 0;
        padding: 0.35rem 0.5rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: var(--bg-card);
    }
    .qb-label-filter-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 0.25rem;
    }
    .qb-label-filter-select,
    .qb-assignee-filter {
        width: 100%;
        min-width: 0;
        padding: 0.625rem 0.75rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        background: var(--bg-card);
        color: var(--text-primary);
    }
    .qb-label-filter-select {
        border: none;
        background: transparent;
        flex: 1;
        min-width: 0;
        padding: 0.2rem 0.15rem;
        font-size: 0.8125rem;
    }
    .qb-label-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.15rem 0.45rem;
        border-radius: 999px;
        font-size: 0.6875rem;
        font-weight: 700;
        white-space: nowrap;
    }
    .qb-label-chip button {
        background: none;
        border: none;
        color: inherit;
        cursor: pointer;
        font-size: 0.9rem;
        line-height: 1;
        padding: 0;
        opacity: 0.85;
    }
    .qb-label-chip-list {
        display: flex;
        flex-wrap: wrap;
        gap: 0.25rem;
    }
    .qb-label-empty,
    .qb-assignee-empty {
        color: var(--text-muted);
        font-size: 0.8125rem;
        font-style: italic;
    }
    .btn-primary, .btn-secondary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.625rem 1.25rem;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.15s;
        border: none;
        text-decoration: none;
        white-space: nowrap;
    }
    .btn-primary { background: var(--accent); color: white; }
    .btn-primary:hover { background: var(--accent-hover); color: white; }
    .btn-primary:disabled,
    .btn-primary[disabled] {
        opacity: 0.5;
        cursor: not-allowed;
    }
    .qb-tooltip {
        position: relative;
        display: inline-flex;
        vertical-align: middle;
    }
    .qb-tooltip-note {
        position: absolute;
        right: 0;
        bottom: calc(100% + 0.5rem);
        z-index: 20;
        width: max-content;
        max-width: 220px;
        padding: 0.45rem 0.65rem;
        border-radius: 8px;
        background: #1e293b;
        color: #f8fafc;
        font-size: 0.75rem;
        font-weight: 500;
        line-height: 1.35;
        text-align: left;
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.18);
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transition: opacity 0.15s ease, visibility 0.15s ease;
    }
    .qb-tooltip-note::after {
        content: '';
        position: absolute;
        top: 100%;
        right: 1rem;
        border: 6px solid transparent;
        border-top-color: #1e293b;
    }
    .qb-tooltip:hover .qb-tooltip-note,
    .qb-tooltip:focus-within .qb-tooltip-note {
        opacity: 1;
        visibility: visible;
    }
    .btn-primary.btn-sm.qb-needs-facility {
        opacity: 0.85;
        background: color-mix(in srgb, var(--accent) 82%, var(--bg-primary));
    }
    .btn-primary.btn-sm.qb-needs-facility:hover {
        background: var(--accent-hover);
        opacity: 1;
    }
    .btn-secondary {
        background: var(--bg-primary);
        color: var(--text-primary);
        border: 1px solid var(--border);
    }
    .btn-secondary:hover { background: var(--border); color: var(--text-primary); }
    .btn-primary.btn-sm, .btn-secondary.btn-sm {
        padding: 0.45rem 0.85rem;
        font-size: 0.8125rem;
    }
    .quotations-section {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.5rem;
    }
    .table-container {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        margin-bottom: 1.5rem;
    }
    .data-table { width: 100%; border-collapse: collapse; }
    .data-table thead { background: var(--bg-primary); }
    .data-table th {
        padding: 0.875rem 1rem;
        text-align: left;
        font-size: 0.8125rem;
        font-weight: 600;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 2px solid var(--border);
        white-space: nowrap;
    }
    .data-table td {
        padding: 1rem;
        font-size: 0.875rem;
        color: var(--text-primary);
        border-bottom: 1px solid var(--border);
        vertical-align: middle;
    }
    .data-table tbody tr:hover { background: var(--bg-primary); }
    .data-table tbody tr:last-child td { border-bottom: none; }
    .empty-cell {
        text-align: center;
        color: var(--text-muted);
        padding: 2.5rem 1rem !important;
    }
    .qb-loading {
        display: inline-flex;
        align-items: center;
        gap: 0.75rem;
        color: var(--text-secondary);
        font-size: 0.875rem;
    }
    .lead-name { font-weight: 600; color: var(--text-primary); }
    .lead-email { color: var(--text-secondary); word-break: break-word; }
    .lead-facility {
        display: inline-block;
        max-width: 180px;
        font-size: 0.8125rem;
        color: var(--text-secondary);
        word-break: break-word;
    }
    .lead-facility.missing { color: var(--text-muted); font-style: italic; }
    .lead-badge {
        display: inline-block;
        font-size: 0.6875rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        padding: 0.2rem 0.55rem;
        border-radius: 999px;
        background: #eef2ff;
        color: #4338ca;
    }
    .lead-badge.new { background: #eef2ff; color: #4338ca; }
    .lead-badge.contacted { background: #e0f2fe; color: #0369a1; }
    .lead-badge.qualified { background: #dcfce7; color: #166534; }
    .lead-badge.converted { background: #d1fae5; color: #065f46; }
    .lead-badge.lost { background: #fee2e2; color: #991b1b; }
    .lead-badge.snoozed { background: #fef3c7; color: #92400e; }
    .lead-badge.archived { background: #e2e8f0; color: #475569; }
    .table-actions { display: flex; gap: 0.5rem; justify-content: flex-end; }
    .quotations-cards { display: none; flex-direction: column; gap: 1rem; margin-bottom: 1.5rem; }
    .quotation-card {
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.25rem;
        transition: border-color 0.15s;
    }
    .quotation-card:hover { border-color: color-mix(in srgb, var(--accent) 35%, var(--border)); }
    .card-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--border);
    }
    .card-title { font-weight: 600; color: var(--text-primary); font-size: 0.9375rem; }
    .card-details {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }
    .card-detail { display: flex; flex-direction: column; gap: 0.25rem; min-width: 0; }
    .card-label {
        font-size: 0.6875rem;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-weight: 600;
    }
    .card-value { font-size: 0.875rem; color: var(--text-primary); font-weight: 500; word-break: break-word; }
    .card-footer { display: flex; justify-content: flex-end; }
    .table-pagination {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
        padding-top: 1.5rem;
        border-top: 1px solid var(--border);
    }
    .pagination-info { font-size: 0.875rem; color: var(--text-secondary); }
    .pagination-controls { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
    .pagination-btn {
        padding: 0.625rem 1rem;
        border: 1px solid var(--border);
        background: var(--bg-card);
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
        cursor: pointer;
        transition: all 0.15s;
    }
    .pagination-btn:hover:not(:disabled) {
        background: var(--bg-primary);
        border-color: var(--accent);
        color: var(--accent);
    }
    .pagination-btn:disabled { opacity: 0.5; cursor: not-allowed; }
    .spinner {
        border: 3px solid var(--border);
        border-top: 3px solid var(--accent);
        border-radius: 50%;
        width: 22px;
        height: 22px;
        animation: qb-spin 0.8s linear infinite;
        flex-shrink: 0;
    }
    @keyframes qb-spin { to { transform: rotate(360deg); } }

    @media (max-width: 900px) {
        .quotations-cards { display: flex; }
        .table-container { display: none; }
        .card-details { grid-template-columns: 1fr; }
    }
    @media (max-width: 640px) {
        .quotation-header { flex-direction: column; align-items: stretch; }
        .header-left, .header-right { width: 100%; }
        .header-filters-stack { max-width: none; }
        .header-filter-row { grid-template-columns: 1fr; }
        .header-right .btn-secondary { width: 100%; justify-content: center; }
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const clientsUrl = @json(route('api.quotation-builder.clients'));
    const filterOptionsUrl = @json(route('api.quotation-builder.client-filters'));
    const leadsUrl = @json(route('leads'));
    let currentPage = 1;
    let searchTimer = null;
    const filters = {
        labelIds: [],
        assignedTo: '',
        labels: [],
        assignees: [],
    };

    const tbody = document.getElementById('leadsTableBody');
    const cardsEl = document.getElementById('leadsCards');
    const paginationInfo = document.getElementById('paginationInfo');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const searchInput = document.getElementById('leadSearch');
    const labelFilterSelect = document.getElementById('qbLabelFilterSelect');
    const labelFilterChips = document.getElementById('qbLabelFilterChips');
    const assigneeFilter = document.getElementById('qbAssigneeFilter');

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function chipText(hex) {
        if (!hex || !/^#[0-9A-Fa-f]{6}$/.test(hex)) {
            return '#fff';
        }
        const r = parseInt(hex.slice(1, 3), 16);
        const g = parseInt(hex.slice(3, 5), 16);
        const b = parseInt(hex.slice(5, 7), 16);
        const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
        return luminance > 0.6 ? '#111827' : '#fff';
    }

    function statusBadge(status) {
        const key = String(status || 'new').toLowerCase().replace(/[^a-z]/g, '');
        const label = String(status || 'new').replace(/_/g, ' ');
        return `<span class="lead-badge ${escapeHtml(key)}">${escapeHtml(label)}</span>`;
    }

    function facilityCell(facilityName) {
        if (!facilityName) {
            return '<span class="lead-facility missing">Not set</span>';
        }
        return `<span class="lead-facility">${escapeHtml(facilityName)}</span>`;
    }

    function labelsCell(labels) {
        const items = Array.isArray(labels) ? labels.filter(function (label) { return label && label.name; }) : [];
        if (!items.length) {
            return '<span class="qb-label-empty">—</span>';
        }
        return `<div class="qb-label-chip-list">${items.map(function (label) {
            return `<span class="qb-label-chip" style="background:${escapeHtml(label.color || '#4338ca')};color:${chipText(label.color)}">${escapeHtml(label.name)}</span>`;
        }).join('')}</div>`;
    }

    function assigneeCell(name) {
        if (!name) {
            return '<span class="qb-assignee-empty">Unassigned</span>';
        }
        return escapeHtml(name);
    }

    function quoteButton(lead) {
        if (!lead.facility_name) {
            const note = 'Set a facility on the lead first. Click to open the lead.';
            const editUrl = `${leadsUrl}?lead=${encodeURIComponent(lead.id)}&tab=source`;
            return `<span class="qb-tooltip" tabindex="0" aria-label="${escapeHtml(note)}"><a href="${escapeHtml(editUrl)}" class="btn-primary btn-sm qb-needs-facility">Build Quote</a><span class="qb-tooltip-note" role="tooltip">${escapeHtml(note)}</span></span>`;
        }
        return `<a href="${escapeHtml(lead.quote_url)}" class="btn-primary btn-sm">Build Quote</a>`;
    }

    function hasActiveFilters(search) {
        return Boolean(search || filters.labelIds.length || filters.assignedTo);
    }

    function loadingMarkup(message) {
        return `
            <tr>
                <td colspan="7" class="empty-cell">
                    <div class="qb-loading">
                        <span class="spinner" aria-hidden="true"></span>
                        ${escapeHtml(message)}
                    </div>
                </td>
            </tr>
        `;
    }

    function emptyMarkup(message) {
        return `<tr><td colspan="7" class="empty-cell">${escapeHtml(message)}</td></tr>`;
    }

    function selectedFilterLabels() {
        return filters.labels.filter(function (label) {
            return filters.labelIds.includes(String(label.id));
        });
    }

    function renderLabelFilter() {
        const selected = selectedFilterLabels();
        labelFilterChips.innerHTML = selected.map(function (label) {
            return `
                <span class="qb-label-chip" style="background:${escapeHtml(label.color || '#4338ca')};color:${chipText(label.color)}">
                    ${escapeHtml(label.name)}
                    <button type="button" data-unfilter-label="${label.id}" title="Remove filter">&times;</button>
                </span>
            `;
        }).join('');

        const available = filters.labels.filter(function (label) {
            return !filters.labelIds.includes(String(label.id));
        });
        labelFilterSelect.innerHTML = `<option value="">${selected.length ? 'Add label…' : 'Filter labels…'}</option>` +
            available.map(function (label) {
                return `<option value="${label.id}">${escapeHtml(label.name)}</option>`;
            }).join('');
        labelFilterSelect.hidden = available.length === 0 && selected.length > 0 && filters.labels.length > 0;
    }

    function renderAssigneeFilter() {
        const current = filters.assignedTo || '';
        const users = filters.assignees.slice();
        if (current && current !== '__none__' && !users.some(function (user) { return String(user.id) === String(current); })) {
            users.push({ id: current, name: 'Assignee #' + current });
        }
        assigneeFilter.innerHTML = `<option value="">All assignees</option><option value="__none__">Unassigned</option>` +
            users.map(function (user) {
                return `<option value="${user.id}">${escapeHtml(user.name)}</option>`;
            }).join('');
        assigneeFilter.value = current;
    }

    async function loadFilterOptions() {
        try {
            const response = await fetch(filterOptionsUrl, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const result = await response.json();
            if (response.ok && result.success) {
                filters.labels = Array.isArray(result.labels) ? result.labels : [];
                filters.assignees = Array.isArray(result.assignees) ? result.assignees : [];
            }
        } catch (error) {
            console.error(error);
        }
        renderLabelFilter();
        renderAssigneeFilter();
    }

    function renderRows(leads) {
        tbody.innerHTML = leads.map(function (lead) {
            return `
                <tr>
                    <td><span class="lead-name">${escapeHtml(lead.name)}</span></td>
                    <td><span class="lead-email">${escapeHtml(lead.email || '—')}</span></td>
                    <td>${labelsCell(lead.labels)}</td>
                    <td>${assigneeCell(lead.assignee_name)}</td>
                    <td>${statusBadge(lead.status)}</td>
                    <td>${facilityCell(lead.facility_name)}</td>
                    <td><div class="table-actions">${quoteButton(lead)}</div></td>
                </tr>
            `;
        }).join('');

        cardsEl.innerHTML = leads.map(function (lead) {
            return `
                <article class="quotation-card">
                    <div class="card-header">
                        <div class="card-title">${escapeHtml(lead.name)}</div>
                        ${statusBadge(lead.status)}
                    </div>
                    <div class="card-details">
                        <div class="card-detail">
                            <span class="card-label">Email</span>
                            <span class="card-value">${escapeHtml(lead.email || '—')}</span>
                        </div>
                        <div class="card-detail">
                            <span class="card-label">Assigned</span>
                            <span class="card-value">${lead.assignee_name ? escapeHtml(lead.assignee_name) : 'Unassigned'}</span>
                        </div>
                        <div class="card-detail">
                            <span class="card-label">Labels</span>
                            <span class="card-value">${labelsCell(lead.labels)}</span>
                        </div>
                        <div class="card-detail">
                            <span class="card-label">Facility</span>
                            <span class="card-value">${lead.facility_name ? escapeHtml(lead.facility_name) : 'Not set'}</span>
                        </div>
                    </div>
                    <div class="card-footer">${quoteButton(lead)}</div>
                </article>
            `;
        }).join('');
    }

    async function loadLeads(page, search) {
        currentPage = page;
        tbody.innerHTML = loadingMarkup('Loading leads…');
        cardsEl.innerHTML = '';

        const params = new URLSearchParams({ page: String(page), per_page: '25' });
        if (search) {
            params.set('search', search);
        }
        if (filters.assignedTo) {
            params.set('assigned_to', filters.assignedTo);
        }
        filters.labelIds.forEach(function (id) {
            params.append('label_ids[]', id);
        });

        try {
            const response = await fetch(`${clientsUrl}?${params.toString()}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const result = await response.json();
            const emptyMessage = hasActiveFilters(search) ? 'No leads match your filters.' : 'No leads found.';

            if (!result.success || !Array.isArray(result.data) || result.data.length === 0) {
                tbody.innerHTML = emptyMarkup(emptyMessage);
                cardsEl.innerHTML = `<div class="empty-cell">${escapeHtml(emptyMessage)}</div>`;
                paginationInfo.textContent = 'No results';
                prevBtn.disabled = true;
                nextBtn.disabled = true;
                return;
            }

            renderRows(result.data);

            const pagination = result.pagination || {};
            const from = ((pagination.current_page - 1) * pagination.per_page) + 1;
            const to = Math.min(pagination.current_page * pagination.per_page, pagination.total);
            paginationInfo.textContent = `Showing ${from} to ${to} of ${pagination.total} leads`;
            prevBtn.disabled = pagination.current_page <= 1;
            nextBtn.disabled = pagination.current_page >= pagination.last_page;
        } catch (error) {
            console.error(error);
            tbody.innerHTML = emptyMarkup('Could not load leads. Please try again.');
            cardsEl.innerHTML = '<div class="empty-cell" style="color:#dc2626;">Could not load leads.</div>';
            paginationInfo.textContent = 'Error loading leads';
            prevBtn.disabled = true;
            nextBtn.disabled = true;
        }
    }

    function reloadLeads() {
        loadLeads(1, searchInput.value.trim());
    }

    prevBtn.addEventListener('click', function () {
        if (currentPage > 1) {
            loadLeads(currentPage - 1, searchInput.value.trim());
        }
    });

    nextBtn.addEventListener('click', function () {
        loadLeads(currentPage + 1, searchInput.value.trim());
    });

    searchInput.addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () {
            reloadLeads();
        }, 300);
    });

    labelFilterSelect.addEventListener('change', function () {
        const id = String(labelFilterSelect.value || '');
        if (id && !filters.labelIds.includes(id)) {
            filters.labelIds.push(id);
            renderLabelFilter();
            reloadLeads();
        }
        labelFilterSelect.value = '';
    });

    labelFilterChips.addEventListener('click', function (event) {
        const btn = event.target.closest('[data-unfilter-label]');
        if (!btn) {
            return;
        }
        filters.labelIds = filters.labelIds.filter(function (id) {
            return id !== String(btn.dataset.unfilterLabel);
        });
        renderLabelFilter();
        reloadLeads();
    });

    assigneeFilter.addEventListener('change', function () {
        filters.assignedTo = assigneeFilter.value || '';
        reloadLeads();
    });

    loadFilterOptions().finally(function () {
        loadLeads(1, '');
    });
})();
</script>
@endpush
