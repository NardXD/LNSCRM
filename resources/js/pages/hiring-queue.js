/* Vite page entry — IIFE preserves onclick globals */
(function () {
const CFG = window.__hiringQueueConfig || {};
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        const queueStatuses = ['open', 'cancel', 'pending', 'close'];
        const candidateStatuses = ['pending', 'accepted', 'rejected'];
        let queueItems = [];
        let queueCurrentPage = 1;
        let queueItemsPerPage = 10;
        let queueTotalItems = 0;
        let queueTotalPages = 1;
        let selectedQueueItem = null;
        let candidateItems = [];
        let reopenCandidatesAfterAddModal = false;
        let activeCommentsItemId = null;

        async function loadQueue(page = 1) {
            try {
                const params = new URLSearchParams({
                    page: String(page),
                    per_page: String(queueItemsPerPage)
                });
                const r = await fetch(`${CFG.indexUrl || "/api/hiring-queue"}?${params.toString()}`, { headers: { 'Accept': 'application/json' } });
                const data = await r.json();
                queueItems = data.items || [];
                queueTotalItems = Number(data.pagination?.total ?? queueItems.length);
                queueTotalPages = Number(data.pagination?.last_page ?? 1);
                queueCurrentPage = Number(data.pagination?.current_page ?? page);
                renderTable();
                renderQueuePagination();
                if (selectedQueueItem && isCandidatesModalOpen()) {
                    loadCandidates(selectedQueueItem.id, selectedQueueItem.job_title);
                }
            } catch (e) {
                console.error(e);
            }
        }

        function renderTable() {
            const tbody = document.getElementById('hiringQueueBody');
            document.getElementById('queueCount').textContent = queueTotalItems;
            if (!queueItems.length) {
                tbody.innerHTML = '<tr><td colspan="7" class="empty-state">No positions in hiring queue yet.</td></tr>';
                return;
            }
            tbody.innerHTML = queueItems.map(item => {
                const title = escapeHtml(item.job_title);
                const creator = escapeHtml(item.created_by || 'Unknown');
                const statusOptions = queueStatuses.map(status => `<option value="${status}" ${item.status === status ? 'selected' : ''}>${formatStatusLabel(status)}</option>`).join('');
                const sourceInfo = getSourceBadgeData(item.source);
                const commentCount = Number(item.comments_count || 0);
                return `
                <tr data-id="${item.id}" data-title="${title}" class="queue-row">
                    <td class="job-title-cell">${title}</td>
                    <td><span class="source-badge ${sourceInfo.className}">${sourceInfo.label}</span></td>
                    <td>
                        <select class="status-select queue-status-select" data-id="${item.id}">
                            ${statusOptions}
                        </select>
                    </td>
                    <td>${creator}</td>
                    <td>${item.created_at}</td>
                    <td class="comments-cell">
                        <button type="button" class="comments-open-btn" data-id="${item.id}" title="View comments">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                            Comments
                            <span class="comments-count-badge">${commentCount}</span>
                        </button>
                    </td>
                    <td class="actions-cell">
                        <div class="action-btns">
                            <button type="button" title="Add candidate" class="add-candidate-btn">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
                            </button>
                            <button type="button" title="View" class="view-job-btn">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                            <button type="button" title="View candidates" class="view-candidates-btn">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            </button>
                            <button type="button" title="Edit" class="edit-queue-btn">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </button>
                            <a href="${CFG.baseUrl || '/api/hiring-queue'}/${item.id}/pdf" class="action-btn-pdf" title="Download PDF" target="_blank" rel="noopener">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            </a>
                        </div>
                    </td>
                </tr>
            `}).join('');
            tbody.querySelectorAll('.queue-row').forEach(tr => {
                const id = parseInt(tr.dataset.id);
                const title = tr.dataset.title;
                tr.querySelector('.add-candidate-btn').addEventListener('click', (e) => { e.stopPropagation(); openAddCandidateModal(id, title); });
                tr.querySelector('.edit-queue-btn').addEventListener('click', (e) => { e.stopPropagation(); openEditQueueModal(id); });
                tr.querySelector('.view-job-btn').addEventListener('click', (e) => { e.stopPropagation(); viewJob(id); });
                tr.querySelector('.view-candidates-btn').addEventListener('click', (e) => {
                    e.stopPropagation();
                    openCandidatesModal(id, title);
                });
                tr.querySelector('.queue-status-select').addEventListener('click', (e) => e.stopPropagation());
                tr.querySelector('.queue-status-select').addEventListener('change', async (e) => {
                    await updateQueueStatus(id, e.target.value);
                });
                tr.querySelector('.comments-open-btn').addEventListener('click', (e) => {
                    e.stopPropagation();
                    openCommentsModal(id, title);
                });
                const pdfLink = tr.querySelector('.action-btn-pdf');
                if (pdfLink) pdfLink.addEventListener('click', (e) => e.stopPropagation());
            });
        }

        function renderCommentItem(comment) {
            const deleteBtn = comment.can_delete
                ? `<button type="button" class="comment-delete-btn" data-comment-id="${comment.id}" title="Delete comment">Delete</button>`
                : '';
            return `
                <div class="comment-item" data-comment-id="${comment.id}">
                    <div class="comment-avatar">${escapeHtml(comment.initials || '—')}</div>
                    <div class="comment-content">
                        <div class="comment-header">
                            <div class="comment-header-main">
                                <span class="comment-author">${escapeHtml(comment.author || 'Unknown')}</span>
                                <span class="comment-time">${escapeHtml(comment.created_at || '')}</span>
                            </div>
                            ${deleteBtn}
                        </div>
                        <div class="comment-text">${escapeHtml(comment.content || '')}</div>
                    </div>
                </div>
            `;
        }

        function renderCommentsList(comments) {
            const list = document.getElementById('queueCommentsList');
            if (!comments.length) {
                list.innerHTML = '<p class="comments-empty">No comments yet. Be the first to add one.</p>';
                return;
            }
            list.innerHTML = comments.map(renderCommentItem).join('');
            list.scrollTop = list.scrollHeight;
        }

        function updateCommentsCount(itemId, count) {
            const item = queueItems.find(i => i.id === itemId);
            if (item) {
                item.comments_count = count;
            }
            const badge = document.querySelector(`.comments-open-btn[data-id="${itemId}"] .comments-count-badge`);
            if (badge) {
                badge.textContent = count;
            }
        }

        async function openCommentsModal(itemId, jobTitle) {
            activeCommentsItemId = itemId;
            document.getElementById('commentsModalJobTitle').textContent = jobTitle;
            document.getElementById('queueCommentTextarea').value = '';
            document.getElementById('commentsModal').classList.add('open');
            await loadQueueComments(itemId);
        }

        function closeCommentsModal() {
            document.getElementById('commentsModal').classList.remove('open');
            activeCommentsItemId = null;
        }

        async function loadQueueComments(itemId) {
            const list = document.getElementById('queueCommentsList');
            list.innerHTML = '<p class="comments-empty">Loading comments…</p>';
            try {
                const r = await fetch(`${CFG.baseUrl || "/api/hiring-queue"}/${itemId}/comments`, { headers: { 'Accept': 'application/json' } });
                const data = await r.json();
                const comments = data.comments || [];
                renderCommentsList(comments);
                updateCommentsCount(itemId, comments.length);
            } catch (e) {
                console.error(e);
                list.innerHTML = '<p class="comments-empty">Could not load comments.</p>';
            }
        }

        async function addQueueComment() {
            if (!activeCommentsItemId) return;
            const textarea = document.getElementById('queueCommentTextarea');
            const button = document.getElementById('addQueueCommentBtn');
            const content = textarea.value.trim();
            if (!content) return;

            button.disabled = true;
            button.textContent = 'Posting…';
            try {
                const r = await fetch(`${CFG.baseUrl || "/api/hiring-queue"}/${activeCommentsItemId}/comments`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ content })
                });
                if (r.ok) {
                    const data = await r.json();
                    const list = document.getElementById('queueCommentsList');
                    const empty = list.querySelector('.comments-empty');
                    if (empty) {
                        list.innerHTML = '';
                    }
                    list.insertAdjacentHTML('beforeend', renderCommentItem(data.comment));
                    list.scrollTop = list.scrollHeight;
                    textarea.value = '';
                    const item = queueItems.find(i => i.id === activeCommentsItemId);
                    const newCount = (item?.comments_count || 0) + 1;
                    updateCommentsCount(activeCommentsItemId, newCount);
                } else {
                    const data = await r.json().catch(() => ({}));
                    alert(data.message || 'Could not add comment. Please try again.');
                }
            } catch (e) {
                console.error(e);
                alert('Could not add comment. Please try again.');
            }
            button.disabled = false;
            button.textContent = 'Add Comment';
        }

        async function deleteQueueComment(itemId, commentId) {
            if (!confirm('Delete this comment?')) {
                return;
            }

            try {
                const r = await fetch(`${CFG.baseUrl || "/api/hiring-queue"}/${itemId}/comments/${commentId}`, {
                    method: 'DELETE',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                });
                if (r.ok) {
                    const list = document.getElementById('queueCommentsList');
                    const item = list.querySelector(`.comment-item[data-comment-id="${commentId}"]`);
                    if (item) {
                        item.remove();
                    }
                    if (!list.querySelector('.comment-item')) {
                        list.innerHTML = '<p class="comments-empty">No comments yet. Be the first to add one.</p>';
                    }
                    const queueItem = queueItems.find(i => i.id === itemId);
                    const newCount = Math.max(0, (queueItem?.comments_count || 1) - 1);
                    updateCommentsCount(itemId, newCount);
                } else {
                    const data = await r.json().catch(() => ({}));
                    alert(data.message || 'Could not delete comment. Please try again.');
                }
            } catch (e) {
                console.error(e);
                alert('Could not delete comment. Please try again.');
            }
        }

        async function updateQueueStatus(itemId, status) {
            try {
                const r = await fetch(`/api/hiring-queue/${itemId}/status`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ status })
                });
                if (!r.ok) {
                    await loadQueue(queueCurrentPage);
                }
            } catch (e) {
                console.error(e);
                await loadQueue(queueCurrentPage);
            }
        }

        function renderQueuePagination() {
            const info = document.getElementById('queuePaginationInfo');
            const numbers = document.getElementById('queuePaginationNumbers');
            const prevBtn = document.getElementById('queuePrevBtn');
            const nextBtn = document.getElementById('queueNextBtn');

            const start = queueTotalItems > 0 ? (queueCurrentPage - 1) * queueItemsPerPage + 1 : 0;
            const end = Math.min(queueCurrentPage * queueItemsPerPage, queueTotalItems);
            info.textContent = `Showing ${start} to ${end} of ${queueTotalItems} results`;

            prevBtn.disabled = queueCurrentPage <= 1;
            nextBtn.disabled = queueCurrentPage >= queueTotalPages;

            const maxVisible = 5;
            let startPage = Math.max(1, queueCurrentPage - Math.floor(maxVisible / 2));
            let endPage = Math.min(queueTotalPages, startPage + maxVisible - 1);
            startPage = Math.max(1, endPage - maxVisible + 1);

            let html = '';
            if (startPage > 1) {
                html += `<button class="pagination-number" data-page="1">1</button>`;
                if (startPage > 2) html += `<span class="pagination-number ellipsis">...</span>`;
            }

            for (let i = startPage; i <= endPage; i += 1) {
                html += `<button class="pagination-number ${i === queueCurrentPage ? 'active' : ''}" data-page="${i}">${i}</button>`;
            }

            if (endPage < queueTotalPages) {
                if (endPage < queueTotalPages - 1) html += `<span class="pagination-number ellipsis">...</span>`;
                html += `<button class="pagination-number" data-page="${queueTotalPages}">${queueTotalPages}</button>`;
            }

            numbers.innerHTML = html;
            numbers.querySelectorAll('.pagination-number:not(.ellipsis)').forEach(btn => {
                btn.addEventListener('click', () => {
                    const page = parseInt(btn.dataset.page, 10);
                    if (!Number.isNaN(page)) loadQueue(page);
                });
            });
        }

        function openCandidatesModal(itemId, jobTitle) {
            document.getElementById('candidatesModal').classList.add('open');
            loadCandidates(itemId, jobTitle);
        }

        function closeCandidatesModal(preserveSelection = false) {
            document.getElementById('candidatesModal').classList.remove('open');
            if (!preserveSelection) {
                resetCandidatesPane();
            }
        }

        function isCandidatesModalOpen() {
            return document.getElementById('candidatesModal').classList.contains('open');
        }

        async function loadCandidates(itemId, jobTitle) {
            selectedQueueItem = { id: itemId, job_title: jobTitle };
            document.getElementById('selectedQueueLabel').textContent = `For: ${jobTitle}`;
            document.getElementById('addCandidateFromListBtn').disabled = false;

            try {
                const r = await fetch(`/api/hiring-queue/${itemId}/candidates`, { headers: { 'Accept': 'application/json' } });
                const data = await r.json();
                candidateItems = data.candidates || [];
                renderCandidateList();
            } catch (e) {
                console.error(e);
            }
        }

        function resetCandidatesPane() {
            selectedQueueItem = null;
            candidateItems = [];
            document.getElementById('selectedQueueLabel').textContent = 'Select a hiring queue item to view candidates.';
            document.getElementById('addCandidateFromListBtn').disabled = true;
            renderCandidateList();
        }

        function renderCandidateList() {
            const tbody = document.getElementById('candidateListBody');
            document.getElementById('candidateCount').textContent = candidateItems.length;

            if (!selectedQueueItem) {
                tbody.innerHTML = '<tr><td colspan="7" class="empty-state">Select a queue item to load candidates.</td></tr>';
                return;
            }

            if (!candidateItems.length) {
                tbody.innerHTML = '<tr><td colspan="7" class="empty-state">No candidates added yet for this job.</td></tr>';
                return;
            }

            tbody.innerHTML = candidateItems.map(candidate => {
                const options = candidateStatuses.map(status => `<option value="${status}" ${candidate.status === status ? 'selected' : ''}>${formatStatusLabel(status)}</option>`).join('');
                const interviewDate = candidate.interview_date || '—';
                const notes = candidate.notes ? escapeHtml(candidate.notes) : '—';
                return `
                    <tr>
                        <td>${escapeHtml(candidate.name)}</td>
                        <td>${escapeHtml(candidate.email)}</td>
                        <td>${candidate.phone ? escapeHtml(candidate.phone) : '—'}</td>
                        <td>${interviewDate}</td>
                        <td>
                            <select class="candidate-status-select" data-candidate-id="${candidate.id}">
                                ${options}
                            </select>
                        </td>
                        <td>${notes}</td>
                        <td>
                            <button type="button" class="candidate-edit-btn" data-candidate-id="${candidate.id}">
                                Edit
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');

            tbody.querySelectorAll('.candidate-status-select').forEach(select => {
                select.addEventListener('change', async (e) => {
                    if (!selectedQueueItem) return;
                    const candidateId = parseInt(e.target.dataset.candidateId);
                    await updateCandidateStatus(selectedQueueItem.id, candidateId, e.target.value);
                });
            });
            tbody.querySelectorAll('.candidate-edit-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const candidateId = parseInt(e.currentTarget.dataset.candidateId);
                    openEditCandidateModal(candidateId);
                });
            });
        }

        async function updateCandidateStatus(itemId, candidateId, status) {
            try {
                const r = await fetch(`/api/hiring-queue/${itemId}/candidates/${candidateId}/status`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ status })
                });
                if (!r.ok && selectedQueueItem) {
                    await loadCandidates(selectedQueueItem.id, selectedQueueItem.job_title);
                }
            } catch (e) {
                console.error(e);
                if (selectedQueueItem) {
                    await loadCandidates(selectedQueueItem.id, selectedQueueItem.job_title);
                }
            }
        }

        function escapeHtml(s) {
            return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
        }

        function formatStatusLabel(status) {
            return status.charAt(0).toUpperCase() + status.slice(1);
        }

        function getSourceBadgeData(source) {
            if (source === 'sales_rep') {
                return { className: 'sales_rep', label: 'Sales Rep' };
            }
            if (source === 'client') {
                return { className: 'client', label: 'Client' };
            }
            return { className: 'generic', label: escapeHtml(source || 'Client') };
        }

        function openAddCandidateModal(itemId, jobTitle, options = {}) {
            const fromCandidatesModal = Boolean(options.fromCandidatesModal);

            if (fromCandidatesModal && isCandidatesModalOpen()) {
                reopenCandidatesAfterAddModal = true;
                closeCandidatesModal(true);
            } else {
                reopenCandidatesAfterAddModal = false;
            }

            document.getElementById('addCandidateItemId').value = itemId;
            document.getElementById('addCandidateJobTitle').textContent = jobTitle;
            document.getElementById('addCandidateForm').reset();
            document.getElementById('addCandidateItemId').value = itemId;
            document.getElementById('candidateStatus').value = 'pending';
            document.getElementById('addCandidateModal').classList.add('open');
        }

        function openEditCandidateModal(candidateId) {
            if (!selectedQueueItem) return;
            const candidate = candidateItems.find(item => item.id === candidateId);
            if (!candidate) return;

            document.getElementById('editCandidateItemId').value = selectedQueueItem.id;
            document.getElementById('editCandidateId').value = candidate.id;
            document.getElementById('editCandidateJobTitle').textContent = selectedQueueItem.job_title || '—';
            document.getElementById('editCandidateName').value = candidate.name || '';
            document.getElementById('editCandidateEmail').value = candidate.email || '';
            document.getElementById('editCandidatePhone').value = candidate.phone || '';
            document.getElementById('editCandidateInterviewDate').value = candidate.interview_date || '';
            document.getElementById('editCandidateNotes').value = candidate.notes || '';
            document.getElementById('editCandidateStatus').value = candidate.status || 'pending';
            document.getElementById('editCandidateModal').classList.add('open');
        }

        function closeAddCandidateModal() {
            document.getElementById('addCandidateModal').classList.remove('open');
            if (reopenCandidatesAfterAddModal && selectedQueueItem) {
                const { id, job_title: jobTitle } = selectedQueueItem;
                reopenCandidatesAfterAddModal = false;
                openCandidatesModal(id, jobTitle);
                return;
            }
            reopenCandidatesAfterAddModal = false;
        }

        function closeEditCandidateModal() {
            document.getElementById('editCandidateModal').classList.remove('open');
        }

        function closeViewJobModal() {
            document.getElementById('viewJobModal').classList.remove('open');
        }

        async function viewJob(id) {
            try {
                const r = await fetch(`/api/hiring-queue/${id}`, { headers: { 'Accept': 'application/json' } });
                const data = await r.json();
                document.getElementById('viewJobTitle').textContent = data.item.job_title;
                document.getElementById('viewJobDescription').textContent = data.item.full_description;
                document.getElementById('viewJobPdfLink').href = `${CFG.baseUrl || "/api/hiring-queue"}/${id}/pdf`;
                document.getElementById('viewJobModal').classList.add('open');
            } catch (e) {
                console.error(e);
            }
        }

        document.getElementById('addCandidateForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const itemId = document.getElementById('addCandidateItemId').value;
            const payload = {
                name: document.getElementById('candidateName').value,
                email: document.getElementById('candidateEmail').value,
                phone: document.getElementById('candidatePhone').value || null,
                interview_date: document.getElementById('candidateInterviewDate').value || null,
                notes: document.getElementById('candidateNotes').value || null,
                status: document.getElementById('candidateStatus').value,
            };
            try {
                const r = await fetch(`/api/hiring-queue/${itemId}/candidates`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify(payload)
                });
                if (r.ok) {
                    closeAddCandidateModal();
                    await loadQueue(queueCurrentPage);
                }
            } catch (e) {
                console.error(e);
            }
        });
        document.getElementById('editCandidateForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const itemId = document.getElementById('editCandidateItemId').value;
            const candidateId = document.getElementById('editCandidateId').value;
            const payload = {
                name: document.getElementById('editCandidateName').value,
                email: document.getElementById('editCandidateEmail').value,
                phone: document.getElementById('editCandidatePhone').value || null,
                interview_date: document.getElementById('editCandidateInterviewDate').value || null,
                notes: document.getElementById('editCandidateNotes').value || null,
                status: document.getElementById('editCandidateStatus').value,
            };
            try {
                const r = await fetch(`/api/hiring-queue/${itemId}/candidates/${candidateId}`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify(payload)
                });
                if (r.ok && selectedQueueItem) {
                    closeEditCandidateModal();
                    await loadCandidates(selectedQueueItem.id, selectedQueueItem.job_title);
                    await loadQueue(queueCurrentPage);
                }
            } catch (e) {
                console.error(e);
            }
        });

        function openEditQueueModal(itemId) {
            const item = queueItems.find(i => i.id === itemId);
            if (!item) return;
            document.getElementById('editQueueItemId').value = item.id;
            document.getElementById('editQueueJobTitle').value = item.job_title || '';
            document.getElementById('editQueueClientEmail').value = item.client_email || '';
            document.getElementById('editQueueDescription').value = item.full_description || '';
            document.getElementById('editQueueModal').classList.add('open');
        }

        function closeEditQueueModal() {
            document.getElementById('editQueueModal').classList.remove('open');
        }

        document.getElementById('editQueueForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const itemId = document.getElementById('editQueueItemId').value;
            const submitBtn = document.getElementById('editQueueSubmitBtn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving...';
            const payload = {
                job_title: document.getElementById('editQueueJobTitle').value,
                client_email: document.getElementById('editQueueClientEmail').value || null,
                full_description: document.getElementById('editQueueDescription').value,
            };
            try {
                const r = await fetch(`${CFG.baseUrl || "/api/hiring-queue"}/${itemId}`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify(payload)
                });
                if (r.ok) {
                    closeEditQueueModal();
                    await loadQueue(queueCurrentPage);
                } else {
                    const data = await r.json().catch(() => ({}));
                    alert(data.message || 'Could not save changes. Please try again.');
                }
            } catch (err) {
                console.error(err);
            }
            submitBtn.disabled = false;
            submitBtn.textContent = 'Save Changes';
        });

        document.getElementById('editQueueModal').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) closeEditQueueModal();
        });

        document.getElementById('addCandidateModal').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) closeAddCandidateModal();
        });
        document.getElementById('editCandidateModal').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) closeEditCandidateModal();
        });
        document.getElementById('commentsModal').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) closeCommentsModal();
        });
        document.getElementById('addQueueCommentBtn').addEventListener('click', addQueueComment);
        document.getElementById('queueCommentsList').addEventListener('click', async (e) => {
            const btn = e.target.closest('.comment-delete-btn');
            if (!btn || !activeCommentsItemId) return;
            const commentId = parseInt(btn.dataset.commentId, 10);
            if (Number.isNaN(commentId)) return;
            await deleteQueueComment(activeCommentsItemId, commentId);
        });
        document.getElementById('queueCommentTextarea').addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                addQueueComment();
            }
        });
        document.getElementById('viewJobModal').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) closeViewJobModal();
        });
        document.getElementById('candidatesModal').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) closeCandidatesModal();
        });
        document.getElementById('addCandidateFromListBtn').addEventListener('click', () => {
            if (!selectedQueueItem) return;
            openAddCandidateModal(selectedQueueItem.id, selectedQueueItem.job_title, { fromCandidatesModal: true });
        });
        document.getElementById('queuePrevBtn').addEventListener('click', () => {
            if (queueCurrentPage > 1) loadQueue(queueCurrentPage - 1);
        });
        document.getElementById('queueNextBtn').addEventListener('click', () => {
            if (queueCurrentPage < queueTotalPages) loadQueue(queueCurrentPage + 1);
        });

        loadQueue();

    if (typeof closeAddCandidateModal === 'function') window.closeAddCandidateModal = closeAddCandidateModal;
    if (typeof closeCandidatesModal === 'function') window.closeCandidatesModal = closeCandidatesModal;
    if (typeof closeCommentsModal === 'function') window.closeCommentsModal = closeCommentsModal;
    if (typeof closeEditCandidateModal === 'function') window.closeEditCandidateModal = closeEditCandidateModal;
    if (typeof closeEditQueueModal === 'function') window.closeEditQueueModal = closeEditQueueModal;
    if (typeof closeViewJobModal === 'function') window.closeViewJobModal = closeViewJobModal;
})();
