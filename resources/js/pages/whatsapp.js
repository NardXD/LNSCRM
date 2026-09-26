(function () {
    const root = document.getElementById('waApp');
    if (!root) return;

    const apiBase = root.dataset.apiBase;
    const csrf = root.dataset.csrf;
    const appTimezone = root.dataset.timezone || 'Asia/Manila';
    const PAGE_SIZE = 40;
    let connected = root.dataset.connected === '1';
    let conversations = [];
    let activeId = null;
    let activeHistoryOpts = null;
    let readFilter = '';
    let pollTimer = null;
    let autoSyncTimer = null;
    let searchTimer = null;
    let uploadKind = 'document';
    let convHasMore = false;
    let convLoading = false;
    let messagesHasMore = false;
    let loadOlderInProgress = false;
    let messageIds = new Set();
    let oldestMessageId = null;

    const els = {
        list: document.getElementById('waThreadList'),
        empty: document.getElementById('waEmpty'),
        chat: document.getElementById('waChat'),
        messages: document.getElementById('waMessages'),
        messageList: document.getElementById('waMessageList'),
        loadOlder: document.getElementById('waLoadOlder'),
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
        syncBtn: document.getElementById('waSyncBtn'),
        syncNote: document.getElementById('waSyncNote'),
    };
    let syncInFlight = false;

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
        if (!res.ok) {
            const validation = data.errors
                ? Object.values(data.errors).flat().filter(Boolean).join(' ')
                : '';
            throw new Error(data.message || data.error || validation || `Request failed (HTTP ${res.status}).`);
        }
        return data;
    }

    function initials(name) {
        return (name || 'W').split(/\s+/).map(p => p[0]).join('').slice(0, 2).toUpperCase();
    }

    function threadDisplayName(c) {
        const leadName = String(c?.lead?.name || '').trim();
        if (leadName) return leadName;
        return c?.name || 'WhatsApp User';
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

    function assignedLeadLine(c) {
        const leadMarkup = window.LnsAssignedLead?.markup
            ? window.LnsAssignedLead.markup(c.lead, escapeHtml)
            : (() => {
                const name = c?.lead?.assigned_user?.name;
                const chips = (c?.lead?.labels || []).filter(l => l?.name).map(l => escapeHtml(l.name)).join(', ');
                if (!name && !chips) return '';
                return `${name ? `<div class="channel-assigned">Assigned to ${escapeHtml(name)}</div>` : ''}${chips ? `<div class="channel-assigned">${chips}</div>` : ''}`;
            })();
        return leadMarkup + conversationLabelChips(c);
    }

    function conversationLabelChips(c) {
        const items = (c?.labels || []).filter(l => l?.name);
        if (!items.length) return '';
        return `<div class="channel-label-chips">${items.map(l => {
            const color = l.color || '#4338ca';
            return `<span class="channel-label-chip" style="background:${escapeHtml(color)}">${escapeHtml(l.name)}</span>`;
        }).join('')}</div>`;
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
        const next = { ...conversations[idx], lead: payload };
        if (payload?.name) next.name = payload.name;
        conversations[idx] = next;
        const conv = conversations[idx];
        els.headerName.textContent = threadDisplayName(conv);
        setAvatar(els.headerAvatar, threadDisplayName(conv));
        renderThreads();
        setHeaderStatus(conv);
    }

    function applyConversationLabelsToActive(conversationId, labels) {
        const idx = conversations.findIndex(c => c.id === conversationId);
        if (idx < 0) return;
        conversations[idx] = { ...conversations[idx], labels: labels || [] };
        renderThreads();
    }

    function setHeaderStatus(conv) {
        els.headerStatus.textContent = (conv.within_window
            ? ('Within 24h window · ' + (conv.phone || conv.wa_id || ''))
            : ('Outside 24h window · ' + (conv.phone || conv.wa_id || ''))) + assignedLeadSuffix(conv);
    }

    function nearBottom() {
        return els.messages.scrollHeight - els.messages.scrollTop - els.messages.clientHeight < 80;
    }

    function lastBubble() {
        const nodes = els.messageList.querySelectorAll('.wa-bubble');
        return nodes[nodes.length - 1] || null;
    }

    function visibleConversations() {
        if (readFilter === 'unread') return conversations.filter(c => !c.is_read);
        if (readFilter === 'read') return conversations.filter(c => c.is_read);
        return conversations;
    }

    function renderThreads() {
        els.list.removeAttribute('aria-busy');
        const visible = visibleConversations();
        if (!visible.length) {
            els.list.innerHTML = `<div class="wa-list-hint">${readFilter ? 'No ' + readFilter + ' conversations.' : 'No conversations yet.'}</div>`;
            return;
        }

        els.list.innerHTML = visible.map(c => `
            <div class="wa-thread ${c.id === activeId ? 'active' : ''} ${!c.is_read ? 'unread' : ''}" data-id="${c.id}">
                <div class="wa-avatar">${initials(threadDisplayName(c))}</div>
                <div class="wa-thread-body">
                    <div class="wa-thread-top">
                        <div class="wa-thread-name">${escapeHtml(threadDisplayName(c))}</div>
                        <div class="wa-thread-time">${formatListTime(c.last_message_at)}</div>
                    </div>
                    <div class="wa-thread-preview">${escapeHtml(c.last_message_preview || '')}</div>
                    ${assignedLeadLine(c)}
                </div>
                ${!c.is_read ? `<span class="wa-unread-dot" aria-hidden="true"></span>` : ''}
            </div>
        `).join('') + (convHasMore ? `<div class="wa-list-hint">Scroll for older chats</div>` : '');

        els.list.querySelectorAll('.wa-thread').forEach(node => {
            node.addEventListener('click', () => openConversation(Number(node.dataset.id)));
        });
    }

    function stampMarkup(iso) {
        return `<div class="wa-stamp" data-day="${dayKey(iso)}" data-ts="${iso}">${escapeHtml(formatStamp(iso))}</div>`;
    }

    function messageBody(m) {
        if ((m.type === 'image' || m.type === 'sticker') && m.media_url) {
            return `${m.text ? `<div>${escapeHtml(m.text)}</div>` : ''}<img src="${escapeHtml(m.media_url)}" alt="Image">`;
        }
        if (m.type === 'video' && m.media_url) {
            return `<video controls src="${escapeHtml(m.media_url)}"></video>${m.text ? `<div>${escapeHtml(m.text)}</div>` : ''}`;
        }
        if (m.type === 'audio' && m.media_url) {
            return `<audio controls src="${escapeHtml(m.media_url)}"></audio>`;
        }
        if (m.type === 'document' && m.media_url) {
            return `<a href="${escapeHtml(m.media_url)}" target="_blank" rel="noopener">${escapeHtml(m.file_name || 'Download file')}</a>`;
        }
        if (m.type === 'location' && m.latitude != null) {
            const maps = `https://maps.google.com/?q=${m.latitude},${m.longitude}`;
            return `<a href="${maps}" target="_blank" rel="noopener">📍 ${m.latitude}, ${m.longitude}</a>`;
        }
        if (m.type === 'contact') {
            return `👤 ${escapeHtml(m.contact_name || 'Contact')}${m.contact_phone ? `<br>${escapeHtml(m.contact_phone)}` : ''}`;
        }
        return escapeHtml(m.text || '');
    }

    function messageMarkup(m) {
        const status = (m.status || '').toLowerCase();
        const failed = status === 'failed' || status === 'undelivered';
        const iso = m.sent_at || m.created_at || '';
        return `<div class="wa-bubble ${m.direction}${failed ? ' failed' : ''}" data-id="${m.id}" data-direction="${m.direction}" data-day="${dayKey(iso)}" data-ts="${iso}" data-status="${escapeHtml(status)}">${messageBody(m)}</div>`;
    }

    function refreshThreadChrome() {
        const nodes = [...els.messageList.querySelectorAll('.wa-bubble')];
        els.messageList.querySelectorAll('.wa-delivered').forEach(n => n.remove());
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
        lastOut.insertAdjacentHTML('afterend', `<div class="wa-delivered${failed ? ' is-failed' : ''}">${label}</div>`);
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

    function prependMessages(items) {
        if (!items.length) return;
        const firstStamp = els.messageList.firstElementChild?.classList.contains('wa-stamp')
            ? els.messageList.firstElementChild
            : null;
        let html = '';
        let prevIso = null;
        items.forEach(m => {
            if (messageIds.has(m.id)) return;
            const iso = m.sent_at || m.created_at;
            if (shouldStamp(prevIso, iso)) html += stampMarkup(iso);
            html += messageMarkup(m);
            messageIds.add(m.id);
            prevIso = iso;
        });
        els.messageList.insertAdjacentHTML('afterbegin', html);
        if (firstStamp && prevIso && !shouldStamp(prevIso, firstStamp.dataset.ts)) {
            firstStamp.remove();
        }
        oldestMessageId = firstLoadedMessageId();
        refreshThreadChrome();
    }

    function firstLoadedMessageId() {
        const first = els.messageList.querySelector('.wa-bubble');
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

    async function loadBootstrap() {
        try {
            const data = await api('/bootstrap');
            connected = !!data.connected;
            window.waTemplates?.applyBootstrap(data);
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

    function sortConversations(list) {
        return list.slice().sort((a, b) => {
            const ta = a.last_message_at || '';
            const tb = b.last_message_at || '';
            if (ta === tb) return (b.id || 0) - (a.id || 0);
            return tb.localeCompare(ta);
        });
    }

    function conversationParams({ append = false } = {}) {
        const params = new URLSearchParams({ limit: String(PAGE_SIZE) });
        const q = (els.search.value || '').trim();
        if (q) params.set('q', q);
        if (readFilter) params.set('read', readFilter);
        if (append && conversations.length) {
            params.set('before_id', String(conversations[conversations.length - 1].id));
        }
        return params;
    }

    async function loadConversations({ append = false, merge = false } = {}) {
        if (convLoading) return;
        convLoading = true;
        if (!append && !merge) {
            els.list.setAttribute('aria-busy', 'true');
        }
        try {
            const data = await api('/conversations?' + conversationParams({ append }).toString());
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

    function rememberConversation(conv) {
        if (!conv || conv.id == null) return null;
        const id = Number(conv.id);
        const next = { ...conv, is_read: true, unread_count: 0 };
        const idx = conversations.findIndex(c => Number(c.id) === id);
        if (idx >= 0) {
            conversations[idx] = { ...conversations[idx], ...next };
            return conversations[idx];
        }
        conversations.unshift(next);
        return conversations[0];
    }

    async function openConversation(id) {
        id = Number(id);
        if (!id) return;

        let conv = conversations.find(c => Number(c.id) === id) || null;
        let data = null;
        if (!conv) {
            try {
                data = await api(`/conversations/${id}/messages?limit=${PAGE_SIZE}`);
            } catch (e) {
                return;
            }
            conv = rememberConversation(data.conversation);
            if (!conv) return;
        }

        activeId = id;
        conv.is_read = true;
        conv.unread_count = 0;
        els.empty.style.display = 'none';
        els.chat.style.display = 'flex';
        els.headerName.textContent = threadDisplayName(conv);
        setHeaderStatus(conv);
        setAvatar(els.headerAvatar, threadDisplayName(conv));
        renderThreads();
        resetMessages();

        if (window.matchMedia('(max-width: 900px)').matches) {
            els.sidebar.classList.add('hidden-mobile');
            els.main.classList.remove('hidden-mobile');
        }

        if (!data) {
            data = await api(`/conversations/${id}/messages?limit=${PAGE_SIZE}`);
        }
        messagesHasMore = !!data.has_more;
        if (els.loadOlder) els.loadOlder.hidden = !messagesHasMore;
        (data.data || []).forEach(appendMessage);
        oldestMessageId = firstLoadedMessageId();
        els.messages.scrollTop = els.messages.scrollHeight;
        await fillUntilScrollable();
        els.messages.scrollTop = els.messages.scrollHeight;
        window.updateHeaderNotificationsBadge?.();
        window.updateSidebarUnreadBadges?.();

        if (data.conversation) {
            const idx = conversations.findIndex(c => c.id === id);
            if (idx >= 0) conversations[idx] = { ...conversations[idx], ...data.conversation, is_read: true, unread_count: 0 };
            Object.assign(conv, conversations[idx] || data.conversation, { is_read: true, unread_count: 0 });
            els.headerName.textContent = threadDisplayName(conv);
            setHeaderStatus(conv);
            setAvatar(els.headerAvatar, threadDisplayName(conv));
            renderThreads();
        }

        document.querySelector('.wa-layout')?.classList.add('with-history');
        const extractedName = data.conversation?.extracted_name || (data.conversation?.extracted_names || [])[0] || '';
        const historyOpts = {
            name: extractedName || conv.name || conv.profile_name || '',
            excludeChannel: 'whatsapp',
            excludeId: conv.id,
            source: 'whatsapp',
            phone: conv.phone || conv.wa_id || '',
            email: (data.conversation?.extracted_emails || [])[0] || '',
            extracted_phones: data.conversation?.extracted_phones || [],
            extracted_emails: data.conversation?.extracted_emails || [],
            extracted_name: extractedName,
            extracted_names: data.conversation?.extracted_names || [],
            canEditLead: true,
            onLeadUpdated: applyLeadToActive,
            conversationLabels: conv.labels || [],
            conversationLabelsApi: `/api/whatsapp/conversations/${conv.id}/labels`,
            onConversationLabelsChange: (labels) => applyConversationLabelsToActive(conv.id, labels),
            onSaved(data, extra) {
                if (extra?.existing && data.existing_lead_id) {
                    window.location.href = '/leads?lead=' + data.existing_lead_id;
                    return;
                }
                if (data.data) applyLeadToActive(data.data);
                window.loadChannelContactHistory('#waContactHistory', historyOpts);
            },
        };
        activeHistoryOpts = historyOpts;
        window.loadChannelContactHistory('#waContactHistory', historyOpts);
    }

    async function pollActiveMessages() {
        if (!activeId || loadOlderInProgress) return;
        const data = await api(`/conversations/${activeId}/messages?limit=${PAGE_SIZE}&poll=1`);
        const incoming = data.data || [];
        const newer = incoming.filter(m => !messageIds.has(m.id));
        if (newer.length) {
            const pin = nearBottom();
            newer.forEach(appendMessage);
            if (pin) els.messages.scrollTop = els.messages.scrollHeight;
        }

        const emails = data.conversation?.extracted_emails || [];
        const names = data.conversation?.extracted_names || [];
        const extractedName = data.conversation?.extracted_name || names[0] || '';
        if (!activeHistoryOpts || document.querySelector('#waContactHistory .chp-lead-form')) {
            return;
        }
        const nextEmail = emails[0] || '';
        const sameEmails = JSON.stringify(emails) === JSON.stringify(activeHistoryOpts.extracted_emails || []);
        const sameName = extractedName === (activeHistoryOpts.extracted_name || '');
        if (sameEmails && sameName && nextEmail === (activeHistoryOpts.email || '')) {
            return;
        }
        if (data.conversation?.name && activeId) {
            const idx = conversations.findIndex(c => c.id === activeId);
            if (idx >= 0 && conversations[idx].name !== data.conversation.name) {
                conversations[idx] = { ...conversations[idx], name: data.conversation.name, lead: data.conversation.lead ?? conversations[idx].lead };
                els.headerName.textContent = threadDisplayName(conversations[idx]);
                setAvatar(els.headerAvatar, threadDisplayName(conversations[idx]));
                renderThreads();
            }
        }
        activeHistoryOpts.email = nextEmail;
        activeHistoryOpts.extracted_emails = emails;
        activeHistoryOpts.extracted_name = extractedName;
        activeHistoryOpts.extracted_names = names;
        if (extractedName) {
            activeHistoryOpts.name = extractedName;
        }
        window.loadChannelContactHistory('#waContactHistory', activeHistoryOpts);
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

    function showSyncNote(text) {
        if (!els.syncNote) return;
        els.syncNote.textContent = text;
        els.syncNote.classList.toggle('is-visible', !!text);
    }

    async function syncMessages() {
        if (!els.syncBtn || syncInFlight) return;
        syncInFlight = true;
        els.syncBtn.disabled = true;
        els.syncBtn.classList.add('is-syncing');
        showSyncNote('Checking Twilio for messages missed by the CRM...');
        try {
            const data = await api('/sync', {
                method: 'POST',
                body: JSON.stringify({ days: 30, limit: 500 }),
            });
            const result = data.data || {};
            const imported = Number(result.imported || 0);
            const scanned = Number(result.scanned || 0);
            showSyncNote(imported
                ? `Imported ${imported} message${imported === 1 ? '' : 's'} from Twilio.`
                : (scanned ? `No new messages. Found ${scanned} already in the CRM.` : 'No WhatsApp history found on Twilio for the last 30 days.'));
            await loadConversations({ merge: true });
            if (activeId) await pollActiveMessages();
        } catch (e) {
            showSyncNote(e.message || 'Could not sync WhatsApp messages.');
            alert(e.message || 'Could not sync WhatsApp messages.');
        } finally {
            syncInFlight = false;
            els.syncBtn.disabled = false;
            els.syncBtn.classList.remove('is-syncing');
        }
    }

    async function autoSyncRecent() {
        if (!connected || syncInFlight) return;
        syncInFlight = true;
        els.syncBtn?.classList.add('is-syncing');
        try {
            const data = await api('/sync', {
                method: 'POST',
                body: JSON.stringify({ recent: true, minutes: 90 }),
            });
            const imported = Number(data.data?.imported || 0);
            if (imported > 0) {
                showSyncNote(`Auto-synced ${imported} new message${imported === 1 ? '' : 's'}.`);
                await loadConversations({ merge: true });
                if (activeId) await pollActiveMessages();
            }
        } catch (e) {
            console.warn('WhatsApp auto-sync failed', e);
        } finally {
            syncInFlight = false;
            els.syncBtn?.classList.remove('is-syncing');
        }
    }

    els.syncBtn?.addEventListener('click', () => syncMessages().catch(console.error));
    document.getElementById('waRefreshBtn').addEventListener('click', () => loadConversations().catch(console.error));
    document.getElementById('waBackBtn').addEventListener('click', () => {
        els.sidebar.classList.remove('hidden-mobile');
        els.main.classList.add('hidden-mobile');
    });
    document.getElementById('waMarkUnreadBtn').addEventListener('click', async () => {
        if (!activeId) return;
        try {
            await api(`/conversations/${activeId}/read`, {
                method: 'PATCH',
                body: JSON.stringify({ is_read: false }),
            });
        } catch (e) {
            return;
        }
        const idx = conversations.findIndex(c => Number(c.id) === activeId);
        if (idx >= 0) conversations[idx] = { ...conversations[idx], is_read: false, unread_count: 1 };
        renderThreads();
        window.updateHeaderNotificationsBadge?.();
        window.updateSidebarUnreadBadges?.();
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
    els.text.addEventListener('input', () => {
        els.text.style.height = 'auto';
        els.text.style.height = Math.min(els.text.scrollHeight, 110) + 'px';
    });
    document.querySelectorAll('.wa-read-filters .wa-chip').forEach(chip => {
        chip.addEventListener('click', () => {
            document.querySelectorAll('.wa-read-filters .wa-chip').forEach(c => c.classList.remove('active'));
            chip.classList.add('active');
            readFilter = chip.dataset.read || '';
            loadConversations().catch(console.error);
        });
    });
    document.getElementById('waAttachImage').addEventListener('click', () => { uploadKind = 'image'; els.file.accept = 'image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp'; els.file.click(); });
    document.getElementById('waAttachVideo').addEventListener('click', () => { uploadKind = 'video'; els.file.accept = 'video/mp4,.mp4,.3gp'; els.file.click(); });
    document.getElementById('waAttachFile').addEventListener('click', () => { uploadKind = 'document'; els.file.accept = '*/*'; els.file.click(); });
    els.file.addEventListener('change', () => uploadAndSend(els.file.files[0], uploadKind));
    document.getElementById('waCallBtn').addEventListener('click', openWhatsApp);
    document.getElementById('waOpenBtn').addEventListener('click', openWhatsApp);

    els.list.addEventListener('scroll', () => {
        if (convLoading || !convHasMore) return;
        const remaining = els.list.scrollHeight - els.list.scrollTop - els.list.clientHeight;
        if (remaining < 120) loadConversations({ append: true }).catch(console.error);
    });

    els.messages.addEventListener('scroll', () => {
        if (els.messages.scrollTop < 48) loadOlderMessages();
    });

    window.waTemplates = window.initChannelReplyTemplates({
        prefix: 'wa',
        bodyMax: 4096,
        label: 'WhatsApp Templates',
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
            autoSyncTimer = setInterval(() => {
                autoSyncRecent().catch(() => {});
            }, 45000);
            setTimeout(() => autoSyncRecent().catch(console.warn), 8000);
            pollTimer = setInterval(async () => {
                try {
                    await loadConversations({ merge: true });
                    await pollActiveMessages();
                } catch (e) {}
            }, 5000);
        }
    })();
})();
