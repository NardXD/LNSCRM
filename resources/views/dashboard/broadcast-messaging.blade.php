@extends('layouts.app')

@section('title', 'Broadcast Messaging')

@section('content')
    @if(session('status') === 'outlook-mail-connected')
        <div class="flash-alert flash-alert-success" role="alert">Microsoft 365 mailbox connected. You can now use it as an email sender.</div>
    @endif

    <div class="bc-page" id="broadcastApp"
         data-api-base="{{ url('api/broadcast') }}"
         data-csrf="{{ csrf_token() }}"
         data-can-sms="{{ !empty($canSendSms) ? '1' : '0' }}"
         data-can-email="{{ !empty($canSendEmail) ? '1' : '0' }}"
         data-twilio="{{ !empty($twilioConnected) ? '1' : '0' }}"
         data-outlook="{{ !empty($outlookConfigured) ? '1' : '0' }}">

        <div class="page-header bc-header">
            <div>
                <h1 class="page-title">Broadcast Messaging</h1>
                <p class="page-subtitle">Send bulk SMS and email messages, then track delivery results</p>
            </div>
            <button type="button" class="btn btn-primary" id="btnNew" {{ (empty($canSendSms) && empty($canSendEmail)) ? 'disabled' : '' }}>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New Broadcast
            </button>
        </div>

        <div id="viewList">
            <div class="bc-toolbar">
                <div class="bc-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    <input type="search" id="listSearch" placeholder="Search broadcasts...">
                </div>
                <select id="listType" class="bc-select">
                    <option value="">All types</option>
                    <option value="sms">SMS</option>
                    <option value="email">Email</option>
                </select>
                <select id="listStatus" class="bc-select">
                    <option value="all">All statuses</option>
                    <option value="sending">Sending</option>
                    <option value="sent">Sent</option>
                    <option value="partial">Partial</option>
                    <option value="failed">Failed</option>
                </select>
            </div>
            <div class="bc-card">
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Broadcast name</th>
                                <th>Type</th>
                                <th>Sender</th>
                                <th>Recipients</th>
                                <th>Status</th>
                                <th>Date created</th>
                                <th>Date sent</th>
                            </tr>
                        </thead>
                        <tbody id="listBody">
                            <tr><td colspan="7" class="bc-empty">Loading broadcasts…</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="bc-pager" id="listPager"></div>
            </div>
        </div>

        <div id="viewWizard" hidden>
            <div class="bc-steps">
                <button type="button" class="bc-step active" data-step="1">1. Setup</button>
                <button type="button" class="bc-step" data-step="2">2. Recipients</button>
                <button type="button" class="bc-step" data-step="3">3. Compose</button>
                <button type="button" class="bc-step" data-step="4">4. Review</button>
            </div>

            <div class="bc-card bc-wizard-card">
                <section class="bc-panel" data-panel="1">
                    <label class="bc-label">Broadcast name</label>
                    <input type="text" id="fName" class="bc-input" maxlength="160" placeholder="e.g. August storage promotion">

                    <label class="bc-label">Type</label>
                    <div class="bc-type-row">
                        <label class="bc-type-card" id="typeSmsCard">
                            <input type="radio" name="bcType" value="sms">
                            <strong>SMS</strong>
                            <span>Send via Twilio</span>
                        </label>
                        <label class="bc-type-card" id="typeEmailCard">
                            <input type="radio" name="bcType" value="email">
                            <strong>Email</strong>
                            <span>Send via Microsoft 365</span>
                        </label>
                    </div>
                    <p class="bc-hint" id="typeHint"></p>

                    <div id="smsSenderBlock">
                        <label class="bc-label">Twilio sender number</label>
                        <select id="fFromNumber" class="bc-input"></select>
                        <p class="bc-hint">This number is used as the SMS From value. Manage numbers in Phone System or Integrations.</p>
                    </div>

                    <div id="emailSenderBlock" hidden>
                        <div class="bc-sender-head">
                            <label class="bc-label">Microsoft 365 sender</label>
                            <a href="{{ route('inbox') }}" class="btn btn-secondary" id="btnAddAccount">Manage shared mailboxes</a>
                        </div>
                        <select id="fInbox" class="bc-input"></select>
                        <p class="bc-hint">Only shared Microsoft 365 mailboxes you belong to can send broadcasts. Personal mailboxes are not listed. Add or connect shared mailboxes in Inbox.</p>
                    </div>
                </section>

                <section class="bc-panel" data-panel="2" hidden>
                    <div class="bc-recip-layout">
                        <div>
                            <div class="bc-recip-tools">
                                <input type="search" id="recipSearch" class="bc-input" placeholder="Search leads, clients, and contacts…">
                                <select id="recipSource" class="bc-select">
                                    <option value="all">All sources</option>
                                    <option value="leads">Leads</option>
                                    <option value="clients">Clients</option>
                                    <option value="contacts">Contacts</option>
                                </select>
                            </div>
                            <div class="bc-recip-results" id="recipResults">
                                <div class="bc-empty">Search to find people with a phone number or email address.</div>
                            </div>
                            <label class="bc-label">Or paste addresses (one per line)</label>
                            <textarea id="recipPaste" class="bc-input" rows="4" placeholder="+15551234567 or name@example.com"></textarea>
                            <button type="button" class="btn btn-secondary" id="btnPaste">Add pasted addresses</button>
                        </div>
                        <div class="bc-selected">
                            <div class="bc-selected-head">
                                <strong>Selected</strong>
                                <span id="selectedCount">0</span>
                            </div>
                            <div id="selectedList" class="bc-selected-list">
                                <div class="bc-empty">No recipients yet.</div>
                            </div>
                            <button type="button" class="btn btn-secondary" id="btnClearSelected">Clear all</button>
                        </div>
                    </div>
                </section>

                <section class="bc-panel" data-panel="3" hidden>
                    <div id="emailSubjectBlock" hidden>
                        <label class="bc-label">Subject</label>
                        <input type="text" id="fSubject" class="bc-input" maxlength="500" placeholder="Email subject">
                    </div>
                    <label class="bc-label" id="bodyLabel">Message</label>
                    <textarea id="fBody" class="bc-input" rows="10" placeholder="Write your message…"></textarea>
                    <div class="bc-char" id="charCount"></div>
                </section>

                <section class="bc-panel" data-panel="4" hidden>
                    <div class="bc-review" id="reviewSummary"></div>
                    <h3 class="bc-review-title">Recipient list</h3>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr><th>Name</th><th>Address</th><th>Source</th></tr>
                            </thead>
                            <tbody id="reviewRecipients"></tbody>
                        </table>
                    </div>
                </section>

                <div class="bc-wizard-actions">
                    <button type="button" class="btn btn-secondary" id="btnCancel">Cancel</button>
                    <div class="bc-wizard-nav">
                        <button type="button" class="btn btn-secondary" id="btnBack" hidden>Back</button>
                        <button type="button" class="btn btn-primary" id="btnNext">Continue</button>
                    </div>
                </div>
            </div>
        </div>

        <div id="viewDetail" hidden>
            <button type="button" class="btn btn-secondary bc-back" id="btnBackList">Back to broadcasts</button>
            <div class="bc-detail-head">
                <div>
                    <h2 id="detailName" class="bc-detail-title"></h2>
                    <p id="detailMeta" class="page-subtitle"></p>
                </div>
                <div id="detailStatus"></div>
            </div>
            <div class="bc-stats" id="detailStats"></div>
            <div class="bc-card">
                <div class="bc-detail-body">
                    <div>
                        <h3 class="bc-review-title">Message</h3>
                        <div id="detailMessage" class="bc-message-preview"></div>
                    </div>
                    <div>
                        <h3 class="bc-review-title">Results</h3>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Address</th>
                                        <th>Status</th>
                                        <th>Error</th>
                                    </tr>
                                </thead>
                                <tbody id="detailRecipients"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
    .bc-page { max-width: 1200px; }
    .bc-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; }
    .bc-toolbar { display: flex; gap: 0.75rem; margin-bottom: 1rem; flex-wrap: wrap; }
    .bc-search { position: relative; flex: 1; min-width: 220px; }
    .bc-search svg { position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: var(--text-muted); }
    .bc-search input, .bc-select, .bc-input {
        width: 100%; padding: 0.625rem 0.875rem; border: 1px solid var(--border); border-radius: 8px;
        font-size: 0.875rem; font-family: inherit; background: #fff; color: var(--text-primary);
    }
    .bc-search input { padding-left: 2.25rem; }
    .bc-select { width: auto; min-width: 140px; }
    .bc-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
    .table-container { overflow-x: auto; }
    .data-table { width: 100%; border-collapse: collapse; }
    .data-table th { padding: 0.875rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.04em; border-bottom: 1px solid var(--border); background: var(--bg-primary); }
    .data-table td { padding: 0.9rem 1rem; font-size: 0.875rem; border-bottom: 1px solid var(--border); }
    .data-table tbody tr { cursor: pointer; }
    .data-table tbody tr:hover { background: var(--bg-primary); }
    .bc-empty { text-align: center; color: var(--text-secondary); padding: 2rem 1rem !important; cursor: default; }
    .bc-badge { display: inline-flex; align-items: center; padding: 0.2rem 0.65rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; }
    .bc-badge.sms { background: #eef2ff; color: #4338ca; }
    .bc-badge.email { background: #eff6ff; color: #1d4ed8; }
    .bc-badge.sending { background: #fff7ed; color: #c2410c; }
    .bc-badge.sent, .bc-badge.delivered { background: #ecfdf5; color: #047857; }
    .bc-badge.partial { background: #fef3c7; color: #b45309; }
    .bc-badge.failed, .bc-badge.undelivered { background: #fef2f2; color: #b91c1c; }
    .bc-badge.pending { background: #f3f4f6; color: #4b5563; }
    .bc-pager { display: flex; justify-content: flex-end; gap: 0.5rem; padding: 0.75rem 1rem; }
    .bc-steps { display: flex; gap: 0.5rem; margin-bottom: 1rem; flex-wrap: wrap; }
    .bc-step { border: 1px solid var(--border); background: #fff; border-radius: 999px; padding: 0.45rem 0.9rem; font-size: 0.8125rem; color: var(--text-secondary); cursor: pointer; }
    .bc-step.active { background: var(--accent-light); color: var(--accent); border-color: transparent; font-weight: 600; }
    .bc-wizard-card { padding: 1.5rem; }
    .bc-label { display: block; font-size: 0.8125rem; font-weight: 600; margin: 1rem 0 0.4rem; }
    .bc-hint { font-size: 0.8125rem; color: var(--text-secondary); margin-top: 0.4rem; }
    .bc-type-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
    .bc-type-card { border: 1px solid var(--border); border-radius: 10px; padding: 1rem; cursor: pointer; display: flex; flex-direction: column; gap: 0.25rem; }
    .bc-type-card:has(input:checked) { border-color: var(--accent); background: var(--accent-light); }
    .bc-type-card input { accent-color: var(--accent); }
    .bc-type-card span { font-size: 0.8125rem; color: var(--text-secondary); }
    .bc-sender-head { display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; }
    .bc-recip-layout { display: grid; grid-template-columns: 1.4fr 0.9fr; gap: 1rem; }
    .bc-recip-tools { display: flex; gap: 0.5rem; margin-bottom: 0.75rem; }
    .bc-recip-results { border: 1px solid var(--border); border-radius: 10px; max-height: 320px; overflow: auto; margin-bottom: 1rem; }
    .bc-recip-row { display: flex; gap: 0.75rem; align-items: flex-start; padding: 0.7rem 0.85rem; border-bottom: 1px solid var(--border); cursor: pointer; }
    .bc-recip-row:hover { background: var(--bg-primary); }
    .bc-recip-row small { display: block; color: var(--text-secondary); }
    .bc-selected { border: 1px solid var(--border); border-radius: 10px; padding: 0.85rem; background: var(--bg-primary); }
    .bc-selected-head { display: flex; justify-content: space-between; margin-bottom: 0.75rem; }
    .bc-selected-list { max-height: 360px; overflow: auto; margin-bottom: 0.75rem; }
    .bc-chip { display: flex; justify-content: space-between; gap: 0.5rem; background: #fff; border: 1px solid var(--border); border-radius: 8px; padding: 0.5rem 0.65rem; margin-bottom: 0.4rem; font-size: 0.8125rem; }
    .bc-chip button { border: 0; background: none; color: #b91c1c; cursor: pointer; }
    .bc-char { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.4rem; text-align: right; }
    .bc-wizard-actions { display: flex; justify-content: space-between; gap: 0.75rem; margin-top: 1.5rem; }
    .bc-wizard-nav { display: flex; gap: 0.5rem; }
    .bc-review { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem; margin-bottom: 1.25rem; }
    .bc-review-item { background: var(--bg-primary); border-radius: 10px; padding: 0.85rem; }
    .bc-review-item span { display: block; font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 0.25rem; }
    .bc-review-title { font-size: 0.95rem; margin: 0 0 0.75rem; }
    .bc-back { margin-bottom: 1rem; }
    .bc-detail-head { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; margin-bottom: 1rem; }
    .bc-detail-title { font-size: 1.25rem; margin-bottom: 0.35rem; }
    .bc-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.75rem; margin-bottom: 1rem; }
    .bc-stat { background: #fff; border: 1px solid var(--border); border-radius: 10px; padding: 0.9rem; }
    .bc-stat span { display: block; font-size: 0.75rem; color: var(--text-secondary); }
    .bc-stat strong { font-size: 1.25rem; }
    .bc-detail-body { display: grid; grid-template-columns: 0.9fr 1.4fr; gap: 1.25rem; padding: 1.25rem; }
    .bc-message-preview { white-space: pre-wrap; background: var(--bg-primary); border-radius: 10px; padding: 1rem; font-size: 0.875rem; min-height: 120px; }
    .bc-type-card.is-disabled { opacity: 0.5; pointer-events: none; }
    @media (max-width: 900px) {
        .bc-recip-layout, .bc-detail-body, .bc-stats, .bc-type-row { grid-template-columns: 1fr; }
        .bc-header { flex-direction: column; }
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const root = document.getElementById('broadcastApp');
    const API = root.dataset.apiBase;
    const CSRF = root.dataset.csrf;
    const canSms = root.dataset.canSms === '1';
    const canEmail = root.dataset.canEmail === '1';

    const state = {
        view: 'list',
        step: 1,
        page: 1,
        bootstrap: null,
        selected: new Map(),
        current: null,
        poll: null,
        type: canSms ? 'sms' : 'email',
    };

    const el = (id) => document.getElementById(id);

    function headers() {
        return { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' };
    }

    async function api(path, options = {}) {
        const res = await fetch(API + path, { credentials: 'same-origin', ...options, headers: { ...headers(), ...(options.headers || {}) } });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.message || 'Request failed');
        return data;
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (ch) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
    }

    function formatDate(value) {
        if (!value) return '—';
        const d = new Date(value);
        if (Number.isNaN(d.getTime())) return '—';
        return d.toLocaleString();
    }

    function badge(kind, label) {
        return `<span class="bc-badge ${kind}">${escapeHtml(label)}</span>`;
    }

    function statusLabel(status) {
        return ({ sending: 'Sending', sent: 'Sent', partial: 'Partial', failed: 'Failed', delivered: 'Delivered', undelivered: 'Undelivered', pending: 'Pending' })[status] || status;
    }

    function showView(name) {
        state.view = name;
        el('viewList').hidden = name !== 'list';
        el('viewWizard').hidden = name !== 'wizard';
        el('viewDetail').hidden = name !== 'detail';
        if (name !== 'detail' && state.poll) {
            clearInterval(state.poll);
            state.poll = null;
        }
    }

    function recipientKey(row) {
        return `${row.source || 'manual'}:${row.source_id || ''}:${String(row.address || '').toLowerCase()}`;
    }

    function setStep(step) {
        state.step = step;
        document.querySelectorAll('.bc-step').forEach((btn) => btn.classList.toggle('active', Number(btn.dataset.step) === step));
        document.querySelectorAll('.bc-panel').forEach((panel) => { panel.hidden = Number(panel.dataset.panel) !== step; });
        el('btnBack').hidden = step === 1;
        el('btnNext').textContent = step === 4 ? 'Send broadcast' : 'Continue';
        if (step === 2) searchRecipients();
        if (step === 3) updateCompose();
        if (step === 4) renderReview();
    }

    function selectedType() {
        return document.querySelector('input[name="bcType"]:checked')?.value || state.type;
    }

    function fillSenders() {
        const data = state.bootstrap || {};
        const smsSelect = el('fFromNumber');
        smsSelect.innerHTML = (data.sms_senders || []).map((n) =>
            `<option value="${escapeHtml(n.phone_number)}">${escapeHtml((n.friendly_name ? n.friendly_name + ' — ' : '') + n.phone_number)}${n.assigned ? ' (assigned)' : ''}</option>`
        ).join('') || '<option value="">No Twilio SMS numbers found</option>';

        const emailSelect = el('fInbox');
        emailSelect.innerHTML = (data.email_senders || []).map((inbox) =>
            `<option value="${inbox.id}" ${inbox.connected ? '' : 'disabled'}>${escapeHtml(inbox.name)} — ${escapeHtml(inbox.email || 'No address')}${inbox.connected ? '' : ' (not connected)'}</option>`
        ).join('') || '<option value="">No shared Microsoft 365 mailboxes available</option>';
    }

    function applyType() {
        const type = selectedType();
        state.type = type;
        el('smsSenderBlock').hidden = type !== 'sms';
        el('emailSenderBlock').hidden = type !== 'email';
        el('emailSubjectBlock').hidden = type !== 'email';
        el('bodyLabel').textContent = type === 'email' ? 'Email body' : 'SMS message';
        el('typeHint').textContent = type === 'sms'
            ? (state.bootstrap?.twilio_connected ? '' : 'Connect Twilio in Integrations before sending SMS broadcasts.')
            : (state.bootstrap?.outlook_configured ? '' : 'Add Microsoft OAuth credentials in Integrations before connecting a mailbox.');
        updateCompose();
        state.selected.clear();
        renderSelected();
        searchRecipients();
    }

    function updateCompose() {
        const body = el('fBody').value || '';
        if (selectedType() === 'sms') {
            el('charCount').textContent = `${body.length} / 1600 characters`;
        } else {
            el('charCount').textContent = `${body.length} characters`;
        }
    }

    function renderSelected() {
        const items = [...state.selected.values()];
        el('selectedCount').textContent = items.length;
        el('selectedList').innerHTML = items.length
            ? items.map((row) => `<div class="bc-chip"><div><strong>${escapeHtml(row.name || row.address)}</strong><small style="display:block;color:var(--text-secondary)">${escapeHtml(row.address)}</small></div><button type="button" data-key="${escapeHtml(recipientKey(row))}">Remove</button></div>`).join('')
            : '<div class="bc-empty">No recipients yet.</div>';
        el('selectedList').querySelectorAll('button[data-key]').forEach((btn) => {
            btn.addEventListener('click', () => {
                state.selected.delete(btn.dataset.key);
                renderSelected();
            });
        });
    }

    async function searchRecipients() {
        const box = el('recipResults');
        box.innerHTML = '<div class="bc-empty">Searching…</div>';
        try {
            const params = new URLSearchParams({
                channel: selectedType(),
                q: el('recipSearch').value.trim(),
                source: el('recipSource').value,
            });
            const data = await api('/recipients?' + params.toString());
            const rows = data.data || [];
            if (!rows.length) {
                box.innerHTML = '<div class="bc-empty">No matching people with a valid address.</div>';
                return;
            }
            box.innerHTML = rows.map((row) => {
                const key = recipientKey(row);
                const checked = state.selected.has(key) ? 'checked' : '';
                return `<label class="bc-recip-row">
                    <input type="checkbox" data-key="${escapeHtml(key)}" ${checked}>
                    <div>
                        <strong>${escapeHtml(row.name || row.address)}</strong>
                        <small>${escapeHtml(row.address)} · ${escapeHtml(row.meta || row.source)}</small>
                    </div>
                </label>`;
            }).join('');
            box.querySelectorAll('input[type="checkbox"]').forEach((input, index) => {
                input.addEventListener('change', () => {
                    const row = rows[index];
                    const key = recipientKey(row);
                    if (input.checked) state.selected.set(key, row);
                    else state.selected.delete(key);
                    renderSelected();
                });
            });
        } catch (err) {
            box.innerHTML = `<div class="bc-empty">${escapeHtml(err.message)}</div>`;
        }
    }

    function addPasted() {
        const lines = el('recipPaste').value.split(/\r?\n/).map((line) => line.trim()).filter(Boolean);
        lines.forEach((address) => {
            const row = { source: 'manual', source_id: null, name: address, address, meta: 'Manual' };
            state.selected.set(recipientKey(row), row);
        });
        el('recipPaste').value = '';
        renderSelected();
    }

    function renderReview() {
        const type = selectedType();
        const sender = type === 'sms'
            ? (el('fFromNumber').selectedOptions[0]?.textContent || el('fFromNumber').value)
            : (el('fInbox').selectedOptions[0]?.textContent || 'Microsoft 365 mailbox');
        const recipients = [...state.selected.values()];
        el('reviewSummary').innerHTML = [
            ['Name', el('fName').value.trim()],
            ['Type', type.toUpperCase()],
            ['Sender', sender],
            ['Recipients', String(recipients.length)],
            ...(type === 'email' ? [['Subject', el('fSubject').value.trim()]] : []),
        ].map(([label, value]) => `<div class="bc-review-item"><span>${escapeHtml(label)}</span><strong>${escapeHtml(value || '—')}</strong></div>`).join('');
        el('reviewRecipients').innerHTML = recipients.length
            ? recipients.map((row) => `<tr><td>${escapeHtml(row.name || '—')}</td><td>${escapeHtml(row.address)}</td><td>${escapeHtml(row.meta || row.source)}</td></tr>`).join('')
            : '<tr><td colspan="3" class="bc-empty">No recipients selected.</td></tr>';
    }

    function validateStep(step) {
        if (step === 1) {
            if (!el('fName').value.trim()) return 'Enter a broadcast name.';
            if (selectedType() === 'sms') {
                if (!canSms) return 'You do not have permission to send SMS broadcasts.';
                if (!el('fFromNumber').value) return 'Select a Twilio phone number.';
            } else {
                if (!canEmail) return 'You do not have permission to send email broadcasts.';
                if (!el('fInbox').value) return 'Select a shared Microsoft 365 mailbox.';
            }
        }
        if (step === 2 && state.selected.size === 0) return 'Select at least one recipient.';
        if (step === 3) {
            if (selectedType() === 'email' && !el('fSubject').value.trim()) return 'Enter an email subject.';
            if (!el('fBody').value.trim()) return 'Compose a message.';
            if (selectedType() === 'sms' && el('fBody').value.length > 1600) return 'SMS messages can be at most 1600 characters.';
        }
        return null;
    }

    async function sendBroadcast() {
        const error = validateStep(4) || validateStep(1) || validateStep(2) || validateStep(3);
        if (error) { alert(error); return; }
        el('btnNext').disabled = true;
        el('btnNext').textContent = 'Sending…';
        try {
            const payload = {
                name: el('fName').value.trim(),
                type: selectedType(),
                from_number: el('fFromNumber').value || null,
                shared_inbox_id: el('fInbox').value ? Number(el('fInbox').value) : null,
                subject: el('fSubject').value.trim(),
                body: el('fBody').value,
                recipients: [...state.selected.values()].map((row) => ({
                    source: row.source,
                    source_id: row.source_id,
                    name: row.name,
                    address: row.address,
                })),
            };
            const data = await api('/campaigns', { method: 'POST', body: JSON.stringify(payload) });
            await openDetail(data.data.id);
        } catch (err) {
            alert(err.message);
        } finally {
            el('btnNext').disabled = false;
            el('btnNext').textContent = 'Send broadcast';
        }
    }

    async function loadList() {
        const params = new URLSearchParams({
            q: el('listSearch').value.trim(),
            type: el('listType').value,
            status: el('listStatus').value,
            page: String(state.page),
        });
        const data = await api('/campaigns?' + params.toString());
        const rows = data.data || [];
        el('listBody').innerHTML = rows.length
            ? rows.map((row) => `<tr data-id="${row.id}">
                <td><strong>${escapeHtml(row.name)}</strong></td>
                <td>${badge(row.type, row.type === 'sms' ? 'SMS' : 'Email')}</td>
                <td>${escapeHtml(row.sender || '—')}</td>
                <td>${row.recipient_count}</td>
                <td>${badge(row.status, statusLabel(row.status))}</td>
                <td>${escapeHtml(formatDate(row.created_at))}</td>
                <td>${escapeHtml(formatDate(row.sent_at))}</td>
            </tr>`).join('')
            : '<tr><td colspan="7" class="bc-empty">No broadcasts yet. Create one to send bulk SMS or email.</td></tr>';
        el('listBody').querySelectorAll('tr[data-id]').forEach((row) => {
            row.addEventListener('click', () => openDetail(Number(row.dataset.id)));
        });
        const pg = data.pagination || { current_page: 1, last_page: 1 };
        el('listPager').innerHTML = pg.last_page > 1
            ? `<button type="button" class="btn btn-secondary" ${pg.current_page <= 1 ? 'disabled' : ''} data-page="${pg.current_page - 1}">Previous</button>
               <button type="button" class="btn btn-secondary" ${pg.current_page >= pg.last_page ? 'disabled' : ''} data-page="${pg.current_page + 1}">Next</button>`
            : '';
        el('listPager').querySelectorAll('button[data-page]').forEach((btn) => {
            btn.addEventListener('click', () => { state.page = Number(btn.dataset.page); loadList(); });
        });
    }

    function renderDetail(campaign) {
        el('detailName').textContent = campaign.name;
        el('detailMeta').textContent = `${campaign.type === 'sms' ? 'SMS' : 'Email'} · ${campaign.sender || 'No sender'} · Created ${formatDate(campaign.created_at)}`;
        el('detailStatus').innerHTML = badge(campaign.status, statusLabel(campaign.status));
        el('detailStats').innerHTML = [
            ['Recipients', campaign.recipient_count],
            ['Sent', campaign.sent_count],
            [campaign.type === 'sms' ? 'Delivered' : 'Successful', campaign.delivered_count],
            ['Failed', campaign.failed_count],
        ].map(([label, value]) => `<div class="bc-stat"><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong></div>`).join('');
        const body = campaign.type === 'email' && campaign.subject
            ? `Subject: ${campaign.subject}\n\n${campaign.body}`
            : campaign.body;
        el('detailMessage').textContent = body || '';
        const recipients = campaign.recipients || [];
        el('detailRecipients').innerHTML = recipients.length
            ? recipients.map((row) => `<tr>
                <td>${escapeHtml(row.name || '—')}</td>
                <td>${escapeHtml(row.address)}</td>
                <td>${badge(row.status, statusLabel(row.status))}</td>
                <td>${escapeHtml(row.error_message || '—')}</td>
            </tr>`).join('')
            : '<tr><td colspan="4" class="bc-empty">No recipient results yet.</td></tr>';
    }

    async function openDetail(id) {
        const data = await api('/campaigns/' + id);
        state.current = data.data;
        renderDetail(state.current);
        showView('detail');
        if (state.current.status === 'sending') {
            state.poll = setInterval(async () => {
                try {
                    const fresh = await api('/campaigns/' + id);
                    state.current = fresh.data;
                    renderDetail(state.current);
                    if (state.current.status !== 'sending') {
                        clearInterval(state.poll);
                        state.poll = null;
                        loadList();
                    }
                } catch (_) {}
            }, 2500);
        }
    }

    function resetWizard() {
        el('fName').value = '';
        el('fSubject').value = '';
        el('fBody').value = '';
        el('recipSearch').value = '';
        el('recipPaste').value = '';
        state.selected.clear();
        renderSelected();
        const smsRadio = document.querySelector('input[name="bcType"][value="sms"]');
        const emailRadio = document.querySelector('input[name="bcType"][value="email"]');
        if (canSms && smsRadio) smsRadio.checked = true;
        else if (emailRadio) emailRadio.checked = true;
        applyType();
        setStep(1);
    }

    async function boot() {
        if (!canSms) el('typeSmsCard').classList.add('is-disabled');
        if (!canEmail) el('typeEmailCard').classList.add('is-disabled');
        const data = await api('/bootstrap');
        state.bootstrap = data.data;
        fillSenders();
        resetWizard();
        showView('list');
        await loadList();
    }

    el('btnNew').addEventListener('click', () => { resetWizard(); showView('wizard'); });
    el('btnCancel').addEventListener('click', () => showView('list'));
    el('btnBackList').addEventListener('click', () => { showView('list'); loadList(); });
    el('btnBack').addEventListener('click', () => setStep(Math.max(1, state.step - 1)));
    el('btnNext').addEventListener('click', () => {
        if (state.step === 4) { sendBroadcast(); return; }
        const error = validateStep(state.step);
        if (error) { alert(error); return; }
        setStep(state.step + 1);
    });
    document.querySelectorAll('.bc-step').forEach((btn) => {
        btn.addEventListener('click', () => {
            const next = Number(btn.dataset.step);
            if (next > state.step) {
                const error = validateStep(state.step);
                if (error) { alert(error); return; }
            }
            setStep(next);
        });
    });
    document.querySelectorAll('input[name="bcType"]').forEach((input) => input.addEventListener('change', applyType));
    el('fBody').addEventListener('input', updateCompose);
    el('recipSearch').addEventListener('input', () => { clearTimeout(state.searchTimer); state.searchTimer = setTimeout(searchRecipients, 250); });
    el('recipSource').addEventListener('change', searchRecipients);
    el('btnPaste').addEventListener('click', addPasted);
    el('btnClearSelected').addEventListener('click', () => { state.selected.clear(); renderSelected(); searchRecipients(); });
    ['listSearch', 'listType', 'listStatus'].forEach((id) => {
        el(id).addEventListener('change', () => { state.page = 1; loadList(); });
        el(id).addEventListener('input', () => { state.page = 1; clearTimeout(state.listTimer); state.listTimer = setTimeout(loadList, 250); });
    });

    boot().catch((err) => {
        el('listBody').innerHTML = `<tr><td colspan="7" class="bc-empty">${escapeHtml(err.message)}</td></tr>`;
    });
})();
</script>
@endpush
