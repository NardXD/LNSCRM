{{-- Shared contact-history side panel. Pass $panelId (default: contactHistoryPanel). --}}
@php
    $panelId = $panelId ?? 'contactHistoryPanel';
@endphp
<aside class="chp-panel" id="{{ $panelId }}" hidden
       data-api="/api/crm/contact-history"
       data-can-save-lead="{{ auth()->user()?->hasPermission('view_leads') ? '1' : '0' }}">
    <div class="chp-header">
        <strong>Contact history</strong>
        <span class="chp-hint">All channels</span>
    </div>
    <div class="chp-body" id="{{ $panelId }}Body">
        <p class="chp-empty">Select a conversation to see history across WhatsApp, Viber, SMS, Inbox, Calls, and Facebook.</p>
    </div>
</aside>

<style>
.chp-panel {
    display: none;
    flex-direction: column;
    width: 300px;
    min-width: 260px;
    max-width: 340px;
    border-left: 1px solid var(--border, #d8dee6);
    background: var(--bg-primary, #f7f8fa);
    min-height: 0;
    height: 100%;
    overflow: hidden;
}
.chp-panel.chp-visible,
.chp-panel:not([hidden]) {
    display: flex;
}
.chp-header {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.85rem 1rem;
    border-bottom: 1px solid var(--border, #d8dee6);
    flex-shrink: 0;
}
.chp-header strong { font-size: 0.92rem; color: var(--text-primary, #1a2332); }
.chp-hint { font-size: 0.72rem; color: var(--text-secondary, #5b6b7c); text-transform: uppercase; letter-spacing: 0.04em; }
.chp-body {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
    padding: 0.75rem 0.9rem 1.25rem;
}
.chp-section { margin-bottom: 1.1rem; }
.chp-label {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-secondary, #5b6b7c);
    margin-bottom: 0.45rem;
}
.chp-name { font-weight: 700; font-size: 0.98rem; color: var(--text-primary, #1a2332); margin-bottom: 0.25rem; }
.chp-meta { font-size: 0.8rem; color: var(--text-secondary, #5b6b7c); margin: 0.15rem 0; word-break: break-all; }
.chp-assigned { font-size: 0.8rem; font-weight: 600; color: #0b5cab; margin: 0.2rem 0 0.1rem; }
.channel-label-chips { display: flex; flex-wrap: wrap; gap: 0.25rem; margin-top: 0.25rem; }
.channel-label-chip { display: inline-flex; align-items: center; padding: 0.1rem 0.4rem; border-radius: 999px; font-size: 0.65rem; font-weight: 700; line-height: 1.2; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.chp-link { display: inline-block; margin-top: 0.45rem; margin-right: 0.65rem; font-size: 0.82rem; font-weight: 600; color: #0b5cab; text-decoration: none; }
.chp-save-lead {
    display: inline-block;
    margin-top: 0.45rem;
    padding: 0.28rem 0.6rem;
    border: 1px solid #0b5cab;
    border-radius: 6px;
    background: #fff;
    color: #0b5cab;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
}
.chp-select {
    width: 100%;
    margin: 0.15rem 0 0.45rem;
    padding: 0.38rem 0.5rem;
    border: 1px solid var(--border, #d8dee6);
    border-radius: 6px;
    font-size: 0.84rem;
    background: #fff;
    color: var(--text-primary, #1a2332);
}
.chp-lead-edit .chp-label { margin-top: 0.7rem; }
.chp-label-pills { display: flex; flex-wrap: wrap; gap: 0.25rem; min-height: 1.2rem; margin-bottom: 0.35rem; }
.chp-lead-label-add { display: flex; gap: 0.4rem; align-items: stretch; margin-top: 0.35rem; }
.chp-lead-label-add input { flex: 1; min-width: 0; margin: 0; }
.chp-lead-label-add .chp-save-lead { margin-top: 0; flex-shrink: 0; }
.channel-label-chip button { border: none; background: transparent; cursor: pointer; color: inherit; font-size: 0.8rem; line-height: 1; margin-left: 0.15rem; padding: 0; }
.chp-lead-form { margin-top: 0.65rem; display: flex; flex-direction: column; gap: 0.45rem; }
.chp-lead-form .chp-empty { margin-bottom: 0.15rem; }
.chp-field {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-secondary, #5b6b7c);
}
.chp-field input {
    padding: 0.38rem 0.5rem;
    border: 1px solid var(--border, #d8dee6);
    border-radius: 6px;
    font-size: 0.84rem;
    font-weight: 400;
    text-transform: none;
    letter-spacing: normal;
    color: var(--text-primary, #1a2332);
    background: #fff;
}
.chp-lead-error { font-size: 0.78rem; color: #b42318; margin: 0; }
.chp-lead-actions { display: flex; gap: 0.45rem; align-items: center; flex-wrap: wrap; }
.chp-lead-cancel {
    margin-top: 0.45rem;
    padding: 0.28rem 0.6rem;
    border: 0;
    background: transparent;
    color: var(--text-secondary, #5b6b7c);
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
}
.chp-item {
    display: block;
    text-decoration: none;
    color: inherit;
    padding: 0.55rem 0;
    border-bottom: 1px solid var(--border, #e6ebf0);
}
.chp-item:last-child { border-bottom: 0; }
.chp-item:hover .chp-item-title { color: #0b5cab; }
.chp-item-title { font-size: 0.86rem; font-weight: 600; margin: 0.2rem 0; color: var(--text-primary, #1a2332); }
.chp-item-preview { font-size: 0.78rem; color: var(--text-secondary, #5b6b7c); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.chp-event { padding: 0.45rem 0; border-bottom: 1px solid var(--border, #e6ebf0); }
.chp-event:last-child { border-bottom: 0; }
.chp-dir { font-size: 0.72rem; color: var(--text-secondary, #5b6b7c); }
.chp-empty { font-size: 0.84rem; color: var(--text-secondary, #5b6b7c); margin: 0; line-height: 1.4; }
.chp-badge {
    display: inline-block;
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    padding: 0.12rem 0.4rem;
    border-radius: 4px;
    background: #e8f1fb;
    color: #0b5cab;
}
.chp-badge.whatsapp { background: #e8f8ef; color: #128c7e; }
.chp-badge.viber { background: #efeaff; color: #5b3cc4; }
.chp-badge.sms { background: #e8f8ef; color: #0f7b4c; }
.chp-badge.inbox { background: #fff4e5; color: #b45309; }
.chp-badge.call { background: #f1f3f5; color: #495057; }
.chp-badge.facebook { background: #e8f1ff; color: #1877f2; }
.chp-panel .chp-body > .chp-section:first-child { padding-bottom: 0.5rem; border-bottom: 1px solid var(--border, #e6ebf0); margin-bottom: 0.9rem; }
@media (max-width: 900px) {
    .chp-panel { display: none !important; }
}
@media (min-width: 901px) {
    .sms-layout.with-history .chp-panel,
    .wa-layout.with-history .chp-panel,
    .viber-layout.with-history .chp-panel,
    .fb-layout.with-history .chp-panel {
        display: flex !important;
    }
}
</style>
<script>
(function () {
    function esc(str) {
        return String(str ?? '').replace(/[&<>"']/g, (c) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
        }[c]));
    }

    function badgeClass(channel) {
        return ['whatsapp', 'viber', 'sms', 'inbox', 'call', 'facebook'].includes(channel) ? channel : '';
    }

    function formatAt(iso) {
        if (!iso) return '';
        try { return new Date(iso).toLocaleString(); } catch { return iso; }
    }

    function chipTextColor(hex) {
        const h = String(hex || '').replace('#', '');
        if (h.length !== 6 || Number.isNaN(parseInt(h, 16))) return '#fff';
        const r = parseInt(h.slice(0, 2), 16);
        const g = parseInt(h.slice(2, 4), 16);
        const b = parseInt(h.slice(4, 6), 16);
        return ((r * 299) + (g * 587) + (b * 114)) / 1000 > 160 ? '#111' : '#fff';
    }

    function leadLabelChipsHtml(labels) {
        const items = Array.isArray(labels) ? labels.filter((label) => label && label.name) : [];
        if (!items.length) return '';
        return `<div class="channel-label-chips">${items.map((label) => {
            const color = label.color || '#4338ca';
            return `<span class="channel-label-chip" style="background:${esc(color)};color:${chipTextColor(color)}">${esc(label.name)}</span>`;
        }).join('')}</div>`;
    }

    function conversationLabelsSectionHtml(opts) {
        if (!opts.conversationLabelsApi) return '';
        const items = Array.isArray(opts.conversationLabels) ? opts.conversationLabels.filter((label) => label && label.name) : [];
        const pills = items.length
            ? items.map((label) => {
                const color = label.color || '#4338ca';
                return `<span class="channel-label-chip" style="background:${esc(color)};color:${chipTextColor(color)}">${esc(label.name)} <button type="button" data-chp-remove-conv-label="${label.id}" title="Remove">×</button></span>`;
            }).join('')
            : '<span class="chp-empty">No labels yet</span>';
        return `
            <div class="chp-section" data-chp-conv-labels-section>
                <div class="chp-label">Conversation labels</div>
                <div class="chp-label-pills" data-chp-conv-labels>${pills}</div>
                <select class="chp-select" data-chp-add-conv-label><option value="">Add existing label…</option></select>
                <div class="chp-lead-label-add">
                    <input type="text" class="chp-select" data-chp-new-conv-label maxlength="50" placeholder="New label">
                    <button type="button" class="chp-save-lead" data-chp-add-conv-label-btn>Add</button>
                </div>
            </div>
        `;
    }

    async function conversationLabelApi(opts, method, body, labelId) {
        const url = opts.conversationLabelsApi + (labelId ? '/' + labelId : '');
        const res = await fetch(url, Object.assign({
            method,
            credentials: 'same-origin',
            headers: leadCsrfHeaders(),
        }, body ? { body: JSON.stringify(body) } : {}));
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.message || 'Request failed.');
        return data;
    }

    function bindConversationLabelEditors(root, opts) {
        if (!opts.conversationLabelsApi) return;
        const section = root.querySelector('[data-chp-conv-labels-section]');
        if (!section) return;

        const pills = section.querySelector('[data-chp-conv-labels]');
        const addSelect = section.querySelector('[data-chp-add-conv-label]');
        const nameInput = section.querySelector('[data-chp-new-conv-label]');
        const addBtn = section.querySelector('[data-chp-add-conv-label-btn]');
        let currentLabels = Array.isArray(opts.conversationLabels) ? opts.conversationLabels.slice() : [];

        const renderPills = () => {
            if (!pills) return;
            pills.innerHTML = currentLabels.length
                ? currentLabels.map((label) => {
                    const color = label.color || '#4338ca';
                    return `<span class="channel-label-chip" style="background:${esc(color)};color:${chipTextColor(color)}">${esc(label.name)} <button type="button" data-chp-remove-conv-label="${label.id}" title="Remove">×</button></span>`;
                }).join('')
                : '<span class="chp-empty">No labels yet</span>';
        };

        const fillAddSelect = () => {
            if (!addSelect) return;
            const used = new Set(currentLabels.map((label) => Number(label.id)));
            addSelect.innerHTML = '<option value="">Add existing label…</option>' +
                leadOptionsCache.labels.filter((label) => !used.has(Number(label.id)))
                    .map((label) => `<option value="${label.id}">${esc(label.name)}</option>`).join('');
        };

        const applyLabels = (labels) => {
            currentLabels = Array.isArray(labels) ? labels : [];
            renderPills();
            fillAddSelect();
            if (typeof opts.onConversationLabelsChange === 'function') opts.onConversationLabelsChange(currentLabels);
        };

        ensureLeadOptions().then(fillAddSelect);

        addSelect && addSelect.addEventListener('change', async () => {
            const labelId = addSelect.value;
            if (!labelId) return;
            try {
                addSelect.disabled = true;
                const data = await conversationLabelApi(opts, 'POST', { label_id: Number(labelId) });
                if (data.data && !leadOptionsCache.labels.some((label) => String(label.id) === String(data.data.id))) {
                    leadOptionsCache.labels.push(data.data);
                }
                applyLabels(data.labels || currentLabels);
            } catch (err) {
                alert(err.message || 'Could not add label.');
            } finally {
                addSelect.disabled = false;
                addSelect.value = '';
            }
        });

        const addByName = async () => {
            const name = String(nameInput && nameInput.value || '').trim();
            if (!name) {
                nameInput && nameInput.focus();
                return;
            }
            try {
                if (addBtn) addBtn.disabled = true;
                const data = await conversationLabelApi(opts, 'POST', { name });
                if (data.data && !leadOptionsCache.labels.some((label) => String(label.id) === String(data.data.id))) {
                    leadOptionsCache.labels.push(data.data);
                }
                if (nameInput) nameInput.value = '';
                applyLabels(data.labels || currentLabels);
            } catch (err) {
                alert(err.message || 'Could not add label.');
            } finally {
                if (addBtn) addBtn.disabled = false;
            }
        };

        addBtn && addBtn.addEventListener('click', addByName);
        nameInput && nameInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                addByName();
            }
        });

        pills && pills.addEventListener('click', async (e) => {
            const btn = e.target.closest('[data-chp-remove-conv-label]');
            if (!btn) return;
            try {
                const data = await conversationLabelApi(opts, 'DELETE', null, btn.dataset.chpRemoveConvLabel);
                applyLabels(data.labels || currentLabels.filter((label) => String(label.id) !== String(btn.dataset.chpRemoveConvLabel)));
            } catch (err) {
                alert(err.message || 'Could not remove label.');
            }
        });
    }

    function assignedLeadPanelHtml(lead, canEdit) {
        if (!lead) return '';
        const link = lead.crm_url
            ? `<a class="chp-link" href="${esc(lead.crm_url)}" target="_blank" rel="noopener">Open lead →</a>`
            : '';
        if (!canEdit) {
            const assignee = lead.assigned_user && lead.assigned_user.name;
            const line = assignee
                ? `<div class="chp-assigned">Assigned to ${esc(assignee)}${lead.status ? ' · ' + esc(lead.status) : ''}</div>`
                : `<div class="chp-meta">Lead${lead.status ? ' · ' + esc(lead.status) : ''} · Unassigned</div>`;
            return line + leadLabelChipsHtml(lead.labels) + link;
        }
        const items = Array.isArray(lead.labels) ? lead.labels.filter((label) => label && label.name) : [];
        const pills = items.length
            ? items.map((label) => {
                const color = label.color || '#4338ca';
                return `<span class="channel-label-chip" style="background:${esc(color)};color:${chipTextColor(color)}">${esc(label.name)} <button type="button" data-chp-remove-label="${label.id}" title="Remove">×</button></span>`;
            }).join('')
            : '<span class="chp-empty">No labels</span>';
        return `
            <div class="chp-lead-edit">
                <div class="chp-label">Assignee</div>
                <select class="chp-select" data-chp-assign><option value="">Unassigned</option></select>
                <div class="chp-label">Labels</div>
                <div class="chp-label-pills" data-chp-labels>${pills}</div>
                <select class="chp-select" data-chp-add-label><option value="">Add existing label…</option></select>
                <div class="chp-lead-label-add">
                    <input type="text" class="chp-select" data-chp-new-label maxlength="50" placeholder="New label">
                    <button type="button" class="chp-save-lead" data-chp-add-label-btn>Add</button>
                </div>
                ${link}
            </div>
        `;
    }

    function getBody(root) {
        return root.querySelector('.chp-body') || root;
    }

    const leadOptionsCache = { assignees: [], labels: [], loaded: false };

    function leadCsrfHeaders() {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        return {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(token ? { 'X-CSRF-TOKEN': token } : {}),
        };
    }

    async function ensureLeadOptions() {
        if (leadOptionsCache.loaded) return leadOptionsCache;
        try {
            const [assigneesRes, labelsRes] = await Promise.all([
                fetch('/api/leads/assignees', { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }),
                fetch('/api/leads/labels', { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }),
            ]);
            const assigneesData = await assigneesRes.json().catch(() => ({}));
            const labelsData = await labelsRes.json().catch(() => ({}));
            leadOptionsCache.assignees = assigneesData.data || [];
            leadOptionsCache.labels = labelsData.data || [];
            leadOptionsCache.loaded = assigneesRes.ok && labelsRes.ok;
        } catch (_) {
            leadOptionsCache.assignees = [];
            leadOptionsCache.labels = [];
        }
        return leadOptionsCache;
    }

    async function leadApi(path, options) {
        options = options || {};
        const res = await fetch('/api/leads' + path, Object.assign({
            credentials: 'same-origin',
            headers: leadCsrfHeaders(),
        }, options));
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.message || 'Request failed.');
        return data;
    }

    function fillAssigneeSelect(select, lead) {
        const users = leadOptionsCache.assignees.slice();
        if (lead.assigned_user && lead.assigned_user.id && !users.some((user) => String(user.id) === String(lead.assigned_user.id))) {
            users.push(lead.assigned_user);
        }
        const selected = lead.assigned_to == null || lead.assigned_to === '' ? '' : String(lead.assigned_to);
        select.innerHTML = '<option value="">Unassigned</option>' + users.map((user) =>
            `<option value="${user.id}"${String(user.id) === selected ? ' selected' : ''}>${esc(user.name)}</option>`
        ).join('');
    }

    function fillAddLabelSelect(select, lead) {
        const used = new Set((lead.labels || []).map((label) => Number(label.id)));
        select.innerHTML = '<option value="">Add existing label…</option>' +
            leadOptionsCache.labels.filter((label) => !used.has(Number(label.id)))
                .map((label) => `<option value="${label.id}">${esc(label.name)}</option>`).join('');
    }

    function renderEditableLabelPills(container, lead) {
        const items = Array.isArray(lead.labels) ? lead.labels.filter((label) => label && label.name) : [];
        container.innerHTML = items.length
            ? items.map((label) => {
                const color = label.color || '#4338ca';
                return `<span class="channel-label-chip" style="background:${esc(color)};color:${chipTextColor(color)}">${esc(label.name)} <button type="button" data-chp-remove-label="${label.id}" title="Remove">×</button></span>`;
            }).join('')
            : '<span class="chp-empty">No labels</span>';
    }

    function compactLead(lead) {
        if (!lead) return null;
        return {
            id: lead.id,
            name: lead.name,
            status: lead.status,
            crm_url: lead.crm_url,
            assigned_to: lead.assigned_to,
            assigned_user: lead.assigned_user || null,
            labels: Array.isArray(lead.labels) ? lead.labels : [],
        };
    }

    function bindLeadEditors(root, opts, contact) {
        const lead = contact.lead;
        if (!lead || !lead.id || opts.canEditLead !== true || opts.canSaveLead === false) return;

        const assignSelect = root.querySelector('[data-chp-assign]');
        const addSelect = root.querySelector('[data-chp-add-label]');
        const pills = root.querySelector('[data-chp-labels]');
        const nameInput = root.querySelector('[data-chp-new-label]');
        const addBtn = root.querySelector('[data-chp-add-label-btn]');

        const notify = () => {
            if (typeof opts.onLeadUpdated === 'function') opts.onLeadUpdated(compactLead(contact.lead));
        };
        const applyLabels = (labels) => {
            contact.lead.labels = labels;
            if (pills) renderEditableLabelPills(pills, contact.lead);
            if (addSelect) fillAddLabelSelect(addSelect, contact.lead);
            notify();
        };

        ensureLeadOptions().then(() => {
            if (assignSelect) fillAssigneeSelect(assignSelect, contact.lead);
            if (addSelect) fillAddLabelSelect(addSelect, contact.lead);
        });

        assignSelect && assignSelect.addEventListener('change', async () => {
            try {
                assignSelect.disabled = true;
                const data = await leadApi('/' + lead.id + '/assign', {
                    method: 'PATCH',
                    body: JSON.stringify({ assigned_to: assignSelect.value || null }),
                });
                const updated = data.data || {};
                contact.lead.assigned_to = updated.assigned_to ?? (assignSelect.value ? Number(assignSelect.value) : null);
                contact.lead.assigned_user = updated.assigned_user || (assignSelect.value
                    ? (leadOptionsCache.assignees.find((user) => String(user.id) === String(assignSelect.value)) || null)
                    : null);
                if (updated.labels) contact.lead.labels = updated.labels;
                notify();
            } catch (err) {
                alert(err.message || 'Could not assign lead.');
                fillAssigneeSelect(assignSelect, contact.lead);
            } finally {
                assignSelect.disabled = false;
            }
        });

        addSelect && addSelect.addEventListener('change', async () => {
            const labelId = addSelect.value;
            if (!labelId) return;
            try {
                addSelect.disabled = true;
                const data = await leadApi('/' + lead.id + '/labels', {
                    method: 'POST',
                    body: JSON.stringify({ label_id: Number(labelId) }),
                });
                if (data.data && !leadOptionsCache.labels.some((label) => String(label.id) === String(data.data.id))) {
                    leadOptionsCache.labels.push(data.data);
                }
                applyLabels(data.labels || contact.lead.labels);
            } catch (err) {
                alert(err.message || 'Could not add label.');
            } finally {
                addSelect.disabled = false;
                addSelect.value = '';
            }
        });

        const addByName = async () => {
            const name = String(nameInput && nameInput.value || '').trim();
            if (!name) {
                nameInput && nameInput.focus();
                return;
            }
            try {
                if (addBtn) addBtn.disabled = true;
                const data = await leadApi('/' + lead.id + '/labels', {
                    method: 'POST',
                    body: JSON.stringify({ name }),
                });
                if (data.data && !leadOptionsCache.labels.some((label) => String(label.id) === String(data.data.id))) {
                    leadOptionsCache.labels.push(data.data);
                }
                if (nameInput) nameInput.value = '';
                applyLabels(data.labels || contact.lead.labels);
            } catch (err) {
                alert(err.message || 'Could not add label.');
            } finally {
                if (addBtn) addBtn.disabled = false;
            }
        };

        addBtn && addBtn.addEventListener('click', addByName);
        nameInput && nameInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                addByName();
            }
        });

        pills && pills.addEventListener('click', async (e) => {
            const btn = e.target.closest('[data-chp-remove-label]');
            if (!btn) return;
            try {
                const data = await leadApi('/' + lead.id + '/labels/' + btn.dataset.chpRemoveLabel, { method: 'DELETE' });
                applyLabels(data.labels || (contact.lead.labels || []).filter((label) => String(label.id) !== String(btn.dataset.chpRemoveLabel)));
            } catch (err) {
                alert(err.message || 'Could not remove label.');
            }
        });
    }

    function renderPanel(root, data, opts) {
        opts = opts || {};
        const excludeChannel = opts.excludeChannel || null;
        const excludeId = opts.excludeId != null ? Number(opts.excludeId) : null;
        const contact = data.contact || {};
        const threads = (data.threads || []).filter((t) => {
            if (excludeChannel && t.channel === excludeChannel && Number(t.conversation_id) === excludeId) {
                return false;
            }
            return true;
        });
        const events = (data.events || []).slice(0, 25);
        const extractedName = String(opts.extracted_name || (opts.extracted_names || [])[0] || '').trim();
        const placeholder = isPlaceholderName(contact.display_name || opts.name);
        const displayName = extractedName || contact.display_name || opts.name || 'Contact';
        const foundInMessages = (opts.extracted_phones || []).length > 0 || (opts.extracted_emails || []).length > 0 || Boolean(extractedName);
        let placeholderHint = '';
        if (placeholder && !contact.lead) {
            if (extractedName && ((opts.extracted_phones || []).length || (opts.extracted_emails || []).length)) {
                placeholderHint = '<p class="chp-empty">Name and contact details were found in the messages. Review them, then save as a lead.</p>';
            } else if (extractedName) {
                placeholderHint = '<p class="chp-empty">A name was found in the messages. Add a phone or email to save as a lead.</p>';
            } else if (foundInMessages) {
                placeholderHint = '<p class="chp-empty">No real name on this thread. Phone or email was found in the messages — add a name to save as a lead.</p>';
            } else {
                placeholderHint = '<p class="chp-empty">No real name on this thread. You can still save as an individual lead — a name and a phone or email are required.</p>';
            }
        }
        const contactHtml = `
            <div class="chp-name">${esc(displayName)}</div>
            ${uniqueList([...(contact.matched_phones || []), ...(opts.extracted_phones || []), opts.phone]).slice(0, 3).map((p) => `<div class="chp-meta">${esc(p)}</div>`).join('')}
            ${uniqueList([...(contact.matched_emails || []), ...(opts.extracted_emails || []), opts.email]).slice(0, 3).map((em) => `<div class="chp-meta">${esc(em)}</div>`).join('')}
            ${foundInMessages ? '<div class="chp-meta">Found in conversation messages</div>' : ''}
            ${placeholderHint}
            ${assignedLeadPanelHtml(contact.lead, opts.canEditLead === true && opts.canSaveLead !== false)}
            ${contact.client?.crm_url ? `<a class="chp-link" href="${esc(contact.client.crm_url)}" target="_blank" rel="noopener">Open client →</a>` : ''}
            ${!contact.lead && (opts.canSaveLead !== false) ? `<button type="button" class="chp-save-lead" data-chp-save-lead>Save as lead</button>` : ''}
        `;
        const threadsHtml = threads.length
            ? threads.map((t) => `
                <a class="chp-item" href="${esc(t.deep_link || '#')}">
                    <span class="chp-badge ${badgeClass(t.channel)}">${esc(t.label || t.channel)}</span>
                    <div class="chp-item-title">${esc(t.title || '')}</div>
                    <div class="chp-item-preview">${esc(t.preview || '')}</div>
                </a>`).join('')
            : '<p class="chp-empty">No other channel threads found.</p>';
        const eventsHtml = events.length
            ? events.map((ev) => `
                <div class="chp-event">
                    <span class="chp-badge ${badgeClass(ev.channel)}">${esc(ev.label || ev.channel)}</span>
                    <span class="chp-dir">${esc(ev.direction || '')} · ${esc(formatAt(ev.at))}</span>
                    <div class="chp-item-preview">${esc(ev.preview || '')}</div>
                </div>`).join('')
            : '<p class="chp-empty">No timeline events.</p>';
        root.innerHTML = `
            <div class="chp-section">${contactHtml}</div>
            ${contact.lead ? '' : conversationLabelsSectionHtml(opts)}
            <div class="chp-section">
                <div class="chp-label">Other channels</div>
                ${threadsHtml}
            </div>
            <div class="chp-section">
                <div class="chp-label">Timeline</div>
                ${eventsHtml}
            </div>
        `;

        const saveBtn = root.querySelector('[data-chp-save-lead]');
        if (saveBtn) {
            saveBtn.addEventListener('click', () => saveAsLead(root, opts, contact));
        }
        bindLeadEditors(root, opts, contact);
        bindConversationLabelEditors(root, opts);
    }

    function uniqueList(items) {
        const seen = new Set();
        const out = [];
        for (const item of items) {
            const value = String(item || '').trim();
            if (!value) continue;
            const key = value.toLowerCase();
            if (seen.has(key)) continue;
            seen.add(key);
            out.push(value);
        }
        return out;
    }

    function isPlaceholderName(name) {
        return ['messenger user', 'instagram user', 'facebook user'].includes(String(name || '').trim().toLowerCase());
    }

    function collectPlaceholderLeadDetails(bodyEl, placeholderName, defaults) {
        defaults = defaults || {};
        return new Promise((resolve) => {
            const btn = bodyEl.querySelector('[data-chp-save-lead]');
            if (!btn) {
                resolve(null);
                return;
            }

            const existing = bodyEl.querySelector('.chp-lead-form');
            if (existing) existing.remove();

            const channelLabel = /instagram/i.test(placeholderName) ? 'Instagram' : 'Messenger';
            const foundContact = Boolean(defaults.phone || defaults.email || defaults.name);
            const form = document.createElement('div');
            form.className = 'chp-lead-form';
            form.innerHTML = `
                <p class="chp-empty">${foundContact
                    ? `Details were found in this ${esc(channelLabel)} conversation. Review the name and add a phone or email if needed.`
                    : `This ${esc(channelLabel)} contact has no real name. Add a name and a phone or email to save as an individual lead.`}</p>
                <label class="chp-field"><span>Name</span><input type="text" data-chp-lead-name autocomplete="name"></label>
                <label class="chp-field"><span>Phone</span><input type="tel" data-chp-lead-phone autocomplete="tel"></label>
                <label class="chp-field"><span>Email</span><input type="email" data-chp-lead-email autocomplete="email"></label>
                <p class="chp-lead-error" data-chp-lead-error hidden></p>
                <div class="chp-lead-actions">
                    <button type="button" class="chp-save-lead" data-chp-lead-submit>Save lead</button>
                    <button type="button" class="chp-lead-cancel" data-chp-lead-cancel>Cancel</button>
                </div>
            `;
            btn.hidden = true;
            btn.insertAdjacentElement('afterend', form);
            const nameInput = form.querySelector('[data-chp-lead-name]');
            const phoneInput = form.querySelector('[data-chp-lead-phone]');
            const emailInput = form.querySelector('[data-chp-lead-email]');
            if (phoneInput) phoneInput.value = defaults.phone || '';
            if (emailInput) emailInput.value = defaults.email || '';
            if (nameInput && defaults.name) nameInput.value = defaults.name;
            nameInput?.focus();

            const errorEl = form.querySelector('[data-chp-lead-error]');
            const showError = (message) => {
                errorEl.hidden = false;
                errorEl.textContent = message;
            };

            form.querySelector('[data-chp-lead-cancel]').addEventListener('click', () => {
                form.remove();
                btn.hidden = false;
                resolve(null);
            });

            form.querySelector('[data-chp-lead-submit]').addEventListener('click', () => {
                const name = String(form.querySelector('[data-chp-lead-name]')?.value || '').trim();
                const phone = String(form.querySelector('[data-chp-lead-phone]')?.value || '').trim();
                const email = String(form.querySelector('[data-chp-lead-email]')?.value || '').trim();
                if (!name || isPlaceholderName(name)) {
                    showError('Enter the person’s real name.');
                    return;
                }
                if (!phone && !email) {
                    showError('Add a phone number or an email.');
                    return;
                }
                if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    showError('Enter a valid email address.');
                    return;
                }
                form.remove();
                btn.hidden = false;
                resolve({ name, phone, email });
            });
        });
    }

    async function saveAsLead(bodyEl, opts, contact) {
        let name = String(contact.display_name || opts.name || opts.phone || opts.email || 'New lead').trim();
        const extractedName = String(opts.extracted_name || (opts.extracted_names || [])[0] || '').trim();
        if (extractedName) {
            name = extractedName;
        }
        let phones = uniqueList([...(contact.matched_phones || []), ...(opts.extracted_phones || []), opts.phone]);
        let emails = uniqueList([...(contact.matched_emails || []), ...(opts.extracted_emails || []), opts.email]);

        if (isPlaceholderName(name) || isPlaceholderName(contact.display_name || opts.name) || opts.needsLeadDetails) {
            const details = await collectPlaceholderLeadDetails(bodyEl, contact.display_name || opts.name || name, {
                name: isPlaceholderName(name) ? '' : name,
                phone: phones[0] || '',
                email: emails[0] || '',
            });
            if (!details) return;
            name = details.name;
            phones = uniqueList([details.phone, ...phones]);
            emails = uniqueList([details.email, ...emails]);
        }

        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const btn = bodyEl.querySelector('[data-chp-save-lead]');
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Saving…';
        }

        let facebookName = opts.facebook_name ?? (opts.excludeChannel === 'facebook' && opts.source !== 'instagram' ? opts.name : null);
        let instagramUsername = opts.instagram_username ?? (opts.source === 'instagram' ? (opts.username || opts.name) : null);
        if (isPlaceholderName(facebookName)) facebookName = name;
        if (isPlaceholderName(instagramUsername)) instagramUsername = opts.username && !isPlaceholderName(opts.username) ? opts.username : name;

        try {
            const res = await fetch('/api/leads', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                },
                body: JSON.stringify({
                    name,
                    phones,
                    emails,
                    facebook_name: facebookName,
                    instagram_username: instagramUsername,
                    facebook_conversation_id: opts.excludeChannel === 'facebook' ? opts.excludeId : null,
                    inbox_conversation_ids: opts.excludeChannel === 'inbox' && opts.excludeId ? [opts.excludeId] : [],
                    source: opts.source || opts.excludeChannel || 'contact-history',
                }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                if (typeof opts.onSaved === 'function' && data.existing_lead_id) {
                    opts.onSaved(data, { existing: true });
                    return;
                }
                if (data.existing_lead_id) {
                    window.location.href = '/leads?lead=' + data.existing_lead_id;
                    return;
                }
                throw new Error(data.message || 'Could not save lead.');
            }
            if (typeof opts.onSaved === 'function') {
                opts.onSaved(data, { existing: false });
                return;
            }
            const url = data.data?.crm_url || ('/leads?lead=' + (data.data?.id || ''));
            window.location.href = url;
        } catch (err) {
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Save as lead';
            }
            alert(err.message || 'Could not save lead.');
        }
    }

    async function load(rootOrSelector, opts) {
        opts = opts || {};
        const root = typeof rootOrSelector === 'string'
            ? document.querySelector(rootOrSelector)
            : rootOrSelector;
        if (!root) return null;

        const phone = String(opts.phone || '').trim();
        const email = String(opts.email || '').trim();
        const name = String(opts.name || '').trim();
        const api = root.dataset.api || '/api/crm/contact-history';
        const body = getBody(root);
        if (root.dataset.canSaveLead === '0') {
            opts.canSaveLead = false;
        }

        root.hidden = false;
        root.removeAttribute('hidden');
        root.classList.add('chp-visible');

        if (!phone && !email && !name) {
            body.innerHTML = '<p class="chp-empty">No phone, email, or name on this conversation to look up history.</p>';
            return null;
        }

        body.innerHTML = '<p class="chp-empty">Loading contact history…</p>';

        const q = new URLSearchParams();
        if (phone) q.set('phone', phone);
        if (email) q.set('email', email);
        if (name) q.set('name', name);
        q.set('limit', String(opts.limit || 60));

        try {
            const res = await fetch(api + '?' + q.toString(), {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.error || data.message || 'Failed to load history');
            renderPanel(body, data, opts);
            return data;
        } catch (err) {
            body.innerHTML = `<p class="chp-empty">${esc(err.message || 'Could not load contact history.')}</p>`;
            return null;
        }
    }

    function clear(rootOrSelector) {
        const root = typeof rootOrSelector === 'string'
            ? document.querySelector(rootOrSelector)
            : rootOrSelector;
        if (!root) return;
        root.hidden = true;
        root.setAttribute('hidden', '');
        root.classList.remove('chp-visible');
        getBody(root).innerHTML = '<p class="chp-empty">Select a conversation to see history across WhatsApp, Viber, SMS, Inbox, Calls, and Facebook.</p>';
    }

    if (!window.LnsContactHistory) {
        window.LnsContactHistory = { load, clear, renderPanel, saveAsLead };
    }

    window.loadChannelContactHistory = function (selector, opts) {
        if (window.LnsContactHistory && typeof window.LnsContactHistory.load === 'function') {
            return window.LnsContactHistory.load(selector, opts);
        }
        return load(selector, opts);
    };
})();
</script>
