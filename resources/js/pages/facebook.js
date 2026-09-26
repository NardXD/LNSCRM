(function () {
    const root = document.getElementById('fbApp');
    if (!root) return;

    const apiBase = root.dataset.apiBase;
    const csrf = root.dataset.csrf;
    const appTimezone = root.dataset.timezone || 'Asia/Manila';
    const PAGE_SIZE = 40;
    let connected = root.dataset.connected === '1';
    let conversations = [];
    let activeId = null;
    let activeHistoryOpts = null;
    let channelFilter = '';
    let readFilter = '';
    let pollTimer = null;
    let searchTimer = null;
    let uploadKind = 'file';
    let convHasMore = false;
    let convLoading = false;
    let convPolling = false;
    let messagesHasMore = false;
    let loadOlderInProgress = false;
    let messageIds = new Set();
    let oldestMessageId = null;
    let hydrateInFlight = null;

    const els = {
        list: document.getElementById('fbThreadList'),
        empty: document.getElementById('fbEmpty'),
        chat: document.getElementById('fbChat'),
        messages: document.getElementById('fbMessages'),
        messageList: document.getElementById('fbMessageList'),
        loadOlder: document.getElementById('fbLoadOlder'),
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
        syncBtn: document.getElementById('fbSyncBtn'),
        syncNote: document.getElementById('fbSyncNote'),
    };
    let hasPageToken = false;
    let syncInFlight = false;
    let autoSyncTimer = null;

    async function api(path, options = {}) {
        let res;
        try {
            res = await fetch(apiBase + path, {
                ...options,
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    ...(options.body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
                    ...(options.headers || {}),
                },
            });
        } catch (e) {
            if (e?.name === 'AbortError') {
                throw new Error('Sync timed out. Newer messages keep coming in via auto-sync; try Sync again or use Last 30 days under Integrations.');
            }
            throw new Error(e?.message || 'Could not reach the server.');
        }
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            const validation = data.errors
                ? Object.values(data.errors).flat().filter(Boolean).join(' ')
                : '';
            const timeout = [408, 502, 504, 524].includes(res.status);
            throw new Error(
                data.message || data.error || validation
                || (timeout
                    ? 'Sync timed out. Try again — auto-sync will keep importing recent messages.'
                    : `Request failed (HTTP ${res.status}).`)
            );
        }
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
            el.textContent = '';
            el.innerHTML = `<img src="${escapeHtml(pic)}" alt="" loading="lazy" decoding="async">`;
            return;
        }
        el.innerHTML = '';
        el.textContent = initials(name);
    }

    function avatarMarkup(name, pic) {
        if (pic) {
            return `<div class="fb-avatar"><img src="${escapeHtml(pic)}" alt="" loading="lazy" decoding="async"></div>`;
        }
        return `<div class="fb-avatar">${escapeHtml(initials(name))}</div>`;
    }

    function escapeHtml(str) {
        return String(str || '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s]));
    }

    function conversationLabelChips(c) {
        const items = (c?.labels || []).filter(l => l?.name);
        if (!items.length) return '';
        return `<div class="channel-label-chips">${items.map(l => {
            const color = l.color || '#4338ca';
            return `<span class="channel-label-chip" style="background:${escapeHtml(color)}">${escapeHtml(l.name)}</span>`;
        }).join('')}</div>`;
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
        els.headerStatus.textContent = channelLabel(conv.channel) + (conv.username ? ' · @' + conv.username : '') + assignedLeadSuffix(conv);
    }

    function applyConversationLabelsToActive(conversationId, labels) {
        const idx = conversations.findIndex(c => c.id === conversationId);
        if (idx < 0) return;
        conversations[idx] = { ...conversations[idx], labels: labels || [] };
        renderThreads();
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

    function visibleConversations() {
        if (readFilter === 'unread') return conversations.filter(c => !c.is_read);
        if (readFilter === 'read') return conversations.filter(c => c.is_read);
        return conversations;
    }

    function threadSkeletonMarkup(count = 8) {
        return `<div class="fb-skel-list" aria-hidden="true">${Array.from({ length: count }, () => `
            <div class="fb-skel-thread">
                <div class="fb-skel-avatar"></div>
                <div class="fb-skel-lines">
                    <span class="fb-skel-line w-55"></span>
                    <span class="fb-skel-line w-80"></span>
                </div>
            </div>`).join('')}</div>`;
    }

    function messageSkeletonMarkup() {
        return `<div class="fb-skel-messages" aria-hidden="true">
            <div class="fb-skel-bubble in"></div>
            <div class="fb-skel-bubble in short"></div>
            <div class="fb-skel-bubble out"></div>
            <div class="fb-skel-bubble out short"></div>
            <div class="fb-skel-bubble in"></div>
        </div>`;
    }

    function showThreadSkeleton() {
        els.list.setAttribute('aria-busy', 'true');
        els.list.innerHTML = threadSkeletonMarkup();
    }

    function showAppendSkeleton() {
        if (document.getElementById('fbListMoreSkel')) return;
        document.getElementById('fbListMore')?.remove();
        els.list.insertAdjacentHTML('beforeend', `<div id="fbListMoreSkel">${threadSkeletonMarkup(3)}</div>`);
    }

    function showMessageSkeleton() {
        els.messageList.innerHTML = messageSkeletonMarkup();
    }

    function renderThreads() {
        const visible = visibleConversations();
        els.list.removeAttribute('aria-busy');
        if (!visible.length) {
            els.list.innerHTML = `<div class="fb-list-hint">${readFilter ? 'No ' + readFilter + ' conversations.' : 'No conversations yet.'}</div>`;
            return;
        }

        els.list.innerHTML = visible.map(c => `
            <div class="fb-thread ${c.id === activeId ? 'active' : ''} ${!c.is_read ? 'unread' : ''}" data-id="${c.id}">
                ${avatarMarkup(c.name, c.profile_pic)}
                <div class="fb-thread-body">
                    <div class="fb-thread-top">
                        <div class="fb-thread-name">${escapeHtml(c.name || channelLabel(c.channel) + ' User')}</div>
                        <div class="fb-thread-time">${formatListTime(c.last_message_at)}</div>
                    </div>
                    <div class="fb-thread-preview">${escapeHtml(c.last_message_preview || '')}</div>
                    ${assignedLeadLine(c)}
                    <span class="fb-channel-tag ${c.channel === 'instagram' ? 'instagram' : ''}">${channelLabel(c.channel)}</span>
                </div>
                ${!c.is_read ? `<span class="fb-unread-dot" aria-hidden="true"></span>` : ''}
            </div>
        `).join('') + (convHasMore ? `<div class="fb-list-hint" id="fbListMore">Scroll for older chats</div>` : '');
    }

    function stampMarkup(iso) {
        return `<div class="fb-stamp" data-day="${dayKey(iso)}" data-ts="${iso}">${escapeHtml(formatStamp(iso))}</div>`;
    }

    function messageBody(m) {
        if (m.type === 'image' && m.media_url) {
            return `${m.text ? `<div>${escapeHtml(m.text)}</div>` : ''}<img src="${escapeHtml(m.media_url)}" alt="Image" loading="lazy" decoding="async">`;
        }
        if (m.type === 'video' && m.media_url) {
            return `<video controls preload="metadata" src="${escapeHtml(m.media_url)}"></video>${m.text ? `<div>${escapeHtml(m.text)}</div>` : ''}`;
        }
        if (m.type === 'audio' && m.media_url) {
            return `<audio controls preload="none" src="${escapeHtml(m.media_url)}"></audio>`;
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

    function prependMessages(items) {
        if (!items.length) return;
        const firstStamp = els.messageList.firstElementChild?.classList.contains('fb-stamp')
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
        const first = els.messageList.querySelector('.fb-bubble');
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
            window.fbTemplates?.applyBootstrap(data);
            hasPageToken = !!data.account?.has_page_access_token;
            if (data.account?.token_expired) {
                showSyncNote('Your Facebook Page Access Token expired. Update it under Integrations, then click Sync.');
                if (els.connectLink) {
                    els.connectLink.style.display = '';
                    els.connectLink.textContent = 'Update Page Access Token';
                }
            }
            const parts = [];
            if (data.account?.page_name) parts.push(data.account.page_name);
            if (data.account?.instagram_username) parts.push('@' + data.account.instagram_username);
            if (parts.length) els.accountLabel.textContent = parts.join(' · ');
            if (!connected) {
                els.emptyTitle.textContent = 'Connect Facebook';
                els.emptyText.textContent = 'Connect Twilio for Messenger, and a Page Access Token plus Meta Instagram webhooks under Integrations to receive Instagram Direct.';
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

    function conversationParams({ append = false, poll = false, sync = false } = {}) {
        const params = new URLSearchParams({ limit: String(PAGE_SIZE) });
        const q = (els.search.value || '').trim();
        if (q) params.set('q', q);
        if (channelFilter) params.set('channel', channelFilter);
        if (readFilter) params.set('read', readFilter);
        if (poll) params.set('poll', '1');
        if (sync) params.set('sync', '1');
        if (append && conversations.length) {
            params.set('before_id', String(conversations[conversations.length - 1].id));
        }
        return params;
    }

    async function loadConversations({ append = false, merge = false, poll = false, sync = false } = {}) {
        if (poll) {
            if (convPolling || convLoading) return;
            convPolling = true;
        } else {
            if (convLoading) return;
            convLoading = true;
            if (!append && !merge) showThreadSkeleton();
            if (append) showAppendSkeleton();
        }
        try {
            const data = await api('/conversations?' + conversationParams({ append, poll, sync }).toString());
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
            window.updateSidebarUnreadBadges?.();
        } catch (e) {
            console.error(e);
            if (!append && !merge && !poll) {
                els.list.removeAttribute('aria-busy');
                els.list.innerHTML = `<div class="fb-list-hint">${escapeHtml(e.message || 'Could not load conversations.')}</div>`;
            }
        } finally {
            if (poll) convPolling = false;
            else convLoading = false;
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
        while (messagesHasMore && els.messages.scrollHeight <= els.messages.clientHeight + 4 && guard < 3) {
            const loaded = await loadOlderMessages();
            if (!loaded) break;
            guard += 1;
        }
    }

    function rememberConversation(conv) {
        if (!conv || conv.id == null) return null;
        const id = Number(conv.id);
        const next = { ...conv, is_read: true };
        const idx = conversations.findIndex(c => Number(c.id) === id);
        if (idx >= 0) {
            conversations[idx] = { ...conversations[idx], ...next };
            return conversations[idx];
        }
        conversations.unshift(next);
        return conversations[0];
    }

    function applyConversationHeader(conv) {
        els.headerName.textContent = conv.name || (channelLabel(conv.channel) + ' User');
        els.headerStatus.textContent = channelLabel(conv.channel) + (conv.username ? ' · @' + conv.username : '') + assignedLeadSuffix(conv);
        setAvatar(els.headerAvatar, conv.name, conv.profile_pic);
    }

    function mergeIncomingMessages(rows) {
        const newer = (rows || []).filter(m => !messageIds.has(m.id));
        if (!newer.length) return;
        const pin = nearBottom();
        newer.forEach(appendMessage);
        if (pin) els.messages.scrollTop = els.messages.scrollHeight;
    }

    function historyOptsFor(conv, data, wasPlaceholder) {
        const extractedName = data.conversation?.extracted_name || (data.conversation?.extracted_names || [])[0] || '';
        const historyOpts = {
            name: extractedName || conv.name || conv.username || '',
            username: conv.username || '',
            excludeChannel: 'facebook',
            excludeId: conv.id,
            source: conv.channel === 'instagram' ? 'instagram' : 'facebook',
            facebook_name: conv.channel === 'instagram' ? null : conv.name,
            instagram_username: conv.channel === 'instagram' ? (conv.username || conv.name) : null,
            phone: (data.conversation?.extracted_phones || [])[0] || '',
            email: (data.conversation?.extracted_emails || [])[0] || '',
            extracted_phones: data.conversation?.extracted_phones || [],
            extracted_emails: data.conversation?.extracted_emails || [],
            extracted_name: extractedName,
            extracted_names: data.conversation?.extracted_names || [],
            needsLeadDetails: wasPlaceholder,
            canEditLead: true,
            onLeadUpdated: applyLeadToActive,
            conversationLabels: conv.labels || [],
            conversationLabelsApi: `/api/facebook/conversations/${conv.id}/labels`,
            onConversationLabelsChange: (labels) => applyConversationLabelsToActive(conv.id, labels),
            onSaved(saved, extra) {
                if (extra?.existing && saved.existing_lead_id) {
                    window.location.href = '/leads?lead=' + saved.existing_lead_id;
                    return;
                }
                const newName = saved.data?.name;
                if (newName) {
                    const idx = conversations.findIndex(c => c.id === conv.id);
                    if (idx >= 0) conversations[idx] = { ...conversations[idx], name: newName };
                    els.headerName.textContent = newName;
                    renderThreads();
                    historyOpts.name = newName;
                    historyOpts.facebook_name = conv.channel === 'instagram' ? null : newName;
                    historyOpts.instagram_username = conv.channel === 'instagram' ? (conv.username || newName) : null;
                    historyOpts.extracted_name = newName;
                    historyOpts.needsLeadDetails = false;
                }
                if (saved.data) applyLeadToActive(saved.data);
                window.loadChannelContactHistory('#fbContactHistory', historyOpts);
            },
        };
        return historyOpts;
    }

    async function hydrateConversation(id, wasPlaceholder) {
        if (hydrateInFlight === id) return;
        hydrateInFlight = id;
        try {
            const data = await api(`/conversations/${id}/messages?limit=${PAGE_SIZE}&hydrate=1`);
            if (Number(activeId) !== Number(id)) return;
            mergeIncomingMessages(data.data || []);
            let conv = conversations.find(c => Number(c.id) === Number(id));
            if (data.conversation) {
                conv = rememberConversation({ ...data.conversation, is_read: true }) || conv;
                if (conv) applyConversationHeader(conv);
                renderThreads();
            }
            if (conv) {
                const historyOpts = historyOptsFor(conv, data, wasPlaceholder);
                activeHistoryOpts = historyOpts;
                window.loadChannelContactHistory('#fbContactHistory', historyOpts);
            }
        } catch (e) {
            console.warn('Facebook hydrate failed', e);
        } finally {
            if (hydrateInFlight === id) hydrateInFlight = null;
        }
    }

    async function openConversation(id) {
        id = Number(id);
        if (!id) return;

        let conv = conversations.find(c => Number(c.id) === id) || null;
        activeId = id;
        els.empty.style.display = 'none';
        els.chat.style.display = 'flex';
        resetMessages();
        showMessageSkeleton();

        if (conv) {
            conv.is_read = true;
            applyConversationHeader(conv);
            renderThreads();
        } else {
            els.headerName.textContent = 'Loading…';
            els.headerStatus.textContent = '';
            els.headerAvatar.innerHTML = '';
            els.headerAvatar.textContent = '';
        }

        if (window.matchMedia('(max-width: 900px)').matches) {
            els.sidebar.classList.add('hidden-mobile');
            els.main.classList.remove('hidden-mobile');
        }

        let data = null;
        try {
            data = await api(`/conversations/${id}/messages?limit=${PAGE_SIZE}`);
        } catch (e) {
            return;
        }
        if (Number(activeId) !== id) return;

        conv = rememberConversation(data.conversation) || conv;
        if (!conv) return;

        const wasPlaceholder = ['messenger user', 'instagram user', 'facebook user'].includes(String(conv.name || '').trim().toLowerCase());
        applyConversationHeader(conv);
        renderThreads();
        resetMessages();
        messagesHasMore = !!data.has_more;
        if (els.loadOlder) els.loadOlder.hidden = !messagesHasMore;
        (data.data || []).forEach(appendMessage);
        oldestMessageId = firstLoadedMessageId();
        els.messages.scrollTop = els.messages.scrollHeight;
        await fillUntilScrollable();
        if (Number(activeId) !== id) return;
        els.messages.scrollTop = els.messages.scrollHeight;
        window.updateHeaderNotificationsBadge?.();
        window.updateSidebarUnreadBadges?.();

        document.querySelector('.fb-layout')?.classList.add('with-history');
        const historyOpts = historyOptsFor(conv, data, wasPlaceholder);
        activeHistoryOpts = historyOpts;
        window.loadChannelContactHistory('#fbContactHistory', historyOpts);
        hydrateConversation(id, wasPlaceholder);
    }

    async function pollActiveMessages() {
        if (!activeId || loadOlderInProgress) return;
        const data = await api(`/conversations/${activeId}/messages?limit=${PAGE_SIZE}&poll=1`);
        mergeIncomingMessages(data.data || []);

        const phones = data.conversation?.extracted_phones || [];
        const emails = data.conversation?.extracted_emails || [];
        const names = data.conversation?.extracted_names || [];
        const extractedName = data.conversation?.extracted_name || names[0] || '';
        if (!activeHistoryOpts || document.querySelector('#fbContactHistory .chp-lead-form')) {
            return;
        }
        const nextPhone = phones[0] || '';
        const nextEmail = emails[0] || '';
        const samePhones = JSON.stringify(phones) === JSON.stringify(activeHistoryOpts.extracted_phones || []);
        const sameEmails = JSON.stringify(emails) === JSON.stringify(activeHistoryOpts.extracted_emails || []);
        const sameName = extractedName === (activeHistoryOpts.extracted_name || '');
        if (samePhones && sameEmails && sameName && nextPhone === (activeHistoryOpts.phone || '') && nextEmail === (activeHistoryOpts.email || '')) {
            return;
        }
        if (data.conversation?.name && activeId) {
            const idx = conversations.findIndex(c => c.id === activeId);
            if (idx >= 0 && conversations[idx].name !== data.conversation.name) {
                conversations[idx] = { ...conversations[idx], name: data.conversation.name };
                els.headerName.textContent = data.conversation.name;
                renderThreads();
            }
        }
        activeHistoryOpts.phone = nextPhone;
        activeHistoryOpts.email = nextEmail;
        activeHistoryOpts.extracted_phones = phones;
        activeHistoryOpts.extracted_emails = emails;
        activeHistoryOpts.extracted_name = extractedName;
        activeHistoryOpts.extracted_names = names;
        if (extractedName) {
            activeHistoryOpts.name = extractedName;
        }
        window.loadChannelContactHistory('#fbContactHistory', activeHistoryOpts);
    }

    function showSyncNote(text) {
        if (!els.syncNote) return;
        els.syncNote.textContent = text;
        els.syncNote.classList.toggle('is-visible', !!text);
    }

    async function syncMessengerInbox() {
        if (!els.syncBtn || syncInFlight) return;
        if (!hasPageToken) {
            const go = confirm('Add a Facebook Page Access Token under Integrations to import replies sent from Messenger / Page Inbox.\n\nSync Twilio history only?');
            if (!go) {
                window.location.href = root.dataset.integrationsUrl || '/integrations';
                return;
            }
        }
        syncInFlight = true;
        els.syncBtn.disabled = true;
        els.syncBtn.classList.add('is-syncing');
        showSyncNote('Importing Messenger inbox, including replies sent from Facebook…');
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 120000);
        try {
            const data = await api('/sync', {
                method: 'POST',
                body: JSON.stringify({ days: 30, limit: 800 }),
                signal: controller.signal,
            });
            const result = data.data || {};
            const imported = Number(result.imported || 0);
            const skipped = Number(result.skipped || 0);
            const scanned = Number(result.scanned || 0);
            const hint = result.hint || result.graph_error || '';
            if (imported === 0 && /expired|user token|personal mailbox|read_mailbox/i.test(hint)) {
                showSyncNote(hint);
                alert(hint);
                return;
            }
            let summary = imported
                ? `Imported ${imported} message${imported === 1 ? '' : 's'} from Messenger.`
                : (scanned
                    ? `No new messages. Found ${scanned} already in the CRM.`
                    : 'No Messenger history found for the last 30 days.');
            if (skipped && imported) summary += ` ${skipped} already in CRM.`;
            if (hint) summary += ' ' + hint;
            showSyncNote(summary);
            await loadConversations({ merge: true });
            if (activeId) await pollActiveMessages();
        } catch (e) {
            showSyncNote(e.message || 'Could not sync Messenger inbox.');
            alert(e.message || 'Could not sync Messenger inbox.');
        } finally {
            clearTimeout(timer);
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
            const hint = data.data?.hint || '';
            if (imported > 0) {
                showSyncNote(`Auto-synced ${imported} new message${imported === 1 ? '' : 's'}.`);
                await loadConversations({ merge: true });
                if (activeId) await pollActiveMessages();
            } else if (hint) {
                showSyncNote(hint);
            }
        } catch (e) {
            console.warn('Facebook auto-sync failed', e);
        } finally {
            syncInFlight = false;
            els.syncBtn?.classList.remove('is-syncing');
        }
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

    document.getElementById('fbSyncBtn')?.addEventListener('click', () => syncMessengerInbox().catch(console.error));
    document.getElementById('fbRefreshBtn').addEventListener('click', () => loadConversations({ sync: true }).catch(console.error));
    document.getElementById('fbBackBtn').addEventListener('click', () => {
        els.sidebar.classList.remove('hidden-mobile');
        els.main.classList.add('hidden-mobile');
    });
    document.getElementById('fbMarkUnreadBtn').addEventListener('click', async () => {
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
        if (idx >= 0) conversations[idx] = { ...conversations[idx], is_read: false };
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
    document.querySelectorAll('.fb-filters:not(.fb-read-filters) .fb-chip').forEach(chip => {
        chip.addEventListener('click', () => {
            document.querySelectorAll('.fb-filters:not(.fb-read-filters) .fb-chip').forEach(c => c.classList.remove('active'));
            chip.classList.add('active');
            channelFilter = chip.dataset.channel || '';
            loadConversations().catch(console.error);
        });
    });
    document.querySelectorAll('.fb-read-filters .fb-chip').forEach(chip => {
        chip.addEventListener('click', () => {
            document.querySelectorAll('.fb-read-filters .fb-chip').forEach(c => c.classList.remove('active'));
            chip.classList.add('active');
            readFilter = chip.dataset.read || '';
            loadConversations().catch(console.error);
        });
    });
    document.getElementById('fbAttachImage').addEventListener('click', () => { uploadKind = 'image'; els.file.accept = 'image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp'; els.file.click(); });
    document.getElementById('fbAttachVideo').addEventListener('click', () => { uploadKind = 'video'; els.file.accept = 'video/mp4,.mp4'; els.file.click(); });
    document.getElementById('fbAttachFile').addEventListener('click', () => { uploadKind = 'file'; els.file.accept = '*/*'; els.file.click(); });
    els.file.addEventListener('change', () => uploadAndSend(els.file.files[0], uploadKind));

    els.list.addEventListener('click', (e) => {
        const node = e.target.closest('.fb-thread');
        if (!node) return;
        openConversation(Number(node.dataset.id));
    });
    els.list.addEventListener('scroll', () => {
        if (convLoading || !convHasMore) return;
        const remaining = els.list.scrollHeight - els.list.scrollTop - els.list.clientHeight;
        if (remaining < 120) loadConversations({ append: true }).catch(console.error);
    });

    els.messages.addEventListener('scroll', () => {
        if (els.messages.scrollTop < 48) loadOlderMessages();
    });

    window.fbTemplates = window.initChannelReplyTemplates({
        prefix: 'fb',
        bodyMax: 2000,
        label: 'Facebook Templates',
        api,
        getComposer: () => els.text,
        escapeHtml,
    });

    async function startConnectedPolling() {
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
                await loadConversations({ merge: true, poll: true });
                await pollActiveMessages();
            } catch (e) {}
        }, 8000);
    }

    (async function init() {
        const boot = loadBootstrap().catch(console.error);
        await Promise.all([boot, loadConversations().catch(console.error)]);
        if (connected) {
            await startConnectedPolling();
        }
    })();
    window.addEventListener('beforeunload', () => {
        if (autoSyncTimer) clearInterval(autoSyncTimer);
        if (pollTimer) clearInterval(pollTimer);
    });
})();
