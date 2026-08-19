@extends('layouts.app')

@section('title', 'Leads')

@section('content')
    <div class="page-header leads-header">
        <div>
            <h1 class="page-title">Leads</h1>
            <p class="page-subtitle">Store a customer’s phones, emails, and social names so Phone, Inbox, Viber, WhatsApp, Facebook, and SMS share one Contact history.</p>
        </div>
        <button type="button" class="btn btn-primary" id="newLeadBtn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New Lead
        </button>
    </div>

    <div class="leads-toolbar">
        <input type="search" id="leadSearch" class="leads-search" placeholder="Search name, phone, email, or label…">
        <div class="leads-label-filter" id="leadLabelFilter">
            <div id="leadLabelFilterChips" class="lead-label-filter-chips"></div>
            <select id="leadLabelFilterSelect" aria-label="Filter by labels">
                <option value="">Filter labels…</option>
            </select>
        </div>
        <div class="leads-tabs" role="tablist">
            <button type="button" class="leads-tab active" data-status="all">All</button>
            <button type="button" class="leads-tab" data-status="new">New</button>
            <button type="button" class="leads-tab" data-status="contacted">Contacted</button>
            <button type="button" class="leads-tab" data-status="qualified">Qualified</button>
            <button type="button" class="leads-tab" data-status="converted">Converted</button>
            <button type="button" class="leads-tab" data-status="lost">Lost</button>
        </div>
    </div>

    <div class="leads-card">
        <div class="table-container">
            <table class="data-table leads-table">
                <thead>
                    <tr>
                        <th>Lead</th>
                        <th>Phones</th>
                        <th>Emails</th>
                        <th>Labels</th>
                        <th>Assigned</th>
                        <th>Status</th>
                        <th>Updated</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="leadsTableBody">
                    <tr><td colspan="8" class="empty-state">Loading leads…</td></tr>
                </tbody>
            </table>
        </div>
        <div class="leads-pagination">
            <span id="leadsPageInfo">Showing 0 of 0</span>
            <div>
                <button type="button" class="btn btn-secondary btn-sm" id="leadsPrev" disabled>Previous</button>
                <button type="button" class="btn btn-secondary btn-sm" id="leadsNext" disabled>Next</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="leadModal">
        <div class="modal-content leads-modal">
            <div class="modal-header">
                <h3 id="leadModalTitle">New Lead</h3>
                <button type="button" class="modal-close-btn" id="closeLeadModal">&times;</button>
            </div>
            <div class="leads-modal-grid">
                <form id="leadForm" class="leads-form">
                    <input type="hidden" id="leadId">
                    <div class="form-group">
                        <label for="leadName">Name *</label>
                        <input type="text" id="leadName" required maxlength="255" placeholder="Customer name">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="leadCompany">Company</label>
                            <input type="text" id="leadCompany" maxlength="255" placeholder="Optional">
                        </div>
                        <div class="form-group">
                            <label for="leadStatus">Status</label>
                            <select id="leadStatus">
                                <option value="new">New</option>
                                <option value="contacted">Contacted</option>
                                <option value="qualified">Qualified</option>
                                <option value="converted">Converted</option>
                                <option value="lost">Lost</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="leadAssignedTo">Assigned</label>
                            <select id="leadAssignedTo">
                                <option value="">Unassigned</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="leadSource">Source</label>
                            <input type="text" id="leadSource" maxlength="255" placeholder="Inbound call, Facebook, referral…">
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="identity-label">
                            <label>Phone numbers</label>
                            <button type="button" class="link-btn" id="addPhoneBtn">+ Add phone</button>
                        </div>
                        <div id="phonesList" class="identity-list"></div>
                    </div>
                    <div class="form-group">
                        <div class="identity-label">
                            <label>Emails</label>
                            <button type="button" class="link-btn" id="addEmailBtn">+ Add email</button>
                        </div>
                        <div id="emailsList" class="identity-list"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="leadFacebook">Facebook name</label>
                            <input type="text" id="leadFacebook" maxlength="255" placeholder="Matches Messenger threads">
                        </div>
                        <div class="form-group">
                            <label for="leadInstagram">Instagram username</label>
                            <input type="text" id="leadInstagram" maxlength="255" placeholder="Matches Instagram DMs">
                        </div>
                    </div>
                    <div id="leadExtras" hidden>
                        <div class="form-group">
                            <label>Labels</label>
                            <div id="leadLabelsList" class="lead-label-list"></div>
                            <div class="lead-label-add">
                                <input type="text" id="leadLabelInput" list="leadLabelSuggestions" maxlength="50" placeholder="Add or create a label" autocomplete="off">
                                <datalist id="leadLabelSuggestions"></datalist>
                                <button type="button" class="btn btn-secondary btn-sm" id="addLeadLabelBtn">Add</button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Notes</label>
                            <div id="leadNotesList" class="lead-notes-list"></div>
                            <textarea id="leadNoteInput" rows="3" maxlength="5000" placeholder="Add a note for the next agent…"></textarea>
                            <button type="button" class="btn btn-secondary btn-sm" id="addLeadNoteBtn" style="margin-top:0.4rem">Add note</button>
                        </div>
                    </div>
                    <p id="leadExtrasHint" class="chp-empty">Save this lead to add notes and labels.</p>
                    <p class="form-error" id="leadFormError" hidden></p>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" id="deleteLeadBtn" hidden>Delete</button>
                        <span style="flex:1"></span>
                        <button type="button" class="btn btn-secondary" id="cancelLeadBtn">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="saveLeadBtn">Save lead</button>
                    </div>
                </form>
                <div class="leads-history" id="leadHistoryPane">
                    <button type="button" class="lead-activity-trigger" id="leadActivityTrigger" disabled>
                        <div class="lead-activity-trigger-head">
                            <h4>Activity</h4>
                            <span id="leadActivityCount">View all updates</span>
                        </div>
                        <div id="leadActivityPreview"><p class="chp-empty">Save this lead to start an activity history.</p></div>
                    </button>
                    <h4 class="leads-history-sub">Contact history</h4>
                    <p class="chp-empty" id="leadHistoryEmpty">Save this lead to load Phone, Inbox, Viber, WhatsApp, Facebook, and SMS history.</p>
                    <div id="leadHistoryBody" hidden></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="leadActivityModal">
        <div class="modal-content lead-activity-modal">
            <div class="modal-header">
                <h3 id="leadActivityModalTitle">Lead activity</h3>
                <button type="button" class="modal-close-btn" id="closeLeadActivityModal">&times;</button>
            </div>
            <div class="lead-activity-full" id="leadActivityFull"></div>
            <div class="leads-pagination lead-activity-pagination">
                <span id="leadActivityPageInfo">Showing 0 of 0</span>
                <div>
                    <button type="button" class="btn btn-secondary btn-sm" id="leadActivityPrev" disabled>Previous</button>
                    <button type="button" class="btn btn-secondary btn-sm" id="leadActivityNext" disabled>Next</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
.leads-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap; }
.leads-toolbar { display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; margin-bottom: 1rem; }
.leads-search { flex: 1; min-width: 220px; padding: 0.55rem 0.85rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.9rem; background: var(--bg-card); }
.leads-label-filter { display: flex; flex-wrap: wrap; align-items: center; gap: 0.35rem; min-width: 220px; padding: 0.3rem 0.45rem; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-card); }
.leads-label-filter select { border: none; background: transparent; font-size: 0.9rem; color: var(--text-primary); min-width: 140px; padding: 0.25rem 0.2rem; }
.lead-label-filter-chips { display: flex; flex-wrap: wrap; gap: 0.3rem; }
.leads-tabs { display: flex; gap: 0.25rem; flex-wrap: wrap; }
.leads-tab { border: 1px solid var(--border); background: var(--bg-card); color: var(--text-secondary); border-radius: 999px; padding: 0.35rem 0.75rem; font-size: 0.8rem; font-weight: 600; cursor: pointer; }
.leads-tab.active { background: var(--accent); border-color: var(--accent); color: #fff; }
.leads-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
.leads-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.leads-table th { text-align: left; padding: 0.7rem 1rem; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-secondary); border-bottom: 1px solid var(--border); background: var(--bg-primary); }
.leads-table td { padding: 0.75rem 1rem; border-bottom: 1px solid var(--border); vertical-align: top; }
.leads-table tbody tr { cursor: pointer; }
.leads-table tbody tr:hover { background: var(--bg-primary); }
.lead-name { font-weight: 600; }
.lead-company { font-size: 0.78rem; color: var(--text-secondary); }
.lead-meta { font-size: 0.8rem; color: var(--text-secondary); }
.lead-badge { display: inline-block; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; padding: 0.15rem 0.45rem; border-radius: 999px; background: #eef2ff; color: #4338ca; }
.lead-badge.contacted { background: #e0f2fe; color: #0369a1; }
.lead-badge.qualified { background: #dcfce7; color: #166534; }
.lead-badge.converted { background: #d1fae5; color: #065f46; }
.lead-badge.lost { background: #fee2e2; color: #991b1b; }
.lead-assign { max-width: 160px; font-size: 0.8rem; padding: 0.3rem 0.45rem; border: 1px solid var(--border); border-radius: 6px; background: var(--bg-card); color: var(--text-primary); }
.lead-assign:focus { outline: 2px solid var(--accent); outline-offset: 1px; }
.lead-label-list, .lead-notes-list { display: flex; flex-wrap: wrap; gap: 0.35rem; margin-bottom: 0.45rem; }
.lead-notes-list { flex-direction: column; flex-wrap: nowrap; }
.lead-label-chip { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.18rem 0.5rem; border-radius: 999px; font-size: 0.72rem; font-weight: 700; }
.lead-label-chip button { background: none; border: none; color: inherit; cursor: pointer; font-size: 0.9rem; line-height: 1; padding: 0; opacity: 0.8; }
.lead-label-add { display: flex; gap: 0.4rem; }
.lead-label-add input { flex: 1; }
.lead-note-item { padding: 0.7rem 0.8rem; background: var(--bg-primary); border-radius: 8px; border-left: 3px solid var(--accent); }
.lead-note-text { font-size: 0.875rem; line-height: 1.45; white-space: pre-wrap; }
.lead-note-meta { display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; margin-top: 0.45rem; padding-top: 0.4rem; border-top: 1px solid var(--border); font-size: 0.75rem; color: var(--text-muted); }
.lead-note-empty { font-size: 0.8rem; color: var(--text-secondary); }
.empty-state { text-align: center; color: var(--text-secondary); padding: 2rem !important; }
.leads-pagination { display: flex; justify-content: space-between; align-items: center; padding: 0.85rem 1rem; font-size: 0.8rem; color: var(--text-secondary); }
.modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 1000; align-items: center; justify-content: center; padding: 1rem; }
.modal-overlay.open { display: flex; }
#leadActivityModal { z-index: 1100; }
.leads-modal { background: var(--bg-card); border-radius: 12px; width: min(1080px, 96vw); max-height: 92vh; overflow: hidden; display: flex; flex-direction: column; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); }
.modal-close-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted); }
.leads-modal-grid { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(280px, 0.9fr); min-height: 0; overflow: hidden; }
.leads-form { padding: 1.15rem 1.25rem; overflow-y: auto; max-height: calc(92vh - 60px); }
.leads-history { border-left: 1px solid var(--border); padding: 1.15rem 1.1rem; overflow-y: auto; background: var(--bg-primary); max-height: calc(92vh - 60px); }
.leads-history h4 { margin: 0; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-secondary); }
.leads-history-sub { margin: 1.25rem 0 0.75rem !important; padding-top: 1rem; border-top: 1px solid var(--border); }
.lead-activity-trigger { display: block; width: 100%; text-align: left; border: 1px solid var(--border); background: var(--bg-card); border-radius: 10px; padding: 0.75rem 0.85rem; cursor: pointer; color: inherit; }
.lead-activity-trigger:hover:not(:disabled) { border-color: var(--accent); }
.lead-activity-trigger:disabled { cursor: default; opacity: 0.85; }
.lead-activity-trigger-head { display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; margin-bottom: 0.55rem; }
.lead-activity-trigger-head span { font-size: 0.75rem; font-weight: 700; color: var(--accent); white-space: nowrap; }
.lead-activity-modal { background: var(--bg-card); border-radius: 12px; width: min(560px, 96vw); max-height: 88vh; overflow: hidden; display: flex; flex-direction: column; }
.lead-activity-full { padding: 0.4rem 0.4rem 0.5rem; overflow-y: auto; min-height: 180px; }
.lead-activity-pagination { border-top: 1px solid var(--border); }
.lead-activity-item { display: block; width: 100%; text-align: left; padding: 0.7rem 0.85rem; border: 0; border-bottom: 1px solid var(--border); background: transparent; cursor: pointer; color: inherit; }
.lead-activity-item:hover { background: var(--bg-primary); }
.lead-activity-item.open { background: var(--bg-primary); }
.lead-activity-summary { font-size: 0.82rem; line-height: 1.4; color: var(--text-primary); }
.lead-activity-meta { margin-top: 0.15rem; font-size: 0.72rem; color: var(--text-muted); }
.lead-activity-details { margin-top: 0.45rem; padding-top: 0.4rem; border-top: 1px dashed var(--border); font-size: 0.75rem; line-height: 1.45; color: var(--text-secondary); }
.form-group { margin-bottom: 0.9rem; }
.form-group label { display: block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.3rem; }
.form-group input, .form-group textarea, .form-group select { width: 100%; padding: 0.5rem 0.7rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.875rem; font-family: inherit; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
.identity-label { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem; }
.link-btn { background: none; border: none; color: var(--accent); font-weight: 600; font-size: 0.8rem; cursor: pointer; }
.identity-row { display: grid; grid-template-columns: 1fr 110px auto; gap: 0.4rem; margin-bottom: 0.4rem; }
.identity-row input { width: 100%; padding: 0.45rem 0.6rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.85rem; }
.icon-btn { border: 1px solid var(--border); background: #fff; border-radius: 8px; width: 34px; cursor: pointer; color: #991b1b; }
.modal-actions { display: flex; gap: 0.6rem; align-items: center; margin-top: 0.5rem; }
.form-error { color: #b91c1c; font-size: 0.82rem; margin: 0 0 0.75rem; }
.lh-item, .lh-event { padding: 0.5rem 0; border-bottom: 1px solid var(--border); }
.lh-title { font-weight: 600; font-size: 0.85rem; margin: 0.2rem 0; }
.lh-preview { font-size: 0.78rem; color: var(--text-secondary); }
.lh-link { font-size: 0.78rem; font-weight: 600; color: var(--accent); text-decoration: none; }
.chp-empty { font-size: 0.84rem; color: var(--text-secondary); }
@media (max-width: 860px) {
    .leads-modal-grid { grid-template-columns: 1fr; }
    .leads-history { border-left: 0; border-top: 1px solid var(--border); }
    .form-row, .identity-row { grid-template-columns: 1fr; }
}
</style>
@endpush

@push('scripts')
<script>
(function () {
    const api = '/api/leads';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const state = { page: 1, status: 'all', search: '', labelIds: [], editingId: null, labels: [], notes: [], companyLabels: [], assignees: [], activities: [], activityPage: 1, activityLastPage: 1, activityTotal: 0 };

    const body = document.getElementById('leadsTableBody');
    const modal = document.getElementById('leadModal');
    const activityModal = document.getElementById('leadActivityModal');
    const form = document.getElementById('leadForm');
    const errorEl = document.getElementById('leadFormError');

    function headers(json) {
        const h = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        if (csrf) h['X-CSRF-TOKEN'] = csrf;
        if (json) h['Content-Type'] = 'application/json';
        return h;
    }
    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }
    function formatAt(iso) {
        if (!iso) return '—';
        try { return new Date(iso).toLocaleString(); } catch { return iso; }
    }
    function chipText(hex) {
        const c = String(hex || '#4338ca').replace('#', '');
        if (c.length !== 6) return '#fff';
        const r = parseInt(c.slice(0, 2), 16), g = parseInt(c.slice(2, 4), 16), b = parseInt(c.slice(4, 6), 16);
        return (r * 299 + g * 587 + b * 114) / 1000 > 160 ? '#111' : '#fff';
    }
    function labelChips(labels) {
        return (labels || []).map(label =>
            `<span class="lead-label-chip" style="background:${esc(label.color || '#4338ca')};color:${chipText(label.color)}">${esc(label.name)}</span>`
        ).join(' ') || '<span class="lead-meta">—</span>';
    }
    function assigneeOptions(selectedId, extraUser) {
        const users = [...state.assignees];
        if (extraUser && extraUser.id && !users.some(u => String(u.id) === String(extraUser.id))) {
            users.push(extraUser);
        }
        const selected = selectedId == null || selectedId === '' ? '' : String(selectedId);
        return `<option value="">Unassigned</option>` + users.map(user =>
            `<option value="${user.id}"${String(user.id) === selected ? ' selected' : ''}>${esc(user.name)}</option>`
        ).join('');
    }
    function fillAssigneeSelect(selectEl, selectedId, extraUser) {
        if (!selectEl) return;
        selectEl.innerHTML = assigneeOptions(selectedId, extraUser);
    }
    function setExtrasVisible(saved) {
        document.getElementById('leadExtras').hidden = !saved;
        document.getElementById('leadExtrasHint').hidden = saved;
    }
    function renderLabelSuggestions() {
        document.getElementById('leadLabelSuggestions').innerHTML =
            state.companyLabels.map(label => `<option value="${esc(label.name)}"></option>`).join('');
        renderLabelFilter();
    }
    function selectedFilterLabels() {
        return state.companyLabels.filter(label => state.labelIds.includes(String(label.id)));
    }
    function renderLabelFilter() {
        const chips = document.getElementById('leadLabelFilterChips');
        const select = document.getElementById('leadLabelFilterSelect');
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
        select.hidden = available.length === 0 && selected.length > 0 && state.companyLabels.length > 0;
    }
    function renderLabels(labels) {
        state.labels = Array.isArray(labels) ? labels : [];
        const list = document.getElementById('leadLabelsList');
        if (!state.labels.length) {
            list.innerHTML = '<span class="lead-note-empty">No labels yet.</span>';
            return;
        }
        list.innerHTML = state.labels.map(label => `
            <span class="lead-label-chip" style="background:${esc(label.color || '#4338ca')};color:${chipText(label.color)}">
                ${esc(label.name)}
                <button type="button" data-remove-label="${label.id}" title="Remove label">&times;</button>
            </span>
        `).join('');
    }
    function renderNotes(notes) {
        state.notes = Array.isArray(notes) ? notes : [];
        const list = document.getElementById('leadNotesList');
        if (!state.notes.length) {
            list.innerHTML = '<div class="lead-note-empty">No notes yet. Add one below.</div>';
            return;
        }
        list.innerHTML = state.notes.map(note => `
            <div class="lead-note-item">
                <div class="lead-note-text">${esc(note.note)}</div>
                <div class="lead-note-meta">
                    <span>Added by ${esc(note.author || 'Unknown')}</span>
                    <span>
                        ${esc(note.time_ago || formatAt(note.created_at))}
                        <button type="button" class="icon-btn" data-remove-note="${note.id}" title="Delete note">&times;</button>
                    </span>
                </div>
            </div>
        `).join('');
    }
    function sortActivitiesDesc(items) {
        return [...(items || [])].sort((a, b) => {
            const timeDiff = new Date(b.created_at || 0) - new Date(a.created_at || 0);
            if (timeDiff !== 0) return timeDiff;
            return (Number(b.id) || 0) - (Number(a.id) || 0);
        });
    }
    function activityDetailLines(item) {
        const meta = item.meta || {};
        const lines = [];
        if (meta.from_user_name !== undefined || meta.to_user_name !== undefined || meta.from_user_id || meta.to_user_id) {
            lines.push((meta.from_user_name || 'Unassigned') + ' → ' + (meta.to_user_name || 'Unassigned'));
        }
        if (meta.field) {
            lines.push((meta.field || 'Field') + ': ' + (meta.from || '—') + ' → ' + (meta.to || '—'));
        } else if ((meta.from != null && meta.from !== '') || (meta.to != null && meta.to !== '')) {
            lines.push((meta.from || '—') + ' → ' + (meta.to || '—'));
        }
        if (meta.reason) lines.push('Reason: ' + meta.reason);
        if (meta.label) lines.push('Label: ' + meta.label);
        if (meta.value) lines.push((meta.type || 'Value') + ': ' + meta.value);
        if (meta.source) lines.push('Source: ' + meta.source);
        lines.push('By ' + (item.actor || 'System'));
        lines.push(formatAt(item.created_at));
        return lines;
    }
    function activityItemHtml(item) {
        const details = activityDetailLines(item);
        return `
            <div class="lead-activity-item" data-activity-id="${esc(item.id || '')}" role="button" tabindex="0">
                <div class="lead-activity-summary">${esc(item.summary || 'Updated this lead')}</div>
                <div class="lead-activity-meta">${esc(item.time_ago || formatAt(item.created_at))} · Click for details</div>
                <div class="lead-activity-details" hidden>
                    ${details.map(line => `<div>${esc(line)}</div>`).join('')}
                </div>
            </div>
        `;
    }
    function renderActivityPreview(latest, total) {
        const preview = document.getElementById('leadActivityPreview');
        const countEl = document.getElementById('leadActivityCount');
        const trigger = document.getElementById('leadActivityTrigger');
        state.activityTotal = Number(total || 0);
        if (trigger) trigger.disabled = !state.editingId;
        if (!latest) {
            if (preview) preview.innerHTML = '<p class="chp-empty">No lead activity yet.</p>';
            if (countEl) countEl.textContent = 'View all updates';
            return;
        }
        if (preview) {
            preview.innerHTML = `
                <div class="lead-activity-summary">${esc(latest.summary || 'Updated this lead')}</div>
                <div class="lead-activity-meta">${esc(latest.time_ago || formatAt(latest.created_at))}</div>
            `;
        }
        if (countEl) {
            const n = state.activityTotal || 1;
            countEl.textContent = 'View all ' + n + ' update' + (n === 1 ? '' : 's');
        }
        document.getElementById('leadActivityModalTitle').textContent =
            (document.getElementById('leadName')?.value || 'Lead') + ' · activity';
    }
    function renderActivityPage(items, pagination) {
        const full = document.getElementById('leadActivityFull');
        const page = pagination?.current_page || state.activityPage || 1;
        const last = pagination?.last_page || state.activityLastPage || 1;
        const total = pagination?.total ?? state.activityTotal ?? 0;
        state.activities = sortActivitiesDesc(items);
        state.activityPage = page;
        state.activityLastPage = last;
        state.activityTotal = total;
        if (full) {
            full.innerHTML = state.activities.length
                ? state.activities.map(activityItemHtml).join('')
                : '<p class="chp-empty" style="padding:1rem">No lead activity yet.</p>';
        }
        document.getElementById('leadActivityPageInfo').textContent =
            `Showing page ${page} of ${last} (${total} update${total === 1 ? '' : 's'})`;
        document.getElementById('leadActivityPrev').disabled = page <= 1;
        document.getElementById('leadActivityNext').disabled = page >= last;
        if (page === 1) {
            renderActivityPreview(state.activities[0] || null, total);
        } else {
            const countEl = document.getElementById('leadActivityCount');
            if (countEl) countEl.textContent = 'View all ' + total + ' update' + (total === 1 ? '' : 's');
        }
    }
    async function loadActivityPage(page) {
        if (!state.editingId) return;
        const q = new URLSearchParams({ page: String(page || 1), per_page: '20' });
        const res = await fetch(api + '/' + state.editingId + '/activity-log?' + q.toString(), {
            credentials: 'same-origin',
            headers: headers(),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.message || 'Could not load activity.');
        renderActivityPage(data.data || [], data.pagination || {});
    }
    function renderActivities(leadOrItems) {
        if (Array.isArray(leadOrItems)) {
            const items = sortActivitiesDesc(leadOrItems);
            renderActivityPreview(items[0] || null, items.length);
            return;
        }
        const lead = leadOrItems || {};
        renderActivityPreview(lead.latest_activity || (lead.activities || [])[0] || null, lead.activity_count ?? (lead.activities || []).length);
    }
    async function refreshActivities(id) {
        if (!id) return;
        try {
            await loadActivityPage(1);
        } catch {}
    }
    async function loadAssignees() {
        try {
            const res = await fetch(api + '/assignees', { credentials: 'same-origin', headers: headers() });
            const data = await res.json();
            state.assignees = data.data || [];
        } catch {
            state.assignees = [];
        }
        fillAssigneeSelect(document.getElementById('leadAssignedTo'), document.getElementById('leadAssignedTo')?.value || '');
    }

    async function loadCompanyLabels() {
        try {
            const res = await fetch(api + '/labels', { credentials: 'same-origin', headers: headers() });
            const data = await res.json();
            state.companyLabels = data.data || [];
            renderLabelSuggestions();
        } catch {
            state.companyLabels = [];
        }
    }

    function addIdentityRow(listId, value, label, placeholder) {
        const row = document.createElement('div');
        row.className = 'identity-row';
        row.innerHTML = `
            <input type="text" class="id-value" value="${esc(value || '')}" placeholder="${esc(placeholder)}">
            <input type="text" class="id-label" value="${esc(label || '')}" placeholder="Label">
            <button type="button" class="icon-btn" title="Remove">&times;</button>
        `;
        row.querySelector('.icon-btn').addEventListener('click', () => row.remove());
        document.getElementById(listId).appendChild(row);
    }
    function readIdentityRows(listId) {
        return [...document.getElementById(listId).querySelectorAll('.identity-row')].map(row => ({
            value: row.querySelector('.id-value').value.trim(),
            label: row.querySelector('.id-label').value.trim() || null,
        })).filter(item => item.value);
    }

    async function loadLeads() {
        const q = new URLSearchParams({ page: String(state.page), per_page: '20', status: state.status });
        if (state.search) q.set('search', state.search);
        state.labelIds.forEach(id => q.append('label_ids[]', id));
        const res = await fetch(api + '?' + q.toString(), { credentials: 'same-origin', headers: headers() });
        const data = await res.json();
        const rows = data.data || [];
        body.innerHTML = rows.length ? rows.map(lead => `
            <tr data-id="${lead.id}">
                <td>
                    <div class="lead-name">${esc(lead.name)}</div>
                    <div class="lead-company">${esc(lead.company_name || lead.source || '')}</div>
                </td>
                <td class="lead-meta">${esc((lead.phones || []).map(p => p.value).join(', ') || '—')}</td>
                <td class="lead-meta">${esc((lead.emails || []).map(e => e.value).join(', ') || '—')}</td>
                <td>${labelChips(lead.labels)}</td>
                <td onclick="event.stopPropagation()">
                    <select class="lead-assign" data-id="${lead.id}" aria-label="Assign lead">
                        ${assigneeOptions(lead.assigned_to, lead.assigned_user)}
                    </select>
                </td>
                <td><span class="lead-badge ${esc(lead.status)}">${esc(lead.status)}</span></td>
                <td class="lead-meta">${esc(formatAt(lead.updated_at))}</td>
                <td><button type="button" class="btn btn-secondary btn-sm" data-open="${lead.id}">Open</button></td>
            </tr>
        `).join('') : `<tr><td colspan="8" class="empty-state">${state.search || state.labelIds.length ? 'No leads match this search.' : 'No leads yet. Create one to start matching conversations across channels.'}</td></tr>`;

        const pag = data.pagination || {};
        document.getElementById('leadsPageInfo').textContent = `Showing page ${pag.current_page || 1} of ${pag.last_page || 1} (${pag.total || 0} leads)`;
        document.getElementById('leadsPrev').disabled = (pag.current_page || 1) <= 1;
        document.getElementById('leadsNext').disabled = (pag.current_page || 1) >= (pag.last_page || 1);
    }

    function resetForm() {
        form.reset();
        document.getElementById('leadId').value = '';
        document.getElementById('phonesList').innerHTML = '';
        document.getElementById('emailsList').innerHTML = '';
        addIdentityRow('phonesList', '', '', 'Phone number');
        addIdentityRow('emailsList', '', '', 'name@company.com');
        errorEl.hidden = true;
        document.getElementById('leadModalTitle').textContent = 'New Lead';
        document.getElementById('deleteLeadBtn').hidden = true;
        document.getElementById('leadHistoryEmpty').hidden = false;
        document.getElementById('leadHistoryEmpty').textContent = 'Save this lead to load Phone, Inbox, Viber, WhatsApp, Facebook, and SMS history.';
        document.getElementById('leadHistoryBody').hidden = true;
        document.getElementById('leadHistoryBody').innerHTML = '';
        document.getElementById('leadNoteInput').value = '';
        document.getElementById('leadLabelInput').value = '';
        fillAssigneeSelect(document.getElementById('leadAssignedTo'), '');
        renderLabels([]);
        renderNotes([]);
        setExtrasVisible(false);
        state.editingId = null;
        renderActivities([]);
    }

    function fillForm(lead) {
        document.getElementById('leadId').value = lead.id;
        document.getElementById('leadName').value = lead.name || '';
        document.getElementById('leadCompany').value = lead.company_name || '';
        document.getElementById('leadStatus').value = lead.status || 'new';
        document.getElementById('leadSource').value = lead.source || '';
        fillAssigneeSelect(document.getElementById('leadAssignedTo'), lead.assigned_to, lead.assigned_user);
        document.getElementById('leadFacebook').value = lead.facebook_name || '';
        document.getElementById('leadInstagram').value = lead.instagram_username || '';
        document.getElementById('phonesList').innerHTML = '';
        document.getElementById('emailsList').innerHTML = '';
        (lead.phones || []).forEach(p => addIdentityRow('phonesList', p.value, p.label, 'Phone number'));
        if (!(lead.phones || []).length) addIdentityRow('phonesList', '', '', 'Phone number');
        (lead.emails || []).forEach(e => addIdentityRow('emailsList', e.value, e.label, 'name@company.com'));
        if (!(lead.emails || []).length) addIdentityRow('emailsList', '', '', 'name@company.com');
        document.getElementById('leadModalTitle').textContent = lead.name;
        document.getElementById('deleteLeadBtn').hidden = false;
        state.editingId = lead.id;
        renderLabels(lead.labels || []);
        renderNotes(lead.notes || []);
        renderActivities(lead);
        setExtrasVisible(true);
        loadHistory(lead.id);
        if (activityModal.classList.contains('open')) {
            loadActivityPage(1).catch(() => {});
        }
    }

    async function loadHistory(id) {
        const empty = document.getElementById('leadHistoryEmpty');
        const pane = document.getElementById('leadHistoryBody');
        empty.hidden = false;
        empty.textContent = 'Loading contact history…';
        pane.hidden = true;
        try {
            const res = await fetch(api + '/' + id + '/history', { credentials: 'same-origin', headers: headers() });
            const data = await res.json();
            const threads = data.threads || [];
            const events = (data.events || []).slice(0, 20);
            if (!threads.length && !events.length) {
                empty.textContent = 'No matching conversations yet. History appears after this person messages any channel.';
                return;
            }
            empty.hidden = true;
            pane.hidden = false;
            pane.innerHTML = `
                ${threads.map(t => `
                    <div class="lh-item">
                        <span class="lead-badge">${esc(t.label || t.channel)}</span>
                        <div class="lh-title">${esc(t.title || '')}</div>
                        <div class="lh-preview">${esc(t.preview || '')}</div>
                        ${t.deep_link ? `<a class="lh-link" href="${esc(t.deep_link)}">Open thread →</a>` : ''}
                    </div>
                `).join('')}
                <h4 style="margin-top:1rem">Timeline</h4>
                ${events.map(ev => `
                    <div class="lh-event">
                        <span class="lead-badge">${esc(ev.label || ev.channel)}</span>
                        <span class="lead-meta">${esc(ev.direction || '')} · ${esc(formatAt(ev.at))}</span>
                        <div class="lh-preview">${esc(ev.preview || '')}</div>
                    </div>
                `).join('')}
            `;
        } catch (err) {
            empty.textContent = err.message || 'Could not load contact history.';
        }
    }

    function openModal() { modal.classList.add('open'); }
    function closeActivityModal() {
        activityModal.classList.remove('open');
    }
    async function openActivityModal() {
        if (!state.editingId) return;
        activityModal.classList.add('open');
        document.getElementById('leadActivityFull').innerHTML = '<p class="chp-empty" style="padding:1rem">Loading updates…</p>';
        try {
            await loadActivityPage(1);
        } catch (err) {
            document.getElementById('leadActivityFull').innerHTML =
                `<p class="chp-empty" style="padding:1rem">${esc(err.message || 'Could not load activity.')}</p>`;
        }
    }
    function closeModal() {
        closeActivityModal();
        modal.classList.remove('open');
        const url = new URL(window.location.href);
        url.searchParams.delete('lead');
        history.replaceState(null, '', url);
    }

    async function openLead(id) {
        const res = await fetch(api + '/' + id, { credentials: 'same-origin', headers: headers() });
        const data = await res.json();
        if (!res.ok) throw new Error(data.message || 'Lead not found');
        fillForm(data.data);
        openModal();
        const url = new URL(window.location.href);
        url.searchParams.set('lead', id);
        history.replaceState(null, '', url);
    }

    document.getElementById('newLeadBtn').addEventListener('click', () => { resetForm(); openModal(); });
    document.getElementById('closeLeadModal').addEventListener('click', closeModal);
    document.getElementById('cancelLeadBtn').addEventListener('click', closeModal);
    document.getElementById('leadActivityTrigger').addEventListener('click', openActivityModal);
    document.getElementById('closeLeadActivityModal').addEventListener('click', closeActivityModal);
    document.getElementById('leadActivityPrev').addEventListener('click', () => {
        if (state.activityPage > 1) loadActivityPage(state.activityPage - 1);
    });
    document.getElementById('leadActivityNext').addEventListener('click', () => {
        if (state.activityPage < state.activityLastPage) loadActivityPage(state.activityPage + 1);
    });
    activityModal.addEventListener('click', (e) => {
        if (e.target === activityModal) closeActivityModal();
    });
    document.getElementById('leadActivityFull').addEventListener('click', (e) => {
        const item = e.target.closest('.lead-activity-item');
        if (!item) return;
        const details = item.querySelector('.lead-activity-details');
        if (!details) return;
        const opening = details.hidden;
        item.classList.toggle('open', opening);
        details.hidden = !opening;
    });
    document.getElementById('leadActivityFull').addEventListener('keydown', (e) => {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        const item = e.target.closest('.lead-activity-item');
        if (!item) return;
        e.preventDefault();
        item.click();
    });
    document.getElementById('addPhoneBtn').addEventListener('click', () => addIdentityRow('phonesList', '', '', 'Phone number'));
    document.getElementById('addEmailBtn').addEventListener('click', () => addIdentityRow('emailsList', '', '', 'name@company.com'));
    document.getElementById('leadsPrev').addEventListener('click', () => { state.page = Math.max(1, state.page - 1); loadLeads(); });
    document.getElementById('leadsNext').addEventListener('click', () => { state.page += 1; loadLeads(); });

    let searchTimer;
    document.getElementById('leadSearch').addEventListener('input', (e) => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            state.search = e.target.value.trim();
            state.page = 1;
            loadLeads();
        }, 250);
    });
    document.getElementById('leadLabelFilterSelect').addEventListener('change', (e) => {
        const id = e.target.value;
        if (id && !state.labelIds.includes(id)) {
            state.labelIds.push(id);
            state.page = 1;
            renderLabelFilter();
            loadLeads();
        }
        e.target.value = '';
    });
    document.getElementById('leadLabelFilterChips').addEventListener('click', (e) => {
        const btn = e.target.closest('[data-unfilter-label]');
        if (!btn) return;
        state.labelIds = state.labelIds.filter(id => id !== String(btn.dataset.unfilterLabel));
        state.page = 1;
        renderLabelFilter();
        loadLeads();
    });
    document.querySelectorAll('.leads-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.leads-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            state.status = tab.dataset.status;
            state.page = 1;
            loadLeads();
        });
    });
    body.addEventListener('click', (e) => {
        if (e.target.closest('.lead-assign')) return;
        const row = e.target.closest('tr[data-id]');
        if (row) openLead(row.dataset.id);
    });
    body.addEventListener('change', async (e) => {
        const select = e.target.closest('.lead-assign');
        if (!select) return;
        const id = select.dataset.id;
        const assignedTo = select.value || null;
        select.disabled = true;
        try {
            const res = await fetch(api + '/' + id + '/assign', {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: headers(true),
                body: JSON.stringify({ assigned_to: assignedTo }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || 'Could not assign lead.');
            if (String(state.editingId) === String(id) && data.data) {
                fillAssigneeSelect(document.getElementById('leadAssignedTo'), data.data.assigned_to, data.data.assigned_user);
                renderActivities(data.data);
            }
        } catch (err) {
            alert(err.message);
            loadLeads();
        } finally {
            select.disabled = false;
        }
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        errorEl.hidden = true;
        const payload = {
            name: document.getElementById('leadName').value.trim(),
            company_name: document.getElementById('leadCompany').value.trim() || null,
            status: document.getElementById('leadStatus').value,
            source: document.getElementById('leadSource').value.trim() || null,
            assigned_to: document.getElementById('leadAssignedTo').value || null,
            phones: readIdentityRows('phonesList'),
            emails: readIdentityRows('emailsList'),
            facebook_name: document.getElementById('leadFacebook').value.trim() || null,
            instagram_username: document.getElementById('leadInstagram').value.trim() || null,
        };
        const id = document.getElementById('leadId').value;
        document.getElementById('saveLeadBtn').disabled = true;
        try {
            const res = await fetch(id ? api + '/' + id : api, {
                method: id ? 'PUT' : 'POST',
                credentials: 'same-origin',
                headers: headers(true),
                body: JSON.stringify(payload),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                const firstError = data.errors ? Object.values(data.errors)[0]?.[0] : null;
                throw new Error(firstError || data.message || 'Could not save lead.');
            }
            await loadLeads();
            fillForm(data.data);
            if (data.data?.id) {
                const url = new URL(window.location.href);
                url.searchParams.set('lead', data.data.id);
                history.replaceState(null, '', url);
            }
        } catch (err) {
            errorEl.hidden = false;
            errorEl.textContent = err.message;
        } finally {
            document.getElementById('saveLeadBtn').disabled = false;
        }
    });

    document.getElementById('deleteLeadBtn').addEventListener('click', async () => {
        const id = document.getElementById('leadId').value;
        if (!id || !confirm('Delete this lead? Channel conversations stay, but this identity will be removed.')) return;
        const res = await fetch(api + '/' + id, { method: 'DELETE', credentials: 'same-origin', headers: headers() });
        if (!res.ok) {
            const data = await res.json().catch(() => ({}));
            alert(data.message || 'Could not delete lead.');
            return;
        }
        closeModal();
        loadLeads();
    });

    async function addLeadNote() {
        const id = state.editingId;
        const input = document.getElementById('leadNoteInput');
        const text = input.value.trim();
        if (!id) return;
        if (!text) { input.focus(); return; }
        document.getElementById('addLeadNoteBtn').disabled = true;
        try {
            const res = await fetch(api + '/' + id + '/notes', {
                method: 'POST',
                credentials: 'same-origin',
                headers: headers(true),
                body: JSON.stringify({ note: text }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || 'Could not add note.');
            input.value = '';
            renderNotes([data.data, ...state.notes]);
            refreshActivities(id);
            loadLeads();
        } catch (err) {
            errorEl.hidden = false;
            errorEl.textContent = err.message;
        } finally {
            document.getElementById('addLeadNoteBtn').disabled = false;
        }
    }

    async function addLeadLabel() {
        const id = state.editingId;
        const input = document.getElementById('leadLabelInput');
        const name = input.value.trim();
        if (!id) return;
        if (!name) { input.focus(); return; }
        if (state.labels.some(label => label.name.toLowerCase() === name.toLowerCase())) {
            input.value = '';
            return;
        }
        document.getElementById('addLeadLabelBtn').disabled = true;
        try {
            const res = await fetch(api + '/' + id + '/labels', {
                method: 'POST',
                credentials: 'same-origin',
                headers: headers(true),
                body: JSON.stringify({ name }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || 'Could not add label.');
            input.value = '';
            renderLabels(data.labels || []);
            refreshActivities(id);
            if (data.data && !state.companyLabels.some(label => label.id === data.data.id)) {
                state.companyLabels.push(data.data);
                renderLabelSuggestions();
            }
            loadLeads();
        } catch (err) {
            errorEl.hidden = false;
            errorEl.textContent = err.message;
        } finally {
            document.getElementById('addLeadLabelBtn').disabled = false;
        }
    }

    document.getElementById('addLeadNoteBtn').addEventListener('click', addLeadNote);
    document.getElementById('addLeadLabelBtn').addEventListener('click', addLeadLabel);
    document.getElementById('leadLabelInput').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            addLeadLabel();
        }
    });
    document.getElementById('leadNoteInput').addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
            e.preventDefault();
            addLeadNote();
        }
    });
    document.getElementById('leadNotesList').addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-remove-note]');
        if (!btn || !state.editingId) return;
        const noteId = btn.dataset.removeNote;
        const res = await fetch(api + '/' + state.editingId + '/notes/' + noteId, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: headers(),
        });
        if (!res.ok) {
            const data = await res.json().catch(() => ({}));
            alert(data.message || 'Could not delete note.');
            return;
        }
        renderNotes(state.notes.filter(note => String(note.id) !== String(noteId)));
        refreshActivities(state.editingId);
        loadLeads();
    });
    document.getElementById('leadLabelsList').addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-remove-label]');
        if (!btn || !state.editingId) return;
        const labelId = btn.dataset.removeLabel;
        const res = await fetch(api + '/' + state.editingId + '/labels/' + labelId, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: headers(),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            alert(data.message || 'Could not remove label.');
            return;
        }
        renderLabels(data.labels || state.labels.filter(label => String(label.id) !== String(labelId)));
        refreshActivities(state.editingId);
        loadLeads();
    });

    resetForm();
    Promise.all([loadCompanyLabels(), loadAssignees()]).then(() => loadLeads()).then(() => {
        const id = new URLSearchParams(window.location.search).get('lead');
        if (id) openLead(id).catch(() => {});
    });
})();
</script>
@endpush
