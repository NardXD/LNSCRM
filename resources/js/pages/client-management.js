/* Vite page entry — IIFE preserves onclick globals */
(function () {
const CFG = window.__clientManagementConfig || {};
    // Client Data
    let clientsData = [];
    let statsData = {};

    // Pagination State
    let currentPage = 1;
    const itemsPerPage = 10;
    let totalPages = 1;
    let totalClients = 0;

    // API Base URL
    const apiBase = '/api/client-management';

    // Fetch clients from API
    async function fetchClients(page = 1, search = '', status = 'all', industry = 'all') {
        try {
            const params = new URLSearchParams({
                page: page,
                per_page: itemsPerPage,
            });
            
            if (search) params.append('search', search);
            if (status !== 'all') params.append('status', status);
            if (industry !== 'all') params.append('industry', industry);

            const response = await fetch(`${apiBase}/clients?${params}`);
            const result = await response.json();

            if (result.success) {
                clientsData = result.data.map(client => ({
                    ...client,
                    contactPerson: client.contact_person,
                    initials: generateInitialsFromName(client.name),
                }));
                
                totalClients = result.pagination.total;
                totalPages = result.pagination.last_page;
                currentPage = result.pagination.current_page;
                
                updateView();
            } else {
                console.error('Failed to fetch clients:', result.message);
                alert('Failed to load clients: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error fetching clients:', error);
            alert('Error loading clients. Please refresh the page.');
        }
    }

    // Fetch stats from API
    async function fetchStats() {
        try {
            const response = await fetch(`${apiBase}/stats`);
            const result = await response.json();

            if (result.success) {
                statsData = result.data;
                updateStatsDisplay();
            }
        } catch (error) {
            console.error('Error fetching stats:', error);
        }
    }

    // Update stats display
    function updateStatsDisplay() {
        document.querySelector('.stat-card:nth-child(1) .stat-value').textContent = statsData.total_clients || 0;
        document.querySelector('.stat-card:nth-child(2) .stat-value').textContent = statsData.active_clients || 0;
        document.querySelector('.stat-card:nth-child(2) .stat-change').textContent = `${statsData.active_percentage || 0}% of total`;
        document.querySelector('.stat-card:nth-child(3) .stat-value').textContent = statsData.new_this_month || 0;
        document.querySelector('.stat-card:nth-child(3) .stat-change').textContent = `${statsData.growth_percentage || 0}% growth`;
        
        const revenue = statsData.total_revenue || 0;
        const revenueText = revenue >= 1000000 
            ? `$${(revenue / 1000000).toFixed(1)}M`
            : `$${Math.round(revenue).toLocaleString()}`;
        document.querySelector('.stat-card:nth-child(4) .stat-value').textContent = revenueText;
    }

    // Generate initials from name
    function generateInitialsFromName(name) {
        const words = name.trim().split(' ');
        if (words.length >= 2) {
            return (words[0][0] + words[words.length - 1][0]).toUpperCase();
        }
        return name.substring(0, 2).toUpperCase();
    }

    // Render Functions
    function renderTable() {
        const tbody = document.getElementById('clientsTableBody');
        // API returns only the current page; do not slice (page 2 would slice indices 10–19 of a 10-item array).
        const pageData = clientsData;

        tbody.innerHTML = pageData.map(client => `
            <tr onclick="openClientModal(${client.id})">
                <td onclick="event.stopPropagation()"><input type="checkbox" class="table-checkbox" data-id="${client.id}"></td>
                <td>
                    <div class="client-cell">
                        <div class="client-avatar">${client.initials}</div>
                        <div class="client-info">
                            <div class="client-name">${client.name}</div>
                        </div>
                    </div>
                </td>
                <td>${client.contactPerson}</td>
                <td>${client.email}</td>
                <td>${client.phone}</td>
                <td>${client.industry}</td>
                <td><span class="status-badge ${client.status}">${client.status.charAt(0).toUpperCase() + client.status.slice(1)}</span></td>
                <td><strong>$${client.revenue.toLocaleString()}</strong></td>
                <td onclick="event.stopPropagation()">
                    <div class="table-actions">
                        <button class="icon-btn" title="View" onclick="openClientModal(${client.id})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                        <button class="icon-btn" title="Edit" onclick="event.stopPropagation(); editClientById(${client.id})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                            </svg>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    function renderCards() {
        const container = document.getElementById('clientsCards');
        const pageData = clientsData;

        container.innerHTML = pageData.map(client => `
            <div class="client-card" onclick="openClientModal(${client.id})">
                <div class="card-header">
                    <div class="card-main">
                        <input type="checkbox" class="table-checkbox" data-id="${client.id}" onclick="event.stopPropagation()">
                        <div class="client-avatar">${client.initials}</div>
                        <div>
                            <div class="client-name">${client.name}</div>
                            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">${client.contactPerson}</div>
                        </div>
                    </div>
                    <span class="status-badge ${client.status}">${client.status.charAt(0).toUpperCase() + client.status.slice(1)}</span>
                </div>
                <div class="card-details">
                    <div class="card-detail">
                        <span class="card-label">Email</span>
                        <span class="card-value">${client.email}</span>
                    </div>
                    <div class="card-detail">
                        <span class="card-label">Phone</span>
                        <span class="card-value">${client.phone}</span>
                    </div>
                    <div class="card-detail">
                        <span class="card-label">Industry</span>
                        <span class="card-value">${client.industry}</span>
                    </div>
                    <div class="card-detail">
                        <span class="card-label">Revenue</span>
                        <span class="card-value">$${client.revenue.toLocaleString()}</span>
                    </div>
                </div>
            </div>
        `).join('');
    }

    function renderPagination() {
        const info = document.getElementById('paginationInfo');
        const numbers = document.getElementById('paginationNumbers');
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');

        const start = totalClients > 0 ? (currentPage - 1) * itemsPerPage + 1 : 0;
        const end = Math.min(currentPage * itemsPerPage, totalClients);
        info.textContent = totalClients > 0 
            ? `Showing ${start} to ${end} of ${totalClients} results`
            : 'No results found';

        prevBtn.disabled = currentPage === 1;
        nextBtn.disabled = currentPage === totalPages;

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
                loadClients(parseInt(btn.dataset.page));
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
    document.getElementById('prevBtn').addEventListener('click', () => {
        if (currentPage > 1) {
            loadClients(currentPage - 1);
        }
    });

    document.getElementById('nextBtn').addEventListener('click', () => {
        if (currentPage < totalPages) {
            loadClients(currentPage + 1);
        }
    });

    // Search and Filter handlers
    let searchTimeout;
    document.getElementById('clientSearch')?.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            loadClients(1);
        }, 500);
    });

    document.getElementById('statusFilter')?.addEventListener('change', function() {
        setActiveStatusTab(this.value);
        loadClients(1);
    });

    document.getElementById('industryFilter')?.addEventListener('change', function() {
        loadClients(1);
    });

    function setActiveStatusTab(status) {
        document.querySelectorAll('.client-status-tab').forEach(tab => {
            tab.classList.toggle('active', tab.getAttribute('data-status') === status);
        });
        const statusFilter = document.getElementById('statusFilter');
        if (statusFilter) statusFilter.value = status;
    }

    document.querySelectorAll('.client-status-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            const status = this.getAttribute('data-status');
            setActiveStatusTab(status);
            loadClients(1);
        });
    });

    // Load clients helper
    function loadClients(page = 1) {
        const search = document.getElementById('clientSearch')?.value || '';
        const status = document.getElementById('statusFilter')?.value || 'all';
        const industry = document.getElementById('industryFilter')?.value || 'all';
        fetchClients(page, search, status, industry);
    }

    // Select All Checkbox
    document.getElementById('selectAllClients')?.addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.table-checkbox:not(#selectAllClients)');
        checkboxes.forEach(cb => cb.checked = this.checked);
    });

    // Client Modal
    async function openClientModal(clientId) {
        currentClientId = clientId;
        
        try {
            const response = await fetch(`${apiBase}/clients/${clientId}`);
            const result = await response.json();

            if (!result.success) {
                alert('Failed to load client data: ' + (result.message || 'Unknown error'));
                return;
            }

            const client = result.data;

        // Update modal header
            document.getElementById('modalClientInitials').textContent = generateInitialsFromName(client.name);
        document.getElementById('modalClientName').textContent = client.name;
            document.getElementById('modalClientIndustry').textContent = client.industry || 'N/A';

            // Update overview details
            document.getElementById('detailCompanyName').textContent = client.name || 'N/A';
            document.getElementById('detailContactPerson').textContent = client.contact_person || 'N/A';
            document.getElementById('detailEmail').textContent = client.email || 'N/A';
            document.getElementById('detailPhone').textContent = client.phone || 'N/A';
            document.getElementById('detailIndustry').textContent = client.industry || 'N/A';
            document.getElementById('detailStatus').innerHTML = `<span class="status-badge ${client.status}">${client.status.charAt(0).toUpperCase() + client.status.slice(1)}</span>`;
            
            // Update website with proper link handling
            const websiteElement = document.getElementById('detailWebsite');
            if (client.website) {
                // Ensure URL has protocol
                let websiteUrl = client.website;
                if (!websiteUrl.match(/^https?:\/\//i)) {
                    websiteUrl = 'https://' + websiteUrl;
                }
                websiteElement.innerHTML = `<a href="${websiteUrl}" target="_blank" rel="noopener noreferrer">${client.website}</a>`;
            } else {
                websiteElement.textContent = 'N/A';
            }
            
            document.getElementById('detailAddress').textContent = client.address || 'N/A';
            document.getElementById('detailRevenue').textContent = `$${(parseFloat(client.revenue) || 0).toLocaleString()}`;
            
            // console.log('Client data loaded:', client);
            // console.log('Contacts:', client.contacts);

            // Render contacts - ensure contacts array exists
            if (client.contacts && Array.isArray(client.contacts)) {
                renderContactsFromData(client.contacts);
            } else {
                console.warn('Contacts data is missing or not an array:', client.contacts);
                renderContactsFromData([]);
            }

            // Render employees
            if (client.employees && Array.isArray(client.employees)) {
                renderEmployeesFromData(client.employees);
            } else {
                renderEmployeesFromData([]);
            }

            // Render projects
            if (client.projects && Array.isArray(client.projects)) {
                renderProjectsFromData(client.projects);
            } else {
                renderProjectsFromData([]);
            }

            // Render notes - use client data if available, otherwise fetch
            if (client.notes && Array.isArray(client.notes) && client.notes.length > 0) {
                renderNotesFromData(client.notes);
            } else {
                // Always fetch fresh notes when modal opens
                await renderNotes(clientId);
            }

            // Load portal users
            await loadPortalUsers(clientId);

            // Reset to Overview tab
            const modal = document.getElementById('clientModal');
            modal.querySelectorAll('.modal-tab').forEach((tab, index) => {
                if (index === 0) {
                    tab.classList.add('active');
                } else {
                    tab.classList.remove('active');
                }
            });
            modal.querySelectorAll('.modal-tab-content').forEach((content, index) => {
                if (index === 0) {
                    content.classList.add('active');
                } else {
                    content.classList.remove('active');
                }
            });

            // Show modal
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        } catch (error) {
            console.error('Error loading client:', error);
            alert('Error loading client data. Please try again.');
        }
    }

    function closeClientModal() {
        document.getElementById('clientModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    document.getElementById('clientModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeClientModal();
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (document.getElementById('addPortalUserModal').classList.contains('active')) {
                closeAddPortalUserModal();
            } else if (document.getElementById('addProjectModal').classList.contains('active')) {
                closeAddProjectModal();
            } else if (document.getElementById('addEmployeeModal').classList.contains('active')) {
                closeAddEmployeeModal();
            } else if (document.getElementById('newClientModal').classList.contains('active')) {
                closeNewClientModal();
            } else {
                closeClientModal();
            }
        }
    });

    // Modal Tabs - Client Detail Modal only
    document.querySelectorAll('#clientModal .modal-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            const tabId = this.dataset.tab;
            const modal = this.closest('.client-modal');
            
            // Update tabs in this modal only
            modal.querySelectorAll('.modal-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            // Update tab content in this modal only
            modal.querySelectorAll('.modal-tab-content').forEach(content => {
                content.classList.remove('active');
            });
            modal.querySelector(`#${tabId}Tab`).classList.add('active');
            
            // Load notes when notes tab is clicked
            if (tabId === 'notes' && currentClientId) {
                renderNotes(currentClientId);
            }
        });
    });
    
    // Character counter for notes textarea (using event delegation)
    document.addEventListener('input', function(e) {
        if (e.target.id === 'notesTextarea') {
            const charCount = e.target.value.length;
            const counter = document.getElementById('noteCharCount');
            if (counter) {
                counter.textContent = `${charCount} / 5000 characters`;
                if (charCount > 4500) {
                    counter.style.color = '#ef4444';
                } else {
                    counter.style.color = 'var(--text-muted)';
                }
            }
        }
    });
    
    // Allow Enter+Shift for new line, Enter alone to submit (using event delegation)
    document.addEventListener('keydown', function(e) {
        if (e.target.id === 'notesTextarea' && e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            addNote();
        }
    });

    // Render Modal Content
    function renderContactsFromData(contacts) {
        const contactsListElement = document.getElementById('contactsList');
        if (!contactsListElement) {
            console.error('contactsList element not found');
            return;
        }

        // console.log('Rendering contacts:', contacts);
        
        if (!contacts || contacts.length === 0) {
            contactsListElement.innerHTML = `
                <div class="empty-state">
                    <p>No contacts added yet.</p>
                </div>
            `;
            return;
        }
        
        contactsListElement.innerHTML = contacts.map(contact => {
            const initials = generateInitials(contact.name);
            return `
                <div class="contact-item">
                    <div class="contact-avatar">${initials}</div>
                    <div class="contact-info">
                        <div class="contact-name">${contact.name || 'N/A'}</div>
                        ${contact.role ? `<div class="contact-role">${contact.role}</div>` : ''}
                        ${contact.email ? `<div class="contact-email">${contact.email}</div>` : ''}
                        ${contact.phone ? `<div class="contact-phone">${contact.phone}</div>` : ''}
                    </div>
                </div>
            `;
        }).join('');
    }

    // Project Management Functions
    function renderProjectsFromData(projects) {
        const container = document.getElementById('projectsListContainer');
        const emptyState = document.getElementById('projectsEmptyState');
        
        if (!container) return;

        // Clear existing projects
        container.querySelectorAll('.project-item').forEach(item => item.remove());

        if (!projects || projects.length === 0) {
            emptyState.style.display = 'block';
            return;
        }

        emptyState.style.display = 'none';

        projects.forEach(project => {
            const projectItem = document.createElement('div');
            projectItem.className = 'project-item';
            projectItem.setAttribute('data-project-id', project.id);
            projectItem.addEventListener('click', function() {
                openProjectTasks(project.id);
            });
            projectItem.innerHTML = `
                <div class="project-header">
                    <div class="project-name">${project.title || 'Untitled Project'}</div>
                    <span class="project-status-badge ${project.status}">${project.status.charAt(0).toUpperCase() + project.status.slice(1).replace('-', ' ')}</span>
                </div>
                ${project.description ? `<div class="project-description">${project.description}</div>` : ''}
                <div class="project-meta">
                    <span>Deadline: ${project.deadline || 'N/A'}</span>
                    ${project.progress !== undefined ? `<span>Progress: ${project.progress}%</span>` : ''}
                    ${project.tasks !== undefined ? `<span>Tasks: ${project.completed || 0}/${project.tasks || 0}</span>` : ''}
                </div>
                ${project.progress !== undefined ? `
                    <div class="project-progress">
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: ${project.progress}%"></div>
                        </div>
                    </div>
                ` : ''}
            `;
            container.appendChild(projectItem);
        });
    }

    function openAddProjectModal() {
        if (!currentClientId) return;
        
        // Set minimum date to today
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('projectDeadline').min = today;
        
        document.getElementById('addProjectModal').classList.add('active');
    }

    function closeAddProjectModal() {
        document.getElementById('addProjectModal').classList.remove('active');
        document.getElementById('newProjectForm').reset();
    }

    document.getElementById('addProjectModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeAddProjectModal();
        }
    });

    async function submitProjectForm(event) {
        event.preventDefault();
        
        if (!currentClientId) {
            alert('Client ID not found.');
            return;
        }

        const form = event.target;
        const formData = new FormData(form);

        const client = clientsData.find(c => c.id === currentClientId);
        
        const projectData = {
            title: formData.get('title'),
            client: client?.name || '',
            client_id: currentClientId,
            status: formData.get('status'),
            deadline: formData.get('deadline'),
            description: formData.get('description') || '',
        };

        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = 'Creating...';

        try {
            const response = await fetch('/api/project-management/projects', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify(projectData),
            });

            const result = await response.json();

            if (result.success) {
                closeAddProjectModal();
                // Reload projects list
                await reloadProjectsList();
                // Switch to projects tab
                switchToProjectsTab();
            } else {
                alert('Error: ' + (result.message || 'Failed to create project'));
            }
        } catch (error) {
            console.error('Error creating project:', error);
            alert('Error creating project. Please try again.');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    }

    async function reloadProjectsList() {
        if (!currentClientId) return;

        try {
            const response = await fetch(`${apiBase}/clients/${currentClientId}`);
            const result = await response.json();

            if (result.success) {
                const client = result.data;
                if (client.projects && Array.isArray(client.projects)) {
                    renderProjectsFromData(client.projects);
                } else {
                    renderProjectsFromData([]);
                }
            }
        } catch (error) {
            console.error('Error reloading projects list:', error);
        }
    }

    function openProjectTasks(projectId) {
        // Navigate to project management page with tasks tab and project filter
        const url = new URL(CFG.projectManagementUrl || '/project-management', window.location.origin);
        url.searchParams.set('tab', 'tasks');
        url.searchParams.set('project', projectId);
        window.location.href = url.toString();
    }

    function switchToProjectsTab() {
        const modal = document.getElementById('clientModal');
        if (!modal) return;

        // Switch to projects tab
        modal.querySelectorAll('.modal-tab').forEach(tab => {
            if (tab.dataset.tab === 'projects') {
                tab.classList.add('active');
            } else {
                tab.classList.remove('active');
            }
        });

        modal.querySelectorAll('.modal-tab-content').forEach(content => {
            if (content.id === 'projectsTab') {
                content.classList.add('active');
            } else {
                content.classList.remove('active');
            }
        });
    }

    // Employee Management Functions
    let currentClientId = null;
    let availableEmployeesData = [];
    let assignedEmployeeIds = [];

    function renderEmployeesFromData(employees) {
        const container = document.getElementById('employeesListContainer');
        const emptyState = document.getElementById('employeesEmptyState');
        
        if (!container) return;

        // Clear existing employees
        container.querySelectorAll('.employee-item').forEach(item => item.remove());

        if (!employees || employees.length === 0) {
            emptyState.style.display = 'block';
            return;
        }

        emptyState.style.display = 'none';

        employees.forEach(employee => {
            const initials = generateInitialsFromName(employee.name);
            const employeeItem = document.createElement('div');
            employeeItem.className = 'employee-item';
            const avatarContent = employee.photo 
                ? `<img src="${employee.photo}" alt="${employee.name}" class="employee-avatar-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                   <div class="employee-avatar-fallback" style="display: none;">${initials}</div>`
                : `<div class="employee-avatar-fallback">${initials}</div>`;
            // Handle department - could be string or object
            const departmentName = employee.department 
                ? (typeof employee.department === 'string' 
                    ? employee.department 
                    : (employee.department.name || employee.department))
                : null;

            employeeItem.innerHTML = `
                <div class="employee-avatar">
                    ${avatarContent}
                </div>
                <div class="employee-info">
                    <div class="employee-name">${employee.name || 'N/A'}</div>
                    ${employee.email ? `<div class="employee-email">${employee.email}</div>` : ''}
                    ${departmentName ? `<div class="employee-department">${departmentName}</div>` : ''}
                </div>
                <div class="employee-actions">
                    <button type="button" class="icon-btn" onclick="removeEmployeeFromClient(${employee.id})" title="Remove employee">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"/>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                            <line x1="10" y1="11" x2="10" y2="17"/>
                            <line x1="14" y1="11" x2="14" y2="17"/>
                        </svg>
                    </button>
                </div>
            `;
            container.appendChild(employeeItem);
        });
    }

    async function openAddEmployeeModal() {
        if (!currentClientId) return;

        try {
            const response = await fetch(`${apiBase}/clients/${currentClientId}/available-employees`);
            
            // Check if response is OK before parsing JSON
            if (!response.ok) {
                const errorText = await response.text();
                console.error('HTTP Error:', response.status, errorText);
                alert(`Error loading employees: ${response.status === 404 ? 'Client not found.' : response.status === 403 ? 'Access denied.' : 'Server error. Please try again.'}`);
                return;
            }

            const result = await response.json();

            if (result.success) {
                const allEmployees = result.data.all_employees || [];
                assignedEmployeeIds = result.data.assigned_ids || [];
                // Filter out already assigned employees from the list
                availableEmployeesData = allEmployees.filter(emp => !assignedEmployeeIds.includes(emp.id));
                renderEmployeeSelectList(availableEmployeesData);
                document.getElementById('addEmployeeModal').classList.add('active');
                document.getElementById('employeeSearchInput').value = '';
            } else {
                alert('Failed to load employees: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error loading employees:', error);
            alert('Error loading employees. Please try again.');
        }
    }

    function closeAddEmployeeModal() {
        document.getElementById('addEmployeeModal').classList.remove('active');
        availableEmployeesData = [];
    }

    document.getElementById('addEmployeeModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeAddEmployeeModal();
        }
    });

    function renderEmployeeSelectList(employees, searchTerm = '') {
        const container = document.getElementById('employeesSelectList');
        if (!container) return;

        let filtered = employees;
        if (searchTerm) {
            const term = searchTerm.toLowerCase();
            filtered = employees.filter(emp => 
                emp.name.toLowerCase().includes(term) || 
                (emp.email && emp.email.toLowerCase().includes(term)) ||
                (emp.department && emp.department.toLowerCase().includes(term))
            );
        }

        if (filtered.length === 0) {
            container.innerHTML = '<div class="empty-state"><p>No employees found.</p></div>';
            return;
        }

        container.innerHTML = filtered.map(employee => {
            const initials = generateInitialsFromName(employee.name);
            const avatarContent = employee.photo 
                ? `<img src="${employee.photo}" alt="${employee.name}" class="employee-select-avatar-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                   <div class="employee-select-avatar-fallback" style="display: none;">${initials}</div>`
                : `<div class="employee-select-avatar-fallback">${initials}</div>`;
            return `
                <div class="employee-select-item" onclick="toggleEmployeeSelection(${employee.id})">
                    <input type="checkbox" id="emp_${employee.id}" value="${employee.id}" onclick="event.stopPropagation()">
                    <div class="employee-select-avatar">
                        ${avatarContent}
                    </div>
                    <div class="employee-select-info">
                        <div class="employee-select-name">${employee.name || 'N/A'}</div>
                        ${employee.email ? `<div class="employee-select-email">${employee.email}</div>` : ''}
                    </div>
                </div>
            `;
        }).join('');
    }

    function toggleEmployeeSelection(employeeId) {
        const checkbox = document.getElementById(`emp_${employeeId}`);
        if (checkbox) {
            checkbox.checked = !checkbox.checked;
        }
    }

    function filterEmployeeList() {
        const searchTerm = document.getElementById('employeeSearchInput').value;
        renderEmployeeSelectList(availableEmployeesData, searchTerm);
    }

    // Reload just the employee list without reloading the entire client modal
    async function reloadEmployeeList() {
        if (!currentClientId) return;

        try {
            const response = await fetch(`${apiBase}/clients/${currentClientId}`);
            
            // Check if response is OK before parsing JSON
            if (!response.ok) {
                console.error('HTTP Error reloading employee list:', response.status);
                return;
            }

            const result = await response.json();

            if (result.success) {
                const client = result.data;
                if (client.employees && Array.isArray(client.employees)) {
                    renderEmployeesFromData(client.employees);
                } else {
                    renderEmployeesFromData([]);
                }
            }
        } catch (error) {
            console.error('Error reloading employee list:', error);
        }
    }

    // Switch to employees tab in client modal
    function switchToEmployeesTab() {
        const modal = document.getElementById('clientModal');
        if (!modal) return;

        // Switch to employees tab
        modal.querySelectorAll('.modal-tab').forEach(tab => {
            if (tab.dataset.tab === 'employees') {
                tab.classList.add('active');
            } else {
                tab.classList.remove('active');
            }
        });

        modal.querySelectorAll('.modal-tab-content').forEach(content => {
            if (content.id === 'employeesTab') {
                content.classList.add('active');
            } else {
                content.classList.remove('active');
            }
        });
    }

    async function assignSelectedEmployees() {
        if (!currentClientId) return;

        const checkboxes = document.querySelectorAll('#employeesSelectList input[type="checkbox"]:checked:not(:disabled)');
        const employeeIds = Array.from(checkboxes).map(cb => parseInt(cb.value));

        if (employeeIds.length === 0) {
            alert('Please select at least one employee to add.');
            return;
        }

        try {
            const response = await fetch(`${apiBase}/clients/${currentClientId}/assign-employees`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({ employee_ids: employeeIds }),
            });

            const result = await response.json();

            if (result.success) {
                closeAddEmployeeModal();
                // Reload just the employee list and switch to employees tab
                await reloadEmployeeList();
                switchToEmployeesTab();
                // Optional: Show success message (remove alert if you prefer)
                // alert('Employees assigned successfully!');
            } else {
                alert('Failed to assign employees: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error assigning employees:', error);
            alert('Error assigning employees. Please try again.');
        }
    }

    async function removeEmployeeFromClient(userId) {
        if (!currentClientId) {
            alert('Client ID not found.');
            return;
        }

        if (!confirm('Are you sure you want to remove this employee from the client?')) {
            return;
        }

        try {
            const response = await fetch(`${apiBase}/clients/${currentClientId}/remove-employee`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({ user_id: userId }),
            });

            const result = await response.json();

            if (result.success) {
                // Reload just the employee list without closing the modal
                await reloadEmployeeList();
                // Optional: Show success message (remove alert if you prefer)
                // alert('Employee removed successfully!');
            } else {
                alert('Failed to remove employee: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error removing employee:', error);
            alert('Error removing employee. Please try again.');
        }
    }

    function renderNotesFromData(notes) {
        const notesList = document.getElementById('notesList');
        if (!notesList) return;

        if (!notes || notes.length === 0) {
            notesList.innerHTML = `
                <div class="empty-state">
                    <p>No notes added yet. Add a note below.</p>
                </div>
            `;
            return;
        }

        notesList.innerHTML = notes.map(note => {
            const author = note.user ? note.user.name : (note.author || 'Unknown');
            const timeAgo = note.created_at ? new Date(note.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '';
            const timeAgoRelative = note.time_ago || timeAgo;
            
            return `
                <div class="note-item" data-note-id="${note.id}">
                    <div class="note-text">${escapeHtml(note.note)}</div>
                    <div class="note-meta">
                        <span>${escapeHtml(author)}</span>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span>${timeAgoRelative}</span>
                            <button type="button" class="icon-btn" onclick="deleteNote(${note.id}, ${currentClientId})" title="Delete note" style="width: 24px; height: 24px; padding: 0;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 14px; height: 14px;">
                                    <polyline points="3 6 5 6 21 6"/>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                    <line x1="10" y1="11" x2="10" y2="17"/>
                                    <line x1="14" y1="11" x2="14" y2="17"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    async function renderNotes(clientId) {
        if (!clientId) return;

        try {
            const response = await fetch(`${apiBase}/clients/${clientId}/notes`);
            const result = await response.json();

            const notesList = document.getElementById('notesList');
            if (!notesList) return;

            if (!result.success || !result.data || result.data.length === 0) {
                notesList.innerHTML = `
                    <div class="empty-state">
                        <p>No notes added yet. Add a note below.</p>
                    </div>
                `;
                return;
            }

            notesList.innerHTML = result.data.map(note => `
                <div class="note-item" data-note-id="${note.id}">
                    <div class="note-text">${escapeHtml(note.note)}</div>
                    <div class="note-meta">
                        <span>${escapeHtml(note.author)}</span>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span>${note.time_ago}</span>
                            <button type="button" class="icon-btn" onclick="deleteNote(${note.id}, ${clientId})" title="Delete note" style="width: 24px; height: 24px; padding: 0;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 14px; height: 14px;">
                                    <polyline points="3 6 5 6 21 6"/>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                    <line x1="10" y1="11" x2="10" y2="17"/>
                                    <line x1="14" y1="11" x2="14" y2="17"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            `).join('');
        } catch (error) {
            console.error('Error fetching notes:', error);
            const notesList = document.getElementById('notesList');
            if (notesList) {
                notesList.innerHTML = `
                    <div class="empty-state">
                        <p>Error loading notes. Please try again.</p>
                    </div>
                `;
            }
        }
    }

    async function addNote() {
        if (!currentClientId) {
            alert('Client ID not found.');
            return;
        }

        const textarea = document.getElementById('notesTextarea');
        const text = textarea.value.trim();
        
        if (!text) {
            alert('Please enter a note.');
            textarea.focus();
            return;
        }

        if (text.length > 5000) {
            alert('Note is too long. Maximum 5000 characters allowed.');
            textarea.focus();
            return;
        }

        const addBtn = document.querySelector('#notesTab button.btn-primary');
        const originalText = addBtn ? addBtn.innerHTML : 'Add Note';
        if (addBtn) {
            addBtn.disabled = true;
            addBtn.innerHTML = 'Adding...';
        }

        try {
            const response = await fetch(`${apiBase}/clients/${currentClientId}/notes`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({ note: text }),
            });

            const result = await response.json();

            if (result.success) {
                textarea.value = '';
                // Reload notes list
                await renderNotes(currentClientId);
            } else {
                alert('Error adding note: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error adding note:', error);
            alert('Error adding note. Please try again.');
        } finally {
            if (addBtn) {
                addBtn.disabled = false;
                addBtn.innerHTML = originalText;
            }
        }
    }

    async function deleteNote(noteId, clientId) {
        if (!confirm('Are you sure you want to delete this note?')) {
            return;
        }

        try {
            const response = await fetch(`${apiBase}/clients/${clientId}/notes/${noteId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
            });

            const result = await response.json();

            if (result.success) {
                // Reload notes list
                await renderNotes(clientId);
            } else {
                alert('Error deleting note: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error deleting note:', error);
            alert('Error deleting note. Please try again.');
        }
    }

    // Helper function to escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // New Client Modal Functions
    let editingClientId = null;
    let contactsList = [];

    function createClient() {
        editingClientId = null;
        contactsList = [];
        document.getElementById('newClientModalTitle').textContent = 'New Client';
        document.getElementById('submitBtnText').textContent = 'Create Client';
        document.getElementById('newClientForm').reset();
        
        // Reset status to active by default
        document.getElementById('clientStatus').value = 'active';
        
        // Reset tabs to first tab
        document.querySelectorAll('#newClientModal .modal-tab').forEach((tab, index) => {
            if (index === 0) {
                tab.classList.add('active');
            } else {
                tab.classList.remove('active');
            }
        });
        document.querySelectorAll('#newClientModal .modal-tab-content').forEach((content, index) => {
            if (index === 0) {
                content.classList.add('active');
            } else {
                content.classList.remove('active');
            }
        });
        
        // Clear contacts list
        renderContactsList();
        updateContactsCount();
        
        openNewClientModal();
    }

    function openNewClientModal() {
        document.getElementById('newClientModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeNewClientModal() {
        document.getElementById('newClientModal').classList.remove('active');
        document.body.style.overflow = '';
        editingClientId = null;
        contactsList = [];
        document.getElementById('newClientForm').reset();
        renderContactsList();
        updateContactsCount();
    }

    // New Client Modal Tabs
    document.querySelectorAll('#newClientModal .modal-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            const tabId = this.dataset.tab;
            
            document.querySelectorAll('#newClientModal .modal-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            document.querySelectorAll('#newClientModal .modal-tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.getElementById(tabId + 'Tab').classList.add('active');
        });
    });

    // Contact Management Functions
    function generateInitials(name) {
        const words = name.trim().split(' ');
        if (words.length >= 2) {
            return (words[0][0] + words[words.length - 1][0]).toUpperCase();
        }
        return name.substring(0, 2).toUpperCase();
    }

    function updateContactsCount() {
        const count = contactsList.length;
        const countText = document.getElementById('contactsCountText');
        if (countText) {
            countText.textContent = count === 1 ? '1 contact added' : `${count} contacts added`;
        }
    }

    function clearContactForm() {
        document.getElementById('contactName').value = '';
        document.getElementById('contactRole').value = '';
        document.getElementById('contactEmailInput').value = '';
        document.getElementById('contactPhoneInput').value = '';
        document.getElementById('contactName').focus();
    }

    function handleContactFormKeypress(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            addContactToList(false);
        }
    }

    function addContactToList(keepFormOpen = false) {
        const name = document.getElementById('contactName').value.trim();
        const role = document.getElementById('contactRole').value.trim();
        const email = document.getElementById('contactEmailInput').value.trim();
        const phone = document.getElementById('contactPhoneInput').value.trim();

        if (!name) {
            alert('Please enter a contact name');
            document.getElementById('contactName').focus();
            return;
        }

        const contact = {
            id: Date.now(),
            name: name,
            role: role || '',
            email: email || '',
            phone: phone || '',
            initials: generateInitials(name)
        };

        contactsList.push(contact);
        renderContactsList();
        updateContactsCount();

        if (keepFormOpen) {
            // Keep role and other fields, but clear name for next entry
            document.getElementById('contactName').value = '';
            document.getElementById('contactName').focus();
            // Optionally clear email and phone if you want fresh entry each time
            // Or keep them if contacts from same company often have same domain/prefix
        } else {
            // Clear all form fields
            clearContactForm();
        }
    }

    function removeContactFromList(contactId) {
        if (confirm('Are you sure you want to remove this contact?')) {
            contactsList = contactsList.filter(c => c.id !== contactId);
            renderContactsList();
            updateContactsCount();
        }
    }

    function renderContactsList() {
        const container = document.getElementById('contactsListContainer');
        const emptyState = document.getElementById('contactsEmptyState');

        if (contactsList.length === 0) {
            emptyState.style.display = 'block';
            // Remove all contact items except empty state
            container.querySelectorAll('.contact-item-form').forEach(item => item.remove());
            updateContactsCount();
            return;
        }

        emptyState.style.display = 'none';

        // Remove existing contact items
        container.querySelectorAll('.contact-item-form').forEach(item => item.remove());

        // Add contact items
        contactsList.forEach(contact => {
            const contactItem = document.createElement('div');
            contactItem.className = 'contact-item-form';
            contactItem.innerHTML = `
                <div class="contact-avatar">${contact.initials}</div>
                <div class="contact-info">
                    <div class="contact-name">${contact.name}</div>
                    ${contact.role ? `<div class="contact-role">${contact.role}</div>` : ''}
                    ${contact.email ? `<div class="contact-email">${contact.email}</div>` : ''}
                    ${contact.phone ? `<div class="contact-phone">${contact.phone}</div>` : ''}
                </div>
                <div class="contact-actions">
                    <button type="button" class="icon-btn" onclick="removeContactFromList(${contact.id})" title="Remove contact">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"/>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                            <line x1="10" y1="11" x2="10" y2="17"/>
                            <line x1="14" y1="11" x2="14" y2="17"/>
                        </svg>
                    </button>
                </div>
            `;
            container.insertBefore(contactItem, emptyState.nextSibling);
        });
    }

    document.getElementById('newClientModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeNewClientModal();
        }
    });

    async function submitClientForm(event) {
        event.preventDefault();
        
        const form = event.target;
        const formData = new FormData(form);
        
        const clientData = {
            name: formData.get('name'),
            contact_person: formData.get('contactPerson'),
            email: formData.get('email'),
            phone: formData.get('phone') || '',
            industry: formData.get('industry') || '',
            status: formData.get('status'),
            website: formData.get('website') || '',
            revenue: parseFloat(formData.get('revenue')) || 0,
            address: formData.get('address') || '',
            contacts: contactsList.map(contact => ({
                name: contact.name,
                role: contact.role || null,
                email: contact.email || null,
                phone: contact.phone || null,
            }))
        };

        const submitBtn = document.getElementById('submitClientBtn');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span>Saving...</span>';

        try {
            const url = editingClientId 
                ? `${apiBase}/clients/${editingClientId}`
                : `${apiBase}/clients`;
            
            const method = editingClientId ? 'PUT' : 'POST';

            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify(clientData),
            });

            const result = await response.json();

            if (result.success) {
                // Close modal
                closeNewClientModal();
                
                // Reload clients and stats
                await fetchClients(currentPage);
                await fetchStats();
                
                // Show success message
                alert(editingClientId ? 'Client updated successfully!' : 'Client created successfully!');
            } else {
                alert('Error: ' + (result.message || 'Failed to save client'));
            }
        } catch (error) {
            console.error('Error submitting form:', error);
            alert('Error saving client. Please try again.');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    }

    async function editClientById(clientId) {
        try {
            const response = await fetch(`${apiBase}/clients/${clientId}`);
            const result = await response.json();

            if (!result.success) {
                alert('Failed to load client data: ' + (result.message || 'Unknown error'));
                return;
            }

            const client = result.data;
            
            // Close detail modal if open
            if (document.getElementById('clientModal').classList.contains('active')) {
                closeClientModal();
            }
            
            // Populate form with client data
            editingClientId = client.id;
            document.getElementById('newClientModalTitle').textContent = 'Edit Client';
            document.getElementById('submitBtnText').textContent = 'Update Client';
            
            document.getElementById('clientName').value = client.name || '';
            document.getElementById('contactPerson').value = client.contact_person || '';
            document.getElementById('contactEmail').value = client.email || '';
            document.getElementById('contactPhone').value = client.phone || '';
            document.getElementById('clientIndustry').value = client.industry || '';
            document.getElementById('clientStatus').value = client.status || 'active';
            document.getElementById('clientWebsite').value = client.website || '';
            document.getElementById('clientRevenue').value = client.revenue || 0;
            document.getElementById('clientAddress').value = client.address || '';
            
            // Load existing contacts
            contactsList = (client.contacts || []).map(contact => ({
                id: contact.id,
                name: contact.name,
                role: contact.role || '',
                email: contact.email || '',
                phone: contact.phone || '',
                initials: generateInitials(contact.name),
            }));
            renderContactsList();
            updateContactsCount();
            
            // Reset to first tab
            document.querySelectorAll('#newClientModal .modal-tab').forEach((tab, index) => {
                if (index === 0) {
                    tab.classList.add('active');
                } else {
                    tab.classList.remove('active');
                }
            });
            document.querySelectorAll('#newClientModal .modal-tab-content').forEach((content, index) => {
                if (index === 0) {
                    content.classList.add('active');
                } else {
                    content.classList.remove('active');
                }
            });
            
            // Open edit modal
            openNewClientModal();
        } catch (error) {
            console.error('Error loading client:', error);
            alert('Error loading client data. Please try again.');
        }
    }

    function editClient() {
        const modal = document.getElementById('clientModal');
        if (!modal.classList.contains('active')) return;
        
        // Get current client data from modal
        const clientName = document.getElementById('modalClientName').textContent;
        const client = clientsData.find(c => c.name === clientName);
        
        if (!client) return;
        
        // Use the shared function
        editClientById(client.id);
    }

    async function exportClients() {
        try {
            const status = document.getElementById('statusFilter')?.value || 'all';
            const industry = document.getElementById('industryFilter')?.value || 'all';
            
            const params = new URLSearchParams();
            if (status !== 'all') params.append('status', status);
            if (industry !== 'all') params.append('industry', industry);

            const response = await fetch(`${apiBase}/export?${params}`);
            const result = await response.json();

            if (result.success) {
                // Convert to CSV (simple implementation)
                const headers = ['Name', 'Contact Person', 'Email', 'Phone', 'Industry', 'Status', 'Website', 'Revenue', 'Address'];
                const csv = [
                    headers.join(','),
                    ...result.data.map(client => [
                        `"${client.name}"`,
                        `"${client.contact_person}"`,
                        `"${client.email}"`,
                        `"${client.phone || ''}"`,
                        `"${client.industry || ''}"`,
                        `"${client.status}"`,
                        `"${client.website || ''}"`,
                        client.revenue || 0,
                        `"${(client.address || '').replace(/"/g, '""')}"`,
                    ].join(','))
                ].join('\n');

                const blob = new Blob([csv], { type: 'text/csv' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `clients_export_${new Date().toISOString().split('T')[0]}.csv`;
                a.click();
                window.URL.revokeObjectURL(url);
            } else {
                alert('Failed to export clients: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error exporting clients:', error);
            alert('Error exporting clients. Please try again.');
        }
    }

    // Window Resize Handler
    window.addEventListener('resize', updateView);

    // ========================================
    // Portal Users Management
    // ========================================
    let portalUsersData = [];
    let editingPortalUserId = null;

    // Load portal users for a client
    async function loadPortalUsers(clientId) {
        try {
            const response = await fetch(`${apiBase}/clients/${clientId}/users`);
            const result = await response.json();

            if (result.success) {
                portalUsersData = result.data || [];
                renderPortalUsers();
            } else {
                console.error('Failed to load portal users:', result.message);
                portalUsersData = [];
                renderPortalUsers();
            }
        } catch (error) {
            console.error('Error loading portal users:', error);
            portalUsersData = [];
            renderPortalUsers();
        }
    }

    // Render portal users list
    function renderPortalUsers() {
        const container = document.getElementById('portalUsersListContainer');
        const emptyState = document.getElementById('portalUsersEmptyState');

        if (!container) return;

        // Clear existing portal user items only (preserve emptyState)
        container.querySelectorAll('.portal-user-item').forEach(item => item.remove());

        if (!portalUsersData || portalUsersData.length === 0) {
            if (emptyState) emptyState.style.display = 'block';
            return;
        }

        if (emptyState) emptyState.style.display = 'none';

        portalUsersData.forEach(user => {
            const initials = generateInitialsFromName(user.name);
            const portalUserItem = document.createElement('div');
            portalUserItem.className = 'portal-user-item';
            portalUserItem.innerHTML = `
                <div class="portal-user-avatar">${initials}</div>
                <div class="portal-user-info">
                    <div class="portal-user-name">${user.name}</div>
                    <div class="portal-user-email">${user.email}</div>
                    <div class="portal-user-meta">
                        ${user.position ? `<span class="portal-user-position">${user.position}</span>` : ''}
                        ${user.phone ? `<span>${user.phone}</span>` : ''}
                        <span class="portal-user-status ${user.status}">${user.status}</span>
                    </div>
                </div>
                <div class="portal-user-actions">
                    <button class="btn-secondary btn-small" onclick="editPortalUser(${user.id})" title="Edit">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                        </svg>
                    </button>
                    <button class="btn-danger btn-small" onclick="deletePortalUser(${user.id})" title="Delete">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                            <polyline points="3 6 5 6 21 6"/>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        </svg>
                    </button>
                </div>
            `;
            container.appendChild(portalUserItem);
        });
    }

    // Open add portal user modal
    function openAddPortalUserModal() {
        editingPortalUserId = null;
        document.getElementById('portalUserModalTitle').textContent = 'Add Portal User';
        document.getElementById('submitPortalUserBtnText').textContent = 'Create Portal User';
        document.getElementById('portalUserForm').reset();
        document.getElementById('portalUserId').value = '';
        document.getElementById('portalUserPassword').required = true;
        document.getElementById('passwordRequired').style.display = 'inline';
        document.getElementById('passwordHint').textContent = 'Minimum 8 characters';
        document.getElementById('portalUserStatusGroup').style.display = 'none';
        
        document.getElementById('addPortalUserModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    // Close add portal user modal
    function closeAddPortalUserModal() {
        document.getElementById('addPortalUserModal').classList.remove('active');
        document.body.style.overflow = 'hidden'; // Keep hidden since client modal is still open
        editingPortalUserId = null;
    }

    // Edit portal user
    function editPortalUser(userId) {
        const user = portalUsersData.find(u => u.id === userId);
        if (!user) return;

        editingPortalUserId = userId;
        document.getElementById('portalUserModalTitle').textContent = 'Edit Portal User';
        document.getElementById('submitPortalUserBtnText').textContent = 'Update Portal User';
        
        document.getElementById('portalUserId').value = user.id;
        document.getElementById('portalUserName').value = user.name;
        document.getElementById('portalUserEmail').value = user.email;
        document.getElementById('portalUserPhone').value = user.phone || '';
        document.getElementById('portalUserPosition').value = user.position || '';
        document.getElementById('portalUserPassword').value = '';
        document.getElementById('portalUserPassword').required = false;
        document.getElementById('passwordRequired').style.display = 'none';
        document.getElementById('passwordHint').textContent = 'Leave blank to keep current password';
        document.getElementById('portalUserStatus').value = user.status;
        document.getElementById('portalUserStatusGroup').style.display = 'block';
        
        document.getElementById('addPortalUserModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    // Submit portal user form
    async function submitPortalUserForm(event) {
        event.preventDefault();

        if (!currentClientId) {
            alert('No client selected');
            return;
        }

        const formData = {
            name: document.getElementById('portalUserName').value.trim(),
            email: document.getElementById('portalUserEmail').value.trim(),
            phone: document.getElementById('portalUserPhone').value.trim() || null,
            position: document.getElementById('portalUserPosition').value.trim() || null,
        };

        const password = document.getElementById('portalUserPassword').value;
        if (password) {
            formData.password = password;
        }

        if (editingPortalUserId) {
            formData.status = document.getElementById('portalUserStatus').value;
        }

        const submitBtn = document.getElementById('submitPortalUserBtn');
        const originalText = document.getElementById('submitPortalUserBtnText').textContent;
        submitBtn.disabled = true;
        document.getElementById('submitPortalUserBtnText').textContent = 'Saving...';

        try {
            let url, method;
            if (editingPortalUserId) {
                url = `${apiBase}/clients/${currentClientId}/users/${editingPortalUserId}`;
                method = 'PUT';
            } else {
                url = `${apiBase}/clients/${currentClientId}/users`;
                method = 'POST';
            }

            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(formData),
            });

            const result = await response.json();

            if (result.success) {
                closeAddPortalUserModal();
                await loadPortalUsers(currentClientId);
                alert(editingPortalUserId ? 'Portal user updated successfully!' : 'Portal user created successfully!');
            } else {
                alert('Error: ' + (result.message || 'Failed to save portal user'));
            }
        } catch (error) {
            console.error('Error saving portal user:', error);
            alert('Error saving portal user. Please try again.');
        } finally {
            submitBtn.disabled = false;
            document.getElementById('submitPortalUserBtnText').textContent = originalText;
        }
    }

    // Delete portal user
    async function deletePortalUser(userId) {
        if (!confirm('Are you sure you want to delete this portal user? They will no longer be able to access the client portal.')) {
            return;
        }

        try {
            const response = await fetch(`${apiBase}/clients/${currentClientId}/users/${userId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
            });

            const result = await response.json();

            if (result.success) {
                await loadPortalUsers(currentClientId);
                alert('Portal user deleted successfully!');
            } else {
                alert('Error: ' + (result.message || 'Failed to delete portal user'));
            }
        } catch (error) {
            console.error('Error deleting portal user:', error);
            alert('Error deleting portal user. Please try again.');
        }
    }

    // Copy portal URL to clipboard
    function copyPortalUrl() {
        const url = document.getElementById('portalLoginUrl').textContent;
        navigator.clipboard.writeText(url).then(() => {
            // Show temporary success feedback
            const btn = event.target.closest('.btn-icon');
            const originalTitle = btn.title;
            btn.title = 'Copied!';
            btn.style.color = '#10b981';
            setTimeout(() => {
                btn.title = originalTitle;
                btn.style.color = '';
            }, 2000);
        }).catch(err => {
            console.error('Failed to copy:', err);
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = url;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            alert('URL copied to clipboard!');
        });
    }

    // Toggle portal user password visibility
    function togglePortalUserPassword() {
        const passwordInput = document.getElementById('portalUserPassword');
        const eyeIcon = document.getElementById('portalPasswordEye');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            eyeIcon.innerHTML = `
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                <line x1="1" y1="1" x2="23" y2="23"/>
            `;
        } else {
            passwordInput.type = 'password';
            eyeIcon.innerHTML = `
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
            `;
        }
    }

    // Portal user modal click outside to close
    document.getElementById('addPortalUserModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeAddPortalUserModal();
        }
    });

    // Initialize
    document.addEventListener('DOMContentLoaded', async function() {
        await Promise.all([
            fetchClients(1),
            fetchStats()
        ]);
    });

    if (typeof addContactToList === 'function') window.addContactToList = addContactToList;
    if (typeof addNote === 'function') window.addNote = addNote;
    if (typeof assignSelectedEmployees === 'function') window.assignSelectedEmployees = assignSelectedEmployees;
    if (typeof clearContactForm === 'function') window.clearContactForm = clearContactForm;
    if (typeof closeAddEmployeeModal === 'function') window.closeAddEmployeeModal = closeAddEmployeeModal;
    if (typeof closeAddPortalUserModal === 'function') window.closeAddPortalUserModal = closeAddPortalUserModal;
    if (typeof closeAddProjectModal === 'function') window.closeAddProjectModal = closeAddProjectModal;
    if (typeof closeClientModal === 'function') window.closeClientModal = closeClientModal;
    if (typeof closeNewClientModal === 'function') window.closeNewClientModal = closeNewClientModal;
    if (typeof copyPortalUrl === 'function') window.copyPortalUrl = copyPortalUrl;
    if (typeof createClient === 'function') window.createClient = createClient;
    if (typeof deleteNote === 'function') window.deleteNote = deleteNote;
    if (typeof deletePortalUser === 'function') window.deletePortalUser = deletePortalUser;
    if (typeof editClient === 'function') window.editClient = editClient;
    if (typeof editClientById === 'function') window.editClientById = editClientById;
    if (typeof editPortalUser === 'function') window.editPortalUser = editPortalUser;
    if (typeof exportClients === 'function') window.exportClients = exportClients;
    if (typeof openAddEmployeeModal === 'function') window.openAddEmployeeModal = openAddEmployeeModal;
    if (typeof openAddPortalUserModal === 'function') window.openAddPortalUserModal = openAddPortalUserModal;
    if (typeof openClientModal === 'function') window.openClientModal = openClientModal;
    if (typeof removeContactFromList === 'function') window.removeContactFromList = removeContactFromList;
    if (typeof removeEmployeeFromClient === 'function') window.removeEmployeeFromClient = removeEmployeeFromClient;
    if (typeof stopPropagation === 'function') window.stopPropagation = stopPropagation;
    if (typeof toggleEmployeeSelection === 'function') window.toggleEmployeeSelection = toggleEmployeeSelection;
    if (typeof togglePortalUserPassword === 'function') window.togglePortalUserPassword = togglePortalUserPassword;
})();
