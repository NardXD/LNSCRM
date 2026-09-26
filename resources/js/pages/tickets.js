/* Vite page entry — IIFE preserves onclick globals */
(function () {
const CFG = window.__ticketsConfig || {};
    const API_BASE = CFG.apiBase || '/api/tickets';
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    let ticketsData = [];
    let currentPage = 1;
    let pagination = { last_page: 1, total: 0 };
    const itemsPerPage = 10;
    let activeStatusTab = 'open';
    let activeViewFilter = 'all';
    let searchQuery = '';
    let priorityFilter = 'all';
    let currentTicketId = null;
    let searchTimeout = null;

    function getFilteredTickets() {
        return ticketsData;
    }

    async function fetchTickets() {
        try {
            const params = new URLSearchParams({
                page: currentPage,
                per_page: itemsPerPage,
                status: activeStatusTab,
                priority: priorityFilter,
            });
            if (activeViewFilter === 'assigned-to-me') params.set('assigned_to_me', '1');
            if (searchQuery) params.set('search', searchQuery);
            const res = await fetch(`${API_BASE}?${params}`);
            const json = await res.json();
            if (!json.success) throw new Error(json.message || 'Failed to load tickets');
            ticketsData = json.data;
            pagination = json.pagination;
            if (json.stats) updateStats(json.stats);
            updateView();
        } catch (err) {
            console.error(err);
            ticketsData = [];
            updateView();
        }
    }

    function updateStats(stats) {
        const setCount = (id, value) => {
            const el = document.getElementById(id);
            if (el) el.textContent = value ?? 0;
        };
        setCount('tabCountOpen', stats.open);
        setCount('tabCountInProgress', stats.in_progress);
        setCount('tabCountPending', stats.pending);
        setCount('tabCountResolved', stats.resolved);
        setCount('tabCountClosed', stats.closed);
    }

    // Render Functions
    function renderTable() {
        const tbody = document.getElementById('ticketsTableBody');
        const pageData = getFilteredTickets();
        if (!pageData.length) {
            tbody.innerHTML = '<tr><td colspan="9" class="empty-state">No tickets found.</td></tr>';
            return;
        }

        tbody.innerHTML = pageData.map(ticket => `
            <tr class="is-clickable" onclick="openTicketModal(${ticket.id})">
                <td><strong>${ticket.ticketId}</strong></td>
                <td>${ticket.subject}</td>
                <td>${ticket.client}</td>
                <td>
                    <div class="employee-cell">
                        <div class="employee-avatar">${ticket.assignedTo.initials}</div>
                        <span>${ticket.assignedTo.name}</span>
                    </div>
                </td>
                <td><span class="priority-badge ${ticket.priority}">${ticket.priority.charAt(0).toUpperCase() + ticket.priority.slice(1)}</span></td>
                <td><span class="status-badge ${ticket.status}">${ticket.status.replace('-', ' ').split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ')}</span></td>
                <td><span class="sla-badge ${ticket.sla}">${ticket.sla.charAt(0).toUpperCase() + ticket.sla.slice(1)}</span></td>
                <td>${ticket.created}</td>
                <td onclick="event.stopPropagation()">
                    <div class="table-actions">
                        <button class="icon-btn" title="View" onclick="openTicketModal(${ticket.id})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    function renderCards() {
        const container = document.getElementById('ticketsCards');
        const pageData = getFilteredTickets();
        if (!pageData.length) {
            container.innerHTML = '<div class="empty-state">No tickets found.</div>';
            return;
        }

        container.innerHTML = pageData.map(ticket => `
            <div class="ticket-card" onclick="openTicketModal(${ticket.id})">
                <div class="card-header">
                    <div>
                        <div class="card-title">${ticket.ticketId}</div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">${ticket.subject}</div>
                    </div>
                    <span class="status-badge ${ticket.status}">${ticket.status.replace('-', ' ').split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ')}</span>
                </div>
                <div class="card-details">
                    <div class="card-detail">
                        <span class="card-label">Client</span>
                        <span class="card-value">${ticket.client}</span>
                    </div>
                    <div class="card-detail">
                        <span class="card-label">Assigned To</span>
                        <span class="card-value">${ticket.assignedTo.name}</span>
                    </div>
                    <div class="card-detail">
                        <span class="card-label">Priority</span>
                        <span class="card-value"><span class="priority-badge ${ticket.priority}">${ticket.priority.charAt(0).toUpperCase() + ticket.priority.slice(1)}</span></span>
                    </div>
                    <div class="card-detail">
                        <span class="card-label">SLA</span>
                        <span class="card-value"><span class="sla-badge ${ticket.sla}">${ticket.sla.charAt(0).toUpperCase() + ticket.sla.slice(1)}</span></span>
                    </div>
                </div>
            </div>
        `).join('');
    }

    function renderPagination() {
        const totalPages = pagination.last_page || 1;
        const total = pagination.total || 0;

        const info = document.getElementById('paginationInfo');
        const numbers = document.getElementById('paginationNumbers');
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');

        const start = total ? (currentPage - 1) * itemsPerPage + 1 : 0;
        const end = Math.min(currentPage * itemsPerPage, total);
        info.textContent = total ? `Showing ${start}–${end} of ${total}` : 'Showing 0 of 0';

        prevBtn.disabled = currentPage === 1;
        nextBtn.disabled = currentPage >= totalPages;

        let html = '';
        const maxVisible = 5;
        let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
        let endPage = Math.min(totalPages, startPage + maxVisible - 1);

        if (endPage - startPage < maxVisible - 1) {
            startPage = Math.max(1, endPage - maxVisible + 1);
        }

        if (startPage > 1) {
            html += `<button class="pagination-number" data-page="1">1</button>`;
            if (startPage > 2) html += `<span class="pagination-number ellipsis">...</span>`;
        }

        for (let i = startPage; i <= endPage; i++) {
            html += `<button class="pagination-number ${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</button>`;
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) html += `<span class="pagination-number ellipsis">...</span>`;
            html += `<button class="pagination-number" data-page="${totalPages}">${totalPages}</button>`;
        }

        numbers.innerHTML = html;
        numbers.querySelectorAll('.pagination-number:not(.ellipsis)').forEach(btn => {
            btn.addEventListener('click', () => {
                currentPage = parseInt(btn.dataset.page);
                fetchTickets();
            });
        });
    }

    function updateView() {
        if (window.innerWidth <= 768) {
            renderCards();
        } else {
            renderTable();
        }
        renderPagination();
    }

    // Event Listeners
    document.getElementById('nextBtn').addEventListener('click', () => {
        if (currentPage < pagination.last_page) {
            currentPage++;
            fetchTickets();
        }
    });

    document.getElementById('prevBtn').addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            fetchTickets();
        }
    });

    document.getElementById('ticketSearch').addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            searchQuery = document.getElementById('ticketSearch').value.trim();
            currentPage = 1;
            fetchTickets();
        }, 300);
    });

    document.getElementById('priorityFilter').addEventListener('change', function() {
        priorityFilter = this.value;
        currentPage = 1;
        fetchTickets();
    });

    document.querySelectorAll('.view-submenu-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.view-submenu-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            activeViewFilter = tab.dataset.view;
            currentPage = 1;
            fetchTickets();
        });
    });

    document.querySelectorAll('.status-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.status-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            activeStatusTab = tab.dataset.status;
            currentPage = 1;
            fetchTickets();
        });
    });

    // Ticket Modal
    async function openTicketModal(ticketId) {
        currentTicketId = ticketId;
        const ticket = ticketsData.find(t => t.id === ticketId);
        if (ticket) {
            populateTicketModal(ticket);
        }
        document.getElementById('ticketModal').classList.add('active');
        document.body.style.overflow = 'hidden';

        try {
            const res = await fetch(`${API_BASE}/${ticketId}`);
            const json = await res.json();
            if (json.success && json.data) {
                populateTicketModal(json.data);
            }
        } catch (err) {
            console.error(err);
        }
    }

    function populateTicketModal(ticket) {
        const fmt = s => (s || '').replace('-', ' ').split(' ').map(w => (w || '').charAt(0).toUpperCase() + (w || '').slice(1)).join(' ');
        const el = id => document.getElementById(id);
        const set = (id, fn) => { const e = el(id); if (e) fn(e); };

        set('modalTicketId', e => e.textContent = '#' + (ticket.ticketId || ticket.ticket_number || ''));
        set('modalTicketSubject', e => e.textContent = ticket.subject || '');
        set('modalTicketClient', e => e.textContent = 'Client: ' + (ticket.client || ''));
        set('modalTicketDate', e => e.textContent = 'Created: ' + (ticket.created_at || ticket.created || ''));
        set('modalDescription', e => e.textContent = ticket.description || '');
        set('modalStatus', e => e.value = ticket.status || 'open');
        set('sidebarPriority', e => { e.textContent = fmt(ticket.priority); e.className = `priority-badge ${ticket.priority || 'medium'}`; });
        set('sidebarStatus', e => { e.textContent = fmt(ticket.status); e.className = `status-badge ${ticket.status || 'open'}`; });
        set('sidebarCategory', e => e.textContent = ticket.category ? fmt(ticket.category) : '—');
        const attSection = el('ticketAttachmentSection');
        const attImg = el('ticketAttachmentImg');
        if (ticket.image_url && attSection && attImg) {
            attSection.style.display = 'block';
            attImg.src = ticket.image_url;
        } else if (attSection) {
            attSection.style.display = 'none';
        }
        const assigned = ticket.assignedTo || { name: 'Unassigned', initials: '—' };
        set('sidebarAssignedTo', e => e.innerHTML = `<div class="employee-cell"><div class="employee-avatar">${(assigned.initials || '—')}</div><span>${(assigned.name || 'Unassigned')}</span></div>`);
        const commentsEl = el('commentsList');
        if (commentsEl) {
            if (ticket.comments) {
                commentsEl.innerHTML = ticket.comments.map(c => `
                <div class="comment-item">
                    <div class="comment-avatar">${c.initials || c.author?.slice(0,2).toUpperCase() || '—'}</div>
                    <div class="comment-content">
                        <div class="comment-header">
                            <span class="comment-author">${c.author || 'User'}</span>
                            <span class="comment-time">${c.time || ''}</span>
                        </div>
                        <div class="comment-text">${c.text || c.content || ''}</div>
                    </div>
                </div>
            `).join('');
            } else {
                commentsEl.innerHTML = '';
            }
        }

        const sla = ticket.sla_tracking || {};
        const resp = sla.response || { status: ticket.sla || 'compliant', text: '—' };
        const res = sla.resolution || { status: ticket.sla || 'compliant', text: '—' };
        const pct = status => (status === 'compliant' ? 100 : status === 'warning' ? 65 : 30);
        const statusLabel = s => (s === 'warning' ? 'At Risk' : (s || 'compliant').charAt(0).toUpperCase() + (s || '').slice(1));
        set('slaResponseStatus', e => { e.textContent = statusLabel(resp.status); e.className = `sla-status ${resp.status || 'compliant'}`; });
        set('slaResponseText', e => e.textContent = resp.text || '—');
        set('slaResponseFill', e => { e.style.width = pct(resp.status) + '%'; e.className = `sla-fill ${resp.status || 'compliant'}`; });
        set('slaResolutionStatus', e => { e.textContent = statusLabel(res.status); e.className = `sla-status ${res.status || 'compliant'}`; });
        set('slaResolutionText', e => e.textContent = res.text || '—');
        set('slaResolutionFill', e => { e.style.width = pct(res.status) + '%'; e.className = `sla-fill ${res.status || 'compliant'}`; });

        const activitiesEl = el('activityList');
        if (activitiesEl) {
            const activities = ticket.activities || [];
            activitiesEl.innerHTML = activities.length ? activities.map(a => `
                <div class="activity-item">
                    <svg class="activity-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                    </svg>
                    <div class="activity-text">${a.text || ''}</div>
                    <div class="activity-time">${a.time || ''}</div>
                </div>
            `).join('') : '<div class="activity-empty">No activity yet</div>';
        }

        const isReadOnly = ['resolved', 'closed'].includes(ticket.status || '');
        const statusSelect = el('modalStatus');
        const commentTextarea = el('commentTextarea');
        const addCommentBtn = el('addCommentBtn');
        const commentInputSection = el('commentInputSection');
        if (statusSelect) statusSelect.disabled = isReadOnly;
        if (commentTextarea) commentTextarea.disabled = isReadOnly;
        if (addCommentBtn) addCommentBtn.disabled = isReadOnly;
        if (commentInputSection) commentInputSection.classList.toggle('ticket-readonly', isReadOnly);
    }

    function closeTicketModal() {
        document.getElementById('ticketModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    function openImagePopup(clickable) {
        const img = clickable?.querySelector('img') || document.getElementById('ticketAttachmentImg');
        const overlay = document.getElementById('imagePopupOverlay');
        const popupImg = document.getElementById('imagePopupImg');
        if (img?.src && overlay && popupImg) {
            popupImg.src = img.src;
            overlay.classList.add('visible');
            document.body.style.overflow = 'hidden';
            document.addEventListener('keydown', handleImagePopupEscape);
        }
    }

    function closeImagePopup() {
        const overlay = document.getElementById('imagePopupOverlay');
        if (overlay) {
            overlay.classList.remove('visible');
            document.body.style.overflow = '';
            document.removeEventListener('keydown', handleImagePopupEscape);
        }
    }

    function handleImagePopupEscape(e) {
        if (e.key === 'Escape') closeImagePopup();
    }

    document.getElementById('ticketAttachmentClickable')?.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            openImagePopup(this);
        }
    });

    document.getElementById('ticketModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeTicketModal();
        }
    });

    document.getElementById('modalStatus').addEventListener('change', async function() {
        if (!currentTicketId || this.disabled) return;
        try {
            const res = await fetch(`${API_BASE}/${currentTicketId}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: JSON.stringify({ status: this.value })
            });
            const json = await res.json();
            if (json.success) {
                const t = ticketsData.find(x => x.id === currentTicketId);
                if (t) t.status = this.value;
                document.getElementById('sidebarStatus').textContent = this.value.replace('-', ' ').split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
                document.getElementById('sidebarStatus').className = `status-badge ${this.value}`;
            }
        } catch (err) { console.error(err); }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeTicketModal();
            closeNewTicketModal();
        }
    });

    // New Ticket Modal
    function closeNewTicketModal() {
        document.getElementById('newTicketModal').classList.remove('active');
        document.body.style.overflow = document.getElementById('ticketModal').classList.contains('active') ? 'hidden' : '';
    }

    document.getElementById('newTicketModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeNewTicketModal();
        }
    });

    async function addComment() {
        const textarea = document.getElementById('commentTextarea');
        const text = textarea.value.trim();
        if (!text || !currentTicketId) return;

        try {
            const res = await fetch(`${API_BASE}/${currentTicketId}/comments`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
                body: JSON.stringify({ content: text })
            });
            const json = await res.json();
            if (json.success && json.data) {
                const c = json.data;
                const commentsList = document.getElementById('commentsList');
                const div = document.createElement('div');
                div.className = 'comment-item';
                div.innerHTML = `
                    <div class="comment-avatar">${c.initials || 'ME'}</div>
                    <div class="comment-content">
                        <div class="comment-header">
                            <span class="comment-author">${c.author || 'You'}</span>
                            <span class="comment-time">${c.created_at || 'Just now'}</span>
                        </div>
                        <div class="comment-text">${c.content}</div>
                    </div>
                `;
                commentsList.appendChild(div);
                textarea.value = '';
                const activityList = document.getElementById('activityList');
                if (activityList && !activityList.querySelector('.activity-empty')) {
                    const item = document.createElement('div');
                    item.className = 'activity-item';
                    item.innerHTML = `
                        <svg class="activity-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                        </svg>
                        <div class="activity-text">${c.author || 'You'} commented</div>
                        <div class="activity-time">${c.created_at || 'Just now'}</div>
                    `;
                    activityList.insertBefore(item, activityList.firstChild);
                } else if (activityList) {
                    activityList.innerHTML = `
                        <div class="activity-item">
                            <svg class="activity-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                            </svg>
                            <div class="activity-text">${c.author || 'You'} commented</div>
                            <div class="activity-time">${c.created_at || 'Just now'}</div>
                        </div>
                    `;
                }
            }
        } catch (err) { console.error(err); }
    }

    // Functions
    async function createTicket() {
        document.getElementById('newTicketModal').classList.add('active');
        document.body.style.overflow = 'hidden';

        const clientSelect = document.getElementById('newTicketClient');
        const assignedSelect = document.getElementById('newTicketAssignedTo');

        clientSelect.innerHTML = '<option value="">Select client</option>';
        assignedSelect.innerHTML = '<option value="">Unassigned</option>';

        try {
            const res = await fetch(`${API_BASE}/form-data`);
            const json = await res.json();
            if (json.success && json.data) {
                const { clients, employees } = json.data;
                if (clients && clients.length > 0) {
                    clients.forEach(c => {
                        const opt = document.createElement('option');
                        opt.value = c.id;
                        opt.textContent = c.name;
                        clientSelect.appendChild(opt);
                    });
                } else {
                    const opt = document.createElement('option');
                    opt.value = '';
                    opt.textContent = 'No clients yet';
                    opt.disabled = true;
                    clientSelect.appendChild(opt);
                }
                if (employees && employees.length > 0) {
                    employees.forEach(u => {
                        const opt = document.createElement('option');
                        opt.value = u.id;
                        opt.textContent = u.name;
                        assignedSelect.appendChild(opt);
                    });
                } else {
                    const opt = document.createElement('option');
                    opt.value = '';
                    opt.textContent = 'No employees yet';
                    opt.disabled = true;
                    assignedSelect.appendChild(opt);
                }
            }
        } catch (err) {
            console.error(err);
        }

        document.getElementById('newTicketSubject').value = '';
        document.getElementById('newTicketDescription').value = '';
        clientSelect.value = '';
        assignedSelect.value = '';
        document.getElementById('newTicketPriority').value = 'medium';
        document.getElementById('newTicketCategory').value = '';
        removeTicketImage();
    }

    function previewTicketImage(input) {
        const placeholder = document.getElementById('imageUploadPlaceholder');
        const preview = document.getElementById('imagePreview');
        const previewImg = document.getElementById('imagePreviewImg');
        const file = input.files && input.files[0];
        if (file) {
            if (!file.type.startsWith('image/')) {
                alert('Please select an image file (PNG, JPG, GIF).');
                input.value = '';
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                alert('Image must be under 5MB.');
                input.value = '';
                return;
            }
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImg.src = e.target.result;
                placeholder.style.display = 'none';
                preview.style.display = 'flex';
            };
            reader.readAsDataURL(file);
        } else {
            removeTicketImage();
        }
    }

    function removeTicketImage() {
        const input = document.getElementById('newTicketImage');
        const placeholder = document.getElementById('imageUploadPlaceholder');
        const preview = document.getElementById('imagePreview');
        input.value = '';
        placeholder.style.display = 'flex';
        preview.style.display = 'none';
        const img = document.getElementById('imagePreviewImg');
        if (img) img.src = '';
    }

    function handleImageDragOver(e) {
        e.preventDefault();
        e.stopPropagation();
        e.currentTarget.classList.add('image-upload-dragover');
    }

    function handleImageDragLeave(e) {
        e.preventDefault();
        e.stopPropagation();
        e.currentTarget.classList.remove('image-upload-dragover');
    }

    function handleImageDrop(e) {
        e.preventDefault();
        e.stopPropagation();
        e.currentTarget.classList.remove('image-upload-dragover');
        const files = e.dataTransfer.files;
        if (files.length && files[0].type.startsWith('image/')) {
            const input = document.getElementById('newTicketImage');
            const dt = new DataTransfer();
            dt.items.add(files[0]);
            input.files = dt.files;
            previewTicketImage(input);
        }
    }

    async function submitNewTicket(event) {
        event.preventDefault();
        const form = document.getElementById('newTicketForm');
        const formData = new FormData(form);

        try {
            const res = await fetch(API_BASE, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN
                },
                body: formData
            });
            const json = await res.json();
            if (json.success) {
                closeNewTicketModal();
                currentPage = 1;
                fetchTickets();
            } else {
                alert(json.message || 'Failed to create ticket.');
            }
        } catch (err) {
            console.error(err);
            alert('Failed to create ticket.');
        }
    }

    // Window Resize Handler
    window.addEventListener('resize', updateView);

    // Initialize
    fetchTickets();

    if (typeof addComment === 'function') window.addComment = addComment;
    if (typeof closeImagePopup === 'function') window.closeImagePopup = closeImagePopup;
    if (typeof closeNewTicketModal === 'function') window.closeNewTicketModal = closeNewTicketModal;
    if (typeof closeTicketModal === 'function') window.closeTicketModal = closeTicketModal;
    if (typeof createTicket === 'function') window.createTicket = createTicket;
    if (typeof getElementById === 'function') window.getElementById = getElementById;
    if (typeof openImagePopup === 'function') window.openImagePopup = openImagePopup;
    if (typeof openTicketModal === 'function') window.openTicketModal = openTicketModal;
    if (typeof previewTicketImage === 'function') window.previewTicketImage = previewTicketImage;
    if (typeof removeTicketImage === 'function') window.removeTicketImage = removeTicketImage;
    if (typeof stopPropagation === 'function') window.stopPropagation = stopPropagation;
})();
