@extends('layouts.app')

@section('title', 'Contact History')

@section('content')
<div class="ch-page" id="contactHistoryApp"
     data-api="/api/crm/contact-history"
     data-csrf="{{ csrf_token() }}">
    <div class="ch-header">
        <div>
            <h1>Contact History</h1>
            <p class="ch-sub">Search by phone or email to see conversations across WhatsApp, Viber, SMS, Inbox, Calls, and Facebook.</p>
        </div>
    </div>

    <form class="ch-search" id="chSearchForm">
        <div class="ch-field">
            <label for="chPhone">Phone</label>
            <input type="tel" id="chPhone" name="phone" placeholder="+63917… or 0917…" autocomplete="tel">
        </div>
        <div class="ch-field">
            <label for="chEmail">Email</label>
            <input type="email" id="chEmail" name="email" placeholder="name@company.com" autocomplete="email">
        </div>
        <button type="submit" class="ch-btn" id="chSearchBtn">Search</button>
    </form>

    <p class="ch-status" id="chStatus">Enter a phone number and/or email, then search.</p>

    <div class="ch-results" id="chResults" hidden>
        <section class="ch-card" id="chContactCard"></section>

        <div class="ch-grid">
            <section class="ch-card">
                <h2>Channels</h2>
                <div id="chThreads"></div>
            </section>
            <section class="ch-card">
                <h2>Timeline</h2>
                <div id="chEvents"></div>
            </section>
        </div>

        <p class="ch-notes" id="chNotes"></p>
    </div>
</div>

<style>
.ch-page { max-width: 1100px; margin: 0 auto; padding: 1.25rem 1.5rem 2.5rem; }
.ch-header h1 { margin: 0 0 0.35rem; font-size: 1.55rem; font-weight: 700; color: var(--text-primary, #1a2332); }
.ch-sub { margin: 0; color: var(--text-secondary, #5b6b7c); font-size: 0.95rem; line-height: 1.45; }
.ch-search {
    display: grid;
    grid-template-columns: 1fr 1fr auto;
    gap: 0.75rem;
    align-items: end;
    margin: 1.35rem 0 1rem;
    padding: 1rem;
    background: var(--bg-secondary, #fff);
    border: 1px solid var(--border, #d8dee6);
    border-radius: 10px;
}
.ch-field label { display: block; font-size: 0.78rem; font-weight: 600; color: var(--text-secondary, #5b6b7c); margin-bottom: 0.35rem; }
.ch-field input {
    width: 100%;
    padding: 0.6rem 0.75rem;
    border: 1px solid var(--border, #d8dee6);
    border-radius: 8px;
    background: var(--bg-primary, #f7f8fa);
    color: var(--text-primary, #1a2332);
    font-size: 0.95rem;
}
.ch-btn {
    padding: 0.65rem 1.15rem;
    border: 0;
    border-radius: 8px;
    background: #0b5cab;
    color: #fff;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
}
.ch-btn:disabled { opacity: 0.6; cursor: wait; }
.ch-status { color: var(--text-secondary, #5b6b7c); font-size: 0.9rem; margin: 0 0 1rem; }
.ch-grid { display: grid; grid-template-columns: 1fr 1.4fr; gap: 1rem; }
.ch-card {
    background: var(--bg-secondary, #fff);
    border: 1px solid var(--border, #d8dee6);
    border-radius: 10px;
    padding: 1rem 1.1rem;
    margin-bottom: 1rem;
}
.ch-card h2 { margin: 0 0 0.75rem; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-secondary, #5b6b7c); }
.ch-contact-name { font-size: 1.2rem; font-weight: 700; margin: 0 0 0.35rem; }
.ch-meta { color: var(--text-secondary, #5b6b7c); font-size: 0.88rem; margin: 0.2rem 0; }
.ch-link { color: #0b5cab; font-weight: 600; text-decoration: none; font-size: 0.9rem; }
.ch-thread, .ch-event {
    padding: 0.7rem 0;
    border-bottom: 1px solid var(--border, #e6ebf0);
}
.ch-thread:last-child, .ch-event:last-child { border-bottom: 0; }
.ch-badge {
    display: inline-block;
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    padding: 0.15rem 0.45rem;
    border-radius: 4px;
    background: #e8f1fb;
    color: #0b5cab;
    margin-right: 0.4rem;
}
.ch-badge.viber { background: #efeaff; color: #5b3cc4; }
.ch-badge.sms { background: #e8f8ef; color: #0f7b4c; }
.ch-badge.inbox { background: #fff4e5; color: #b45309; }
.ch-badge.call { background: #f1f3f5; color: #495057; }
.ch-badge.facebook { background: #e8f1ff; color: #1877f2; }
.ch-thread-title { font-weight: 600; color: var(--text-primary, #1a2332); margin: 0.25rem 0; }
.ch-preview { font-size: 0.88rem; color: var(--text-secondary, #5b6b7c); margin: 0; }
.ch-empty { color: var(--text-secondary, #5b6b7c); font-size: 0.9rem; }
.ch-notes { font-size: 0.8rem; color: var(--text-secondary, #5b6b7c); line-height: 1.45; }
.ch-dir { font-size: 0.75rem; color: var(--text-secondary, #5b6b7c); }
@media (max-width: 800px) {
    .ch-search, .ch-grid { grid-template-columns: 1fr; }
}
</style>

<script>
(function () {
    const root = document.getElementById('contactHistoryApp');
    if (!root) return;
    const api = root.dataset.api;
    const form = document.getElementById('chSearchForm');
    const statusEl = document.getElementById('chStatus');
    const results = document.getElementById('chResults');
    const btn = document.getElementById('chSearchBtn');

    const params = new URLSearchParams(window.location.search);
    if (params.get('phone')) document.getElementById('chPhone').value = params.get('phone');
    if (params.get('email')) document.getElementById('chEmail').value = params.get('email');
    if (params.get('phone') || params.get('email')) {
        setTimeout(() => form.requestSubmit(), 50);
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const phone = document.getElementById('chPhone').value.trim();
        const email = document.getElementById('chEmail').value.trim();
        if (!phone && !email) {
            statusEl.textContent = 'Enter a phone number and/or email.';
            results.hidden = true;
            return;
        }

        btn.disabled = true;
        statusEl.textContent = 'Searching…';
        try {
            const q = new URLSearchParams();
            if (phone) q.set('phone', phone);
            if (email) q.set('email', email);
            const res = await fetch(api + '?' + q.toString(), {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.error || 'Search failed');
            render(data);
            statusEl.textContent = data.contact?.found
                ? ('Showing history for ' + (data.contact.display_name || 'this contact'))
                : 'No CRM match — showing any channel threads that match the search.';
            results.hidden = false;

            const url = new URL(window.location.href);
            if (phone) url.searchParams.set('phone', phone); else url.searchParams.delete('phone');
            if (email) url.searchParams.set('email', email); else url.searchParams.delete('email');
            history.replaceState(null, '', url);
        } catch (err) {
            console.error(err);
            statusEl.textContent = err.message || 'Search failed.';
            results.hidden = true;
        } finally {
            btn.disabled = false;
        }
    });

    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    function badgeClass(channel) {
        return ['whatsapp','viber','sms','inbox','call','facebook'].includes(channel) ? channel : '';
    }

    function render(data) {
        const c = data.contact || {};
        const contactHtml = `
            <div class="ch-contact-name">${esc(c.display_name || 'Unknown contact')}</div>
            ${(c.matched_phones || []).map(p => `<p class="ch-meta">Phone: ${esc(p)}</p>`).join('')}
            ${(c.matched_emails || []).map(em => `<p class="ch-meta">Email: ${esc(em)}</p>`).join('')}
            ${c.lead?.crm_url ? `<p style="margin-top:0.6rem"><a class="ch-link" href="${esc(c.lead.crm_url)}">Open lead →</a></p>` : ''}
            ${c.client?.crm_url ? `<p style="margin-top:0.6rem"><a class="ch-link" href="${esc(c.client.crm_url)}">Open in Client Management →</a></p>` : ''}
        `;
        document.getElementById('chContactCard').innerHTML = contactHtml;

        const threads = data.threads || [];
        document.getElementById('chThreads').innerHTML = threads.length
            ? threads.map(t => `
                <div class="ch-thread">
                    <span class="ch-badge ${badgeClass(t.channel)}">${esc(t.label || t.channel)}</span>
                    <div class="ch-thread-title">${esc(t.title)}</div>
                    <p class="ch-preview">${esc(t.preview || '')}</p>
                    ${t.match_note ? `<p class="ch-dir">${esc(t.match_note)}</p>` : ''}
                    <a class="ch-link" href="${esc(t.deep_link)}">Open thread →</a>
                </div>`).join('')
            : '<p class="ch-empty">No channel threads found.</p>';

        const events = data.events || [];
        document.getElementById('chEvents').innerHTML = events.length
            ? events.map(ev => `
                <div class="ch-event">
                    <span class="ch-badge ${badgeClass(ev.channel)}">${esc(ev.label || ev.channel)}</span>
                    <span class="ch-dir">${esc(ev.direction || '')} · ${esc(formatAt(ev.at))}</span>
                    <p class="ch-preview">${esc(ev.preview || '')}</p>
                    ${ev.deep_link ? `<a class="ch-link" href="${esc(ev.deep_link)}">Open →</a>` : ''}
                </div>`).join('')
            : '<p class="ch-empty">No timeline events found.</p>';

        document.getElementById('chNotes').textContent = (data.notes || []).join(' ');
    }

    function formatAt(iso) {
        if (!iso) return '';
        try { return new Date(iso).toLocaleString(); } catch { return iso; }
    }
})();
</script>
@endsection
