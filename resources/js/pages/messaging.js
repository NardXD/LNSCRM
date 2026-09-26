/* messaging page logic (Vite entry) */
(function() {
    const app = document.getElementById('messagingApp');
    const baseUrl = app.dataset.apiBase;
    const csrf = app.dataset.csrf;
    const currentUserId = Number(app.dataset.userId || 0);
    let currentConversationId = null;
    let currentConversationType = 'direct';
    let currentGroupMembers = [];
    let conversationReceipts = [];
    let editingMessageId = null;
    let replyingTo = null;
    let companyUsers = [];
    const GROUP_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>';
    const sidebar = document.getElementById('msgSidebar');
    const main = document.getElementById('msgMain');
    const chatEl = document.getElementById('msgChat');
    const emptyEl = document.getElementById('messagingPlaceholder');

    function api(url, options = {}) {
        const opts = {
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                ...(options.headers || {})
            },
            ...options
        };
        if (opts.body && typeof opts.body === 'object' && !(opts.body instanceof FormData)) {
            opts.body = JSON.stringify(opts.body);
        }
        return fetch(url, opts);
    }

    function formatListTime(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        const now = new Date();
        if (d.toDateString() === now.toDateString()) return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
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
        if (d > weekAgo) return d.toLocaleDateString([], { weekday: 'long' }) + ' ' + time;
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

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    // Load conversations (paginated)
    const CONVERSATIONS_PAGE_SIZE = 15;
    let conversationsOffset = 0;
    let conversationsHasMore = false;
    let conversationsSearch = '';
    let loadMoreInProgress = false;
    let conversationsRequestSeq = 0;

    async function loadConversations(search = '') {
        conversationsOffset = 0;
        conversationsSearch = search;
        const seq = ++conversationsRequestSeq;
        const params = new URLSearchParams({ limit: CONVERSATIONS_PAGE_SIZE, offset: 0 });
        if (search) params.set('search', search);
        const res = await api(baseUrl + '/conversations?' + params.toString());
        const json = await res.json();
        if (seq !== conversationsRequestSeq) return; // a newer request has since superseded this one
        if (!json.success) return;
        conversationsHasMore = json.has_more ?? false;
        renderConversations(json.data, true);
    }

    async function loadMoreConversations() {
        if (loadMoreInProgress || !conversationsHasMore) return;
        loadMoreInProgress = true;
        conversationsOffset += CONVERSATIONS_PAGE_SIZE;
        const params = new URLSearchParams({ limit: CONVERSATIONS_PAGE_SIZE, offset: conversationsOffset });
        if (conversationsSearch) params.set('search', conversationsSearch);
        const res = await api(baseUrl + '/conversations?' + params.toString());
        const json = await res.json();
        if (!json.success) {
            conversationsOffset -= CONVERSATIONS_PAGE_SIZE;
        } else {
            conversationsHasMore = json.has_more ?? false;
            renderConversations(json.data, false);
        }
        loadMoreInProgress = false;
    }
    window.loadMoreConversations = loadMoreConversations;

    function createChatItem(c) {
        const item = document.createElement('div');
        item.className = 'msg-thread' + (c.id === currentConversationId ? ' active' : '') + (c.unread_count ? ' unread' : '');
        item.dataset.conversationId = c.id;
        const avatar = c.type === 'group'
            ? (c.avatar_photo
                ? '<div class="msg-avatar group"><img src="' + c.avatar_photo + '" alt=""></div>'
                : '<div class="msg-avatar group">' + GROUP_ICON + '</div>')
            : '<div class="msg-avatar">' + (c.avatar_photo ? '<img src="' + c.avatar_photo + '" alt="">' : escapeHtml(c.avatar_initials || '?')) + '</div>';
        item.innerHTML = avatar + `
            <div class="msg-thread-body">
                <div class="msg-thread-top">
                    <div class="msg-thread-name">${escapeHtml(c.name)}</div>
                    <div class="msg-thread-time">${formatListTime(c.last_message_at)}</div>
                </div>
                <div class="msg-thread-preview">${escapeHtml(c.preview || '')}</div>
            </div>
            ${c.unread_count ? '<span class="msg-badge">' + c.unread_count + '</span>' : ''}
        `;
        item.addEventListener('click', () => selectConversation(c.id));
        return item;
    }

    function renderConversations(conversations, replace = true) {
        const container = document.getElementById('chatsListItems');
        const empty = document.getElementById('chatsListEmpty');
        const loadMoreEl = document.getElementById('chatsLoadMore');
        container.removeAttribute('aria-busy');
        if (replace) container.innerHTML = '';
        if (conversations.length === 0 && replace) {
            empty.style.display = 'block';
            if (loadMoreEl) loadMoreEl.style.display = 'none';
            return;
        }
        empty.style.display = 'none';
        conversations.forEach(c => container.appendChild(createChatItem(c)));
        if (loadMoreEl) loadMoreEl.style.display = conversationsHasMore ? 'block' : 'none';
    }

    async function selectConversation(id) {
        currentConversationId = id;
        document.querySelectorAll('.msg-thread').forEach(i => {
            i.classList.toggle('active', i.dataset.conversationId == id);
        });
        emptyEl.style.display = 'none';
        chatEl.style.display = 'flex';
        if (window.matchMedia('(max-width: 900px)').matches) {
            sidebar.classList.add('hidden-mobile');
            main.classList.remove('hidden-mobile');
        }
        await loadMessages(id);
    }

    window.goBackToConversationList = function() {
        sidebar.classList.remove('hidden-mobile');
        main.classList.add('hidden-mobile');
    };

    const MESSAGES_PAGE_SIZE = 25;
    let messagesHasMore = false;
    let messagesOldestId = null;
    let loadOlderInProgress = false;

    function formatBodyWithMentions(text, mentions) {
        let html = escapeHtml(text || '');
        const list = [...(mentions || [])]
            .filter(m => m && m.name)
            .sort((a, b) => String(b.name).length - String(a.name).length);
        list.forEach(m => {
            const token = escapeHtml('@' + m.name);
            const pattern = token.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            html = html.replace(new RegExp(pattern + '(?![\\w])', 'gi'), '<span class="msg-mention">' + token + '</span>');
        });
        return html;
    }

    function stampMarkup(iso) {
        const el = document.createElement('div');
        el.className = 'msg-stamp';
        el.dataset.ts = iso || '';
        el.dataset.day = dayKey(iso);
        el.textContent = formatStamp(iso);
        return el;
    }

    function buildMessageElement(m) {
        const mine = m.is_mine || m.author === 'You';
        const dir = mine ? 'outbound' : 'inbound';
        const iso = m.created_at || '';
        const row = document.createElement('div');
        row.className = 'msg-row ' + dir + (currentConversationType === 'direct' ? ' direct' : '');
        row.dataset.messageId = m.id;
        row.dataset.direction = dir;
        row.dataset.author = m.author || '';
        row.dataset.ts = iso;
        row.dataset.day = dayKey(iso);
        row.dataset.body = m.body || '';
        row.dataset.editedAt = m.edited_at || '';
        row.dataset.hasAttachment = m.attachment_path ? '1' : '0';
        row.dataset.preview = m.body || (m.attachment_type === 'image' ? 'Photo' : (m.attachment_name || 'Attachment') || '');
        row.dataset.seenBy = JSON.stringify(m.seen_by || []);
        row.dataset.reactions = JSON.stringify(m.reactions || []);
        row.dataset.myReaction = m.my_reaction || '';
        row.dataset.mentions = JSON.stringify(m.mentions || []);
        if ((m.reactions || []).length) row.classList.add('has-reactions');
        if (m.mentions_me) row.classList.add('mentions-me');

        let quote = '';
        if (m.reply_to && m.reply_to.id) {
            quote = '<button type="button" class="msg-quote" onclick="window.scrollToRepliedMessage(' + Number(m.reply_to.id) + ', event)">' +
                '<span class="msg-quote-author">' + escapeHtml(m.reply_to.author || '') + '</span>' +
                '<span class="msg-quote-body">' + escapeHtml(m.reply_to.body || '') + '</span>' +
                '</button>';
        }
        let body = quote;
        if (m.body) body += '<div class="msg-bubble-text">' + formatBodyWithMentions(m.body, m.mentions || []) + '</div>';
        if (m.attachment_path) {
            if (m.attachment_type === 'image') {
                body += '<img class="msg-inline-image" src="' + escapeHtml(m.attachment_path) + '" alt="" onclick="window.openMessageImagePreview(this.src, event)">';
            } else {
                body += '<div class="msg-attachment"><svg class="msg-attachment-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg><a href="' + escapeHtml(m.attachment_path) + '" target="_blank" rel="noopener" download class="msg-attachment-name">' + escapeHtml(m.attachment_name || 'File') + '</a></div>';
            }
        }
        const avatarInner = m.author_photo
            ? '<img src="' + escapeHtml(m.author_photo) + '" alt="">'
            : escapeHtml(m.author_initials || '?');
        const editBtn = mine
            ? '<button type="button" class="msg-edit-btn" title="Edit" aria-label="Edit" onclick="window.startEditMessage(' + Number(m.id) + ', event)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg></button>'
            : '';
        const replyBtn = currentConversationType === 'group'
            ? '<button type="button" class="msg-reply-btn" title="Reply" aria-label="Reply" onclick="window.startReplyMessage(' + Number(m.id) + ', event)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/></svg></button>'
            : '';
        const reactBtn = '<button type="button" class="msg-react-btn" title="React" aria-label="React" onclick="window.openMessageReactionPicker(' + Number(m.id) + ', event)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg></button>';
        const edited = m.edited_at ? '<div class="msg-meta"><span>Edited</span></div>' : '';
        const actions = '<div class="msg-actions">' + reactBtn + replyBtn + editBtn + '</div>';
        row.innerHTML =
            '<div class="msg-row-avatar">' + avatarInner + '</div>' +
            '<div class="msg-col">' +
                '<div class="msg-sender">' + escapeHtml(m.author || '') + '</div>' +
                '<div class="msg-bubble-wrap">' + actions + '<div class="msg-bubble ' + dir + '">' + (body || '') + '</div></div>' +
                reactionChipsMarkup(m) +
                edited +
            '</div>';
        return row;
    }

    const MESSAGE_REACTIONS = [
        { type: 'like', emoji: '👍', label: 'Like' },
        { type: 'love', emoji: '❤️', label: 'Love' },
        { type: 'care', emoji: '🥰', label: 'Care' },
        { type: 'haha', emoji: '😂', label: 'Haha' },
        { type: 'wow', emoji: '😮', label: 'Wow' },
        { type: 'sad', emoji: '😢', label: 'Sad' },
        { type: 'angry', emoji: '😡', label: 'Angry' }
    ];
    let openReactionMessageId = null;

    function reactionChipsMarkup(m) {
        const reactions = m.reactions || [];
        if (!reactions.length) return '';
        return '<div class="msg-reactions">' + reactions.map(function(r) {
            const count = Number(r.count) > 1 ? '<span class="msg-reaction-count">' + Number(r.count) + '</span>' : '';
            return '<button type="button" class="msg-reaction-chip' + (r.reacted ? ' is-mine' : '') + '" title="See who reacted" onclick="window.showMessageReactionPeople(' + Number(m.id) + ', event)">' +
                '<span>' + escapeHtml(r.emoji || '') + '</span>' + count +
                '</button>';
        }).join('') + '</div>';
    }

    function getReactionPicker() {
        let pop = document.getElementById('msgReactionPicker');
        if (pop) return pop;
        pop = document.createElement('div');
        pop.id = 'msgReactionPicker';
        pop.className = 'msg-reaction-picker';
        pop.setAttribute('role', 'listbox');
        pop.setAttribute('aria-label', 'React to message');
        pop.innerHTML = MESSAGE_REACTIONS.map(function(r) {
            return '<button type="button" data-type="' + r.type + '" title="' + r.label + '" aria-label="' + r.label + '">' + r.emoji + '</button>';
        }).join('');
        pop.addEventListener('click', function(e) {
            const btn = e.target.closest('button[data-type]');
            if (!btn || !openReactionMessageId) return;
            e.stopPropagation();
            window.reactToMessage(openReactionMessageId, btn.dataset.type);
        });
        document.body.appendChild(pop);
        document.addEventListener('click', function(e) {
            if (!pop.contains(e.target) && !e.target.closest('.msg-react-btn')) {
                closeMessageReactionPicker();
            }
        });
        return pop;
    }

    function positionReactionPicker(anchor) {
        const pop = getReactionPicker();
        const rect = anchor.getBoundingClientRect();
        const width = pop.offsetWidth || 292;
        const height = pop.offsetHeight || 52;
        let left = rect.left + (rect.width / 2) - (width / 2);
        let top = rect.top - height - 10;
        left = Math.max(8, Math.min(left, window.innerWidth - width - 8));
        if (top < 8) top = rect.bottom + 10;
        pop.style.left = left + 'px';
        pop.style.top = top + 'px';
    }

    function closeMessageReactionPicker() {
        const pop = document.getElementById('msgReactionPicker');
        if (pop) pop.classList.remove('open');
        document.querySelectorAll('.msg-row.picker-open').forEach(function(row) {
            row.classList.remove('picker-open');
        });
        openReactionMessageId = null;
    }

    window.openMessageReactionPicker = function(id, ev) {
        if (ev) ev.stopPropagation();
        const row = document.querySelector('.msg-row[data-message-id="' + id + '"]');
        if (!row) return;
        const pop = getReactionPicker();
        if (openReactionMessageId === Number(id) && pop.classList.contains('open')) {
            closeMessageReactionPicker();
            return;
        }
        closeMessageReactionPeople();
        openReactionMessageId = Number(id);
        document.querySelectorAll('.msg-row.picker-open').forEach(function(other) {
            other.classList.remove('picker-open');
        });
        row.classList.add('picker-open');
        const mine = row.dataset.myReaction || '';
        pop.querySelectorAll('button[data-type]').forEach(function(btn) {
            btn.classList.toggle('is-mine', btn.dataset.type === mine);
        });
        pop.classList.add('open');
        const anchor = (ev && ev.currentTarget) || row.querySelector('.msg-react-btn') || row.querySelector('.msg-bubble') || row;
        positionReactionPicker(anchor);
    };

    window.reactToMessage = async function(id, type) {
        if (!currentConversationId || !type) return;
        closeMessageReactionPicker();
        const res = await api(baseUrl + '/conversations/' + currentConversationId + '/messages/' + id + '/react', {
            method: 'POST',
            body: { type: type }
        });
        const json = await res.json();
        if (!json.success) {
            alert(json.message || 'Failed to react');
            return;
        }
        const row = document.querySelector('.msg-row[data-message-id="' + id + '"]');
        if (row) row.replaceWith(buildMessageElement(json.data));
        refreshThreadChrome();
        applySeenLabels();
    };

    function closeMessageReactionPeople() {
        const pop = document.getElementById('msgReactionPeople');
        if (pop) pop.classList.remove('open');
    }

    window.showMessageReactionPeople = function(id, ev) {
        if (ev) ev.stopPropagation();
        closeMessageReactionPicker();
        const row = document.querySelector('.msg-row[data-message-id="' + id + '"]');
        if (!row) return;
        let reactions = [];
        try { reactions = JSON.parse(row.dataset.reactions || '[]'); } catch (_) { reactions = []; }
        let pop = document.getElementById('msgReactionPeople');
        if (!pop) {
            pop = document.createElement('div');
            pop.id = 'msgReactionPeople';
            pop.className = 'msg-reaction-people';
            document.body.appendChild(pop);
            document.addEventListener('click', function(e) {
                if (!pop.contains(e.target) && !e.target.closest('.msg-reaction-chip')) {
                    pop.classList.remove('open');
                }
            });
        }
        if (pop.classList.contains('open') && pop.dataset.messageId === String(id)) {
            pop.classList.remove('open');
            return;
        }
        const people = [];
        reactions.forEach(function(group) {
            (group.users || []).forEach(function(u) {
                people.push({ emoji: group.emoji, name: u.name, photo: u.photo, initials: u.initials });
            });
        });
        pop.innerHTML = people.length
            ? people.map(function(p) {
                const avatar = p.photo
                    ? '<img src="' + escapeHtml(p.photo) + '" alt="">'
                    : '<span class="msg-seen-initials">' + escapeHtml(p.initials || '?') + '</span>';
                return '<div class="msg-reaction-person"><span class="msg-reaction-person-emoji">' + escapeHtml(p.emoji || '') + '</span>' + avatar + '<span>' + escapeHtml(p.name || '') + '</span></div>';
            }).join('')
            : '<div class="msg-reaction-people-empty">No reactions yet</div>';
        pop.dataset.messageId = String(id);
        const rect = (ev && ev.currentTarget ? ev.currentTarget : row).getBoundingClientRect();
        pop.style.left = Math.max(8, Math.min(rect.left, window.innerWidth - 288)) + 'px';
        pop.style.top = (rect.bottom + 6) + 'px';
        pop.classList.add('open');
    };

    function seenForRow(row) {
        try {
            const fromServer = JSON.parse(row.dataset.seenBy || '[]');
            if (Array.isArray(fromServer)) return fromServer;
        } catch (_) { /* ignore */ }
        return [];
    }

    function applySeenLabels() {
        document.querySelectorAll('.msg-seen').forEach(el => el.remove());
        const rows = [...document.querySelectorAll('#messageGroup .msg-row.outbound')];
        const last = rows[rows.length - 1];
        if (!last) return;
        const seen = seenForRow(last);
        if (!seen.length) return;
        const el = document.createElement('div');
        const isGroup = currentConversationType === 'group';
        el.className = 'msg-seen' + (isGroup ? ' is-clickable' : '');
        if (!isGroup) {
            const when = seen[0].last_read_at
                ? ' · ' + new Date(seen[0].last_read_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
                : '';
            el.textContent = 'Seen' + when;
        } else if (seen.length === 1) {
            el.textContent = 'Seen by ' + seen[0].name;
        } else {
            el.textContent = 'Seen by ' + seen.length;
        }
        if (isGroup) {
            el.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleSeenPopover(el, seen);
            });
        }
        last.querySelector('.msg-col').appendChild(el);
    }

    function toggleSeenPopover(anchor, seen) {
        let pop = document.getElementById('msgSeenPopover');
        if (!pop) {
            pop = document.createElement('div');
            pop.id = 'msgSeenPopover';
            pop.className = 'msg-seen-popover';
            document.body.appendChild(pop);
            document.addEventListener('click', function(e) {
                if (!pop.contains(e.target) && !e.target.closest('.msg-seen')) {
                    pop.classList.remove('open');
                }
            });
        }
        if (pop.classList.contains('open')) {
            pop.classList.remove('open');
            return;
        }
        pop.innerHTML = seen.length
            ? seen.map(s => {
                const avatar = s.photo
                    ? '<img src="' + escapeHtml(s.photo) + '" alt="">'
                    : '<span class="msg-seen-initials">' + escapeHtml(s.initials || '?') + '</span>';
                return '<div class="msg-seen-person">' + avatar + '<span>' + escapeHtml(s.name) + '</span></div>';
            }).join('')
            : '<div class="msg-seen-empty">No one has seen this yet</div>';
        const rect = anchor.getBoundingClientRect();
        pop.style.left = Math.max(8, Math.min(rect.right - 200, window.innerWidth - 228)) + 'px';
        pop.style.top = (rect.bottom + 6) + 'px';
        pop.classList.add('open');
    }

    function refreshThreadChrome() {
        const group = document.getElementById('messageGroup');
        const nodes = [...group.querySelectorAll('.msg-row')];
        nodes.forEach((node, i) => {
            const prev = nodes[i - 1];
            const next = nodes[i + 1];
            const samePrev = prev && prev.dataset.direction === node.dataset.direction && prev.dataset.author === node.dataset.author && !shouldStamp(prev.dataset.ts, node.dataset.ts);
            const sameNext = next && next.dataset.direction === node.dataset.direction && next.dataset.author === node.dataset.author && !shouldStamp(node.dataset.ts, next.dataset.ts);
            node.classList.toggle('solo', !samePrev && !sameNext);
            node.classList.toggle('group-start', !samePrev && sameNext);
            node.classList.toggle('group-mid', samePrev && sameNext);
            node.classList.toggle('group-end', samePrev && !sameNext);
            node.classList.toggle('tail', !sameNext);
        });
    }

    function lastRow() {
        const nodes = document.getElementById('messageGroup').querySelectorAll('.msg-row');
        return nodes[nodes.length - 1] || null;
    }

    function appendMessage(m, group) {
        const iso = m.created_at || '';
        const prev = lastRow();
        if (shouldStamp(prev && prev.dataset.ts, iso)) {
            group.appendChild(stampMarkup(iso));
        }
        group.appendChild(buildMessageElement(m));
        refreshThreadChrome();
        applySeenLabels();
    }

    function appendMessagesToGroup(group, messages) {
        const sorted = [...messages].sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
        sorted.forEach(m => appendMessage(m, group));
    }

    function prependMessagesToGroup(group, messages) {
        const sorted = [...messages].sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
        const firstStamp = group.firstElementChild && group.firstElementChild.classList.contains('msg-stamp') ? group.firstElementChild : null;
        const fragment = document.createDocumentFragment();
        let prevIso = null;
        sorted.forEach(m => {
            const iso = m.created_at || '';
            if (shouldStamp(prevIso, iso)) fragment.appendChild(stampMarkup(iso));
            fragment.appendChild(buildMessageElement(m));
            prevIso = iso;
        });
        group.insertBefore(fragment, group.firstChild);
        if (firstStamp && prevIso && !shouldStamp(prevIso, firstStamp.dataset.ts)) {
            firstStamp.remove();
        }
        refreshThreadChrome();
        applySeenLabels();
    }

    function setHeaderAvatar(conversation) {
        const headerAvatar = document.getElementById('chatHeaderAvatar');
        headerAvatar.classList.toggle('group', conversation.type === 'group' && !conversation.avatar_photo);
        if (conversation.avatar_photo) {
            headerAvatar.innerHTML = '<img src="' + conversation.avatar_photo + '" alt="">';
        } else if (conversation.type === 'group') {
            headerAvatar.innerHTML = GROUP_ICON;
        } else {
            headerAvatar.textContent = conversation.avatar_initials || '?';
        }
    }

    async function loadMessages(conversationId) {
        if (typeof window.cancelEditMessage === 'function') window.cancelEditMessage();
        if (typeof window.cancelReplyMessage === 'function') window.cancelReplyMessage();
        closeMessageReactionPicker();
        closeMessageReactionPeople();
        const params = new URLSearchParams({ limit: MESSAGES_PAGE_SIZE });
        const res = await api(baseUrl + '/conversations/' + conversationId + '/messages?' + params.toString());
        const json = await res.json();
        if (!json.success) return;
        const { conversation, messages, has_more, receipts } = json.data;
        conversationReceipts = receipts || [];
        currentConversationType = conversation.type || 'direct';
        currentGroupMembers = conversation.members || [];
        setMentionUiForConversation(currentConversationType);
        messagesHasMore = has_more ?? false;
        messagesOldestId = messages.length > 0 ? messages[0].id : null;
        document.getElementById('chatHeaderName').textContent = conversation.name;
        document.getElementById('chatHeaderStatus').textContent = conversation.type === 'group' ? 'Group' : 'Direct message';
        const deleteBtn = document.getElementById('chatDeleteBtn');
        if (deleteBtn) {
            const canDelete = conversation.type === 'direct' || conversation.is_creator;
            deleteBtn.style.display = canDelete ? '' : 'none';
        }
        setHeaderAvatar(conversation);
        const group = document.getElementById('messageGroup');
        group.innerHTML = '';
        appendMessagesToGroup(group, messages);
        const loadOlderEl = document.getElementById('messagesLoadOlder');
        if (loadOlderEl) loadOlderEl.hidden = !messagesHasMore;
        document.getElementById('messagesArea').scrollTop = document.getElementById('messagesArea').scrollHeight;
        applySeenLabels();
        if (typeof window.updateHeaderMessagingBadge === 'function') window.updateHeaderMessagingBadge();
        if (typeof window.updateSidebarUnreadBadges === 'function') window.updateSidebarUnreadBadges();
    }

    async function loadOlderMessages() {
        if (!currentConversationId || loadOlderInProgress || !messagesHasMore || !messagesOldestId) return;
        loadOlderInProgress = true;
        const loadOlderEl = document.getElementById('messagesLoadOlder');
        if (loadOlderEl) loadOlderEl.hidden = false;
        const params = new URLSearchParams({ limit: MESSAGES_PAGE_SIZE, before_id: messagesOldestId });
        const res = await api(baseUrl + '/conversations/' + currentConversationId + '/messages?' + params.toString());
        const json = await res.json();
        if (!json.success) {
            loadOlderInProgress = false;
            if (loadOlderEl) loadOlderEl.hidden = !messagesHasMore;
            return;
        }
        const { messages, has_more } = json.data;
        messagesHasMore = has_more ?? false;
        if (messages.length > 0) {
            messagesOldestId = messages[0].id;
            const group = document.getElementById('messageGroup');
            const area = document.getElementById('messagesArea');
            const scrollHeightBefore = area.scrollHeight;
            prependMessagesToGroup(group, messages);
            area.scrollTop = area.scrollHeight - scrollHeightBefore;
        }
        if (loadOlderEl) loadOlderEl.hidden = !messagesHasMore;
        loadOlderInProgress = false;
    }
    window.loadOlderMessages = loadOlderMessages;


    // Search
    document.getElementById('conversationSearch').addEventListener('input', debounce(function() {
        loadConversations(this.value);
    }, 300));

    function debounce(fn, ms) {
        let t;
        return function() {
            clearTimeout(t);
            t = setTimeout(() => fn.apply(this, arguments), ms);
        };
    }

    // Send message
    const messageInput = document.getElementById('messageInput');
    const sendBtn = document.getElementById('sendBtn');
    const mentionPopup = document.getElementById('msgMentionPopup');
    const mentionBtn = document.getElementById('msgMentionBtn');
    const defaultComposerPlaceholder = messageInput ? messageInput.getAttribute('placeholder') : 'Message or paste an image';
    let pendingAttachment = null;

    function setMentionUiForConversation(type) {
        if (mentionBtn) mentionBtn.hidden = type !== 'group';
        if (messageInput) {
            messageInput.placeholder = type === 'group'
                ? 'Message, @mention, or paste an image'
                : defaultComposerPlaceholder;
        }
        hideMentionPopup();
    }

    function hideMentionPopup() {
        if (!mentionPopup) return;
        mentionPopup.hidden = true;
        mentionPopup.innerHTML = '';
    }

    function mentionPopupIsOpen() {
        return !!(mentionPopup && !mentionPopup.hidden && mentionPopup.querySelector('[data-mention-id]'));
    }

    function mentionQueryAtCursor() {
        if (currentConversationType !== 'group' || !messageInput) return null;
        const start = messageInput.selectionStart;
        const before = messageInput.value.slice(0, start);
        const match = before.match(/(^|[\s\n])@([a-zA-Z0-9._\- ]*)$/);
        if (!match) return null;
        return {
            query: match[2] || '',
            start: start - (match[2] || '').length - 1,
            end: start
        };
    }

    function filteredGroupMembers(query) {
        const q = String(query || '').trim().toLowerCase();
        return (currentGroupMembers || []).filter(m => {
            if (m.is_me || Number(m.id) === currentUserId) return false;
            if (!q) return true;
            return (m.name || '').toLowerCase().includes(q) || (m.email || '').toLowerCase().includes(q);
        }).slice(0, 8);
    }

    function renderMentionPopup(query) {
        if (!mentionPopup || currentConversationType !== 'group') {
            hideMentionPopup();
            return;
        }
        const members = filteredGroupMembers(query);
        if (!members.length) {
            hideMentionPopup();
            return;
        }
        mentionPopup.innerHTML = members.map((m, idx) => `
            <button type="button" class="msg-mention-item ${idx === 0 ? 'is-active' : ''}" data-mention-id="${m.id}">
                <span class="msg-mention-name">${escapeHtml(m.name)}</span>
                <span class="msg-mention-email">${escapeHtml(m.email || '')}</span>
            </button>
        `).join('');
        mentionPopup.hidden = false;
    }

    function updateMentionPopup() {
        const info = mentionQueryAtCursor();
        if (!info) {
            hideMentionPopup();
            return;
        }
        renderMentionPopup(info.query);
    }

    function applyMention(member) {
        if (!member || !messageInput) return;
        const info = mentionQueryAtCursor();
        const start = info ? info.start : messageInput.selectionStart;
        const end = info ? info.end : messageInput.selectionEnd;
        const value = messageInput.value;
        const insert = '@' + member.name + ' ';
        messageInput.value = value.slice(0, start) + insert + value.slice(end);
        const pos = start + insert.length;
        messageInput.selectionStart = messageInput.selectionEnd = pos;
        hideMentionPopup();
        messageInput.focus();
        messageInput.dispatchEvent(new Event('input'));
    }

    function applyMentionById(id) {
        const member = (currentGroupMembers || []).find(m => String(m.id) === String(id));
        applyMention(member);
    }

    function mentionedIdsInBody(text) {
        if (currentConversationType !== 'group') return [];
        const body = text || '';
        return (currentGroupMembers || []).filter(m => {
            if (m.is_me || Number(m.id) === currentUserId || !m.name) return false;
            const token = '@' + m.name;
            const pattern = token.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            return new RegExp(pattern + '(?![\\w])', 'i').test(body);
        }).map(m => Number(m.id));
    }

    window.insertMentionTrigger = function() {
        if (currentConversationType !== 'group' || !messageInput) return;
        const start = messageInput.selectionStart;
        const before = messageInput.value.slice(0, start);
        const needsSpace = before.length > 0 && !/[\s\n]$/.test(before);
        insertTextAtCursor(messageInput, needsSpace ? ' @' : '@');
        messageInput.focus();
        updateMentionPopup();
    };

    mentionPopup?.addEventListener('click', function(e) {
        const item = e.target.closest('[data-mention-id]');
        if (!item) return;
        e.preventDefault();
        applyMentionById(item.dataset.mentionId);
    });

    document.addEventListener('click', function(e) {
        if (!mentionPopupIsOpen()) return;
        if (e.target.closest('#msgMentionPopup') || e.target.closest('#msgMentionBtn') || e.target === messageInput) return;
        hideMentionPopup();
    });

    function updateSendButtonState() {
        if (editingMessageId) {
            const row = document.querySelector('.msg-row[data-message-id="' + editingMessageId + '"]');
            const hasAttachment = row && row.dataset.hasAttachment === '1';
            sendBtn.disabled = !messageInput.value.trim() && !hasAttachment;
            return;
        }
        const hasText = messageInput.value.trim().length > 0;
        const hasAttachment = pendingAttachment !== null;
        sendBtn.disabled = !hasText && !hasAttachment;
    }

    messageInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 110) + 'px';
        updateSendButtonState();
        updateMentionPopup();
    });
    messageInput.addEventListener('click', updateMentionPopup);
    messageInput.addEventListener('keyup', function(e) {
        if (['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(e.key)) updateMentionPopup();
    });

    function setPendingAttachment(att) {
        if (pendingAttachment?.previewUrl) URL.revokeObjectURL(pendingAttachment.previewUrl);
        pendingAttachment = att;
        const bar = document.getElementById('attachmentPreviewBar');
        if (!att) {
            bar.style.display = 'none';
            bar.innerHTML = '';
        } else {
            bar.style.display = 'flex';
            if (att.type === 'image' && att.previewUrl) {
                bar.innerHTML = `
                    <div class="attachment-preview-chip">
                        <img src="${att.previewUrl}" alt="">
                        <span class="chip-name">${escapeHtml(att.name)}</span>
                        <button type="button" onclick="window.clearPendingAttachment()" aria-label="Remove">✕</button>
                    </div>
                `;
            } else {
                bar.innerHTML = `
                    <div class="attachment-preview-chip">
                        <span class="chip-name">${escapeHtml(att.name)}</span>
                        <button type="button" onclick="window.clearPendingAttachment()" aria-label="Remove">✕</button>
                    </div>
                `;
            }
        }
        updateSendButtonState();
    }

    window.clearPendingAttachment = async function() {
        const path = pendingAttachment?.path;
        if (path) {
            try {
                await fetch(baseUrl + '/attachments/discard', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ path })
                });
            } catch (_) { /* ignore */ }
        }
        setPendingAttachment(null);
    };

    async function uploadFile(file) {
        const fd = new FormData();
        fd.append('file', file);
        fd.append('_token', csrf);
        const res = await fetch(baseUrl + '/attachments', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: fd
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Upload failed');
        return json.data;
    }

    function namedImageFile(file) {
        if (file.name && file.name !== 'blob') return file;
        const rawExt = (file.type.split('/')[1] || 'png').split('+')[0];
        const ext = rawExt === 'jpeg' ? 'jpg' : rawExt;
        return new File([file], 'pasted-image.' + ext, { type: file.type || 'image/png' });
    }

    async function queueChatAttachment(file, asImage) {
        if (!currentConversationId || editingMessageId) return;
        if (asImage && file.type && !file.type.startsWith('image/')) {
            alert('Please choose an image file');
            return;
        }
        const upload = (asImage || (file.type && file.type.startsWith('image/'))) ? namedImageFile(file) : file;
        if (pendingAttachment?.path) {
            await window.clearPendingAttachment();
        }
        try {
            const data = await uploadFile(upload);
            const isImage = data.type === 'image' || asImage;
            const previewUrl = isImage ? URL.createObjectURL(upload) : null;
            setPendingAttachment({
                path: data.path,
                name: data.name,
                type: isImage ? 'image' : data.type,
                previewUrl
            });
            messageInput.focus();
        } catch (err) {
            alert(err.message || 'Upload failed');
        }
    }

    function clipboardImageFile(clipboardData) {
        if (!clipboardData) return null;
        const items = clipboardData.items;
        if (items) {
            for (let i = 0; i < items.length; i++) {
                const item = items[i];
                if (item.kind === 'file' && item.type && item.type.startsWith('image/')) {
                    return item.getAsFile();
                }
            }
        }
        if (clipboardData.files && clipboardData.files.length) {
            const f = clipboardData.files[0];
            if (f && f.type && f.type.startsWith('image/')) return f;
        }
        return null;
    }

    function insertTextAtCursor(textarea, text) {
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const value = textarea.value;
        textarea.value = value.slice(0, start) + text + value.slice(end);
        textarea.selectionStart = textarea.selectionEnd = start + text.length;
        textarea.dispatchEvent(new Event('input'));
    }

    window.startReplyMessage = function(id, ev) {
        if (ev) ev.stopPropagation();
        if (currentConversationType !== 'group') return;
        const row = document.querySelector('.msg-row[data-message-id="' + id + '"]');
        if (!row) return;
        if (editingMessageId) window.cancelEditMessage();
        replyingTo = {
            id: Number(id),
            author: row.dataset.author || '',
            preview: row.dataset.preview || ''
        };
        document.getElementById('msgReplyAuthor').textContent = replyingTo.author;
        document.getElementById('msgReplyPreview').textContent = replyingTo.preview;
        document.getElementById('msgReplyBanner').hidden = false;
        messageInput.focus();
    };

    window.cancelReplyMessage = function() {
        replyingTo = null;
        const banner = document.getElementById('msgReplyBanner');
        if (banner) banner.hidden = true;
    };

    window.scrollToRepliedMessage = function(id, ev) {
        if (ev) ev.stopPropagation();
        const row = document.querySelector('.msg-row[data-message-id="' + id + '"]');
        if (!row) return;
        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
        row.classList.add('msg-row-flash');
        setTimeout(() => row.classList.remove('msg-row-flash'), 1400);
    };

    window.openMessageImagePreview = function(src, ev) {
        if (ev) ev.stopPropagation();
        if (!src) return;
        const box = document.getElementById('msgImageLightbox');
        const img = document.getElementById('msgImageLightboxImg');
        img.src = src;
        box.hidden = false;
    };

    window.closeMessageImagePreview = function() {
        const box = document.getElementById('msgImageLightbox');
        const img = document.getElementById('msgImageLightboxImg');
        box.hidden = true;
        img.src = '';
    };

    document.getElementById('msgImageLightbox').addEventListener('click', function(e) {
        if (e.target === this || e.target.id === 'msgImageLightboxClose') {
            window.closeMessageImagePreview();
        }
    });
    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Escape') return;
        const box = document.getElementById('msgImageLightbox');
        if (box && !box.hidden) {
            e.preventDefault();
            window.closeMessageImagePreview();
            return;
        }
        if (openReactionMessageId) {
            e.preventDefault();
            closeMessageReactionPicker();
            return;
        }
        closeMessageReactionPeople();
    });

    window.startEditMessage = function(id, ev) {
        if (ev) ev.stopPropagation();
        const row = document.querySelector('.msg-row[data-message-id="' + id + '"]');
        if (!row) return;
        if (typeof window.cancelReplyMessage === 'function') window.cancelReplyMessage();
        editingMessageId = id;
        messageInput.value = row.dataset.body || '';
        messageInput.dispatchEvent(new Event('input'));
        document.getElementById('msgEditBanner').hidden = false;
        document.getElementById('messageInputArea').classList.add('editing');
        sendBtn.title = 'Save';
        sendBtn.setAttribute('aria-label', 'Save');
        messageInput.focus();
        updateSendButtonState();
    };

    window.cancelEditMessage = function() {
        editingMessageId = null;
        document.getElementById('msgEditBanner').hidden = true;
        document.getElementById('messageInputArea').classList.remove('editing');
        sendBtn.title = 'Send';
        sendBtn.setAttribute('aria-label', 'Send');
        messageInput.value = '';
        messageInput.style.height = 'auto';
        hideMentionPopup();
        updateSendButtonState();
    };

    async function saveEditedMessage() {
        if (!editingMessageId || !currentConversationId) return;
        const text = messageInput.value.trim();
        const row = document.querySelector('.msg-row[data-message-id="' + editingMessageId + '"]');
        const hasAttachment = row && row.dataset.hasAttachment === '1';
        if (!text && !hasAttachment) return;
        const res = await api(baseUrl + '/conversations/' + currentConversationId + '/messages/' + editingMessageId + '/update', {
            method: 'POST',
            body: {
                body: text || null,
                mentioned_user_ids: currentConversationType === 'group' ? mentionedIdsInBody(text) : []
            }
        });
        const json = await res.json();
        if (!json.success) {
            alert(json.message || 'Failed to edit message');
            return;
        }
        if (row) row.replaceWith(buildMessageElement(json.data));
        refreshThreadChrome();
        applySeenLabels();
        window.cancelEditMessage();
        loadConversations(document.getElementById('conversationSearch').value);
    }

    async function sendMessage() {
        if (editingMessageId) {
            await saveEditedMessage();
            return;
        }
        const text = messageInput.value.trim();
        if ((!text && !pendingAttachment) || !currentConversationId) return;
        const body = { body: text || null };
        if (currentConversationType === 'group') {
            body.mentioned_user_ids = mentionedIdsInBody(text);
        }
        if (pendingAttachment) {
            body.attachment_path = pendingAttachment.path;
            body.attachment_name = pendingAttachment.name;
            body.attachment_type = pendingAttachment.type;
        }
        if (replyingTo && currentConversationType === 'group') {
            body.reply_to_id = replyingTo.id;
        }
        let res, json;
        try {
            res = await api(baseUrl + '/conversations/' + currentConversationId + '/messages', {
                method: 'POST',
                body: body
            });
            json = await res.json();
        } catch (err) {
            alert('Failed to send message: the server did not return a valid response. Please try again.');
            return;
        }
        if (json.success) {
            messageInput.value = '';
            messageInput.style.height = 'auto';
            setPendingAttachment(null);
            window.cancelReplyMessage();
            updateSendButtonState();
            const m = json.data;
            const group = document.getElementById('messageGroup');
            appendMessage(m, group);
            document.getElementById('messagesArea').scrollTop = document.getElementById('messagesArea').scrollHeight;
            loadConversations(document.getElementById('conversationSearch').value);
        } else {
            alert(json.message || 'Failed to send message. Please try again.');
        }
    }

    function handleMessageInput(event) {
        if (mentionPopupIsOpen()) {
            const items = [...mentionPopup.querySelectorAll('[data-mention-id]')];
            const active = mentionPopup.querySelector('.is-active') || items[0];
            let idx = Math.max(0, items.indexOf(active));
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                items[idx]?.classList.remove('is-active');
                idx = (idx + 1) % items.length;
                items[idx]?.classList.add('is-active');
                items[idx]?.scrollIntoView({ block: 'nearest' });
                return;
            }
            if (event.key === 'ArrowUp') {
                event.preventDefault();
                items[idx]?.classList.remove('is-active');
                idx = (idx - 1 + items.length) % items.length;
                items[idx]?.classList.add('is-active');
                items[idx]?.scrollIntoView({ block: 'nearest' });
                return;
            }
            if (event.key === 'Enter' || event.key === 'Tab') {
                event.preventDefault();
                if (items[idx]) applyMentionById(items[idx].dataset.mentionId);
                return;
            }
            if (event.key === 'Escape') {
                event.preventDefault();
                hideMentionPopup();
                return;
            }
        }
        if (event.key === 'Escape' && editingMessageId) {
            event.preventDefault();
            window.cancelEditMessage();
            return;
        }
        if (event.key === 'Escape' && replyingTo) {
            event.preventDefault();
            window.cancelReplyMessage();
            return;
        }
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendMessage();
        }
    }
    messageInput.onkeydown = handleMessageInput;
    sendBtn.onclick = sendMessage;
    window.sendMessage = sendMessage;

    // Create Group Modal
    let groupAvatarPath = null;

    window.openCreateGroupModal = async function() {
        document.getElementById('createGroupModal').classList.add('open');
        document.getElementById('groupName').value = '';
        document.querySelectorAll('.group-member-option').forEach(el => el.classList.remove('selected'));
        updateSelectedMembersDisplay();
        window.clearGroupAvatar();
        await loadCompanyUsers('');
    }

    function closeCreateGroupModal() {
        document.getElementById('createGroupModal').classList.remove('open');
        window.clearGroupAvatar();
    }

    window.pickGroupAvatar = function() {
        document.getElementById('groupAvatarInput').click();
    };

    window.clearGroupAvatar = function() {
        groupAvatarPath = null;
        const placeholder = document.getElementById('groupAvatarPlaceholder');
        const img = document.getElementById('groupAvatarImg');
        const removeBtn = document.getElementById('groupAvatarRemove');
        if (placeholder) {
            placeholder.style.display = 'flex';
        }
        if (img) {
            img.src = '';
            img.style.display = 'none';
        }
        if (removeBtn) removeBtn.style.display = 'none';
    };

    document.getElementById('groupAvatarInput').onchange = async function(e) {
        const file = e.target.files?.[0];
        if (!file || !file.type.startsWith('image/')) return;
        e.target.value = '';
        try {
            const data = await uploadFile(file);
            groupAvatarPath = data.path;
            const img = document.getElementById('groupAvatarImg');
            const placeholder = document.getElementById('groupAvatarPlaceholder');
            const removeBtn = document.getElementById('groupAvatarRemove');
            img.src = data.url || ('/media/' + data.path);
            img.style.display = 'block';
            placeholder.style.display = 'none';
            removeBtn.style.display = 'block';
        } catch (err) {
            alert(err.message || 'Failed to upload photo');
        }
    };
    window.closeCreateGroupModal = closeCreateGroupModal;

    async function loadCompanyUsers(search) {
        const url = baseUrl + '/users' + (search ? '?search=' + encodeURIComponent(search) : '');
        const res = await api(url);
        const json = await res.json();
        if (!json.success) return;
        companyUsers = json.data;
        const list = document.getElementById('groupMembersList');
        list.innerHTML = companyUsers.map(u => `
            <div class="group-member-option" data-id="${u.id}" onclick="window.messagingToggleGroupMember(this)">
                ${u.photo ? '<img src="' + u.photo + '" alt="" class="avatar-photo">' : '<div class="avatar-initials">' + u.initials + '</div>'}
                <span>${escapeHtml(u.name)}</span>
            </div>
        `).join('');
    }

    window.messagingToggleGroupMember = function(el) {
        el.classList.toggle('selected');
        updateSelectedMembersDisplay();
    };

    function updateSelectedMembersDisplay() {
        const selected = document.querySelectorAll('.group-member-option.selected');
        const container = document.getElementById('groupSelectedMembers');
        container.innerHTML = '';
        selected.forEach(el => {
            const chip = document.createElement('span');
            chip.className = 'selected-member-chip';
            chip.innerHTML = el.querySelector('span').textContent;
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.setAttribute('aria-label', 'Remove');
            removeBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
            removeBtn.onclick = () => { el.classList.remove('selected'); updateSelectedMembersDisplay(); };
            chip.appendChild(removeBtn);
            container.appendChild(chip);
        });
    }

    document.getElementById('groupMemberSearch').oninput = debounce(function() {
        filterGroupMembers(this.value);
    }, 200);

    function filterGroupMembers(query) {
        loadCompanyUsers(query);
    }

    document.getElementById('createGroupForm').onsubmit = async function(e) {
        e.preventDefault();
        const name = document.getElementById('groupName').value.trim();
        const selected = [...document.querySelectorAll('.group-member-option.selected')].map(el => el.dataset.id);
        if (!name || selected.length === 0) return;
        const body = { type: 'group', name, participant_ids: selected };
        if (groupAvatarPath) body.photo_path = groupAvatarPath;
        const res = await api(baseUrl + '/conversations', {
            method: 'POST',
            body: body
        });
        const json = await res.json();
        if (json.success) {
            closeCreateGroupModal();
            loadConversations();
            selectConversation(json.data.id);
        } else {
            alert(json.message || 'Failed to create group');
        }
    };

    document.getElementById('createGroupModal').addEventListener('click', function(e) {
        if (e.target === this) closeCreateGroupModal();
    });

    // New Chat Modal
    let newChatModal = null;
    window.openNewChatModal = async function() {
        if (!newChatModal) {
            newChatModal = document.createElement('div');
            newChatModal.className = 'modal-overlay';
            newChatModal.id = 'newChatModal';
            newChatModal.innerHTML = `
                <div class="modal create-group-modal">
                    <div class="modal-header">
                        <h3 class="modal-title">New Chat</h3>
                        <button type="button" class="modal-close" onclick="window.messagingCloseNewChat()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Select a team member</label>
                            <div class="group-members-search">
                                <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                                <input type="text" class="form-input" placeholder="Search..." id="newChatSearch">
                            </div>
                            <div class="group-members-list" id="newChatMembersList"></div>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(newChatModal);
            document.getElementById('newChatSearch').oninput = debounce(function() { loadNewChatUsers(this.value); }, 200);
        }
        newChatModal.classList.add('open');
        loadNewChatUsers('');
    }

    window.messagingCloseNewChat = function() {
        document.getElementById('newChatModal').classList.remove('open');
    };

    async function loadNewChatUsers(search) {
        const url = baseUrl + '/users' + (search ? '?search=' + encodeURIComponent(search) : '');
        const res = await api(url);
        const json = await res.json();
        if (!json.success) return;
        const list = document.getElementById('newChatMembersList');
        list.innerHTML = json.data.map(u => `
            <div class="group-member-option" data-id="${u.id}" data-name="${escapeHtml(u.name)}" onclick="window.messagingStartDirectChat(this)">
                ${u.photo ? '<img src="' + u.photo + '" alt="" class="avatar-photo">' : '<div class="avatar-initials">' + u.initials + '</div>'}
                <span>${escapeHtml(u.name)}</span>
            </div>
        `).join('');
    }

    window.messagingStartDirectChat = async function(el) {
        const userId = el.dataset.id;
        const res = await api(baseUrl + '/conversations', {
            method: 'POST',
            body: { type: 'direct', participant_ids: [parseInt(userId)] }
        });
        const json = await res.json();
        if (json.success) {
            window.messagingCloseNewChat();
            loadConversations();
            selectConversation(json.data.id);
        }
    };

    document.addEventListener('click', function(e) {
        if (e.target.id === 'newChatModal') window.messagingCloseNewChat();
    });

    window.deleteChat = async function() {
        if (!currentConversationId) return;
        if (!confirm('Are you sure you want to delete this chat? All messages will be permanently removed.')) return;
        const res = await api(baseUrl + '/conversations/' + currentConversationId, { method: 'DELETE' });
        const json = await res.json();
        if (json.success) {
            currentConversationId = null;
            currentGroupMembers = [];
            setMentionUiForConversation('direct');
            emptyEl.style.display = 'flex';
            chatEl.style.display = 'none';
            document.querySelectorAll('.msg-thread').forEach(i => i.classList.remove('active'));
            loadConversations(document.getElementById('conversationSearch').value);
        } else {
            alert(json.message || 'Failed to delete chat');
        }
    };

    window.startVideoCall = function() { alert('Video call - Future phase'); };
    window.startAudioCall = function() { alert('Audio call - Future phase'); };

    let chatInfoData = null;

    window.showChatInfo = async function() {
        if (!currentConversationId) return;
        const res = await api(baseUrl + '/conversations/' + currentConversationId);
        const json = await res.json();
        if (!json.success) {
            alert(json.message || 'Failed to load chat info');
            return;
        }
        chatInfoData = json.data;
        if (chatInfoData.type === 'group') {
            currentGroupMembers = chatInfoData.members || [];
        }
        document.getElementById('chatInfoDirect').style.display = 'none';
        document.getElementById('chatInfoGroup').style.display = 'none';
        if (chatInfoData.type === 'direct') {
            document.getElementById('chatInfoTitle').textContent = 'Chat Info';
            document.getElementById('chatInfoDirect').style.display = 'block';
            const u = chatInfoData.user;
            const avatarEl = document.getElementById('chatInfoUserAvatar');
            avatarEl.innerHTML = u?.photo ? '<img src="' + u.photo + '" alt="">' : '<span>' + (u?.initials || '?') + '</span>';
            document.getElementById('chatInfoUserName').textContent = u?.name || 'Unknown';
            document.getElementById('chatInfoUserEmail').textContent = u?.email || '';
            const phoneEl = document.getElementById('chatInfoUserPhone');
            if (u?.phone) {
                phoneEl.textContent = u.phone;
                phoneEl.style.display = 'block';
            } else phoneEl.style.display = 'none';
        } else {
            document.getElementById('chatInfoTitle').textContent = 'Group Info';
            document.getElementById('chatInfoGroup').style.display = 'block';
            const isCreator = chatInfoData.is_creator;
            const avatarEl = document.getElementById('chatInfoGroupAvatar');
            avatarEl.innerHTML = chatInfoData.photo ? '<img src="' + chatInfoData.photo + '" alt="">' : '<span>' + (chatInfoData.name ? chatInfoData.name.slice(0, 2).toUpperCase() : '?') + '</span>';
            document.getElementById('chatInfoGroupName').textContent = chatInfoData.name || 'Group';
            document.getElementById('chatInfoAddMemberBtn').style.display = isCreator ? 'inline-block' : 'none';
            document.getElementById('chatInfoTransferSection').style.display = isCreator ? 'block' : 'none';
            const list = document.getElementById('chatInfoMembersList');
            list.innerHTML = chatInfoData.members.map(m => `
                <div class="chat-info-member" data-user-id="${m.id}">
                    <div class="chat-info-member-avatar">${m.photo ? '<img src="' + m.photo + '" alt="">' : m.initials}</div>
                    <div class="chat-info-member-info">
                        <div class="chat-info-member-name">${escapeHtml(m.name)}${m.is_me ? ' (You)' : ''}${m.is_creator ? ' • Creator' : ''}</div>
                        <div class="chat-info-member-email">${escapeHtml(m.email || '')}</div>
                    </div>
                    ${!m.is_me && isCreator ? '<button type="button" class="chat-info-btn chat-info-btn-outline chat-info-member-remove" onclick="window.removeMemberFromGroup(' + m.id + ')" title="Remove">Remove</button>' : ''}
                </div>
            `).join('');
        }
        document.getElementById('chatInfoModal').classList.add('open');
    };

    window.closeChatInfo = function() {
        document.getElementById('chatInfoModal').classList.remove('open');
        chatInfoData = null;
    };

    window.pickChatInfoGroupPhoto = function() {
        document.getElementById('chatInfoGroupPhotoInput').click();
    };

    document.getElementById('chatInfoGroupPhotoInput').onchange = async function(e) {
        const file = e.target.files?.[0];
        if (!file || !file.type.startsWith('image/')) return;
        e.target.value = '';
        try {
            const data = await uploadFile(file);
            const res = await api(baseUrl + '/conversations/' + currentConversationId + '/update', {
                method: 'POST',
                body: { photo_path: data.path }
            });
            const json = await res.json();
            if (json.success) {
                chatInfoData.photo = data.url || ('/media/' + data.path);
                const avatarEl = document.getElementById('chatInfoGroupAvatar');
                avatarEl.innerHTML = '<img src="' + chatInfoData.photo + '" alt="">';
                loadConversations(document.getElementById('conversationSearch').value);
                const headerAvatar = document.getElementById('chatHeaderAvatar');
                if (headerAvatar) {
                    headerAvatar.classList.remove('group');
                    headerAvatar.innerHTML = '<img src="' + chatInfoData.photo + '" alt="">';
                }
            } else alert(json.message || 'Failed to update photo');
        } catch (err) { alert(err.message || 'Failed'); }
    };

    let addMemberToListUsers = [];

    window.openAddMemberToGroup = async function() {
        document.getElementById('addMemberToGroupModal').classList.add('open');
        const excludeIds = (chatInfoData?.members || []).map(m => m.id);
        const params = new URLSearchParams();
        excludeIds.forEach(id => params.append('exclude[]', id));
        const url = baseUrl + '/users' + (excludeIds.length ? '?' + params.toString() : '');
        const res = await api(url);
        const json = await res.json();
        if (json.success) {
            addMemberToListUsers = json.data;
            renderAddMemberList('');
        }
    };

    window.closeAddMemberToGroup = function() {
        document.getElementById('addMemberToGroupModal').classList.remove('open');
    };

    function filterAddMemberList(query) {
        renderAddMemberList(query);
    }

    function renderAddMemberList(search) {
        const q = (search || '').toLowerCase();
        const filtered = addMemberToListUsers.filter(u =>
            !q || (u.name || '').toLowerCase().includes(q) || (u.email || '').toLowerCase().includes(q)
        );
        const list = document.getElementById('addMemberToList');
        list.innerHTML = filtered.map(u => `
            <div class="group-member-option" data-id="${u.id}" onclick="window.addMemberToGroup(${u.id})">
                ${u.photo ? '<img src="' + u.photo + '" alt="" class="avatar-photo">' : '<div class="avatar-initials">' + u.initials + '</div>'}
                <span>${escapeHtml(u.name)}</span>
            </div>
        `).join('');
    }

    window.addMemberToGroup = async function(userId) {
        if (!currentConversationId) return;
        const res = await api(baseUrl + '/conversations/' + currentConversationId + '/members', {
            method: 'POST',
            body: { user_id: userId }
        });
        const json = await res.json();
        if (json.success) {
            window.closeAddMemberToGroup();
            window.closeChatInfo();
            await window.showChatInfo();
            loadConversations(document.getElementById('conversationSearch').value);
        } else alert(json.message || 'Failed to add member');
    };

    window.removeMemberFromGroup = async function(userId) {
        if (!currentConversationId) return;
        if (!confirm('Remove this member from the group?')) return;
        const res = await api(baseUrl + '/conversations/' + currentConversationId + '/members/' + userId + '/remove', { method: 'POST' });
        const json = await res.json();
        if (json.success) {
            chatInfoData.members = chatInfoData.members.filter(m => m.id !== userId);
            currentGroupMembers = chatInfoData.members;
            const row = document.querySelector('.chat-info-member[data-user-id="' + userId + '"]');
            if (row) row.remove();
            loadConversations(document.getElementById('conversationSearch').value);
        } else alert(json.message || 'Failed to remove member');
    };

    window.openTransferOwnershipModal = function() {
        document.getElementById('transferOwnershipModal').classList.add('open');
        const list = document.getElementById('transferOwnershipList');
        const members = (chatInfoData?.members || []).filter(m => !m.is_me);
        list.innerHTML = members.map(m => `
            <div class="chat-info-member chat-info-member-clickable" data-user-id="${m.id}" onclick="window.transferOwnershipToUser(${m.id})">
                <div class="chat-info-member-avatar">${m.photo ? '<img src="' + m.photo + '" alt="">' : m.initials}</div>
                <div class="chat-info-member-info">
                    <div class="chat-info-member-name">${escapeHtml(m.name)}${m.is_creator ? ' • Creator' : ''}</div>
                    <div class="chat-info-member-email">${escapeHtml(m.email || '')}</div>
                </div>
                <span class="chat-info-transfer-arrow">→</span>
            </div>
        `).join('') || '<p class="chat-info-transfer-hint">No other members to transfer to.</p>';
    };

    window.closeTransferOwnershipModal = function() {
        document.getElementById('transferOwnershipModal').classList.remove('open');
    };

    window.transferOwnershipToUser = async function(userId) {
        if (!currentConversationId) return;
        if (!confirm('Transfer ownership to this member? You will no longer be able to add or remove members.')) return;
        const res = await api(baseUrl + '/conversations/' + currentConversationId + '/transfer-ownership', {
            method: 'POST',
            body: { user_id: userId }
        });
        const json = await res.json();
        if (json.success) {
            window.closeTransferOwnershipModal();
            window.closeChatInfo();
            loadConversations(document.getElementById('conversationSearch').value);
        } else {
            alert(json.message || 'Failed to transfer ownership');
        }
    };

    document.getElementById('chatInfoModal').addEventListener('click', function(e) {
        if (e.target === this) window.closeChatInfo();
    });
    document.getElementById('addMemberToGroupModal').addEventListener('click', function(e) {
        if (e.target === this) window.closeAddMemberToGroup();
    });
    document.getElementById('transferOwnershipModal').addEventListener('click', function(e) {
        if (e.target === this) window.closeTransferOwnershipModal();
    });
    window.attachFile = function() {
        if (!currentConversationId) return;
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = '';
        input.onchange = async function(e) {
            const file = e.target.files?.[0];
            if (!file) return;
            await queueChatAttachment(file, false);
        };
        input.click();
    };

    window.attachImage = function() {
        if (!currentConversationId) return;
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.onchange = async function(e) {
            const file = e.target.files?.[0];
            if (!file) return;
            await queueChatAttachment(file, true);
        };
        input.click();
    };

    document.getElementById('msgChat').addEventListener('paste', function(e) {
        if (editingMessageId) return;
        const imageFile = clipboardImageFile(e.clipboardData);
        if (!imageFile) return;
        e.preventDefault();
        const text = e.clipboardData.getData('text/plain');
        if (text) insertTextAtCursor(messageInput, text);
        queueChatAttachment(imageFile, true);
    });

    const emojiList = ['😀','😃','😄','😁','😅','😂','🤣','😊','😇','🙂','🙃','😉','😌','😍','🥰','😘','😗','😙','😚','😋','😛','😜','🤪','😝','🤑','🤗','🤭','🤫','🤔','🤐','🤨','😐','😑','😶','😏','😒','🙄','😬','🤥','😌','😔','😪','🤤','😴','😷','🤒','🤕','👍','👎','👌','✌️','🤞','🤟','🤘','🤙','👋','🤚','🖐️','✋','🖖','👏','🙌','🤝','🙏','✍️','💪','❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔','❣️','💕','💞','💓','💗','💖','💘','💝','🔥','⭐','🌟','✨','💫','🎉','🎊','🙌','👏','🙏'];
    const emojiPicker = document.getElementById('emojiPickerPopover');
    const emojiGrid = document.getElementById('emojiPickerGrid');
    emojiGrid.innerHTML = emojiList.map(e => '<button type="button" class="emoji-picker-btn" data-emoji="' + e + '">' + e + '</button>').join('');
    emojiGrid.querySelectorAll('button').forEach(btn => {
        btn.onclick = () => {
            const emoji = btn.dataset.emoji;
            const start = messageInput.selectionStart;
            const end = messageInput.selectionEnd;
            const text = messageInput.value;
            messageInput.value = text.slice(0, start) + emoji + text.slice(end);
            messageInput.selectionStart = messageInput.selectionEnd = start + emoji.length;
            messageInput.focus();
        };
    });

    window.showEmojiPicker = function() {
        emojiPicker.classList.toggle('open');
        document.addEventListener('click', function closeEmojiPicker(e) {
            if (!emojiPicker.contains(e.target) && !e.target.closest('[onclick*="showEmojiPicker"]')) {
                emojiPicker.classList.remove('open');
                document.removeEventListener('click', closeEmojiPicker);
            }
        });
    };
    function downloadFile(name) { /* Handled via link */ }

    document.getElementById('msgRefreshBtn')?.addEventListener('click', () => {
        loadConversations(document.getElementById('conversationSearch').value);
    });
    document.getElementById('chatsList').addEventListener('scroll', () => {
        if (loadMoreInProgress || !conversationsHasMore) return;
        const list = document.getElementById('chatsList');
        const remaining = list.scrollHeight - list.scrollTop - list.clientHeight;
        if (remaining < 120) loadMoreConversations();
    });
    document.getElementById('messagesArea').addEventListener('scroll', () => {
        if (document.getElementById('messagesArea').scrollTop < 48) loadOlderMessages();
        closeMessageReactionPicker();
        closeMessageReactionPeople();
    });

    const messageGroupEl = document.getElementById('messageGroup');
    let reactionPressTimer = null;
    let suppressRowClick = false;
    messageGroupEl.addEventListener('touchstart', function(e) {
        const bubble = e.target.closest('.msg-bubble');
        if (!bubble || e.target.closest('a, button, img, .msg-quote')) return;
        const row = bubble.closest('.msg-row');
        if (!row) return;
        reactionPressTimer = setTimeout(function() {
            suppressRowClick = true;
            window.openMessageReactionPicker(row.dataset.messageId);
        }, 450);
    }, { passive: true });
    messageGroupEl.addEventListener('touchend', function() {
        if (reactionPressTimer) clearTimeout(reactionPressTimer);
        reactionPressTimer = null;
    });
    messageGroupEl.addEventListener('touchmove', function() {
        if (reactionPressTimer) clearTimeout(reactionPressTimer);
        reactionPressTimer = null;
    });
    messageGroupEl.addEventListener('click', function(e) {
        if (suppressRowClick) {
            suppressRowClick = false;
            e.preventDefault();
            e.stopPropagation();
            return;
        }
        const row = e.target.closest('.msg-row');
        if (!row || e.target.closest('a, button, img, .msg-quote, .msg-reactions')) return;
        document.querySelectorAll('#messageGroup .msg-row.is-active').forEach(function(other) {
            if (other !== row) other.classList.remove('is-active');
        });
        row.classList.toggle('is-active');
    });

    // Init
    async function refreshOpenConversation() {
        if (!currentConversationId) return;
        const params = new URLSearchParams({ limit: MESSAGES_PAGE_SIZE });
        const res = await api(baseUrl + '/conversations/' + currentConversationId + '/messages?' + params.toString());
        const json = await res.json();
        if (!json.success) return;
        conversationReceipts = json.data.receipts || [];
        const group = document.getElementById('messageGroup');
        const area = document.getElementById('messagesArea');
        const nearBottom = area.scrollHeight - area.scrollTop - area.clientHeight < 80;
        let appended = false;
        (json.data.messages || []).forEach(m => {
            const existing = group.querySelector('.msg-row[data-message-id="' + m.id + '"]');
            if (existing) {
                existing.dataset.seenBy = JSON.stringify(m.seen_by || []);
                const nextReactions = JSON.stringify(m.reactions || []);
                const reactionsChanged = existing.dataset.reactions !== nextReactions;
                const bodyChanged = existing.dataset.body !== (m.body || '') || existing.dataset.editedAt !== (m.edited_at || '');
                if ((bodyChanged || reactionsChanged) && String(openReactionMessageId) !== String(m.id)) {
                    existing.replaceWith(buildMessageElement(m));
                } else if (reactionsChanged) {
                    existing.dataset.reactions = nextReactions;
                    existing.dataset.myReaction = m.my_reaction || '';
                }
            } else {
                appendMessage(m, group);
                appended = true;
            }
        });
        refreshThreadChrome();
        applySeenLabels();
        if (appended && nearBottom) {
            area.scrollTop = area.scrollHeight;
        }
    }

    loadConversations().then(() => {
        const openId = new URLSearchParams(window.location.search).get('conversation');
        if (openId) selectConversation(openId);
    });

    // Poll for new chats, unread, edits, and seen-by updates
    setInterval(function() {
        if (document.visibilityState === 'visible') {
            loadConversations(document.getElementById('conversationSearch').value);
            refreshOpenConversation();
        }
    }, 15000);
})();
