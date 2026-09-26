/* Leads page logic (Vite entry) */
(function () {
    const api = '/api/leads';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const LEAD_OPTIONS = window.__leadsConfig?.leadOptions || {};
    const STOREGANISE_CONNECTED = !!window.__leadsConfig?.storeganiseConnected;
    const CAN_VIEW_QUOTATION_BUILDER = !!window.__leadsConfig?.canViewQuotationBuilder;
    const LEAD_QUOTE_URL_BASE = window.__leadsConfig?.leadQuoteUrlBase || '/quotation-builder/leads';
    const state = { page: 1, status: 'all', search: '', source: '', assignedTo: '', noSharedThread: false, labelIds: [], sort: 'lead_age', sortDir: 'asc', statusCounts: {}, editingId: null, editingRuleId: null, labels: [], notes: [], companyLabels: [], statuses: [], defaultStatus: 'new', assignees: [], inboxes: [], emailTemplates: [], activities: [], activityPage: 1, activityLastPage: 1, activityTotal: 0, rules: [], rulesPage: 1, rulesLastPage: 1, rulesTotal: 0, rulesSearch: '', canManageRules: !!window.__leadsConfig?.canManageLeadRules, attachedInboxConversations: [], pendingInboxConversations: [], inboxSearchTimer: null, messageLeadId: '', messageChannels: [], messageChannel: '', leadPhones: [], leadName: '', savedLeadStoreganiseSiteId: null, storeganiseSites: [], storeganiseSitesLoaded: false, storeganiseAction: null };

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
    function timeAgo(iso) {
        if (!iso) return '—';
        const then = new Date(iso).getTime();
        if (Number.isNaN(then)) return '—';
        const diffSec = Math.floor((Date.now() - then) / 1000);
        if (diffSec < 60) return 'just now';
        const units = [
            ['y', 31536000],
            ['mo', 2592000],
            ['w', 604800],
            ['d', 86400],
            ['h', 3600],
            ['m', 60],
        ];
        for (const [label, secs] of units) {
            const value = Math.floor(diffSec / secs);
            if (value >= 1) return `${value}${label} ago`;
        }
        return 'just now';
    }
    function leadAgeDays(iso) {
        if (!iso) return '—';
        const created = new Date(iso).getTime();
        if (Number.isNaN(created)) return '—';
        const diffMs = Math.max(0, Date.now() - created);
        const totalMinutes = Math.floor(diffMs / 60000);
        const days = Math.floor(totalMinutes / 1440);
        const hours = Math.floor((totalMinutes % 1440) / 60);
        const minutes = totalMinutes % 60;
        if (totalMinutes < 1) return 'Just now';
        const parts = [];
        if (days > 0) parts.push(`${days}d`);
        if (hours > 0 || days > 0) parts.push(`${hours}h`);
        parts.push(`${minutes}m`);
        return parts.join(' ');
    }
    function formatDate(iso) {
        if (!iso) return '';
        try { return new Date(iso).toLocaleDateString(); } catch { return iso; }
    }
    function setLeadModalAdded(lead) {
        const el = document.getElementById('leadModalAdded');
        if (!el) return;
        if (!lead?.created_at) {
            el.hidden = true;
            el.textContent = '';
            return;
        }
        const added = formatDate(lead.created_at);
        const days = Number.isFinite(Number(lead.lead_age_days))
            ? Math.max(0, Number(lead.lead_age_days))
            : null;
        let age = '';
        if (days === 0) age = 'today';
        else if (days === 1) age = '1 day';
        else if (days != null) age = days + ' days';
        el.textContent = age ? `Added ${added} · ${age}` : `Added ${added}`;
        el.hidden = false;
    }
    function activeLeadTab() {
        return document.querySelector('.lead-form-tab.active')?.dataset.leadTab || 'primary';
    }
    function leadQuoteUrl(leadId) {
        return `${LEAD_QUOTE_URL_BASE}/${encodeURIComponent(leadId)}/quote`;
    }
    function resetLeadStoreganiseFacilitySelect() {
        const select = document.getElementById('leadStoreganiseSite');
        if (!select) return;
        select.innerHTML = '<option value="">Select a facility…</option>';
        select.value = '';
    }
    function setSavedLeadStoreganiseSiteId(siteId) {
        const normalized = String(siteId ?? '').trim();
        state.savedLeadStoreganiseSiteId = normalized !== '' ? normalized : null;
    }
    function leadHasStoreganiseFacility() {
        if (!STOREGANISE_CONNECTED) return false;
        if (!(state.editingId || document.getElementById('leadId')?.value)) return false;

        return String(state.savedLeadStoreganiseSiteId || '').trim() !== '';
    }
    function updateLeadModalQuoteButton() {
        const btn = document.getElementById('leadModalQuoteBtn');
        if (!btn || !CAN_VIEW_QUOTATION_BUILDER) return;

        const leadId = state.editingId || document.getElementById('leadId')?.value || '';
        const onSourceTab = activeLeadTab() === 'source';
        const show = Boolean(leadId) && onSourceTab && leadHasStoreganiseFacility();

        btn.hidden = !show;
        btn.href = show ? leadQuoteUrl(leadId) : '#';
    }
    function renderLeadModalHeaderMeta(lead = null) {
        const metaRow = document.getElementById('leadModalHeaderMeta');
        const labelsWrap = document.getElementById('leadModalLabelsWrap');
        const labelsEl = document.getElementById('leadModalLabels');
        const facilityWrap = document.getElementById('leadModalStoreganiseWrap');
        const facilityEl = document.getElementById('leadModalStoreganise');
        if (!metaRow || !labelsWrap || !labelsEl || !facilityWrap || !facilityEl) return;

        const labels = state.labels || [];
        const hasLabels = labels.length > 0;
        labelsWrap.hidden = !hasLabels;
        labelsEl.innerHTML = hasLabels ? labelChips(labels) : '';

        let facilityLabel = '';
        const siteId = String(state.savedLeadStoreganiseSiteId || document.getElementById('leadStoreganiseSite')?.value || '').trim();
        if (STOREGANISE_CONNECTED && (state.editingId || lead?.id) && siteId) {
            const site = state.storeganiseSites.find(s => String(s.id) === String(siteId));
            facilityLabel = site ? storeganiseSiteLabel(site) : siteId;
        }
        const hasFacility = facilityLabel !== '';
        facilityWrap.hidden = !hasFacility;
        facilityEl.textContent = facilityLabel;

        metaRow.hidden = !hasLabels && !hasFacility;
        updateLeadModalQuoteButton();
    }
    function statusName(slug) {
        const key = String(slug || '');
        const row = (state.statuses || []).find(s => s.slug === key);
        return row?.name || key || 'new';
    }
    function statusBadge(lead) {
        const status = lead.status || state.defaultStatus || 'new';
        const label = status === 'snoozed' && lead.reopen_at
            ? statusName(status) + ' until ' + formatDate(lead.reopen_at)
            : statusName(status);
        return `<span class="lead-badge ${esc(status)}">${esc(label)}</span>`;
    }
    function sourceVisual(lead) {
        const source = String(lead.source || '').trim();
        const hasThread = !!lead.has_connected_thread;
        if (!hasThread) {
            return source ? `<div class="lead-company">${esc(source)}</div>` : '';
        }
        const channel = String(lead.connected_thread_channel || 'inbox');
        const label = source || lead.connected_thread_label || 'Connected thread';
        const title = lead.connected_thread_label
            ? `Open ${lead.connected_thread_label} thread`
            : 'Open connected thread';
        const icon = channelIcon(channel);
        const cls = `lead-source has-thread ${esc(channel)}`;
        if (lead.connected_thread_url) {
            return `<a class="${cls}" href="${esc(lead.connected_thread_url)}" title="${esc(title)}">${icon}<span>${esc(label)}</span></a>`;
        }
        return `<span class="${cls}" title="${esc(title)}">${icon}<span>${esc(label)}</span></span>`;
    }
    function leadSourceCellHtml(lead) {
        return sourceVisual(lead);
    }
    const LEAD_MODAL_CHANNEL_NAV = [
        { key: 'phone', label: 'Phone', channels: ['call'] },
        { key: 'inbox', label: 'Inbox', channels: ['inbox'] },
        { key: 'viber', label: 'Viber', channels: ['viber'] },
        { key: 'facebook', label: 'Facebook', channels: ['facebook', 'instagram'] },
        { key: 'sms', label: 'SMS', channels: ['sms'] },
    ];
    function channelIcon(channel) {
        if (channel === 'call' || channel === 'phone') {
            return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>`;
        }
        if (channel === 'whatsapp' || channel === 'viber' || channel === 'sms') {
            return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>`;
        }
        if (channel === 'facebook' || channel === 'instagram') {
            return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>`;
        }
        return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>`;
    }
    function pickLeadChannelLink(threads, events, channelIds) {
        for (const thread of threads || []) {
            const channel = String(thread.channel || '');
            if (channelIds.includes(channel) && thread.deep_link) {
                return { url: thread.deep_link, title: thread.title || thread.label || '' };
            }
        }
        for (const event of events || []) {
            const channel = String(event.channel || '');
            if (channelIds.includes(channel) && event.deep_link) {
                return { url: event.deep_link, title: event.label || '' };
            }
        }
        return null;
    }
    function leadPhoneValues(lead) {
        const values = [];
        const push = (value) => {
            const trimmed = String(value || '').trim();
            if (trimmed) values.push(trimmed);
        };
        for (const list of [lead?.primary_phones, lead?.phones, lead?.alt_phones]) {
            if (!Array.isArray(list)) continue;
            for (const item of list) push(typeof item === 'string' ? item : item?.value);
        }
        push(lead?.phone);
        push(lead?.alt_phone);
        return [...new Set(values)];
    }
    function leadPhoneChannelUrl(channelKey, phone, name) {
        const trimmed = String(phone || '').trim();
        if (!trimmed) return null;
        if (channelKey === 'phone') {
            return '/twilio/call?phone=' + encodeURIComponent(trimmed);
        }
        if (channelKey === 'sms') {
            const params = new URLSearchParams({ phone: trimmed });
            const leadName = String(name || '').trim();
            if (leadName) params.set('name', leadName);
            return '/sms?' + params.toString();
        }
        return null;
    }
    function clearLeadModalChannelLinks() {
        const el = document.getElementById('leadModalChannelLinks');
        if (!el) return;
        el.hidden = true;
        el.innerHTML = '';
    }
    function renderLeadModalChannelLinks(threads, events, leadPhones) {
        const el = document.getElementById('leadModalChannelLinks');
        if (!el) return;
        const primaryPhone = (leadPhones || [])[0] || '';
        const links = LEAD_MODAL_CHANNEL_NAV.map(def => {
            const picked = pickLeadChannelLink(threads, events, def.channels);
            let url = picked?.url || '';
            let title = picked?.title || '';
            if (!url && primaryPhone && (def.key === 'phone' || def.key === 'sms')) {
                url = leadPhoneChannelUrl(def.key, primaryPhone, state.leadName) || '';
                title = primaryPhone;
            }
            if (!url) return '';
            const iconChannel = def.key === 'phone' ? 'call' : def.key;
            const linkTitle = title ? `Open ${def.label}: ${title}` : `Open ${def.label}`;
            return `<a class="lead-source has-thread ${esc(def.key)}" href="${esc(url)}" title="${esc(linkTitle)}">${channelIcon(iconChannel)}<span>${esc(def.label)}</span></a>`;
        }).filter(Boolean).join('');
        if (!links) {
            el.hidden = true;
            el.innerHTML = '';
            return;
        }
        el.innerHTML = links;
        el.hidden = false;
    }
    function chipText(hex) {
        const c = String(hex || '#4338ca').replace('#', '');
        if (c.length !== 6) return '#fff';
        const r = parseInt(c.slice(0, 2), 16), g = parseInt(c.slice(2, 4), 16), b = parseInt(c.slice(4, 6), 16);
        return (r * 299 + g * 587 + b * 114) / 1000 > 160 ? '#111' : '#fff';
    }
    function labelChips(labels) {
        return (labels || []).map(label => {
            const color = label.color || '#4338ca';
            return `<span class="inbox-pill" style="background:${color}22;color:${color}">${esc(label.name)}</span>`;
        }).join(' ') || '<span class="lead-meta">—</span>';
    }
    function spinnerHtml() {
        return '<span class="leads-spinner" aria-hidden="true"></span>';
    }
    function setBusy(btn, busy, label) {
        if (!btn) return;
        if (busy) {
            if (btn.dataset.idleHtml == null) btn.dataset.idleHtml = btn.innerHTML;
            btn.disabled = true;
            btn.classList.add('is-busy');
            btn.setAttribute('aria-busy', 'true');
            btn.innerHTML = spinnerHtml() + '<span>' + esc(label || 'Please wait…') + '</span>';
            return;
        }
        btn.disabled = false;
        btn.classList.remove('is-busy');
        btn.removeAttribute('aria-busy');
        if (btn.dataset.idleHtml != null) btn.innerHTML = btn.dataset.idleHtml;
    }
    function setOverlay(id, busy, text) {
        const el = document.getElementById(id);
        if (!el) return;
        el.hidden = !busy;
        const label = el.querySelector('#leadModalBusyText, [data-busy-text]');
        if (label && text) label.textContent = text;
    }
    function leadRowHtml(lead) {
        return `
            <tr data-id="${lead.id}" data-lead-source="${esc(lead.source || '')}">
                <td>
                    <div class="lead-name">${esc([lead.title, lead.name].filter(Boolean).join(' '))}</div>
                    ${lead.company_name ? `<div class="lead-company">${esc(lead.company_name)}</div>` : ''}
                    <div data-col="source">${sourceVisual(lead)}</div>
                </td>
                <td class="lead-meta" title="${lead.created_at ? esc(formatAt(lead.created_at)) : ''}">${esc(leadAgeDays(lead.created_at))}</td>
                <td class="lead-meta">${esc((lead.phones || []).map(p => p.value).join(', ') || '—')}</td>
                <td class="lead-meta">${esc((lead.emails || []).map(e => e.value).join(', ') || '—')}</td>
                <td>${labelChips(lead.labels)}</td>
                <td>
                    <select class="lead-assign" data-id="${lead.id}" aria-label="Assign lead">
                        ${assigneeOptions(lead.assigned_to, lead.assigned_user)}
                    </select>
                </td>
                <td>${statusBadge(lead)}</td>
                <td class="lead-meta">${esc(formatAt(lead.updated_at))}</td>
                <td class="lead-meta" data-col="thread-age" title="${lead.connected_thread_label ? esc(lead.connected_thread_label + ' · ' + formatAt(lead.connected_thread_at)) : 'No connected thread'}">${esc(timeAgo(lead.connected_thread_at))}</td>
                <td>
                    <button type="button" class="btn btn-secondary btn-sm" data-message="${lead.id}">Message</button>
                </td>
            </tr>
        `;
    }
    function upsertLeadRow(lead) {
        if (!lead?.id) return;
        const existing = body.querySelector('tr[data-id="' + lead.id + '"]');
        if (existing) {
            existing.outerHTML = leadRowHtml(lead);
        } else if (body.querySelector('.empty-state')) {
            body.innerHTML = leadRowHtml(lead);
        } else {
            body.insertAdjacentHTML('afterbegin', leadRowHtml(lead));
        }
    }
    function removeLeadRow(id) {
        const existing = body.querySelector('tr[data-id="' + id + '"]');
        if (existing) existing.remove();
        if (!body.querySelector('tr[data-id]')) {
            body.innerHTML = `<tr><td colspan="10" class="empty-state">${state.search || state.labelIds.length || state.source || state.assignedTo ? 'No leads match this search.' : 'No leads yet. Create one to start matching conversations across channels.'}</td></tr>`;
        }
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
        const select = document.getElementById('leadLabelSelect');
        if (select) {
            const attached = new Set((state.labels || []).map(label => String(label.id)));
            const available = (state.companyLabels || []).filter(label => !attached.has(String(label.id)));
            select.innerHTML = '<option value="">Select a label…</option>' + available.map(label =>
                `<option value="${esc(label.name)}">${esc(label.name)}</option>`
            ).join('');
            select.disabled = !state.editingId || !available.length;
        }
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
    function renderSourceFilter(sources) {
        const select = document.getElementById('leadSourceFilter');
        if (!select) return;
        const fromDb = Array.isArray(sources) ? sources.filter(Boolean).map(String) : [];
        const list = [...new Set([...(LEAD_OPTIONS.sources || []), ...fromDb])];
        const current = state.source || '';
        if (current && current !== '__none__' && !list.includes(current)) list.push(current);
        select.innerHTML = `<option value="">All sources</option><option value="__none__">No source</option>` +
            list.map(source => `<option value="${esc(source)}">${esc(source)}</option>`).join('');
        select.value = current;
    }
    function renderAssigneeFilter() {
        const select = document.getElementById('leadAssigneeFilter');
        if (!select) return;
        const current = state.assignedTo || '';
        const users = [...state.assignees];
        if (current && current !== '__none__' && !users.some(user => String(user.id) === String(current))) {
            users.push({ id: current, name: 'Assignee #' + current });
        }
        select.innerHTML = `<option value="">All assignees</option><option value="__none__">Unassigned</option>` +
            users.map(user => `<option value="${esc(user.id)}">${esc(user.name)}</option>`).join('');
        select.value = current;
    }
    function renderLabels(labels) {
        state.labels = Array.isArray(labels) ? labels : [];
        const list = document.getElementById('leadLabelsList');
        if (!state.labels.length) {
            list.innerHTML = '<span class="lead-note-empty">No labels yet.</span>';
            renderLabelSuggestions();
            renderLeadModalHeaderMeta();
            return;
        }
        list.innerHTML = state.labels.map(label => `
            <span class="lead-label-chip" style="background:${esc(label.color || '#4338ca')};color:${chipText(label.color)}">
                ${esc(label.name)}
                <button type="button" data-remove-label="${label.id}" title="Remove label">&times;</button>
            </span>
        `).join('');
        renderLabelSuggestions();
        renderLeadModalHeaderMeta();
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
        if (meta.site_name) lines.push('Facility: ' + meta.site_name);
        if (meta.user_id) lines.push('Storeganise user: ' + meta.user_id);
        if (meta.linked_existing) lines.push('Linked to existing Storeganise user.');
        if (Array.isArray(meta.duplicates) && meta.duplicates.length) {
            meta.duplicates.forEach((dup) => {
                const bits = [dup.name, dup.email, dup.phone].filter(Boolean).join(' · ');
                const matches = Array.isArray(dup.match_values) ? dup.match_values.join(', ') : '';
                lines.push('Possible duplicate: ' + (bits || dup.id || 'User') + (matches ? ' (' + matches + ')' : ''));
            });
        }
        if (Array.isArray(meta.match_values) && meta.match_values.length) {
            lines.push('Matched on: ' + meta.match_values.join(', '));
        }
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
            (leadDisplayName() || 'Lead') + ' · activity';
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
        renderAssigneeFilter();
        fillAssigneeSelect(document.getElementById('leadAssignedTo'), document.getElementById('leadAssignedTo')?.value || '');
    }

    async function loadCompanyLabels() {
        try {
            const res = await fetch(api + '/labels', { credentials: 'same-origin', headers: headers() });
            const data = await res.json();
            state.companyLabels = data.data || [];
            renderLabelSuggestions();
            renderCompanyLabelList();
            syncOpenLeadLabelsFromCompany();
        } catch {
            state.companyLabels = [];
            renderCompanyLabelList();
        }
    }
    async function loadCompanyStatuses() {
        try {
            const res = await fetch(api + '/statuses', { credentials: 'same-origin', headers: headers() });
            const data = await res.json();
            state.statuses = data.data || [];
            state.defaultStatus = data.meta?.default || state.defaultStatus || 'new';
        } catch {
            state.statuses = [];
        }
        renderStatusTabs();
        fillStatusSelect(document.getElementById('leadStatus')?.value || state.defaultStatus);
        renderCompanyStatusList();
    }
    function statusOptions(selected) {
        const current = selected || state.defaultStatus || 'new';
        const rows = [...(state.statuses || [])];
        if (current && !rows.some(s => s.slug === current)) {
            rows.push({ slug: current, name: current });
        }
        return rows.map(s =>
            `<option value="${esc(s.slug)}" ${s.slug === current ? 'selected' : ''}>${esc(s.name)}</option>`
        ).join('');
    }
    function fillStatusSelect(selected) {
        const select = document.getElementById('leadStatus');
        if (!select) return;
        select.innerHTML = statusOptions(selected || state.defaultStatus || 'new');
    }
    function renderStatusTabs() {
        const wrap = document.getElementById('leadStatusTabs');
        if (!wrap) return;
        const current = state.status || 'all';
        const counts = state.statusCounts || {};
        const tabs = [{ slug: 'all', name: 'All' }, ...(state.statuses || [])];
        wrap.innerHTML = tabs.map(status => {
            const count = counts[status.slug];
            const badge = count == null ? '' : ` <span data-count="${esc(status.slug)}">${count}</span>`;
            return `<button type="button" class="leads-tab${current === status.slug ? ' active' : ''}" data-status="${esc(status.slug)}">${esc(status.name)}${badge}</button>`;
        }).join('');
        if (current !== 'all' && !(state.statuses || []).some(s => s.slug === current)) {
            state.status = 'all';
            wrap.querySelector('[data-status="all"]')?.classList.add('active');
        }
    }
    function renderCompanyStatusList() {
        const list = document.getElementById('leadCompanyStatusList');
        if (!list) return;
        if (!state.statuses.length) {
            list.innerHTML = '<div class="chp-empty">No statuses yet. Add one below.</div>';
            return;
        }
        list.innerHTML = state.statuses.map(status => `
            <div class="leads-rule-row">
                <div class="leads-rule-row-main">
                    <input type="text" class="leads-status-name-input" data-status-name="${status.id}" value="${esc(status.name)}" maxlength="50" aria-label="Status name">
                </div>
                <div class="leads-rule-row-actions">
                    <button type="button" class="btn btn-secondary btn-sm" data-save-company-status="${status.id}">Save</button>
                    ${status.is_locked ? '<span class="lead-meta">Required</span>' : `<button type="button" class="btn btn-secondary btn-sm" data-delete-company-status="${status.id}">Delete</button>`}
                </div>
            </div>
        `).join('');
    }
    function openStatusesModal() {
        document.getElementById('leadStatusesModal')?.classList.add('open');
        renderCompanyStatusList();
        loadCompanyStatuses();
        document.getElementById('leadCompanyStatusName')?.focus();
    }
    function closeStatusesModal() {
        document.getElementById('leadStatusesModal')?.classList.remove('open');
    }
    function renderCompanyLabelList() {
        const list = document.getElementById('leadCompanyLabelList');
        if (!list) return;
        if (!state.companyLabels.length) {
            list.innerHTML = '<div class="chp-empty">No labels yet. Add one below.</div>';
            return;
        }
        list.innerHTML = state.companyLabels.map(label => `
            <div class="leads-rule-row">
                <div class="leads-rule-row-main">
                    <input type="text" class="leads-status-name-input" data-label-name="${label.id}" value="${esc(label.name)}" maxlength="50" aria-label="Label name">
                </div>
                <div class="leads-rule-row-actions">
                    <input type="color" class="leads-label-row-color" data-label-color="${label.id}" value="${esc(label.color || '#4338ca')}" title="Change color" aria-label="Change color">
                    <button type="button" class="btn btn-secondary btn-sm" data-save-company-label="${label.id}">Save</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-delete-company-label="${label.id}">Delete</button>
                </div>
            </div>
        `).join('');
    }
    function openLabelsModal() {
        document.getElementById('leadLabelsModal')?.classList.add('open');
        renderCompanyLabelList();
        loadCompanyLabels();
        document.getElementById('leadCompanyLabelName')?.focus();
    }
    function closeLabelsModal() {
        document.getElementById('leadLabelsModal')?.classList.remove('open');
    }
    function syncOpenLeadLabelsFromCompany() {
        if (!state.labels.length) return;
        const next = state.labels.map(label => {
            const updated = state.companyLabels.find(item => String(item.id) === String(label.id));
            return updated ? { ...label, name: updated.name, color: updated.color } : label;
        });
        const changed = next.some((label, index) =>
            label.name !== state.labels[index].name || label.color !== state.labels[index].color
        );
        if (changed) renderLabels(next);
    }
    async function saveCompanyLabel(id) {
        const input = document.querySelector(`[data-label-name="${id}"]`);
        const colorEl = document.querySelector(`[data-label-color="${id}"]`);
        const name = input?.value.trim() || '';
        if (!name) { input?.focus(); return; }
        const save = document.querySelector(`[data-save-company-label="${id}"]`);
        if (save) save.disabled = true;
        try {
            const res = await fetch(api + '/labels/' + id, {
                method: 'PATCH', credentials: 'same-origin', headers: headers(true),
                body: JSON.stringify({ name, color: colorEl?.value || '#4338ca' }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || 'Could not update label.');
            await loadCompanyLabels();
            loadLeads();
        } catch (err) {
            alert(err.message);
        } finally {
            if (save) save.disabled = false;
        }
    }

    function contactKindLabel(type) {
        return type === 'email' ? 'email address' : 'phone number';
    }
    function contactRoleLabel(listId) {
        return String(listId || '').startsWith('alt') ? 'alternate' : 'primary';
    }
    function addContactRow(listId, value, placeholder, opts = {}) {
        const list = document.getElementById(listId);
        if (!list) return;
        const row = document.createElement('div');
        row.className = 'identity-row';
        const type = opts.type || 'text';
        const required = opts.required ? 'required' : '';
        const max = opts.max || (type === 'email' ? '255' : '50');
        const identityId = opts.identityId ? String(opts.identityId) : '';
        if (identityId) row.dataset.identityId = identityId;
        row.innerHTML = `
            <input type="${esc(type)}" class="id-value" value="${esc(value || '')}" placeholder="${esc(placeholder)}" maxlength="${esc(max)}" ${required}>
            <button type="button" class="icon-btn" title="Remove" aria-label="Remove ${esc(contactKindLabel(type))}">&times;</button>
        `;
        row.querySelector('.icon-btn').addEventListener('click', () => removeContactRow(listId, row, opts));
        list.appendChild(row);
    }
    async function removeContactRow(listId, row, opts = {}) {
        const list = document.getElementById(listId);
        if (!list || !row) return;
        const btn = row.querySelector('.icon-btn');
        const input = row.querySelector('.id-value');
        const identityId = row.dataset.identityId || '';
        const value = (input?.value || '').trim();
        const kind = contactKindLabel(opts.type);
        const role = contactRoleLabel(listId);
        const keepLast = () => opts.keepOne && list.querySelectorAll('.identity-row').length <= 1;
        const clearOrRemove = () => {
            if (keepLast()) {
                if (input) input.value = '';
                delete row.dataset.identityId;
                return;
            }
            row.remove();
        };

        if (state.editingId && identityId) {
            if (!confirm(`Remove this ${role} ${kind} from the lead? This takes effect immediately.`)) return;
            if (btn) {
                btn.disabled = true;
                btn.classList.add('is-busy');
                btn.innerHTML = '<span class="leads-spinner" aria-hidden="true"></span>';
            }
            try {
                const res = await fetch(api + '/' + state.editingId + '/identities/' + identityId, {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: headers(true),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(data.message || 'Could not remove this ' + kind + '.');
                clearOrRemove();
                if (data.data) {
                    upsertLeadRow(data.data);
                    renderActivities(data.data);
                    if (activityModal.classList.contains('open')) {
                        loadActivityPage(1).catch(() => {});
                    }
                }
                await loadLeads();
            } catch (err) {
                alert(err.message);
                if (btn) btn.disabled = false;
            }
            return;
        }

        if (value && !confirm(`Remove this ${role} ${kind}?`)) return;
        clearOrRemove();
    }
    function fillContactList(listId, items, placeholder, opts = {}) {
        const list = document.getElementById(listId);
        if (!list) return;
        list.innerHTML = '';
        const rows = (Array.isArray(items) ? items : [])
            .map(item => {
                if (typeof item === 'string') return { value: item.trim(), id: null };
                return { value: String(item?.value || '').trim(), id: item?.id || null };
            })
            .filter(item => item.value);
        if (!rows.length) {
            addContactRow(listId, '', placeholder, { ...opts, required: !!opts.required });
            return;
        }
        rows.forEach((item, index) => {
            addContactRow(listId, item.value, placeholder, {
                ...opts,
                required: !!opts.required && index === 0,
                identityId: item.id,
            });
        });
    }
    function readContactRows(listId) {
        const list = document.getElementById(listId);
        if (!list) return [];
        return [...list.querySelectorAll('.identity-row')].map(row => ({
            value: row.querySelector('.id-value').value.trim(),
        })).filter(item => item.value);
    }

    async function loadLeads(opts = {}) {
        if (opts.overlay !== false) setOverlay('leadsTableBusy', true);
        const q = new URLSearchParams({ page: String(state.page), per_page: '20', status: state.status });
        if (state.sort) q.set('sort', state.sort);
        if (state.sortDir) q.set('direction', state.sortDir);
        if (state.search) q.set('search', state.search);
        if (state.source) q.set('source', state.source);
        if (state.assignedTo) q.set('assigned_to', state.assignedTo);
        if (state.noSharedThread) q.set('no_shared_thread', '1');
        // Thread matching is deferred (see hydrateConnectedThreads) except when sorting by thread age.
        if (state.sort === 'thread_age') q.set('include_threads', '1');
        state.labelIds.forEach(id => q.append('label_ids[]', id));
        try {
            const res = await fetch(api + '?' + q.toString(), { credentials: 'same-origin', headers: headers() });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || 'Could not load leads.');
            const rows = data.data || [];
            body.innerHTML = rows.length
                ? rows.map(lead => leadRowHtml(lead)).join('')
                : `<tr><td colspan="10" class="empty-state">${state.search || state.labelIds.length || state.source || state.assignedTo || state.noSharedThread ? 'No leads match this search.' : 'No leads yet. Create one to start matching conversations across channels.'}</td></tr>`;

            const pag = data.pagination || {};
            document.getElementById('leadsPageInfo').textContent = `Showing page ${pag.current_page || 1} of ${pag.last_page || 1} (${pag.total || 0} leads)`;
            document.getElementById('leadsPrev').disabled = (pag.current_page || 1) <= 1;
            document.getElementById('leadsNext').disabled = (pag.current_page || 1) >= (pag.last_page || 1);
            renderSourceFilter(data.sources || []);
            if (data.status_counts && typeof data.status_counts === 'object') {
                state.statusCounts = data.status_counts;
                renderStatusTabs();
            } else {
                loadStatusCounts();
            }
            if (state.sort !== 'thread_age' && rows.length) {
                hydrateConnectedThreads(rows.map(r => r.id));
            }
        } catch (err) {
            body.innerHTML = '<tr><td colspan="10" class="empty-state">Could not load leads. Try again.</td></tr>';
            if (err?.message) console.error(err.message);
        } finally {
            if (opts.overlay !== false) setOverlay('leadsTableBusy', false);
        }
    }

    async function hydrateConnectedThreads(leadIds) {
        const ids = (leadIds || []).map(Number).filter(id => id > 0);
        if (!ids.length) return;
        try {
            const q = new URLSearchParams();
            ids.forEach(id => q.append('ids[]', String(id)));
            const res = await fetch(api + '/connected-threads?' + q.toString(), { credentials: 'same-origin', headers: headers() });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.data) return;
            const map = data.data;
            ids.forEach(id => {
                const patch = map[String(id)] || map[id];
                if (!patch) return;
                const row = body.querySelector(`tr[data-id="${id}"]`);
                if (!row) return;
                const lead = {
                    id,
                    source: row.dataset.leadSource || '',
                    has_connected_thread: !!patch.has_connected_thread,
                    connected_thread_url: patch.connected_thread_url,
                    connected_thread_channel: patch.connected_thread_channel,
                    connected_thread_label: patch.connected_thread_label,
                    connected_thread_at: patch.connected_thread_at,
                };
                const sourceCell = row.querySelector('[data-col="source"]');
                if (sourceCell) sourceCell.innerHTML = leadSourceCellHtml(lead);
                const ageCell = row.querySelector('[data-col="thread-age"]');
                if (ageCell) {
                    ageCell.title = lead.connected_thread_label
                        ? `${lead.connected_thread_label} · ${formatAt(lead.connected_thread_at)}`
                        : 'No connected thread';
                    ageCell.textContent = timeAgo(lead.connected_thread_at);
                }
            });
        } catch (err) {
            console.warn('Could not hydrate connected threads', err);
        }
    }

    async function loadStatusCounts() {
        const q = new URLSearchParams({ status: state.status });
        if (state.search) q.set('search', state.search);
        if (state.source) q.set('source', state.source);
        if (state.assignedTo) q.set('assigned_to', state.assignedTo);
        if (state.noSharedThread) q.set('no_shared_thread', '1');
        state.labelIds.forEach(id => q.append('label_ids[]', id));
        try {
            const res = await fetch(api + '/status-counts?' + q.toString(), { credentials: 'same-origin', headers: headers() });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) return;
            if (data.data && typeof data.data === 'object') {
                state.statusCounts = data.data;
                renderStatusTabs();
            }
        } catch {}
    }

    function val(id) {
        return (document.getElementById(id)?.value || '').trim();
    }
    function setVal(id, value) {
        const el = document.getElementById(id);
        if (el) el.value = value || '';
    }
    function splitName(name) {
        const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
        if (!parts.length) return { first: '', last: '' };
        if (parts.length === 1) return { first: parts[0], last: '' };
        return { first: parts[0], last: parts.slice(1).join(' ') };
    }
    function leadDisplayName() {
        const composed = [val('leadTitle'), val('leadFirstName'), val('leadLastName')].filter(Boolean).join(' ');
        return composed || 'Lead';
    }
    function selectedCustomerType() {
        return document.querySelector('input[name="leadCustomerType"]:checked')?.value || '';
    }
    function setCustomerType(value) {
        document.querySelectorAll('input[name="leadCustomerType"]').forEach((input) => {
            input.checked = input.value === value;
        });
    }
    function fillSourceSelect(value) {
        const select = document.getElementById('leadSource');
        if (!select) return;
        const options = LEAD_OPTIONS.sources || [];
        const current = value || '';
        select.innerHTML = `<option value="">Select one</option>` +
            options.map(source => `<option value="${esc(source)}">${esc(source)}</option>`).join('');
        if (current && !options.includes(current)) {
            select.insertAdjacentHTML('beforeend', `<option value="${esc(current)}">${esc(current)}</option>`);
        }
        select.value = current;
    }
    function syncLeadProfileFields() {
        const type = selectedCustomerType();
        const resWrap = document.getElementById('leadResidentialWrap');
        const bizWrap = document.getElementById('leadBusinessWrap');
        const industry = val('leadBusinessIndustry');
        const reason = val('leadStorageReason');
        if (resWrap) resWrap.hidden = type !== 'residential';
        if (bizWrap) bizWrap.hidden = type !== 'business';
        const bizOtherWrap = document.getElementById('leadBusinessIndustryOtherWrap');
        const reasonOtherWrap = document.getElementById('leadStorageReasonOtherWrap');
        if (bizOtherWrap) bizOtherWrap.hidden = type !== 'business' || industry !== 'Other';
        if (reasonOtherWrap) reasonOtherWrap.hidden = reason !== 'Other';
    }

    function showLeadTab(name) {
        const tabName = name || 'primary';
        document.querySelectorAll('.lead-form-tab').forEach((tab) => {
            const active = tab.dataset.leadTab === tabName;
            tab.classList.toggle('active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        document.querySelectorAll('.lead-form-panel').forEach((panel) => {
            const active = panel.dataset.leadPanel === tabName;
            panel.classList.toggle('active', active);
            panel.hidden = !active;
        });
        if (tabName === 'matching') {
            searchInboxEmails(document.getElementById('leadInboxSearch').value.trim());
        }
        updateLeadModalQuoteButton();
    }
    function showLeadTabForElement(el) {
        const panel = el?.closest?.('[data-lead-panel]');
        if (panel?.dataset.leadPanel) showLeadTab(panel.dataset.leadPanel);
    }
    function resetForm() {
        form.reset();
        document.getElementById('leadId').value = '';
        showLeadTab('primary');
        fillContactList('primaryPhonesList', [], 'Phone number', { type: 'tel', keepOne: true });
        fillContactList('primaryEmailsList', [], 'name@company.com', { type: 'email', keepOne: true, max: '255' });
        fillContactList('altPhonesList', [], 'Phone number', { type: 'tel' });
        fillContactList('altEmailsList', [], 'name@company.com', { type: 'email', max: '255' });
        fillSourceSelect('');
        setCustomerType('');
        fillStatusSelect(state.defaultStatus);
        syncLeadProfileFields();
        errorEl.hidden = true;
        document.getElementById('leadModalTitle').textContent = 'New Lead';
        setLeadModalAdded(null);
        clearLeadModalChannelLinks();
        document.getElementById('deleteLeadBtn').hidden = true;
        document.getElementById('leadHistoryEmpty').hidden = false;
        document.getElementById('leadHistoryEmpty').textContent = 'Save this lead to load Phone, Inbox, Viber, WhatsApp, Facebook, and SMS history.';
        document.getElementById('leadHistoryBody').hidden = true;
        document.getElementById('leadHistoryBody').innerHTML = '';
        document.getElementById('leadNoteInput').value = '';
        const labelSelect = document.getElementById('leadLabelSelect');
        if (labelSelect) labelSelect.value = '';
        fillAssigneeSelect(document.getElementById('leadAssignedTo'), '');
        renderLabels([]);
        renderNotes([]);
        setExtrasVisible(false);
        state.editingId = null;
        state.savedLeadStoreganiseSiteId = null;
        resetLeadStoreganiseFacilitySelect();
        state.leadPhones = [];
        state.leadName = '';
        state.attachedInboxConversations = [];
        state.pendingInboxConversations = [];
        document.getElementById('leadInboxSearch').value = '';
        document.getElementById('leadInboxResults').innerHTML = '';
        renderAttachedInboxEmails();
        renderActivities([]);
        renderLeadModalHeaderMeta(null);
        renderStoreganiseBlock(null);
        updateLeadModalQuoteButton();
    }

    function storeganiseSiteLabel(site) {
        const name = String(site?.name || '').trim();
        const code = String(site?.code || '').trim();
        if (name && code && name.toLowerCase() !== code.toLowerCase()) {
            return `${name} (${code})`;
        }
        return name || code || String(site?.id || 'Facility');
    }

    async function loadStoreganiseSites() {
        if (!STOREGANISE_CONNECTED || state.storeganiseSitesLoaded) {
            return state.storeganiseSites;
        }
        const statusEl = document.getElementById('leadStoreganiseStatus');
        try {
            const res = await fetch('/api/integrations/storeganise/sites', {
                credentials: 'same-origin',
                headers: headers(),
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok && Array.isArray(data.sites)) {
                state.storeganiseSites = data.sites;
                state.storeganiseSitesLoaded = true;
                return state.storeganiseSites;
            }
            if (statusEl) {
                statusEl.hidden = false;
                statusEl.className = 'form-hint is-error';
                statusEl.textContent = data.error || 'Could not load Storeganise facilities. Refresh the page or check the integration.';
            }
        } catch (error) {
            console.error('Failed to load Storeganise facilities', error);
            if (statusEl) {
                statusEl.hidden = false;
                statusEl.className = 'form-hint is-error';
                statusEl.textContent = 'Could not load Storeganise facilities.';
            }
        }
        return state.storeganiseSites;
    }

    function renderStoreganiseSiteOptions(selectedId) {
        const select = document.getElementById('leadStoreganiseSite');
        if (!select) return;
        const options = ['<option value="">Select a facility…</option>'];
        state.storeganiseSites.forEach((site) => {
            const selected = String(site.id) === String(selectedId || '') ? ' selected' : '';
            options.push(`<option value="${esc(site.id)}"${selected}>${esc(storeganiseSiteLabel(site))}</option>`);
        });
        select.innerHTML = options.join('');
    }

    function renderStoreganiseActionButton() {
        const btn = document.getElementById('syncLeadStoreganiseBtn');
        const siteId = document.getElementById('leadStoreganiseSite')?.value || '';
        if (!btn) return;
        if (!siteId || !state.storeganiseAction) {
            btn.hidden = true;
            return;
        }
        btn.hidden = false;
        btn.disabled = false;
        btn.textContent = state.storeganiseAction === 'update' ? 'Update in Storeganise' : 'Push to Storeganise';
    }

    async function refreshStoreganiseAction(leadId, siteId) {
        state.storeganiseAction = null;
        renderStoreganiseActionButton();
        if (!STOREGANISE_CONNECTED || !leadId || !siteId) {
            renderLeadModalHeaderMeta();
            return;
        }
        try {
            const q = new URLSearchParams({ site_id: siteId });
            const res = await fetch(`${api}/${leadId}/storeganise/status?${q.toString()}`, {
                credentials: 'same-origin',
                headers: headers(),
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok && (data.action === 'push' || data.action === 'update')) {
                state.storeganiseAction = data.action;
            }
        } catch (error) {
            console.error('Failed to resolve Storeganise action', error);
        }
        renderStoreganiseActionButton();
        renderLeadModalHeaderMeta();
    }

    async function renderStoreganiseBlock(lead) {
        const block = document.getElementById('leadStoreganiseBlock');
        if (!block) return;
        if (!STOREGANISE_CONNECTED) {
            block.hidden = true;
            renderLeadModalHeaderMeta(lead);
            return;
        }
        block.hidden = !lead?.id;
        if (!lead?.id) {
            state.storeganiseAction = null;
            renderStoreganiseActionButton();
            renderLeadModalHeaderMeta(null);
            return;
        }
        await loadStoreganiseSites();
        const selectedSite = lead.storeganise_site_id || document.getElementById('leadStoreganiseSite')?.value || '';
        renderStoreganiseSiteOptions(selectedSite);
        await refreshStoreganiseAction(lead.id, selectedSite || document.getElementById('leadStoreganiseSite')?.value || '');
        renderLeadModalHeaderMeta(lead);
    }

    async function submitStoreganiseAction(mode) {
        const siteId = document.getElementById('leadStoreganiseSite')?.value || '';
        const btn = document.getElementById('syncLeadStoreganiseBtn');
        if (!document.getElementById('leadId').value) {
            alert('Save this lead before syncing to Storeganise.');
            return;
        }
        if (!siteId) {
            alert('Select a facility first.');
            return;
        }
        const busyLabel = mode === 'update' ? 'Updating…' : 'Pushing…';
        setBusy(btn, true, busyLabel);
        try {
            const saved = await persistLeadForm({ reloadList: false, useOverlay: false });
            if (!saved?.id) {
                return;
            }
            const leadId = saved.id;
            const endpoint = mode === 'update' ? 'update' : 'push';
            const res = await fetch(`${api}/${leadId}/storeganise/${endpoint}`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: headers(true),
                body: JSON.stringify({ site_id: siteId }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                alert(data.error || data.message || `Failed to ${mode === 'update' ? 'update' : 'push'} lead in Storeganise.`);
                await refreshStoreganiseAction(leadId, siteId);
                return;
            }
            if (data.data) {
                fillForm(data.data);
                renderStoreganiseBlock(data.data);
                renderActivities(data.data);
            }
            alert(data.message || `Lead ${mode === 'update' ? 'updated in' : 'pushed to'} Storeganise.`);
        } catch (error) {
            console.error(error);
            alert(`Failed to ${mode === 'update' ? 'update' : 'push'} lead in Storeganise.`);
        } finally {
            setBusy(btn, false);
        }
    }

    async function syncLeadToStoreganise() {
        const mode = state.storeganiseAction === 'update' ? 'update' : 'push';
        await submitStoreganiseAction(mode);
    }

    function fillForm(lead) {
        const parsed = splitName(lead.name);
        document.getElementById('leadId').value = lead.id;
        setSavedLeadStoreganiseSiteId(lead.storeganise_site_id);
        resetLeadStoreganiseFacilitySelect();
        updateLeadModalQuoteButton();
        setVal('leadTitle', lead.title);
        setVal('leadFirstName', lead.first_name || parsed.first);
        setVal('leadLastName', lead.last_name || parsed.last);
        setVal('leadAddress', lead.address);
        setVal('leadCity', lead.city);
        setVal('leadPostal', lead.postal_code);
        fillContactList('primaryPhonesList', lead.primary_phones || (lead.phone ? [lead.phone] : []), 'Phone number', { type: 'tel', keepOne: true });
        fillContactList('primaryEmailsList', lead.primary_emails || (lead.email ? [lead.email] : []), 'name@company.com', { type: 'email', keepOne: true, max: '255' });
        setVal('leadCompany', lead.company_name);
        setVal('leadDob', lead.date_of_birth);
        setVal('leadAltTitle', lead.alt_title);
        setVal('leadAltFirstName', lead.alt_first_name);
        setVal('leadAltLastName', lead.alt_last_name);
        setVal('leadAltAddress', lead.alt_address);
        setVal('leadAltCity', lead.alt_city);
        setVal('leadAltPostal', lead.alt_postal_code);
        fillContactList('altPhonesList', lead.alt_phones || (lead.alt_phone ? [lead.alt_phone] : []), 'Phone number', { type: 'tel' });
        fillContactList('altEmailsList', lead.alt_emails || (lead.alt_email ? [lead.alt_email] : []), 'name@company.com', { type: 'email', max: '255' });
        fillStatusSelect(lead.status || state.defaultStatus);
        fillSourceSelect(lead.source || '');
        setCustomerType(lead.customer_type || '');
        setVal('leadResidentialType', lead.residential_type);
        setVal('leadBusinessIndustry', lead.business_industry);
        setVal('leadBusinessIndustryOther', lead.business_industry_other);
        setVal('leadStorageReason', lead.storage_reason);
        setVal('leadStorageReasonOther', lead.storage_reason_other);
        syncLeadProfileFields();
        fillAssigneeSelect(document.getElementById('leadAssignedTo'), lead.assigned_to, lead.assigned_user);
        document.getElementById('leadFacebook').value = lead.facebook_name || '';
        document.getElementById('leadInstagram').value = lead.instagram_username || '';
        document.getElementById('leadInboxSearch').value = '';
        document.getElementById('leadInboxResults').innerHTML = '';
        state.pendingInboxConversations = [];
        state.attachedInboxConversations = Array.isArray(lead.attached_inbox_conversations) ? lead.attached_inbox_conversations : [];
        state.editingId = lead.id;
        renderAttachedInboxEmails();
        if (!lead.attached_inbox_conversations) {
            loadAttachedInboxEmails(lead.id);
        }
        document.getElementById('leadModalTitle').textContent = [lead.title, lead.name].filter(Boolean).join(' ') || 'Lead';
        setLeadModalAdded(lead);
        document.getElementById('deleteLeadBtn').hidden = false;
        state.leadPhones = leadPhoneValues(lead);
        state.leadName = [lead.title, lead.name].filter(Boolean).join(' ').trim();
        renderLeadModalChannelLinks([], [], state.leadPhones);
        renderLabels(lead.labels || []);
        renderNotes(lead.notes || []);
        renderActivities(lead);
        setExtrasVisible(true);
        loadHistory(lead.id);
        if (activityModal.classList.contains('open')) {
            loadActivityPage(1).catch(() => {});
        }
        renderStoreganiseBlock(lead);
    }

    async function loadHistory(id) {
        const empty = document.getElementById('leadHistoryEmpty');
        const pane = document.getElementById('leadHistoryBody');
        empty.hidden = false;
        empty.textContent = 'Loading contact history…';
        pane.hidden = true;
        renderLeadModalChannelLinks([], [], state.leadPhones);
        try {
            const res = await fetch(api + '/' + id + '/history', { credentials: 'same-origin', headers: headers() });
            const data = await res.json();
            const threads = data.threads || [];
            const events = (data.events || []).slice(0, 20);
            renderLeadModalChannelLinks(threads, data.events || [], state.leadPhones);
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
            renderLeadModalChannelLinks([], [], state.leadPhones);
            empty.textContent = err.message || 'Could not load contact history.';
        }
    }

    function inboxEmailIds(list) {
        return (list || []).map(c => Number(c.id));
    }
    function inboxEmailMeta(c) {
        const from = c.from_name || c.from_email || 'Unknown sender';
        const mailbox = c.inbox?.name || c.inbox?.email || 'Shared inbox';
        const subject = c.subject || '(No subject)';
        return { from, mailbox, subject };
    }
    function renderAttachedInboxEmails() {
        renderLeadModalHeaderMeta();
        const list = document.getElementById('leadInboxAttachedList');
        if (!list) return;
        const items = state.editingId ? state.attachedInboxConversations : state.pendingInboxConversations;
        if (!items.length) {
            list.innerHTML = '<p class="lead-inbox-empty">No shared emails attached yet.</p>';
            return;
        }
        list.innerHTML = items.map(c => {
            const meta = inboxEmailMeta(c);
            return `
                <div class="lead-inbox-row" data-inbox-id="${c.id}">
                    <div>
                        <strong>${esc(meta.subject)}</strong>
                        <div class="lead-meta">${esc(meta.from)}${c.from_email && c.from_name ? ' · ' + esc(c.from_email) : ''}</div>
                        <div class="lead-meta">${esc(meta.mailbox)}</div>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" data-inbox-detach="${c.id}">Remove</button>
                </div>
            `;
        }).join('');
    }
    function renderInboxSearchResults(items) {
        const box = document.getElementById('leadInboxResults');
        if (!box) return;
        const attached = new Set(inboxEmailIds(state.editingId ? state.attachedInboxConversations : state.pendingInboxConversations));
        const rows = (items || []).filter(c => !attached.has(Number(c.id)));
        if (!rows.length) {
            box.innerHTML = '<p class="lead-inbox-empty">No matching shared emails.</p>';
            return;
        }
        box.innerHTML = rows.map(c => {
            const meta = inboxEmailMeta(c);
            return `
                <div class="lead-inbox-row" data-inbox-id="${c.id}">
                    <div>
                        <strong>${esc(meta.subject)}</strong>
                        <div class="lead-meta">${esc(meta.from)}${c.from_email && c.from_name ? ' · ' + esc(c.from_email) : ''}</div>
                        <div class="lead-meta">${esc(meta.mailbox)}</div>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" data-inbox-attach="${c.id}">Attach</button>
                </div>
            `;
        }).join('');
        box._results = rows;
    }
    async function loadAttachedInboxEmails(id) {
        if (!id) return;
        try {
            const res = await fetch(api + '/' + id + '/inbox-conversations', { credentials: 'same-origin', headers: headers() });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Could not load attached emails.');
            state.attachedInboxConversations = data.data || [];
            renderAttachedInboxEmails();
        } catch (err) {
            document.getElementById('leadInboxAttachedList').innerHTML =
                `<p class="lead-inbox-empty">${esc(err.message || 'Could not load attached emails.')}</p>`;
        }
    }
    async function searchInboxEmails(q) {
        const box = document.getElementById('leadInboxResults');
        if (!box) return;
        box.innerHTML = '<p class="lead-inbox-empty">Searching…</p>';
        try {
            const params = new URLSearchParams({ q });
            if (state.editingId) params.set('except_lead_id', String(state.editingId));
            const res = await fetch(api + '/inbox-conversations?' + params.toString(), { credentials: 'same-origin', headers: headers() });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Could not search emails.');
            renderInboxSearchResults(data.data || []);
        } catch (err) {
            box.innerHTML = `<p class="lead-inbox-empty">${esc(err.message || 'Could not search emails.')}</p>`;
        }
    }
    async function attachInboxEmail(conversation) {
        if (state.editingId) {
            const res = await fetch(api + '/' + state.editingId + '/inbox-conversations', {
                method: 'POST',
                credentials: 'same-origin',
                headers: headers(true),
                body: JSON.stringify({ conversation_id: conversation.id }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || 'Could not attach email.');
            state.attachedInboxConversations = data.data?.attached_inbox_conversations
                || (data.conversation ? state.attachedInboxConversations.concat([data.conversation]) : state.attachedInboxConversations);
            if (data.data?.labels) {
                renderLabels(data.data.labels);
            }
            renderAttachedInboxEmails();
            loadHistory(state.editingId);
            return;
        }
        if (!state.pendingInboxConversations.some(c => Number(c.id) === Number(conversation.id))) {
            state.pendingInboxConversations.push(conversation);
        }
        renderAttachedInboxEmails();
    }
    async function detachInboxEmail(id) {
        if (state.editingId) {
            const res = await fetch(api + '/' + state.editingId + '/inbox-conversations/' + id, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: headers(),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || 'Could not detach email.');
            state.attachedInboxConversations = data.data || [];
            renderAttachedInboxEmails();
            loadHistory(state.editingId);
            return;
        }
        state.pendingInboxConversations = state.pendingInboxConversations.filter(c => Number(c.id) !== Number(id));
        renderAttachedInboxEmails();
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
        url.searchParams.delete('tab');
        history.replaceState(null, '', url);
    }

    function resolveLeadTab(name) {
        const allowed = ['primary', 'alternate', 'source', 'matching', 'notes'];
        return allowed.includes(name) ? name : 'primary';
    }

    async function openLead(id, options = {}) {
        const res = await fetch(api + '/' + id, { credentials: 'same-origin', headers: headers() });
        const data = await res.json();
        if (!res.ok) throw new Error(data.message || 'Lead not found');
        fillForm(data.data);
        const tab = resolveLeadTab(options.tab || new URLSearchParams(window.location.search).get('tab') || 'primary');
        showLeadTab(tab);
        openModal();
        const url = new URL(window.location.href);
        url.searchParams.set('lead', id);
        if (tab !== 'primary') {
            url.searchParams.set('tab', tab);
        } else {
            url.searchParams.delete('tab');
        }
        history.replaceState(null, '', url);
    }

    document.getElementById('newLeadBtn').addEventListener('click', () => { resetForm(); openModal(); });
    document.getElementById('closeLeadModal').addEventListener('click', closeModal);
    document.getElementById('cancelLeadBtn').addEventListener('click', closeModal);
    document.getElementById('syncLeadStoreganiseBtn')?.addEventListener('click', () => { syncLeadToStoreganise(); });
    document.getElementById('leadStoreganiseSite')?.addEventListener('change', () => {
        const leadId = document.getElementById('leadId')?.value || '';
        const siteId = document.getElementById('leadStoreganiseSite')?.value || '';
        refreshStoreganiseAction(leadId, siteId);
        renderLeadModalHeaderMeta();
        updateLeadModalQuoteButton();
    });
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
    document.getElementById('addPrimaryPhoneBtn').addEventListener('click', () => addContactRow('primaryPhonesList', '', 'Phone number', { type: 'tel', keepOne: true }));
    document.getElementById('addPrimaryEmailBtn').addEventListener('click', () => addContactRow('primaryEmailsList', '', 'name@company.com', { type: 'email', keepOne: true, max: '255' }));
    document.getElementById('addAltPhoneBtn').addEventListener('click', () => addContactRow('altPhonesList', '', 'Phone number', { type: 'tel' }));
    document.getElementById('addAltEmailBtn').addEventListener('click', () => addContactRow('altEmailsList', '', 'name@company.com', { type: 'email', max: '255' }));
    document.getElementById('leadInboxSearch').addEventListener('input', () => {
        const q = document.getElementById('leadInboxSearch').value.trim();
        clearTimeout(state.inboxSearchTimer);
        state.inboxSearchTimer = setTimeout(() => searchInboxEmails(q), 250);
    });
    document.getElementById('leadInboxAttachedList').addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-inbox-detach]');
        if (!btn) return;
        btn.disabled = true;
        try {
            await detachInboxEmail(btn.dataset.inboxDetach);
        } catch (err) {
            alert(err.message || 'Could not detach email.');
        } finally {
            btn.disabled = false;
        }
    });
    document.getElementById('leadInboxResults').addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-inbox-attach]');
        if (!btn) return;
        const id = Number(btn.dataset.inboxAttach);
        const results = document.getElementById('leadInboxResults')._results || [];
        const conversation = results.find(c => Number(c.id) === id);
        if (!conversation) return;
        btn.disabled = true;
        try {
            await attachInboxEmail(conversation);
            await searchInboxEmails(document.getElementById('leadInboxSearch').value.trim());
        } catch (err) {
            alert(err.message || 'Could not attach email.');
        } finally {
            btn.disabled = false;
        }
    });
    document.querySelectorAll('.lead-form-tab').forEach((tab) => {
        tab.addEventListener('click', () => showLeadTab(tab.dataset.leadTab));
    });
    document.querySelectorAll('input[name="leadCustomerType"]').forEach((input) => {
        input.addEventListener('change', syncLeadProfileFields);
    });
    document.getElementById('leadBusinessIndustry')?.addEventListener('change', syncLeadProfileFields);
    document.getElementById('leadStorageReason')?.addEventListener('change', syncLeadProfileFields);
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
    document.getElementById('leadSourceFilter')?.addEventListener('change', (e) => {
        state.source = e.target.value || '';
        state.page = 1;
        loadLeads();
    });
    document.getElementById('leadAssigneeFilter')?.addEventListener('change', (e) => {
        state.assignedTo = e.target.value || '';
        state.page = 1;
        loadLeads();
    });
    document.getElementById('leadThreadFilter')?.addEventListener('change', (e) => {
        state.noSharedThread = e.target.value === '1';
        state.page = 1;
        loadLeads();
    });
    document.getElementById('leadSortFilter')?.addEventListener('change', (e) => {
        state.sort = e.target.value || 'updated_at';
        state.page = 1;
        loadLeads();
    });
    document.getElementById('leadSortDirFilter')?.addEventListener('change', (e) => {
        state.sortDir = e.target.value || 'desc';
        state.page = 1;
        loadLeads();
    });
    document.getElementById('leadStatusTabs')?.addEventListener('click', (e) => {
        const tab = e.target.closest('.leads-tab');
        if (!tab) return;
        document.querySelectorAll('#leadStatusTabs .leads-tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        state.status = tab.dataset.status;
        state.page = 1;
        loadLeads();
    });
    function closeMessageModal() {
        document.getElementById('leadMessageModal')?.classList.remove('open');
        state.messageLeadId = '';
        state.messageChannels = [];
        state.messageChannel = '';
    }
    function isMailFollowUp() {
        return currentMessageChannel()?.id === 'inbox';
    }
    function getMailHtmlEditor() {
        return {
            root: document.getElementById('leadMessageHtmlEditor'),
            visual: document.getElementById('leadMessageHtmlVisual'),
            source: document.getElementById('leadMessageHtmlSource'),
        };
    }
    function sanitizeFollowUpHtml(html) {
        const wrap = document.createElement('div');
        wrap.innerHTML = String(html || '');
        wrap.querySelectorAll('script,iframe,object,embed,link,meta').forEach((node) => node.remove());
        wrap.querySelectorAll('*').forEach((node) => {
            [...node.attributes].forEach((attr) => {
                const name = attr.name.toLowerCase();
                const value = String(attr.value || '');
                if (name.startsWith('on') || (name === 'href' && /^\s*javascript:/i.test(value))) {
                    node.removeAttribute(attr.name);
                }
            });
        });
        return wrap.innerHTML;
    }
    function htmlToPlainFollowUp(html) {
        const wrap = document.createElement('div');
        wrap.innerHTML = String(html || '');
        return (wrap.textContent || '').replace(/\u00a0/g, ' ').trim();
    }
    function plainToFollowUpHtml(text) {
        return esc(String(text || '')).replace(/\r\n|\r|\n/g, '<br>');
    }
    function setMailEditorMode(mode) {
        const ed = getMailHtmlEditor();
        if (!ed.root) return;
        const visualMode = mode !== 'source';
        if (visualMode) {
            if (ed.visual && ed.source) ed.visual.innerHTML = sanitizeFollowUpHtml(ed.source.value);
            if (ed.visual) ed.visual.hidden = false;
            if (ed.source) ed.source.hidden = true;
        } else {
            if (ed.source && ed.visual) ed.source.value = sanitizeFollowUpHtml(ed.visual.innerHTML);
            if (ed.visual) ed.visual.hidden = true;
            if (ed.source) {
                ed.source.hidden = false;
                ed.source.focus();
            }
        }
        ed.root.querySelectorAll('[data-html-mode]').forEach((btn) => {
            btn.classList.toggle('is-active', btn.dataset.htmlMode === (visualMode ? 'visual' : 'source'));
        });
    }
    function setMailEditorContent(html) {
        const ed = getMailHtmlEditor();
        const clean = sanitizeFollowUpHtml(html || '');
        if (ed.visual) ed.visual.innerHTML = clean;
        if (ed.source) ed.source.value = clean;
        setMailEditorMode('visual');
    }
    function getMailEditorContent() {
        const ed = getMailHtmlEditor();
        if (!ed.source) return '';
        if (ed.source.hidden === false) return sanitizeFollowUpHtml(ed.source.value.trim());
        return sanitizeFollowUpHtml((ed.visual?.innerHTML || '').trim());
    }
    function syncFollowUpComposer(isMail) {
        const htmlWrap = document.getElementById('leadMessageHtmlWrap');
        const plainWrap = document.getElementById('leadMessagePlainWrap');
        const subjectWrap = document.getElementById('leadMessageSubjectWrap');
        const wasMail = htmlWrap && !htmlWrap.hidden;
        if (wasMail && !isMail) {
            const html = getMailEditorContent();
            const plain = htmlToPlainFollowUp(html);
            const textarea = document.getElementById('leadMessageBody');
            if (textarea && plain) textarea.value = plain;
        } else if (!wasMail && isMail) {
            const textarea = document.getElementById('leadMessageBody');
            const plain = textarea?.value || '';
            if (plain.trim()) setMailEditorContent(plainToFollowUpHtml(plain));
        }
        if (htmlWrap) htmlWrap.hidden = !isMail;
        if (plainWrap) plainWrap.hidden = isMail;
        if (subjectWrap) subjectWrap.hidden = !isMail;
        fillMessageMailboxes(isMail);
        fillMessageRecipients(isMail);
    }
    function getFollowUpBody() {
        return isMailFollowUp() ? getMailEditorContent() : (document.getElementById('leadMessageBody')?.value || '');
    }
    function setFollowUpBody(value) {
        if (isMailFollowUp()) setMailEditorContent(value || '');
        else document.getElementById('leadMessageBody').value = value || '';
    }
    async function openMessageModal(leadId) {
        state.messageLeadId = String(leadId || '');
        const title = document.getElementById('leadMessageModalTitle');
        const err = document.getElementById('leadMessageError');
        if (err) { err.hidden = true; err.textContent = ''; }
        if (title) title.textContent = 'Send follow-up';
        document.getElementById('leadMessageModal')?.classList.add('open');
        document.getElementById('leadMessageBody').value = '';
        document.getElementById('leadMessageSubject').value = '';
        const toList = document.getElementById('leadMessageTo');
        if (toList) toList.innerHTML = '';
        setMailEditorContent('');
        setMailEditorMode('visual');
        if (!state.messageLeadId) return;
        try {
            const res = await fetch(api + '/' + state.messageLeadId + '/message-channels', { credentials: 'same-origin', headers: headers() });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Could not load channels.');
            state.messageChannels = data.data?.channels || [];
            renderMessageChannels();
        } catch (e) {
            if (err) { err.hidden = false; err.textContent = e.message; }
        }
    }
    function renderMessageChannels() {
        const wrap = document.getElementById('leadMessageChannels');
        if (!wrap) return;
        const firstAvailable = state.messageChannels.find(c => c.available);
        if (!state.messageChannel || !state.messageChannels.some(c => c.id === state.messageChannel && c.available)) {
            state.messageChannel = firstAvailable?.id || '';
        }
        wrap.innerHTML = state.messageChannels.map(ch => `
            <button type="button" class="lead-message-channel ${ch.id === state.messageChannel ? 'active' : ''}" data-channel="${esc(ch.id)}" ${ch.available ? '' : 'disabled'} title="${esc(ch.reason || ch.label)}">${esc(ch.label)}</button>
        `).join('') || '<p class="chp-empty">No channels available.</p>';
        fillMessageTemplates();
    }
    function currentMessageChannel() {
        return state.messageChannels.find(c => c.id === state.messageChannel) || null;
    }
    function mailboxOptionLabel(box) {
        const name = String(box.name || 'Mailbox');
        const email = String(box.email || '');
        const kind = box.type === 'shared' ? 'shared' : 'personal';
        return email ? `${name} — ${email} (${kind})` : `${name} (${kind})`;
    }
    function fillMessageMailboxes(isMail) {
        const wrap = document.getElementById('leadMessageMailboxWrap');
        const sel = document.getElementById('leadMessageMailbox');
        if (!wrap || !sel) return;
        const boxes = isMail ? (currentMessageChannel()?.mailboxes || []) : [];
        const previous = sel.value;
        sel.innerHTML = boxes.length
            ? boxes.map(box => `<option value="${box.id}">${esc(mailboxOptionLabel(box))}</option>`).join('')
            : '<option value="">No connected mailbox</option>';
        const shared = boxes.find(box => box.type === 'shared');
        if (previous && boxes.some(box => String(box.id) === String(previous))) sel.value = previous;
        else if (shared) sel.value = String(shared.id);
        else if (boxes[0]) sel.value = String(boxes[0].id);
        wrap.hidden = !isMail;
    }
    function fillMessageRecipients(isMail) {
        const wrap = document.getElementById('leadMessageToWrap');
        const list = document.getElementById('leadMessageTo');
        if (!wrap || !list) return;
        const emails = isMail ? (currentMessageChannel()?.emails || []) : [];
        const previous = new Set(getSelectedToEmails().map((email) => email.toLowerCase()));
        const selectAll = previous.size === 0;
        list.innerHTML = emails.length
            ? emails.map((email) => {
                const checked = selectAll || previous.has(String(email).toLowerCase()) ? 'checked' : '';
                return `<label class="lead-message-to-item"><input type="checkbox" value="${esc(email)}" ${checked}> <span>${esc(email)}</span></label>`;
            }).join('')
            : '<p class="chp-empty">No email addresses on this lead.</p>';
        wrap.hidden = !isMail;
    }
    function getSelectedToEmails() {
        return [...document.querySelectorAll('#leadMessageTo input[type="checkbox"]:checked')].map((el) => el.value);
    }
    function fillMessageTemplates() {
        const sel = document.getElementById('leadMessageTemplate');
        const ch = currentMessageChannel();
        const templates = ch?.templates || [];
        sel.innerHTML = `<option value="">Custom message</option>` + templates.map(t =>
            `<option value="${t.id}">${esc(t.name)}</option>`
        ).join('');
        syncFollowUpComposer(ch?.id === 'inbox');
        applyMessageTemplate();
    }
    function applyMessageTemplate() {
        const ch = currentMessageChannel();
        const id = document.getElementById('leadMessageTemplate').value;
        const tpl = (ch?.templates || []).find(t => String(t.id) === String(id));
        if (tpl) {
            setFollowUpBody(tpl.body || '');
            if (tpl.subject) document.getElementById('leadMessageSubject').value = tpl.subject;
        }
    }
    document.getElementById('leadMessageChannels')?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-channel]');
        if (!btn || btn.disabled) return;
        state.messageChannel = btn.dataset.channel;
        renderMessageChannels();
    });
    document.getElementById('leadMessageTemplate')?.addEventListener('change', applyMessageTemplate);
    document.getElementById('leadMessageHtmlEditor')?.addEventListener('mousedown', (e) => {
        if (e.target.closest('button')) e.preventDefault();
    });
    document.getElementById('leadMessageHtmlEditor')?.addEventListener('click', (e) => {
        const modeBtn = e.target.closest('[data-html-mode]');
        if (modeBtn) {
            e.preventDefault();
            setMailEditorMode(modeBtn.dataset.htmlMode);
            return;
        }
        const cmdBtn = e.target.closest('[data-cmd]');
        if (!cmdBtn) return;
        e.preventDefault();
        const ed = getMailHtmlEditor();
        if (ed.source && !ed.source.hidden) {
            alert('Switch to Visual to use formatting, or edit the HTML directly.');
            return;
        }
        ed.visual?.focus();
        const cmd = cmdBtn.dataset.cmd;
        if (cmd === 'createLink') {
            const url = prompt('Link URL', 'https://');
            if (url) document.execCommand('createLink', false, url);
        } else {
            document.execCommand(cmd, false, null);
        }
        if (ed.source) ed.source.value = sanitizeFollowUpHtml(ed.visual?.innerHTML || '');
    });
    document.getElementById('leadMessageHtmlVisual')?.addEventListener('input', () => {
        const ed = getMailHtmlEditor();
        if (ed.source) ed.source.value = sanitizeFollowUpHtml(ed.visual?.innerHTML || '');
    });
    document.getElementById('closeLeadMessageModal')?.addEventListener('click', closeMessageModal);
    document.getElementById('cancelLeadMessageBtn')?.addEventListener('click', closeMessageModal);
    document.getElementById('sendLeadMessageBtn')?.addEventListener('click', async () => {
        const err = document.getElementById('leadMessageError');
        const btn = document.getElementById('sendLeadMessageBtn');
        if (!state.messageChannel) {
            if (err) { err.hidden = false; err.textContent = 'Choose a channel.'; }
            return;
        }
        if (isMailFollowUp() && !document.getElementById('leadMessageMailbox')?.value) {
            if (err) { err.hidden = false; err.textContent = 'Choose a mailbox to send from.'; }
            return;
        }
        if (isMailFollowUp() && getSelectedToEmails().length < 1) {
            if (err) { err.hidden = false; err.textContent = 'Choose at least one recipient.'; }
            return;
        }
        const payload = {
            channel: state.messageChannel,
            template_id: document.getElementById('leadMessageTemplate').value ? Number(document.getElementById('leadMessageTemplate').value) : null,
            body: getFollowUpBody(),
            subject: document.getElementById('leadMessageSubject').value || null,
            inbox_id: isMailFollowUp() && document.getElementById('leadMessageMailbox')?.value
                ? Number(document.getElementById('leadMessageMailbox').value)
                : null,
            to: isMailFollowUp() ? getSelectedToEmails() : null,
        };
        btn.disabled = true;
        try {
            const res = await fetch(api + '/' + state.messageLeadId + '/messages', {
                method: 'POST', credentials: 'same-origin', headers: headers(true),
                body: JSON.stringify(payload),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || 'Could not send.');
            closeMessageModal();
            loadLeads();
        } catch (e) {
            if (err) { err.hidden = false; err.textContent = e.message; }
        } finally {
            btn.disabled = false;
        }
    });
    body.addEventListener('click', (e) => {
        const messageBtn = e.target.closest('[data-message]');
        if (messageBtn) {
            openMessageModal(messageBtn.dataset.message);
            return;
        }
        if (e.target.closest('a, button, input, select, textarea')) {
            return;
        }
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
            if (data.data) upsertLeadRow(data.data);
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

    function collectLeadFormPayload() {
        return {
            title: val('leadTitle') || null,
            first_name: val('leadFirstName'),
            last_name: val('leadLastName'),
            name: [val('leadFirstName'), val('leadLastName')].filter(Boolean).join(' '),
            address: val('leadAddress'),
            city: val('leadCity'),
            postal_code: val('leadPostal') || null,
            primary_phones: readContactRows('primaryPhonesList'),
            primary_emails: readContactRows('primaryEmailsList'),
            phone: (readContactRows('primaryPhonesList')[0] || {}).value || null,
            email: (readContactRows('primaryEmailsList')[0] || {}).value || null,
            company_name: val('leadCompany') || null,
            date_of_birth: val('leadDob') || null,
            alt_title: val('leadAltTitle') || null,
            alt_first_name: val('leadAltFirstName') || null,
            alt_last_name: val('leadAltLastName') || null,
            alt_address: val('leadAltAddress') || null,
            alt_city: val('leadAltCity') || null,
            alt_postal_code: val('leadAltPostal') || null,
            alt_phones: readContactRows('altPhonesList'),
            alt_emails: readContactRows('altEmailsList'),
            alt_phone: (readContactRows('altPhonesList')[0] || {}).value || null,
            alt_email: (readContactRows('altEmailsList')[0] || {}).value || null,
            status: document.getElementById('leadStatus').value,
            source: val('leadSource') || null,
            customer_type: selectedCustomerType() || null,
            residential_type: val('leadResidentialType') || null,
            business_industry: val('leadBusinessIndustry') || null,
            business_industry_other: val('leadBusinessIndustryOther') || null,
            storage_reason: val('leadStorageReason') || null,
            storage_reason_other: val('leadStorageReasonOther') || null,
            storeganise_site_id: document.getElementById('leadStoreganiseSite')?.value || null,
            assigned_to: document.getElementById('leadAssignedTo').value || null,
            facebook_name: document.getElementById('leadFacebook').value.trim() || null,
            instagram_username: document.getElementById('leadInstagram').value.trim() || null,
            inbox_conversation_ids: state.pendingInboxConversations.map(c => c.id),
        };
    }

    async function persistLeadForm(options = {}) {
        const { reloadList = true, useOverlay = true } = options;
        errorEl.hidden = true;
        if (!form.checkValidity()) {
            const invalid = form.querySelector(':invalid');
            showLeadTabForElement(invalid);
            invalid?.focus();
            form.reportValidity();
            return null;
        }
        const payload = collectLeadFormPayload();
        const id = document.getElementById('leadId').value;
        const saveBtn = document.getElementById('saveLeadBtn');
        const busyLabel = id ? 'Saving…' : 'Adding…';
        if (useOverlay) {
            setBusy(saveBtn, true, busyLabel);
            setOverlay('leadModalBusy', true, busyLabel);
        }
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
            upsertLeadRow(data.data);
            if (reloadList) {
                await loadLeads();
            }
            fillForm(data.data);
            if (data.data?.id) {
                const url = new URL(window.location.href);
                url.searchParams.set('lead', data.data.id);
                history.replaceState(null, '', url);
            }
            return data.data;
        } catch (err) {
            errorEl.hidden = false;
            errorEl.textContent = err.message;
            return null;
        } finally {
            if (useOverlay) {
                setOverlay('leadModalBusy', false);
                setBusy(saveBtn, false);
            }
        }
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        await persistLeadForm();
    });

    document.getElementById('deleteLeadBtn').addEventListener('click', async () => {
        const id = document.getElementById('leadId').value;
        if (!id || !confirm('Delete this lead? Channel conversations stay, but this identity will be removed.')) return;
        const deleteBtn = document.getElementById('deleteLeadBtn');
        setBusy(deleteBtn, true, 'Deleting…');
        setOverlay('leadModalBusy', true, 'Deleting…');
        try {
            const res = await fetch(api + '/' + id, { method: 'DELETE', credentials: 'same-origin', headers: headers() });
            if (!res.ok) {
                const data = await res.json().catch(() => ({}));
                throw new Error(data.message || 'Could not delete lead.');
            }
            removeLeadRow(id);
            closeModal();
            await loadLeads();
        } catch (err) {
            alert(err.message);
        } finally {
            setOverlay('leadModalBusy', false);
            setBusy(deleteBtn, false);
        }
    });

    async function addLeadNote() {
        const id = state.editingId;
        const input = document.getElementById('leadNoteInput');
        const text = input.value.trim();
        if (!id) return;
        if (!text) { input.focus(); return; }
        const noteBtn = document.getElementById('addLeadNoteBtn');
        setBusy(noteBtn, true, 'Adding…');
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
            await loadLeads();
        } catch (err) {
            errorEl.hidden = false;
            errorEl.textContent = err.message;
        } finally {
            setBusy(noteBtn, false);
        }
    }

    async function addLeadLabel() {
        const id = state.editingId;
        const select = document.getElementById('leadLabelSelect');
        const name = (select?.value || '').trim();
        const busy = document.getElementById('leadLabelBusy');
        if (!id || !select) return;
        if (!name) return;
        if (state.labels.some(label => label.name.toLowerCase() === name.toLowerCase())) {
            select.value = '';
            return;
        }
        select.disabled = true;
        if (busy) busy.hidden = false;
        try {
            const res = await fetch(api + '/' + id + '/labels', {
                method: 'POST',
                credentials: 'same-origin',
                headers: headers(true),
                body: JSON.stringify({ name }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || 'Could not add label.');
            select.value = '';
            renderLabels(data.labels || []);
            refreshActivities(id);
            if (data.data && !state.companyLabels.some(label => label.id === data.data.id)) {
                state.companyLabels.push(data.data);
            }
            renderLabelSuggestions();
            await loadLeads();
        } catch (err) {
            errorEl.hidden = false;
            errorEl.textContent = err.message;
            renderLabelSuggestions();
        } finally {
            if (busy) busy.hidden = true;
        }
    }

    document.getElementById('addLeadNoteBtn').addEventListener('click', addLeadNote);
    document.getElementById('leadLabelSelect').addEventListener('change', addLeadLabel);
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
        setBusy(btn, true, 'Deleting…');
        try {
            const res = await fetch(api + '/' + state.editingId + '/notes/' + noteId, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: headers(),
            });
            if (!res.ok) {
                const data = await res.json().catch(() => ({}));
                throw new Error(data.message || 'Could not delete note.');
            }
            renderNotes(state.notes.filter(note => String(note.id) !== String(noteId)));
            refreshActivities(state.editingId);
            await loadLeads();
        } catch (err) {
            alert(err.message);
            setBusy(btn, false);
        }
    });
    document.getElementById('leadLabelsList').addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-remove-label]');
        if (!btn || !state.editingId) return;
        const labelId = btn.dataset.removeLabel;
        setBusy(btn, true, 'Deleting…');
        try {
            const res = await fetch(api + '/' + state.editingId + '/labels/' + labelId, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: headers(),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || 'Could not remove label.');
            renderLabels(data.labels || state.labels.filter(label => String(label.id) !== String(labelId)));
            refreshActivities(state.editingId);
            await loadLeads();
        } catch (err) {
            alert(err.message);
            setBusy(btn, false);
        }
    });

    const RULE_TRIGGERS = [
        { value: 'inbound_message', label: 'Inbound message is received', help: 'Any inbound Inbox, Viber, WhatsApp, Facebook, or SMS message.' },
        { value: 'inbound_message_new', label: 'Inbound message is received (new conversation)', help: 'Only the first inbound message that starts a conversation.' },
        { value: 'outbound_message_new', label: 'Outbound message is sent (new conversation)', help: 'When you start a new Inbox, Viber, WhatsApp, Facebook, or SMS thread.' },
        { value: 'outbound_reply', label: 'Outbound reply is sent', help: 'When a reply is sent on an existing conversation.' },
        { value: 'outbound_email_synced', label: 'Outbound email synced from Sent', help: 'When an email sent outside this CRM (for example from Outlook or an external automation) is imported from the shared inbox Sent folder. Only mail from the last 3 days is considered.' },
        { value: 'inbound_call', label: 'Inbound call is received', help: 'When a phone call comes in.' },
        { value: 'outbound_call', label: 'Outbound call is placed', help: 'When an outbound phone call is placed.' },
        { value: 'lead_assigned', label: 'Lead is assigned', help: 'When a teammate is assigned to the lead.' },
        { value: 'lead_labeled', label: 'Label added', help: 'When this label is added to the lead.' },
        { value: 'lead_status_changed', label: 'Status changed', help: 'When the lead status changes to this status. Delayed actions, like set status after X days, start counting from this change date.' },
        { value: 'lead_note_added', label: 'Note is added to lead', help: 'When a note is saved on the lead.' },
        { value: 'lead_age_reached', label: 'Lead age is reached', help: 'Checked once a day, based on how many days since the lead was created. Channel and shared inbox filters match the lead’s linked email threads (this trigger has no message channel of its own).' },
    ];
    const RULE_CHANNELS = [
        ['phone', 'Phone'],
        ['inbox', 'Inbox'],
        ['viber', 'Viber'],
        ['whatsapp', 'WhatsApp'],
        ['facebook', 'Facebook'],
        ['sms', 'SMS'],
    ];

    const rulesModal = document.getElementById('leadRulesModal');
    function showRuleList() {
        state.editingRuleId = null;
        const listView = document.getElementById('leadRuleListView');
        const builder = document.getElementById('leadRuleBuilder');
        const help = document.getElementById('leadRulesHelp');
        const title = document.getElementById('leadRulesModalTitle');
        const saveBtn = document.getElementById('saveLeadRuleBtn');
        const newBtn = document.getElementById('newLeadRuleBtn');
        const cancelBtn = document.getElementById('cancelLeadRuleBtn');
        if (listView) listView.hidden = false;
        if (builder) builder.hidden = true;
        if (help) help.hidden = false;
        if (title) title.textContent = 'Lead rules';
        if (saveBtn) saveBtn.hidden = true;
        if (newBtn) newBtn.hidden = !state.canManageRules;
        if (cancelBtn) cancelBtn.textContent = 'Close';
        const menu = document.getElementById('leadRuleChannelMenu');
        if (menu) menu.hidden = true;
        const inboxMenu = document.getElementById('leadRuleInboxMenu');
        if (inboxMenu) inboxMenu.hidden = true;
        renderRuleList();
    }
    function showRuleEditor() {
        const listView = document.getElementById('leadRuleListView');
        const builder = document.getElementById('leadRuleBuilder');
        const help = document.getElementById('leadRulesHelp');
        const title = document.getElementById('leadRulesModalTitle');
        const saveBtn = document.getElementById('saveLeadRuleBtn');
        const newBtn = document.getElementById('newLeadRuleBtn');
        const cancelBtn = document.getElementById('cancelLeadRuleBtn');
        if (listView) listView.hidden = true;
        if (builder) builder.hidden = false;
        if (help) help.hidden = true;
        if (title) title.textContent = state.editingRuleId ? 'Edit rule' : 'New rule';
        if (saveBtn) {
            saveBtn.hidden = !state.canManageRules;
            saveBtn.textContent = state.editingRuleId ? 'Save changes' : 'Create rule';
        }
        if (newBtn) newBtn.hidden = true;
        if (cancelBtn) cancelBtn.textContent = 'Back';
        renderRuleChannelPicker();
        renderRuleInboxPicker();
    }
    function openRulesModal() {
        rulesModal.classList.add('open');
        showRuleList();
        renderRuleChannelPicker();
        renderRuleInboxPicker();
    }
    function closeRulesModal() {
        rulesModal.classList.remove('open');
        state.editingRuleId = null;
        const menu = document.getElementById('leadRuleChannelMenu');
        if (menu) menu.hidden = true;
        const inboxMenu = document.getElementById('leadRuleInboxMenu');
        if (inboxMenu) inboxMenu.hidden = true;
    }
    async function loadRules() {
        const q = new URLSearchParams({ page: String(state.rulesPage || 1), per_page: '10' });
        if (state.rulesSearch) q.set('search', state.rulesSearch);
        const res = await fetch(api + '/rules?' + q.toString(), { credentials: 'same-origin', headers: headers() });
        const data = await res.json().catch(() => ({}));
        const pag = data.pagination || {};
        const lastPage = Math.max(1, Number(pag.last_page) || 1);
        const currentPage = Number(pag.current_page) || 1;
        if (currentPage > lastPage && (Number(pag.total) || 0) > 0) {
            state.rulesPage = lastPage;
            return loadRules();
        }
        state.rules = data.data || [];
        state.rulesPage = currentPage;
        state.rulesLastPage = lastPage;
        state.rulesTotal = Number(pag.total) || 0;
        if (data.meta && typeof data.meta.can_manage === 'boolean') state.canManageRules = data.meta.can_manage;
        state.inboxes = Array.isArray(data.meta?.inboxes) ? data.meta.inboxes : [];
        state.emailTemplates = Array.isArray(data.meta?.email_templates) ? data.meta.email_templates : [];
        renderRuleInboxPicker();
        if (!document.getElementById('leadRuleListView')?.hidden) showRuleList();
    }
    function renderRuleList() {
        const list = document.getElementById('leadRuleList');
        if (!list) return;
        if (!state.rules.length) {
            list.innerHTML = `<div class="chp-empty">${state.rulesSearch ? 'No rules match this search.' : 'No rules yet.'}</div>`;
        } else {
            list.innerHTML = state.rules.map(rule => {
                const when = rule.last_applied_at ? esc(formatAt(rule.last_applied_at)) : '';
                let lastApplied = 'Last applied never';
                if (rule.last_applied_at && rule.last_applied_lead_id) {
                    lastApplied = `<a href="${esc('/leads?lead=' + rule.last_applied_lead_id)}" data-open-last-applied-lead="${esc(String(rule.last_applied_lead_id))}" title="Open the lead this rule last ran on">Last applied ${when}</a>`;
                } else if (rule.last_applied_at && rule.last_applied_inbox_conversation_id) {
                    lastApplied = `<a href="${esc('/inbox?conversation=' + rule.last_applied_inbox_conversation_id)}" title="Open the inbox thread this rule last ran on">Last applied ${when}</a>`;
                } else if (rule.last_applied_at) {
                    lastApplied = `Last applied ${when}`;
                }
                return `
                <div class="leads-rule-row" title="${esc(rule.name)}">
                    <div class="leads-rule-row-main">
                        <div class="leads-rule-row-name">${esc(rule.name)}</div>
                        <div class="leads-rule-row-meta">${rule.is_active ? 'On' : 'Off'} · ${lastApplied}</div>
                    </div>
                    ${state.canManageRules ? `
                        <div class="leads-rule-row-actions">
                            <button type="button" class="btn btn-secondary btn-sm" data-edit-lead-rule="${rule.id}">Edit</button>
                            <button type="button" class="btn btn-secondary btn-sm" data-toggle-lead-rule="${rule.id}">${rule.is_active ? 'On' : 'Off'}</button>
                            <button type="button" class="btn btn-secondary btn-sm" data-delete-lead-rule="${rule.id}">Delete</button>
                        </div>
                    ` : ''}
                </div>
            `;
            }).join('');
        }
        const info = document.getElementById('leadRulesPageInfo');
        const prev = document.getElementById('leadRulesPrev');
        const next = document.getElementById('leadRulesNext');
        if (info) info.textContent = `Showing page ${state.rulesPage || 1} of ${state.rulesLastPage || 1} (${state.rulesTotal || 0} rules)`;
        if (prev) prev.disabled = (state.rulesPage || 1) <= 1;
        if (next) next.disabled = (state.rulesPage || 1) >= (state.rulesLastPage || 1);
    }
    function selectedRuleChannels() {
        return [...document.querySelectorAll('#leadRuleChannelMenu input[type="checkbox"]:checked')]
            .map(cb => cb.value)
            .filter(Boolean);
    }
    function updateRuleChannelLabel() {
        const ids = selectedRuleChannels();
        const label = document.getElementById('leadRuleChannelToggleLabel');
        if (!label) return;
        if (!ids.length) {
            label.textContent = 'All channels';
            return;
        }
        const names = RULE_CHANNELS.filter(([id]) => ids.includes(id)).map(([, name]) => name);
        label.textContent = names.length <= 2 ? names.join(', ') : `${names.length} channels selected`;
    }
    function renderRuleChannelPicker() {
        const menu = document.getElementById('leadRuleChannelMenu');
        if (!menu) return;
        const prev = new Set(selectedRuleChannels());
        menu.innerHTML = RULE_CHANNELS.map(([id, name]) => `
            <label class="leads-rule-channel-option">
                <input type="checkbox" value="${id}" ${prev.has(id) ? 'checked' : ''}>
                <span>${esc(name)}</span>
            </label>
        `).join('');
        updateRuleChannelLabel();
    }
    function selectedRuleInboxes() {
        return [...document.querySelectorAll('#leadRuleInboxMenu input[type="checkbox"]:checked')]
            .map(cb => Number(cb.value))
            .filter(id => id > 0);
    }
    function inboxDisplayName(inbox) {
        return inbox?.name || inbox?.email || ('Inbox #' + inbox?.id);
    }
    function updateRuleInboxLabel() {
        const ids = selectedRuleInboxes().map(String);
        const label = document.getElementById('leadRuleInboxToggleLabel');
        if (!label) return;
        if (!ids.length) {
            label.textContent = 'All shared inboxes';
            return;
        }
        const names = (state.inboxes || []).filter(inbox => ids.includes(String(inbox.id))).map(inboxDisplayName);
        label.textContent = names.length <= 2 ? names.join(', ') : `${names.length} inboxes selected`;
    }
    function renderRuleInboxPicker() {
        const menu = document.getElementById('leadRuleInboxMenu');
        if (!menu) return;
        const prev = new Set(selectedRuleInboxes().map(String));
        const inboxes = state.inboxes || [];
        menu.innerHTML = inboxes.length
            ? inboxes.map(inbox => `
                <label class="leads-rule-channel-option">
                    <input type="checkbox" value="${inbox.id}" ${prev.has(String(inbox.id)) ? 'checked' : ''}>
                    <span>${esc(inboxDisplayName(inbox))}</span>
                </label>
            `).join('')
            : '<div class="chp-empty" style="padding:0.45rem;">No shared inboxes yet.</div>';
        updateRuleInboxLabel();
    }
    function triggerOptions(selected = 'inbound_message') {
        return RULE_TRIGGERS.map(t =>
            `<option value="${t.value}" ${t.value === selected ? 'selected' : ''}>${esc(t.label)}</option>`
        ).join('');
    }
    function triggerHelp(value) {
        return RULE_TRIGGERS.find(t => t.value === value)?.help || '';
    }
    function conditionFieldOptions(selected = 'contact_name') {
        const fields = [
            ['contact_name', 'Contact name'],
            ['phone', 'Phone'],
            ['email', 'Email'],
            ['subject', 'Subject'],
            ['message', 'Message'],
            ['lead_status', 'Lead status'],
            ['status_changed', 'Status changed'],
            ['lead_label', 'Lead label'],
            ['label_added', 'Label added'],
            ['lead_age', 'Lead age'],
        ];
        return fields.map(([value, label]) =>
            `<option value="${value}" ${value === selected ? 'selected' : ''}>${label}</option>`
        ).join('');
    }
    function conditionOperatorOptions(field, selected = '') {
        if (field === 'lead_label') {
            const current = selected === 'does_not_have' || selected === 'not_equals' ? 'does_not_have' : 'equals';
            return [['equals', 'has'], ['does_not_have', "doesn't have"]]
                .map(([value, label]) => `<option value="${value}" ${current === value ? 'selected' : ''}>${label}</option>`)
                .join('');
        }
        if (field === 'lead_age') {
            return [['greater_than', 'greater than or equal to'], ['less_than', 'less than or equal to'], ['equals', 'equal to']]
                .map(([value, label]) => `<option value="${value}" ${value === selected ? 'selected' : ''}>${label}</option>`)
                .join('');
        }
        return [['contains', 'contains'], ['equals', 'equals'], ['starts_with', 'starts with']]
            .map(([value, label]) => `<option value="${value}" ${value === selected ? 'selected' : ''}>${label}</option>`)
            .join('');
    }
    function conditionValueControl(field, selected = '') {
        if (field === 'lead_status' || field === 'status_changed') {
            return `<select data-rule-cond-value>${statusOptions(selected)}</select>`;
        }
        if (field === 'lead_label') {
            return labelMultiSelectHtml(selected, 'data-rule-cond-value');
        }
        if (field === 'label_added') {
            return `<select data-rule-cond-value>${(state.companyLabels || []).map(l =>
                `<option value="${l.id}" ${String(selected) === String(l.id) || String(selected) === String(l.name) ? 'selected' : ''}>${esc(l.name)}</option>`
            ).join('') || '<option value="">No labels</option>'}</select>`;
        }
        if (field === 'lead_age') {
            return `<input type="number" data-rule-cond-value min="0" max="3650" step="1" placeholder="Days" value="${esc(selected || '')}">`;
        }
        return `<input type="text" data-rule-cond-value placeholder="Value" value="${esc(selected || '')}">`;
    }
    function selectedLabelValues(value) {
        if (Array.isArray(value)) {
            return value.map(String).filter(v => v !== '');
        }
        if (value == null || value === '') {
            return [];
        }
        return [String(value)];
    }
    function labelMultiSelectHtml(selected = [], dataAttr = 'data-rule-cond-value') {
        const chosen = new Set(selectedLabelValues(selected));
        const labels = state.companyLabels || [];
        if (!labels.length) {
            return `<div class="leads-rule-label-multi" ${dataAttr}><p class="leads-rule-rr-help">No labels yet.</p></div>`;
        }
        return `
            <div class="leads-rule-label-multi" ${dataAttr}>
                ${labels.map(l => `
                    <label>
                        <input type="checkbox" value="${l.id}" ${chosen.has(String(l.id)) || chosen.has(String(l.name)) ? 'checked' : ''}>
                        <span>${esc(l.name)}</span>
                    </label>
                `).join('')}
            </div>
        `;
    }
    function collectLabelMultiValues(root) {
        if (!root) return [];
        if (root.matches?.('select, input:not([type="checkbox"])')) {
            const value = root.value?.trim() || '';
            return value ? [value] : [];
        }
        return [...root.querySelectorAll('input[type="checkbox"]:checked')]
            .map(cb => cb.value)
            .filter(v => v !== '');
    }
    function actionNeedsValue(type) {
        return !['create_lead', 'notify_assignee', 'unsnooze', 'attach_shared_inbox', 'reopen_email_thread'].includes(type);
    }
    function actionTypeOptions(selected = 'assign') {
        return [
            ['create_lead', 'Create a lead'],
            ['attach_shared_inbox', 'Attach shared inbox thread'],
            ['assign', 'Assign lead to'],
            ['add_label', 'Add label'],
            ['set_status', 'Set status'],
            ['set_status_after_days', 'Set status after days'],
            ['reopen_after_days', 'Reopen after days'],
            ['reopen_email_thread', 'Reopen email thread'],
            ['unsnooze', 'Unsnooze lead'],
            ['notify_assignee', 'Notify assignee'],
            ['send_email', 'Send email'],
        ].map(([value, label]) =>
            `<option value="${value}" ${value === selected ? 'selected' : ''}>${label}</option>`
        ).join('');
    }
    function actionValueOptions(type, selected = '') {
        if (type === 'assign') {
            const current = String(selected || '');
            const users = (state.assignees || []).map(m =>
                `<option value="${m.id}" ${current === String(m.id) ? 'selected' : ''}>${esc(m.name)}</option>`
            ).join('');
            return `
                <optgroup label="All teammates">
                    <option value="__round_robin__" ${current === '__round_robin__' ? 'selected' : ''}>Round robin (all teammates)</option>
                    <option value="__round_robin_selected__" ${current === '__round_robin_selected__' ? 'selected' : ''}>Round robin among selected teammates</option>
                </optgroup>
                <optgroup label="Available for inbound calls">
                    <option value="__available_round_robin__" ${current === '__available_round_robin__' ? 'selected' : ''}>Round robin among available agents</option>
                    <option value="__available__" ${current === '__available__' ? 'selected' : ''}>Any available agent</option>
                </optgroup>
                <optgroup label="Teammate">${users || '<option value="" disabled>No teammates</option>'}</optgroup>
            `;
        }
        if (type === 'add_label') {
            return '<option value="">Select labels below</option>';
        }
        if (type === 'set_status') {
            const selectedStatus = selected || 'contacted';
            return (state.statuses || []).filter(s => s.slug !== 'snoozed').map(s =>
                `<option value="${esc(s.slug)}" ${selectedStatus === s.slug ? 'selected' : ''}>${esc(s.name)}</option>`
            ).join('');
        }
        if (type === 'reopen_after_days' || type === 'set_status_after_days') {
            const days = [1, 2, 3, 5, 7, 14, 30, 60, 90];
            const selectedDay = String(selected || '3');
            const opts = days.map(d =>
                `<option value="${d}" ${selectedDay === String(d) ? 'selected' : ''}>${d} day${d === 1 ? '' : 's'}</option>`
            ).join('');
            return opts + (days.includes(Number(selectedDay)) ? '' : `<option value="${esc(selectedDay)}" selected>${esc(selectedDay)} days</option>`);
        }
        if (type === 'send_email') {
            const templates = state.emailTemplates || [];
            const current = String(selected || '');
            const opts = templates.map(t =>
                `<option value="${t.id}" ${current === String(t.id) ? 'selected' : ''}>${esc(t.name)}</option>`
            ).join('');
            return opts || '<option value="">No email templates yet</option>';
        }
        return '<option value="">—</option>';
    }
    function sendEmailTemplateId(value) {
        if (value && typeof value === 'object' && !Array.isArray(value) && value.template_id != null) {
            return String(value.template_id);
        }
        return '';
    }
    function sendEmailDays(value) {
        if (value && typeof value === 'object' && !Array.isArray(value) && value.days != null && value.days !== '') {
            return String(value.days);
        }
        return '0';
    }
    function sendEmailMailboxId(value) {
        if (value && typeof value === 'object' && !Array.isArray(value) && value.mailbox_id != null && value.mailbox_id !== '') {
            return String(value.mailbox_id);
        }
        return '';
    }
    function sendEmailExtraHtml(preset = {}) {
        const days = sendEmailDays(preset.value);
        const mailboxId = sendEmailMailboxId(preset.value);
        const mailboxes = state.inboxes || [];
        const mailboxOpts = `<option value="">Default mailbox</option>` + mailboxes.map(m =>
            `<option value="${m.id}" ${String(mailboxId) === String(m.id) ? 'selected' : ''}>${esc(inboxDisplayName(m))}</option>`
        ).join('');
        return `
            <div class="leads-rule-send-email" data-send-email-fields>
                <label class="leads-rule-send-email-days" title="Days after the trigger to send this email (0 = immediately)">
                    <input type="number" data-rule-action-days min="0" max="365" step="1" value="${esc(days)}" placeholder="0">
                    <span>days</span>
                </label>
                <select data-rule-action-mailbox>${mailboxOpts}</select>
            </div>
        `;
    }
    function triggerLabelOptions(selected = '') {
        const labels = state.companyLabels || [];
        const opts = labels.map(l =>
            `<option value="${l.id}" ${String(selected) === String(l.id) || String(selected) === String(l.name) ? 'selected' : ''}>${esc(l.name)}</option>`
        ).join('');
        return `<option value="">Select label…</option>` + (opts || '<option value="" disabled>No labels yet</option>');
    }
    function triggerStatusOptions(selected = '') {
        return `<option value="">Select status…</option>` + (state.statuses || []).map(s =>
            `<option value="${esc(s.slug)}" ${selected === s.slug ? 'selected' : ''}>${esc(s.name)}</option>`
        ).join('');
    }
    function triggerExtraKind(type) {
        if (type === 'lead_labeled') return 'label';
        if (type === 'lead_status_changed') return 'status';
        if (type === 'lead_age_reached') return 'age';
        return '';
    }
    function triggerExtraOptions(type, selected = '') {
        if (type === 'lead_labeled') return triggerLabelOptions(selected);
        if (type === 'lead_status_changed') return triggerStatusOptions(selected);
        return '<option value="">—</option>';
    }
    function triggerAgeOperatorOptions(selected = 'equals') {
        return [['greater_than', 'greater than or equal to'], ['less_than', 'less than or equal to'], ['equals', 'equal to']]
            .map(([value, label]) => `<option value="${value}" ${value === selected ? 'selected' : ''}>${label}</option>`)
            .join('');
    }
    function triggerExtraFieldsHtml(type, preset = {}) {
        const kind = triggerExtraKind(type);
        if (kind === 'age') {
            return `<select data-rule-trigger-op>${triggerAgeOperatorOptions(preset.operator || 'equals')}</select>`
                + `<input type="number" data-rule-trigger-label min="0" max="3650" step="1" placeholder="Days" value="${esc(preset.day || '')}">`;
        }
        return `<select data-rule-trigger-label ${kind ? '' : 'hidden disabled'}>${triggerExtraOptions(type, preset.label || preset.status || preset.day || '')}</select>`;
    }
    function syncTriggerLabelSelect(row) {
        const type = row?.querySelector('[data-rule-trigger]')?.value;
        const fields = row?.querySelector('.leads-rule-trigger-fields');
        if (!fields) return;
        const kind = triggerExtraKind(type);
        fields.classList.toggle('has-label', !!kind && kind !== 'age');
        fields.classList.toggle('has-age', kind === 'age');
        fields.querySelectorAll('[data-rule-trigger-label], [data-rule-trigger-op]').forEach(el => el.remove());
        fields.insertAdjacentHTML('beforeend', triggerExtraFieldsHtml(type, {}));
    }
    function addRuleTriggerRow(preset = {}) {
        const wrap = document.getElementById('leadRuleTriggers');
        if (!wrap) return;
        const value = preset.value || 'inbound_message';
        const kind = triggerExtraKind(value);
        const row = document.createElement('div');
        row.className = 'leads-rule-extra-card is-trigger';
        row.innerHTML = `
            <div>
                <div class="leads-rule-trigger-fields${kind === 'age' ? ' has-age' : (kind ? ' has-label' : '')}">
                    <select data-rule-trigger>${triggerOptions(value)}</select>
                    ${triggerExtraFieldsHtml(value, preset)}
                </div>
                <p class="leads-rule-trigger-help">${esc(triggerHelp(value))}</p>
            </div>
            <button type="button" class="leads-rule-remove" data-remove-rule-row title="Remove">×</button>
        `;
        wrap.appendChild(row);
    }
    function addRuleConditionRow(preset = {}) {
        const wrap = document.getElementById('leadRuleConditions');
        if (!wrap) return;
        const field = preset.field || 'contact_name';
        const row = document.createElement('div');
        row.className = 'leads-rule-extra-card' + (field === 'lead_label' ? ' is-multi-label' : '');
        row.innerHTML = `
            <select data-rule-cond-field>${conditionFieldOptions(field)}</select>
            <select data-rule-cond-operator>${conditionOperatorOptions(field, preset.operator || (field === 'lead_label' || field === 'lead_age' ? 'equals' : 'contains'))}</select>
            ${conditionValueControl(field, preset.value || '')}
            <button type="button" class="leads-rule-remove" data-remove-rule-row title="Remove">×</button>
        `;
        wrap.appendChild(row);
    }
    function assignMode(value) {
        if (value && typeof value === 'object' && !Array.isArray(value)) {
            return String(value.mode || '');
        }
        return String(value || '');
    }
    function delayedStatusDays(value) {
        if (value && typeof value === 'object' && !Array.isArray(value) && value.days != null && value.days !== '') {
            return String(value.days);
        }
        if (value != null && value !== '' && typeof value !== 'object') {
            return String(value);
        }
        return '3';
    }
    function delayedStatusSlug(value) {
        if (value && typeof value === 'object' && !Array.isArray(value)) {
            return String(value.status || 'contacted');
        }
        return 'contacted';
    }
    function delayedStatusSelectHtml(selected = 'contacted') {
        return `<select data-rule-action-status>${actionValueOptions('set_status', selected || 'contacted')}</select>`;
    }
    function selectedRoundRobinIds(value) {
        if (value && typeof value === 'object' && Array.isArray(value.user_ids)) {
            return value.user_ids.map(String);
        }
        return [];
    }
    function roundRobinUsersHtml(selectedIds = []) {
        const chosen = new Set((selectedIds || []).map(String));
        const users = state.assignees || [];
        if (!users.length) {
            return '<p class="leads-rule-rr-help">No teammates to select.</p>';
        }
        return `
            <div class="leads-rule-rr-users" data-rr-users>
                ${users.map(m => `
                    <label>
                        <input type="checkbox" value="${m.id}" ${chosen.has(String(m.id)) ? 'checked' : ''}>
                        <span>${esc(m.name)}</span>
                    </label>
                `).join('')}
            </div>
            <p class="leads-rule-rr-help">Leads rotate through the teammates you check, whether or not they are available for inbound calls.</p>
        `;
    }
    function syncAssignTeammatePicker(row, preset = {}) {
        if (!row) return;
        row.querySelector('[data-rr-users]')?.remove();
        row.querySelector('.leads-rule-rr-help')?.remove();
        const type = row.querySelector('[data-rule-action-type]')?.value;
        const mode = row.querySelector('[data-rule-action-value]')?.value;
        const selected = type === 'assign' && mode === '__round_robin_selected__';
        row.classList.toggle('is-rr-selected', selected);
        if (selected) {
            row.insertAdjacentHTML('beforeend', roundRobinUsersHtml(selectedRoundRobinIds(preset.value)));
        }
    }
    function actionKeywordValues(preset = {}) {
        const value = (preset.value && typeof preset.value === 'object' && !Array.isArray(preset.value))
            ? preset.value
            : {};
        return {
            name: value.name || value.name_keyword || 'Name',
            phone: value.phone || value.phone_keyword || 'Phone',
            email: value.email || value.email_keyword || 'Email',
        };
    }
    function createLeadKeywordsHtml(preset = {}) {
        const kw = actionKeywordValues(preset);
        return `
            <div class="leads-rule-create-keywords" data-create-lead-keywords>
                <label>Name keyword<input type="text" data-lead-keyword="name" placeholder="Name" maxlength="80" value="${esc(kw.name)}"></label>
                <label>Phone keyword<input type="text" data-lead-keyword="phone" placeholder="Phone" maxlength="80" value="${esc(kw.phone)}"></label>
                <label>Email keyword<input type="text" data-lead-keyword="email" placeholder="Email" maxlength="80" value="${esc(kw.email)}"></label>
            </div>
            <p class="leads-rule-create-help">Read these labels from the message or email body, e.g. Name: Jane Doe. Comma-separate aliases like Full name, Name.</p>
        `;
    }
    function syncActionRow(row, type, preset = {}) {
        if (!row) return;
        row.classList.toggle('is-create-lead', type === 'create_lead');
        row.classList.toggle('is-add-label', type === 'add_label');
        row.classList.toggle('is-delayed-status', type === 'set_status_after_days');
        row.classList.toggle('is-send-email', type === 'send_email');
        const valueSel = row.querySelector('[data-rule-action-value]');
        const needsValue = actionNeedsValue(type);
        if (valueSel) {
            valueSel.hidden = !needsValue || type === 'add_label';
            valueSel.disabled = !needsValue || type === 'add_label';
            if (needsValue && type !== 'add_label') {
                let fallback = '';
                if (type === 'set_status') fallback = 'contacted';
                if (type === 'reopen_after_days' || type === 'set_status_after_days') fallback = '3';
                const selected = type === 'set_status_after_days'
                    ? delayedStatusDays(preset.value)
                    : (type === 'send_email' ? sendEmailTemplateId(preset.value) : (assignMode(preset.value) || fallback));
                valueSel.innerHTML = actionValueOptions(type, selected || fallback);
            }
        }
        row.querySelector('[data-create-lead-keywords]')?.remove();
        row.querySelector('.leads-rule-create-help')?.remove();
        row.querySelector('[data-rule-action-status]')?.remove();
        row.querySelector('.leads-rule-delayed-help')?.remove();
        row.querySelector('[data-send-email-fields]')?.remove();
        row.querySelector('[data-rule-action-labels]')?.remove();
        if (type === 'create_lead') {
            row.insertAdjacentHTML('beforeend', createLeadKeywordsHtml(preset));
        }
        if (type === 'add_label') {
            row.insertAdjacentHTML('beforeend', labelMultiSelectHtml(preset.value || [], 'data-rule-action-labels'));
            row.insertAdjacentHTML('beforeend', '<p class="leads-rule-delayed-help">Check every label this rule should add. On inbox threads without a lead yet, labels are applied to the email thread and move to the lead when it is saved.</p>');
        }
        if (type === 'set_status_after_days') {
            valueSel?.insertAdjacentHTML('afterend', delayedStatusSelectHtml(delayedStatusSlug(preset.value)));
            row.insertAdjacentHTML('beforeend', '<p class="leads-rule-delayed-help">The countdown starts when this rule’s trigger happens, for example the date the status changed to Qualified.</p>');
        }
        if (type === 'send_email') {
            valueSel?.insertAdjacentHTML('afterend', sendEmailExtraHtml(preset));
            row.insertAdjacentHTML('beforeend', '<p class="leads-rule-delayed-help">Sends the chosen template by email, either immediately or after a delay measured from when this rule’s trigger happens.</p>');
        }
        if (type === 'reopen_email_thread') {
            row.insertAdjacentHTML('beforeend', '<p class="leads-rule-delayed-help">Reopens the matching archived or snoozed email thread, even when it is not saved as a lead yet.</p>');
        }
        syncAssignTeammatePicker(row, preset);
    }
    function addRuleActionRow(preset = {}) {
        const wrap = document.getElementById('leadRuleActions');
        if (!wrap) return;
        const type = preset.type || 'assign';
        const needsValue = actionNeedsValue(type);
        let defaultValue = assignMode(preset.value)
            || ((preset.value && typeof preset.value !== 'object') ? preset.value : '');
        if (!defaultValue && (type === 'reopen_after_days' || type === 'set_status_after_days')) {
            defaultValue = delayedStatusDays(preset.value);
        }
        if (!defaultValue && type === 'send_email') {
            defaultValue = sendEmailTemplateId(preset.value);
        }
        const row = document.createElement('div');
        row.className = 'leads-rule-extra-card is-action'
            + (type === 'create_lead' ? ' is-create-lead' : '')
            + (type === 'add_label' ? ' is-add-label' : '')
            + (type === 'set_status_after_days' ? ' is-delayed-status' : '')
            + (type === 'send_email' ? ' is-send-email' : '');
        row.innerHTML = `
            <select data-rule-action-type>${actionTypeOptions(type)}</select>
            <select data-rule-action-value ${needsValue ? '' : 'disabled hidden'}>${actionValueOptions(type, defaultValue)}</select>
            <button type="button" class="leads-rule-remove" data-remove-rule-row title="Remove">×</button>
        `;
        wrap.appendChild(row);
        syncActionRow(row, type, preset);
    }
    function resetRuleBuilder() {
        state.editingRuleId = null;
        const name = document.getElementById('leadRuleName');
        const stop = document.getElementById('leadRuleStopProcessing');
        if (name) name.value = '';
        if (stop) stop.checked = false;
        document.getElementById('leadRuleTriggers').innerHTML = '';
        document.getElementById('leadRuleConditions').innerHTML = '';
        document.getElementById('leadRuleActions').innerHTML = '';
        renderRuleChannelPicker();
        renderRuleInboxPicker();
        document.querySelectorAll('#leadRuleChannelMenu input[type="checkbox"]').forEach(cb => { cb.checked = false; });
        document.querySelectorAll('#leadRuleInboxMenu input[type="checkbox"]').forEach(cb => { cb.checked = false; });
        updateRuleChannelLabel();
        updateRuleInboxLabel();
        addRuleTriggerRow({ value: 'inbound_message' });
        addRuleActionRow();
    }
    function fillRuleBuilder(rule) {
        state.editingRuleId = rule.id;
        const name = document.getElementById('leadRuleName');
        const stop = document.getElementById('leadRuleStopProcessing');
        if (name) name.value = rule.name || '';
        if (stop) stop.checked = !!rule.stop_processing;
        document.getElementById('leadRuleTriggers').innerHTML = '';
        document.getElementById('leadRuleConditions').innerHTML = '';
        document.getElementById('leadRuleActions').innerHTML = '';
        const conditions = Array.isArray(rule.conditions) ? rule.conditions : [];
        const channel = conditions.find(c => c.field === 'channel');
        const inboxCond = conditions.find(c => c.field === 'shared_inbox' || c.field === 'inbox');
        const addedLabel = conditions.find(c => c.field === 'label_added');
        const changedStatus = conditions.find(c => c.field === 'status_changed');
        renderRuleChannelPicker();
        renderRuleInboxPicker();
        const selected = new Set((Array.isArray(channel?.value) ? channel.value : []).map(String));
        document.querySelectorAll('#leadRuleChannelMenu input[type="checkbox"]').forEach(cb => {
            cb.checked = selected.has(cb.value);
        });
        updateRuleChannelLabel();
        const selectedInboxes = new Set((Array.isArray(inboxCond?.value) ? inboxCond.value : []).map(String));
        document.querySelectorAll('#leadRuleInboxMenu input[type="checkbox"]').forEach(cb => {
            cb.checked = selectedInboxes.has(cb.value);
        });
        updateRuleInboxLabel();
        const triggers = Array.isArray(rule.triggers) ? rule.triggers : [];
        const leadAgeCond = triggers.includes('lead_age_reached') ? conditions.find(c => c.field === 'lead_age') : null;
        if (!triggers.length) addRuleTriggerRow({ value: 'inbound_message' });
        else triggers.forEach(trigger => addRuleTriggerRow({
            value: trigger,
            label: trigger === 'lead_labeled' ? (addedLabel?.value || '') : '',
            status: trigger === 'lead_status_changed' ? (changedStatus?.value || '') : '',
            day: trigger === 'lead_age_reached' ? (leadAgeCond?.value || '') : '',
            operator: trigger === 'lead_age_reached' ? (leadAgeCond?.operator || 'equals') : '',
        }));
        conditions
            .filter(c => c.field && c.field !== 'channel' && c.field !== 'shared_inbox' && c.field !== 'inbox' && c.field !== 'label_added' && c.field !== 'status_changed' && c !== leadAgeCond)
            .forEach(c => addRuleConditionRow(c));
        const actions = Array.isArray(rule.actions) ? rule.actions : [];
        if (!actions.length) addRuleActionRow();
        else actions.forEach(action => addRuleActionRow(action));
    }
    function collectRulePayload() {
        const triggers = [];
        document.querySelectorAll('#leadRuleTriggers [data-rule-trigger]').forEach(sel => {
            if (sel.value && !triggers.includes(sel.value)) triggers.push(sel.value);
        });
        const conditions = [
            { field: 'channel', operator: 'in', value: selectedRuleChannels() },
            { field: 'shared_inbox', operator: 'in', value: selectedRuleInboxes() },
        ];
        document.querySelectorAll('#leadRuleTriggers .leads-rule-extra-card').forEach(row => {
            const sel = row.querySelector('[data-rule-trigger]');
            const extraVal = row.querySelector('[data-rule-trigger-label]')?.value;
            if (sel?.value === 'lead_labeled' && extraVal) {
                conditions.push({ field: 'label_added', operator: 'equals', value: extraVal });
            }
            if (sel?.value === 'lead_status_changed' && extraVal) {
                conditions.push({ field: 'status_changed', operator: 'equals', value: extraVal });
            }
            if (sel?.value === 'lead_age_reached' && extraVal) {
                const ageOp = row.querySelector('[data-rule-trigger-op]')?.value || 'equals';
                conditions.push({ field: 'lead_age', operator: ageOp, value: extraVal });
            }
        });
        document.querySelectorAll('#leadRuleConditions .leads-rule-extra-card').forEach(row => {
            const field = row.querySelector('[data-rule-cond-field]')?.value;
            const operator = row.querySelector('[data-rule-cond-operator]')?.value;
            const valueRoot = row.querySelector('[data-rule-cond-value]');
            if (!field || !operator) return;
            if (field === 'lead_label') {
                const labels = collectLabelMultiValues(valueRoot).map(v => (Number.isFinite(Number(v)) ? Number(v) : v));
                conditions.push({ field, operator, value: labels });
                return;
            }
            const value = valueRoot?.value?.trim() || '';
            conditions.push({ field, operator, value });
        });
        const actions = [];
        document.querySelectorAll('#leadRuleActions .leads-rule-extra-card').forEach(row => {
            const type = row.querySelector('[data-rule-action-type]')?.value;
            if (!type) return;
            if (type === 'create_lead') {
                actions.push({
                    type,
                    value: {
                        name: row.querySelector('[data-lead-keyword="name"]')?.value.trim() || '',
                        phone: row.querySelector('[data-lead-keyword="phone"]')?.value.trim() || '',
                        email: row.querySelector('[data-lead-keyword="email"]')?.value.trim() || '',
                    },
                });
                return;
            }
            if (type === 'add_label') {
                const labels = collectLabelMultiValues(row.querySelector('[data-rule-action-labels]'))
                    .map(v => Number(v))
                    .filter(id => id > 0);
                actions.push({ type, value: labels });
                return;
            }
            const valueSel = row.querySelector('[data-rule-action-value]');
            if (type === 'set_status_after_days') {
                actions.push({
                    type,
                    value: {
                        days: Number(valueSel?.value || 0),
                        status: row.querySelector('[data-rule-action-status]')?.value || '',
                    },
                });
                return;
            }
            if (type === 'assign' && valueSel?.value === '__round_robin_selected__') {
                const userIds = [...row.querySelectorAll('[data-rr-users] input:checked')]
                    .map(cb => Number(cb.value))
                    .filter(id => id > 0);
                actions.push({ type, value: { mode: '__round_robin_selected__', user_ids: userIds } });
                return;
            }
            if (type === 'send_email') {
                const mailboxVal = row.querySelector('[data-rule-action-mailbox]')?.value || '';
                actions.push({
                    type,
                    value: {
                        template_id: Number(valueSel?.value || 0),
                        days: Number(row.querySelector('[data-rule-action-days]')?.value || 0),
                        mailbox_id: mailboxVal ? Number(mailboxVal) : null,
                    },
                });
                return;
            }
            actions.push({ type, value: valueSel && !valueSel.disabled ? (valueSel.value || null) : null });
        });
        return {
            name: document.getElementById('leadRuleName')?.value.trim() || '',
            stop_processing: !!document.getElementById('leadRuleStopProcessing')?.checked,
            triggers,
            conditions,
            actions,
        };
    }

    document.getElementById('leadLabelsBtn')?.addEventListener('click', openLabelsModal);
    document.getElementById('leadStatusesBtn')?.addEventListener('click', openStatusesModal);
    document.getElementById('closeLeadStatusesModal')?.addEventListener('click', closeStatusesModal);
    document.getElementById('closeLeadStatusesBtn')?.addEventListener('click', closeStatusesModal);
    document.getElementById('leadCompanyStatusForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const nameEl = document.getElementById('leadCompanyStatusName');
        const name = nameEl?.value.trim() || '';
        if (!name) { nameEl?.focus(); return; }
        const btn = document.getElementById('saveLeadCompanyStatusBtn');
        btn.disabled = true;
        try {
            const res = await fetch(api + '/statuses', {
                method: 'POST', credentials: 'same-origin', headers: headers(true),
                body: JSON.stringify({ name }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || 'Could not create status.');
            if (nameEl) nameEl.value = '';
            await loadCompanyStatuses();
        } catch (err) {
            alert(err.message);
        } finally {
            btn.disabled = false;
        }
    });
    document.getElementById('leadCompanyStatusList')?.addEventListener('click', async (e) => {
        const save = e.target.closest('[data-save-company-status]');
        if (save) {
            const id = save.dataset.saveCompanyStatus;
            const input = document.querySelector(`[data-status-name="${id}"]`);
            const name = input?.value.trim() || '';
            if (!name) { input?.focus(); return; }
            save.disabled = true;
            try {
                const res = await fetch(api + '/statuses/' + id, {
                    method: 'PATCH', credentials: 'same-origin', headers: headers(true),
                    body: JSON.stringify({ name }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(data.message || 'Could not update status.');
                await loadCompanyStatuses();
                loadLeads();
            } catch (err) {
                alert(err.message);
            } finally {
                save.disabled = false;
            }
            return;
        }
        const del = e.target.closest('[data-delete-company-status]');
        if (!del) return;
        if (!confirm('Delete this status? Leads using it will move to the default status.')) return;
        const res = await fetch(api + '/statuses/' + del.dataset.deleteCompanyStatus, {
            method: 'DELETE', credentials: 'same-origin', headers: headers(),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            alert(data.message || 'Could not delete status.');
            return;
        }
        await loadCompanyStatuses();
        loadLeads();
    });
    document.getElementById('closeLeadLabelsModal')?.addEventListener('click', closeLabelsModal);
    document.getElementById('closeLeadLabelsBtn')?.addEventListener('click', closeLabelsModal);
    document.getElementById('leadCompanyLabelForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const nameEl = document.getElementById('leadCompanyLabelName');
        const colorEl = document.getElementById('leadCompanyLabelColor');
        const name = nameEl?.value.trim() || '';
        if (!name) { nameEl?.focus(); return; }
        const btn = document.getElementById('saveLeadCompanyLabelBtn');
        btn.disabled = true;
        try {
            const res = await fetch(api + '/labels', {
                method: 'POST', credentials: 'same-origin', headers: headers(true),
                body: JSON.stringify({ name, color: colorEl?.value || '#4338ca' }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || 'Could not create label.');
            if (nameEl) nameEl.value = '';
            await loadCompanyLabels();
        } catch (err) {
            alert(err.message);
        } finally {
            btn.disabled = false;
        }
    });
    document.getElementById('leadCompanyLabelList')?.addEventListener('click', async (e) => {
        const save = e.target.closest('[data-save-company-label]');
        if (save) {
            await saveCompanyLabel(save.dataset.saveCompanyLabel);
            return;
        }
        const del = e.target.closest('[data-delete-company-label]');
        if (!del) return;
        if (!confirm('Delete this label? It will be removed from all leads.')) return;
        const res = await fetch(api + '/labels/' + del.dataset.deleteCompanyLabel, {
            method: 'DELETE', credentials: 'same-origin', headers: headers(),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) return alert(data.message || 'Could not delete label.');
        state.labelIds = state.labelIds.filter(id => id !== String(del.dataset.deleteCompanyLabel));
        await loadCompanyLabels();
        loadLeads();
    });
    document.getElementById('leadCompanyLabelList')?.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;
        const input = e.target.closest('[data-label-name]');
        if (!input) return;
        e.preventDefault();
        saveCompanyLabel(input.dataset.labelName);
    });
    document.getElementById('leadCompanyLabelList')?.addEventListener('change', async (e) => {
        const color = e.target.closest('[data-label-color]');
        if (!color) return;
        const res = await fetch(api + '/labels/' + color.dataset.labelColor, {
            method: 'PATCH', credentials: 'same-origin', headers: headers(true),
            body: JSON.stringify({ color: color.value }),
        });
        if (!res.ok) return alert('Could not update label color.');
        await loadCompanyLabels();
        loadLeads();
    });
    document.getElementById('leadRulesBtn')?.addEventListener('click', () => {
        state.rulesPage = 1;
        state.rulesSearch = '';
        const search = document.getElementById('leadRuleSearch');
        if (search) search.value = '';
        loadRules().catch(() => {});
        openRulesModal();
    });
    document.getElementById('leadRulesPrev')?.addEventListener('click', () => {
        if (state.rulesPage > 1) {
            state.rulesPage -= 1;
            loadRules().catch(() => {});
        }
    });
    document.getElementById('leadRulesNext')?.addEventListener('click', () => {
        if (state.rulesPage < state.rulesLastPage) {
            state.rulesPage += 1;
            loadRules().catch(() => {});
        }
    });
    let ruleSearchTimer;
    document.getElementById('leadRuleSearch')?.addEventListener('input', (e) => {
        clearTimeout(ruleSearchTimer);
        ruleSearchTimer = setTimeout(() => {
            state.rulesSearch = e.target.value.trim();
            state.rulesPage = 1;
            loadRules().catch(() => {});
        }, 250);
    });
    document.getElementById('closeLeadRulesModal')?.addEventListener('click', closeRulesModal);
    document.getElementById('cancelLeadRuleBtn')?.addEventListener('click', () => {
        if (document.getElementById('leadRuleBuilder')?.hidden) closeRulesModal();
        else showRuleList();
    });
    document.getElementById('newLeadRuleBtn')?.addEventListener('click', () => {
        if (!state.canManageRules) return alert('You do not have permission to add rules.');
        resetRuleBuilder();
        showRuleEditor();
    });
    document.getElementById('btnAddLeadRuleTrigger')?.addEventListener('click', () => {
        const used = new Set([...document.querySelectorAll('#leadRuleTriggers [data-rule-trigger]')].map(s => s.value));
        const next = RULE_TRIGGERS.find(t => !used.has(t.value));
        addRuleTriggerRow({ value: next?.value || 'inbound_message' });
    });
    document.getElementById('btnAddLeadRuleCondition')?.addEventListener('click', () => addRuleConditionRow());
    document.getElementById('btnAddLeadRuleAction')?.addEventListener('click', () => addRuleActionRow());
    document.getElementById('leadRuleChannelToggle')?.addEventListener('click', (e) => {
        e.preventDefault();
        const menu = document.getElementById('leadRuleChannelMenu');
        const inboxMenu = document.getElementById('leadRuleInboxMenu');
        if (inboxMenu) inboxMenu.hidden = true;
        if (menu) menu.hidden = !menu.hidden;
    });
    document.getElementById('leadRuleChannelMenu')?.addEventListener('change', updateRuleChannelLabel);
    document.getElementById('leadRuleInboxToggle')?.addEventListener('click', (e) => {
        e.preventDefault();
        const menu = document.getElementById('leadRuleInboxMenu');
        const channelMenu = document.getElementById('leadRuleChannelMenu');
        if (channelMenu) channelMenu.hidden = true;
        if (menu) menu.hidden = !menu.hidden;
    });
    document.getElementById('leadRuleInboxMenu')?.addEventListener('change', updateRuleInboxLabel);
    document.getElementById('leadRuleTriggers')?.addEventListener('change', (e) => {
        const sel = e.target.closest('[data-rule-trigger]');
        if (!sel) return;
        const row = sel.closest('.leads-rule-extra-card');
        const help = row?.querySelector('.leads-rule-trigger-help');
        if (help) help.textContent = triggerHelp(sel.value);
        syncTriggerLabelSelect(row);
    });
    document.getElementById('leadRuleConditions')?.addEventListener('change', (e) => {
        const fieldSel = e.target.closest('[data-rule-cond-field]');
        if (!fieldSel) return;
        const row = fieldSel.closest('.leads-rule-extra-card');
        row?.classList.toggle('is-multi-label', fieldSel.value === 'lead_label');
        const current = row.querySelector('[data-rule-cond-value]');
        const wrap = document.createElement('div');
        wrap.innerHTML = conditionValueControl(fieldSel.value, '');
        const next = wrap.querySelector('[data-rule-cond-value]') || wrap.firstElementChild;
        if (next) current?.replaceWith(next);
        const op = row.querySelector('[data-rule-cond-operator]');
        const opWrap = document.createElement('div');
        opWrap.innerHTML = `<select data-rule-cond-operator>${conditionOperatorOptions(fieldSel.value, '')}</select>`;
        op?.replaceWith(opWrap.firstElementChild);
    });
    document.getElementById('leadRuleActions')?.addEventListener('change', (e) => {
        const typeSel = e.target.closest('[data-rule-action-type]');
        if (typeSel) {
            const row = typeSel.closest('.leads-rule-extra-card');
            syncActionRow(row, typeSel.value);
            return;
        }
        const valueSel = e.target.closest('[data-rule-action-value]');
        if (!valueSel) return;
        const row = valueSel.closest('.leads-rule-extra-card');
        if (row?.querySelector('[data-rule-action-type]')?.value === 'assign') {
            syncAssignTeammatePicker(row);
        }
    });
    document.getElementById('leadRuleBuilder')?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-remove-rule-row]');
        if (btn) btn.closest('.leads-rule-extra-card')?.remove();
    });
    document.getElementById('leadRuleList')?.addEventListener('click', async (e) => {
        const lastApplied = e.target.closest('[data-open-last-applied-lead]');
        if (lastApplied) {
            e.preventDefault();
            e.stopPropagation();
            const leadId = lastApplied.dataset.openLastAppliedLead;
            if (!leadId) return;
            closeRulesModal();
            try {
                await openLead(leadId);
            } catch (err) {
                alert(err.message || 'Lead not found.');
            }
            return;
        }
        if (!state.canManageRules) return;
        const del = e.target.closest('[data-delete-lead-rule]');
        if (del) {
            if (!confirm('Delete this rule?')) return;
            const res = await fetch(api + '/rules/' + del.dataset.deleteLeadRule, {
                method: 'DELETE', credentials: 'same-origin', headers: headers(),
            });
            if (!res.ok) return alert('Could not delete rule.');
            await loadRules();
            return;
        }
        const toggle = e.target.closest('[data-toggle-lead-rule]');
        if (toggle) {
            const rule = state.rules.find(r => String(r.id) === String(toggle.dataset.toggleLeadRule));
            if (!rule) return;
            const res = await fetch(api + '/rules/' + rule.id, {
                method: 'PATCH', credentials: 'same-origin', headers: headers(true),
                body: JSON.stringify({ is_active: !rule.is_active }),
            });
            if (!res.ok) return alert('Could not update rule.');
            await loadRules();
            return;
        }
        const edit = e.target.closest('[data-edit-lead-rule]');
        if (edit) {
            const rule = state.rules.find(r => String(r.id) === String(edit.dataset.editLeadRule));
            if (!rule) return;
            fillRuleBuilder(rule);
            showRuleEditor();
        }
    });
    document.getElementById('saveLeadRuleBtn')?.addEventListener('click', async () => {
        if (!state.canManageRules) return alert('You do not have permission to add rules.');
        const payload = collectRulePayload();
        if (!payload.name) return alert('Enter a name for this rule.');
        if (!payload.triggers.length) return alert('Add at least one trigger.');
        if (payload.triggers.includes('lead_labeled')) {
            const labeled = payload.conditions.find(c => c.field === 'label_added');
            if (!labeled || !String(labeled.value || '').trim()) {
                return alert('Choose which label was added.');
            }
        }
        if (payload.triggers.includes('lead_status_changed')) {
            const changed = payload.conditions.find(c => c.field === 'status_changed');
            if (!changed || !String(changed.value || '').trim()) {
                return alert('Choose which status was set.');
            }
        }
        const extra = payload.conditions.filter(c => c.field !== 'channel' && c.field !== 'shared_inbox');
        if (extra.some(c => Array.isArray(c.value) ? !c.value.length : !String(c.value || '').trim())) {
            return alert('Each condition needs a value.');
        }
        if (!payload.actions.length) return alert('Add at least one action.');
        for (const action of payload.actions) {
            if (action.type === 'assign') {
                if (action.value && typeof action.value === 'object' && action.value.mode === '__round_robin_selected__') {
                    if (!Array.isArray(action.value.user_ids) || !action.value.user_ids.length) {
                        return alert('Select teammates for round robin.');
                    }
                    continue;
                }
                if (action.value === null || action.value === '') {
                    return alert('That action needs a value.');
                }
                continue;
            }
            if (action.type === 'add_label') {
                if (!Array.isArray(action.value) || !action.value.length) {
                    return alert('Choose at least one label.');
                }
                continue;
            }
            if (['set_status'].includes(action.type) && (action.value === null || action.value === '')) {
                return alert('That action needs a value.');
            }
            if (action.type === 'set_status_after_days') {
                const days = Number(action.value?.days);
                const status = String(action.value?.status || '').trim();
                if (!Number.isFinite(days) || days < 1 || days > 365) {
                    return alert('Choose how many days before the status changes (1–365).');
                }
                if (!status) {
                    return alert('Choose which status to set after the delay.');
                }
            }
            if (action.type === 'reopen_after_days') {
                const days = Number(action.value);
                if (!Number.isFinite(days) || days < 1 || days > 365) {
                    return alert('Choose how many days before reopen (1–365).');
                }
            }
            if (action.type === 'send_email') {
                const templateId = Number(action.value?.template_id);
                const days = Number(action.value?.days);
                if (!Number.isFinite(templateId) || templateId < 1) {
                    return alert('Choose an email template.');
                }
                if (!Number.isFinite(days) || days < 0 || days > 365) {
                    return alert('Choose how many days to wait before sending (0–365).');
                }
            }
        }
        const btn = document.getElementById('saveLeadRuleBtn');
        btn.disabled = true;
        const editingId = state.editingRuleId;
        try {
            const res = await fetch(editingId ? api + '/rules/' + editingId : api + '/rules', {
                method: editingId ? 'PATCH' : 'POST',
                credentials: 'same-origin',
                headers: headers(true),
                body: JSON.stringify(payload),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || (editingId ? 'Failed to update rule' : 'Failed to create rule'));
            state.editingRuleId = null;
            await loadRules();
            showRuleList();
        } catch (err) {
            alert(err.message);
        } finally {
            btn.disabled = false;
        }
    });

    resetForm();
    Promise.all([loadCompanyLabels(), loadCompanyStatuses(), loadAssignees()]).then(() => {
        return loadLeads();
    }).then(() => {
        const params = new URLSearchParams(window.location.search);
        const id = params.get('lead');
        const tab = params.get('tab') || undefined;
        if (id) openLead(id, { tab }).catch(() => {});
        if (params.get('openRules')) {
            state.rulesPage = 1;
            state.rulesSearch = '';
            const search = document.getElementById('leadRuleSearch');
            if (search) search.value = '';
            loadRules().catch(() => {});
            openRulesModal();
        }
    });
})();
