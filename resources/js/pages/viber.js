(function () {
    const root = document.getElementById('viberApp');
    if (!root) return;

    const apiBase = root.dataset.apiBase;
    const csrf = root.dataset.csrf;
    const PAGE_SIZE = 40;
    let connected = root.dataset.connected === '1';
    let conversations = [];
    let activeId = null;
    let pollTimer = null;
    let searchTimer = null;
    let uploadKind = 'file';
    let convHasMore = false;
    let convLoading = false;
    let messagesHasMore = false;
    let loadOlderInProgress = false;
    let messageIds = new Set();
    let oldestMessageId = null;

    const els = {
        list: document.getElementById('viberThreadList'),
        empty: document.getElementById('viberEmpty'),
        chat: document.getElementById('viberChat'),
        messages: document.getElementById('viberMessages'),
        messageList: document.getElementById('viberMessageList'),
        loadOlder: document.getElementById('viberLoadOlder'),
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
        els.list.removeAttribute('aria-busy');
        if (!conversations.length) {
            els.list.innerHTML = `<div class="viber-list-hint">No conversations yet.</div>`;
            return;
        }

        els.list.innerHTML = conversations.map(c => `
            <div class="viber-thread ${c.id === activeId ? 'active' : ''}" data-id="${c.id}">
                <div class="viber-avatar" style="${c.avatar ? `background-image:url(${c.avatar})` : ''}">${c.avatar ? '' : initials(c.name)}</div>
                <div class="viber-thread-body">
                    <div class="viber-thread-top">
                        <div class="viber-thread-name">${escapeHtml(c.name || 'Viber User')}</div>
                        <div class="viber-thread-time">${formatTime(c.last_message_at)}</div>
                    </div>
                    <div class="viber-thread-preview">${escapeHtml(c.last_message_preview || '')}</div>
                    ${assignedLeadLine(c)}
                </div>
                ${c.unread_count ? `<span class="viber-badge">${c.unread_count}</span>` : ''}
            </div>
        `).join('') + (convHasMore ? `<div class="viber-list-hint">Scroll for older chats</div>` : '');

        els.list.querySelectorAll('.viber-thread').forEach(node => {
            node.addEventListener('click', () => openConversation(Number(node.dataset.id)));
        });
    }

    function escapeHtml(str) {
        return String(str || '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s]));
    }

    function assignedLeadLine(c) {
        if (window.LnsAssignedLead?.markup) return window.LnsAssignedLead.markup(c.lead, escapeHtml);
        const name = c?.lead?.assigned_user?.name;
        const chips = (c?.lead?.labels || []).filter(l => l?.name).map(l => escapeHtml(l.name)).join(', ');
        if (!name && !chips) return '';
        return `${name ? `<div class="channel-assigned">Assigned to ${escapeHtml(name)}</div>` : ''}${chips ? `<div class="channel-assigned">${chips}</div>` : ''}`;
    }

    function assignedLeadSuffix(c) {
        if (window.LnsAssignedLead?.suffix) return window.LnsAssignedLead.suffix(c.lead);
        const name = c?.lead?.assigned_user?.name;
        const labels = (c?.lead?.labels || []).map(l => l.name).filter(Boolean);
        return (name ? ' · Assigned to ' + name : '') + (labels.length ? ' · ' + labels.join(', ') : '');
    }

    function applyLeadToActive(lead) {
        if (!activeId || !lead) return;
        const payload = window.LnsAssignedLead?.compact ? window.LnsAssignedLead.compact(lead) : lead;
        const idx = conversations.findIndex(c => c.id === activeId);
        if (idx < 0) return;
        conversations[idx] = { ...conversations[idx], lead: payload };
        const conv = conversations[idx];
        renderThreads();
        els.headerStatus.textContent = (conv.is_subscribed ? 'Subscribed' : 'Unsubscribed') + assignedLeadSuffix(conv);
    }

    function contactHistoryOpts(conv) {
        return {
            phone: conv.phone || '',
            name: conv.name || '',
            excludeChannel: 'viber',
            excludeId: conv.id,
            canEditLead: true,
            onLeadUpdated: applyLeadToActive,
            onSaved(data) {
                if (data?.data) applyLeadToActive(data.data);
                const current = conversations.find(c => c.id === activeId) || conv;
                window.loadChannelContactHistory('#viberContactHistory', contactHistoryOpts(current));
            },
        };
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

        return `<div class="viber-bubble ${m.direction}" data-id="${m.id}">
            ${body}
            <span class="viber-meta">${formatTime(m.sent_at || m.created_at)}${m.status ? ' · ' + escapeHtml(m.status) : ''}</span>
        </div>`;
    }

    async function loadBootstrap() {
        try {
            const data = await api('/bootstrap');
            connected = !!data.connected;
            window.vbTemplates?.applyBootstrap(data);
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

    function firstLoadedMessageId() {
        const first = els.messageList.querySelector('.viber-bubble');
        return first ? Number(first.dataset.id) : null;
    }

    function resetMessages() {
        els.messageList.innerHTML = '';
        messageIds = new Set();
        oldestMessageId = null;
        messagesHasMore = false;
        loadOlderInProgress = false;
        if (els.loadOlder) els.loadOlder.hidden = true;
    }

    function appendMessages(items) {
        items.forEach(m => {
            if (messageIds.has(m.id)) return;
            els.messageList.insertAdjacentHTML('beforeend', renderMessage(m));
            messageIds.add(m.id);
        });
        oldestMessageId = firstLoadedMessageId();
    }

    function prependMessages(items) {
        const html = items.filter(m => !messageIds.has(m.id)).map(m => {
            messageIds.add(m.id);
            return renderMessage(m);
        }).join('');
        if (html) els.messageList.insertAdjacentHTML('afterbegin', html);
        oldestMessageId = firstLoadedMessageId();
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
        if (!append && !merge) {
            els.list.setAttribute('aria-busy', 'true');
        }
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
        if (els.loadOlder) els.loadOlder.hidden = false;
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
            if (els.loadOlder) els.loadOlder.hidden = !messagesHasMore;
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
        id = Number(id);
        if (!id) return;

        let conv = conversations.find(c => Number(c.id) === id) || null;
        if (!conv) {
            try {
                const preview = await api(`/conversations/${id}/messages?limit=${PAGE_SIZE}`);
                conv = preview.conversation ? { ...preview.conversation, unread_count: 0 } : null;
                if (conv && !conversations.some(c => Number(c.id) === id)) {
                    conversations.unshift(conv);
                } else if (conv) {
                    const idx = conversations.findIndex(c => Number(c.id) === id);
                    conversations[idx] = { ...conversations[idx], ...conv };
                    conv = conversations[idx];
                }
            } catch (e) {
                return;
            }
            if (!conv) return;
        }

        activeId = id;

        conv.unread_count = 0;
        els.empty.style.display = 'none';
        els.chat.style.display = 'flex';
        els.headerName.textContent = conv.name || 'Viber User';
        els.headerStatus.textContent = (conv.is_subscribed ? 'Subscribed' : 'Unsubscribed') + assignedLeadSuffix(conv);
        setAvatar(els.headerAvatar, conv.name, conv.avatar);
        renderThreads();
        resetMessages();

        if (window.matchMedia('(max-width: 900px)').matches) {
            els.sidebar.classList.add('hidden-mobile');
            els.main.classList.remove('hidden-mobile');
        }

        const data = await api(`/conversations/${id}/messages?limit=${PAGE_SIZE}`);
        messagesHasMore = !!data.has_more;
        if (els.loadOlder) els.loadOlder.hidden = !messagesHasMore;
        appendMessages(data.data || []);
        els.messages.scrollTop = els.messages.scrollHeight;
        await fillUntilScrollable();
        els.messages.scrollTop = els.messages.scrollHeight;
        els.messages.querySelectorAll('img, video').forEach((media) => {
            media.addEventListener('load', () => {
                els.messages.scrollTop = els.messages.scrollHeight;
            }, { once: true });
        });
        if (data.conversation) {
            const idx = conversations.findIndex(c => c.id === id);
            if (idx >= 0) conversations[idx] = { ...conversations[idx], ...data.conversation, unread_count: 0 };
            Object.assign(conv, conversations[idx] || data.conversation, { unread_count: 0 });
            els.headerStatus.textContent = (conv.is_subscribed ? 'Subscribed' : 'Unsubscribed') + assignedLeadSuffix(conv);
            setAvatar(els.headerAvatar, conv.name, conv.avatar);
            renderThreads();
        }

        document.querySelector('.viber-layout')?.classList.add('with-history');
        window.loadChannelContactHistory('#viberContactHistory', contactHistoryOpts(conv));
        window.updateHeaderNotificationsBadge?.();
        window.updateSidebarUnreadBadges?.();
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
    els.search.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => loadConversations().catch(console.error), 250);
    });
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

    els.list.addEventListener('scroll', () => {
        if (convLoading || !convHasMore) return;
        const remaining = els.list.scrollHeight - els.list.scrollTop - els.list.clientHeight;
        if (remaining < 120) loadConversations({ append: true }).catch(console.error);
    });

    els.messages.addEventListener('scroll', () => {
        if (els.messages.scrollTop < 48) loadOlderMessages();
    });

    window.vbTemplates = window.initChannelReplyTemplates({
        prefix: 'vb',
        bodyMax: 7000,
        label: 'Viber Templates',
        api,
        getComposer: () => els.text,
        escapeHtml,
    });

    (async function init() {
        await Promise.all([
            loadBootstrap().catch(console.error),
            loadConversations().catch(console.error),
        ]);
        if (connected) {
            const params = new URLSearchParams(window.location.search);
            const openId = Number(params.get('conversation') || 0);
            if (openId) {
                await openConversation(openId);
            }
            pollTimer = setInterval(() => loadConversations({ merge: true }).catch(() => {}), 15000);
        }
    })();
})();
