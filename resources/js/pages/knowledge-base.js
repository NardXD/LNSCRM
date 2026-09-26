/* Vite page entry — IIFE preserves onclick globals */
(function () {
// Rich text editor: sync contenteditable to hidden input
    function richEditorSync(editorEl) {
        const hiddenId = editorEl.dataset.hidden;
        if (hiddenId) {
            const hidden = document.getElementById(hiddenId);
            if (hidden) hidden.value = editorEl.innerHTML;
        }
    }

    function stripHtml(html) {
        const div = document.createElement('div');
        div.innerHTML = html || '';
        return (div.textContent || div.innerText || '').trim();
    }

    document.querySelectorAll('.rich-editor-content').forEach(editor => {
        editor.addEventListener('input', function() { richEditorSync(this); });
        editor.addEventListener('paste', function() { setTimeout(() => richEditorSync(this), 0); });
    });

    document.querySelectorAll('.rich-editor-toolbar').forEach(toolbar => {
        toolbar.addEventListener('click', function(e) {
            const btn = e.target.closest('.rich-editor-btn');
            if (!btn) return;
            e.preventDefault();
            const editorId = this.dataset.editor;
            const cmd = btn.dataset.cmd;
            const value = btn.dataset.value;
            const editor = document.getElementById(editorId);
            if (!editor) return;
            editor.focus();
            if (cmd === 'createLink') {
                const url = prompt('Enter URL:', 'https://');
                if (url) {
                    document.execCommand('createLink', false, url);
                    richEditorSync(editor);
                }
            } else if (cmd === 'formatBlock' && value) {
                document.execCommand('formatBlock', false, value);
                richEditorSync(editor);
            } else {
                document.execCommand(cmd, false, null);
                richEditorSync(editor);
            }
        });
    });

    document.querySelectorAll('.rich-editor-select').forEach(select => {
        select.addEventListener('change', function() {
            const editorId = this.dataset.editor;
            const editor = document.getElementById(editorId);
            if (!editor) return;
            editor.focus();
            document.execCommand('formatBlock', false, this.value);
            richEditorSync(editor);
        });
    });

    // Tab Switching
    function kebabToCamel(str) {
        return str.replace(/-([a-z])/g, (g) => g[1].toUpperCase());
    }

    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const tabId = this.dataset.tab;
            const camelTabId = kebabToCamel(tabId);
            
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            const tabContent = document.getElementById(camelTabId + 'Tab');
            if (tabContent) {
                tabContent.classList.add('active');
            }
        });
    });

    // Articles, FAQs, Guides (from backend, per company)
    let articlesData = [];
    let faqsData = [];
    let guidesData = [];
    const CFG = window.__knowledgeBaseConfig || {};
    const canCreateKnowledgeBase = CFG.canCreate !== false;
    const canEditKnowledgeBase = CFG.canEdit !== false;
    const canDeleteKnowledgeBase = CFG.canDelete !== false;

    let articleCategoriesData = [];
    let faqCategoriesData = [];
    let guideCategoriesData = [];

    const knowledgeBaseApi = {
        baseUrl: CFG.baseUrl || '/api/knowledge-base',
        csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        async postCategories(data) {
            return this._post('/categories', data, 'Failed to add category.');
        },
        async _post(path, data, errorLabel) {
            const res = await fetch(this.baseUrl + path, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify(data)
            });
            const json = await res.json();
            if (!res.ok) {
                const msg = json.errors ? Object.values(json.errors).flat().join(' ') : (json.message || errorLabel);
                throw new Error(msg);
            }
            return json;
        },
        async postArticles(data) {
            return this._post('/articles', data, 'Failed to create article.');
        },
        async postFaqs(data) {
            return this._post('/faqs', data, 'Failed to create FAQ.');
        },
        async postGuides(data) {
            return this._post('/guides', data, 'Failed to create guide.');
        },
        async _put(path, data, errorLabel) {
            const res = await fetch(this.baseUrl + path, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify(data)
            });
            const json = await res.json();
            if (!res.ok) {
                const msg = json.errors ? Object.values(json.errors).flat().join(' ') : (json.message || errorLabel);
                throw new Error(msg);
            }
            return json;
        },
        async _delete(path, errorLabel) {
            const res = await fetch(this.baseUrl + path, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' }
            });
            const json = res.status === 204 ? {} : await res.json();
            if (!res.ok) {
                const msg = json.message || errorLabel;
                throw new Error(msg);
            }
            return json;
        },
        async putArticle(id, data) {
            return this._put('/articles/' + id, data, 'Failed to update article.');
        },
        async deleteArticle(id) {
            return this._delete('/articles/' + id, 'Failed to delete article.');
        },
        async putFaq(id, data) {
            return this._put('/faqs/' + id, data, 'Failed to update FAQ.');
        },
        async deleteFaq(id) {
            return this._delete('/faqs/' + id, 'Failed to delete FAQ.');
        },
        async putGuide(id, data) {
            return this._put('/guides/' + id, data, 'Failed to update guide.');
        },
        async deleteGuide(id) {
            return this._delete('/guides/' + id, 'Failed to delete guide.');
        }
    };

    function formatArticleStatus(status) {
        const labels = {
            draft: 'Draft',
            published: 'Published',
            archived: 'Archived',
            internal: 'Published',
            public: 'Published',
        };
        return labels[status] || (status ? status.charAt(0).toUpperCase() + status.slice(1) : 'Draft');
    }

    function normalizeArticleStatus(status) {
        if (status === 'internal' || status === 'public') {
            return 'published';
        }
        return status || 'draft';
    }

    function getArticleCategoryFilter() {
        return document.getElementById('articleCategoryFilter')?.value || 'all';
    }

    // Render Articles
    function renderArticles(categoryFilter = 'all') {
        const grid = document.getElementById('articlesGrid');
        const filtered = categoryFilter === 'all'
            ? articlesData
            : articlesData.filter(article => article.category === categoryFilter);
        grid.innerHTML = filtered.map(article => {
            const status = normalizeArticleStatus(article.visibility);
            return `
            <div class="article-card" onclick="openArticle(${article.id})">
                <div class="article-header">
                    <span class="article-badge ${status}">${formatArticleStatus(article.visibility)}</span>
                </div>
                <h3 class="article-title">${article.title}</h3>
                <div class="article-excerpt article-excerpt-html">${article.excerpt}</div>
                <div class="article-footer">
                    ${article.category ? `<span class="article-category">${article.category}</span>` : ''}
                    <span>${article.views} views</span>
                </div>
            </div>
        `;
        }).join('');
    }

    const articleCategoryFilter = document.getElementById('articleCategoryFilter');
    if (articleCategoryFilter) {
        articleCategoryFilter.addEventListener('change', function() {
            renderArticles(this.value);
        });
    }

    // Render FAQs
    function getFaqCategoryFilter() {
        return document.querySelector('.faq-category-btn.active')?.dataset.category || 'all';
    }

    function renderFAQs(category = getFaqCategoryFilter()) {
        const list = document.getElementById('faqsList');
        const filtered = category === 'all' ? faqsData : faqsData.filter(faq => faq.category === category);

        list.innerHTML = filtered.map(faq => {
            const status = normalizeArticleStatus(faq.visibility);
            return `
            <div class="faq-item" onclick="toggleFAQ(${faq.id})">
                <div class="faq-question">
                    <div class="faq-question-main">
                        <span class="article-badge ${status}">${formatArticleStatus(faq.visibility)}</span>
                        <div class="faq-question-text">${faq.question}</div>
                    </div>
                    <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6 9 12 15 18 9"/>
                    </svg>
                </div>
                <div class="faq-answer">
                    <div class="faq-answer-text">${faq.answer}</div>
                    <div class="faq-meta">
                        ${faq.category ? `<span>${faq.category}</span>` : ''}
                        <span>${faq.views} views</span>
                        <div class="faq-item-actions" onclick="event.stopPropagation()">
                            ${canEditKnowledgeBase ? `<button type="button" class="btn btn-secondary btn-sm" onclick="editFaq(${faq.id})">Edit</button>` : ''}
                            ${canDeleteKnowledgeBase ? `<button type="button" class="btn btn-secondary btn-sm knowledge-modal-delete" onclick="deleteFaqConfirm(${faq.id})">Delete</button>` : ''}
                        </div>
                    </div>
                </div>
            </div>
        `;
        }).join('');
    }

    // Render Guides
    function renderGuides() {
        const grid = document.getElementById('guidesGrid');
        grid.innerHTML = guidesData.map(guide => `
            <div class="guide-card" onclick="openGuide(${guide.id})">
                <div class="guide-image">${guide.icon}</div>
                <div class="guide-content">
                    <div class="guide-category">${guide.category}</div>
                    <h3 class="guide-title">${guide.title}</h3>
                    <div class="guide-excerpt guide-excerpt-html">${guide.excerpt}</div>
                    <div class="guide-footer">
                        <span>${guide.duration} read</span>
                    </div>
                </div>
            </div>
        `).join('');
    }

    // FAQ Category Switching (delegated so dynamic buttons work)
    document.getElementById('faqCategoriesContainer').addEventListener('click', function(e) {
        const btn = e.target.closest('.faq-category-btn');
        if (!btn) return;
        this.querySelectorAll('.faq-category-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        renderFAQs(btn.dataset.category);
    });

    // Toggle FAQ
    function toggleFAQ(faqId) {
        const faqItem = document.querySelector(`.faq-item[onclick="toggleFAQ(${faqId})"]`);
        const isActive = faqItem.classList.contains('active');
        
        // Close all FAQs
        document.querySelectorAll('.faq-item').forEach(item => {
            item.classList.remove('active');
        });
        
        // Open clicked FAQ if it wasn't active
        if (!isActive) {
            faqItem.classList.add('active');
        }
    }

    // Open Article/Guide
    function openArticle(articleId) {
        const article = articlesData.find(a => a.id === articleId);
        if (!article) return;
        
        openKnowledgeModal(article, 'article');
    }

    function openGuide(guideId) {
        const guide = guidesData.find(g => g.id === guideId);
        if (!guide) return;
        
        openKnowledgeModal(guide, 'guide');
    }

    let currentKnowledgeItem = null;
    let currentKnowledgeType = null;

    function openKnowledgeModal(item, type) {
        currentKnowledgeItem = item;
        currentKnowledgeType = type;
        document.getElementById('modalBadge').textContent = formatArticleStatus(item.visibility);
        document.getElementById('modalBadge').className = `modal-badge ${normalizeArticleStatus(item.visibility)}`;
        document.getElementById('modalTitle').textContent = item.title;
        document.getElementById('modalCategory').textContent = item.category || 'No category';
        document.getElementById('modalCategory').style.display = type === 'article' && !item.category ? 'none' : '';
        document.getElementById('modalDate').textContent = item.date || 'Dec 31, 2025';
        document.getElementById('modalAuthor').textContent = `By ${item.author || 'Admin'}`;
        
        const contentBody = document.getElementById('contentBody');
        if (type === 'article') {
            contentBody.innerHTML = (item.content && item.content.trim()) ? item.content : `<p>${item.excerpt}</p>`;
        } else {
            contentBody.innerHTML = `<h2>${item.title}</h2><p>${item.excerpt}</p>`;
        }
        
        document.getElementById('knowledgeModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function editContent() {
        if (!currentKnowledgeItem || !currentKnowledgeType) return;
        if (currentKnowledgeType === 'article') {
            startEditArticle(currentKnowledgeItem);
        } else {
            startEditGuide(currentKnowledgeItem);
        }
        closeKnowledgeModal();
    }

    async function deleteContent() {
        if (!currentKnowledgeItem || !currentKnowledgeType) return;
        if (!confirm('Are you sure you want to delete this?')) return;
        try {
            if (currentKnowledgeType === 'article') {
                await knowledgeBaseApi.deleteArticle(currentKnowledgeItem.id);
                const idx = articlesData.findIndex(a => a.id === currentKnowledgeItem.id);
                if (idx !== -1) articlesData.splice(idx, 1);
                renderArticles(getArticleCategoryFilter());
            } else {
                await knowledgeBaseApi.deleteGuide(currentKnowledgeItem.id);
                const idx = guidesData.findIndex(g => g.id === currentKnowledgeItem.id);
                if (idx !== -1) guidesData.splice(idx, 1);
                renderGuides();
            }
            closeKnowledgeModal();
        } catch (err) {
            alert(err.message || 'Failed to delete.');
        }
    }

    function closeKnowledgeModal() {
        document.getElementById('knowledgeModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    document.getElementById('knowledgeModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeKnowledgeModal();
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (document.getElementById('addCategoryModal').classList.contains('active')) {
                closeAddCategoryModal();
            } else if (document.getElementById('articlePreviewModal').classList.contains('active')) {
                closeArticlePreviewModal();
            } else if (document.getElementById('newArticleModal').classList.contains('active')) {
                closeNewArticleModal();
            } else if (document.getElementById('faqPreviewModal').classList.contains('active')) {
                closeFaqPreviewModal();
            } else if (document.getElementById('newFAQModal').classList.contains('active')) {
                closeNewFAQModal();
            } else if (document.getElementById('newGuideModal').classList.contains('active')) {
                closeNewGuideModal();
            } else {
                closeKnowledgeModal();
            }
        }
    });

    // Add Category Modal
    const addCategoryTypeLabels = { article: 'Article', faq: 'FAQ', guide: 'Guide' };
    function openAddCategoryModal(type) {
        document.getElementById('addCategoryType').value = type;
        document.getElementById('addCategoryModalTitle').textContent = 'Add ' + addCategoryTypeLabels[type] + ' category';
        document.getElementById('addCategoryName').value = '';
        document.getElementById('addCategoryModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function closeAddCategoryModal() {
        document.getElementById('addCategoryModal').classList.remove('active');
        document.body.style.overflow = '';
    }
    async function submitAddCategory(e) {
        e.preventDefault();
        const type = document.getElementById('addCategoryType').value;
        const name = document.getElementById('addCategoryName').value.trim();
        if (!name) return;
        try {
            const { category } = await knowledgeBaseApi.postCategories({ type, name });
            if (type === 'article') {
                articleCategoriesData.push(category);
                const sel = document.getElementById('newArticleCategory');
                const opt = document.createElement('option');
                opt.value = category.slug;
                opt.textContent = category.name;
                sel.appendChild(opt);
                opt.selected = true;
                const filterSel = document.getElementById('articleCategoryFilter');
                if (filterSel) {
                    const filterOpt = document.createElement('option');
                    filterOpt.value = category.name;
                    filterOpt.textContent = category.name;
                    filterSel.appendChild(filterOpt);
                } else {
                    const sectionActions = document.querySelector('#articlesTab .section-actions');
                    if (sectionActions) {
                        const select = document.createElement('select');
                        select.className = 'filter-select';
                        select.id = 'articleCategoryFilter';
                        select.innerHTML = '<option value="all">All Categories</option>';
                        const filterOpt = document.createElement('option');
                        filterOpt.value = category.name;
                        filterOpt.textContent = category.name;
                        select.appendChild(filterOpt);
                        select.addEventListener('change', function() {
                            renderArticles(this.value);
                        });
                        sectionActions.insertBefore(select, sectionActions.firstChild);
                    }
                }
            } else if (type === 'faq') {
                faqCategoriesData.push(category);
                const sel = document.getElementById('newFAQCategory');
                const opt = document.createElement('option');
                opt.value = category.slug;
                opt.textContent = category.name;
                sel.appendChild(opt);
                opt.selected = true;
                const container = document.getElementById('faqCategoriesContainer');
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'leads-tab faq-category-btn';
                btn.dataset.category = category.name;
                btn.textContent = category.name;
                container.appendChild(btn);
            } else {
                guideCategoriesData.push(category);
                const sel = document.getElementById('newGuideCategory');
                const opt = document.createElement('option');
                opt.value = category.slug;
                opt.textContent = category.name;
                sel.appendChild(opt);
                opt.selected = true;
            }
            closeAddCategoryModal();
        } catch (err) {
            alert(err.message || 'Failed to add category.');
        }
    }
    document.getElementById('addCategoryModal').addEventListener('click', function(e) {
        if (e.target === this) closeAddCategoryModal();
    });

    let editArticleId = null;
    let editFaqId = null;
    let editGuideId = null;

    function setArticleCategoryByDisplayName(displayName) {
        const sel = document.getElementById('newArticleCategory');
        if (!displayName) {
            sel.value = '';
            return;
        }
        for (const opt of sel.options) {
            if (opt.textContent === displayName) { sel.value = opt.value; return; }
        }
        sel.value = '';
    }

    function setFaqCategoryByDisplayName(displayName) {
        const sel = document.getElementById('newFAQCategory');
        if (!displayName) {
            sel.value = '';
            return;
        }
        for (const opt of sel.options) {
            if (opt.textContent === displayName) { sel.value = opt.value; return; }
        }
        sel.value = '';
    }

    function setGuideCategoryByDisplayName(displayName) {
        const sel = document.getElementById('newGuideCategory');
        for (const opt of sel.options) {
            if (opt.textContent === displayName) { sel.value = opt.value; return; }
        }
    }

    function startEditArticle(article) {
        editArticleId = article.id;
        document.getElementById('newArticleTitle').value = article.title;
        const excerptEditor = document.getElementById('newArticleExcerptEditor');
        const excerptHidden = document.getElementById('newArticleExcerpt');
        excerptEditor.innerHTML = article.excerpt || '';
        excerptHidden.value = article.excerpt || '';
        const contentEditor = document.getElementById('newArticleContentEditor');
        const contentHidden = document.getElementById('newArticleContent');
        contentEditor.innerHTML = article.content || '';
        contentHidden.value = article.content || '';
        setArticleCategoryByDisplayName(article.category);
        document.getElementById('newArticleModal').classList.add('active');
        document.querySelector('#newArticleModal .modal-title').textContent = 'Edit Article';
        document.body.style.overflow = 'hidden';
    }

    function startEditGuide(guide) {
        editGuideId = guide.id;
        document.getElementById('newGuideTitle').value = guide.title;
        const excerptEditor = document.getElementById('newGuideExcerptEditor');
        const excerptHidden = document.getElementById('newGuideExcerpt');
        excerptEditor.innerHTML = guide.excerpt || '';
        excerptHidden.value = guide.excerpt || '';
        setGuideCategoryByDisplayName(guide.category);
        document.getElementById('newGuideDuration').value = guide.duration || '';
        document.getElementById('newGuideIcon').value = guide.icon || '📖';
        document.querySelectorAll('#newGuideIconPicker .icon-picker-btn').forEach(btn => {
            btn.classList.toggle('selected', btn.dataset.icon === (guide.icon || '📖'));
        });
        document.getElementById('newGuideModal').classList.add('active');
        document.querySelector('#newGuideModal .modal-title').textContent = 'Edit Guide';
        document.body.style.overflow = 'hidden';
    }

    function editFaq(id) {
        const faq = faqsData.find(f => f.id === id);
        if (!faq) return;
        editFaqId = id;
        document.getElementById('newFAQQuestion').value = faq.question;
        const answerEditor = document.getElementById('newFAQAnswerEditor');
        const answerHidden = document.getElementById('newFAQAnswer');
        answerEditor.innerHTML = faq.answer || '';
        answerHidden.value = faq.answer || '';
        setFaqCategoryByDisplayName(faq.category);
        document.getElementById('newFAQModal').classList.add('active');
        document.querySelector('#newFAQModal .modal-title').textContent = 'Edit FAQ';
        document.body.style.overflow = 'hidden';
    }

    async function deleteFaqConfirm(id) {
        if (!confirm('Are you sure you want to delete this FAQ?')) return;
        try {
            await knowledgeBaseApi.deleteFaq(id);
            const idx = faqsData.findIndex(f => f.id === id);
            if (idx !== -1) faqsData.splice(idx, 1);
            renderFAQs(getFaqCategoryFilter());
        } catch (err) {
            alert(err.message || 'Failed to delete.');
        }
    }

    function syncArticleEditors() {
        ['newArticleExcerptEditor', 'newArticleContentEditor'].forEach(id => {
            const editor = document.getElementById(id);
            if (editor) richEditorSync(editor);
        });
    }

    function collectArticleFormData() {
        syncArticleEditors();
        const title = document.getElementById('newArticleTitle').value.trim();
        const excerptHtml = document.getElementById('newArticleExcerpt').value;
        const excerpt = stripHtml(excerptHtml).length ? excerptHtml : (document.getElementById('newArticleExcerptEditor').innerText || '').trim();
        const category = document.getElementById('newArticleCategory').value;
        const content = document.getElementById('newArticleContent').value;
        const categorySelect = document.getElementById('newArticleCategory');
        const categoryLabel = category
            ? categorySelect.options[categorySelect.selectedIndex]?.textContent || 'No category'
            : 'No category';
        return { title, excerpt, category, categoryLabel, content };
    }

    function validateArticleForm(data) {
        if (!data.title) {
            document.getElementById('newArticleTitle').focus();
            return false;
        }
        if (!data.excerpt) {
            document.getElementById('newArticleExcerptEditor').focus();
            return false;
        }
        return true;
    }

    // New Article Modal
    function createArticle() {
        editArticleId = null;
        document.getElementById('newArticleForm').reset();
        document.querySelector('#newArticleModal .modal-title').textContent = 'New Article';
        const excerptEditor = document.getElementById('newArticleExcerptEditor');
        const excerptHidden = document.getElementById('newArticleExcerpt');
        const contentEditor = document.getElementById('newArticleContentEditor');
        const contentHidden = document.getElementById('newArticleContent');
        if (excerptEditor) { excerptEditor.innerHTML = ''; }
        if (excerptHidden) { excerptHidden.value = ''; }
        if (contentEditor) { contentEditor.innerHTML = ''; }
        if (contentHidden) { contentHidden.value = ''; }
        document.getElementById('newArticleCategory').value = '';
        document.getElementById('newArticleModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeNewArticleModal() {
        document.getElementById('newArticleModal').classList.remove('active');
        if (!document.getElementById('articlePreviewModal').classList.contains('active')) {
            document.body.style.overflow = '';
        }
    }

    function previewArticle(e) {
        e.preventDefault();
        const data = collectArticleFormData();
        if (!validateArticleForm(data)) return;

        document.getElementById('articlePreviewTitle').textContent = data.title;
        document.getElementById('articlePreviewCategory').textContent = data.categoryLabel;
        document.getElementById('articlePreviewMeta').style.display = data.category ? '' : 'none';
        document.getElementById('articlePreviewExcerpt').innerHTML = data.excerpt;
        document.getElementById('articlePreviewContent').innerHTML = data.content || '';
        document.getElementById('articlePreviewBadge').textContent = 'Preview';
        document.getElementById('articlePreviewBadge').className = 'modal-badge draft';

        document.getElementById('articlePreviewModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeArticlePreviewModal() {
        document.getElementById('articlePreviewModal').classList.remove('active');
        if (document.getElementById('newArticleModal').classList.contains('active')) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    }

    async function saveArticleWithStatus(status) {
        const data = collectArticleFormData();
        if (!validateArticleForm(data)) {
            closeArticlePreviewModal();
            return;
        }

        const payload = {
            title: data.title,
            excerpt: data.excerpt || data.title,
            content: data.content,
            category: data.category || null,
            visibility: status,
        };

        try {
            if (editArticleId) {
                const { article } = await knowledgeBaseApi.putArticle(editArticleId, payload);
                const idx = articlesData.findIndex(a => a.id === editArticleId);
                if (idx !== -1) articlesData[idx] = article;
                editArticleId = null;
            } else {
                const { article } = await knowledgeBaseApi.postArticles(payload);
                articlesData.unshift(article);
            }
            renderArticles(getArticleCategoryFilter());
            closeArticlePreviewModal();
            closeNewArticleModal();
        } catch (err) {
            alert(err.message || 'Failed to save article.');
        }
    }

    document.getElementById('newArticleModal').addEventListener('click', function(e) {
        if (e.target === this) closeNewArticleModal();
    });

    document.getElementById('articlePreviewModal').addEventListener('click', function(e) {
        if (e.target === this) closeArticlePreviewModal();
    });

    // New FAQ Modal
    function syncFaqEditors() {
        const editor = document.getElementById('newFAQAnswerEditor');
        if (editor) richEditorSync(editor);
    }

    function collectFaqFormData() {
        syncFaqEditors();
        const question = document.getElementById('newFAQQuestion').value.trim();
        const answerHtml = document.getElementById('newFAQAnswer').value;
        const answer = stripHtml(answerHtml).length ? answerHtml : (document.getElementById('newFAQAnswerEditor').innerText || '').trim();
        const category = document.getElementById('newFAQCategory').value;
        const categorySelect = document.getElementById('newFAQCategory');
        const categoryLabel = category
            ? categorySelect.options[categorySelect.selectedIndex]?.textContent || 'No category'
            : 'No category';
        return { question, answer, answerHtml, category, categoryLabel };
    }

    function validateFaqForm(data) {
        if (!data.question) {
            document.getElementById('newFAQQuestion').focus();
            return false;
        }
        if (!data.answer) {
            document.getElementById('newFAQAnswerEditor').focus();
            return false;
        }
        return true;
    }

    function createFAQ() {
        editFaqId = null;
        document.getElementById('newFAQForm').reset();
        document.querySelector('#newFAQModal .modal-title').textContent = 'New FAQ';
        const answerEditor = document.getElementById('newFAQAnswerEditor');
        const answerHidden = document.getElementById('newFAQAnswer');
        if (answerEditor) { answerEditor.innerHTML = ''; }
        if (answerHidden) { answerHidden.value = ''; }
        document.getElementById('newFAQCategory').value = '';
        document.getElementById('newFAQModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeNewFAQModal() {
        document.getElementById('newFAQModal').classList.remove('active');
        if (!document.getElementById('faqPreviewModal').classList.contains('active')) {
            document.body.style.overflow = '';
        }
    }

    function previewFaq(e) {
        e.preventDefault();
        const data = collectFaqFormData();
        if (!validateFaqForm(data)) return;

        document.getElementById('faqPreviewQuestion').textContent = data.question;
        document.getElementById('faqPreviewCategory').textContent = data.categoryLabel;
        document.getElementById('faqPreviewMeta').style.display = data.category ? '' : 'none';
        document.getElementById('faqPreviewAnswer').innerHTML = data.answer;
        document.getElementById('faqPreviewBadge').textContent = 'Preview';
        document.getElementById('faqPreviewBadge').className = 'modal-badge draft';

        document.getElementById('faqPreviewModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeFaqPreviewModal() {
        document.getElementById('faqPreviewModal').classList.remove('active');
        if (document.getElementById('newFAQModal').classList.contains('active')) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    }

    async function saveFaqWithStatus(status) {
        const data = collectFaqFormData();
        if (!validateFaqForm(data)) {
            closeFaqPreviewModal();
            return;
        }

        const payload = {
            question: data.question,
            answer: data.answerHtml.length ? data.answerHtml : data.answer,
            category: data.category || null,
            visibility: status,
        };

        try {
            if (editFaqId) {
                const { faq } = await knowledgeBaseApi.putFaq(editFaqId, payload);
                const idx = faqsData.findIndex(f => f.id === editFaqId);
                if (idx !== -1) faqsData[idx] = faq;
                editFaqId = null;
            } else {
                const { faq } = await knowledgeBaseApi.postFaqs(payload);
                faqsData.unshift(faq);
            }
            renderFAQs(getFaqCategoryFilter());
            closeFaqPreviewModal();
            closeNewFAQModal();
        } catch (err) {
            alert(err.message || 'Failed to save FAQ.');
        }
    }

    document.getElementById('newFAQModal').addEventListener('click', function(e) {
        if (e.target === this) closeNewFAQModal();
    });

    document.getElementById('faqPreviewModal').addEventListener('click', function(e) {
        if (e.target === this) closeFaqPreviewModal();
    });

    // New Guide Modal
    function createGuide() {
        editGuideId = null;
        document.getElementById('newGuideForm').reset();
        document.querySelector('#newGuideModal .modal-title').textContent = 'New Guide';
        document.getElementById('newGuideIcon').value = '📖';
        document.querySelectorAll('#newGuideIconPicker .icon-picker-btn').forEach((btn) => {
            btn.classList.toggle('selected', btn.dataset.icon === '📖');
        });
        const guideExcerptEditor = document.getElementById('newGuideExcerptEditor');
        const guideExcerptHidden = document.getElementById('newGuideExcerpt');
        if (guideExcerptEditor) { guideExcerptEditor.innerHTML = ''; }
        if (guideExcerptHidden) { guideExcerptHidden.value = ''; }
        document.getElementById('newGuideModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeNewGuideModal() {
        document.getElementById('newGuideModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    async function submitNewGuide(e) {
        e.preventDefault();
        const title = document.getElementById('newGuideTitle').value.trim();
        const excerptHtml = document.getElementById('newGuideExcerpt').value;
        const excerpt = stripHtml(excerptHtml).length ? excerptHtml : (document.getElementById('newGuideExcerptEditor').innerText || '').trim();
        if (!excerpt) {
            document.getElementById('newGuideExcerptEditor').focus();
            return;
        }
        const category = document.getElementById('newGuideCategory').value;
        const duration = document.getElementById('newGuideDuration').value.trim() || '10 min';
        const icon = document.getElementById('newGuideIcon').value.trim() || '📖';
        if (!title || !category) return;
        try {
            if (editGuideId) {
                const { guide } = await knowledgeBaseApi.putGuide(editGuideId, { title, excerpt: excerpt || title, category, duration, icon });
                const idx = guidesData.findIndex(g => g.id === editGuideId);
                if (idx !== -1) guidesData[idx] = guide;
                editGuideId = null;
            } else {
                const { guide } = await knowledgeBaseApi.postGuides({ title, excerpt: excerpt || title, category, duration, icon });
                guidesData.unshift(guide);
            }
            renderGuides();
            closeNewGuideModal();
        } catch (err) {
            alert(err.message || 'Failed to save guide.');
        }
    }

    document.getElementById('newGuideModal').addEventListener('click', function(e) {
        if (e.target === this) closeNewGuideModal();
    });

    document.getElementById('newGuideIconPicker').addEventListener('click', function(e) {
        const btn = e.target.closest('.icon-picker-btn');
        if (!btn) return;
        document.getElementById('newGuideIcon').value = btn.dataset.icon;
        this.querySelectorAll('.icon-picker-btn').forEach(b => b.classList.remove('selected'));
        btn.classList.add('selected');
    });

    // Initialize after bootstrap (inbox/facebook-style: shell first, then JSON)
    function fillCategorySelect(selectId, categories, placeholder) {
        const sel = document.getElementById(selectId);
        if (!sel) return;
        const current = sel.value;
        const keepFirst = sel.querySelector('option');
        sel.innerHTML = '';
        if (keepFirst && !keepFirst.value) {
            sel.appendChild(keepFirst);
        } else if (placeholder) {
            const opt = document.createElement('option');
            opt.value = '';
            opt.textContent = placeholder;
            sel.appendChild(opt);
        }
        categories.forEach((category) => {
            const opt = document.createElement('option');
            opt.value = category.slug;
            opt.textContent = category.name;
            sel.appendChild(opt);
        });
        if (current) sel.value = current;
    }

    function applyKnowledgeBootstrap(data) {
        articlesData = data.articles || [];
        faqsData = data.faqs || [];
        guidesData = data.guides || [];
        articleCategoriesData = data.article_categories || [];
        faqCategoriesData = data.faq_categories || [];
        guideCategoriesData = data.guide_categories || [];

        fillCategorySelect('newArticleCategory', articleCategoriesData, 'No category');
        fillCategorySelect('newFAQCategory', faqCategoriesData, 'No category');
        fillCategorySelect('newGuideCategory', guideCategoriesData, 'Select category');

        const filterSel = document.getElementById('articleCategoryFilter');
        if (filterSel) {
            const current = filterSel.value || 'all';
            filterSel.innerHTML = '<option value="all">All Categories</option>';
            articleCategoriesData.forEach((category) => {
                const opt = document.createElement('option');
                opt.value = category.name;
                opt.textContent = category.name;
                filterSel.appendChild(opt);
            });
            filterSel.value = current;
        }

        const faqContainer = document.getElementById('faqCategoriesContainer');
        if (faqContainer) {
            const active = faqContainer.querySelector('.faq-category-btn.active')?.dataset.category || 'all';
            faqContainer.querySelectorAll('.faq-category-btn:not([data-category="all"])').forEach((btn) => btn.remove());
            faqCategoriesData.forEach((category) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'leads-tab faq-category-btn';
                btn.dataset.category = category.name;
                btn.textContent = category.name;
                faqContainer.appendChild(btn);
            });
            const keep = faqContainer.querySelector(`.faq-category-btn[data-category="${active}"]`);
            faqContainer.querySelectorAll('.faq-category-btn').forEach((b) => b.classList.remove('active'));
            (keep || faqContainer.querySelector('[data-category="all"]'))?.classList.add('active');
        }

        document.getElementById('articlesGrid')?.removeAttribute('aria-busy');
        document.getElementById('faqsList')?.removeAttribute('aria-busy');
        document.getElementById('guidesGrid')?.removeAttribute('aria-busy');
        renderArticles(getArticleCategoryFilter());
        renderFAQs(getFaqCategoryFilter());
        renderGuides();
    }

    fetch(CFG.bootstrapUrl || '/api/knowledge-base/bootstrap', { headers: { Accept: 'application/json' } })
        .then((res) => {
            if (!res.ok) throw new Error('Failed to load knowledge base');
            return res.json();
        })
        .then(applyKnowledgeBootstrap)
        .catch((err) => {
            console.error(err);
            document.getElementById('articlesGrid').innerHTML = '';
            document.getElementById('faqsList').innerHTML = '';
            document.getElementById('guidesGrid').innerHTML = '';
        });

    if (typeof closeAddCategoryModal === 'function') window.closeAddCategoryModal = closeAddCategoryModal;
    if (typeof closeArticlePreviewModal === 'function') window.closeArticlePreviewModal = closeArticlePreviewModal;
    if (typeof closeFaqPreviewModal === 'function') window.closeFaqPreviewModal = closeFaqPreviewModal;
    if (typeof closeKnowledgeModal === 'function') window.closeKnowledgeModal = closeKnowledgeModal;
    if (typeof closeNewArticleModal === 'function') window.closeNewArticleModal = closeNewArticleModal;
    if (typeof closeNewFAQModal === 'function') window.closeNewFAQModal = closeNewFAQModal;
    if (typeof closeNewGuideModal === 'function') window.closeNewGuideModal = closeNewGuideModal;
    if (typeof createArticle === 'function') window.createArticle = createArticle;
    if (typeof createFAQ === 'function') window.createFAQ = createFAQ;
    if (typeof createGuide === 'function') window.createGuide = createGuide;
    if (typeof deleteContent === 'function') window.deleteContent = deleteContent;
    if (typeof deleteFaqConfirm === 'function') window.deleteFaqConfirm = deleteFaqConfirm;
    if (typeof editContent === 'function') window.editContent = editContent;
    if (typeof editFaq === 'function') window.editFaq = editFaq;
    if (typeof openAddCategoryModal === 'function') window.openAddCategoryModal = openAddCategoryModal;
    if (typeof openArticle === 'function') window.openArticle = openArticle;
    if (typeof openGuide === 'function') window.openGuide = openGuide;
    if (typeof saveArticleWithStatus === 'function') window.saveArticleWithStatus = saveArticleWithStatus;
    if (typeof saveFaqWithStatus === 'function') window.saveFaqWithStatus = saveFaqWithStatus;
    if (typeof stopPropagation === 'function') window.stopPropagation = stopPropagation;
    if (typeof toggleFAQ === 'function') window.toggleFAQ = toggleFAQ;
})();
