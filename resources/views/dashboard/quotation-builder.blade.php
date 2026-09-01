@extends('layouts.app')

@section('title', 'Quotation Builder')

@section('content')
    <div class="ld-page">
        <div class="ld-top">
            <div class="ld-top-main">
                <h1 class="ld-title">Quotation Builder</h1>
                <p class="ld-subtitle">Select a lead to build a storage quote with unit pricing, fee schedule, and contract PDF.</p>
            </div>
            <div class="ld-top-actions">
                <a href="{{ route('leads') }}" class="btn btn-secondary btn-sm">View all leads</a>
            </div>
        </div>

        <div class="leads-toolbar-stack">
            <input type="search" id="leadSearch" class="leads-search" placeholder="Search leads by name, email, phone, or label…">
            <div class="leads-toolbar-row">
                <div class="leads-label-filter" id="qbLabelFilter">
                    <div id="qbLabelFilterChips" class="lead-label-filter-chips"></div>
                    <select id="qbLabelFilterSelect" aria-label="Filter by labels">
                        <option value="">Filter labels…</option>
                    </select>
                </div>
                <select id="qbAssigneeFilter" class="leads-assignee-filter" aria-label="Filter by assignee">
                    <option value="">All assignees</option>
                    <option value="__none__">Unassigned</option>
                </select>
            </div>
        </div>

        <div class="leads-card">
            <div class="qb-table-wrap">
                <table class="leads-table" id="leadsTable">
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
                            <td colspan="7" class="empty-state">
                                <div class="ld-loading">
                                    <span class="ld-spinner" aria-hidden="true"></span>
                                    Loading leads…
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="qb-mobile-cards" id="leadsCards"></div>

            <div class="leads-pagination">
                <span id="paginationInfo">Loading…</span>
                <div>
                    <button type="button" class="btn btn-secondary btn-sm" id="prevBtn" disabled>Previous</button>
                    <button type="button" class="btn btn-secondary btn-sm" id="nextBtn" disabled>Next</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    @include('partials.leads-page-base-styles')
<style>
    .ld-page .leads-table tbody tr { cursor: default; }
    .lead-email { color: var(--text-secondary); word-break: break-word; font-size: 0.75rem; }
    .lead-facility {
        display: inline-block;
        max-width: 180px;
        font-size: 0.6875rem;
        color: var(--text-secondary);
        word-break: break-word;
    }
    .lead-facility.missing { color: var(--text-muted); font-style: italic; }
    .qb-label-chip-list { display: flex; flex-wrap: wrap; gap: 0.25rem; }
    .qb-label-empty,
    .qb-assignee-empty { color: var(--text-muted); font-size: 0.75rem; }
    .qb-table-actions { display: flex; gap: 0.35rem; justify-content: flex-end; }
    .qb-tooltip {
        position: relative;
        display: inline-flex;
        vertical-align: middle;
    }
    .qb-tooltip-note {
        position: absolute;
        right: 0;
        bottom: calc(100% + 0.35rem);
        z-index: 20;
        width: max-content;
        max-width: 220px;
        padding: 0.35rem 0.55rem;
        border-radius: 6px;
        background: #1e293b;
        color: #f8fafc;
        font-size: 0.6875rem;
        font-weight: 500;
        line-height: 1.35;
        text-align: left;
        box-shadow: 0 6px 16px rgba(15, 23, 42, 0.18);
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transition: opacity 0.15s ease, visibility 0.15s ease;
    }
    .qb-tooltip-note::after {
        content: '';
        position: absolute;
        top: 100%;
        right: 0.75rem;
        border: 5px solid transparent;
        border-top-color: #1e293b;
    }
    .qb-tooltip:hover .qb-tooltip-note,
    .qb-tooltip:focus-within .qb-tooltip-note {
        opacity: 1;
        visibility: visible;
    }
    .ld-page .btn-primary.qb-needs-facility {
        opacity: 0.85;
        background: color-mix(in srgb, var(--accent) 82%, var(--bg-primary));
    }
    .ld-page .btn-primary.qb-needs-facility:hover {
        background: var(--accent-hover);
        opacity: 1;
    }
    .qb-mobile-cards { display: none; flex-direction: column; gap: 0.55rem; padding: 0.65rem; }
    .qb-mobile-card {
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 0.65rem 0.75rem;
        background: var(--bg-primary);
    }
    .qb-mobile-card-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.5rem;
        margin-bottom: 0.55rem;
    }
    .qb-mobile-card-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.45rem 0.65rem;
        margin-bottom: 0.55rem;
        font-size: 0.75rem;
    }
    .qb-mobile-card-label {
        display: block;
        font-size: 0.625rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--text-muted);
        margin-bottom: 0.1rem;
    }
    .qb-mobile-card-foot { display: flex; justify-content: flex-end; }
    @media (max-width: 900px) {
        .qb-table-wrap { display: none; }
        .qb-mobile-cards { display: flex; }
        .qb-mobile-card-grid { grid-template-columns: 1fr; }
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
            return `<span class="lead-label-chip" style="background:${escapeHtml(label.color || '#4338ca')};color:${chipText(label.color)}">${escapeHtml(label.name)}</span>`;
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
            return `<span class="qb-tooltip" tabindex="0" aria-label="${escapeHtml(note)}"><a href="${escapeHtml(editUrl)}" class="btn btn-primary btn-sm qb-needs-facility">Build Quote</a><span class="qb-tooltip-note" role="tooltip">${escapeHtml(note)}</span></span>`;
        }
        return `<a href="${escapeHtml(lead.quote_url)}" class="btn btn-primary btn-sm">Build Quote</a>`;
    }

    function hasActiveFilters(search) {
        return Boolean(search || filters.labelIds.length || filters.assignedTo);
    }

    function loadingMarkup(message) {
        return `
            <tr>
                <td colspan="7" class="empty-state">
                    <div class="ld-loading">
                        <span class="ld-spinner" aria-hidden="true"></span>
                        ${escapeHtml(message)}
                    </div>
                </td>
            </tr>
        `;
    }

    function emptyMarkup(message) {
        return `<tr><td colspan="7" class="empty-state">${escapeHtml(message)}</td></tr>`;
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
                <span class="lead-label-chip" style="background:${escapeHtml(label.color || '#4338ca')};color:${chipText(label.color)}">
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
                    <td><div class="qb-table-actions">${quoteButton(lead)}</div></td>
                </tr>
            `;
        }).join('');

        cardsEl.innerHTML = leads.map(function (lead) {
            return `
                <article class="qb-mobile-card">
                    <div class="qb-mobile-card-head">
                        <span class="lead-name">${escapeHtml(lead.name)}</span>
                        ${statusBadge(lead.status)}
                    </div>
                    <div class="qb-mobile-card-grid">
                        <div>
                            <span class="qb-mobile-card-label">Email</span>
                            ${escapeHtml(lead.email || '—')}
                        </div>
                        <div>
                            <span class="qb-mobile-card-label">Assigned</span>
                            ${lead.assignee_name ? escapeHtml(lead.assignee_name) : 'Unassigned'}
                        </div>
                        <div>
                            <span class="qb-mobile-card-label">Labels</span>
                            ${labelsCell(lead.labels)}
                        </div>
                        <div>
                            <span class="qb-mobile-card-label">Facility</span>
                            ${lead.facility_name ? escapeHtml(lead.facility_name) : 'Not set'}
                        </div>
                    </div>
                    <div class="qb-mobile-card-foot">${quoteButton(lead)}</div>
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
                cardsEl.innerHTML = `<div class="empty-state">${escapeHtml(emptyMessage)}</div>`;
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
            cardsEl.innerHTML = '<div class="empty-state" style="color:#dc2626;">Could not load leads.</div>';
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
