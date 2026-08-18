/**
 * Shared contact-history side panel for channel conversation UIs.
 * Usage: window.LnsContactHistory.load(rootEl, { phone, email, name, excludeChannel, excludeId })
 */

(function injectStyles() {
    if (typeof document === 'undefined' || document.getElementById('chp-panel-styles')) return;
    const style = document.createElement('style');
    style.id = 'chp-panel-styles';
    style.textContent = `
.chp-panel{display:none;flex-direction:column;width:300px;min-width:260px;max-width:340px;border-left:1px solid var(--border,#d8dee6);background:var(--bg-primary,#f7f8fa);min-height:0;height:100%;overflow:hidden}
.chp-panel.chp-visible,.chp-panel:not([hidden]){display:flex}
.chp-header{display:flex;align-items:baseline;justify-content:space-between;gap:.5rem;padding:.85rem 1rem;border-bottom:1px solid var(--border,#d8dee6);flex-shrink:0}
.chp-header strong{font-size:.92rem;color:var(--text-primary,#1a2332)}
.chp-hint{font-size:.72rem;color:var(--text-secondary,#5b6b7c);text-transform:uppercase;letter-spacing:.04em}
.chp-body{flex:1 1 auto;min-height:0;overflow-y:auto;padding:.75rem .9rem 1.25rem}
.chp-section{margin-bottom:1.1rem}
.chp-label{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text-secondary,#5b6b7c);margin-bottom:.45rem}
.chp-name{font-weight:700;font-size:.98rem;color:var(--text-primary,#1a2332);margin-bottom:.25rem}
.chp-meta{font-size:.8rem;color:var(--text-secondary,#5b6b7c);margin:.15rem 0;word-break:break-all}
.chp-link{display:inline-block;margin-top:.45rem;margin-right:.65rem;font-size:.82rem;font-weight:600;color:#0b5cab;text-decoration:none}
.chp-save-lead{display:inline-block;margin-top:.45rem;padding:.28rem .6rem;border:1px solid #0b5cab;border-radius:6px;background:#fff;color:#0b5cab;font-size:.78rem;font-weight:600;cursor:pointer}
.chp-item{display:block;text-decoration:none;color:inherit;padding:.55rem 0;border-bottom:1px solid var(--border,#e6ebf0)}
.chp-item:last-child{border-bottom:0}
.chp-item:hover .chp-item-title{color:#0b5cab}
.chp-item-title{font-size:.86rem;font-weight:600;margin:.2rem 0;color:var(--text-primary,#1a2332)}
.chp-item-preview{font-size:.78rem;color:var(--text-secondary,#5b6b7c);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.chp-event{padding:.45rem 0;border-bottom:1px solid var(--border,#e6ebf0)}
.chp-event:last-child{border-bottom:0}
.chp-dir{font-size:.72rem;color:var(--text-secondary,#5b6b7c)}
.chp-empty{font-size:.84rem;color:var(--text-secondary,#5b6b7c);margin:0;line-height:1.4}
.chp-badge{display:inline-block;font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.03em;padding:.12rem .4rem;border-radius:4px;background:#e8f1fb;color:#0b5cab}
.chp-badge.whatsapp{background:#e8f8ef;color:#128c7e}
.chp-badge.viber{background:#efeaff;color:#5b3cc4}
.chp-badge.sms{background:#e8f8ef;color:#0f7b4c}
.chp-badge.inbox{background:#fff4e5;color:#b45309}
.chp-badge.call{background:#f1f3f5;color:#495057}
.chp-badge.facebook{background:#e8f1ff;color:#1877f2}
.chp-panel .chp-body>.chp-section:first-child{padding-bottom:.5rem;border-bottom:1px solid var(--border,#e6ebf0);margin-bottom:.9rem}
@media (max-width:900px){.wa-layout .chp-panel,.viber-layout .chp-panel,.sms-layout .chp-panel,.fb-layout .chp-panel{display:none!important}}
`;
    document.head.appendChild(style);
})();

function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    }[c]));
}

function badgeClass(channel) {
    return ['whatsapp', 'viber', 'sms', 'inbox', 'call', 'facebook'].includes(channel) ? channel : '';
}

function formatAt(iso) {
    if (!iso) return '';
    try {
        return new Date(iso).toLocaleString();
    } catch {
        return iso;
    }
}

function renderPanel(root, data, opts = {}) {
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

    const contactHtml = `
        <div class="chp-name">${esc(contact.display_name || 'Contact')}</div>
        ${(contact.matched_phones || []).slice(0, 2).map((p) => `<div class="chp-meta">${esc(p)}</div>`).join('')}
        ${(contact.matched_emails || []).slice(0, 2).map((em) => `<div class="chp-meta">${esc(em)}</div>`).join('')}
        ${contact.lead?.crm_url ? `<a class="chp-link" href="${esc(contact.lead.crm_url)}" target="_blank" rel="noopener">Open lead →</a>` : ''}
        ${contact.client?.crm_url ? `<a class="chp-link" href="${esc(contact.client.crm_url)}" target="_blank" rel="noopener">Open client →</a>` : ''}
        ${!contact.lead && opts.canSaveLead !== false ? `<button type="button" class="chp-save-lead" data-chp-save-lead>Save as lead</button>` : ''}
    `;

    const threadsHtml = threads.length
        ? threads.map((t) => `
            <a class="chp-item" href="${esc(t.deep_link || '#')}" ${t.deep_link ? '' : 'onclick="return false;"'}>
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

async function saveAsLead(bodyEl, opts, contact) {
    const name = String(contact.display_name || opts.name || opts.phone || opts.email || 'New lead').trim();
    const phones = (contact.matched_phones || []).filter(Boolean);
    if (opts.phone && !phones.length) phones.push(opts.phone);
    const emails = (contact.matched_emails || []).filter(Boolean);
    if (opts.email && !emails.length) emails.push(opts.email);
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const btn = bodyEl.querySelector('[data-chp-save-lead]');
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Saving…';
    }
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
                facebook_name: opts.name || null,
                source: opts.source || 'contact-history',
            }),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            if (data.existing_lead_id) {
                window.location.href = '/leads?lead=' + data.existing_lead_id;
                return;
            }
            throw new Error(data.message || 'Could not save lead.');
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

function getBody(root) {
    return root.querySelector('.chp-body') || root;
}

async function load(rootOrSelector, opts = {}) {
    const root = typeof rootOrSelector === 'string'
        ? document.querySelector(rootOrSelector)
        : rootOrSelector;
    if (!root) return null;

    const phone = (opts.phone || '').trim();
    const email = (opts.email || '').trim();
    const name = (opts.name || '').trim();
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
        const res = await fetch(`${api}?${q.toString()}`, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || 'Failed to load history');
        renderPanel(body, data, opts);
        return data;
    } catch (err) {
        console.error(err);
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
    const body = getBody(root);
    body.innerHTML = '<p class="chp-empty">Select a conversation to see history across WhatsApp, Viber, SMS, Inbox, Calls, and Facebook.</p>';
}

window.LnsContactHistory = { load, clear, renderPanel };
