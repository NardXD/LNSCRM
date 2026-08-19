@extends('layouts.app')

@section('title', 'Facebook & Instagram')

@section('content')
<div class="fb-page-wrapper">
<div class="fb-page" id="fbApp"
     data-api-base="{{ url('api/facebook') }}"
     data-csrf="{{ csrf_token() }}"
     data-connected="{{ $integrationConnected ? '1' : '0' }}"
     data-timezone="{{ $appTimezone ?? config('app.timezone') }}"
     data-integrations-url="{{ route('integrations') }}">
    <div class="fb-layout">
        <aside class="fb-sidebar">
            <div class="fb-sidebar-header">
                <div>
                    <h2>Messenger &amp; Instagram</h2>
                    <p class="fb-sub" id="fbAccountLabel">
                        @if($pageName && $instagramUsername)
                            {{ $pageName }} · {{ '@'.$instagramUsername }}
                        @elseif($pageName)
                            {{ $pageName }}
                        @elseif($instagramUsername)
                            {{ '@'.$instagramUsername }}
                        @else
                            Page messages
                        @endif
                    </p>
                </div>
                <div class="fb-header-actions">
                    <button type="button" class="fb-icon-btn" id="fbRefreshBtn" title="Refresh">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                    </button>
                </div>
            </div>
            <div class="fb-filters">
                <button type="button" class="fb-chip active" data-channel="">All</button>
                <button type="button" class="fb-chip" data-channel="messenger">Messenger</button>
                <button type="button" class="fb-chip" data-channel="instagram">Instagram</button>
            </div>
            <div class="fb-search">
                <input type="search" id="fbSearch" placeholder="Search conversations…" autocomplete="off">
            </div>
            <div class="fb-thread-list" id="fbThreadList"></div>
        </aside>

        <main class="fb-main">
            <div class="fb-empty" id="fbEmpty">
                <div class="fb-empty-card">
                    <h3 id="fbEmptyTitle">Select a conversation</h3>
                    <p id="fbEmptyText">Facebook Page and Instagram Direct messages appear here after customers message your Twilio-connected Page.</p>
                    <a href="{{ route('integrations') }}" class="fb-link-btn" id="fbConnectLink" style="{{ $integrationConnected ? 'display:none' : '' }}">Connect Facebook in Integrations</a>
                </div>
            </div>

            <div class="fb-chat" id="fbChat" style="display:none;">
                <header class="fb-chat-header">
                    <button type="button" class="fb-icon-btn fb-back" id="fbBackBtn" aria-label="Back">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                    </button>
                    <div class="fb-avatar" id="fbHeaderAvatar"></div>
                    <div class="fb-chat-meta">
                        <h3 id="fbHeaderName">Customer</h3>
                        <span id="fbHeaderStatus">Messenger</span>
                    </div>
                </header>

                <div class="fb-messages" id="fbMessages">
                    <div class="fb-message-list" id="fbMessageList"></div>
                </div>

                <footer class="fb-composer">
                    <div class="fb-attach">
                        <button type="button" class="fb-icon-btn" id="fbAttachImage" title="Send image">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        </button>
                        <button type="button" class="fb-icon-btn" id="fbAttachVideo" title="Send video">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 7l-7 5 7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
                        </button>
                        <button type="button" class="fb-icon-btn" id="fbAttachFile" title="Send file">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                        </button>
                        <input type="file" id="fbFileInput" hidden>
                    </div>
                    <textarea id="fbTextInput" rows="1" placeholder="Type a message…"></textarea>
                    <button type="button" class="fb-send-btn" id="fbSendBtn" title="Send" aria-label="Send">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 4l-1.4 1.4 5.6 5.6H4v2h12.2l-5.6 5.6L12 20l8-8z"/></svg>
                    </button>
                </footer>
            </div>
        </main>
        @include('partials.contact-history-panel', ['panelId' => 'fbContactHistory'])
    </div>
</div>
</div>

<style>
.main-content > .content:has(.fb-page-wrapper) {
    max-width: none !important;
    width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
}

.fb-page-wrapper {
    --fb-bg: #f4f5f7;
    --fb-panel: #ffffff;
    --fb-accent: #1877f2;
    --fb-accent-soft: #e7f0fd;
    --fb-bubble: #1877f2;
    --fb-gray-bubble: #E9E9EB;
    --fb-chat-bg: #ffffff;
    margin: 0;
    width: 100%;
    height: calc(100vh - 64px);
    min-height: calc(100vh - 64px);
    padding: 10px 12px 12px;
    background: var(--bg-primary, #fafafa);
}

.fb-page {
    height: 100%;
    width: 100%;
    position: relative;
    display: flex;
    flex-direction: column;
    background: var(--fb-panel);
    overflow: hidden;
    border: 1px solid var(--border);
    border-radius: 10px;
}

.fb-layout {
    display: grid;
    grid-template-columns: minmax(280px, 340px) minmax(0, 1fr);
    height: 100%;
    width: 100%;
    min-height: 0;
    background: var(--fb-panel);
}
.fb-layout.with-history { grid-template-columns: minmax(260px, 320px) minmax(0, 1fr) 300px; }

.fb-sidebar {
    display: flex;
    flex-direction: column;
    min-height: 0;
    min-width: 0;
    background: var(--fb-panel);
    border-right: 1px solid var(--border);
}
.fb-sidebar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    min-height: 64px;
    padding: 1rem 1.15rem;
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
}
.fb-sidebar-header h2 { margin: 0; font-size: 1rem; font-weight: 700; }
.fb-sub { margin: 0.15rem 0 0; color: var(--text-secondary); font-size: 0.75rem; }
.fb-header-actions { display: flex; gap: 0.15rem; }
.fb-filters { display: flex; gap: 0.35rem; padding: 0.7rem 1.15rem 0; flex-shrink: 0; }
.fb-chip {
    border: 1px solid var(--border);
    background: var(--fb-bg);
    color: var(--text-secondary);
    border-radius: 999px;
    padding: 0.28rem 0.7rem;
    font-size: 0.72rem;
    font-weight: 600;
    cursor: pointer;
}
.fb-chip.active { background: var(--fb-accent); border-color: var(--fb-accent); color: #fff; }
.fb-search { padding: 0.7rem 1.15rem 0.8rem; flex-shrink: 0; }
.fb-search input {
    width: 100%;
    padding: 0.45rem 0.7rem;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--fb-bg);
    color: var(--text-primary);
    font-size: 0.82rem;
}
.fb-thread-list { flex: 1; min-height: 0; overflow-y: auto; overscroll-behavior: contain; padding: 0.35rem 0.55rem 0.75rem; }
.fb-thread {
    display: flex;
    gap: 0.6rem;
    align-items: center;
    padding: 0.55rem 0.65rem;
    cursor: pointer;
    border-radius: 10px;
}
.fb-thread:hover { background: var(--fb-bg); }
.fb-thread.active { background: #eef0f3; }
.fb-thread.unread .fb-thread-name { font-weight: 700; }
.fb-thread-body { min-width: 0; flex: 1; }
.fb-thread-top { display: flex; justify-content: space-between; gap: 0.5rem; align-items: baseline; }
.fb-thread-name { font-weight: 600; font-size: 0.84rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text-primary); }
.fb-thread-time { color: var(--text-secondary); font-size: 0.68rem; white-space: nowrap; }
.fb-thread-preview { color: var(--text-secondary); font-size: 0.75rem; margin-top: 0.1rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.3; }
.fb-channel-tag { display: inline-block; margin-top: 0.15rem; font-size: 0.64rem; font-weight: 600; color: var(--fb-accent); text-transform: uppercase; letter-spacing: 0.02em; }
.fb-channel-tag.instagram { color: #c13584; }
.fb-badge {
    display: inline-flex; min-width: 1.05rem; height: 1.05rem; padding: 0 0.3rem;
    align-items: center; justify-content: center; border-radius: 999px;
    background: var(--fb-accent); color: #fff; font-size: 0.64rem; font-weight: 700; flex-shrink: 0;
}
.fb-list-hint { text-align: center; padding: 0.7rem; font-size: 0.72rem; color: var(--text-secondary); }
.fb-avatar {
    width: 32px; height: 32px; border-radius: 8px; background: var(--fb-accent); color: #fff;
    display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.7rem; flex-shrink: 0;
    background-size: cover; background-position: center;
}

.fb-main { display: flex; flex-direction: column; min-width: 0; min-height: 0; overflow: hidden; background: var(--fb-bg); }
.fb-empty { flex: 1; display: flex; align-items: center; justify-content: center; padding: 2rem; }
.fb-empty-card { text-align: center; max-width: 360px; }
.fb-empty-card h3 { margin: 0 0 0.4rem; font-size: 1.05rem; }
.fb-empty-card p { color: var(--text-secondary); margin: 0 0 1rem; font-size: 0.88rem; line-height: 1.45; }
.fb-link-btn { display: inline-block; padding: 0.5rem 0.85rem; border-radius: 8px; background: var(--fb-accent); color: #fff; text-decoration: none; font-weight: 600; font-size: 0.84rem; }

.fb-chat { display: flex; flex-direction: column; height: 100%; min-height: 0; background: var(--fb-chat-bg); }
.fb-chat-header {
    display: flex; align-items: center; gap: 0.75rem;
    min-height: 64px;
    padding: 1rem 1.15rem;
    border-bottom: 1px solid var(--border);
    background: var(--fb-panel); flex-shrink: 0;
}
.fb-chat-meta { flex: 1; min-width: 0; }
.fb-chat-meta h3 { margin: 0; font-size: 0.92rem; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.fb-chat-meta span { color: var(--text-secondary); font-size: 0.72rem; }

.fb-messages {
    flex: 1 1 auto;
    min-height: 0;
    overflow-x: hidden;
    overflow-y: auto;
    overscroll-behavior: contain;
    padding: 0.75rem 1rem 1.1rem;
    display: flex;
    flex-direction: column;
    background: var(--fb-chat-bg);
}
.fb-message-list {
    margin-top: auto;
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-height: min-content;
    font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", sans-serif;
}
.fb-stamp {
    align-self: center;
    font-size: 11px;
    font-weight: 600;
    color: #8e8e93;
    letter-spacing: -0.01em;
    margin: 12px 0 8px;
    text-align: center;
    line-height: 1.3;
}
.fb-bubble {
    position: relative;
    max-width: min(72%, 460px);
    padding: 7px 13px 8px;
    border-radius: 18px;
    font-size: 15px;
    line-height: 1.32;
    letter-spacing: -0.01em;
    word-break: break-word;
    white-space: pre-wrap;
}
.fb-bubble.inbound {
    align-self: flex-start;
    background: var(--fb-gray-bubble);
    color: #000;
    margin-left: 10px;
}
.fb-bubble.outbound {
    align-self: flex-end;
    background: var(--fb-bubble);
    color: #fff;
    margin-right: 10px;
}
.fb-bubble.solo,
.fb-bubble.group-start { margin-top: 8px; }
.fb-stamp + .fb-bubble { margin-top: 0; }
.fb-bubble.inbound.group-start:not(.solo) { border-bottom-left-radius: 5px; }
.fb-bubble.inbound.group-mid { border-top-left-radius: 5px; border-bottom-left-radius: 5px; }
.fb-bubble.inbound.group-end { border-top-left-radius: 5px; }
.fb-bubble.outbound.group-start:not(.solo) { border-bottom-right-radius: 5px; }
.fb-bubble.outbound.group-mid { border-top-right-radius: 5px; border-bottom-right-radius: 5px; }
.fb-bubble.outbound.group-end { border-top-right-radius: 5px; }
.fb-bubble.inbound.tail::before,
.fb-bubble.outbound.tail::before {
    content: "";
    position: absolute;
    bottom: 0;
    width: 16px;
    height: 16px;
}
.fb-bubble.inbound.tail::after,
.fb-bubble.outbound.tail::after {
    content: "";
    position: absolute;
    bottom: 0;
    width: 10px;
    height: 16px;
    background: var(--fb-chat-bg);
}
.fb-bubble.inbound.tail::before {
    left: -6px;
    background: var(--fb-gray-bubble);
    border-bottom-right-radius: 12px;
}
.fb-bubble.inbound.tail::after {
    left: -10px;
    border-bottom-right-radius: 8px;
}
.fb-bubble.outbound.tail::before {
    right: -6px;
    background: var(--fb-bubble);
    border-bottom-left-radius: 12px;
}
.fb-bubble.outbound.tail::after {
    right: -10px;
    border-bottom-left-radius: 8px;
}
.fb-bubble img, .fb-bubble video { display: block; max-width: 100%; border-radius: 12px; margin: 0.2rem 0; }
.fb-bubble audio { width: 100%; margin-top: 0.2rem; }
.fb-bubble a { color: inherit; text-decoration: underline; }
.fb-delivered {
    align-self: flex-end;
    font-size: 11px;
    font-weight: 500;
    color: #8e8e93;
    margin: 2px 18px 2px 0;
    letter-spacing: -0.01em;
}
.fb-delivered.is-failed { color: #ff3b30; font-weight: 600; }
.fb-bubble.failed { opacity: 0.9; }

.fb-composer {
    display: flex; align-items: flex-end; gap: 0.45rem;
    padding: 0.55rem 0.85rem 0.75rem; border-top: 1px solid #e5e5ea;
    background: var(--fb-chat-bg); flex-shrink: 0;
}
.fb-attach { display: flex; gap: 0.05rem; flex-shrink: 0; }
.fb-layout .chp-header {
    min-height: 64px;
    align-items: center;
    padding: 1rem 1.15rem;
}
.fb-composer textarea {
    flex: 1; resize: none; min-height: 36px; max-height: 110px;
    padding: 8px 14px; border: 1px solid #c7c7cc; border-radius: 20px;
    background: #fff; color: #000; font: inherit; font-size: 15px; line-height: 1.3;
    font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", sans-serif;
}
.fb-composer textarea::placeholder { color: #8e8e93; }
.fb-send-btn {
    width: 32px; height: 32px; border: 0; border-radius: 50%; padding: 0;
    background: var(--fb-accent); color: #fff; cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.fb-send-btn svg { width: 16px; height: 16px; transform: rotate(-90deg); }
.fb-send-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.fb-icon-btn {
    width: 32px; height: 32px; border: 0; border-radius: 8px; background: transparent;
    color: var(--text-secondary); cursor: pointer; display: inline-flex; align-items: center; justify-content: center;
}
.fb-icon-btn:hover { background: var(--fb-bg); color: var(--text-primary); }
.fb-icon-btn svg { width: 16px; height: 16px; }
.fb-back { display: none; }

@media (max-width: 900px) {
    .fb-page-wrapper { padding: 8px; }
    .fb-page-wrapper, .fb-page { height: auto; min-height: calc(100vh - 64px); overflow: visible; }
    .fb-layout, .fb-layout.with-history { grid-template-columns: 1fr; height: auto; min-height: calc(100vh - 80px); }
    .fb-sidebar { min-height: calc(100vh - 64px); }
    .fb-sidebar.hidden-mobile { display: none; }
    .fb-main { min-height: calc(100vh - 64px); }
    .fb-main.hidden-mobile { display: none; }
    .fb-back { display: inline-flex; }
    .fb-chat { min-height: calc(100vh - 64px); }
}
</style>

<script>
(function () {
    const root = document.getElementById('fbApp');
    if (!root) return;

    const apiBase = root.dataset.apiBase;
    const csrf = root.dataset.csrf;
    const appTimezone = root.dataset.timezone || 'Asia/Manila';
    let connected = root.dataset.connected === '1';
    let conversations = [];
    let activeId = null;
    let channelFilter = '';
    let pollTimer = null;
    let uploadKind = 'file';
    let messageIds = new Set();

    const els = {
        list: document.getElementById('fbThreadList'),
        empty: document.getElementById('fbEmpty'),
        chat: document.getElementById('fbChat'),
        messages: document.getElementById('fbMessages'),
        messageList: document.getElementById('fbMessageList'),
        search: document.getElementById('fbSearch'),
        text: document.getElementById('fbTextInput'),
        send: document.getElementById('fbSendBtn'),
        file: document.getElementById('fbFileInput'),
        headerName: document.getElementById('fbHeaderName'),
        headerStatus: document.getElementById('fbHeaderStatus'),
        headerAvatar: document.getElementById('fbHeaderAvatar'),
        sidebar: document.querySelector('.fb-sidebar'),
        main: document.querySelector('.fb-main'),
        connectLink: document.getElementById('fbConnectLink'),
        emptyTitle: document.getElementById('fbEmptyTitle'),
        emptyText: document.getElementById('fbEmptyText'),
        accountLabel: document.getElementById('fbAccountLabel'),
    };

    async function api(path, options = {}) {
        const res = await fetch(apiBase + path, {
            ...options,
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                ...(options.body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
                ...(options.headers || {}),
            },
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.message || data.error || 'Request failed');
        return data;
    }

    function initials(name) {
        return (name || 'F').split(/\s+/).map(p => p[0]).join('').slice(0, 2).toUpperCase();
    }

    function formatListTime(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        if (Number.isNaN(d.getTime())) return '';
        const now = new Date();
        const sameDay = d.toDateString() === now.toDateString();
        const opts = { timeZone: appTimezone };
        if (sameDay) return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', ...opts });
        const yesterday = new Date(now);
        yesterday.setDate(now.getDate() - 1);
        if (d.toDateString() === yesterday.toDateString()) return 'Yesterday';
        return d.toLocaleDateString([], { month: 'short', day: 'numeric', ...opts });
    }

    function formatStamp(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        if (Number.isNaN(d.getTime())) return '';
        const now = new Date();
        const opts = { timeZone: appTimezone };
        const time = d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', ...opts });
        if (d.toDateString() === now.toDateString()) return 'Today ' + time;
        const yesterday = new Date(now);
        yesterday.setDate(now.getDate() - 1);
        if (d.toDateString() === yesterday.toDateString()) return 'Yesterday ' + time;
        const weekAgo = new Date(now);
        weekAgo.setDate(now.getDate() - 6);
        if (d > weekAgo) {
            return d.toLocaleDateString([], { weekday: 'long', ...opts }) + ' ' + time;
        }
        if (d.getFullYear() === now.getFullYear()) {
            return d.toLocaleDateString([], { month: 'short', day: 'numeric', ...opts }) + ' at ' + time;
        }
        return d.toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric', ...opts }) + ' at ' + time;
    }

    function dayKey(iso) {
        if (!iso) return '';
        return new Date(iso).toDateString();
    }

    function shouldStamp(prevIso, iso) {
        if (!iso) return false;
        if (!prevIso) return true;
        if (dayKey(prevIso) !== dayKey(iso)) return true;
        return (new Date(iso) - new Date(prevIso)) > 45 * 60 * 1000;
    }

    function setAvatar(el, name, pic) {
        if (pic) {
            el.style.backgroundImage = `url("${pic}")`;
            el.textContent = '';
            return;
        }
        el.style.backgroundImage = '';
        el.textContent = initials(name);
    }

    function escapeHtml(str) {
        return String(str || '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s]));
    }

    function channelLabel(channel) {
        return channel === 'instagram' ? 'Instagram' : 'Messenger';
    }

    function nearBottom() {
        return els.messages.scrollHeight - els.messages.scrollTop - els.messages.clientHeight < 80;
    }

    function lastBubble() {
        const nodes = els.messageList.querySelectorAll('.fb-bubble');
        return nodes[nodes.length - 1] || null;
    }

    function renderThreads() {
        const q = (els.search.value || '').toLowerCase();
        const items = conversations.filter(c => {
            if (channelFilter && c.channel !== channelFilter) return false;
            if (!q) return true;
            return [c.name, c.username, c.peer_id, c.last_message_preview, c.channel].join(' ').toLowerCase().includes(q);
        });

        if (!items.length) {
            els.list.innerHTML = `<div class="fb-list-hint">No conversations yet.</div>`;
            return;
        }

        els.list.innerHTML = items.map(c => `
            <div class="fb-thread ${c.id === activeId ? 'active' : ''} ${c.unread_count ? 'unread' : ''}" data-id="${c.id}">
                <div class="fb-avatar" style="${c.profile_pic ? `background-image:url('${escapeHtml(c.profile_pic)}')` : ''}">${c.profile_pic ? '' : initials(c.name)}</div>
                <div class="fb-thread-body">
                    <div class="fb-thread-top">
                        <div class="fb-thread-name">${escapeHtml(c.name || channelLabel(c.channel) + ' User')}</div>
                        <div class="fb-thread-time">${formatListTime(c.last_message_at)}</div>
                    </div>
                    <div class="fb-thread-preview">${escapeHtml(c.last_message_preview || '')}</div>
                    <span class="fb-channel-tag ${c.channel === 'instagram' ? 'instagram' : ''}">${channelLabel(c.channel)}</span>
                </div>
                ${c.unread_count ? `<span class="fb-badge">${c.unread_count}</span>` : ''}
            </div>
        `).join('');

        els.list.querySelectorAll('.fb-thread').forEach(node => {
            node.addEventListener('click', () => openConversation(Number(node.dataset.id)));
        });
    }

    function stampMarkup(iso) {
        return `<div class="fb-stamp" data-day="${dayKey(iso)}" data-ts="${iso}">${escapeHtml(formatStamp(iso))}</div>`;
    }

    function messageBody(m) {
        if (m.type === 'image' && m.media_url) {
            return `${m.text ? `<div>${escapeHtml(m.text)}</div>` : ''}<img src="${escapeHtml(m.media_url)}" alt="Image">`;
        }
        if (m.type === 'video' && m.media_url) {
            return `<video controls src="${escapeHtml(m.media_url)}"></video>${m.text ? `<div>${escapeHtml(m.text)}</div>` : ''}`;
        }
        if (m.type === 'audio' && m.media_url) {
            return `<audio controls src="${escapeHtml(m.media_url)}"></audio>`;
        }
        if (m.type === 'file' && m.media_url) {
            return `<a href="${escapeHtml(m.media_url)}" target="_blank" rel="noopener">${escapeHtml(m.file_name || 'Download file')}</a>`;
        }
        return escapeHtml(m.text || '');
    }

    function messageMarkup(m) {
        const status = (m.status || '').toLowerCase();
        const failed = status === 'failed' || status === 'undelivered';
        const iso = m.sent_at || m.created_at || '';
        return `<div class="fb-bubble ${m.direction}${failed ? ' failed' : ''}" data-id="${m.id}" data-direction="${m.direction}" data-day="${dayKey(iso)}" data-ts="${iso}" data-status="${escapeHtml(status)}">${messageBody(m)}</div>`;
    }

    function refreshThreadChrome() {
        const nodes = [...els.messageList.querySelectorAll('.fb-bubble')];
        els.messageList.querySelectorAll('.fb-delivered').forEach(n => n.remove());
        nodes.forEach((node, i) => {
            const prev = nodes[i - 1];
            const next = nodes[i + 1];
            const samePrev = prev && prev.dataset.direction === node.dataset.direction && !shouldStamp(prev.dataset.ts, node.dataset.ts);
            const sameNext = next && next.dataset.direction === node.dataset.direction && !shouldStamp(node.dataset.ts, next.dataset.ts);
            node.classList.toggle('solo', !samePrev && !sameNext);
            node.classList.toggle('group-start', !samePrev && sameNext);
            node.classList.toggle('group-mid', samePrev && sameNext);
            node.classList.toggle('group-end', samePrev && !sameNext);
            node.classList.toggle('tail', !sameNext);
        });

        const lastOut = [...nodes].reverse().find(n => n.dataset.direction === 'outbound');
        if (!lastOut) return;
        const st = (lastOut.dataset.status || '').toLowerCase();
        const failed = lastOut.classList.contains('failed') || st === 'failed' || st === 'undelivered';
        let label = 'Sent';
        if (failed) label = 'Not Delivered';
        else if (st === 'queued' || st === 'accepted' || st === 'sending') label = 'Sending';
        else if (st === 'delivered' || st === 'read') label = 'Delivered';
        lastOut.insertAdjacentHTML('afterend', `<div class="fb-delivered${failed ? ' is-failed' : ''}">${label}</div>`);
    }

    function appendMessage(m) {
        if (messageIds.has(m.id)) return;
        const iso = m.sent_at || m.created_at;
        const prev = lastBubble();
        if (shouldStamp(prev?.dataset.ts, iso)) {
            els.messageList.insertAdjacentHTML('beforeend', stampMarkup(iso));
        }
        els.messageList.insertAdjacentHTML('beforeend', messageMarkup(m));
        messageIds.add(m.id);
        refreshThreadChrome();
        els.messageList.querySelectorAll('img, video').forEach((media) => {
            media.addEventListener('load', () => {
                if (nearBottom()) els.messages.scrollTop = els.messages.scrollHeight;
            }, { once: true });
        });
    }

    function resetMessages() {
        els.messageList.innerHTML = '';
        messageIds = new Set();
    }

    async function loadBootstrap() {
        try {
            const data = await api('/bootstrap');
            connected = !!data.connected;
            const parts = [];
            if (data.account?.page_name) parts.push(data.account.page_name);
            if (data.account?.instagram_username) parts.push('@' + data.account.instagram_username);
            if (parts.length) els.accountLabel.textContent = parts.join(' · ');
            if (!connected) {
                els.emptyTitle.textContent = 'Connect Facebook';
                els.emptyText.textContent = 'Connect Twilio and your Facebook Messenger sender under Integrations to receive Page messages.';
                els.connectLink.style.display = '';
            } else {
                els.connectLink.style.display = 'none';
            }
        } catch (e) {
            console.error(e);
        }
    }

    async function loadConversations({ merge = false } = {}) {
        const qs = channelFilter ? `?channel=${encodeURIComponent(channelFilter)}` : '';
        const data = await api('/conversations' + qs);
        const rows = data.data || [];
        if (merge) {
            const byId = new Map(conversations.map(c => [c.id, c]));
            rows.forEach(c => byId.set(c.id, c));
            conversations = [...byId.values()].sort((a, b) => {
                const ta = a.last_message_at || '';
                const tb = b.last_message_at || '';
                if (ta === tb) return (b.id || 0) - (a.id || 0);
                return tb.localeCompare(ta);
            });
        } else {
            conversations = rows;
        }
        renderThreads();
    }

    async function openConversation(id) {
        activeId = id;
        const conv = conversations.find(c => c.id === id);
        if (!conv) return;

        conv.unread_count = 0;
        els.empty.style.display = 'none';
        els.chat.style.display = 'flex';
        els.headerName.textContent = conv.name || (channelLabel(conv.channel) + ' User');
        els.headerStatus.textContent = channelLabel(conv.channel) + (conv.username ? ' · @' + conv.username : '');
        setAvatar(els.headerAvatar, conv.name, conv.profile_pic);
        renderThreads();
        resetMessages();

        if (window.matchMedia('(max-width: 900px)').matches) {
            els.sidebar.classList.add('hidden-mobile');
            els.main.classList.remove('hidden-mobile');
        }

        const data = await api(`/conversations/${id}/messages`);
        (data.data || []).forEach(appendMessage);
        els.messages.scrollTop = els.messages.scrollHeight;
        window.updateHeaderNotificationsBadge?.();

        if (data.conversation) {
            const idx = conversations.findIndex(c => c.id === id);
            if (idx >= 0) conversations[idx] = { ...conversations[idx], ...data.conversation, unread_count: 0 };
            renderThreads();
        }

        document.querySelector('.fb-layout')?.classList.add('with-history');
        window.loadChannelContactHistory('#fbContactHistory', {
            name: conv.name || conv.username || '',
            excludeChannel: 'facebook',
            excludeId: conv.id,
        });
    }

    async function pollActiveMessages() {
        if (!activeId) return;
        const data = await api(`/conversations/${activeId}/messages`);
        const incoming = data.data || [];
        const newer = incoming.filter(m => !messageIds.has(m.id));
        if (!newer.length) return;
        const pin = nearBottom();
        newer.forEach(appendMessage);
        if (pin) els.messages.scrollTop = els.messages.scrollHeight;
    }

    async function sendText() {
        if (!activeId) return;
        const text = els.text.value.trim();
        if (!text) return;
        els.send.disabled = true;
        try {
            const data = await api(`/conversations/${activeId}/messages`, {
                method: 'POST',
                body: JSON.stringify({ type: 'text', text }),
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

    async function uploadAndSend(file, kind) {
        if (!activeId || !file) return;
        const form = new FormData();
        form.append('file', file);
        form.append('kind', kind);
        els.send.disabled = true;
        try {
            const uploaded = await api('/media', { method: 'POST', body: form, headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } });
            const media = uploaded.data;
            const data = await api(`/conversations/${activeId}/messages`, {
                method: 'POST',
                body: JSON.stringify({
                    type: kind,
                    media_url: media.url,
                    file_name: media.file_name,
                    file_size: media.file_size,
                }),
            });
            if (data.data) appendMessage(data.data);
            els.messages.scrollTop = els.messages.scrollHeight;
            await loadConversations({ merge: true });
        } catch (e) {
            alert(e.message);
        } finally {
            els.send.disabled = false;
            els.file.value = '';
        }
    }

    document.getElementById('fbRefreshBtn').addEventListener('click', () => loadConversations().catch(console.error));
    document.getElementById('fbBackBtn').addEventListener('click', () => {
        els.sidebar.classList.remove('hidden-mobile');
        els.main.classList.add('hidden-mobile');
    });
    els.search.addEventListener('input', renderThreads);
    els.send.addEventListener('click', sendText);
    els.text.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendText();
        }
    });
    els.text.addEventListener('input', () => {
        els.text.style.height = 'auto';
        els.text.style.height = Math.min(els.text.scrollHeight, 110) + 'px';
    });
    document.querySelectorAll('.fb-chip').forEach(chip => {
        chip.addEventListener('click', () => {
            document.querySelectorAll('.fb-chip').forEach(c => c.classList.remove('active'));
            chip.classList.add('active');
            channelFilter = chip.dataset.channel || '';
            loadConversations().catch(console.error);
        });
    });
    document.getElementById('fbAttachImage').addEventListener('click', () => { uploadKind = 'image'; els.file.accept = 'image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp'; els.file.click(); });
    document.getElementById('fbAttachVideo').addEventListener('click', () => { uploadKind = 'video'; els.file.accept = 'video/mp4,.mp4'; els.file.click(); });
    document.getElementById('fbAttachFile').addEventListener('click', () => { uploadKind = 'file'; els.file.accept = '*/*'; els.file.click(); });
    els.file.addEventListener('change', () => uploadAndSend(els.file.files[0], uploadKind));

    (async function init() {
        await loadBootstrap();
        if (connected) {
            await loadConversations();
            const params = new URLSearchParams(window.location.search);
            const openId = Number(params.get('conversation') || 0);
            if (openId && conversations.some(c => c.id === openId)) {
                await openConversation(openId);
            }
            pollTimer = setInterval(async () => {
                try {
                    await loadConversations({ merge: true });
                    await pollActiveMessages();
                } catch (e) {}
            }, 5000);
        }
    })();
})();
</script>
@endsection
