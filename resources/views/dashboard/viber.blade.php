@extends('layouts.app')

@section('title', 'Viber')

@section('content')
<div class="viber-page" id="viberApp"
     data-api-base="{{ url('api/viber') }}"
     data-csrf="{{ csrf_token() }}"
     data-connected="{{ $integrationConnected ? '1' : '0' }}"
     data-bot-name="{{ $botName ?? '' }}"
     data-bot-share="{{ $botShareUrl ?? '' }}"
     data-integrations-url="{{ route('integrations') }}">
    <div class="viber-layout">
        <aside class="viber-sidebar">
            <div class="viber-sidebar-header">
                <div>
                    <h2>Viber</h2>
                    <p class="viber-sub" id="viberBotLabel">{{ $botName ?: 'Business chats' }}</p>
                </div>
                <button type="button" class="viber-icon-btn" id="viberRefreshBtn" title="Refresh">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                </button>
            </div>
            <div class="viber-search">
                <input type="search" id="viberSearch" placeholder="Search conversations...">
            </div>
            <div class="viber-thread-list" id="viberThreadList"></div>
        </aside>

        <main class="viber-main">
            <div class="viber-empty" id="viberEmpty">
                <div class="viber-empty-card">
                    <h3 id="viberEmptyTitle">Select a conversation</h3>
                    <p id="viberEmptyText">Customer messages appear here after they message your Viber sender.</p>
                    <a href="{{ route('integrations') }}" class="viber-link-btn" id="viberConnectLink" style="{{ $integrationConnected ? 'display:none' : '' }}">Connect Viber in Integrations</a>
                </div>
            </div>

            <div class="viber-chat" id="viberChat" style="display:none;">
                <header class="viber-chat-header">
                    <button type="button" class="viber-icon-btn viber-back" id="viberBackBtn" aria-label="Back">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                    </button>
                    <div class="viber-avatar" id="viberHeaderAvatar"></div>
                    <div class="viber-chat-meta">
                        <h3 id="viberHeaderName">Customer</h3>
                        <span id="viberHeaderStatus">Subscribed</span>
                    </div>
                    <div class="viber-chat-actions">
                        <button type="button" class="viber-icon-btn" id="viberCallBtn" title="Call on Viber">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        </button>
                        <button type="button" class="viber-icon-btn" id="viberOpenBtn" title="Open in Viber">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                        </button>
                    </div>
                </header>

                <div class="viber-messages" id="viberMessages"></div>

                <footer class="viber-composer">
                    <div class="viber-attach">
                        <button type="button" class="viber-icon-btn" id="viberAttachImage" title="Send image">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        </button>
                        <button type="button" class="viber-icon-btn" id="viberAttachVideo" title="Send video">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 7l-7 5 7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
                        </button>
                        <button type="button" class="viber-icon-btn" id="viberAttachFile" title="Send file">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                        </button>
                        <input type="file" id="viberFileInput" hidden>
                    </div>
                    <textarea id="viberTextInput" rows="1" placeholder="Type a message..."></textarea>
                    <button type="button" class="viber-send-btn" id="viberSendBtn">Send</button>
                </footer>
            </div>
        </main>
        @include('partials.contact-history-panel', ['panelId' => 'viberContactHistory'])
    </div>
</div>

<style>
.viber-page { height: calc(100dvh - 140px); max-height: calc(100dvh - 140px); min-height: 420px; min-width: 0; }
.viber-layout { display: grid; grid-template-columns: 320px 1fr; height: 100%; min-height: 0; border: 1px solid var(--border); border-radius: 12px; overflow: hidden; background: var(--bg-card); }
.viber-layout.with-history { grid-template-columns: 320px 1fr 300px; }
.viber-sidebar { border-right: 1px solid var(--border); display: flex; flex-direction: column; background: var(--bg-primary); min-height: 0; min-width: 0; }
.viber-sidebar-header { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.1rem; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.viber-sidebar-header h2 { margin: 0; font-size: 1.15rem; }
.viber-sub { margin: 0.15rem 0 0; color: var(--text-secondary); font-size: 0.8rem; }
.viber-search { padding: 0.75rem 1rem; flex-shrink: 0; }
.viber-search input { width: 100%; padding: 0.55rem 0.75rem; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-card); color: var(--text-primary); }
.viber-thread-list { flex: 1 1 auto; min-height: 0; overflow-y: auto; }
.viber-thread { display: flex; gap: 0.75rem; padding: 0.85rem 1rem; cursor: pointer; border-bottom: 1px solid var(--border); }
.viber-thread:hover, .viber-thread.active { background: var(--bg-card); }
.viber-thread-body { min-width: 0; flex: 1; }
.viber-thread-top { display: flex; justify-content: space-between; gap: 0.5rem; }
.viber-thread-name { font-weight: 600; font-size: 0.92rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.viber-thread-time { color: var(--text-secondary); font-size: 0.72rem; white-space: nowrap; }
.viber-thread-preview { color: var(--text-secondary); font-size: 0.8rem; margin-top: 0.2rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.viber-badge { display: inline-flex; min-width: 1.2rem; height: 1.2rem; padding: 0 0.35rem; align-items: center; justify-content: center; border-radius: 999px; background: #7360f2; color: #fff; font-size: 0.7rem; font-weight: 700; }
.viber-avatar { width: 40px; height: 40px; border-radius: 50%; background: #7360f2; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0; background-size: cover; background-position: center; }
.viber-main { display: flex; flex-direction: column; min-width: 0; min-height: 0; height: 100%; overflow: hidden; }
.viber-empty { flex: 1; display: flex; align-items: center; justify-content: center; padding: 2rem; min-height: 0; }
.viber-empty-card { text-align: center; max-width: 360px; }
.viber-empty-card h3 { margin: 0 0 0.5rem; }
.viber-empty-card p { color: var(--text-secondary); margin: 0 0 1rem; }
.viber-link-btn { display: inline-block; padding: 0.55rem 0.9rem; border-radius: 8px; background: #7360f2; color: #fff; text-decoration: none; font-weight: 600; font-size: 0.9rem; }
.viber-chat { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; height: 100%; overflow: hidden; }
.viber-chat-header { display: flex; align-items: center; gap: 0.75rem; padding: 0.85rem 1rem; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.viber-chat-meta { flex: 1; min-width: 0; }
.viber-chat-meta h3 { margin: 0; font-size: 1rem; }
.viber-chat-meta span { color: var(--text-secondary); font-size: 0.78rem; }
.viber-chat-actions { display: flex; gap: 0.25rem; }
.viber-messages { flex: 1 1 auto; min-height: 0; overflow-x: hidden; overflow-y: auto; padding: 1rem; display: flex; flex-direction: column; gap: 0.65rem; background: linear-gradient(180deg, var(--bg-primary), var(--bg-card)); }
.viber-bubble { max-width: min(72%, 520px); padding: 0.65rem 0.8rem; border-radius: 14px; font-size: 0.92rem; line-height: 1.4; word-break: break-word; flex-shrink: 0; }
.viber-bubble.inbound { align-self: flex-start; background: var(--bg-card); border: 1px solid var(--border); border-bottom-left-radius: 4px; }
.viber-bubble.outbound { align-self: flex-end; background: #7360f2; color: #fff; border-bottom-right-radius: 4px; }
.viber-bubble img, .viber-bubble video { display: block; max-width: 100%; border-radius: 8px; margin-top: 0.35rem; }
.viber-bubble a { color: inherit; text-decoration: underline; }
.viber-meta { display: block; margin-top: 0.35rem; font-size: 0.7rem; opacity: 0.75; }
.viber-composer { display: flex; align-items: flex-end; gap: 0.5rem; padding: 0.75rem 1rem; border-top: 1px solid var(--border); background: var(--bg-card); flex-shrink: 0; }
.viber-attach { display: flex; gap: 0.15rem; }
.viber-composer textarea { flex: 1; resize: none; min-height: 42px; max-height: 120px; padding: 0.65rem 0.75rem; border: 1px solid var(--border); border-radius: 10px; background: var(--bg-primary); color: var(--text-primary); font: inherit; }
.viber-send-btn { border: 0; border-radius: 10px; padding: 0.7rem 1rem; background: #7360f2; color: #fff; font-weight: 600; cursor: pointer; }
.viber-send-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.viber-icon-btn { width: 36px; height: 36px; border: 0; border-radius: 8px; background: transparent; color: var(--text-secondary); cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
.viber-icon-btn:hover { background: var(--bg-primary); color: var(--text-primary); }
.viber-icon-btn svg { width: 18px; height: 18px; }
.viber-back { display: none; }
@media (max-width: 900px) {
    .viber-layout { grid-template-columns: 1fr; }
    .viber-sidebar.hidden-mobile { display: none; }
    .viber-main.hidden-mobile { display: none; }
    .viber-back { display: inline-flex; }
}
</style>

<script>
(function () {
    const root = document.getElementById('viberApp');
    if (!root) return;

    const apiBase = root.dataset.apiBase;
    const csrf = root.dataset.csrf;
    let connected = root.dataset.connected === '1';
    let conversations = [];
    let activeId = null;
    let pollTimer = null;
    let uploadKind = 'file';

    const els = {
        list: document.getElementById('viberThreadList'),
        empty: document.getElementById('viberEmpty'),
        chat: document.getElementById('viberChat'),
        messages: document.getElementById('viberMessages'),
        search: document.getElementById('viberSearch'),
        text: document.getElementById('viberTextInput'),
        send: document.getElementById('viberSendBtn'),
        file: document.getElementById('viberFileInput'),
        headerName: document.getElementById('viberHeaderName'),
        headerStatus: document.getElementById('viberHeaderStatus'),
        headerAvatar: document.getElementById('viberHeaderAvatar'),
        sidebar: document.querySelector('.viber-sidebar'),
        main: document.querySelector('.viber-main'),
        connectLink: document.getElementById('viberConnectLink'),
        emptyTitle: document.getElementById('viberEmptyTitle'),
        emptyText: document.getElementById('viberEmptyText'),
        botLabel: document.getElementById('viberBotLabel'),
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
        return (name || 'V').split(/\s+/).map(p => p[0]).join('').slice(0, 2).toUpperCase();
    }

    function formatTime(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        return d.toLocaleString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    function setAvatar(el, name, url) {
        if (url) {
            el.style.backgroundImage = `url(${url})`;
            el.textContent = '';
        } else {
            el.style.backgroundImage = '';
            el.textContent = initials(name);
        }
    }

    function renderThreads() {
        const q = (els.search.value || '').toLowerCase();
        const items = conversations.filter(c => {
            if (!q) return true;
            return [c.name, c.phone, c.last_message_preview, c.viber_user_id].join(' ').toLowerCase().includes(q);
        });

        if (!items.length) {
            els.list.innerHTML = `<div style="padding:1.25rem;color:var(--text-secondary);font-size:0.9rem;">No conversations yet.</div>`;
            return;
        }

        els.list.innerHTML = items.map(c => `
            <div class="viber-thread ${c.id === activeId ? 'active' : ''}" data-id="${c.id}">
                <div class="viber-avatar" style="${c.avatar ? `background-image:url(${c.avatar})` : ''}">${c.avatar ? '' : initials(c.name)}</div>
                <div class="viber-thread-body">
                    <div class="viber-thread-top">
                        <div class="viber-thread-name">${escapeHtml(c.name || 'Viber User')}</div>
                        <div class="viber-thread-time">${formatTime(c.last_message_at)}</div>
                    </div>
                    <div class="viber-thread-preview">${escapeHtml(c.last_message_preview || '')}</div>
                </div>
                ${c.unread_count ? `<span class="viber-badge">${c.unread_count}</span>` : ''}
            </div>
        `).join('');

        els.list.querySelectorAll('.viber-thread').forEach(node => {
            node.addEventListener('click', () => openConversation(Number(node.dataset.id)));
        });
    }

    function escapeHtml(str) {
        return String(str || '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s]));
    }

    function renderMessage(m) {
        let body = '';
        if (m.type === 'picture' && m.media_url) {
            body = `${m.text ? `<div>${escapeHtml(m.text)}</div>` : ''}<img src="${escapeHtml(m.media_url)}" alt="Image">`;
        } else if (m.type === 'video' && m.media_url) {
            body = `<video controls src="${escapeHtml(m.media_url)}"></video>`;
        } else if (m.type === 'file' && m.media_url) {
            body = `<a href="${escapeHtml(m.media_url)}" target="_blank" rel="noopener">${escapeHtml(m.file_name || 'Download file')}</a>`;
        } else if (m.type === 'url' && m.media_url) {
            body = `<a href="${escapeHtml(m.media_url)}" target="_blank" rel="noopener">${escapeHtml(m.media_url)}</a>`;
        } else if (m.type === 'location' && m.latitude != null) {
            const maps = `https://maps.google.com/?q=${m.latitude},${m.longitude}`;
            body = `<a href="${maps}" target="_blank" rel="noopener">📍 ${m.latitude}, ${m.longitude}</a>`;
        } else if (m.type === 'contact') {
            body = `👤 ${escapeHtml(m.contact_name || 'Contact')}${m.contact_phone ? `<br>${escapeHtml(m.contact_phone)}` : ''}`;
        } else if (m.type === 'sticker') {
            body = m.media_url ? `<img src="${escapeHtml(m.media_url)}" alt="Sticker">` : '🎨 Sticker';
        } else {
            body = escapeHtml(m.text || '');
        }

        return `<div class="viber-bubble ${m.direction}">
            ${body}
            <span class="viber-meta">${formatTime(m.sent_at || m.created_at)}${m.status ? ' · ' + escapeHtml(m.status) : ''}</span>
        </div>`;
    }

    async function loadBootstrap() {
        try {
            const data = await api('/bootstrap');
            connected = !!data.connected;
            if (data.bot?.name) els.botLabel.textContent = data.bot.name;
            if (!connected) {
                els.emptyTitle.textContent = 'Connect Viber';
                els.emptyText.textContent = 'Connect Twilio, then add your Viber sender under Integrations to start chatting.';
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
        els.headerName.textContent = conv.name || 'Viber User';
        els.headerStatus.textContent = conv.is_subscribed ? 'Subscribed' : 'Unsubscribed';
        setAvatar(els.headerAvatar, conv.name, conv.avatar);
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

        document.querySelector('.viber-layout')?.classList.add('with-history');
        window.loadChannelContactHistory('#viberContactHistory', {
            phone: conv.phone || '',
            name: conv.name || '',
            excludeChannel: 'viber',
            excludeId: conv.id,
        });
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
            const payload = {
                type: kind === 'picture' ? 'picture' : (kind === 'video' ? 'video' : 'file'),
                media_url: media.url,
                file_name: media.file_name,
                file_size: media.file_size,
            };
            await api(`/conversations/${activeId}/messages`, {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            await openConversation(activeId);
        } catch (e) {
            alert(e.message);
        } finally {
            els.send.disabled = false;
            els.file.value = '';
        }
    }

    async function startCall() {
        if (!activeId) return;
        try {
            const data = await api(`/conversations/${activeId}/call-link`);
            const links = data.data || {};
            if (links.call) {
                window.location.href = links.call;
            } else if (links.tel) {
                window.location.href = links.tel;
            } else {
                alert('No phone number is available for this contact yet. Ask them to share a contact, or open the bot chat in Viber.');
            }
        } catch (e) {
            alert(e.message);
        }
    }

    async function openInViber() {
        if (!activeId) return;
        try {
            const data = await api(`/conversations/${activeId}/call-link`);
            const links = data.data || {};
            if (links.open_chat) window.location.href = links.open_chat;
            else if (links.call) window.location.href = links.call;
            else alert('Viber deep link is not available yet.');
        } catch (e) {
            alert(e.message);
        }
    }

    document.getElementById('viberRefreshBtn').addEventListener('click', () => loadConversations().catch(console.error));
    document.getElementById('viberBackBtn').addEventListener('click', () => {
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
    document.getElementById('viberAttachImage').addEventListener('click', () => { uploadKind = 'picture'; els.file.accept = 'image/jpeg,image/png,image/gif,.jpg,.jpeg,.png,.gif'; els.file.click(); });
    document.getElementById('viberAttachVideo').addEventListener('click', () => { uploadKind = 'video'; els.file.accept = 'video/mp4,.mp4'; els.file.click(); });
    document.getElementById('viberAttachFile').addEventListener('click', () => { uploadKind = 'file'; els.file.accept = '*/*'; els.file.click(); });
    els.file.addEventListener('change', () => uploadAndSend(els.file.files[0], uploadKind));
    document.getElementById('viberCallBtn').addEventListener('click', startCall);
    document.getElementById('viberOpenBtn').addEventListener('click', openInViber);

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
