/* Vite page entry — Front-style knowledge base: category tree, article list, reader/editor */
(function () {
    const CFG = window.__knowledgeBaseConfig || {};
    const canCreate = CFG.canCreate !== false;
    const canEdit = CFG.canEdit !== false;
    const canDelete = CFG.canDelete !== false;
    const baseUrl = CFG.baseUrl || '/api/knowledge-base';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const COLLAPSED_KEY = 'kb.collapsedCategories';
    const SEPARATOR = ' › ';

    const el = {
        tree: document.getElementById('kbTree'),
        listTitle: document.getElementById('kbListTitle'),
        list: document.getElementById('kbArticleList'),
        empty: document.getElementById('kbEmpty'),
        reader: document.getElementById('kbReader'),
        readerStatus: document.getElementById('kbReaderStatus'),
        readerBreadcrumb: document.getElementById('kbReaderBreadcrumb'),
        readerTitle: document.getElementById('kbReaderTitle'),
        readerMeta: document.getElementById('kbReaderMeta'),
        readerBody: document.getElementById('kbReaderBody'),
        editor: document.getElementById('kbEditor'),
        editorHeading: document.getElementById('kbEditorHeading'),
        editorTitle: document.getElementById('kbEditorTitle'),
        editorCategory: document.getElementById('kbEditorCategory'),
        editorContent: document.getElementById('kbEditorContent'),
        categoryModal: document.getElementById('kbCategoryModal'),
        categoryModalTitle: document.getElementById('kbCategoryModalTitle'),
        categoryForm: document.getElementById('kbCategoryForm'),
        categoryName: document.getElementById('kbCategoryName'),
        categoryParent: document.getElementById('kbCategoryParent'),
        categorySubmit: document.getElementById('kbCategorySubmit'),
    };

    const state = {
        categories: [],
        articles: [],
        selection: { type: 'all', id: null },
        status: 'all',
        articleId: null,
        mode: 'empty',
        editingId: null,
        dirty: false,
        saving: false,
        categoryModal: { mode: 'create', id: null },
        collapsed: new Set(readCollapsed()),
    };

    // ---------- helpers ----------

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function textFromHtml(html) {
        const div = document.createElement('div');
        div.innerHTML = html || '';
        return (div.textContent || '').replace(/\s+/g, ' ').trim();
    }

    function readCollapsed() {
        try {
            const ids = JSON.parse(localStorage.getItem(COLLAPSED_KEY) || '[]');
            return Array.isArray(ids) ? ids.map(Number) : [];
        } catch {
            return [];
        }
    }

    function saveCollapsed() {
        try {
            localStorage.setItem(COLLAPSED_KEY, JSON.stringify([...state.collapsed]));
        } catch {
            /* storage unavailable */
        }
    }

    function normalizeStatus(status) {
        if (status === 'internal' || status === 'public') return 'published';
        return ['draft', 'published', 'archived'].includes(status) ? status : 'draft';
    }

    function statusLabel(status) {
        return { draft: 'Draft', published: 'Published', archived: 'Archived' }[normalizeStatus(status)];
    }

    async function api(method, path, body) {
        const res = await fetch(baseUrl + path, {
            method,
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                ...(body ? { 'Content-Type': 'application/json' } : {}),
            },
            body: body ? JSON.stringify(body) : undefined,
        });
        const json = res.status === 204 ? {} : await res.json().catch(() => ({}));
        if (!res.ok || json.success === false) {
            const msg = json.errors ? Object.values(json.errors).flat().join(' ') : (json.message || 'Request failed.');
            throw new Error(msg);
        }
        return json;
    }

    // ---------- category tree ----------

    function categoryById(id) {
        return state.categories.find(c => c.id === Number(id)) || null;
    }

    function childrenOf(parentId) {
        return state.categories
            .filter(c => (c.parent_id ?? null) === (parentId ?? null))
            .sort((a, b) => (a.sort_order - b.sort_order) || a.name.localeCompare(b.name));
    }

    function subtreeIds(id) {
        const ids = [];
        const queue = [Number(id)];
        while (queue.length) {
            const current = queue.shift();
            if (ids.includes(current)) continue;
            ids.push(current);
            childrenOf(current).forEach(child => queue.push(child.id));
        }
        return ids;
    }

    function ancestorIds(id) {
        const ids = [];
        let current = categoryById(id);
        while (current && current.parent_id && !ids.includes(current.parent_id)) {
            ids.push(current.parent_id);
            current = categoryById(current.parent_id);
        }
        return ids;
    }

    function articleCountFor(categoryId) {
        const ids = subtreeIds(categoryId);
        return state.articles.filter(a => a.category_id && ids.includes(a.category_id)).length;
    }

    function renderTree() {
        const build = (parentId, depth) => childrenOf(parentId).map((category, index, siblings) => {
            const children = childrenOf(category.id);
            const hasChildren = children.length > 0;
            const collapsed = state.collapsed.has(category.id);
            const active = state.selection.type === 'category' && state.selection.id === category.id;
            const actions = [
                canCreate ? `<button type="button" class="kb-row-btn" data-action="add-subcategory" data-id="${category.id}" title="Add subcategory" aria-label="Add subcategory">+</button>` : '',
                canEdit ? `<button type="button" class="kb-row-btn" data-action="edit-category" data-id="${category.id}" title="Rename or move" aria-label="Rename or move">✎</button>` : '',
                canEdit && index > 0 ? `<button type="button" class="kb-row-btn" data-action="move-category" data-direction="up" data-id="${category.id}" title="Move up" aria-label="Move up">↑</button>` : '',
                canEdit && index < siblings.length - 1 ? `<button type="button" class="kb-row-btn" data-action="move-category" data-direction="down" data-id="${category.id}" title="Move down" aria-label="Move down">↓</button>` : '',
                canDelete ? `<button type="button" class="kb-row-btn kb-danger" data-action="delete-category" data-id="${category.id}" title="Delete category" aria-label="Delete category">×</button>` : '',
            ].join('');

            return `
                <div class="kb-tree-node" role="treeitem" aria-selected="${active}"${hasChildren ? ` aria-expanded="${!collapsed}"` : ''}>
                    <div class="kb-tree-row${active ? ' active' : ''}" data-select="category" data-id="${category.id}" style="padding-left:${6 + depth * 14}px">
                        ${hasChildren
                            ? `<button type="button" class="kb-caret${collapsed ? '' : ' open'}" data-action="toggle-category" data-id="${category.id}" aria-label="${collapsed ? 'Expand' : 'Collapse'}">▸</button>`
                            : '<span class="kb-caret-spacer"></span>'}
                        <span class="kb-nav-label" title="${escapeHtml(category.name)}">${escapeHtml(category.name)}</span>
                        <span class="kb-count">${articleCountFor(category.id) || ''}</span>
                        ${actions ? `<span class="kb-row-actions">${actions}</span>` : ''}
                    </div>
                    ${hasChildren && !collapsed ? `<div class="kb-tree-children" role="group">${build(category.id, depth + 1)}</div>` : ''}
                </div>`;
        }).join('');

        el.tree.innerHTML = state.categories.length
            ? build(null, 0)
            : `<p class="kb-tree-empty">No categories yet.${canCreate ? ' Use + to add one.' : ''}</p>`;

        document.querySelectorAll('.kb-nav-item').forEach(item => {
            item.classList.toggle('active', state.selection.type === item.dataset.select);
        });
        document.querySelector('[data-count="all"]').textContent = state.articles.length || '';
        document.querySelector('[data-count="uncategorized"]').textContent = state.articles.filter(a => !a.category_id).length || '';
    }

    function categoryOptions(select, { includeNone, noneLabel, exclude = [], selected = null }) {
        const rows = [];
        const walk = (parentId, depth) => childrenOf(parentId).forEach(category => {
            if (exclude.includes(category.id)) return;
            rows.push(`<option value="${category.id}"${category.id === selected ? ' selected' : ''}>${'\u00a0\u00a0\u00a0'.repeat(depth)}${escapeHtml(category.name)}</option>`);
            walk(category.id, depth + 1);
        });
        walk(null, 0);
        select.innerHTML = (includeNone ? `<option value=""${selected === null ? ' selected' : ''}>${escapeHtml(noneLabel)}</option>` : '') + rows.join('');
    }

    // ---------- article list ----------

    function visibleArticles() {
        let items = state.articles;
        if (state.selection.type === 'uncategorized') {
            items = items.filter(a => !a.category_id);
        } else if (state.selection.type === 'category') {
            const ids = subtreeIds(state.selection.id);
            items = items.filter(a => a.category_id && ids.includes(a.category_id));
        }
        if (state.status !== 'all') {
            items = items.filter(a => normalizeStatus(a.visibility) === state.status);
        }
        return items;
    }

    function renderList() {
        const selectedCategory = state.selection.type === 'category' ? categoryById(state.selection.id) : null;
        el.listTitle.textContent = selectedCategory
            ? selectedCategory.name
            : (state.selection.type === 'uncategorized' ? 'Uncategorized' : 'All articles');

        document.querySelectorAll('.kb-status-tab').forEach(tab => {
            tab.classList.toggle('active', tab.dataset.status === state.status);
        });

        const items = visibleArticles();
        el.list.removeAttribute('aria-busy');

        if (!items.length) {
            el.list.innerHTML = `<p class="kb-list-empty">No articles here yet.</p>`;
            return;
        }

        el.list.innerHTML = items.map(article => {
            const status = normalizeStatus(article.visibility);
            const showPath = article.category && (!selectedCategory || article.category_id !== selectedCategory.id);
            return `
                <button type="button" class="kb-article-item${article.id === state.articleId ? ' active' : ''}" data-article="${article.id}">
                    <span class="kb-article-item-title">${escapeHtml(article.title)}</span>
                    <span class="kb-article-item-excerpt">${escapeHtml(textFromHtml(article.excerpt))}</span>
                    <span class="kb-article-item-meta">
                        <span class="article-badge ${status}">${statusLabel(status)}</span>
                        ${showPath ? `<span class="kb-article-item-path">${escapeHtml(article.category)}</span>` : ''}
                        <span class="kb-article-item-date">${escapeHtml(article.updated || article.date || '')}</span>
                    </span>
                </button>`;
        }).join('');
    }

    // ---------- detail pane ----------

    function setMode(mode) {
        state.mode = mode;
        el.empty.hidden = mode !== 'empty';
        el.reader.hidden = mode !== 'read';
        el.editor.hidden = mode !== 'edit';
    }

    function showEmpty() {
        state.articleId = null;
        state.dirty = false;
        setMode('empty');
        renderList();
    }

    function showArticle(id) {
        const article = state.articles.find(a => a.id === Number(id));
        if (!article) {
            showEmpty();
            return;
        }

        state.articleId = article.id;
        state.dirty = false;
        const status = normalizeStatus(article.visibility);

        el.readerStatus.className = `article-badge ${status}`;
        el.readerStatus.textContent = statusLabel(status);
        el.readerBreadcrumb.innerHTML = article.category_id && categoryById(article.category_id)
            ? [...ancestorIds(article.category_id).reverse(), article.category_id]
                .map(cid => `<button type="button" class="kb-crumb" data-select="category" data-id="${cid}">${escapeHtml(categoryById(cid)?.name)}</button>`)
                .join(`<span class="kb-crumb-sep">${SEPARATOR.trim()}</span>`)
            : '<span class="kb-crumb-muted">Uncategorized</span>';
        el.readerTitle.textContent = article.title;
        el.readerMeta.textContent = `By ${article.author || 'Unknown'} · Updated ${article.updated || article.date || ''}`;
        el.readerBody.innerHTML = (article.content && article.content.trim())
            ? article.content
            : '<p class="kb-crumb-muted">This article has no content yet.</p>';

        setMode('read');
        renderList();
        setHash(`article-${article.id}`);
    }

    function showEditor(article) {
        state.editingId = article ? article.id : null;
        state.articleId = article ? article.id : null;
        state.dirty = false;

        el.editorHeading.textContent = article ? 'Edit article' : 'New article';
        el.editorTitle.value = article ? article.title : '';
        el.editorContent.innerHTML = article ? (article.content || '') : '';

        const defaultCategory = article
            ? (article.category_id || null)
            : (state.selection.type === 'category' ? state.selection.id : null);
        categoryOptions(el.editorCategory, { includeNone: true, noneLabel: 'Uncategorized', selected: defaultCategory });

        setMode('edit');
        renderList();
        el.editorTitle.focus();
    }

    function confirmDiscard() {
        return state.mode !== 'edit' || !state.dirty || confirm('Discard your unsaved changes?');
    }

    async function saveArticle(visibility) {
        if (state.saving) return;
        const title = el.editorTitle.value.trim();
        if (!title) {
            alert('Please enter a title.');
            el.editorTitle.focus();
            return;
        }

        const hasContent = textFromHtml(el.editorContent.innerHTML) !== '' || el.editorContent.querySelector('img') !== null;
        const content = hasContent ? el.editorContent.innerHTML : '';
        const payload = {
            title,
            content,
            category_id: el.editorCategory.value ? Number(el.editorCategory.value) : null,
            visibility,
        };

        state.saving = true;
        el.editor.querySelectorAll('[data-save]').forEach(btn => { btn.disabled = true; });
        try {
            const { article } = state.editingId
                ? await api('PUT', `/articles/${state.editingId}`, payload)
                : await api('POST', '/articles', payload);
            state.articles = [article, ...state.articles.filter(a => a.id !== article.id)];
            state.dirty = false;
            renderTree();
            showArticle(article.id);
        } catch (err) {
            alert(err.message || 'Failed to save article.');
        } finally {
            state.saving = false;
            el.editor.querySelectorAll('[data-save]').forEach(btn => { btn.disabled = false; });
        }
    }

    async function deleteArticle() {
        const article = state.articles.find(a => a.id === state.articleId);
        if (!article || !confirm(`Delete "${article.title}"? This cannot be undone.`)) return;
        try {
            await api('DELETE', `/articles/${article.id}`);
            state.articles = state.articles.filter(a => a.id !== article.id);
            renderTree();
            showEmpty();
            setHash(selectionHash());
        } catch (err) {
            alert(err.message || 'Failed to delete article.');
        }
    }

    // ---------- selection & hash ----------

    function selectionHash() {
        if (state.selection.type === 'category') return `category-${state.selection.id}`;
        if (state.selection.type === 'uncategorized') return 'uncategorized';
        return '';
    }

    function setHash(hash) {
        const target = hash ? `#${hash}` : '';
        if (location.hash !== target) {
            history.replaceState(null, '', location.pathname + location.search + target);
        }
    }

    function select(type, id = null) {
        if (!confirmDiscard()) return;
        state.selection = { type, id: id !== null ? Number(id) : null };
        if (type === 'category') {
            ancestorIds(id).forEach(aid => state.collapsed.delete(aid));
            saveCollapsed();
        }
        renderTree();
        const current = state.articles.find(a => a.id === state.articleId);
        if (state.mode === 'edit' || !current || !visibleArticles().includes(current)) {
            showEmpty();
        } else {
            renderList();
        }
        setHash(state.mode === 'read' && state.articleId ? `article-${state.articleId}` : selectionHash());
    }

    function applyHash() {
        const hash = location.hash.replace(/^#/, '');
        const articleMatch = hash.match(/^article-(\d+)$/);
        const categoryMatch = hash.match(/^category-(\d+)$/);
        if (articleMatch && state.articles.some(a => a.id === Number(articleMatch[1]))) {
            showArticle(articleMatch[1]);
        } else if (categoryMatch && categoryById(categoryMatch[1])) {
            select('category', categoryMatch[1]);
        } else if (hash === 'uncategorized') {
            select('uncategorized');
        }
    }

    // ---------- category modal & actions ----------

    function openCategoryModal(mode, id = null) {
        state.categoryModal = { mode, id: id !== null ? Number(id) : null };
        const category = mode === 'edit' ? categoryById(id) : null;

        el.categoryModalTitle.textContent = mode === 'edit' ? 'Edit category' : 'New category';
        el.categorySubmit.textContent = mode === 'edit' ? 'Save' : 'Create';
        el.categoryName.value = category ? category.name : '';

        const parentId = mode === 'edit'
            ? (category?.parent_id ?? null)
            : (mode === 'create-child' ? Number(id) : null);
        categoryOptions(el.categoryParent, {
            includeNone: true,
            noneLabel: 'None (top level)',
            exclude: mode === 'edit' ? subtreeIds(id) : [],
            selected: parentId,
        });

        el.categoryModal.classList.add('active');
        el.categoryName.focus();
    }

    function closeCategoryModal() {
        el.categoryModal.classList.remove('active');
    }

    async function submitCategory(event) {
        event.preventDefault();
        const name = el.categoryName.value.trim();
        if (!name) {
            el.categoryName.focus();
            return;
        }
        const parentId = el.categoryParent.value ? Number(el.categoryParent.value) : null;

        el.categorySubmit.disabled = true;
        try {
            if (state.categoryModal.mode === 'edit') {
                const { categories } = await api('PUT', `/categories/${state.categoryModal.id}`, { name, parent_id: parentId });
                state.categories = categories;
                const paths = new Map(categories.map(c => [c.id, c.path]));
                state.articles = state.articles.map(a => ({ ...a, category: a.category_id ? (paths.get(a.category_id) ?? null) : null }));
            } else {
                const { categories, category } = await api('POST', '/categories', { type: 'article', name, parent_id: parentId });
                state.categories = categories;
                if (parentId) state.collapsed.delete(parentId);
                saveCollapsed();
                closeCategoryModal();
                select('category', category.id);
                return;
            }
            closeCategoryModal();
            renderTree();
            renderList();
            if (state.mode === 'read') showArticle(state.articleId);
        } catch (err) {
            alert(err.message || 'Failed to save category.');
        } finally {
            el.categorySubmit.disabled = false;
        }
    }

    async function moveCategory(id, direction) {
        try {
            const { categories } = await api('POST', `/categories/${id}/move`, { direction });
            state.categories = categories;
            renderTree();
        } catch (err) {
            alert(err.message || 'Failed to move category.');
        }
    }

    async function deleteCategory(id) {
        const category = categoryById(id);
        if (!category) return;
        const parent = category.parent_id ? categoryById(category.parent_id) : null;
        const direct = state.articles.filter(a => a.category_id === category.id).length;
        const subs = childrenOf(category.id).length;
        const lines = [`Delete the category "${category.name}"?`];
        if (direct) {
            lines.push(`${direct} article${direct === 1 ? '' : 's'} will move to ${parent ? `"${parent.name}"` : 'Uncategorized'}.`);
        }
        if (subs) {
            lines.push(`${subs} subcategor${subs === 1 ? 'y' : 'ies'} will move ${parent ? `under "${parent.name}"` : 'to the top level'}.`);
        }
        if (direct || subs) {
            lines.push('No articles are deleted.');
        }
        if (!confirm(lines.join('\n\n'))) return;

        try {
            const { categories, articles } = await api('DELETE', `/categories/${category.id}`);
            state.categories = categories;
            state.articles = articles;
            state.collapsed.delete(category.id);
            saveCollapsed();
            const selectedGone = state.selection.type === 'category' && !categoryById(state.selection.id);
            if (selectedGone) {
                state.selection = parent ? { type: 'category', id: parent.id } : { type: 'all', id: null };
            }
            renderTree();
            if (state.mode === 'read' && state.articleId) {
                showArticle(state.articleId);
            } else {
                renderList();
            }
            if (selectedGone) setHash(selectionHash());
        } catch (err) {
            alert(err.message || 'Failed to delete category.');
        }
    }

    // ---------- rich editor ----------

    el.editor.querySelector('.rich-editor-toolbar').addEventListener('click', (event) => {
        const btn = event.target.closest('.rich-editor-btn');
        if (!btn) return;
        event.preventDefault();
        el.editorContent.focus();
        const { cmd, value } = btn.dataset;
        if (cmd === 'createLink' || cmd === 'insertImage') {
            const url = prompt(cmd === 'createLink' ? 'Link URL:' : 'Image URL:', 'https://');
            if (url && url !== 'https://') document.execCommand(cmd, false, url);
        } else if (cmd === 'formatBlock') {
            document.execCommand('formatBlock', false, value);
        } else {
            document.execCommand(cmd, false, null);
        }
        state.dirty = true;
    });

    el.editor.querySelector('.rich-editor-select').addEventListener('change', (event) => {
        el.editorContent.focus();
        document.execCommand('formatBlock', false, event.target.value);
        state.dirty = true;
    });

    [el.editorTitle, el.editorContent].forEach(input => input.addEventListener('input', () => { state.dirty = true; }));
    el.editorCategory.addEventListener('change', () => { state.dirty = true; });

    // ---------- events ----------

    el.tree.closest('.ld-page').addEventListener('click', (event) => {
        const action = event.target.closest('[data-action]');
        if (action) {
            const { id, direction } = action.dataset;
            switch (action.dataset.action) {
                case 'new-category': openCategoryModal('create'); return;
                case 'add-subcategory': openCategoryModal('create-child', id); return;
                case 'edit-category': openCategoryModal('edit', id); return;
                case 'move-category': moveCategory(id, direction); return;
                case 'delete-category': deleteCategory(id); return;
                case 'close-category-modal': closeCategoryModal(); return;
                case 'toggle-category': {
                    const cid = Number(id);
                    state.collapsed.has(cid) ? state.collapsed.delete(cid) : state.collapsed.add(cid);
                    saveCollapsed();
                    renderTree();
                    return;
                }
                case 'new-article':
                    if (confirmDiscard()) showEditor(null);
                    return;
                case 'edit-article': {
                    const article = state.articles.find(a => a.id === state.articleId);
                    if (article) showEditor(article);
                    return;
                }
                case 'delete-article': deleteArticle(); return;
                case 'cancel-edit':
                    if (!confirmDiscard()) return;
                    state.dirty = false;
                    state.editingId ? showArticle(state.editingId) : showEmpty();
                    return;
                default: break;
            }
        }

        const save = event.target.closest('[data-save]');
        if (save) {
            saveArticle(save.dataset.save);
            return;
        }

        const selectable = event.target.closest('[data-select]');
        if (selectable) {
            select(selectable.dataset.select, selectable.dataset.id ?? null);
            return;
        }

        const articleItem = event.target.closest('[data-article]');
        if (articleItem) {
            const alreadyOpen = Number(articleItem.dataset.article) === state.articleId && state.mode === 'read';
            if (!alreadyOpen && confirmDiscard()) showArticle(articleItem.dataset.article);
            return;
        }

        const statusTab = event.target.closest('[data-status]');
        if (statusTab) {
            state.status = statusTab.dataset.status;
            renderList();
        }
    });

    el.categoryForm.addEventListener('submit', submitCategory);
    el.categoryModal.addEventListener('click', (event) => {
        if (event.target === el.categoryModal) closeCategoryModal();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && el.categoryModal.classList.contains('active')) closeCategoryModal();
    });
    window.addEventListener('beforeunload', (event) => {
        if (state.mode === 'edit' && state.dirty) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    // ---------- load ----------

    fetch(CFG.bootstrapUrl || `${baseUrl}/bootstrap`, { headers: { Accept: 'application/json' } })
        .then(res => res.json())
        .then(data => {
            if (!data.success) throw new Error(data.message || 'Failed to load knowledge base.');
            state.categories = data.categories || [];
            state.articles = data.articles || [];
            renderTree();
            renderList();
            setMode('empty');
            applyHash();
        })
        .catch(err => {
            el.list.removeAttribute('aria-busy');
            el.list.innerHTML = `<p class="kb-list-empty">${escapeHtml(err.message || 'Failed to load knowledge base.')}</p>`;
        });
})();
