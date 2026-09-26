(function () {
    const app = document.getElementById('discussionsApp');
    const API = app.dataset.api;
    const CSRF = app.dataset.csrf;
    const USER_ID = Number(app.dataset.userId);

    const state = {
        view: 'discussions',
        sharedInboxId: null,
        tagId: null,
        items: [],
        current: null,
        messages: [],
        teammates: [],
        inboxes: [],
        tags: [],
        rules: [],
        permissions: { create_tags: false, create_rules: false },
        ruleMeta: { triggers: {}, actions: {} },
        attachment: null,
        counts: {},
    };

    const el = (id) => document.getElementById(id);

    async function api(path, opts = {}) {
        const res = await fetch(API + path, {
            method: opts.method || 'GET',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                ...(opts.body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
            },
            body: opts.body instanceof FormData ? opts.body : (opts.body ? JSON.stringify(opts.body) : undefined),
            credentials: 'same-origin',
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || data.success === false) {
            throw new Error(data.message || Object.values(data.errors || {})[0]?.[0] || 'Request failed');
        }
        return data;
    }

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    function relativeTime(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        const diff = (Date.now() - d.getTime()) / 1000;
        if (diff < 60) return 'just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h';
        if (diff < 86400 * 7) return Math.floor(diff / 86400) + 'd';
        return d.toLocaleDateString();
    }

    function updateCounts(counts = {}) {
        state.counts = counts;
        document.querySelectorAll('[data-count]').forEach((node) => {
            node.textContent = counts[node.dataset.count] ?? 0;
        });
    }

    function closePopMenus(exceptId) {
        ['assignMenu', 'snoozeMenu', 'moreMenu', 'addParticipantMenu'].forEach((id) => {
            if (id === exceptId) return;
            const m = el(id);
            if (m) m.hidden = true;
        });
    }

    function filterAddParticipantList(query) {
        const list = el('addParticipantList');
        if (!list || !state.current) return;
        const q = String(query || '').trim().toLowerCase();
        const participantIds = new Set((state.current.participants || []).map((p) => Number(p.id)));
        const addable = state.teammates.filter((t) => {
            if (participantIds.has(Number(t.id))) return false;
            if (!q) return true;
            return String(t.name || '').toLowerCase().includes(q)
                || String(t.email || '').toLowerCase().includes(q);
        });
        list.innerHTML = addable.length ? addable.map((t) => `
            <button type="button" data-add-user="${t.id}">
                <span class="inbox-assign-name">${escapeHtml(t.name)}</span>
                <span class="inbox-assign-email">${escapeHtml(t.email || '')}</span>
            </button>
        `).join('') : `<div class="inbox-participants-empty">${q ? 'No matching teammates.' : 'Everyone is already in this discussion.'}</div>`;
    }

    function renderNav() {
        el('discInboxList').innerHTML = state.inboxes.map((i) => `
            <button type="button" class="inbox-inbox-row ${state.sharedInboxId === i.id ? 'active' : ''}" data-inbox="${i.id}">
                <span class="inbox-dot" style="background:${escapeHtml(i.color || '#2f6fed')}"></span>
                <span>${escapeHtml(i.name)}</span>
            </button>
        `).join('') || '<div class="inbox-label-empty">No shared inboxes</div>';

        el('discTagList').innerHTML = state.tags.map((t) => `
            <button type="button" class="inbox-inbox-row ${state.tagId === t.id ? 'active' : ''}" data-tag="${t.id}">
                <span class="inbox-dot" style="background:${escapeHtml(t.color || '#64748b')}"></span>
                <span>${escapeHtml(t.name)}</span>
            </button>
        `).join('') || '<div class="inbox-label-empty">No tags</div>';

        el('btnNewTag').hidden = !state.permissions.create_tags;
        el('btnAddRule').hidden = !state.permissions.create_rules;
    }

    function renderList() {
        const titles = {
            discussions: 'Discussions', subscribed: 'Subscribed', open: 'Open',
            assigned: 'Assigned to me', snoozed: 'Later', archived: 'Done'
        };
        el('discListTitle').textContent = titles[state.view] || 'Discussions';
        el('discThreadList').innerHTML = state.items.map((item) => {
            const unread = (item.unread_count || 0) > 0;
            return `<button type="button" class="inbox-conv ${state.current?.id === item.id ? 'active' : ''} ${unread ? 'unread' : ''}" data-id="${item.id}">
                <div class="inbox-conv-top">
                    <span>${escapeHtml(item.assignee?.name || (item.is_subscribed ? 'Subscribed' : 'Discussion'))}</span>
                    <span>${escapeHtml(relativeTime(item.last_message_at))}</span>
                </div>
                <div class="inbox-conv-subject">${escapeHtml(item.subject || '(No subject)')}</div>
                <div class="inbox-conv-snippet">${escapeHtml(item.preview || '')}</div>
                <div class="inbox-conv-tags">
                    ${(item.tags || []).map((t) => `<span class="inbox-pill" style="background:${escapeHtml(t.color || '#f1f5f9')}">${escapeHtml(t.name)}</span>`).join('')}
                    ${item.shared_inbox ? `<span class="inbox-pill">${escapeHtml(item.shared_inbox.name)}</span>` : ''}
                </div>
            </button>`;
        }).join('') || '<div class="inbox-empty">No conversations in this view.</div>';
    }

    function fillSelects() {
        const move = el('discMoveInbox');
        const teammates = el('newTeammates');
        const inbox = el('newInbox');
        const curMove = move.value;
        move.innerHTML = '<option value="">No shared inbox</option>' + state.inboxes.map((i) =>
            `<option value="${i.id}">${escapeHtml(i.name)}</option>`
        ).join('');
        teammates.innerHTML = state.teammates.filter((t) => t.id !== USER_ID).map((t) =>
            `<option value="${t.id}">${escapeHtml(t.name)} (${escapeHtml(t.email || '')})</option>`
        ).join('');
        inbox.innerHTML = '<option value="">Select shared inbox…</option>' + state.inboxes.map((i) =>
            `<option value="${i.id}">${escapeHtml(i.name)}</option>`
        ).join('');
        move.value = curMove;

        el('assignList').innerHTML = `<button type="button" data-assign="">Unassigned</button>` +
            state.teammates.map((t) => `<button type="button" data-assign="${t.id}">
                <span class="inbox-assign-name">${escapeHtml(t.name)}</span>
                <span class="inbox-assign-email">${escapeHtml(t.email || '')}</span>
            </button>`).join('');
    }

    function renderDetail() {
        const empty = el('discEmpty');
        const detail = el('discDetail');
        if (!state.current) {
            empty.style.display = 'flex';
            detail.style.display = 'none';
            el('discShell')?.classList.remove('has-detail');
            return;
        }
        empty.style.display = 'none';
        detail.style.display = 'flex';
        el('discShell')?.classList.add('has-detail');
        const c = state.current;
        el('discSubject').value = c.subject || '';
        el('discAssigneeLabel').textContent = c.assignee?.name || 'Unassigned';
        el('discMoveInbox').value = c.shared_inbox?.id || '';
        el('btnArchiveLabel').textContent = c.status === 'archived' ? 'Reopen' : 'Done';
        const meta = [];
        if (c.shared_inbox) meta.push(c.shared_inbox.name);
        if (c.reopen_at) meta.push('Snoozed until ' + new Date(c.reopen_at).toLocaleString());
        if (c.status === 'archived') meta.push('Done');
        el('discThreadMeta').textContent = meta.join(' · ');
        const participantIds = new Set((c.participants || []).map((p) => Number(p.id)));
        const addable = state.teammates.filter((t) => !participantIds.has(Number(t.id)));
        el('discParticipants').innerHTML = (c.participants || []).map((p) =>
            `<span class="inbox-chip"><span class="inbox-chip-avatar">${escapeHtml(p.initials || '?')}</span><span>${escapeHtml(p.name)}${p.is_me ? ' (you)' : ''}</span></span>`
        ).join('') + `
            <div class="inbox-pop inbox-participants-pop">
                <button type="button" class="inbox-chip-add" id="btnAddParticipant" title="Add participants" aria-haspopup="menu" aria-expanded="false">+</button>
                <div class="inbox-pop-menu inbox-participants-menu" id="addParticipantMenu" hidden>
                    <div class="inbox-participants-head">Add participants</div>
                    <div class="inbox-participants-search">
                        <input type="search" id="addParticipantSearch" placeholder="Search teammates…" autocomplete="off">
                    </div>
                    <div class="inbox-participants-add-list" id="addParticipantList">
                        ${addable.length ? addable.map((t) => `
                            <button type="button" data-add-user="${t.id}">
                                <span class="inbox-assign-name">${escapeHtml(t.name)}</span>
                                <span class="inbox-assign-email">${escapeHtml(t.email || '')}</span>
                            </button>
                        `).join('') : '<div class="inbox-participants-empty">Everyone is already in this discussion.</div>'}
                    </div>
                </div>
            </div>`;
        const selected = new Set((c.tags || []).map((t) => t.id));
        el('discTagPicker').innerHTML = state.tags.map((t) =>
            `<button type="button" class="inbox-tag-toggle ${selected.has(t.id) ? 'on' : ''}" data-tag-toggle="${t.id}">${escapeHtml(t.name)}</button>`
        ).join('');
        el('discMessages').innerHTML = state.messages.map((m) => {
            const u = m.user || {};
            const avatar = u.photo
                ? `<img src="${escapeHtml(u.photo)}" alt="">`
                : escapeHtml(u.initials || '?');
            const attach = m.attachment
                ? `<div style="margin-top:0.4rem;"><a href="${escapeHtml(m.attachment.url)}" target="_blank" rel="noopener">${escapeHtml(m.attachment.name || 'Attachment')}</a></div>`
                : '';
            return `<div class="disc-msg">
                <div class="disc-msg-head">
                    <div class="inbox-avatar">${avatar}</div>
                    <div class="disc-msg-from">${escapeHtml(u.name || 'Someone')}</div>
                    <div class="disc-msg-time">${m.created_at ? new Date(m.created_at).toLocaleString() : ''}</div>
                </div>
                <div class="disc-msg-body">${escapeHtml(m.body || '')}${attach}</div>
            </div>`;
        }).join('') || '<div class="inbox-empty">No comments yet.</div>';
        el('discMessages').scrollTop = el('discMessages').scrollHeight;
    }

    function renderRules() {
        const triggers = state.ruleMeta.triggers || {};
        el('ruleTrigger').innerHTML = Object.entries(triggers).map(([k, v]) =>
            `<option value="${k}">${escapeHtml(v)}</option>`
        ).join('');
        el('rulesList').innerHTML = state.rules.map((r) => `
            <div class="disc-rule-row">
                <div>
                    <strong>${escapeHtml(r.name)}</strong>
                    <div class="inbox-conv-snippet">${escapeHtml((r.triggers || []).join(', '))} → ${(r.actions || []).map((a) => a.type).join(', ')}</div>
                </div>
                <button type="button" class="inbox-btn ghost" data-del-rule="${r.id}" ${state.permissions.create_rules ? '' : 'hidden'}>Delete</button>
            </div>
        `).join('') || '<div class="inbox-label-empty">No rules yet.</div>';
    }

    async function bootstrap() {
        const data = await api('/bootstrap');
        const d = data.data || {};
        state.teammates = d.teammates || [];
        state.inboxes = d.shared_inboxes || [];
        state.tags = d.tags || [];
        state.rules = d.rules || [];
        state.permissions = d.permissions || state.permissions;
        state.ruleMeta = d.rule_meta || state.ruleMeta;
        updateCounts(d.counts || {});
        fillSelects();
        renderNav();
        renderRules();
    }

    async function loadList() {
        const params = new URLSearchParams({ view: state.view, limit: '50' });
        if (state.sharedInboxId) params.set('shared_inbox_id', state.sharedInboxId);
        if (state.tagId) params.set('tag_id', state.tagId);
        const q = el('discSearch').value.trim();
        if (q) params.set('search', q);
        const data = await api('/conversations?' + params.toString());
        state.items = data.data || [];
        updateCounts(data.counts || state.counts);
        renderList();
    }

    async function openDiscussion(id) {
        const data = await api('/conversations/' + id);
        state.current = data.data.conversation;
        state.messages = data.data.messages || [];
        renderList();
        renderDetail();
    }

    function showModal(id) { el(id).style.display = 'flex'; }
    function hideModal(id) { el(id).style.display = 'none'; }

    function openNewModal() {
        showModal('modalNewDiscussion');
        el('newSubject').value = '';
        el('newComment').value = '';
        el('newTeammates').selectedIndex = -1;
        el('newInbox').value = '';
        document.querySelector('input[name="toMode"][value="teammates"]').checked = true;
        toggleToMode();
    }

    function toggleToMode() {
        const mode = document.querySelector('input[name="toMode"]:checked')?.value;
        el('newTeammates').style.display = mode === 'teammates' ? '' : 'none';
        el('newInbox').style.display = mode === 'inbox' ? '' : 'none';
    }

    async function createDiscussion() {
        const mode = document.querySelector('input[name="toMode"]:checked')?.value;
        const body = {
            subject: el('newSubject').value.trim(),
            comment: el('newComment').value.trim(),
        };
        if (mode === 'inbox') {
            body.shared_inbox_id = Number(el('newInbox').value) || null;
        } else {
            body.teammate_ids = Array.from(el('newTeammates').selectedOptions).map((o) => Number(o.value));
        }
        const data = await api('/conversations', { method: 'POST', body });
        hideModal('modalNewDiscussion');
        await loadList();
        await openDiscussion(data.data.id);
    }

    function mentionedIds(text) {
        const ids = [];
        for (const t of state.teammates) {
            if (text.toLowerCase().includes('@' + String(t.name || '').toLowerCase())) ids.push(t.id);
        }
        return ids;
    }

    async function sendComment() {
        if (!state.current) return;
        const body = el('discComment').value.trim();
        const payload = { body, mentioned_user_ids: mentionedIds(body) };
        if (state.attachment) {
            payload.attachment_path = state.attachment.path;
            payload.attachment_name = state.attachment.name;
            payload.attachment_type = state.attachment.type;
        }
        await api('/conversations/' + state.current.id + '/messages', { method: 'POST', body: payload });
        el('discComment').value = '';
        state.attachment = null;
        el('discAttachLabel').textContent = 'Internal — visible to teammates only';
        await openDiscussion(state.current.id);
        await loadList();
    }

    document.querySelectorAll('.inbox-nav-item[data-view]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            document.querySelectorAll('.inbox-nav-item[data-view]').forEach((b) => b.classList.remove('active'));
            btn.classList.add('active');
            state.view = btn.dataset.view;
            state.sharedInboxId = null;
            state.tagId = null;
            renderNav();
            await loadList();
        });
    });

    el('discInboxList').addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-inbox]');
        if (!btn) return;
        state.sharedInboxId = Number(btn.dataset.inbox);
        state.tagId = null;
        renderNav();
        await loadList();
    });

    el('discTagList').addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-tag]');
        if (!btn) return;
        state.tagId = Number(btn.dataset.tag);
        state.sharedInboxId = null;
        renderNav();
        await loadList();
    });

    el('discThreadList').addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-id]');
        if (!btn) return;
        await openDiscussion(Number(btn.dataset.id));
    });

    ['btnNewDiscussion', 'btnNewDiscussionHeader', 'btnNewDiscussionEmpty'].forEach((id) => {
        el(id)?.addEventListener('click', openNewModal);
    });
    el('btnCloseNew').addEventListener('click', () => hideModal('modalNewDiscussion'));
    el('btnCancelNew').addEventListener('click', () => hideModal('modalNewDiscussion'));
    el('btnCreateDiscussion').addEventListener('click', () => createDiscussion().catch((err) => alert(err.message)));
    document.querySelectorAll('input[name="toMode"]').forEach((r) => r.addEventListener('change', toggleToMode));

    let searchTimer;
    el('discSearch').addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => loadList().catch(console.error), 250);
    });

    el('discSubject').addEventListener('change', async () => {
        if (!state.current) return;
        try {
            const data = await api('/conversations/' + state.current.id, {
                method: 'PATCH',
                body: { subject: el('discSubject').value.trim() },
            });
            state.current = data.data;
            await loadList();
        } catch (err) { alert(err.message); }
    });

    el('btnAssignToggle').addEventListener('click', (e) => {
        e.stopPropagation();
        const menu = el('assignMenu');
        const open = menu.hidden;
        closePopMenus(open ? 'assignMenu' : null);
        menu.hidden = !open;
    });
    el('assignList').addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-assign]');
        if (!btn || !state.current) return;
        const val = btn.dataset.assign;
        try {
            const data = await api('/conversations/' + state.current.id + '/assign', {
                method: 'POST',
                body: { assigned_to: val ? Number(val) : null },
            });
            state.current = data.data;
            el('assignMenu').hidden = true;
            await loadList();
            renderDetail();
        } catch (err) { alert(err.message); }
    });

    el('btnSnooze').addEventListener('click', (e) => {
        e.stopPropagation();
        const menu = el('snoozeMenu');
        const open = menu.hidden;
        closePopMenus(open ? 'snoozeMenu' : null);
        if (open) {
            const d = new Date(Date.now() + 24 * 3600 * 1000);
            el('snoozeAt').value = new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
        }
        menu.hidden = !open;
    });
    el('snoozeMenu').addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-snooze-hours]');
        if (!btn || !state.current) return;
        const hours = Number(btn.dataset.snoozeHours);
        try {
            const data = await api('/conversations/' + state.current.id + '/snooze', {
                method: 'POST',
                body: { reopen_at: new Date(Date.now() + hours * 3600 * 1000).toISOString() },
            });
            state.current = data.data;
            el('snoozeMenu').hidden = true;
            await loadList();
            renderDetail();
        } catch (err) { alert(err.message); }
    });
    el('btnConfirmSnooze').addEventListener('click', async () => {
        if (!state.current) return;
        try {
            const data = await api('/conversations/' + state.current.id + '/snooze', {
                method: 'POST',
                body: { reopen_at: new Date(el('snoozeAt').value).toISOString() },
            });
            state.current = data.data;
            el('snoozeMenu').hidden = true;
            await loadList();
            renderDetail();
        } catch (err) { alert(err.message); }
    });

    el('btnArchive').addEventListener('click', async () => {
        if (!state.current) return;
        try {
            const archived = state.current.status !== 'archived';
            const data = await api('/conversations/' + state.current.id + '/archive', {
                method: 'POST',
                body: { archived },
            });
            state.current = data.data;
            await loadList();
            renderDetail();
        } catch (err) { alert(err.message); }
    });

    el('btnThreadMore').addEventListener('click', (e) => {
        e.stopPropagation();
        const menu = el('moreMenu');
        const open = menu.hidden;
        closePopMenus(open ? 'moreMenu' : null);
        menu.hidden = !open;
    });
    el('btnUnsubscribe').addEventListener('click', async () => {
        if (!state.current) return;
        if (!confirm('Unsubscribe from this discussion?')) return;
        try {
            await api('/conversations/' + state.current.id + '/unsubscribe', { method: 'POST' });
            state.current = null;
            state.messages = [];
            el('moreMenu').hidden = true;
            renderDetail();
            await loadList();
        } catch (err) { alert(err.message); }
    });

    el('discParticipants').addEventListener('click', async (e) => {
        if (e.target.closest('#addParticipantMenu') || e.target.closest('#btnAddParticipant')) {
            e.stopPropagation();
        }

        const toggle = e.target.closest('#btnAddParticipant');
        if (toggle) {
            const menu = el('addParticipantMenu');
            if (!menu) return;
            const open = menu.hidden;
            closePopMenus(open ? 'addParticipantMenu' : null);
            menu.hidden = !open;
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) {
                const search = el('addParticipantSearch');
                if (search) {
                    search.value = '';
                    filterAddParticipantList('');
                    setTimeout(() => search.focus(), 0);
                }
            }
            return;
        }

        const addBtn = e.target.closest('[data-add-user]');
        if (!addBtn || !state.current) return;
        const userId = Number(addBtn.dataset.addUser);
        if (!userId) return;
        try {
            const data = await api('/conversations/' + state.current.id + '/participants', {
                method: 'POST',
                body: { user_ids: [userId] },
            });
            state.current = data.data;
            renderDetail();
            await loadList();
            const menu = el('addParticipantMenu');
            const btn = el('btnAddParticipant');
            if (menu && btn) {
                menu.hidden = false;
                btn.setAttribute('aria-expanded', 'true');
                filterAddParticipantList(el('addParticipantSearch')?.value || '');
            }
        } catch (err) { alert(err.message); }
    });

    el('discParticipants').addEventListener('input', (e) => {
        if (e.target?.id !== 'addParticipantSearch') return;
        filterAddParticipantList(e.target.value);
    });
    el('discMoveInbox').addEventListener('change', async () => {
        if (!state.current) return;
        try {
            const val = el('discMoveInbox').value;
            const data = await api('/conversations/' + state.current.id + '/move', {
                method: 'POST',
                body: { shared_inbox_id: val ? Number(val) : null },
            });
            state.current = data.data;
            el('moreMenu').hidden = true;
            await loadList();
            renderDetail();
        } catch (err) { alert(err.message); }
    });

    document.addEventListener('click', () => closePopMenus());

    el('discTagPicker').addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-tag-toggle]');
        if (!btn || !state.current) return;
        const id = Number(btn.dataset.tagToggle);
        const selected = new Set((state.current.tags || []).map((t) => t.id));
        if (selected.has(id)) selected.delete(id); else selected.add(id);
        try {
            const data = await api('/conversations/' + state.current.id + '/tags', {
                method: 'POST',
                body: { tag_ids: Array.from(selected) },
            });
            state.current = data.data;
            renderDetail();
            await loadList();
        } catch (err) { alert(err.message); }
    });

    el('btnAttach').addEventListener('click', () => el('discFile').click());
    el('discFile').addEventListener('change', async () => {
        const file = el('discFile').files?.[0];
        if (!file) return;
        const fd = new FormData();
        fd.append('file', file);
        try {
            const data = await api('/attachments', { method: 'POST', body: fd });
            state.attachment = data.data;
            el('discAttachLabel').textContent = data.data.name;
        } catch (err) { alert(err.message); }
        el('discFile').value = '';
    });
    el('btnSendComment').addEventListener('click', () => sendComment().catch((err) => alert(err.message)));

    el('btnNewTag').addEventListener('click', async () => {
        const name = prompt('Tag name');
        if (!name) return;
        try {
            await api('/tags', { method: 'POST', body: { name, color: '#2f6fed' } });
            await bootstrap();
            renderDetail();
        } catch (err) { alert(err.message); }
    });

    el('btnOpenRules').addEventListener('click', () => {
        renderRules();
        showModal('modalRules');
        el('ruleForm').style.display = 'none';
    });
    el('btnCloseRules').addEventListener('click', () => hideModal('modalRules'));
    el('btnAddRule').addEventListener('click', () => {
        el('ruleForm').style.display = 'grid';
        el('ruleName').value = '';
        el('ruleActionValue').value = '';
    });
    el('btnCancelRule').addEventListener('click', () => el('ruleForm').style.display = 'none');
    el('btnSaveRule').addEventListener('click', async () => {
        try {
            await api('/rules', {
                method: 'POST',
                body: {
                    name: el('ruleName').value.trim(),
                    triggers: [el('ruleTrigger').value],
                    actions: [{ type: el('ruleActionType').value, value: el('ruleActionValue').value.trim() }],
                },
            });
            await bootstrap();
            renderRules();
            el('ruleForm').style.display = 'none';
        } catch (err) { alert(err.message); }
    });
    el('rulesList').addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-del-rule]');
        if (!btn) return;
        try {
            await api('/rules/' + btn.dataset.delRule, { method: 'DELETE' });
            await bootstrap();
            renderRules();
        } catch (err) { alert(err.message); }
    });

    (async function init() {
        try {
            await bootstrap();
            await loadList();
            const params = new URLSearchParams(location.search);
            const cid = Number(params.get('conversation') || 0);
            if (cid) await openDiscussion(cid);
        } catch (err) {
            console.error(err);
            el('discThreadList').innerHTML = `<div class="inbox-empty">${escapeHtml(err.message)}</div>`;
        }
    })();
})();
