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

    function assignedLeadPanelHtml(lead) {
        if (!lead) return '';
        const assignee = lead.assigned_user && lead.assigned_user.name;
        const line = assignee
            ? `<div class="chp-assigned">Assigned to ${esc(assignee)}${lead.status ? ' · ' + esc(lead.status) : ''}</div>`
            : `<div class="chp-meta">Lead${lead.status ? ' · ' + esc(lead.status) : ''} · Unassigned</div>`;
        const chips = leadLabelChipsHtml(lead.labels);
        const link = lead.crm_url
            ? `<a class="chp-link" href="${esc(lead.crm_url)}" target="_blank" rel="noopener">Open lead →</a>`
            : '';
        return line + chips + link;
    }

    function getBody(root) {
        return root.querySelector('.chp-body') || root;
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
        const displayName = placeholder && extractedName ? extractedName : (contact.display_name || extractedName || 'Contact');
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
            ${assignedLeadPanelHtml(contact.lead)}
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
        if (isPlaceholderName(name) && extractedName) {
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
