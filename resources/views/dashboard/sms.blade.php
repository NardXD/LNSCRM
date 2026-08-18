@extends('layouts.app')

@section('title', 'SMS')

@section('content')
<div class="sms-page-wrapper">
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
                <input type="search" id="smsSearch" placeholder="Search conversations…" autocomplete="off">
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

                <div class="sms-messages" id="smsMessages">
                    <div class="sms-load-older" id="smsLoadOlder" hidden>Loading earlier messages…</div>
                    <div class="sms-message-list" id="smsMessageList"></div>
                </div>

                <footer class="sms-composer" @if(empty($canSendSms) || !$canSendSms) style="display:none;" @endif>
                    <textarea id="smsTextInput" rows="1" placeholder="Type an SMS…" maxlength="1600"></textarea>
                    <button type="button" class="sms-send-btn" id="smsSendBtn">Send</button>
                </footer>
            </div>
        </main>
        @include('partials.contact-history-panel', ['panelId' => 'smsContactHistory'])
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
</div>

<style>
.main-content > .content:has(.sms-page-wrapper) {
    max-width: none !important;
    width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
}

.sms-page-wrapper {
    --sms-bg: #f4f5f7;
    --sms-panel: #ffffff;
    --sms-accent: #0ea5e9;
    --sms-accent-soft: #e0f2fe;
    margin: 0;
    width: 100%;
    height: calc(100vh - 64px);
    min-height: calc(100vh - 64px);
    padding: 10px 12px 12px;
    background: var(--bg-primary, #fafafa);
}

.sms-page {
    height: 100%;
    width: 100%;
    position: relative;
    display: flex;
    flex-direction: column;
    background: var(--sms-panel);
    overflow: hidden;
    border: 1px solid var(--border);
    border-radius: 10px;
}

.sms-layout {
    display: grid;
    grid-template-columns: minmax(280px, 340px) minmax(0, 1fr);
    height: 100%;
    width: 100%;
    min-height: 0;
    background: var(--sms-panel);
}
.sms-layout.with-history { grid-template-columns: minmax(260px, 320px) minmax(0, 1fr) 300px; }

.sms-sidebar {
    display: flex;
    flex-direction: column;
    min-height: 0;
    min-width: 0;
    background: var(--sms-panel);
    border-right: 1px solid var(--border);
}
.sms-sidebar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    min-height: 64px;
    padding: 1rem 1.15rem;
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
}
.sms-sidebar-header h2 { margin: 0; font-size: 1rem; font-weight: 700; }
.sms-sub { margin: 0.15rem 0 0; color: var(--text-secondary); font-size: 0.75rem; }
.sms-header-actions { display: flex; gap: 0.15rem; }
.sms-search { padding: 0.7rem 1.15rem 0.8rem; flex-shrink: 0; }
.sms-search input {
    width: 100%;
    padding: 0.45rem 0.7rem;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--sms-bg);
    color: var(--text-primary);
    font-size: 0.82rem;
}
.sms-thread-list { flex: 1; min-height: 0; overflow-y: auto; overscroll-behavior: contain; padding: 0.35rem 0.55rem 0.75rem; }
.sms-thread {
    display: flex;
    gap: 0.6rem;
    align-items: center;
    padding: 0.55rem 0.65rem;
    cursor: pointer;
    border-radius: 10px;
}
.sms-thread:hover { background: var(--sms-bg); }
.sms-thread.active { background: #eef0f3; }
.sms-thread.unread .sms-thread-name { font-weight: 700; }
.sms-thread-body { min-width: 0; flex: 1; }
.sms-thread-top { display: flex; justify-content: space-between; gap: 0.5rem; align-items: baseline; }
.sms-thread-name { font-weight: 600; font-size: 0.84rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text-primary); }
.sms-thread-time { color: var(--text-secondary); font-size: 0.68rem; white-space: nowrap; }
.sms-thread-preview { color: var(--text-secondary); font-size: 0.75rem; margin-top: 0.1rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.3; }
.sms-badge {
    display: inline-flex; min-width: 1.05rem; height: 1.05rem; padding: 0 0.3rem;
    align-items: center; justify-content: center; border-radius: 999px;
    background: var(--sms-accent); color: #fff; font-size: 0.64rem; font-weight: 700; flex-shrink: 0;
}
.sms-list-hint { text-align: center; padding: 0.7rem; font-size: 0.72rem; color: var(--text-secondary); }
.sms-avatar {
    width: 32px; height: 32px; border-radius: 8px; background: var(--sms-accent); color: #fff;
    display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.7rem; flex-shrink: 0;
}

.sms-main { display: flex; flex-direction: column; min-width: 0; min-height: 0; overflow: hidden; background: var(--sms-bg); }
.sms-empty { flex: 1; display: flex; align-items: center; justify-content: center; padding: 2rem; }
.sms-empty-card { text-align: center; max-width: 360px; }
.sms-empty-card h3 { margin: 0 0 0.4rem; font-size: 1.05rem; }
.sms-empty-card p { color: var(--text-secondary); margin: 0 0 1rem; font-size: 0.88rem; line-height: 1.45; }
.sms-link-btn { display: inline-block; padding: 0.5rem 0.85rem; border-radius: 8px; background: var(--sms-accent); color: #fff; text-decoration: none; font-weight: 600; font-size: 0.84rem; }

.sms-chat { display: flex; flex-direction: column; height: 100%; min-height: 0; }
.sms-chat-header {
    display: flex; align-items: center; gap: 0.75rem;
    min-height: 64px;
    padding: 1rem 1.15rem;
    border-bottom: 1px solid var(--border);
    background: var(--sms-panel); flex-shrink: 0;
}
.sms-chat-meta { flex: 1; min-width: 0; }
.sms-chat-meta h3 { margin: 0; font-size: 0.92rem; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.sms-chat-meta span { color: var(--text-secondary); font-size: 0.72rem; }
.sms-chat-actions { display: flex; gap: 0.25rem; }

.sms-messages {
    flex: 1 1 auto;
    min-height: 0;
    overflow-x: hidden;
    overflow-y: auto;
    overscroll-behavior: contain;
    padding: 0.85rem 1.15rem 1rem;
    display: flex;
    flex-direction: column;
    background: var(--sms-bg);
}
.sms-load-older {
    text-align: center;
    font-size: 0.7rem;
    color: var(--text-secondary);
    padding: 0.35rem 0 0.5rem;
    flex-shrink: 0;
}
.sms-message-list {
    margin-top: auto;
    display: flex;
    flex-direction: column;
    gap: 0.18rem;
    min-height: min-content;
}
.sms-day {
    align-self: center;
    font-size: 0.66rem;
    font-weight: 600;
    color: var(--text-secondary);
    background: var(--sms-panel);
    border: 1px solid var(--border);
    padding: 0.12rem 0.5rem;
    border-radius: 999px;
    margin: 0.5rem 0 0.25rem;
    letter-spacing: 0.01em;
}
.sms-bubble {
    max-width: min(68%, 420px);
    padding: 0.38rem 0.62rem 0.32rem;
    border-radius: 12px;
    font-size: 0.8rem;
    line-height: 1.38;
    word-break: break-word;
    white-space: pre-wrap;
}
.sms-bubble.inbound {
    align-self: flex-start;
    background: var(--sms-panel);
    border: 1px solid var(--border);
    border-bottom-left-radius: 4px;
    color: var(--text-primary);
}
.sms-bubble.outbound {
    align-self: flex-end;
    background: var(--sms-accent);
    color: #fff;
    border-bottom-right-radius: 4px;
}
.sms-bubble.follow { margin-top: 0; }
.sms-bubble.inbound.follow { border-top-left-radius: 6px; }
.sms-bubble.outbound.follow { border-top-right-radius: 6px; }
.sms-meta {
    display: block;
    margin-top: 0.12rem;
    font-size: 0.62rem;
    line-height: 1.2;
    opacity: 0.72;
    font-weight: 500;
}
.sms-bubble.failed { opacity: 0.85; }
.sms-bubble.failed .sms-meta { color: #fecaca; opacity: 1; }

.sms-composer {
    display: flex; align-items: flex-end; gap: 0.5rem;
    padding: 0.8rem 1.15rem 0.95rem; border-top: 1px solid var(--border);
    background: var(--sms-panel); flex-shrink: 0;
}
.sms-layout .chp-header {
    min-height: 64px;
    align-items: center;
    padding: 1rem 1.15rem;
}
.sms-composer textarea {
    flex: 1; resize: none; min-height: 38px; max-height: 110px;
    padding: 0.5rem 0.7rem; border: 1px solid var(--border); border-radius: 10px;
    background: var(--sms-bg); color: var(--text-primary); font: inherit; font-size: 0.84rem; line-height: 1.4;
}
.sms-send-btn {
    border: 0; border-radius: 10px; padding: 0.55rem 0.9rem;
    background: var(--sms-accent); color: #fff; font-weight: 600; font-size: 0.84rem; cursor: pointer;
}
.sms-send-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.sms-icon-btn {
    width: 32px; height: 32px; border: 0; border-radius: 8px; background: transparent;
    color: var(--text-secondary); cursor: pointer; display: inline-flex; align-items: center; justify-content: center;
}
.sms-icon-btn:hover { background: var(--sms-bg); color: var(--text-primary); }
.sms-icon-btn svg { width: 16px; height: 16px; }
.sms-back { display: none; }

.sms-modal {
    position: absolute; inset: 0; background: rgba(15, 23, 42, 0.45);
    display: flex; align-items: center; justify-content: center; z-index: 20; padding: 1rem;
}
.sms-modal[hidden] { display: none; }
.sms-modal-card {
    width: min(420px, 100%); background: var(--sms-panel); border: 1px solid var(--border);
    border-radius: 12px; padding: 1.25rem; box-shadow: 0 12px 40px rgba(0,0,0,.18);
}
.sms-modal-card h3 { margin: 0 0 0.35rem; }
.sms-modal-help { margin: 0 0 1rem; color: var(--text-secondary); font-size: 0.85rem; }
.sms-label { display: block; font-size: 0.8rem; font-weight: 600; margin: 0.65rem 0 0.3rem; }
.sms-input { width: 100%; padding: 0.55rem 0.75rem; border: 1px solid var(--border); border-radius: 8px; background: var(--sms-bg); color: var(--text-primary); }
.sms-modal-actions { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.1rem; }
.sms-btn-primary, .sms-btn-secondary { border: 0; border-radius: 8px; padding: 0.55rem 0.9rem; font-weight: 600; cursor: pointer; }
.sms-btn-primary { background: var(--sms-accent); color: #fff; }
.sms-btn-secondary { background: var(--sms-bg); color: var(--text-primary); border: 1px solid var(--border); }

@media (max-width: 900px) {
    .sms-page-wrapper { padding: 8px; }
    .sms-page-wrapper, .sms-page { height: auto; min-height: calc(100vh - 64px); overflow: visible; }
    .sms-layout, .sms-layout.with-history { grid-template-columns: 1fr; height: auto; min-height: calc(100vh - 80px); }
    .sms-sidebar { min-height: calc(100vh - 64px); }
    .sms-sidebar.hidden-mobile { display: none; }
    .sms-main { min-height: calc(100vh - 64px); }
    .sms-main.hidden-mobile { display: none; }
    .sms-back { display: inline-flex; }
    .sms-chat { min-height: calc(100vh - 64px); }
}
</style>

<script>
(function () {
    const root = document.getElementById('smsApp');
    if (!root) return;

    const apiBase = root.dataset.apiBase;
    const csrf = root.dataset.csrf;
    const canSend = root.dataset.canSend === '1';
    const PAGE_SIZE = 40;
    let connected = root.dataset.connected === '1';
    let conversations = [];
    let activeId = null;
    let pollTimer = null;
    let searchTimer = null;
    let convHasMore = false;
    let convLoading = false;
    let messagesHasMore = false;
    let loadOlderInProgress = false;
    let messageIds = new Set();
    let oldestMessageId = null;
    let lastDirection = null;
    let lastDayKey = null;

    const els = {
        list: document.getElementById('smsThreadList'),
        empty: document.getElementById('smsEmpty'),
        chat: document.getElementById('smsChat'),
        messages: document.getElementById('smsMessages'),
        messageList: document.getElementById('smsMessageList'),
        loadOlder: document.getElementById('smsLoadOlder'),
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

    function formatListTime(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        const now = new Date();
        const sameDay = d.toDateString() === now.toDateString();
        if (sameDay) return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const yesterday = new Date(now);
        yesterday.setDate(now.getDate() - 1);
        if (d.toDateString() === yesterday.toDateString()) return 'Yesterday';
        return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
    }

    function formatBubbleTime(iso) {
        if (!iso) return '';
        return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function formatDayLabel(iso) {
        const d = new Date(iso);
        const now = new Date();
        if (d.toDateString() === now.toDateString()) return 'Today';
        const yesterday = new Date(now);
        yesterday.setDate(now.getDate() - 1);
        if (d.toDateString() === yesterday.toDateString()) return 'Yesterday';
        return d.toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric' });
    }

    function dayKey(iso) {
        if (!iso) return '';
        return new Date(iso).toDateString();
    }

    function escapeHtml(str) {
        return String(str || '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s]));
    }

    function nearBottom() {
        return els.messages.scrollHeight - els.messages.scrollTop - els.messages.clientHeight < 80;
    }

    function renderThreads() {
        if (!conversations.length) {
            els.list.innerHTML = `<div class="sms-list-hint">No SMS conversations yet.</div>`;
            return;
        }

        els.list.innerHTML = conversations.map(c => `
            <div class="sms-thread ${c.id === activeId ? 'active' : ''} ${c.unread_count ? 'unread' : ''}" data-id="${c.id}">
                <div class="sms-avatar">${initials(c.name)}</div>
                <div class="sms-thread-body">
                    <div class="sms-thread-top">
                        <div class="sms-thread-name">${escapeHtml(c.name || c.peer_phone)}</div>
                        <div class="sms-thread-time">${formatListTime(c.last_message_at)}</div>
                    </div>
                    <div class="sms-thread-preview">${escapeHtml(c.last_message_preview || '')}</div>
                </div>
                ${c.unread_count ? `<span class="sms-badge">${c.unread_count}</span>` : ''}
            </div>
        `).join('') + (convHasMore ? `<div class="sms-list-hint" id="smsListMore">Scroll for older chats</div>` : '');

        els.list.querySelectorAll('.sms-thread').forEach(node => {
            node.addEventListener('click', () => openConversation(Number(node.dataset.id)));
        });
    }

    function messageMarkup(m, follow) {
        const status = (m.status || '').toLowerCase();
        const failed = status === 'failed' || status === 'undelivered';
        const metaBits = [formatBubbleTime(m.sent_at || m.created_at)];
        if (failed) metaBits.push(m.status);
        return `<div class="sms-bubble ${m.direction}${follow ? ' follow' : ''}${failed ? ' failed' : ''}" data-id="${m.id}" data-direction="${m.direction}" data-day="${dayKey(m.sent_at || m.created_at)}">
            ${escapeHtml(m.body || '')}
            <span class="sms-meta">${escapeHtml(metaBits.join(' · '))}</span>
        </div>`;
    }

    function appendDayIfNeeded(iso, atStart) {
        const key = dayKey(iso);
        if (!key) return;
        if (atStart) {
            if (lastDayKey === key) return;
            els.messageList.insertAdjacentHTML('afterbegin', `<div class="sms-day" data-day="${key}">${formatDayLabel(iso)}</div>`);
            return;
        }
        if (lastDayKey === key) return;
        els.messageList.insertAdjacentHTML('beforeend', `<div class="sms-day" data-day="${key}">${formatDayLabel(iso)}</div>`);
        lastDayKey = key;
    }

    function appendMessage(m) {
        if (messageIds.has(m.id)) return;
        const follow = lastDirection === m.direction && lastDayKey === dayKey(m.sent_at || m.created_at);
        appendDayIfNeeded(m.sent_at || m.created_at, false);
        els.messageList.insertAdjacentHTML('beforeend', messageMarkup(m, follow));
        messageIds.add(m.id);
        lastDirection = m.direction;
        if (!oldestMessageId || m.id < oldestMessageId) oldestMessageId = m.id;
    }

    function prependMessages(items) {
        if (!items.length) return;
        const first = els.messageList.firstElementChild;
        const firstDay = first?.classList.contains('sms-day') ? first.dataset.day : first?.dataset.day;
        let html = '';
        let prevDir = null;
        let prevDay = null;
        items.forEach(m => {
            if (messageIds.has(m.id)) return;
            const key = dayKey(m.sent_at || m.created_at);
            if (key && key !== prevDay) {
                html += `<div class="sms-day" data-day="${key}">${formatDayLabel(m.sent_at || m.created_at)}</div>`;
                prevDir = null;
            }
            const follow = prevDir === m.direction && prevDay === key;
            html += messageMarkup(m, follow);
            messageIds.add(m.id);
            prevDir = m.direction;
            prevDay = key;
            if (!oldestMessageId || m.id < oldestMessageId) oldestMessageId = m.id;
        });
        if (first && first.classList.contains('sms-day') && first.dataset.day === prevDay) {
            first.remove();
        }
        els.messageList.insertAdjacentHTML('afterbegin', html);
        if (!lastDayKey) lastDayKey = firstDay || prevDay;
    }

    function resetMessages() {
        els.messageList.innerHTML = '';
        messageIds = new Set();
        oldestMessageId = null;
        lastDirection = null;
        lastDayKey = null;
        messagesHasMore = false;
        loadOlderInProgress = false;
        els.loadOlder.hidden = true;
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
                els.emptyTitle.textContent = 'Assign an SMS number';
                els.emptyText.textContent = 'Your account needs an assigned SMS number before you can send or receive texts.';
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

    function sortConversations(list) {
        return list.slice().sort((a, b) => {
            const ta = a.last_message_at || '';
            const tb = b.last_message_at || '';
            if (ta === tb) return (b.id || 0) - (a.id || 0);
            return tb.localeCompare(ta);
        });
    }

    async function loadConversations({ append = false, merge = false } = {}) {
        if (convLoading) return;
        convLoading = true;
        try {
            const params = new URLSearchParams({ limit: String(PAGE_SIZE) });
            const q = (els.search.value || '').trim();
            if (q) params.set('q', q);
            if (append && conversations.length) {
                params.set('before_id', String(conversations[conversations.length - 1].id));
            }
            const data = await api('/conversations?' + params.toString());
            const rows = data.data || [];
            if (!merge) convHasMore = !!data.has_more;
            if (append) {
                const seen = new Set(conversations.map(c => c.id));
                conversations = conversations.concat(rows.filter(c => !seen.has(c.id)));
            } else if (merge) {
                const byId = new Map(conversations.map(c => [c.id, c]));
                rows.forEach(c => byId.set(c.id, c));
                conversations = sortConversations([...byId.values()]);
            } else {
                conversations = rows;
            }
            renderThreads();
        } finally {
            convLoading = false;
        }
    }

    async function loadOlderMessages() {
        if (!activeId || loadOlderInProgress || !messagesHasMore || !oldestMessageId) return false;
        loadOlderInProgress = true;
        els.loadOlder.hidden = false;
        const prevHeight = els.messages.scrollHeight;
        const prevTop = els.messages.scrollTop;
        try {
            const data = await api(`/conversations/${activeId}/messages?limit=${PAGE_SIZE}&before_id=${oldestMessageId}`);
            const rows = data.data || [];
            messagesHasMore = !!data.has_more;
            prependMessages(rows);
            els.messages.scrollTop = els.messages.scrollHeight - prevHeight + prevTop;
            return rows.length > 0;
        } catch (e) {
            console.error(e);
            return false;
        } finally {
            loadOlderInProgress = false;
            els.loadOlder.hidden = !messagesHasMore;
        }
    }

    async function fillUntilScrollable() {
        let guard = 0;
        while (messagesHasMore && els.messages.scrollHeight <= els.messages.clientHeight + 4 && guard < 8) {
            const loaded = await loadOlderMessages();
            if (!loaded) break;
            guard += 1;
        }
    }

    async function openConversation(id) {
        activeId = id;
        const conv = conversations.find(c => c.id === id);
        if (!conv) return;

        conv.unread_count = 0;
        els.empty.style.display = 'none';
        els.chat.style.display = 'flex';
        els.headerName.textContent = conv.name || conv.peer_phone || 'Contact';
        els.headerStatus.textContent = conv.peer_phone || 'SMS';
        els.headerAvatar.textContent = initials(conv.name || conv.peer_phone);
        renderThreads();
        resetMessages();

        if (window.matchMedia('(max-width: 900px)').matches) {
            els.sidebar.classList.add('hidden-mobile');
            els.main.classList.remove('hidden-mobile');
        }

        const data = await api(`/conversations/${id}/messages?limit=${PAGE_SIZE}`);
        messagesHasMore = !!data.has_more;
        els.loadOlder.hidden = !messagesHasMore;
        (data.data || []).forEach(appendMessage);
        els.messages.scrollTop = els.messages.scrollHeight;
        await fillUntilScrollable();
        els.messages.scrollTop = els.messages.scrollHeight;

        if (data.conversation) {
            const idx = conversations.findIndex(c => c.id === id);
            if (idx >= 0) conversations[idx] = { ...conversations[idx], ...data.conversation, unread_count: 0 };
            renderThreads();
        }

        document.querySelector('.sms-layout')?.classList.add('with-history');
        window.loadChannelContactHistory('#smsContactHistory', {
            phone: conv.peer_phone || '',
            name: conv.name || '',
            excludeChannel: 'sms',
            excludeId: conv.id,
        });
    }

    async function pollActiveMessages() {
        if (!activeId || loadOlderInProgress) return;
        const data = await api(`/conversations/${activeId}/messages?limit=${PAGE_SIZE}`);
        const incoming = data.data || [];
        const newer = incoming.filter(m => !messageIds.has(m.id));
        if (!newer.length) return;
        const pin = nearBottom();
        newer.forEach(appendMessage);
        if (pin) els.messages.scrollTop = els.messages.scrollHeight;
    }

    async function sendText() {
        if (!activeId || !canSend) return;
        const body = els.text.value.trim();
        if (!body) return;
        els.send.disabled = true;
        try {
            const data = await api(`/conversations/${activeId}/messages`, {
                method: 'POST',
                body: JSON.stringify({ body }),
            });
            els.text.value = '';
            els.text.style.height = 'auto';
            if (data.data) appendMessage(data.data);
            els.messages.scrollTop = els.messages.scrollHeight;
            await loadConversations({ merge: true });
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
    els.search.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => loadConversations().catch(console.error), 250);
    });
    els.send?.addEventListener('click', sendText);
    els.text?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendText();
        }
    });
    els.text?.addEventListener('input', () => {
        els.text.style.height = 'auto';
        els.text.style.height = Math.min(els.text.scrollHeight, 110) + 'px';
    });
    document.getElementById('smsCallBtn').addEventListener('click', callPeer);

    els.list.addEventListener('scroll', () => {
        if (convLoading || !convHasMore) return;
        const remaining = els.list.scrollHeight - els.list.scrollTop - els.list.clientHeight;
        if (remaining < 120) loadConversations({ append: true }).catch(console.error);
    });

    els.messages.addEventListener('scroll', () => {
        if (els.messages.scrollTop < 48) loadOlderMessages();
    });

    (async function init() {
        await loadBootstrap();
        if (connected) {
            await loadConversations();
            pollTimer = setInterval(() => {
                loadConversations({ merge: true }).catch(() => {});
                pollActiveMessages().catch(() => {});
            }, 12000);
        }
    })();
})();
</script>
@endsection
