/* Vite page entry — IIFE preserves onclick globals */
(function () {
let currentYear = new Date().getFullYear();
    let myCreditsData = {}; // Store credits data for the current user
    
    // Permissions from server
    const CFG = window.__leaveManagementConfig || {};
    const permissions = CFG.permissions || {};

    // Calendar functions
    let currentCalendarMonth = new Date().getMonth() + 1;
    let currentCalendarYear = new Date().getFullYear();

    async function loadCalendar() {
        const month = document.getElementById('calendarMonth').value;
        const year = document.getElementById('calendarYear').value;
        
        try {
            const response = await fetch(`/api/leave-management/calendar?year=${year}&month=${month}`);
            const data = await response.json();
            
            if (data.success) {
                renderCalendar(data.data, parseInt(year), parseInt(month));
            }
        } catch (error) {
            console.error('Error loading calendar:', error);
        }
    }

    function renderCalendar(calendarData, year, month) {
        const grid = document.getElementById('calendarGrid');
        const firstDay = new Date(year, month - 1, 1);
        const lastDay = new Date(year, month, 0);
        const startDate = new Date(firstDay);
        startDate.setDate(startDate.getDate() - startDate.getDay()); // Start from Sunday
        
        grid.innerHTML = '';
        
        // Generate 42 days (6 weeks)
        for (let i = 0; i < 42; i++) {
            const currentDate = new Date(startDate);
            currentDate.setDate(startDate.getDate() + i);
            
            const dateStr = currentDate.toISOString().split('T')[0];
            const dayNumber = currentDate.getDate();
            const isCurrentMonth = currentDate.getMonth() === month - 1;
            const count = calendarData[dateStr] || 0;
            
            const dayElement = document.createElement('div');
            dayElement.className = `calendar-day ${!isCurrentMonth ? 'other-month' : ''} ${count > 0 ? 'has-leaves' : ''}`;
            dayElement.onclick = () => openEmployeesOnLeaveModal(dateStr);
            
            dayElement.innerHTML = `
                <div class="calendar-day-number">${dayNumber}</div>
                ${count > 0 ? `<div class="calendar-day-leave-badge">${count} on leave</div>` : ''}
            `;
            
            grid.appendChild(dayElement);
        }
    }

    function previousMonth() {
        let month = parseInt(document.getElementById('calendarMonth').value);
        let year = parseInt(document.getElementById('calendarYear').value);
        
        month--;
        if (month < 1) {
            month = 12;
            year--;
        }
        
        document.getElementById('calendarMonth').value = String(month).padStart(2, '0');
        document.getElementById('calendarYear').value = year;
        loadCalendar();
    }

    function nextMonth() {
        let month = parseInt(document.getElementById('calendarMonth').value);
        let year = parseInt(document.getElementById('calendarYear').value);
        
        month++;
        if (month > 12) {
            month = 1;
            year++;
        }
        
        document.getElementById('calendarMonth').value = String(month).padStart(2, '0');
        document.getElementById('calendarYear').value = year;
        loadCalendar();
    }

    async function openEmployeesOnLeaveModal(date) {
        const modal = document.getElementById('employeesOnLeaveModal');
        const title = document.getElementById('employeesOnLeaveTitle');
        const list = document.getElementById('employeesOnLeaveList');
        
        const dateObj = new Date(date);
        title.textContent = `Employees on Leave - ${dateObj.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}`;
        
        list.innerHTML = '<div class="empty-state"><div class="empty-state-icon">👥</div><p>Loading...</p></div>';
        modal.classList.add('active');
        
        try {
            const response = await fetch(`/api/leave-management/employees-on-leave?date=${date}`);
            const data = await response.json();
            
            if (data.success) {
                if (data.data.length === 0) {
                    list.innerHTML = '<div class="empty-state"><div class="empty-state-icon">👥</div><p>No employees on leave for this date</p></div>';
                } else {
                    list.innerHTML = `
                        <div style="margin-bottom: 1rem; color: var(--text-secondary); font-size: 0.8125rem;">
                            ${data.count} employee(s) on leave
                        </div>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Leave Type</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Days</th>
                                        <th>Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${data.data.map(emp => `
                                        <tr>
                                            <td>${emp.name}</td>
                                            <td>${emp.leave_type_label}</td>
                                            <td>${new Date(emp.start_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</td>
                                            <td>${new Date(emp.end_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</td>
                                            <td>${emp.days_requested}</td>
                                            <td>${emp.reason || '-'}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    `;
                }
            }
        } catch (error) {
            console.error('Error loading employees on leave:', error);
            list.innerHTML = '<div class="empty-state"><div class="empty-state-icon">⚠️</div><p>Error loading employees on leave</p></div>';
        }
    }

    function closeEmployeesOnLeaveModal() {
        document.getElementById('employeesOnLeaveModal').classList.remove('active');
    }

    // Leave Request Details Modal
    const leaveManagementBaseUrl = CFG.leaveRequestsUrl || '/api/leave-management/leave-requests';

    function openLeaveRequestDetailsModal(event, requestId) {
        const request = (window.leaveRequestsData || []).find(r => r.id === requestId);
        if (!request) return;

        const content = document.getElementById('leaveRequestDetailsContent');
        const actions = document.getElementById('leaveRequestDetailsActions');

        const attachmentViewUrl = `${leaveManagementBaseUrl}/${request.id}/attachment`;
        const ext = (request.attachment_filename || '').split('.').pop()?.toLowerCase() || '';
        const isImage = ['jpg', 'jpeg', 'png'].includes(ext);
        const isPdf = ext === 'pdf';
        const attachmentHtml = request.attachment_path
            ? `<div class="credits-item"><span class="credits-label">Attachment:</span><span class="credits-value"><div class="attachment-preview">${isImage ? `<img src="${attachmentViewUrl}" alt="Attachment" class="attachment-thumb" onclick="event.stopPropagation(); openAttachmentFullscreen('${attachmentViewUrl}')">` : isPdf ? `<a href="${attachmentViewUrl}" target="_blank" rel="noopener" class="attachment-preview-pdf">View PDF</a>` : `<a href="${attachmentViewUrl}" class="btn btn-secondary btn-sm" style="text-decoration: none;">Download</a>`}</div></span></div>`
            : '';

        content.innerHTML = `
            <div class="credits-item"><span class="credits-label">Employee:</span><span class="credits-value">${request.user_name}</span></div>
            <div class="credits-item"><span class="credits-label">Email:</span><span class="credits-value">${request.user_email}</span></div>
            <div class="credits-item"><span class="credits-label">Leave Type:</span><span class="credits-value">${request.leave_type_label}</span></div>
            <div class="credits-item"><span class="credits-label">Start Date:</span><span class="credits-value">${request.start_date_formatted}</span></div>
            <div class="credits-item"><span class="credits-label">End Date:</span><span class="credits-value">${request.end_date_formatted}</span></div>
            <div class="credits-item"><span class="credits-label">Days:</span><span class="credits-value">${request.days_requested}</span></div>
            <div class="credits-item"><span class="credits-label">Status:</span><span class="credits-value"><span class="status-badge status-${request.status}">${request.status_label}</span></span></div>
            <div class="credits-item"><span class="credits-label">Reason:</span><span class="credits-value">${request.reason || '-'}</span></div>
            ${attachmentHtml}
            ${request.rejection_reason ? `<div class="credits-item"><span class="credits-label">Rejection Reason:</span><span class="credits-value credits-used">${request.rejection_reason}</span></div>` : ''}
            ${request.approver_name ? `<div class="credits-item"><span class="credits-label">Approved by:</span><span class="credits-value">${request.approver_name}</span></div>` : ''}
            ${request.approved_at ? `<div class="credits-item"><span class="credits-label">Approved at:</span><span class="credits-value">${request.approved_at}</span></div>` : ''}
            <div class="credits-item"><span class="credits-label">Submitted:</span><span class="credits-value">${request.created_at}</span></div>
        `;

        let actionsHtml = '<button type="button" class="btn btn-secondary" onclick="closeLeaveRequestDetailsModal()">Close</button>';
        if (request.status === 'pending' && request.can_approve) {
            actionsHtml += ` <button type="button" class="btn btn-success" onclick="closeLeaveRequestDetailsModal(); approveRequest(${request.id}, 'approved')">Approve</button>`;
            actionsHtml += ` <button type="button" class="btn btn-danger" onclick="closeLeaveRequestDetailsModal(); approveRequest(${request.id}, 'rejected')">Reject</button>`;
        }
        if (request.can_cancel) {
            actionsHtml += ` <button type="button" class="btn btn-danger" onclick="closeLeaveRequestDetailsModal(); openCancelConfirmModal(${request.id})">Cancel Request</button>`;
        }
        actions.innerHTML = actionsHtml;

        document.getElementById('leaveRequestDetailsModal').classList.add('active');
    }

    function closeLeaveRequestDetailsModal() {
        document.getElementById('leaveRequestDetailsModal').classList.remove('active');
    }

    function openAttachmentFullscreen(src) {
        const overlay = document.getElementById('attachmentFullscreen');
        const img = document.getElementById('attachmentFullscreenImg');
        img.src = src;
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeAttachmentFullscreen(event) {
        if (event && event.target.id !== 'attachmentFullscreen' && !event.target.classList.contains('attachment-fullscreen-close')) {
            return;
        }
        document.getElementById('attachmentFullscreen').classList.remove('active');
        document.getElementById('attachmentFullscreenImg').src = '';
        document.body.style.overflow = '';
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.getElementById('attachmentFullscreen').classList.contains('active')) {
            closeAttachmentFullscreen({ target: document.getElementById('attachmentFullscreen') });
        }
    });

    // Load initial data
    document.addEventListener('DOMContentLoaded', function() {
        // Set current month and year in calendar selectors
        const now = new Date();
        document.getElementById('calendarMonth').value = String(now.getMonth() + 1).padStart(2, '0');
        document.getElementById('calendarYear').value = now.getFullYear();
        
        if (permissions.viewStats) {
            loadStats();
        }
        loadLeaveRequests();
        if (permissions.manageCredits || permissions.viewCredits) {
            loadAvailableUsers();
        }
    });

    // Tab switching
    function switchTab(tab) {
        document.querySelectorAll('.main-tab-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        
        if (tab === 'requests') {
            const requestsBtn = document.querySelector('.main-tab-btn');
            if (requestsBtn) {
                requestsBtn.classList.add('active');
            }
            document.getElementById('requestsTab').classList.add('active');
            loadLeaveRequests(document.querySelector('.status-tab-btn.active')?.dataset.status || 'pending');
        } else if (tab === 'calendar') {
            if (!permissions.viewCalendar) {
                return;
            }
            // Find the calendar button by its onclick attribute
            const calendarBtn = Array.from(document.querySelectorAll('.main-tab-btn')).find(btn => 
                btn.getAttribute('onclick') === "switchTab('calendar')"
            );
            if (calendarBtn) {
                calendarBtn.classList.add('active');
            }
            document.getElementById('calendarTab').classList.add('active');
            loadCalendar();
        } else if (tab === 'credits') {
            if (!permissions.viewCredits && !permissions.manageCredits) {
                return;
            }
            // Find the credits button by its onclick attribute
            const creditsBtn = Array.from(document.querySelectorAll('.main-tab-btn')).find(btn => 
                btn.getAttribute('onclick') === "switchTab('credits')"
            );
            if (creditsBtn) {
                creditsBtn.classList.add('active');
            }
            document.getElementById('creditsTab').classList.add('active');
            loadLeaveCredits();
        }
    }

    // Load stats
    async function loadStats() {
        if (!permissions.viewStats) {
            return;
        }
        
        try {
            const response = await fetch('/api/leave-management/stats?year=' + currentYear);
            const data = await response.json();
            
            if (data.success) {
                document.getElementById('statPending').textContent = data.data.pending_requests;
                document.getElementById('statApproved').textContent = data.data.approved_requests;
                document.getElementById('statTotalCredits').textContent = data.data.total_credits;
                document.getElementById('statAvailableCredits').textContent = data.data.available_credits;
            }
        } catch (error) {
            console.error('Error loading stats:', error);
        }
    }

    // Switch status tab and load leave requests
    function switchStatusTab(status) {
        document.querySelectorAll('.status-tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.status === status);
        });
        leaveRequestsCurrentPage = 1;
        loadLeaveRequests(status, 1);
    }

    // Load leave requests
    let leaveRequestsCurrentPage = 1;

    async function loadLeaveRequests(status, page) {
        const statusParam = status !== undefined ? status : (document.querySelector('.status-tab-btn.active')?.dataset.status || 'pending');
        const pageParam = page !== undefined ? page : leaveRequestsCurrentPage;
        leaveRequestsCurrentPage = pageParam;
        try {
            const url = `/api/leave-management/leave-requests?status=${statusParam}&page=${pageParam}&per_page=10`;
            
            const response = await fetch(url);
            const data = await response.json();
            
            if (data.success) {
                renderLeaveRequests(data.data);
                if (data.pagination) {
                    updateLeaveRequestsPagination(data.pagination);
                }
            }
        } catch (error) {
            console.error('Error loading leave requests:', error);
        }
    }

    function updateLeaveRequestsPagination(pagination) {
        const container = document.getElementById('leaveRequestsPagination');
        const infoEl = document.getElementById('leaveRequestsPaginationInfo');
        const numbersEl = document.getElementById('leaveRequestsPaginationNumbers');
        const prevBtn = document.getElementById('leaveRequestsPrevBtn');
        const nextBtn = document.getElementById('leaveRequestsNextBtn');

        if (!container || !pagination) return;

        if (pagination.total === 0) {
            container.style.display = 'none';
            return;
        }
        container.style.display = 'flex';

        const { current_page, last_page, total, from, to } = pagination;
        infoEl.textContent = `Showing ${from} to ${to} of ${total} results`;

        prevBtn.disabled = current_page <= 1;
        nextBtn.disabled = current_page >= last_page || last_page === 0;

        prevBtn.onclick = () => { if (current_page > 1) loadLeaveRequests(undefined, current_page - 1); };
        nextBtn.onclick = () => { if (current_page < last_page) loadLeaveRequests(undefined, current_page + 1); };

        let html = '';
        const maxVisible = 5;
        let startPage = Math.max(1, current_page - Math.floor(maxVisible / 2));
        let endPage = Math.min(last_page, startPage + maxVisible - 1);
        if (endPage - startPage < maxVisible - 1) startPage = Math.max(1, endPage - maxVisible + 1);

        if (startPage > 1) {
            html += `<button class="pagination-number" data-page="1">1</button>`;
            if (startPage > 2) html += `<span class="pagination-number ellipsis">...</span>`;
        }
        for (let i = startPage; i <= endPage; i++) {
            html += `<button class="pagination-number ${i === current_page ? 'active' : ''}" data-page="${i}">${i}</button>`;
        }
        if (endPage < last_page) {
            if (endPage < last_page - 1) html += `<span class="pagination-number ellipsis">...</span>`;
            html += `<button class="pagination-number" data-page="${last_page}">${last_page}</button>`;
        }
        numbersEl.innerHTML = html;

        numbersEl.querySelectorAll('.pagination-number[data-page]').forEach(btn => {
            btn.addEventListener('click', () => {
                const page = parseInt(btn.dataset.page, 10);
                if (page && page !== current_page) loadLeaveRequests(undefined, page);
            });
        });
    }

    // Render leave requests
    function renderLeaveRequests(requests) {
        const tbody = document.getElementById('requestsTableBody');
        
        if (requests.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <div class="empty-state-icon">📋</div>
                            <p>No leave requests found</p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        window.leaveRequestsData = requests;
        tbody.innerHTML = requests.map(request => `
            <tr class="leave-request-row clickable" data-request-id="${request.id}" onclick="openLeaveRequestDetailsModal(event, ${request.id})">
                <td>${request.user_name}</td>
                <td>${request.leave_type_label}</td>
                <td>${request.start_date_formatted}</td>
                <td>${request.end_date_formatted}</td>
                <td>${request.days_requested}</td>
                <td><span class="status-badge status-${request.status}">${request.status_label}</span></td>
            </tr>
        `).join('');
    }

    // Load leave credits
    async function loadLeaveCredits() {
        if (!permissions.viewCredits && !permissions.manageCredits) {
            return;
        }
        
        try {
            const year = document.getElementById('yearFilter').value;
            const url = `/api/leave-management/leave-credits?year=${year}`;
            
            const response = await fetch(url);
            const data = await response.json();
            
            if (data.success) {
                renderLeaveCredits(data.data);
                // Clear search when loading new data
                document.getElementById('creditsSearchInput').value = '';
            }
        } catch (error) {
            console.error('Error loading leave credits:', error);
        }
    }

    // Store original credits data for filtering
    let allCreditsData = [];

    // Render leave credits grouped by user
    function renderLeaveCredits(credits) {
        const container = document.getElementById('creditsTableBody');
        allCreditsData = credits;
        
        if (credits.length === 0) {
            container.innerHTML = '<div class="empty-state"><div class="empty-state-icon">💳</div><p>No leave credits found</p></div>';
            return;
        }

        // Group credits by user
        const groupedByUser = {};
        credits.forEach(credit => {
            if (!groupedByUser[credit.user_id]) {
                groupedByUser[credit.user_id] = {
                    user_id: credit.user_id,
                    user_name: credit.user_name,
                    user_email: credit.user_email,
                    credits: []
                };
            }
            groupedByUser[credit.user_id].credits.push(credit);
        });

        // Render grouped credits
        let html = '';
        Object.values(groupedByUser).forEach(userGroup => {
            html += `
                <div class="user-credits-group" data-user-id="${userGroup.user_id}" data-user-name="${userGroup.user_name.toLowerCase()}">
                    <div class="user-credits-header">
                        <div class="user-credits-info">
                            <h3 class="user-credits-name">${userGroup.user_name}</h3>
                            <span class="user-credits-email">${userGroup.user_email}</span>
                        </div>
                        <div class="user-credits-count">${userGroup.credits.length} leave type(s)</div>
                    </div>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Leave Type</th>
                                    <th>Total Credits</th>
                                    <th>Available</th>
                                    <th>Year</th>
                                    <th>Notes</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${userGroup.credits.map(credit => `
                                    <tr>
                                        <td>${credit.leave_type_label}</td>
                                        <td>${credit.credits}</td>
                                        <td>${credit.available_credits}</td>
                                        <td>${credit.year}</td>
                                        <td>${credit.notes || '-'}</td>
                                        <td>
                                            ${permissions.manageCredits ? `
                                                <div class="table-actions">
                                                    <button class="icon-btn" title="Edit" onclick="editCredit(${credit.id})">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                                        </svg>
                                                    </button>
                                                </div>
                                            ` : '-'}
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    // Filter credits by search term
    function filterCredits() {
        const searchTerm = document.getElementById('creditsSearchInput').value.toLowerCase().trim();
        const userGroups = document.querySelectorAll('.user-credits-group');
        
        if (!searchTerm) {
            userGroups.forEach(group => {
                group.style.display = 'block';
            });
            return;
        }

        userGroups.forEach(group => {
            const userName = group.getAttribute('data-user-name');
            if (userName.includes(searchTerm)) {
                group.style.display = 'block';
            } else {
                group.style.display = 'none';
            }
        });
    }

    // Load available users
    async function loadAvailableUsers() {
        try {
            const response = await fetch('/api/leave-management/users');
            const data = await response.json();
            
            if (data.success) {
                const select = document.getElementById('creditUserId');
                select.innerHTML = '<option value="">Select employee</option>' + 
                    data.data.map(user => `<option value="${user.id}">${user.name}</option>`).join('');
            }
        } catch (error) {
            console.error('Error loading users:', error);
        }
    }

    // Modal functions
    async function openNewRequestModal() {
        if (!permissions.createRequest) {
            alert('You do not have permission to create leave requests.');
            return;
        }
        document.getElementById('newRequestForm').reset();
        document.getElementById('creditsInfoGroup').style.display = 'none';
        toggleAttachmentField();

        // Set minimum date to today for both date inputs
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('startDateInput').min = today;
        document.getElementById('endDateInput').min = today;
        
        // Clear any error messages
        document.getElementById('startDateError').style.display = 'none';
        document.getElementById('endDateError').style.display = 'none';
        
        document.getElementById('newRequestModal').classList.add('active');
        
        // Load credits data
        await loadMyCredits();
    }

    function closeNewRequestModal() {
        document.getElementById('newRequestModal').classList.remove('active');
        document.getElementById('creditsInfoGroup').style.display = 'none';
    }

    // Load my leave credits
    async function loadMyCredits() {
        try {
            const response = await fetch('/api/leave-management/my-credits');
            const data = await response.json();
            
            if (data.success) {
                myCreditsData = data.data;
                updateCreditsDisplay();
            }
        } catch (error) {
            console.error('Error loading my credits:', error);
        }
    }

    // Show/hide attachment field based on leave type (required for sick leave)
    function toggleAttachmentField() {
        const leaveType = document.getElementById('leaveTypeSelect').value;
        const attachmentGroup = document.getElementById('attachmentGroup');
        const attachmentInput = document.getElementById('attachmentInput');
        const attachmentError = document.getElementById('attachmentError');

        if (leaveType === 'sick') {
            attachmentGroup.style.display = 'block';
            attachmentInput.setAttribute('required', 'required');
        } else {
            attachmentGroup.style.display = 'none';
            attachmentInput.removeAttribute('required');
            attachmentInput.value = '';
            attachmentError.style.display = 'none';
            attachmentError.textContent = '';
        }
    }

    // Update credits display based on selected leave type
    function updateCreditsDisplay() {
        const leaveType = document.getElementById('leaveTypeSelect').value;
        const creditsInfoGroup = document.getElementById('creditsInfoGroup');
        
        if (!leaveType || !myCreditsData[leaveType]) {
            creditsInfoGroup.style.display = 'none';
            return;
        }
        
        const credits = myCreditsData[leaveType];
        document.getElementById('totalCredits').textContent = credits.total.toFixed(1);
        document.getElementById('usedCredits').textContent = credits.used.toFixed(1);
        document.getElementById('availableCredits').textContent = credits.available.toFixed(1);
        
        creditsInfoGroup.style.display = 'block';
    }

    // Validate start and end dates
    function validateDates() {
        const startDateInput = document.getElementById('startDateInput');
        const endDateInput = document.getElementById('endDateInput');
        const startDateError = document.getElementById('startDateError');
        const endDateError = document.getElementById('endDateError');
        
        // Clear previous errors
        startDateError.style.display = 'none';
        startDateError.textContent = '';
        endDateError.style.display = 'none';
        endDateError.textContent = '';
        
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        if (!startDateInput.value) {
            return;
        }
        
        const startDate = new Date(startDateInput.value);
        startDate.setHours(0, 0, 0, 0);
        
        // Validate start date is not in the past
        if (startDate < today) {
            startDateError.textContent = 'Start date cannot be in the past.';
            startDateError.style.display = 'block';
            startDateInput.setCustomValidity('Start date cannot be in the past.');
            return;
        } else {
            startDateInput.setCustomValidity('');
        }
        
        // Set minimum end date to start date
        endDateInput.min = startDateInput.value;
        
        if (!endDateInput.value) {
            return;
        }
        
        const endDate = new Date(endDateInput.value);
        endDate.setHours(0, 0, 0, 0);
        
        // Validate end date is not before start date
        if (endDate < startDate) {
            endDateError.textContent = 'End date must be after or equal to start date.';
            endDateError.style.display = 'block';
            endDateInput.setCustomValidity('End date must be after or equal to start date.');
            return;
        } else {
            endDateInput.setCustomValidity('');
        }
    }

    function openNewCreditModal() {
        if (!permissions.manageCredits) {
            alert('You do not have permission to manage leave credits.');
            return;
        }
        document.getElementById('newCreditModal').classList.add('active');
        document.getElementById('newCreditForm').reset();
    }

    function closeNewCreditModal() {
        document.getElementById('newCreditModal').classList.remove('active');
    }

    function openApproveModal(requestId, status) {
        document.getElementById('approveRequestId').value = requestId;
        document.getElementById('approveStatus').value = status;
        const rejectionReasonTextarea = document.getElementById('rejectionReason');
        const rejectionReasonError = document.getElementById('rejectionReasonError');
        
        // Clear previous errors and values
        rejectionReasonTextarea.value = '';
        rejectionReasonError.style.display = 'none';
        rejectionReasonError.textContent = '';
        rejectionReasonTextarea.removeAttribute('required');
        
        if (status === 'approved') {
            document.getElementById('approveModalTitle').textContent = 'Approve Leave Request';
            document.getElementById('approveSubmitBtn').textContent = 'Approve';
            document.getElementById('approveSubmitBtn').className = 'btn btn-success';
            document.getElementById('rejectionReasonGroup').style.display = 'none';
        } else {
            document.getElementById('approveModalTitle').textContent = 'Reject Leave Request';
            document.getElementById('approveSubmitBtn').textContent = 'Reject';
            document.getElementById('approveSubmitBtn').className = 'btn btn-danger';
            document.getElementById('rejectionReasonGroup').style.display = 'block';
            rejectionReasonTextarea.setAttribute('required', 'required');
        }
        
        document.getElementById('approveModal').classList.add('active');
    }

    function closeApproveModal() {
        document.getElementById('approveModal').classList.remove('active');
        document.getElementById('rejectionReason').value = '';
        document.getElementById('rejectionReasonError').style.display = 'none';
        document.getElementById('rejectionReasonError').textContent = '';
    }

    function openCancelConfirmModal(requestId) {
        document.getElementById('cancelRequestId').value = requestId;
        document.getElementById('cancelConfirmBtn').onclick = () => {
            closeCancelConfirmModal();
            cancelRequest(requestId);
        };
        document.getElementById('cancelConfirmModal').classList.add('active');
    }

    function closeCancelConfirmModal() {
        document.getElementById('cancelConfirmModal').classList.remove('active');
    }

    // Submit leave request
    async function submitLeaveRequest(event) {
        event.preventDefault();
        
        if (!permissions.createRequest) {
            alert('You do not have permission to create leave requests.');
            return;
        }
        
        // Validate dates before submitting
        validateDates();
        
        // Check if there are any validation errors
        const startDateInput = document.getElementById('startDateInput');
        const endDateInput = document.getElementById('endDateInput');
        const startDateError = document.getElementById('startDateError');
        const endDateError = document.getElementById('endDateError');
        
        if (startDateError.style.display === 'block' || endDateError.style.display === 'block' || 
            !startDateInput.validity.valid || !endDateInput.validity.valid) {
            // Focus on the first invalid field
            if (!startDateInput.validity.valid || startDateError.style.display === 'block') {
                startDateInput.focus();
            } else if (!endDateInput.validity.valid || endDateError.style.display === 'block') {
                endDateInput.focus();
            }
            return;
        }
        
        // Validate sick leave attachment before submit
        const leaveType = document.getElementById('leaveTypeSelect').value;
        const attachmentInput = document.getElementById('attachmentInput');
        const attachmentError = document.getElementById('attachmentError');

        if (leaveType === 'sick' && !attachmentInput.files.length) {
            attachmentError.textContent = 'A file attachment is required for sick leave.';
            attachmentError.style.display = 'block';
            return;
        }
        attachmentError.style.display = 'none';
        attachmentError.textContent = '';

        const formData = new FormData(event.target);
        if (leaveType !== 'sick') {
            formData.delete('attachment');
        }

        try {
            const response = await fetch('/api/leave-management/leave-requests', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                closeNewRequestModal();
                loadLeaveRequests();
                if (permissions.viewStats) {
                    loadStats();
                }
                alert('Leave request submitted successfully!');
            } else {
                // Display validation errors if any
                if (result.errors) {
                    let errorMessage = 'Validation errors:\n';
                    Object.keys(result.errors).forEach(key => {
                        errorMessage += `- ${result.errors[key].join(', ')}\n`;
                    });
                    alert(errorMessage);
                } else {
                    alert(result.message || 'Error submitting leave request');
                }
            }
        } catch (error) {
            console.error('Error submitting leave request:', error);
            alert('Error submitting leave request');
        }
    }

    // Approve/Reject request
    function approveRequest(requestId, status) {
        openApproveModal(requestId, status);
    }

    async function submitApproval(event) {
        event.preventDefault();
        
        const requestId = document.getElementById('approveRequestId').value;
        const status = document.getElementById('approveStatus').value;
        const rejectionReasonTextarea = document.getElementById('rejectionReason');
        const rejectionReasonError = document.getElementById('rejectionReasonError');
        const rejectionReason = rejectionReasonTextarea.value.trim();
        
        // Clear previous errors
        rejectionReasonError.style.display = 'none';
        rejectionReasonError.textContent = '';
        
        // Validate rejection reason if status is rejected
        if (status === 'rejected' && !rejectionReason) {
            rejectionReasonError.textContent = 'Rejection reason is required when rejecting a leave request.';
            rejectionReasonError.style.display = 'block';
            rejectionReasonTextarea.focus();
            return;
        }
        
        const data = {
            status: status,
            rejection_reason: status === 'rejected' ? rejectionReason : null
        };
        
        try {
            const response = await fetch(`/api/leave-management/leave-requests/${requestId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                closeApproveModal();
                loadLeaveRequests();
                loadStats();
                alert('Leave request updated successfully!');
            } else {
                // Display validation errors if any
                if (result.errors) {
                    let errorMessage = 'Validation errors:\n';
                    Object.keys(result.errors).forEach(key => {
                        errorMessage += `- ${result.errors[key].join(', ')}\n`;
                    });
                    alert(errorMessage);
                } else {
                    alert(result.message || 'Error updating leave request');
                }
            }
        } catch (error) {
            console.error('Error updating leave request:', error);
            alert('Error updating leave request');
        }
    }

    // Cancel request
    async function cancelRequest(requestId) {
        try {
            const response = await fetch(`/api/leave-management/leave-requests/${requestId}/cancel`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            
            const result = await response.json();
            
            if (result.success) {
                loadLeaveRequests();
                loadStats();
                alert('Leave request cancelled successfully!');
            } else {
                alert(result.message || 'Error cancelling leave request');
            }
        } catch (error) {
            console.error('Error cancelling leave request:', error);
            alert('Error cancelling leave request');
        }
    }

    // Submit leave credit
    async function submitLeaveCredit(event) {
        event.preventDefault();
        
        if (!permissions.manageCredits) {
            alert('You do not have permission to manage leave credits.');
            return;
        }
        
        const formData = new FormData(event.target);
        const data = Object.fromEntries(formData);
        data.credits = parseFloat(data.credits);
        data.year = parseInt(data.year);
        
        try {
            const response = await fetch('/api/leave-management/leave-credits', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                closeNewCreditModal();
                loadLeaveCredits();
                loadStats();
                alert('Leave credits added successfully!');
            } else {
                alert(result.message || 'Error adding leave credits');
            }
        } catch (error) {
            console.error('Error adding leave credits:', error);
            alert('Error adding leave credits');
        }
    }

    // Edit credit (placeholder - can be enhanced)
    function editCredit(creditId) {
        alert('Edit functionality can be added here');
    }

    if (typeof approveRequest === 'function') window.approveRequest = approveRequest;
    if (typeof closeApproveModal === 'function') window.closeApproveModal = closeApproveModal;
    if (typeof closeAttachmentFullscreen === 'function') window.closeAttachmentFullscreen = closeAttachmentFullscreen;
    if (typeof closeCancelConfirmModal === 'function') window.closeCancelConfirmModal = closeCancelConfirmModal;
    if (typeof closeEmployeesOnLeaveModal === 'function') window.closeEmployeesOnLeaveModal = closeEmployeesOnLeaveModal;
    if (typeof closeLeaveRequestDetailsModal === 'function') window.closeLeaveRequestDetailsModal = closeLeaveRequestDetailsModal;
    if (typeof closeNewCreditModal === 'function') window.closeNewCreditModal = closeNewCreditModal;
    if (typeof closeNewRequestModal === 'function') window.closeNewRequestModal = closeNewRequestModal;
    if (typeof editCredit === 'function') window.editCredit = editCredit;
    if (typeof loadCalendar === 'function') window.loadCalendar = loadCalendar;
    if (typeof loadLeaveCredits === 'function') window.loadLeaveCredits = loadLeaveCredits;
    if (typeof nextMonth === 'function') window.nextMonth = nextMonth;
    if (typeof openAttachmentFullscreen === 'function') window.openAttachmentFullscreen = openAttachmentFullscreen;
    if (typeof openCancelConfirmModal === 'function') window.openCancelConfirmModal = openCancelConfirmModal;
    if (typeof openLeaveRequestDetailsModal === 'function') window.openLeaveRequestDetailsModal = openLeaveRequestDetailsModal;
    if (typeof openNewCreditModal === 'function') window.openNewCreditModal = openNewCreditModal;
    if (typeof openNewRequestModal === 'function') window.openNewRequestModal = openNewRequestModal;
    if (typeof previousMonth === 'function') window.previousMonth = previousMonth;
    if (typeof stopPropagation === 'function') window.stopPropagation = stopPropagation;
    if (typeof switchStatusTab === 'function') window.switchStatusTab = switchStatusTab;
    if (typeof switchTab === 'function') window.switchTab = switchTab;
    if (typeof toggleAttachmentField === 'function') window.toggleAttachmentField = toggleAttachmentField;
    if (typeof validateDates === 'function') window.validateDates = validateDates;
})();
