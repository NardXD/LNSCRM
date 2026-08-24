window.initChannelReplyTemplates = function initChannelReplyTemplates(config) {
    const prefix = config.prefix;
    const bodyMax = config.bodyMax || 1600;
    const label = config.label || 'Templates';
    const api = config.api;
    const getComposer = config.getComposer;
    const escapeHtml = config.escapeHtml;

    const el = (id) => document.getElementById(prefix + id);

    const pageSize = config.pageSize || 5;

    const state = {
        templates: [],
        canCreate: false,
        editingId: null,
        pickerOpen: false,
        listOpen: false,
        listPage: 1,
    };

    function filteredTemplates(query) {
        const q = String(query || '').trim().toLowerCase();
        if (!q) return state.templates;
        return state.templates.filter((t) => {
            const hay = [t.name || '', t.body || t.body_text || ''].join(' ').toLowerCase();
            return hay.includes(q);
        });
    }

    function paginatedListItems(query) {
        const items = filteredTemplates(query);
        const total = items.length;
        const totalPages = Math.max(1, Math.ceil(total / pageSize));
        const page = Math.min(Math.max(1, state.listPage), totalPages);
        state.listPage = page;
        const start = (page - 1) * pageSize;
        const end = Math.min(start + pageSize, total);
        return {
            items: items.slice(start, end),
            total,
            totalPages,
            page,
            from: total ? start + 1 : 0,
            to: end,
        };
    }

    function renderPagination(meta) {
        const bar = el('TemplatePagination');
        if (!bar) return;
        if (!meta.total || meta.total <= pageSize) {
            bar.hidden = true;
            return;
        }
        bar.hidden = false;
        const info = el('TemplatePaginationInfo');
        const status = el('TemplatePageStatus');
        const prev = el('TemplatePrevPage');
        const next = el('TemplateNextPage');
        if (info) info.textContent = `Showing ${meta.from}–${meta.to} of ${meta.total}`;
        if (status) status.textContent = `Page ${meta.page} of ${meta.totalPages}`;
        if (prev) prev.disabled = meta.page <= 1;
        if (next) next.disabled = meta.page >= meta.totalPages;
    }

    function updateTemplateCount() {
        const countEl = el('TemplateCount');
        if (!countEl) return;
        countEl.textContent = state.templates.length ? String(state.templates.length) : '';
    }

    function renderTemplateList() {
        const list = el('TemplateList');
        if (!list) return;
        const query = el('TemplateSearch')?.value || '';
        const meta = paginatedListItems(query);
        renderPagination(meta);

        if (!state.templates.length) {
            list.innerHTML = `<div class="ch-tpl-empty">No ${escapeHtml(label.toLowerCase())} yet.${state.canCreate ? ' Click <strong>New template</strong> to add one.' : ''}</div>`;
            return;
        }
        if (!meta.total) {
            list.innerHTML = '<div class="ch-tpl-empty">No matches</div>';
            return;
        }
        list.innerHTML = meta.items.map((t) => {
            const body = String(t.body_text || t.body || '');
            return `
            <div class="ch-tpl-row" data-template-id="${t.id}">
                <div class="ch-tpl-row-main">
                    <div class="ch-tpl-row-name">${escapeHtml(t.name)}</div>
                    <div class="ch-tpl-row-preview">${escapeHtml(body)}</div>
                </div>
                <div class="ch-tpl-row-actions">
                    <button type="button" class="ch-tpl-link-btn" data-insert-template="${t.id}">Use</button>
                    ${state.canCreate ? `<button type="button" class="ch-tpl-link-btn muted" data-edit-template="${t.id}">Edit</button>` : ''}
                </div>
            </div>
        `;
        }).join('');
    }

    function renderPickerList() {
        const list = el('TemplatePickerList');
        if (!list) return;
        const items = filteredTemplates(el('TemplatePickerSearch')?.value || '');
        if (!state.templates.length) {
            list.innerHTML = `<div class="ch-tpl-empty">No ${escapeHtml(label.toLowerCase())}</div>`;
            return;
        }
        if (!items.length) {
            list.innerHTML = '<div class="ch-tpl-empty">No matches</div>';
            return;
        }
        list.innerHTML = items.map((t) => `
            <button type="button" class="ch-tpl-picker-item" data-insert-template="${t.id}" title="${escapeHtml(t.body_text || t.body || '')}">${escapeHtml(t.name)}</button>
        `).join('');
    }

    function insertTemplate(id) {
        const item = state.templates.find((t) => String(t.id) === String(id));
        const composer = getComposer();
        if (!item) return;
        if (!composer) {
            alert('Open a conversation to insert a template.');
            return;
        }
        const text = String(item.body_text || item.body || '');
        if (!composer.value.trim()) {
            composer.value = text;
        } else {
            composer.value = composer.value.replace(/\s+$/, '') + '\n\n' + text;
        }
        composer.dispatchEvent(new Event('input'));
        composer.focus();
        closePicker();
        closeListModal();
    }

    function openListModal() {
        const modal = el('TemplateListModal');
        if (!modal) return;
        modal.hidden = false;
        state.listOpen = true;
        state.listPage = 1;
        if (el('TemplateSearch')) el('TemplateSearch').value = '';
        renderTemplateList();
        setTimeout(() => el('TemplateSearch')?.focus(), 30);
    }

    function closeListModal() {
        const modal = el('TemplateListModal');
        if (modal) modal.hidden = true;
        state.listOpen = false;
    }

    function isListModalOpen() {
        const modal = el('TemplateListModal');
        return modal && !modal.hidden;
    }

    function openTemplateModal(id = null) {
        if (!state.canCreate) {
            alert('You do not have permission to manage templates.');
            return;
        }
        const item = id ? state.templates.find((t) => String(t.id) === String(id)) : null;
        state.editingId = item ? item.id : null;
        el('TemplateModalTitle').textContent = item ? `Edit ${label}` : `New ${label}`;
        el('SaveTemplate').textContent = item ? 'Save' : 'Create';
        el('DeleteTemplate').hidden = !item;
        el('TemplateName').value = item?.name || '';
        el('TemplateBody').value = item?.body_text || item?.body || '';
        el('TemplateModal').hidden = false;
        setTimeout(() => el('TemplateName')?.focus(), 30);
    }

    function closeTemplateModal() {
        const modal = el('TemplateModal');
        if (modal) modal.hidden = true;
        state.editingId = null;
    }

    function isTemplateModalOpen() {
        const modal = el('TemplateModal');
        return modal && !modal.hidden;
    }

    async function saveTemplate() {
        if (!state.canCreate) return;
        const name = el('TemplateName').value.trim();
        const body = el('TemplateBody').value.trim();
        if (!name || !body) {
            alert('Name and message are required.');
            return;
        }
        if (body.length > bodyMax) {
            alert(`Message must be ${bodyMax} characters or less.`);
            return;
        }
        const payload = { name, body, body_text: body };
        const btn = el('SaveTemplate');
        btn.disabled = true;
        try {
            let saved;
            if (state.editingId) {
                const data = await api('/templates/' + state.editingId, { method: 'PUT', body: JSON.stringify(payload) });
                saved = data.template;
                const idx = state.templates.findIndex((t) => String(t.id) === String(state.editingId));
                if (idx >= 0) state.templates[idx] = saved;
                else state.templates.unshift(saved);
            } else {
                const data = await api('/templates', { method: 'POST', body: JSON.stringify(payload) });
                saved = data.template;
                state.templates.unshift(saved);
            }
            state.templates.sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''), undefined, { sensitivity: 'base' }));
            closeTemplateModal();
            updateTemplateCount();
            renderTemplateList();
            renderPickerList();
        } catch (e) {
            alert(e.message || 'Failed to save template');
        } finally {
            btn.disabled = false;
        }
    }

    async function deleteTemplate() {
        if (!state.editingId || !state.canCreate) return;
        const item = state.templates.find((t) => String(t.id) === String(state.editingId));
        if (!item) return;
        if (!confirm(`Delete template "${item.name}"?\n\nThis removes it for everyone in your company.`)) return;
        try {
            await api('/templates/' + state.editingId, { method: 'DELETE' });
            state.templates = state.templates.filter((t) => String(t.id) !== String(state.editingId));
            closeTemplateModal();
            updateTemplateCount();
            renderTemplateList();
            renderPickerList();
        } catch (e) {
            alert(e.message || 'Failed to delete template');
        }
    }

    function openPicker() {
        const menu = el('TemplatePicker');
        if (!menu) return;
        menu.hidden = false;
        state.pickerOpen = true;
        renderPickerList();
        el('TemplatePickerSearch').value = '';
        setTimeout(() => el('TemplatePickerSearch')?.focus(), 0);
    }

    function closePicker() {
        const menu = el('TemplatePicker');
        if (menu) menu.hidden = true;
        state.pickerOpen = false;
    }

    function applyBootstrap(data) {
        state.templates = Array.isArray(data.templates) ? data.templates : [];
        state.canCreate = !!(data.permissions?.create_templates);
        if (el('NewTemplate')) el('NewTemplate').hidden = !state.canCreate;
        updateTemplateCount();
        renderTemplateList();
        renderPickerList();
    }

    el('TemplatesBtn')?.addEventListener('click', openListModal);
    el('NewTemplate')?.addEventListener('click', () => openTemplateModal());
    el('TemplateSearch')?.addEventListener('input', () => {
        state.listPage = 1;
        renderTemplateList();
    });
    el('TemplatePrevPage')?.addEventListener('click', () => {
        if (state.listPage > 1) {
            state.listPage -= 1;
            renderTemplateList();
        }
    });
    el('TemplateNextPage')?.addEventListener('click', () => {
        const meta = paginatedListItems(el('TemplateSearch')?.value || '');
        if (state.listPage < meta.totalPages) {
            state.listPage += 1;
            renderTemplateList();
        }
    });
    el('TemplatePickerSearch')?.addEventListener('input', () => renderPickerList());
    el('TemplateList')?.addEventListener('click', (e) => {
        const edit = e.target.closest('[data-edit-template]');
        if (edit) {
            openTemplateModal(edit.dataset.editTemplate);
            return;
        }
        const insert = e.target.closest('[data-insert-template]');
        if (insert) insertTemplate(insert.dataset.insertTemplate);
    });
    el('TemplatePickerList')?.addEventListener('click', (e) => {
        const insert = e.target.closest('[data-insert-template]');
        if (insert) insertTemplate(insert.dataset.insertTemplate);
    });
    el('TemplateBtn')?.addEventListener('click', (e) => {
        e.stopPropagation();
        if (state.pickerOpen) closePicker();
        else openPicker();
    });
    el('TemplateListClose')?.addEventListener('click', closeListModal);
    el('TemplateListModal')?.addEventListener('click', (e) => {
        if (e.target === el('TemplateListModal')) closeListModal();
    });
    el('TemplateCancel')?.addEventListener('click', closeTemplateModal);
    el('TemplateClose')?.addEventListener('click', closeTemplateModal);
    el('TemplateModal')?.addEventListener('click', (e) => {
        if (e.target === el('TemplateModal')) closeTemplateModal();
    });
    el('SaveTemplate')?.addEventListener('click', () => saveTemplate().catch(console.error));
    el('DeleteTemplate')?.addEventListener('click', () => deleteTemplate().catch(console.error));

    document.addEventListener('click', (e) => {
        if (!state.pickerOpen) return;
        const wrap = el('TemplatePickerWrap');
        if (wrap && !wrap.contains(e.target)) closePicker();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        if (isTemplateModalOpen()) {
            e.preventDefault();
            closeTemplateModal();
            return;
        }
        if (isListModalOpen()) {
            e.preventDefault();
            closeListModal();
            return;
        }
        if (state.pickerOpen) closePicker();
    });

    return { applyBootstrap, insertTemplate, openTemplateModal, openListModal };
};
