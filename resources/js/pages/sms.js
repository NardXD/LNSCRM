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

    function formatStamp(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        const now = new Date();
        const time = d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
        if (d.toDateString() === now.toDateString()) return 'Today ' + time;
        const yesterday = new Date(now);
        yesterday.setDate(now.getDate() - 1);
        if (d.toDateString() === yesterday.toDateString()) return 'Yesterday ' + time;
        const weekAgo = new Date(now);
        weekAgo.setDate(now.getDate() - 6);
        if (d > weekAgo) {
            return d.toLocaleDateString([], { weekday: 'long' }) + ' ' + time;
        }
        if (d.getFullYear() === now.getFullYear()) {
            return d.toLocaleDateString([], { month: 'short', day: 'numeric' }) + ' at ' + time;
        }
        return d.toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' }) + ' at ' + time;
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
        els.headerStatus.textContent = (conv.peer_phone || 'SMS') + assignedLeadSuffix(conv);
    }

    function contactHistoryOpts(conv) {
        return {
            phone: conv.peer_phone || '',
            name: conv.name || '',
            excludeChannel: 'sms',
            excludeId: conv.id,
            canEditLead: true,
            onLeadUpdated: applyLeadToActive,
            onSaved(data) {
                if (data?.data) applyLeadToActive(data.data);
                const current = conversations.find(c => c.id === activeId) || conv;
                window.loadChannelContactHistory('#smsContactHistory', contactHistoryOpts(current));
            },
        };
    }

    function nearBottom() {
        return els.messages.scrollHeight - els.messages.scrollTop - els.messages.clientHeight < 80;
    }

    function lastBubble() {
        const nodes = els.messageList.querySelectorAll('.sms-bubble');
        return nodes[nodes.length - 1] || null;
    }

    function renderThreads() {
        els.list.removeAttribute('aria-busy');
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
                    ${assignedLeadLine(c)}
                </div>
                ${c.unread_count ? `<span class="sms-badge">${c.unread_count}</span>` : ''}
            </div>
        `).join('') + (convHasMore ? `<div class="sms-list-hint" id="smsListMore">Scroll for older chats</div>` : '');

        els.list.querySelectorAll('.sms-thread').forEach(node => {
            node.addEventListener('click', () => openConversation(Number(node.dataset.id)));
        });
    }

    function stampMarkup(iso) {
        return `<div class="sms-stamp" data-day="${dayKey(iso)}" data-ts="${iso}">${escapeHtml(formatStamp(iso))}</div>`;
    }

    function messageMarkup(m) {
        const status = (m.status || '').toLowerCase();
        const failed = status === 'failed' || status === 'undelivered';
        const iso = m.sent_at || m.created_at || '';
        return `<div class="sms-bubble ${m.direction}${failed ? ' failed' : ''}" data-id="${m.id}" data-direction="${m.direction}" data-day="${dayKey(iso)}" data-ts="${iso}" data-status="${escapeHtml(status)}">${escapeHtml(m.body || '')}</div>`;
    }

    function refreshThreadChrome() {
        const nodes = [...els.messageList.querySelectorAll('.sms-bubble')];
        els.messageList.querySelectorAll('.sms-delivered').forEach(n => n.remove());
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
            node.classList.toggle('follow', !!samePrev);
        });

        const lastOut = [...nodes].reverse().find(n => n.dataset.direction === 'outbound');
        if (!lastOut) return;
        const st = (lastOut.dataset.status || '').toLowerCase();
        const failed = lastOut.classList.contains('failed') || st === 'failed' || st === 'undelivered';
        let label = 'Delivered';
        if (failed) label = 'Not Delivered';
        else if (st === 'queued' || st === 'accepted' || st === 'sending') label = 'Sending';
        lastOut.insertAdjacentHTML('afterend', `<div class="sms-delivered${failed ? ' is-failed' : ''}">${label}</div>`);
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
        lastDirection = m.direction;
        lastDayKey = dayKey(iso);
        if (!oldestMessageId || m.id < oldestMessageId) oldestMessageId = m.id;
        refreshThreadChrome();
    }

    function prependMessages(items) {
        if (!items.length) return;
        const firstStamp = els.messageList.firstElementChild?.classList.contains('sms-stamp')
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
            lastDirection = lastDirection || m.direction;
            prevIso = iso;
            if (!oldestMessageId || m.id < oldestMessageId) oldestMessageId = m.id;
        });
        els.messageList.insertAdjacentHTML('afterbegin', html);
        if (firstStamp && prevIso && !shouldStamp(prevIso, firstStamp.dataset.ts)) {
            firstStamp.remove();
        }
        refreshThreadChrome();
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
            window.smsTemplates?.applyBootstrap(data);
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
        els.headerName.textContent = conv.name || conv.peer_phone || 'Contact';
        els.headerStatus.textContent = (conv.peer_phone || 'SMS') + assignedLeadSuffix(conv);
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
            Object.assign(conv, conversations[idx] || data.conversation, { unread_count: 0 });
            els.headerStatus.textContent = (conv.peer_phone || 'SMS') + assignedLeadSuffix(conv);
            renderThreads();
        }

        document.querySelector('.sms-layout')?.classList.add('with-history');
        window.loadChannelContactHistory('#smsContactHistory', contactHistoryOpts(conv));
        window.updateHeaderNotificationsBadge?.();
        window.updateSidebarUnreadBadges?.();
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

    function phonesLooselyMatch(a, b) {
        const da = String(a || '').replace(/\D+/g, '');
        const db = String(b || '').replace(/\D+/g, '');
        if (!da || !db) return false;
        if (da === db) return true;
        const len = Math.min(10, da.length, db.length);
        return len >= 7 && da.slice(-len) === db.slice(-len);
    }

    async function openPhoneFromQuery() {
        const params = new URLSearchParams(window.location.search);
        const phone = params.get('phone');
        if (!phone) return;
        const name = params.get('name');
        const match = conversations.find(c => phonesLooselyMatch(c.peer_phone, phone));
        if (match) {
            await openConversation(match.id);
            return;
        }
        els.newTo.value = phone.trim();
        if (els.newName) els.newName.value = name ? name.trim() : '';
        els.modal.hidden = false;
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

    window.smsTemplates = window.initChannelReplyTemplates({
        prefix: 'sms',
        bodyMax: 1600,
        label: 'SMS Templates',
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
            } else {
                await openPhoneFromQuery();
            }
            pollTimer = setInterval(() => {
                loadConversations({ merge: true }).catch(() => {});
                pollActiveMessages().catch(() => {});
            }, 12000);
        }
    })();
})();
