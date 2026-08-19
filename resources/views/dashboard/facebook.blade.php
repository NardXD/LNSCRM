@extends('layouts.app')

@section('title', 'Facebook & Instagram')

@section('content')
<div class="fb-page" id="fbApp"
     data-api-base="{{ url('api/facebook') }}"
     data-csrf="{{ csrf_token() }}"
     data-connected="{{ $integrationConnected ? '1' : '0' }}"
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
            <div class="fb-sync-bar" id="fbSyncBar" style="{{ $integrationConnected ? '' : 'display:none' }}">
                <select id="fbSyncDays" class="fb-sync-select" title="How far back to import">
                    <option value="30">Last 30 days</option>
                    <option value="90" selected>Last 90 days</option>
                    <option value="365">Last 12 months</option>
                    <option value="0">All available in Twilio</option>
                </select>
                <button type="button" class="fb-sync-btn" id="fbSyncBtn">Sync old messages</button>
            </div>
            <p class="fb-sync-status" id="fbSyncStatus" hidden></p>
            <div class="fb-filters">
                <button type="button" class="fb-chip active" data-channel="">All</button>
                <button type="button" class="fb-chip" data-channel="messenger">Messenger</button>
                <button type="button" class="fb-chip" data-channel="instagram">Instagram</button>
            </div>
            <div class="fb-search">
                <input type="search" id="fbSearch" placeholder="Search conversations...">
            </div>
            <div class="fb-thread-list" id="fbThreadList"></div>
        </aside>

        <main class="fb-main">
            <div class="fb-empty" id="fbEmpty">
                <div class="fb-empty-card">
                    <h3 id="fbEmptyTitle">Select a conversation</h3>
                    <p id="fbEmptyText">Facebook Page and Instagram Direct messages appear here after customers message your Twilio-connected Page. You can also import history from Twilio.</p>
                    <a href="{{ route('integrations') }}" class="fb-link-btn" id="fbConnectLink" style="{{ $integrationConnected ? 'display:none' : '' }}">Connect Facebook in Integrations</a>
                    <button type="button" class="fb-link-btn" id="fbEmptySyncBtn" style="{{ $integrationConnected ? '' : 'display:none' }}">Sync old messages</button>
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

                <div class="fb-messages" id="fbMessages"></div>

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
                    <textarea id="fbTextInput" rows="1" placeholder="Type a message..."></textarea>
                    <button type="button" class="fb-send-btn" id="fbSendBtn">Send</button>
                </footer>
            </div>
        </main>
        @include('partials.contact-history-panel', ['panelId' => 'fbContactHistory'])
    </div>
</div>

<style>
.fb-page { height: calc(100dvh - 140px); max-height: calc(100dvh - 140px); min-height: 420px; min-width: 0; }
.fb-layout { display: grid; grid-template-columns: 320px 1fr; height: 100%; min-height: 0; border: 1px solid var(--border); border-radius: 12px; overflow: hidden; background: var(--bg-card); }
.fb-layout.with-history { grid-template-columns: 320px 1fr 300px; }
.fb-sidebar { border-right: 1px solid var(--border); display: flex; flex-direction: column; background: var(--bg-primary); min-height: 0; min-width: 0; }
.fb-sidebar-header { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.1rem; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.fb-header-actions { display: flex; align-items: center; gap: 0.25rem; }
.fb-sync-bar { display: flex; gap: 0.4rem; padding: 0.55rem 1rem 0; flex-shrink: 0; }
.fb-sync-select { flex: 1; min-width: 0; padding: 0.35rem 0.5rem; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-card); color: var(--text-primary); font-size: 0.78rem; }
.fb-sync-btn { border: 1px solid var(--border); background: var(--bg-card); color: var(--text-primary); border-radius: 8px; padding: 0.35rem 0.65rem; font-size: 0.75rem; font-weight: 600; cursor: pointer; white-space: nowrap; }
.fb-sync-btn:hover { background: var(--bg-primary); }
.fb-sync-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.fb-sync-status { margin: 0.35rem 1rem 0; font-size: 0.75rem; color: var(--text-secondary); flex-shrink: 0; }
.fb-sidebar-header h2 { margin: 0; font-size: 1.05rem; }
.fb-sub { margin: 0.15rem 0 0; color: var(--text-secondary); font-size: 0.8rem; }
.fb-filters { display: flex; gap: 0.35rem; padding: 0.65rem 1rem 0; flex-shrink: 0; }
.fb-chip { border: 1px solid var(--border); background: var(--bg-card); color: var(--text-secondary); border-radius: 999px; padding: 0.3rem 0.7rem; font-size: 0.75rem; cursor: pointer; }
.fb-chip.active { background: #1877f2; border-color: #1877f2; color: #fff; }
.fb-search { padding: 0.75rem 1rem; flex-shrink: 0; }
.fb-search input { width: 100%; padding: 0.55rem 0.75rem; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-card); color: var(--text-primary); }
.fb-thread-list { flex: 1 1 auto; min-height: 0; overflow-y: auto; }
.fb-thread { display: flex; gap: 0.75rem; padding: 0.85rem 1rem; cursor: pointer; border-bottom: 1px solid var(--border); }
.fb-thread:hover, .fb-thread.active { background: var(--bg-card); }
.fb-thread-body { min-width: 0; flex: 1; }
.fb-thread-top { display: flex; justify-content: space-between; gap: 0.5rem; }
.fb-thread-name { font-weight: 600; font-size: 0.92rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.fb-thread-time { color: var(--text-secondary); font-size: 0.72rem; white-space: nowrap; }
.fb-thread-preview { color: var(--text-secondary); font-size: 0.8rem; margin-top: 0.2rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.fb-channel-tag { display: inline-block; margin-top: 0.2rem; font-size: 0.68rem; font-weight: 600; color: #1877f2; text-transform: uppercase; letter-spacing: 0.02em; }
.fb-channel-tag.instagram { color: #c13584; }
.fb-badge { display: inline-flex; min-width: 1.2rem; height: 1.2rem; padding: 0 0.35rem; align-items: center; justify-content: center; border-radius: 999px; background: #1877f2; color: #fff; font-size: 0.7rem; font-weight: 700; }
.fb-avatar { width: 40px; height: 40px; border-radius: 50%; background: #1877f2; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0; background-size: cover; background-position: center; }
.fb-main { display: flex; flex-direction: column; min-width: 0; min-height: 0; height: 100%; overflow: hidden; }
.fb-empty { flex: 1; display: flex; align-items: center; justify-content: center; padding: 2rem; min-height: 0; }
.fb-empty-card { text-align: center; max-width: 380px; }
.fb-empty-card h3 { margin: 0 0 0.5rem; }
.fb-empty-card p { color: var(--text-secondary); margin: 0 0 1rem; }
.fb-link-btn { display: inline-block; padding: 0.55rem 0.9rem; border-radius: 8px; background: #1877f2; color: #fff; text-decoration: none; font-weight: 600; font-size: 0.9rem; }
.fb-chat { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; height: 100%; overflow: hidden; }
.fb-chat-header { display: flex; align-items: center; gap: 0.75rem; padding: 0.85rem 1rem; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.fb-chat-meta { flex: 1; min-width: 0; }
.fb-chat-meta h3 { margin: 0; font-size: 1rem; }
.fb-chat-meta span { color: var(--text-secondary); font-size: 0.78rem; }
.fb-messages { flex: 1 1 auto; min-height: 0; overflow-x: hidden; overflow-y: auto; padding: 1rem; display: flex; flex-direction: column; gap: 0.65rem; background: linear-gradient(180deg, var(--bg-primary), var(--bg-card)); }
.fb-bubble { max-width: min(72%, 520px); padding: 0.65rem 0.8rem; border-radius: 14px; font-size: 0.92rem; line-height: 1.4; word-break: break-word; flex-shrink: 0; }
.fb-bubble.inbound { align-self: flex-start; background: var(--bg-card); border: 1px solid var(--border); border-bottom-left-radius: 4px; }
.fb-bubble.outbound { align-self: flex-end; background: #1877f2; color: #fff; border-bottom-right-radius: 4px; }
.fb-bubble img, .fb-bubble video { display: block; max-width: 100%; border-radius: 8px; margin-top: 0.35rem; }
.fb-bubble a { color: inherit; text-decoration: underline; }
.fb-meta { display: block; margin-top: 0.35rem; font-size: 0.7rem; opacity: 0.75; }
.fb-composer { display: flex; align-items: flex-end; gap: 0.5rem; padding: 0.75rem 1rem; border-top: 1px solid var(--border); background: var(--bg-card); flex-shrink: 0; }
.fb-attach { display: flex; gap: 0.15rem; }
.fb-composer textarea { flex: 1; resize: none; min-height: 42px; max-height: 120px; padding: 0.65rem 0.75rem; border: 1px solid var(--border); border-radius: 10px; background: var(--bg-primary); color: var(--text-primary); font: inherit; }
.fb-send-btn { border: 0; border-radius: 10px; padding: 0.7rem 1rem; background: #1877f2; color: #fff; font-weight: 600; cursor: pointer; }
.fb-send-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.fb-icon-btn { width: 36px; height: 36px; border: 0; border-radius: 8px; background: transparent; color: var(--text-secondary); cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
.fb-icon-btn:hover { background: var(--bg-primary); color: var(--text-primary); }
.fb-icon-btn svg { width: 18px; height: 18px; }
.fb-back { display: none; }
@media (max-width: 900px) {
    .fb-layout { grid-template-columns: 1fr; }
    .fb-sidebar.hidden-mobile { display: none; }
    .fb-main.hidden-mobile { display: none; }
    .fb-back { display: inline-flex; }
}
</style>

<script>
(function () {
    const root = document.getElementById('fbApp');
    if (!root) return;

    const apiBase = root.dataset.apiBase;
    const csrf = root.dataset.csrf;
    let connected = root.dataset.connected === '1';
    let conversations = [];
    let activeId = null;
    let channelFilter = '';
    let pollTimer = null;
    let uploadKind = 'file';

    const els = {
        list: document.getElementById('fbThreadList'),
        empty: document.getElementById('fbEmpty'),
        chat: document.getElementById('fbChat'),
        messages: document.getElementById('fbMessages'),
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
        syncBar: document.getElementById('fbSyncBar'),
        syncDays: document.getElementById('fbSyncDays'),
        syncBtn: document.getElementById('fbSyncBtn'),
        syncStatus: document.getElementById('fbSyncStatus'),
        emptySyncBtn: document.getElementById('fbEmptySyncBtn'),
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

    function formatTime(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        return d.toLocaleString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
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

    function renderThreads() {
        const q = (els.search.value || '').toLowerCase();
        const items = conversations.filter(c => {
            if (channelFilter && c.channel !== channelFilter) return false;
            if (!q) return true;
            return [c.name, c.username, c.peer_id, c.last_message_preview, c.channel].join(' ').toLowerCase().includes(q);
        });

        if (!items.length) {
            els.list.innerHTML = `<div style="padding:1.25rem;color:var(--text-secondary);font-size:0.9rem;">No conversations yet.</div>`;
            return;
        }

        els.list.innerHTML = items.map(c => `
            <div class="fb-thread ${c.id === activeId ? 'active' : ''}" data-id="${c.id}">
                <div class="fb-avatar" style="${c.profile_pic ? `background-image:url('${escapeHtml(c.profile_pic)}')` : ''}">${c.profile_pic ? '' : initials(c.name)}</div>
                <div class="fb-thread-body">
                    <div class="fb-thread-top">
                        <div class="fb-thread-name">${escapeHtml(c.name || channelLabel(c.channel) + ' User')}</div>
                        <div class="fb-thread-time">${formatTime(c.last_message_at)}</div>
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

    function renderMessage(m) {
        let body = '';
        if (m.type === 'image' && m.media_url) {
            body = `${m.text ? `<div>${escapeHtml(m.text)}</div>` : ''}<img src="${escapeHtml(m.media_url)}" alt="Image">`;
        } else if (m.type === 'video' && m.media_url) {
            body = `<video controls src="${escapeHtml(m.media_url)}"></video>${m.text ? `<div>${escapeHtml(m.text)}</div>` : ''}`;
        } else if (m.type === 'audio' && m.media_url) {
            body = `<audio controls src="${escapeHtml(m.media_url)}"></audio>`;
        } else if (m.type === 'file' && m.media_url) {
            body = `<a href="${escapeHtml(m.media_url)}" target="_blank" rel="noopener">${escapeHtml(m.file_name || 'Download file')}</a>`;
        } else {
            body = escapeHtml(m.text || '');
        }

        return `<div class="fb-bubble ${m.direction}">
            ${body}
            <span class="fb-meta">${formatTime(m.sent_at || m.created_at)}${m.status ? ' · ' + escapeHtml(m.status) : ''}</span>
        </div>`;
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
                if (els.emptySyncBtn) els.emptySyncBtn.style.display = 'none';
                if (els.syncBar) els.syncBar.style.display = 'none';
            } else {
                els.connectLink.style.display = 'none';
                if (els.emptySyncBtn) els.emptySyncBtn.style.display = '';
                if (els.syncBar) els.syncBar.style.display = '';
                if (!data.account?.has_page_access_token && els.syncStatus) {
                    els.syncStatus.hidden = false;
                    els.syncStatus.textContent = 'Twilio only has a few recent chats. Save a Facebook Page Access Token under Integrations, then sync again to import the full Page inbox.';
                }
            }
        } catch (e) {
            console.error(e);
        }
    }

    async function loadConversations() {
        const qs = channelFilter ? `?channel=${encodeURIComponent(channelFilter)}` : '';
        const data = await api('/conversations' + qs);
        conversations = data.data || [];
        renderThreads();
    }

    async function openConversation(id) {
        activeId = id;
        const conv = conversations.find(c => c.id === id);
        if (!conv) return;

        els.empty.style.display = 'none';
        els.chat.style.display = 'flex';
        els.headerName.textContent = conv.name || (channelLabel(conv.channel) + ' User');
        els.headerStatus.textContent = channelLabel(conv.channel) + (conv.username ? ' · @' + conv.username : '');
        setAvatar(els.headerAvatar, conv.name, conv.profile_pic);
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

        document.querySelector('.fb-layout')?.classList.add('with-history');
        window.loadChannelContactHistory('#fbContactHistory', {
            name: conv.name || conv.username || '',
            excludeChannel: 'facebook',
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

    async function syncOldMessages() {
        if (!connected) return;
        const days = els.syncDays?.value || '90';
        const buttons = [els.syncBtn, els.emptySyncBtn].filter(Boolean);
        buttons.forEach((btn) => { btn.disabled = true; btn.textContent = 'Syncing…'; });
        if (els.syncStatus) {
            els.syncStatus.hidden = false;
            els.syncStatus.textContent = 'Importing Facebook inbox and Twilio history… this can take a few minutes.';
        }
        try {
            const data = await api('/sync', {
                method: 'POST',
                body: JSON.stringify({ days: Number(days), limit: 2000 }),
            });
            const result = data.data || {};
            const imported = Number(result.imported || 0);
            const skipped = Number(result.skipped || 0);
            const scanned = Number(result.scanned || 0);
            const rangeLabel = Number(result.days || days) === 0 ? 'all available Twilio history' : `the last ${result.days || days} days`;
            const sources = result.sources || {};
            const hint = result.hint || '';
            const summary = imported
                ? `Imported ${imported} message${imported === 1 ? '' : 's'} from ${rangeLabel}${skipped ? ` (${skipped} already in CRM)` : ''}. Facebook inbox: ${sources.graph || 0}, Twilio: ${sources.messages || 0}.`
                : (scanned
                    ? `No new messages. Found ${scanned} in ${rangeLabel}; they are already in the CRM.`
                    : `No Messenger history found in ${rangeLabel}.`);
            const full = hint ? summary + '\n\n' + hint : summary;
            if (els.syncStatus) els.syncStatus.textContent = full;
            if (hint) alert(full);
            await loadConversations();
        } catch (e) {
            if (els.syncStatus) els.syncStatus.textContent = e.message || 'Sync failed.';
            alert(e.message || 'Could not sync old messages.');
        } finally {
            buttons.forEach((btn) => { btn.disabled = false; btn.textContent = 'Sync old messages'; });
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
    els.syncBtn?.addEventListener('click', syncOldMessages);
    els.emptySyncBtn?.addEventListener('click', syncOldMessages);

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
