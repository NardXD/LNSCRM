@extends('layouts.app')

@section('title', 'WhatsApp')

@section('content')
<div class="wa-page" id="waApp"
     data-api-base="{{ url('api/whatsapp') }}"
     data-csrf="{{ csrf_token() }}"
     data-connected="{{ $integrationConnected ? '1' : '0' }}"
     data-integrations-url="{{ route('integrations') }}">
    <div class="wa-layout">
        <aside class="wa-sidebar">
            <div class="wa-sidebar-header">
                <div>
                    <h2>WhatsApp</h2>
                    <p class="wa-sub" id="waAccountLabel">{{ $businessName ?: ($displayPhone ?: 'Business chats') }}</p>
                </div>
                <button type="button" class="wa-icon-btn" id="waRefreshBtn" title="Refresh">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                </button>
            </div>
            <div class="wa-search">
                <input type="search" id="waSearch" placeholder="Search conversations...">
            </div>
            <div class="wa-thread-list" id="waThreadList"></div>
        </aside>

        <main class="wa-main">
            <div class="wa-empty" id="waEmpty">
                <div class="wa-empty-card">
                    <h3 id="waEmptyTitle">Select a conversation</h3>
                    <p id="waEmptyText">Customer messages appear here after they message your WhatsApp Business number.</p>
                    <a href="{{ route('integrations') }}" class="wa-link-btn" id="waConnectLink" style="{{ $integrationConnected ? 'display:none' : '' }}">Connect WhatsApp in Integrations</a>
                </div>
            </div>

            <div class="wa-chat" id="waChat" style="display:none;">
                <header class="wa-chat-header">
                    <button type="button" class="wa-icon-btn wa-back" id="waBackBtn" aria-label="Back">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                    </button>
                    <div class="wa-avatar" id="waHeaderAvatar"></div>
                    <div class="wa-chat-meta">
                        <h3 id="waHeaderName">Customer</h3>
                        <span id="waHeaderStatus">WhatsApp</span>
                    </div>
                    <div class="wa-chat-actions">
                        <button type="button" class="wa-icon-btn" id="waCallBtn" title="Open in WhatsApp">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        </button>
                        <button type="button" class="wa-icon-btn" id="waOpenBtn" title="Open chat">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                        </button>
                    </div>
                </header>

                <div class="wa-messages" id="waMessages"></div>

                <footer class="wa-composer">
                    <div class="wa-attach">
                        <button type="button" class="wa-icon-btn" id="waAttachImage" title="Send image">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        </button>
                        <button type="button" class="wa-icon-btn" id="waAttachVideo" title="Send video">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 7l-7 5 7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
                        </button>
                        <button type="button" class="wa-icon-btn" id="waAttachFile" title="Send file">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                        </button>
                        <input type="file" id="waFileInput" hidden>
                    </div>
                    <textarea id="waTextInput" rows="1" placeholder="Type a message..."></textarea>
                    <button type="button" class="wa-send-btn" id="waSendBtn">Send</button>
                </footer>
            </div>
        </main>
    </div>
</div>

<style>
.wa-page { height: calc(100dvh - 140px); max-height: calc(100dvh - 140px); min-height: 420px; min-width: 0; }
.wa-layout { display: grid; grid-template-columns: 320px 1fr; height: 100%; min-height: 0; border: 1px solid var(--border); border-radius: 12px; overflow: hidden; background: var(--bg-card); }
.wa-sidebar { border-right: 1px solid var(--border); display: flex; flex-direction: column; background: var(--bg-primary); min-height: 0; min-width: 0; }
.wa-sidebar-header { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.1rem; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.wa-sidebar-header h2 { margin: 0; font-size: 1.15rem; }
.wa-sub { margin: 0.15rem 0 0; color: var(--text-secondary); font-size: 0.8rem; }
.wa-search { padding: 0.75rem 1rem; flex-shrink: 0; }
.wa-search input { width: 100%; padding: 0.55rem 0.75rem; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-card); color: var(--text-primary); }
.wa-thread-list { flex: 1 1 auto; min-height: 0; overflow-y: auto; }
.wa-thread { display: flex; gap: 0.75rem; padding: 0.85rem 1rem; cursor: pointer; border-bottom: 1px solid var(--border); }
.wa-thread:hover, .wa-thread.active { background: var(--bg-card); }
.wa-thread-body { min-width: 0; flex: 1; }
.wa-thread-top { display: flex; justify-content: space-between; gap: 0.5rem; }
.wa-thread-name { font-weight: 600; font-size: 0.92rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.wa-thread-time { color: var(--text-secondary); font-size: 0.72rem; white-space: nowrap; }
.wa-thread-preview { color: var(--text-secondary); font-size: 0.8rem; margin-top: 0.2rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.wa-badge { display: inline-flex; min-width: 1.2rem; height: 1.2rem; padding: 0 0.35rem; align-items: center; justify-content: center; border-radius: 999px; background: #25d366; color: #fff; font-size: 0.7rem; font-weight: 700; }
.wa-avatar { width: 40px; height: 40px; border-radius: 50%; background: #25d366; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0; background-size: cover; background-position: center; }
.wa-main { display: flex; flex-direction: column; min-width: 0; min-height: 0; height: 100%; overflow: hidden; }
.wa-empty { flex: 1; display: flex; align-items: center; justify-content: center; padding: 2rem; min-height: 0; }
.wa-empty-card { text-align: center; max-width: 360px; }
.wa-empty-card h3 { margin: 0 0 0.5rem; }
.wa-empty-card p { color: var(--text-secondary); margin: 0 0 1rem; }
.wa-link-btn { display: inline-block; padding: 0.55rem 0.9rem; border-radius: 8px; background: #25d366; color: #fff; text-decoration: none; font-weight: 600; font-size: 0.9rem; }
.wa-chat { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; height: 100%; overflow: hidden; }
.wa-chat-header { display: flex; align-items: center; gap: 0.75rem; padding: 0.85rem 1rem; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.wa-chat-meta { flex: 1; min-width: 0; }
.wa-chat-meta h3 { margin: 0; font-size: 1rem; }
.wa-chat-meta span { color: var(--text-secondary); font-size: 0.78rem; }
.wa-chat-actions { display: flex; gap: 0.25rem; }
.wa-messages { flex: 1 1 auto; min-height: 0; overflow-x: hidden; overflow-y: auto; padding: 1rem; display: flex; flex-direction: column; gap: 0.65rem; background: linear-gradient(180deg, var(--bg-primary), var(--bg-card)); }
.wa-bubble { max-width: min(72%, 520px); padding: 0.65rem 0.8rem; border-radius: 14px; font-size: 0.92rem; line-height: 1.4; word-break: break-word; flex-shrink: 0; }
.wa-bubble.inbound { align-self: flex-start; background: var(--bg-card); border: 1px solid var(--border); border-bottom-left-radius: 4px; }
.wa-bubble.outbound { align-self: flex-end; background: #25d366; color: #fff; border-bottom-right-radius: 4px; }
.wa-bubble img, .wa-bubble video { display: block; max-width: 100%; border-radius: 8px; margin-top: 0.35rem; }
.wa-bubble a { color: inherit; text-decoration: underline; }
.wa-meta { display: block; margin-top: 0.35rem; font-size: 0.7rem; opacity: 0.75; }
.wa-composer { display: flex; align-items: flex-end; gap: 0.5rem; padding: 0.75rem 1rem; border-top: 1px solid var(--border); background: var(--bg-card); flex-shrink: 0; }
.wa-attach { display: flex; gap: 0.15rem; }
.wa-composer textarea { flex: 1; resize: none; min-height: 42px; max-height: 120px; padding: 0.65rem 0.75rem; border: 1px solid var(--border); border-radius: 10px; background: var(--bg-primary); color: var(--text-primary); font: inherit; }
.wa-send-btn { border: 0; border-radius: 10px; padding: 0.7rem 1rem; background: #25d366; color: #fff; font-weight: 600; cursor: pointer; }
.wa-send-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.wa-icon-btn { width: 36px; height: 36px; border: 0; border-radius: 8px; background: transparent; color: var(--text-secondary); cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
.wa-icon-btn:hover { background: var(--bg-primary); color: var(--text-primary); }
.wa-icon-btn svg { width: 18px; height: 18px; }
.wa-back { display: none; }
@media (max-width: 900px) {
    .wa-layout { grid-template-columns: 1fr; }
    .wa-sidebar.hidden-mobile { display: none; }
    .wa-main.hidden-mobile { display: none; }
    .wa-back { display: inline-flex; }
}
</style>

<script>
(function () {
    const root = document.getElementById('waApp');
    if (!root) return;

    const apiBase = root.dataset.apiBase;
    const csrf = root.dataset.csrf;
    let connected = root.dataset.connected === '1';
    let conversations = [];
    let activeId = null;
    let pollTimer = null;
    let uploadKind = 'document';

    const els = {
        list: document.getElementById('waThreadList'),
        empty: document.getElementById('waEmpty'),
        chat: document.getElementById('waChat'),
        messages: document.getElementById('waMessages'),
        search: document.getElementById('waSearch'),
        text: document.getElementById('waTextInput'),
        send: document.getElementById('waSendBtn'),
        file: document.getElementById('waFileInput'),
        headerName: document.getElementById('waHeaderName'),
        headerStatus: document.getElementById('waHeaderStatus'),
        headerAvatar: document.getElementById('waHeaderAvatar'),
        sidebar: document.querySelector('.wa-sidebar'),
        main: document.querySelector('.wa-main'),
        connectLink: document.getElementById('waConnectLink'),
        emptyTitle: document.getElementById('waEmptyTitle'),
        emptyText: document.getElementById('waEmptyText'),
        accountLabel: document.getElementById('waAccountLabel'),
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
        return (name || 'W').split(/\s+/).map(p => p[0]).join('').slice(0, 2).toUpperCase();
    }

    function formatTime(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        return d.toLocaleString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    function setAvatar(el, name) {
        el.style.backgroundImage = '';
        el.textContent = initials(name);
    }

    function escapeHtml(str) {
        return String(str || '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s]));
    }

    function renderThreads() {
        const q = (els.search.value || '').toLowerCase();
        const items = conversations.filter(c => {
            if (!q) return true;
            return [c.name, c.phone, c.last_message_preview, c.wa_id].join(' ').toLowerCase().includes(q);
        });

        if (!items.length) {
            els.list.innerHTML = `<div style="padding:1.25rem;color:var(--text-secondary);font-size:0.9rem;">No conversations yet.</div>`;
            return;
        }

        els.list.innerHTML = items.map(c => `
            <div class="wa-thread ${c.id === activeId ? 'active' : ''}" data-id="${c.id}">
                <div class="wa-avatar">${initials(c.name)}</div>
                <div class="wa-thread-body">
                    <div class="wa-thread-top">
                        <div class="wa-thread-name">${escapeHtml(c.name || 'WhatsApp User')}</div>
                        <div class="wa-thread-time">${formatTime(c.last_message_at)}</div>
                    </div>
                    <div class="wa-thread-preview">${escapeHtml(c.last_message_preview || '')}</div>
                </div>
                ${c.unread_count ? `<span class="wa-badge">${c.unread_count}</span>` : ''}
            </div>
        `).join('');

        els.list.querySelectorAll('.wa-thread').forEach(node => {
            node.addEventListener('click', () => openConversation(Number(node.dataset.id)));
        });
    }

    function renderMessage(m) {
        let body = '';
        if ((m.type === 'image' || m.type === 'sticker') && m.media_url) {
            body = `${m.text ? `<div>${escapeHtml(m.text)}</div>` : ''}<img src="${escapeHtml(m.media_url)}" alt="Image">`;
        } else if (m.type === 'video' && m.media_url) {
            body = `<video controls src="${escapeHtml(m.media_url)}"></video>${m.text ? `<div>${escapeHtml(m.text)}</div>` : ''}`;
        } else if (m.type === 'audio' && m.media_url) {
            body = `<audio controls src="${escapeHtml(m.media_url)}"></audio>`;
        } else if (m.type === 'document' && m.media_url) {
            body = `<a href="${escapeHtml(m.media_url)}" target="_blank" rel="noopener">${escapeHtml(m.file_name || 'Download file')}</a>`;
        } else if (m.type === 'location' && m.latitude != null) {
            const maps = `https://maps.google.com/?q=${m.latitude},${m.longitude}`;
            body = `<a href="${maps}" target="_blank" rel="noopener">📍 ${m.latitude}, ${m.longitude}</a>`;
        } else if (m.type === 'contact') {
            body = `👤 ${escapeHtml(m.contact_name || 'Contact')}${m.contact_phone ? `<br>${escapeHtml(m.contact_phone)}` : ''}`;
        } else {
            body = escapeHtml(m.text || '');
        }

        return `<div class="wa-bubble ${m.direction}">
            ${body}
            <span class="wa-meta">${formatTime(m.sent_at || m.created_at)}${m.status ? ' · ' + escapeHtml(m.status) : ''}</span>
        </div>`;
    }

    async function loadBootstrap() {
        try {
            const data = await api('/bootstrap');
            connected = !!data.connected;
            if (data.account?.business_name) {
                els.accountLabel.textContent = data.account.business_name;
            } else if (data.account?.display_phone_number) {
                els.accountLabel.textContent = data.account.display_phone_number;
            }
            if (!connected) {
                els.emptyTitle.textContent = 'Connect WhatsApp';
                els.emptyText.textContent = 'Connect Twilio, then add your WhatsApp sender under Integrations to start chatting.';
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
        els.headerName.textContent = conv.name || 'WhatsApp User';
        els.headerStatus.textContent = conv.within_window
            ? ('Within 24h window · ' + (conv.phone || conv.wa_id || ''))
            : ('Outside 24h window · ' + (conv.phone || conv.wa_id || ''));
        setAvatar(els.headerAvatar, conv.name);
        renderThreads();

        if (window.matchMedia('(max-width: 900px)').matches) {
            els.sidebar.classList.add('hidden-mobile');
            els.main.classList.remove('hidden-mobile');
        }

        const data = await api(`/conversations/${id}/messages`);
        els.messages.innerHTML = (data.data || []).map(renderMessage).join('');
        requestAnimationFrame(() => {
            els.messages.scrollTop = els.messages.scrollHeight;
        });
        els.messages.querySelectorAll('img, video').forEach((media) => {
            media.addEventListener('load', () => {
                els.messages.scrollTop = els.messages.scrollHeight;
            }, { once: true });
        });
        await loadConversations();
        window.updateHeaderNotificationsBadge?.();
    }

    async function sendText() {
        if (!activeId) return;
        const text = els.text.value.trim();
        if (!text) return;
        els.send.disabled = true;
        try {
            await api(`/conversations/${activeId}/messages`, {
                method: 'POST',
                body: JSON.stringify({ type: 'text', text }),
            });
            els.text.value = '';
            await openConversation(activeId);
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
            await api(`/conversations/${activeId}/messages`, {
                method: 'POST',
                body: JSON.stringify({
                    type: kind,
                    media_url: media.url,
                    file_name: media.file_name,
                    file_size: media.file_size,
                }),
            });
            await openConversation(activeId);
        } catch (e) {
            alert(e.message);
        } finally {
            els.send.disabled = false;
            els.file.value = '';
        }
    }

    async function openWhatsApp() {
        if (!activeId) return;
        try {
            const data = await api(`/conversations/${activeId}/call-link`);
            const links = data.data || {};
            if (links.open_chat) window.open(links.open_chat, '_blank');
            else if (links.tel) window.location.href = links.tel;
            else alert('No phone number is available for this contact yet.');
        } catch (e) {
            alert(e.message);
        }
    }

    document.getElementById('waRefreshBtn').addEventListener('click', () => loadConversations().catch(console.error));
    document.getElementById('waBackBtn').addEventListener('click', () => {
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
    document.getElementById('waAttachImage').addEventListener('click', () => { uploadKind = 'image'; els.file.accept = 'image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp'; els.file.click(); });
    document.getElementById('waAttachVideo').addEventListener('click', () => { uploadKind = 'video'; els.file.accept = 'video/mp4,.mp4,.3gp'; els.file.click(); });
    document.getElementById('waAttachFile').addEventListener('click', () => { uploadKind = 'document'; els.file.accept = '*/*'; els.file.click(); });
    els.file.addEventListener('change', () => uploadAndSend(els.file.files[0], uploadKind));
    document.getElementById('waCallBtn').addEventListener('click', openWhatsApp);
    document.getElementById('waOpenBtn').addEventListener('click', openWhatsApp);

    (async function init() {
        await loadBootstrap();
        if (connected) {
            await loadConversations();
            const params = new URLSearchParams(window.location.search);
            const openId = Number(params.get('conversation') || 0);
            if (openId && conversations.some(c => c.id === openId)) {
                await openConversation(openId);
            }
            pollTimer = setInterval(() => loadConversations().catch(() => {}), 15000);
        }
    })();
})();
</script>
@endsection
