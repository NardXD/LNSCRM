@extends('layouts.app')

@section('title', 'SMS')

@section('content')
<div class="sms-page" id="smsApp"
     data-api-base="{{ url('api/sms') }}"
     data-csrf="{{ csrf_token() }}"
     data-connected="{{ $integrationConnected ? '1' : '0' }}"
     data-can-send="{{ !empty($canSendSms) && $canSendSms ? '1' : '0' }}"
     data-twilio-number="{{ $twilioNumber ?: '' }}"
     data-integrations-url="{{ route('integrations') }}"
     data-phone-url="{{ route('twilio.call') }}">
    <div class="sms-layout">
        <aside class="sms-sidebar">
            <div class="sms-sidebar-header">
                <div>
                    <h2>SMS</h2>
                    <p class="sms-sub" id="smsAccountLabel">{{ $twilioNumber ?: 'Twilio text messages' }}</p>
                </div>
                <div class="sms-header-actions">
                    @if(!empty($canSendSms) && $canSendSms)
                    <button type="button" class="sms-icon-btn" id="smsNewBtn" title="New conversation">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    </button>
                    @endif
                    <button type="button" class="sms-icon-btn" id="smsRefreshBtn" title="Refresh">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                    </button>
                </div>
            </div>
            <div class="sms-search">
                <input type="search" id="smsSearch" placeholder="Search conversations...">
            </div>
            <div class="sms-thread-list" id="smsThreadList"></div>
        </aside>

        <main class="sms-main">
            <div class="sms-empty" id="smsEmpty">
                <div class="sms-empty-card">
                    <h3 id="smsEmptyTitle">Select a conversation</h3>
                    <p id="smsEmptyText">SMS messages sent and received through your Twilio numbers appear here.</p>
                    <a href="{{ route('integrations') }}" class="sms-link-btn" id="smsConnectLink" style="{{ $integrationConnected ? 'display:none' : '' }}">Connect Twilio in Integrations</a>
                </div>
            </div>

            <div class="sms-chat" id="smsChat" style="display:none;">
                <header class="sms-chat-header">
                    <button type="button" class="sms-icon-btn sms-back" id="smsBackBtn" aria-label="Back">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                    </button>
                    <div class="sms-avatar" id="smsHeaderAvatar"></div>
                    <div class="sms-chat-meta">
                        <h3 id="smsHeaderName">Contact</h3>
                        <span id="smsHeaderStatus">SMS</span>
                    </div>
                    <div class="sms-chat-actions">
                        <button type="button" class="sms-icon-btn" id="smsCallBtn" title="Call">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        </button>
                    </div>
                </header>

                <div class="sms-messages" id="smsMessages"></div>

                <footer class="sms-composer" @if(empty($canSendSms) || !$canSendSms) style="display:none;" @endif>
                    <textarea id="smsTextInput" rows="1" placeholder="Type an SMS…" maxlength="1600"></textarea>
                    <button type="button" class="sms-send-btn" id="smsSendBtn">Send</button>
                </footer>
            </div>
        </main>
    </div>

    <div class="sms-modal" id="smsNewModal" hidden>
        <div class="sms-modal-card">
            <h3>New SMS</h3>
            <p class="sms-modal-help">Enter a phone number in E.164 format (e.g. +15551234567).</p>
            <label class="sms-label">To</label>
            <input type="text" id="smsNewTo" class="sms-input" placeholder="+15551234567">
            <label class="sms-label">Name (optional)</label>
            <input type="text" id="smsNewName" class="sms-input" placeholder="Contact name">
            <div class="sms-modal-actions">
                <button type="button" class="sms-btn-secondary" id="smsNewCancel">Cancel</button>
                <button type="button" class="sms-btn-primary" id="smsNewStart">Start</button>
            </div>
        </div>
    </div>
</div>

<style>
.sms-page { height: calc(100vh - 72px); min-height: 520px; position: relative; }
.sms-layout { display: grid; grid-template-columns: 320px 1fr; height: 100%; border: 1px solid var(--border); border-radius: 12px; overflow: hidden; background: var(--bg-card); }
.sms-sidebar { border-right: 1px solid var(--border); display: flex; flex-direction: column; background: var(--bg-primary); }
.sms-sidebar-header { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.1rem; border-bottom: 1px solid var(--border); }
.sms-sidebar-header h2 { margin: 0; font-size: 1.15rem; }
.sms-sub { margin: 0.15rem 0 0; color: var(--text-secondary); font-size: 0.8rem; }
.sms-header-actions { display: flex; gap: 0.15rem; }
.sms-search { padding: 0.75rem 1rem; }
.sms-search input { width: 100%; padding: 0.55rem 0.75rem; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-card); color: var(--text-primary); }
.sms-thread-list { flex: 1; overflow-y: auto; }
.sms-thread { display: flex; gap: 0.75rem; padding: 0.85rem 1rem; cursor: pointer; border-bottom: 1px solid var(--border); }
.sms-thread:hover, .sms-thread.active { background: var(--bg-card); }
.sms-thread-body { min-width: 0; flex: 1; }
.sms-thread-top { display: flex; justify-content: space-between; gap: 0.5rem; }
.sms-thread-name { font-weight: 600; font-size: 0.92rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sms-thread-time { color: var(--text-secondary); font-size: 0.72rem; white-space: nowrap; }
.sms-thread-preview { color: var(--text-secondary); font-size: 0.8rem; margin-top: 0.2rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sms-badge { display: inline-flex; min-width: 1.2rem; height: 1.2rem; padding: 0 0.35rem; align-items: center; justify-content: center; border-radius: 999px; background: #0ea5e9; color: #fff; font-size: 0.7rem; font-weight: 700; }
.sms-avatar { width: 40px; height: 40px; border-radius: 50%; background: #0ea5e9; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0; }
.sms-main { display: flex; flex-direction: column; min-width: 0; }
.sms-empty { flex: 1; display: flex; align-items: center; justify-content: center; padding: 2rem; }
.sms-empty-card { text-align: center; max-width: 360px; }
.sms-empty-card h3 { margin: 0 0 0.5rem; }
.sms-empty-card p { color: var(--text-secondary); margin: 0 0 1rem; }
.sms-link-btn { display: inline-block; padding: 0.55rem 0.9rem; border-radius: 8px; background: #0ea5e9; color: #fff; text-decoration: none; font-weight: 600; font-size: 0.9rem; }
.sms-chat { display: flex; flex-direction: column; height: 100%; }
.sms-chat-header { display: flex; align-items: center; gap: 0.75rem; padding: 0.85rem 1rem; border-bottom: 1px solid var(--border); }
.sms-chat-meta { flex: 1; min-width: 0; }
.sms-chat-meta h3 { margin: 0; font-size: 1rem; }
.sms-chat-meta span { color: var(--text-secondary); font-size: 0.78rem; }
.sms-chat-actions { display: flex; gap: 0.25rem; }
.sms-messages { flex: 1; overflow-y: auto; padding: 1rem; display: flex; flex-direction: column; gap: 0.65rem; background: linear-gradient(180deg, var(--bg-primary), var(--bg-card)); }
.sms-bubble { max-width: min(72%, 520px); padding: 0.65rem 0.8rem; border-radius: 14px; font-size: 0.92rem; line-height: 1.4; word-break: break-word; white-space: pre-wrap; }
.sms-bubble.inbound { align-self: flex-start; background: var(--bg-card); border: 1px solid var(--border); border-bottom-left-radius: 4px; }
.sms-bubble.outbound { align-self: flex-end; background: #0ea5e9; color: #fff; border-bottom-right-radius: 4px; }
.sms-meta { display: block; margin-top: 0.35rem; font-size: 0.7rem; opacity: 0.75; }
.sms-composer { display: flex; align-items: flex-end; gap: 0.5rem; padding: 0.75rem 1rem; border-top: 1px solid var(--border); background: var(--bg-card); }
.sms-composer textarea { flex: 1; resize: none; min-height: 42px; max-height: 120px; padding: 0.65rem 0.75rem; border: 1px solid var(--border); border-radius: 10px; background: var(--bg-primary); color: var(--text-primary); font: inherit; }
.sms-send-btn { border: 0; border-radius: 10px; padding: 0.7rem 1rem; background: #0ea5e9; color: #fff; font-weight: 600; cursor: pointer; }
.sms-send-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.sms-icon-btn { width: 36px; height: 36px; border: 0; border-radius: 8px; background: transparent; color: var(--text-secondary); cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
.sms-icon-btn:hover { background: var(--bg-primary); color: var(--text-primary); }
.sms-icon-btn svg { width: 18px; height: 18px; }
.sms-back { display: none; }
.sms-modal { position: absolute; inset: 0; background: rgba(15, 23, 42, 0.45); display: flex; align-items: center; justify-content: center; z-index: 20; padding: 1rem; }
.sms-modal[hidden] { display: none; }
.sms-modal-card { width: min(420px, 100%); background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 1.25rem; box-shadow: 0 12px 40px rgba(0,0,0,.18); }
.sms-modal-card h3 { margin: 0 0 0.35rem; }
.sms-modal-help { margin: 0 0 1rem; color: var(--text-secondary); font-size: 0.85rem; }
.sms-label { display: block; font-size: 0.8rem; font-weight: 600; margin: 0.65rem 0 0.3rem; }
.sms-input { width: 100%; padding: 0.55rem 0.75rem; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-primary); color: var(--text-primary); }
.sms-modal-actions { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.1rem; }
.sms-btn-primary, .sms-btn-secondary { border: 0; border-radius: 8px; padding: 0.55rem 0.9rem; font-weight: 600; cursor: pointer; }
.sms-btn-primary { background: #0ea5e9; color: #fff; }
.sms-btn-secondary { background: var(--bg-primary); color: var(--text-primary); border: 1px solid var(--border); }
@media (max-width: 900px) {
    .sms-layout { grid-template-columns: 1fr; }
    .sms-sidebar.hidden-mobile { display: none; }
    .sms-main.hidden-mobile { display: none; }
    .sms-back { display: inline-flex; }
}
</style>

<script>
(function () {
    const root = document.getElementById('smsApp');
    if (!root) return;

    const apiBase = root.dataset.apiBase;
    const csrf = root.dataset.csrf;
    const canSend = root.dataset.canSend === '1';
    let connected = root.dataset.connected === '1';
    let conversations = [];
    let activeId = null;
    let pollTimer = null;

    const els = {
        list: document.getElementById('smsThreadList'),
        empty: document.getElementById('smsEmpty'),
        chat: document.getElementById('smsChat'),
        messages: document.getElementById('smsMessages'),
        search: document.getElementById('smsSearch'),
        text: document.getElementById('smsTextInput'),
        send: document.getElementById('smsSendBtn'),
        headerName: document.getElementById('smsHeaderName'),
        headerStatus: document.getElementById('smsHeaderStatus'),
        headerAvatar: document.getElementById('smsHeaderAvatar'),
        sidebar: document.querySelector('.sms-sidebar'),
        main: document.querySelector('.sms-main'),
        connectLink: document.getElementById('smsConnectLink'),
        emptyTitle: document.getElementById('smsEmptyTitle'),
        emptyText: document.getElementById('smsEmptyText'),
        accountLabel: document.getElementById('smsAccountLabel'),
        modal: document.getElementById('smsNewModal'),
        newTo: document.getElementById('smsNewTo'),
        newName: document.getElementById('smsNewName'),
    };

    async function api(path, options = {}) {
        const res = await fetch(apiBase + path, {
            ...options,
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Content-Type': 'application/json',
                ...(options.headers || {}),
            },
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.message || data.error || 'Request failed');
        return data;
    }

    function initials(name) {
        return (name || 'S').split(/\s+/).map(p => p[0]).join('').slice(0, 2).toUpperCase();
    }

    function formatTime(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        return d.toLocaleString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    function escapeHtml(str) {
        return String(str || '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s]));
    }

    function renderThreads() {
        const q = (els.search.value || '').toLowerCase();
        const items = conversations.filter(c => {
            if (!q) return true;
            return [c.name, c.peer_phone, c.last_message_preview].join(' ').toLowerCase().includes(q);
        });

        if (!items.length) {
            els.list.innerHTML = `<div style="padding:1.25rem;color:var(--text-secondary);font-size:0.9rem;">No SMS conversations yet.</div>`;
            return;
        }

        els.list.innerHTML = items.map(c => `
            <div class="sms-thread ${c.id === activeId ? 'active' : ''}" data-id="${c.id}">
                <div class="sms-avatar">${initials(c.name)}</div>
                <div class="sms-thread-body">
                    <div class="sms-thread-top">
                        <div class="sms-thread-name">${escapeHtml(c.name || c.peer_phone)}</div>
                        <div class="sms-thread-time">${formatTime(c.last_message_at)}</div>
                    </div>
                    <div class="sms-thread-preview">${escapeHtml(c.last_message_preview || '')}</div>
                </div>
                ${c.unread_count ? `<span class="sms-badge">${c.unread_count}</span>` : ''}
            </div>
        `).join('');

        els.list.querySelectorAll('.sms-thread').forEach(node => {
            node.addEventListener('click', () => openConversation(Number(node.dataset.id)));
        });
    }

    function renderMessage(m) {
        return `<div class="sms-bubble ${m.direction}">
            ${escapeHtml(m.body || '')}
            <span class="sms-meta">${formatTime(m.sent_at || m.created_at)}${m.status ? ' · ' + escapeHtml(m.status) : ''}</span>
        </div>`;
    }

    async function loadBootstrap() {
        try {
            const data = await api('/bootstrap');
            connected = !!data.connected;
            if (data.account?.twilio_number) {
                els.accountLabel.textContent = data.account.twilio_number;
            }
            if (!connected) {
                els.emptyTitle.textContent = 'Connect Twilio';
                els.emptyText.textContent = 'Add your Twilio credentials under Integrations, then assign a number to start texting.';
                els.connectLink.style.display = '';
            } else if (!data.account?.has_number) {
                els.emptyTitle.textContent = 'Assign a Twilio number';
                els.emptyText.textContent = 'Your account needs an assigned Twilio number before you can send or receive SMS.';
                els.connectLink.href = root.dataset.phoneUrl;
                els.connectLink.textContent = 'Open Phone System';
                els.connectLink.style.display = '';
            } else {
                els.connectLink.style.display = 'none';
            }
        } catch (e) {
            console.error(e);
        }
    }

    async function loadConversations() {
        const data = await api('/conversations');
        conversations = data.data || [];
        renderThreads();
    }

    async function openConversation(id) {
        activeId = id;
        const conv = conversations.find(c => c.id === id);
        if (!conv) return;

        els.empty.style.display = 'none';
        els.chat.style.display = 'flex';
        els.headerName.textContent = conv.name || conv.peer_phone || 'Contact';
        els.headerStatus.textContent = conv.peer_phone || 'SMS';
        els.headerAvatar.textContent = initials(conv.name || conv.peer_phone);
        renderThreads();

        if (window.matchMedia('(max-width: 900px)').matches) {
            els.sidebar.classList.add('hidden-mobile');
            els.main.classList.remove('hidden-mobile');
        }

        const data = await api(`/conversations/${id}/messages`);
        els.messages.innerHTML = (data.data || []).map(renderMessage).join('');
        els.messages.scrollTop = els.messages.scrollHeight;
        await loadConversations();
    }

    async function sendText() {
        if (!activeId || !canSend) return;
        const body = els.text.value.trim();
        if (!body) return;
        els.send.disabled = true;
        try {
            await api(`/conversations/${activeId}/messages`, {
                method: 'POST',
                body: JSON.stringify({ body }),
            });
            els.text.value = '';
            await openConversation(activeId);
        } catch (e) {
            alert(e.message);
        } finally {
            els.send.disabled = false;
        }
    }

    async function startConversation() {
        const to = els.newTo.value.trim();
        if (!to) {
            alert('Enter a phone number.');
            return;
        }
        try {
            const data = await api('/conversations', {
                method: 'POST',
                body: JSON.stringify({
                    to,
                    name: els.newName.value.trim() || null,
                }),
            });
            els.modal.hidden = true;
            els.newTo.value = '';
            els.newName.value = '';
            await loadConversations();
            if (data.data?.id) {
                await openConversation(data.data.id);
            }
        } catch (e) {
            alert(e.message);
        }
    }

    async function callPeer() {
        if (!activeId) return;
        try {
            const data = await api(`/conversations/${activeId}/call-link`);
            if (data.data?.tel) window.location.href = data.data.tel;
            else alert('No phone number available.');
        } catch (e) {
            alert(e.message);
        }
    }

    document.getElementById('smsRefreshBtn').addEventListener('click', () => loadConversations().catch(console.error));
    document.getElementById('smsBackBtn').addEventListener('click', () => {
        els.sidebar.classList.remove('hidden-mobile');
        els.main.classList.add('hidden-mobile');
    });
    document.getElementById('smsNewBtn')?.addEventListener('click', () => { els.modal.hidden = false; els.newTo.focus(); });
    document.getElementById('smsNewCancel')?.addEventListener('click', () => { els.modal.hidden = true; });
    document.getElementById('smsNewStart')?.addEventListener('click', startConversation);
    els.search.addEventListener('input', renderThreads);
    els.send?.addEventListener('click', sendText);
    els.text?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendText();
        }
    });
    document.getElementById('smsCallBtn').addEventListener('click', callPeer);

    (async function init() {
        await loadBootstrap();
        if (connected) {
            await loadConversations();
            pollTimer = setInterval(() => loadConversations().catch(() => {}), 15000);
        }
    })();
})();
</script>
@endsection
