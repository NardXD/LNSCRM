/* Vite page entry — IIFE preserves onclick globals */
(function () {
// Current user information
    const CFG = window.__projectManagementConfig || {};
    const currentUserId = CFG.currentUserId;
    const currentUserName = CFG.currentUserName || '';
    const userPermissions = CFG.userPermissions || [];
    
    // Permission checks
    const canCreateProjects = userPermissions.includes('create_project_management');
    const canCreateTasks = userPermissions.includes('create_task_management');
    const canEditProjects = userPermissions.includes('edit_project_management');
    const canDeleteProjects = userPermissions.includes('delete_project_management');
    const canEditTasks = userPermissions.includes('edit_project_management');
    const canDeleteTasks = userPermissions.includes('delete_project_management');
    
    // Tab Switching
    function kebabToCamel(str) {
        return str.replace(/-([a-z])/g, (g) => g[1].toUpperCase());
    }

    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const tabId = this.dataset.tab;
            const camelTabId = kebabToCamel(tabId);
            
            // Update buttons
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            // Update content
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            const tabContent = document.getElementById(camelTabId + 'Tab');
            if (tabContent) {
                tabContent.classList.add('active');
            }
        });
    });

    // Data storage
    let projectsData = [];
    let tasksData = [];
    let tasksPagination = { current_page: 1, last_page: 1, per_page: 10, total: 0 };
    let timeTrackingData = [];
    let timeTrackingPagination = { current_page: 1, last_page: 1, per_page: 10, total: 0 };
    let usersData = [];
    let projectStats = { total: 0, active: 0, completed: 0, on_hold: 0 };
    let activeTimeTrackingMap = {}; // Maps task_id to time tracking record
    let currentTaskStatus = 'all'; // Current selected status tab

    // API base URL
    const apiBase = '/api/project-management';
    const clientApiBase = '/api/client-management';

    // Client Autocomplete Functions
    let autocompleteTimeout = null;
    let selectedClientIndex = -1;

    function initializeClientAutocomplete(inputId, dropdownId, clientIdInputId) {
        const input = document.getElementById(inputId);
        const dropdown = document.getElementById(dropdownId);
        const clientIdInput = document.getElementById(clientIdInputId);

        if (!input || !dropdown || !clientIdInput) return;

        // Clear autocomplete data when input is cleared
        input.addEventListener('input', function() {
            const value = this.value.trim();
            if (!value) {
                clientIdInput.value = '';
                hideAutocomplete(dropdown);
                return;
            }

            clearTimeout(autocompleteTimeout);
            autocompleteTimeout = setTimeout(() => {
                searchClients(value, dropdown, input, clientIdInput);
            }, 300);
        });

        // Handle keyboard navigation
        input.addEventListener('keydown', function(e) {
            const items = dropdown.querySelectorAll('.client-autocomplete-item');
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedClientIndex = Math.min(selectedClientIndex + 1, items.length - 1);
                updateSelectedItem(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedClientIndex = Math.max(selectedClientIndex - 1, -1);
                updateSelectedItem(items);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (selectedClientIndex >= 0 && items[selectedClientIndex]) {
                    items[selectedClientIndex].click();
                } else {
                    // Validate if the current value matches a client
                    validateClient(input, clientIdInput, dropdown);
                }
            } else if (e.key === 'Escape') {
                hideAutocomplete(dropdown);
            }
        });

        // Hide dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!input.contains(e.target) && !dropdown.contains(e.target)) {
                hideAutocomplete(dropdown);
            }
        });

        // Handle input blur - validate before clearing
        input.addEventListener('blur', function() {
            setTimeout(() => {
                if (!dropdown.contains(document.activeElement)) {
                    validateClient(input, clientIdInput, dropdown);
                }
            }, 200);
        });
    }

    async function searchClients(query, dropdown, input, clientIdInput) {
        try {
            const response = await fetch(`${clientApiBase}/clients/search?q=${encodeURIComponent(query)}`);
            const result = await response.json();

            if (result.success && result.data.length > 0) {
                displayAutocompleteResults(result.data, dropdown, input, clientIdInput);
            } else {
                hideAutocomplete(dropdown);
            }
        } catch (error) {
            console.error('Error searching clients:', error);
            hideAutocomplete(dropdown);
        }
    }

    function displayAutocompleteResults(clients, dropdown, input, clientIdInput) {
        selectedClientIndex = -1;
        dropdown.innerHTML = clients.map((client, index) => `
            <div class="client-autocomplete-item" data-client-id="${client.id}" data-client-name="${client.name}" data-index="${index}">
                <div class="client-autocomplete-item-name">${client.name}</div>
            </div>
        `).join('');

        // Add click handlers
        dropdown.querySelectorAll('.client-autocomplete-item').forEach(item => {
            item.addEventListener('click', function() {
                const clientId = this.getAttribute('data-client-id');
                const clientName = this.getAttribute('data-client-name');
                input.value = clientName;
                clientIdInput.value = clientId;
                hideAutocomplete(dropdown);
            });
        });

        dropdown.classList.add('active');
    }

    function updateSelectedItem(items) {
        items.forEach((item, index) => {
            if (index === selectedClientIndex) {
                item.classList.add('selected');
                item.scrollIntoView({ block: 'nearest' });
            } else {
                item.classList.remove('selected');
            }
        });
    }

    function hideAutocomplete(dropdown) {
        dropdown.classList.remove('active');
        selectedClientIndex = -1;
    }

    async function validateClient(input, clientIdInput, dropdown) {
        const value = input.value.trim();
        
        if (!value) {
            clientIdInput.value = '';
            hideAutocomplete(dropdown);
            return;
        }

        // Check if we already have a valid client_id
        if (clientIdInput.value) {
            return;
        }

        // Try to find exact match
        try {
            const response = await fetch(`${clientApiBase}/clients/search?q=${encodeURIComponent(value)}`);
            const result = await response.json();

            if (result.success && result.data.length > 0) {
                // Check for exact match
                const exactMatch = result.data.find(client => 
                    client.name.toLowerCase() === value.toLowerCase()
                );

                if (exactMatch) {
                    clientIdInput.value = exactMatch.id;
                    input.value = exactMatch.name;
                    hideAutocomplete(dropdown);
                } else {
                    // No exact match - clear the field
                    input.value = '';
                    clientIdInput.value = '';
                    hideAutocomplete(dropdown);
                    alert('No matching client found. Please select a client from the suggestions.');
                    input.focus();
                }
            } else {
                // No results - clear the field
                input.value = '';
                clientIdInput.value = '';
                hideAutocomplete(dropdown);
                alert('No matching client found. Please select a client from the suggestions.');
                input.focus();
            }
        } catch (error) {
            console.error('Error validating client:', error);
            input.value = '';
            clientIdInput.value = '';
            hideAutocomplete(dropdown);
        }
    }

    // Fetch data from API
    async function fetchProjects(status = 'all') {
        try {
            const response = await fetch(`${apiBase}/projects?status=${status}`);
            const result = await response.json();
            if (result.success) {
                projectsData = result.data;
                return result.data;
            }
            return [];
        } catch (error) {
            console.error('Error fetching projects:', error);
            return [];
        }
    }

    async function fetchProjectStats() {
        try {
            const response = await fetch(`${apiBase}/projects/stats`);
            const result = await response.json();
            if (result.success) {
                projectStats = result.data;
                updateProjectStats();
                return result.data;
            }
            return null;
        } catch (error) {
            console.error('Error fetching project stats:', error);
            return null;
        }
    }

    async function fetchTasks(projectId = 'all', status = 'all', page = 1) {
        try {
            const response = await fetch(`${apiBase}/tasks?project_id=${projectId}&status=${status}&per_page=${tasksPagination.per_page || 10}&page=${page}`);
            const result = await response.json();
            if (result.success) {
                tasksData = result.data;
                if (result.pagination) {
                    tasksPagination = result.pagination;
                } else {
                    // Default pagination if not provided
                    tasksPagination = {
                        current_page: 1,
                        last_page: 1,
                        per_page: 10,
                        total: result.data ? result.data.length : 0,
                        from: result.data ? 1 : 0,
                        to: result.data ? result.data.length : 0,
                    };
                }
                // Fetch active time tracking records and map to tasks
                await fetchActiveTimeTracking();
                return result.data;
            }
            return [];
        } catch (error) {
            console.error('Error fetching tasks:', error);
            return [];
        }
    }

    async function fetchActiveTimeTracking() {
        try {
            const response = await fetch(`${apiBase}/time-tracking/active-record`);
            const result = await response.json();
            if (result.success && result.data) {
                activeTimeTrackingMap = {};
                result.data.forEach(record => {
                    if (record.task_id) {
                        activeTimeTrackingMap[record.task_id] = record;
                    }
                });
            } else {
                activeTimeTrackingMap = {};
            }
        } catch (error) {
            console.error('Error fetching active time tracking:', error);
            activeTimeTrackingMap = {};
        }
    }

    async function fetchTimeTracking(projectId = 'all', date = null, page = 1) {
        try {
            let url = `${apiBase}/time-tracking?project_id=${projectId}&per_page=${timeTrackingPagination.per_page || 10}&page=${page}`;
            if (date) {
                url += `&date=${date}`;
            }
            const response = await fetch(url);
            const result = await response.json();
            if (result.success) {
                timeTrackingData = result.data;
                if (result.pagination) {
                    timeTrackingPagination = result.pagination;
                } else {
                    // Default pagination if not provided
                    timeTrackingPagination = {
                        current_page: 1,
                        last_page: 1,
                        per_page: 10,
                        total: result.data ? result.data.length : 0,
                        from: result.data ? 1 : 0,
                        to: result.data ? result.data.length : 0,
                    };
                }
                return result.data;
            }
            return [];
        } catch (error) {
            console.error('Error fetching time tracking:', error);
            return [];
        }
    }

    async function fetchTimeTrackingSummary() {
        try {
            const response = await fetch(`${apiBase}/time-tracking/summary`);
            const result = await response.json();
            if (result.success) {
                updateTimeSummary(result.data);
                return result.data;
            }
            return null;
        } catch (error) {
            console.error('Error fetching time tracking summary:', error);
            return null;
        }
    }

    async function fetchUsers() {
        try {
            const response = await fetch(`${apiBase}/users`);
            const result = await response.json();
            if (result.success) {
                usersData = result.data;
                updateTeamSelect();
                return result.data;
            }
            return [];
        } catch (error) {
            console.error('Error fetching users:', error);
            return [];
        }
    }

    // Update stats displays
    function updateProjectStats() {
        const totalEl = document.getElementById('statTotalProjects');
        const activeEl = document.getElementById('statActiveProjects');
        const completedEl = document.getElementById('statCompletedProjects');
        const onHoldEl = document.getElementById('statOnHoldProjects');

        if (totalEl) totalEl.textContent = projectStats.total || 0;
        if (activeEl) activeEl.textContent = projectStats.active || 0;
        if (completedEl) completedEl.textContent = projectStats.completed || 0;
        if (onHoldEl) onHoldEl.textContent = projectStats.on_hold || 0;
    }

    function updateTimeSummary(data) {
        const todayEl = document.querySelector('.time-summary-grid .summary-value');
        if (todayEl && data.today !== undefined) {
            todayEl.textContent = data.today; // Already formatted as HH:MM:SS
        }
        const weekEl = document.querySelectorAll('.time-summary-grid .summary-value')[1];
        if (weekEl && data.this_week !== undefined) {
            weekEl.textContent = data.this_week; // Already formatted as HH:MM:SS
        }
        const monthEl = document.querySelectorAll('.time-summary-grid .summary-value')[2];
        if (monthEl && data.this_month !== undefined) {
            monthEl.textContent = data.this_month; // Already formatted as HH:MM:SS
        }
    }

    // Update task assigned to dropdown based on selected project
    function updateTaskAssignedToDropdown() {
        const taskAssignedSelect = document.getElementById('taskAssignedTo');
        const taskProjectSelect = document.getElementById('taskProject');
        
        if (!taskAssignedSelect || !taskProjectSelect) {
            return;
        }

        const selectedProjectId = taskProjectSelect.value;
        const currentValue = taskAssignedSelect.value;

        if (!selectedProjectId) {
            // No project selected, show empty dropdown
            taskAssignedSelect.innerHTML = '<option value="">Unassigned</option>';
            return;
        }

        // Find the selected project
        const selectedProject = projectsData.find(p => p.id === parseInt(selectedProjectId));
        if (!selectedProject || !selectedProject.team_members) {
            taskAssignedSelect.innerHTML = '<option value="">Unassigned</option>';
            return;
        }

        // Get team member IDs from the project
        const teamMemberIds = selectedProject.team_members.map(member => member.id);
        
        // Filter users to only show project team members
        const availableUsers = usersData.filter(user => teamMemberIds.includes(user.id));
        
        taskAssignedSelect.innerHTML = '<option value="">Unassigned</option>' + 
            availableUsers.map(user => 
                `<option value="${user.id}">${user.name}</option>`
            ).join('');
        
        // Restore previous value if still valid
        if (currentValue && availableUsers.some(u => u.id === parseInt(currentValue))) {
            taskAssignedSelect.value = currentValue;
        } else {
            taskAssignedSelect.value = '';
        }
    }

    // Update edit task assigned to dropdown based on selected project
    function updateEditTaskAssignedToDropdown() {
        const editTaskAssignedSelect = document.getElementById('editTaskAssignedTo');
        const editTaskProjectSelect = document.getElementById('editTaskProject');
        
        if (!editTaskAssignedSelect || !editTaskProjectSelect) {
            return;
        }

        const selectedProjectId = editTaskProjectSelect.value;
        const currentValue = editTaskAssignedSelect.value;

        if (!selectedProjectId) {
            // No project selected, show empty dropdown
            editTaskAssignedSelect.innerHTML = '<option value="">Unassigned</option>';
            return;
        }

        // Find the selected project
        const selectedProject = projectsData.find(p => p.id === parseInt(selectedProjectId));
        if (!selectedProject || !selectedProject.team_members) {
            editTaskAssignedSelect.innerHTML = '<option value="">Unassigned</option>';
            return;
        }

        // Get team member IDs from the project
        const teamMemberIds = selectedProject.team_members.map(member => member.id);
        
        // Filter users to only show project team members
        const availableUsers = usersData.filter(user => teamMemberIds.includes(user.id));
        
        editTaskAssignedSelect.innerHTML = '<option value="">Unassigned</option>' + 
            availableUsers.map(user => 
                `<option value="${user.id}">${user.name}</option>`
            ).join('');
        
        // Restore previous value if still valid
        if (currentValue && availableUsers.some(u => u.id === parseInt(currentValue))) {
            editTaskAssignedSelect.value = currentValue;
        } else {
            editTaskAssignedSelect.value = '';
        }
    }

    function updateTeamSelect() {
        const teamSelect = document.getElementById('projectTeam');
        if (teamSelect && usersData.length > 0) {
            teamSelect.innerHTML = usersData.map(user => 
                `<option value="${user.id}">${user.name} (${user.initials})</option>`
            ).join('');
        }

        // Update task assigned to dropdown based on selected project
        updateTaskAssignedToDropdown();

        // Update task project dropdown (exclude completed projects)
        const taskProjectSelect = document.getElementById('taskProject');
        if (taskProjectSelect && projectsData.length > 0) {
            const currentValue = taskProjectSelect.value;
            // Filter out completed projects
            const activeProjects = projectsData.filter(project => project.status !== 'completed');
            taskProjectSelect.innerHTML = '<option value="">Select project</option>' + 
                activeProjects.map(project => 
                    `<option value="${project.id}">${project.title}</option>`
                ).join('');
            if (currentValue) {
                taskProjectSelect.value = currentValue;
            }
        }
    }

    // Render Projects
    function renderProjects() {
        const grid = document.getElementById('projectsGrid');
        grid.innerHTML = projectsData.map(project => `
            <div class="project-card">
                <div class="project-card-header">
                    <div>
                        <h3 class="project-title">${project.title}</h3>
                        <p class="project-client">${project.client}</p>
                    </div>
                    <span class="project-status-badge ${project.status}">${project.status.charAt(0).toUpperCase() + project.status.slice(1)}</span>
                </div>
                <div class="project-meta">
                    <div class="project-meta-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 11l3 3L22 4"/>
                            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7"/>
                        </svg>
                        ${project.completed}/${project.tasks} tasks
                    </div>
                    <div class="project-meta-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="12 6 12 12 16 14"/>
                        </svg>
                        ${project.deadline}
                    </div>
                </div>
                <div class="project-progress-section">
                    <div class="project-progress-header">
                        <span class="project-progress-label">Progress</span>
                        <span class="project-progress-value">${project.progress}%</span>
                    </div>
                    <div class="project-progress-bar">
                        <div class="project-progress-fill" style="width: ${project.progress}%"></div>
                    </div>
                </div>
                <div class="project-team">
                    <span class="team-label">Team:</span>
                    ${project.team.map(initials => `<div class="team-avatar">${initials}</div>`).join('')}
                </div>
                ${canEditProjects ? `
                <div style="display: flex; gap: 0.5rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border);">
                    <button class="btn-secondary" onclick="openEditProjectModal(${project.id})" style="flex: 1; font-size: 0.875rem;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                        </svg>
                        Edit
                    </button>
                </div>
                ` : ''}
            </div>
        `).join('');
    }

    // Render Tasks
    function renderTasks() {
        const tbody = document.getElementById('tasksTableBody');
        const cards = document.getElementById('tasksCards');

        // Render pagination
        renderTasksPagination();

        if (window.innerWidth > 768) {
            tbody.innerHTML = tasksData.map(task => {
                const isAssignedToCurrentUser = task.assignedTo && task.assignedTo.id === currentUserId;
                const canStartTask = isAssignedToCurrentUser && task.status !== 'done' && task.progress < 100;
                const isTaskActive = activeTimeTrackingMap[task.id] !== undefined;
                
                return `
                <tr>
                    <td>
                        <div style="font-weight: 500; color: var(--text-primary);">${task.title}</div>
                    </td>
                    <td>${task.project}</td>
                    <td>
                        <div class="employee-cell">
                            <div class="employee-avatar">${task.assignedTo ? task.assignedTo.initials : '--'}</div>
                            <span class="employee-name">${task.assignedTo ? task.assignedTo.name : 'Unassigned'}</span>
                        </div>
                    </td>
                    <td><span class="priority-badge ${task.priority}">${task.priority.charAt(0).toUpperCase() + task.priority.slice(1)}</span></td>
                    <td>${task.deadline || '--'}</td>
                    <td><span class="status-badge ${task.status}">${task.status.replace('-', ' ').split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ')}</span></td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            ${isTaskActive && isAssignedToCurrentUser ? `
                            <span style="display: inline-block; width: 8px; height: 8px; background: #dc2626; border-radius: 50%; animation: pulse 2s infinite;" title="Time tracking active"></span>
                            ` : ''}
                            <div class="progress-bar-inline">
                                <div class="progress-fill-inline" style="width: ${task.progress}%"></div>
                            </div>
                            <span style="font-size: 0.75rem; color: var(--text-secondary);">${task.progress}%</span>
                        </div>
                    </td>
                    <td>
                        <div class="table-actions">
                            ${isTaskActive && isAssignedToCurrentUser ? `
                            <button class="icon-btn" title="Stop Task" onclick="openStopTaskModal(${task.id})" style="color: #dc2626;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <rect x="9" y="9" width="6" height="6"/>
                                </svg>
                            </button>
                            ` : canStartTask ? `
                            <button class="icon-btn" title="Start Task" onclick="startTaskTimeTracking(${task.id})" style="color: var(--accent);">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polygon points="10 8 16 12 10 16 10 8"/>
                                </svg>
                            </button>
                            ` : ''}
                            ${(canEditTasks || isAssignedToCurrentUser) ? `
                            <button class="icon-btn" title="Edit" onclick="openEditTaskModal(${task.id})">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                            </button>
                            ` : ''}
                            ${canDeleteTasks ? `
                            <button class="icon-btn" title="Delete" onclick="deleteTask(${task.id})" style="color: #dc2626;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="3 6 5 6 21 6"/>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                </svg>
                            </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
            }).join('');
        } else {
            cards.innerHTML = tasksData.map(task => {
                const isAssignedToCurrentUser = task.assignedTo && task.assignedTo.id === currentUserId;
                const canStartTask = isAssignedToCurrentUser && task.status !== 'done' && task.progress < 100;
                const isTaskActive = activeTimeTrackingMap[task.id] !== undefined;
                
                return `
                <div class="task-card">
                    <div class="card-header">
                        <div>
                            <div class="card-title" style="display: flex; align-items: center; gap: 0.5rem;">
                                ${isTaskActive && isAssignedToCurrentUser ? `
                                <span style="display: inline-block; width: 8px; height: 8px; background: #dc2626; border-radius: 50%; animation: pulse 2s infinite;" title="Time tracking active"></span>
                                ` : ''}
                                ${task.title}
                            </div>
                            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">${task.project}</div>
                        </div>
                        <span class="status-badge ${task.status}">${task.status.replace('-', ' ').split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ')}</span>
                    </div>
                    <div class="card-details">
                        <div class="card-detail">
                            <span class="card-label">Assigned To</span>
                            <span class="card-value">${task.assignedTo ? task.assignedTo.name : 'Unassigned'}</span>
                        </div>
                        <div class="card-detail">
                            <span class="card-label">Priority</span>
                            <span class="card-value"><span class="priority-badge ${task.priority}">${task.priority.charAt(0).toUpperCase() + task.priority.slice(1)}</span></span>
                        </div>
                        <div class="card-detail">
                            <span class="card-label">Deadline</span>
                            <span class="card-value">${task.deadline || '--'}</span>
                        </div>
                        <div class="card-detail">
                            <span class="card-label">Progress</span>
                            <span class="card-value">${task.progress}%</span>
                        </div>
                    </div>
                    <div style="display: flex; gap: 0.5rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border);">
                        ${isTaskActive && isAssignedToCurrentUser ? `
                        <button class="btn-secondary" onclick="openStopTaskModal(${task.id})" style="flex: 1; font-size: 0.875rem; color: #dc2626;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                                <circle cx="12" cy="12" r="10"/>
                                <rect x="9" y="9" width="6" height="6"/>
                            </svg>
                            Stop Task
                        </button>
                        ` : canStartTask ? `
                        <button class="btn-secondary" onclick="startTaskTimeTracking(${task.id})" style="flex: 1; font-size: 0.875rem;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                                <circle cx="12" cy="12" r="10"/>
                                <polygon points="10 8 16 12 10 16 10 8"/>
                            </svg>
                            Start Task
                        </button>
                        ` : ''}
                        ${(canEditTasks || isAssignedToCurrentUser) ? `
                        <button class="btn-secondary" onclick="openEditTaskModal(${task.id})" style="flex: 1; font-size: 0.875rem;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                            </svg>
                            Edit
                        </button>
                        ` : ''}
                        ${canDeleteTasks ? `
                        <button class="btn-secondary" onclick="deleteTask(${task.id})" style="flex: 1; font-size: 0.875rem; color: #dc2626;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                                <polyline points="3 6 5 6 21 6"/>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                            </svg>
                            Delete
                        </button>
                        ` : ''}
                </div>
                </div>
            `;
            }).join('');
        }
    }

    // Render Tasks Pagination
    function renderTasksPagination() {
        const info = document.getElementById('tasksPaginationInfo');
        const numbers = document.getElementById('tasksPaginationNumbers');
        const prevBtn = document.getElementById('tasksPrevBtn');
        const nextBtn = document.getElementById('tasksNextBtn');

        if (!info || !numbers || !prevBtn || !nextBtn) {
            return;
        }

        const { current_page = 1, last_page = 1, from = 0, to = 0, total = 0 } = tasksPagination;
        
        // Use pagination values directly (API returns correct values)
        const displayFrom = from || 0;
        const displayTo = to || 0;
        const displayTotal = total || 0;
        
        info.textContent = displayTotal > 0 
            ? `Showing ${displayFrom} to ${displayTo} of ${displayTotal} tasks`
            : 'No results found';

        prevBtn.disabled = current_page === 1;
        nextBtn.disabled = current_page === last_page || last_page === 0;

        let html = '';
        if (last_page > 1) {
            const maxVisible = 5;
            let startPage = Math.max(1, current_page - Math.floor(maxVisible / 2));
            let endPage = Math.min(last_page, startPage + maxVisible - 1);

            if (endPage - startPage < maxVisible - 1) {
                startPage = Math.max(1, endPage - maxVisible + 1);
            }

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
        }

        numbers.innerHTML = html;
        numbers.querySelectorAll('.pagination-number:not(.ellipsis)').forEach(btn => {
            btn.addEventListener('click', () => {
                goToTasksPage(parseInt(btn.dataset.page));
            });
        });
    }

    // Navigate to tasks page
    async function goToTasksPage(page) {
        if (page < 1 || page > (tasksPagination.last_page || 1)) {
            return;
        }

        const projectId = document.getElementById('taskProjectFilter')?.value || 'all';
        await fetchTasks(projectId, currentTaskStatus, page);
        renderTasks();
    }

    // Render Time Tracking
    function renderTimeTracking() {
        const tbody = document.getElementById('timeTrackingTableBody');
        const cards = document.getElementById('timeTrackingCards');

        // Render pagination
        renderTimeTrackingPagination();

        if (window.innerWidth > 768) {
            tbody.innerHTML = timeTrackingData.map(entry => `
                <tr>
                    <td>${entry.date}</td>
                    <td>${entry.project}</td>
                    <td>${entry.task}</td>
                    <td>
                        <div class="employee-cell">
                            <div class="employee-avatar">${entry.employee.initials}</div>
                            <span class="employee-name">${entry.employee.name}</span>
                        </div>
                    </td>
                    <td>${entry.hours}</td>
                    <td>${entry.description}</td>
                    <td>
                        <div class="table-actions">
                            <button class="icon-btn" title="Edit">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');
        } else {
            cards.innerHTML = timeTrackingData.map(entry => `
                <div class="time-tracking-card">
                    <div class="card-header">
                        <div>
                            <div class="card-title">${entry.task}</div>
                            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">${entry.project}</div>
                        </div>
                        <span style="font-weight: 600; color: var(--accent);">${entry.hours}</span>
                    </div>
                    <div class="card-details">
                        <div class="card-detail">
                            <span class="card-label">Date</span>
                            <span class="card-value">${entry.date}</span>
                        </div>
                        <div class="card-detail">
                            <span class="card-label">Employee</span>
                            <span class="card-value">${entry.employee.name}</span>
                        </div>
                        <div class="card-detail" style="grid-column: 1 / -1;">
                            <span class="card-label">Description</span>
                            <span class="card-value">${entry.description}</span>
                        </div>
                    </div>
                </div>
            `).join('');
        }
    }

    // Render Time Tracking Pagination
    function renderTimeTrackingPagination() {
        const info = document.getElementById('timeTrackingPaginationInfo');
        const numbers = document.getElementById('timeTrackingPaginationNumbers');
        const prevBtn = document.getElementById('timeTrackingPrevBtn');
        const nextBtn = document.getElementById('timeTrackingNextBtn');

        if (!info || !numbers || !prevBtn || !nextBtn) {
            return;
        }

        const { current_page = 1, last_page = 1, from = 0, to = 0, total = 0 } = timeTrackingPagination;
        
        // Use pagination values directly (API returns correct values)
        const displayFrom = from || 0;
        const displayTo = to || 0;
        const displayTotal = total || 0;
        
        info.textContent = displayTotal > 0 
            ? `Showing ${displayFrom} to ${displayTo} of ${displayTotal} entries`
            : 'No results found';

        prevBtn.disabled = current_page === 1;
        nextBtn.disabled = current_page === last_page || last_page === 0;

        let html = '';
        if (last_page > 1) {
            const maxVisible = 5;
            let startPage = Math.max(1, current_page - Math.floor(maxVisible / 2));
            let endPage = Math.min(last_page, startPage + maxVisible - 1);

            if (endPage - startPage < maxVisible - 1) {
                startPage = Math.max(1, endPage - maxVisible + 1);
            }

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
        }

        numbers.innerHTML = html;
        numbers.querySelectorAll('.pagination-number:not(.ellipsis)').forEach(btn => {
            btn.addEventListener('click', () => {
                goToTimeTrackingPage(parseInt(btn.dataset.page));
            });
        });
    }

    // Navigate to time tracking page
    async function goToTimeTrackingPage(page) {
        if (page < 1 || page > (timeTrackingPagination.last_page || 1)) {
            return;
        }

        const projectId = document.getElementById('timeProjectFilter')?.value || 'all';
        const date = document.getElementById('timeDateFilter')?.value || null;
        await fetchTimeTracking(projectId, date, page);
        renderTimeTracking();
    }

    // Populate project filter dropdowns
    function populateProjectFilters() {
        const projectFilters = ['taskProjectFilter', 'timeProjectFilter'];
        projectFilters.forEach(filterId => {
            const select = document.getElementById(filterId);
            if (select) {
                // Keep "All Projects" option and clear rest
                const currentValue = select.value;
                select.innerHTML = '<option value="all">All Projects</option>';

                // Add project options
                projectsData.forEach(project => {
                    const option = document.createElement('option');
                    option.value = project.id;
                    option.textContent = project.title;
                    select.appendChild(option);
                });

                // Restore previous value if still valid
                if (currentValue && currentValue !== 'all') {
                    if (Array.from(select.options).some(opt => opt.value === currentValue)) {
                        select.value = currentValue;
                    }
                }
            }
        });

        // Also update task project dropdown in modal
        updateTeamSelect();

        // Update edit task dropdowns (exclude completed projects, but allow current project even if completed)
        const editTaskProjectSelect = document.getElementById('editTaskProject');
        if (editTaskProjectSelect && projectsData.length > 0) {
            const currentValue = editTaskProjectSelect.value;
            // Filter out completed projects, but include current project if it exists
            const activeProjects = projectsData.filter(project => {
                if (project.status === 'completed') {
                    // Include if it's the currently selected project
                    return currentValue && project.id === parseInt(currentValue);
                }
                return true;
            });
            editTaskProjectSelect.innerHTML = '<option value="">Select project</option>' + 
                activeProjects.map(project => 
                    `<option value="${project.id}">${project.title}</option>`
                ).join('');
            if (currentValue) {
                editTaskProjectSelect.value = currentValue;
            }
        }

        // Update edit task assigned to dropdown based on selected project
        updateEditTaskAssignedToDropdown();

        // Update edit project team dropdown
        const editProjectTeamSelect = document.getElementById('editProjectTeam');
        if (editProjectTeamSelect && usersData.length > 0) {
            const selectedValues = Array.from(editProjectTeamSelect.selectedOptions).map(opt => opt.value);
            editProjectTeamSelect.innerHTML = usersData.map(user => 
                `<option value="${user.id}">${user.name} (${user.initials})</option>`
            ).join('');
            // Restore selected values
            Array.from(editProjectTeamSelect.options).forEach(option => {
                option.selected = selectedValues.includes(option.value);
            });
        }
    }

    // Modal Functions
    function openProjectModal() {
        const modal = document.getElementById('projectModal');
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Initialize autocomplete
        if (typeof initializeClientAutocomplete === 'function') {
            setTimeout(() => {
                initializeClientAutocomplete('projectClient', 'projectClientDropdown', 'projectClientId');
                document.getElementById('projectTitle').focus();
            }, 100);
        } else {
            setTimeout(() => {
                document.getElementById('projectTitle').focus();
            }, 100);
        }
    }

    function closeProjectModal() {
        const modal = document.getElementById('projectModal');
        modal.classList.remove('active');
        document.body.style.overflow = '';
        
        // Reset form and autocomplete
        document.getElementById('projectForm').reset();
        document.getElementById('projectClientId').value = '';
        const dropdown = document.getElementById('projectClientDropdown');
        if (dropdown) {
            dropdown.classList.remove('active');
        }
    }

    // Close modal on backdrop click
    document.getElementById('projectModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeProjectModal();
        }
    });

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const projectModal = document.getElementById('projectModal');
            if (projectModal && projectModal.classList.contains('active')) {
                closeProjectModal();
            }
            const editProjectModal = document.getElementById('editProjectModal');
            if (editProjectModal && editProjectModal.classList.contains('active')) {
                closeEditProjectModal();
            }
        }
    });

    // Edit Project Functions
    async function openEditProjectModal(projectId) {
        const project = projectsData.find(p => p.id === projectId);
        if (!project) {
            alert('Project not found');
            return;
        }

        // Fetch full project details including team members
        try {
            const response = await fetch(`${apiBase}/projects/${projectId}`);
            const result = await response.json();
            
            if (!result.success) {
                alert('Error fetching project details');
                return;
            }

            const projectDetails = result.data;

            const modal = document.getElementById('editProjectModal');
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';

            // Populate dropdowns
            updateTeamSelect();

            // Fill form with project data
            document.getElementById('editProjectId').value = projectDetails.id;
            document.getElementById('editProjectTitle').value = projectDetails.title;
            document.getElementById('editProjectClient').value = projectDetails.client_name || projectDetails.client || '';
            document.getElementById('editProjectClientId').value = projectDetails.client_id || '';
            document.getElementById('editProjectStatus').value = projectDetails.status;
            document.getElementById('editProjectDescription').value = projectDetails.description || '';
            
            // Format deadline for input (YYYY-MM-DD)
            if (projectDetails.deadline) {
                const deadlineDate = new Date(projectDetails.deadline);
                const formattedDate = deadlineDate.toISOString().split('T')[0];
                document.getElementById('editProjectDeadline').value = formattedDate;
            }

            // Set team members (multi-select)
            const teamSelect = document.getElementById('editProjectTeam');
            if (teamSelect && projectDetails.team && Array.isArray(projectDetails.team)) {
                // projectDetails.team should be an array of user IDs
                Array.from(teamSelect.options).forEach(option => {
                    option.selected = projectDetails.team.includes(parseInt(option.value));
                });
            }

            // Initialize autocomplete
            if (typeof initializeClientAutocomplete === 'function') {
                setTimeout(() => {
                    initializeClientAutocomplete('editProjectClient', 'editProjectClientDropdown', 'editProjectClientId');
                    document.getElementById('editProjectTitle').focus();
                }, 100);
            } else {
                setTimeout(() => {
                    document.getElementById('editProjectTitle').focus();
                }, 100);
            }
        } catch (error) {
            console.error('Error fetching project details:', error);
            alert('Error loading project details. Please try again.');
        }
    }

    function closeEditProjectModal() {
        const modal = document.getElementById('editProjectModal');
        modal.classList.remove('active');
        document.body.style.overflow = '';
        
        // Reset form and autocomplete
        document.getElementById('editProjectForm').reset();
        document.getElementById('editProjectClientId').value = '';
        const dropdown = document.getElementById('editProjectClientDropdown');
        if (dropdown) {
            dropdown.classList.remove('active');
        }
    }

    // Close edit project modal on backdrop click
    document.getElementById('editProjectModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeEditProjectModal();
        }
    });

    // Handle edit project form submission
    async function handleEditProjectSubmit(event) {
        event.preventDefault();
        
        const formData = new FormData(event.target);
        const projectId = parseInt(formData.get('project_id'));
            const clientId = formData.get('client_id');
            if (!clientId) {
                alert('Please select a valid client from the suggestions.');
                document.getElementById('editProjectClient').focus();
                return;
            }

            const projectData = {
                title: formData.get('title'),
                client_id: clientId,
                status: formData.get('status'),
                deadline: formData.get('deadline'),
                description: formData.get('description') || '',
                team: formData.getAll('team[]').map(id => parseInt(id))
            };

        try {
            const response = await fetch(`${apiBase}/projects/${projectId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify(projectData)
            });

            const result = await response.json();

            if (result.success) {
                // Reload data
                await fetchProjects();
                await fetchProjectStats();
                populateProjectFilters();
                renderProjects();

                // Close modal
                closeEditProjectModal();

                alert('Project updated successfully!');
            } else {
                alert('Error updating project: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error updating project:', error);
            alert('Error updating project. Please try again.');
        }
    }

    // Handle form submission
    async function handleProjectSubmit(event) {
        event.preventDefault();
        
        const formData = new FormData(event.target);
        const clientId = formData.get('client_id');
        if (!clientId) {
            alert('Please select a valid client from the suggestions.');
            document.getElementById('projectClient').focus();
            return;
        }

        const projectData = {
            title: formData.get('title'),
            client_id: clientId,
            status: formData.get('status'),
            deadline: formData.get('deadline'),
            description: formData.get('description'),
            team: formData.getAll('team[]').map(id => parseInt(id)).filter(id => !isNaN(id))
        };

        try {
            const response = await fetch(`${apiBase}/projects`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify(projectData)
            });

            const result = await response.json();

            if (result.success) {
                // Reload data
                await fetchProjects();
                await fetchProjectStats();
                populateProjectFilters(); // Update filters with new project
                renderProjects();

                // Close modal
                closeProjectModal();

                alert('Project created successfully!');
            } else {
                alert('Error creating project: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error creating project:', error);
            alert('Error creating project. Please try again.');
        }
    }

    // Task Modal Functions
    function openTaskModal() {
        const modal = document.getElementById('taskModal');
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Populate dropdowns
        updateTeamSelect();
        
        // Focus on first input
        setTimeout(() => {
            const taskProject = document.getElementById('taskProject');
            if (taskProject && projectsData.length > 0) {
                taskProject.focus();
            } else {
                document.getElementById('taskTitle').focus();
            }
        }, 100);
    }

    function closeTaskModal() {
        const modal = document.getElementById('taskModal');
        modal.classList.remove('active');
        document.body.style.overflow = '';
        
        // Reset form
        document.getElementById('taskForm').reset();
    }

    // Close task modal on backdrop click
    document.getElementById('taskModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeTaskModal();
        }
    });

    // Close task modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const taskModal = document.getElementById('taskModal');
            if (taskModal && taskModal.classList.contains('active')) {
                closeTaskModal();
            }
            const editTaskModal = document.getElementById('editTaskModal');
            if (editTaskModal && editTaskModal.classList.contains('active')) {
                closeEditTaskModal();
            }
            const stopTaskModal = document.getElementById('stopTaskModal');
            if (stopTaskModal && stopTaskModal.classList.contains('active')) {
                closeStopTaskModal();
            }
        }
    });

    // Handle task form submission
    async function handleTaskSubmit(event) {
        event.preventDefault();
        
        const formData = new FormData(event.target);
        const taskData = {
            project_id: parseInt(formData.get('project_id')),
            title: formData.get('title'),
            description: formData.get('description'),
            priority: formData.get('priority'),
            deadline: formData.get('deadline') || null,
            status: formData.get('status') || 'todo',
            assigned_to: formData.get('assigned_to') ? parseInt(formData.get('assigned_to')) : null,
            progress: parseInt(formData.get('progress') || '0')
        };

        try {
            const response = await fetch(`${apiBase}/tasks`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify(taskData)
            });

            const result = await response.json();

            if (result.success) {
                // Reload data
                const projectId = document.getElementById('taskProjectFilter')?.value || 'all';
                await fetchTasks(projectId, currentTaskStatus, 1);
                await fetchProjects(); // Refresh projects to update task counts
                await fetchProjectStats();
                
                renderTasks();
                renderProjects();

                // Close modal
                closeTaskModal();

                alert('Task created successfully!');
            } else {
                alert('Error creating task: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error creating task:', error);
            alert('Error creating task. Please try again.');
        }
    }

    // Edit Task Functions
    async function openEditTaskModal(taskId) {
        const task = tasksData.find(t => t.id === taskId);
        if (!task) {
            alert('Task not found');
            return;
        }

        const modal = document.getElementById('editTaskModal');
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';

        // Populate dropdowns
        updateTeamSelect();

        // Fill form with task data
        document.getElementById('editTaskId').value = task.id;
        document.getElementById('editTaskTitle').value = task.title;
        document.getElementById('editTaskProject').value = task.project_id;
        document.getElementById('editTaskPriority').value = task.priority;
        document.getElementById('editTaskStatus').value = task.status;
        document.getElementById('editTaskDescription').value = task.description || '';
        document.getElementById('editTaskProgress').value = task.progress || 0;
        document.getElementById('editTaskDeadline').value = task.deadline_raw || '';
        
        // Update assigned to dropdown based on project, then set value
        updateEditTaskAssignedToDropdown();
        
        if (task.assignedTo && task.assignedTo.id) {
            document.getElementById('editTaskAssignedTo').value = task.assignedTo.id;
        } else {
            document.getElementById('editTaskAssignedTo').value = '';
        }

        // Focus on first input
        setTimeout(() => {
            document.getElementById('editTaskTitle').focus();
        }, 100);
    }

    function closeEditTaskModal() {
        const modal = document.getElementById('editTaskModal');
        modal.classList.remove('active');
        document.body.style.overflow = '';
        
        // Reset form
        document.getElementById('editTaskForm').reset();
    }

    // Close edit task modal on backdrop click
    document.getElementById('editTaskModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeEditTaskModal();
        }
    });

    // Handle edit task form submission
    async function handleEditTaskSubmit(event) {
        event.preventDefault();
        
        const formData = new FormData(event.target);
        const taskId = parseInt(formData.get('task_id'));
        const taskData = {
            project_id: parseInt(formData.get('project_id')),
            title: formData.get('title'),
            description: formData.get('description'),
            priority: formData.get('priority'),
            deadline: formData.get('deadline') || null,
            status: formData.get('status') || 'todo',
            assigned_to: formData.get('assigned_to') ? parseInt(formData.get('assigned_to')) : null,
            progress: parseInt(formData.get('progress') || '0')
        };

        try {
            const response = await fetch(`${apiBase}/tasks/${taskId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify(taskData)
            });

            const result = await response.json();

            if (result.success) {
                // Reload data
                const projectId = document.getElementById('taskProjectFilter')?.value || 'all';
                await fetchTasks(projectId, currentTaskStatus, 1);
                await fetchProjects(); // Refresh projects to update task counts
                await fetchProjectStats();
                
                renderTasks();
                renderProjects();

                // Close modal
                closeEditTaskModal();

                alert('Task updated successfully!');
            } else {
                alert('Error updating task: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error updating task:', error);
            alert('Error updating task. Please try again.');
        }
    }

    // Delete Task Function
    async function deleteTask(taskId) {
        if (!confirm('Are you sure you want to delete this task? This action cannot be undone.')) {
            return;
        }

        try {
            const response = await fetch(`${apiBase}/tasks/${taskId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                }
            });

            const result = await response.json();

            if (result.success) {
                // Reload data
                const projectId = document.getElementById('taskProjectFilter')?.value || 'all';
                await fetchTasks(projectId, currentTaskStatus, tasksPagination.current_page);
                await fetchProjects(); // Refresh projects to update task counts
                await fetchProjectStats();
                
                renderTasks();
                renderProjects();

                alert('Task deleted successfully!');
            } else {
                alert('Error deleting task: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error deleting task:', error);
            alert('Error deleting task. Please try again.');
        }
    }

    // Start Task Time Tracking Function
    async function startTaskTimeTracking(taskId) {
        const task = tasksData.find(t => t.id === taskId);
        if (!task) {
            alert('Task not found');
            return;
        }

        if (!task.project_id) {
            alert('Task must be associated with a project');
            return;
        }

        // Get current date and time
        const now = new Date();
        const date = now.toISOString().split('T')[0]; // YYYY-MM-DD
        const time = now.toTimeString().split(' ')[0]; // HH:MM:SS

        try {
            // First, check if there's already an active project time tracking record
            const activeRecordResponse = await fetch(`${apiBase}/time-tracking/active-record`);
            const activeRecordResult = await activeRecordResponse.json();
            
            if (activeRecordResult.success && activeRecordResult.data && activeRecordResult.data.length > 0) {
                const activeTaskRecord = activeRecordResult.data.find(r => r.task_id === taskId);
                if (activeTaskRecord) {
                    alert('You already have an active time tracking session for this task. Please stop it first.');
                    return;
                }
                
                // Check if there's any other active record
                if (activeRecordResult.data.length > 0) {
                    if (!confirm('You already have an active time tracking session for another task. Do you want to stop it and start tracking this task?')) {
                        return;
                    }
                    // Note: We can't stop another task's tracking here, user should stop it manually
                    // Or we could implement a stop-all functionality
                }
            }

            // Start new project time tracking with task information
            const response = await fetch(`${apiBase}/time-tracking/start`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    project_id: task.project_id,
                    task_id: taskId,
                    description: `Working on task: ${task.title}`
                })
            });

            const result = await response.json();

            if (result.success) {
                // Update task status to in-progress if it's still todo
                if (task.status === 'todo') {
                    try {
                        await fetch(`${apiBase}/tasks/${taskId}`, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                            },
                            body: JSON.stringify({ status: 'in-progress' })
                        });
                    } catch (e) {
                        console.error('Error updating task status:', e);
                    }
                }

                alert('Time tracking started for this task!');
                
                // Refresh active tracking map
                await fetchActiveTimeTracking();
                
                // Reload tasks to reflect status change and show stop button
                const projectId = document.getElementById('taskProjectFilter')?.value || 'all';
                await fetchTasks(projectId, currentTaskStatus, tasksPagination.current_page);
                renderTasks();
            } else {
                alert('Error starting time tracking: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error starting time tracking:', error);
            alert('Error starting time tracking. Please try again.');
        }
    }

    // Stop Task Functions
    async function openStopTaskModal(taskId) {
        const task = tasksData.find(t => t.id === taskId);
        if (!task) {
            alert('Task not found');
            return;
        }

        const activeRecord = activeTimeTrackingMap[taskId];
        if (!activeRecord) {
            alert('No active time tracking found for this task');
            return;
        }

        const modal = document.getElementById('stopTaskModal');
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';

        // Fill form with task data
        document.getElementById('stopTaskId').value = task.id;
        document.getElementById('stopTimeTrackingId').value = activeRecord.id;
        document.getElementById('stopTaskTitle').textContent = task.title;
        document.getElementById('stopTaskProject').textContent = task.project;
        document.getElementById('stopTaskCurrentProgress').textContent = task.progress || 0;
        document.getElementById('stopTaskProgress').value = task.progress || 0;
        document.getElementById('stopTaskNotes').value = '';

        // Focus on notes field
        setTimeout(() => {
            document.getElementById('stopTaskNotes').focus();
        }, 100);
    }

    function closeStopTaskModal() {
        const modal = document.getElementById('stopTaskModal');
        modal.classList.remove('active');
        document.body.style.overflow = '';
        
        // Reset form
        document.getElementById('stopTaskForm').reset();
    }

    // Close stop task modal on backdrop click
    document.getElementById('stopTaskModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeStopTaskModal();
        }
    });

    // Handle stop task form submission
    async function handleStopTaskSubmit(event) {
        event.preventDefault();
        
        const formData = new FormData(event.target);
        const taskId = parseInt(formData.get('task_id'));
        const timeTrackingId = parseInt(formData.get('time_tracking_id'));
        const notes = formData.get('notes');
        const progress = parseInt(formData.get('progress'));

        if (!notes || notes.trim() === '') {
            alert('Please enter notes about what was accomplished');
            return;
        }

        if (progress < 0 || progress > 100) {
            alert('Progress must be between 0 and 100');
            return;
        }

        try {
            const response = await fetch(`${apiBase}/tasks/${taskId}/stop-tracking`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    time_tracking_id: timeTrackingId,
                    notes: notes,
                    progress: progress
                })
            });

            const result = await response.json();

            if (result.success) {
                // Refresh active tracking map
                await fetchActiveTimeTracking();
                
                // Reload data
                const projectId = document.getElementById('taskProjectFilter')?.value || 'all';
                await fetchTasks(projectId, currentTaskStatus, tasksPagination.current_page);
                await fetchProjects(); // Refresh projects to update progress
                await fetchProjectStats();
                await fetchTimeTracking(document.getElementById('timeProjectFilter')?.value || 'all', document.getElementById('timeDateFilter')?.value || null, timeTrackingPagination.current_page);
                
                renderTasks();
                renderProjects();
                renderTimeTracking();

                // Close modal
                closeStopTaskModal();

                alert('Task stopped successfully! Time tracked: ' + result.data.hours_worked + ' hours');
            } else {
                alert('Error stopping task: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error stopping task:', error);
            alert('Error stopping task. Please try again.');
        }
    }

    function exportTimeTracking() {
        alert('Exporting time tracking data...');
    }

    // Window Resize Handler
    window.addEventListener('resize', () => {
        renderTasks();
        renderTimeTracking();
    });

    // Initialize - Load data on page load
    async function initialize() {
        await Promise.all([
            fetchProjects(),
            fetchProjectStats(),
            fetchTasks(), // This will also fetch active time tracking
            fetchTimeTracking('all', null, 1),
            fetchTimeTrackingSummary(),
            fetchUsers()
        ]);

        populateProjectFilters();
        renderProjects();
        renderTasks();
        renderTimeTracking();
    }

    // Initialize on page load
    initialize();

    // Status tabs functionality
    document.querySelectorAll('.status-tab-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            // Update active tab
            document.querySelectorAll('.status-tab-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            // Update current status
            currentTaskStatus = this.getAttribute('data-status');
            
            // Reset to first page and fetch tasks
            const projectId = document.getElementById('taskProjectFilter')?.value || 'all';
            await fetchTasks(projectId, currentTaskStatus, 1);
            renderTasks();
        });
    });

    // Update data when filters change
    document.getElementById('taskProjectFilter')?.addEventListener('change', async function() {
        const projectId = this.value;
        await fetchTasks(projectId, currentTaskStatus, 1);
        renderTasks();
    });

    document.getElementById('timeProjectFilter')?.addEventListener('change', async function() {
        const projectId = this.value;
        const date = document.getElementById('timeDateFilter')?.value || null;
        await fetchTimeTracking(projectId, date, 1);
        renderTimeTracking();
    });

    document.getElementById('timeDateFilter')?.addEventListener('change', async function() {
        const date = this.value;
        const projectId = document.getElementById('timeProjectFilter')?.value || 'all';
        await fetchTimeTracking(projectId, date, 1);
        renderTimeTracking();
    });

    // Time Tracking Pagination Event Listeners
    document.getElementById('timeTrackingPrevBtn')?.addEventListener('click', () => {
        if (timeTrackingPagination.current_page > 1) {
            goToTimeTrackingPage(timeTrackingPagination.current_page - 1);
        }
    });

    document.getElementById('timeTrackingNextBtn')?.addEventListener('click', () => {
        if (timeTrackingPagination.current_page < timeTrackingPagination.last_page) {
            goToTimeTrackingPage(timeTrackingPagination.current_page + 1);
        }
    });

    // Update assigned to dropdown when project changes in new task modal
    document.getElementById('taskProject')?.addEventListener('change', function() {
        updateTaskAssignedToDropdown();
    });

    // Update assigned to dropdown when project changes in edit task modal
    document.getElementById('editTaskProject')?.addEventListener('change', function() {
        updateEditTaskAssignedToDropdown();
    });

    // Tasks Pagination Event Listeners
    document.getElementById('tasksPrevBtn')?.addEventListener('click', () => {
        if (tasksPagination.current_page > 1) {
            goToTasksPage(tasksPagination.current_page - 1);
        }
    });

    document.getElementById('tasksNextBtn')?.addEventListener('click', () => {
        if (tasksPagination.current_page < tasksPagination.last_page) {
            goToTasksPage(tasksPagination.current_page + 1);
        }
    });

    // Initialize client autocomplete for both project forms
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize for new project modal
        initializeClientAutocomplete('projectClient', 'projectClientDropdown', 'projectClientId');
        
        // Initialize for edit project modal
        initializeClientAutocomplete('editProjectClient', 'editProjectClientDropdown', 'editProjectClientId');
        
        // Handle URL parameters for tab and project filter
        const urlParams = new URLSearchParams(window.location.search);
        const tabParam = urlParams.get('tab');
        const projectParam = urlParams.get('project');
        
        if (tabParam === 'tasks') {
            // Switch to tasks tab
            const tasksTabBtn = document.querySelector('.tab-btn[data-tab="tasks"]');
            if (tasksTabBtn) {
                tasksTabBtn.click();
            }
            
            // Set project filter if provided
            if (projectParam) {
                setTimeout(() => {
                    const projectFilter = document.getElementById('taskProjectFilter');
                    if (projectFilter) {
                        projectFilter.value = projectParam;
                        // Trigger change event to load tasks
                        projectFilter.dispatchEvent(new Event('change'));
                    }
                }, 500); // Wait a bit for tabs to switch
            }
        }
    });

    if (typeof closeEditProjectModal === 'function') window.closeEditProjectModal = closeEditProjectModal;
    if (typeof closeEditTaskModal === 'function') window.closeEditTaskModal = closeEditTaskModal;
    if (typeof closeProjectModal === 'function') window.closeProjectModal = closeProjectModal;
    if (typeof closeStopTaskModal === 'function') window.closeStopTaskModal = closeStopTaskModal;
    if (typeof closeTaskModal === 'function') window.closeTaskModal = closeTaskModal;
    if (typeof deleteTask === 'function') window.deleteTask = deleteTask;
    if (typeof exportTimeTracking === 'function') window.exportTimeTracking = exportTimeTracking;
    if (typeof openEditProjectModal === 'function') window.openEditProjectModal = openEditProjectModal;
    if (typeof openEditTaskModal === 'function') window.openEditTaskModal = openEditTaskModal;
    if (typeof openProjectModal === 'function') window.openProjectModal = openProjectModal;
    if (typeof openStopTaskModal === 'function') window.openStopTaskModal = openStopTaskModal;
    if (typeof openTaskModal === 'function') window.openTaskModal = openTaskModal;
    if (typeof startTaskTimeTracking === 'function') window.startTaskTimeTracking = startTaskTimeTracking;
})();
