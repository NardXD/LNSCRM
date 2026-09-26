/* Vite page entry — IIFE preserves onclick globals */
(function () {
// State
    let teams = [];
    let currentTeam = null;
    let availableUsers = [];
    let selectedTab = 'teams';
    let selectedDetailTab = 'overview';

    // Pagination state
    let membersPagination = { page: 1, perPage: 10, total: 0, lastPage: 1, search: '' };
    let timeTrackingPagination = { page: 1, perPage: 10, total: 0, lastPage: 1 };
    // Recordings data is stored in teamRecordingsData object

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        loadTeams();
        loadAvailableUsers();
        setupTabs();
        setupColorPicker();
        
        // Close video modal on outside click
        const videoModal = document.getElementById('videoModal');
        if (videoModal) {
            videoModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeVideoModal();
                }
            });
        }
        
        // Close video modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const videoModal = document.getElementById('videoModal');
                const taskModal = document.getElementById('taskTimeTrackingModal');
                if (videoModal && videoModal.classList.contains('active')) {
                    closeVideoModal();
                } else if (taskModal && taskModal.classList.contains('active')) {
                    closeTaskTimeTrackingModal();
                }
            }
        });

        // Close task time tracking modal on outside click
        const taskTimeTrackingModal = document.getElementById('taskTimeTrackingModal');
        if (taskTimeTrackingModal) {
            taskTimeTrackingModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeTaskTimeTrackingModal();
                }
            });
        }
    });

    // Setup tabs
    function setupTabs() {
        // Main tabs
        document.querySelectorAll('.tab-btn[data-tab]').forEach(btn => {
            btn.addEventListener('click', function() {
                const tab = this.dataset.tab;
                document.querySelectorAll('.tab-btn[data-tab]').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                // Convert tab name to proper ID (my-team -> myTeam)
                const tabId = tab.replace(/-([a-z])/g, (g) => g[1].toUpperCase()) + 'Tab';
                const tabElement = document.getElementById(tabId);
                if (tabElement) {
                    tabElement.classList.add('active');
                }
                selectedTab = tab;

                if (tab === 'my-team') {
                    loadMyTeams();
                }
            });
        });

        // Detail tabs
        document.querySelectorAll('.tab-btn[data-detail-tab]').forEach(btn => {
            btn.addEventListener('click', function() {
                const tab = this.dataset.detailTab;
                document.querySelectorAll('.tab-btn[data-detail-tab]').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                selectedDetailTab = tab;
                loadTeamDetailContent(tab);
            });
        });
    }

    // Setup color picker
    function setupColorPicker() {
        const colorPicker = document.getElementById('teamColor');
        const colorPreview = document.getElementById('colorPreview');

        colorPicker.addEventListener('input', function() {
            colorPreview.style.background = this.value;
        });
    }

    // Load all teams
    async function loadTeams() {
        try {
            const response = await fetch('/api/team-management/teams', {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            const data = await response.json();

            if (data.success) {
                teams = data.data;
                renderTeams();
            }
        } catch (error) {
            console.error('Error loading teams:', error);
        }
    }

    // Load my teams
    async function loadMyTeams() {
        const container = document.getElementById('myTeamsContainer');
        container.innerHTML = '<div class="loading-spinner"></div>';

        try {
            const response = await fetch('/api/team-management/teams', {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            const data = await response.json();

            if (data.success) {
                // Filter to user's teams (where they are leader or member)
                const CFG = window.__teamManagementConfig || {};
    const userId = CFG.userId;
                const myTeams = data.data.filter(team => {
                    if (team.leader && team.leader.id === userId) return true;
                    return team.members.some(m => m.id === userId);
                });

                if (myTeams.length === 0) {
                    container.innerHTML = `
                        <div class="empty-state">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                            <h3>No Teams Found</h3>
                            <p>You are not a member of any team yet.</p>
                        </div>
                    `;
                } else {
                    container.innerHTML = `<div class="teams-grid">${myTeams.map(renderTeamCard).join('')}</div>`;
                }
            }
        } catch (error) {
            console.error('Error loading my teams:', error);
            container.innerHTML = '<p class="text-center text-muted">Error loading teams</p>';
        }
    }

    // Load available users
    async function loadAvailableUsers() {
        try {
            const response = await fetch('/api/team-management/users', {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            const data = await response.json();

            if (data.success) {
                availableUsers = data.data;
                populateLeaderSelect();
            }
        } catch (error) {
            console.error('Error loading users:', error);
        }
    }

    // Populate leader select
    function populateLeaderSelect() {
        const select = document.getElementById('teamLeader');
        select.innerHTML = '<option value="">Select Leader</option>';
        
        availableUsers.forEach(user => {
            select.innerHTML += `<option value="${user.id}">${user.name}</option>`;
        });
    }

    // Populate member selection
    function populateMemberSelection(excludeLeaderId = null, existingMemberIds = []) {
        const container = document.getElementById('memberSelection');
        container.innerHTML = '';

        availableUsers.forEach(user => {
            if (user.id == excludeLeaderId) return;

            const isSelected = existingMemberIds.includes(user.id);
            container.innerHTML += `
                <label class="user-option ${isSelected ? 'selected' : ''}">
                    <input type="checkbox" name="member_ids[]" value="${user.id}" ${isSelected ? 'checked' : ''} onchange="this.parentElement.classList.toggle('selected')">
                    <div class="leader-avatar" style="width: 32px; height: 32px; font-size: 0.7rem;">
                        ${user.photo ? `<img src="${user.photo}" alt="${user.name}">` : user.initials}
                    </div>
                    <div class="member-info">
                        <div class="member-name">${user.name}</div>
                        <div class="member-email">${user.email}</div>
                    </div>
                </label>
            `;
        });
    }

    // Render teams grid
    function renderTeams() {
        const grid = document.getElementById('teamsGrid');

        if (teams.length === 0) {
            grid.innerHTML = `
                <div class="empty-state" style="grid-column: 1 / -1;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                    <h3>No Teams Yet</h3>
                    <p>Create your first team to start organizing your workforce.</p>
                </div>
            `;
            return;
        }

        grid.innerHTML = teams.map(renderTeamCard).join('');
    }

    // Render team card
    function renderTeamCard(team) {
        const membersPreview = team.members.slice(0, 4);
        const extraMembers = team.members.length - 4;

        return `
            <div class="team-card" style="--team-color: ${team.color};" onclick="viewTeam(${team.id})">
                <div class="team-header">
                    <div class="team-info">
                        <h3 class="team-name">${team.name}</h3>
                    </div>
                    <div class="team-actions" onclick="event.stopPropagation()">
                        ${CFG.canEdit ? `
                        <button class="icon-btn" title="Edit" onclick="editTeam(${team.id})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                            </svg>
                        </button>
                        ` : ''}
                        ${CFG.canDelete ? `
                        <button class="icon-btn" title="Delete" onclick="deleteTeam(${team.id})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"/>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                            </svg>
                        </button>
                        ` : ''}
                    </div>
                </div>

                <p class="team-description">${team.description || 'No description'}</p>

                ${team.leader ? `
                <div class="team-leader">
                    <div class="leader-avatar">
                        ${team.leader.photo ? `<img src="${team.leader.photo}" alt="${team.leader.name}">` : team.leader.initials}
                    </div>
                    <div class="leader-info">
                        <div class="leader-label">Team Leader</div>
                        <div class="leader-name">${team.leader.name}</div>
                    </div>
                </div>
                ` : ''}

                <div class="team-members-preview">
                    <div class="members-avatars">
                        ${membersPreview.map(m => `
                            <div class="member-avatar" title="${m.name}">
                                ${m.photo ? `<img src="${m.photo}" alt="${m.name}">` : m.initials}
                            </div>
                        `).join('')}
                        ${extraMembers > 0 ? `<div class="member-avatar more">+${extraMembers}</div>` : ''}
                    </div>
                    <span class="members-count">${team.members_count} member${team.members_count !== 1 ? 's' : ''}</span>
                </div>

                <div class="team-stats">
                    <div class="stat-item">
                        <div class="stat-value">${team.members_count + (team.leader ? 1 : 0)}</div>
                        <div class="stat-label">Total</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">${team.projects_count}</div>
                        <div class="stat-label">Projects</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">${team.is_active ? '●' : '○'}</div>
                        <div class="stat-label">${team.is_active ? 'Active' : 'Inactive'}</div>
                    </div>
                </div>
            </div>
        `;
    }

    // Filter teams
    function filterTeams() {
        const search = document.getElementById('teamSearch').value.toLowerCase();
        const grid = document.getElementById('teamsGrid');

        const filtered = teams.filter(team => 
            team.name.toLowerCase().includes(search) ||
            (team.description && team.description.toLowerCase().includes(search))
        );

        if (filtered.length === 0) {
            grid.innerHTML = `
                <div class="empty-state" style="grid-column: 1 / -1;">
                    <p>No teams found matching your search.</p>
                </div>
            `;
        } else {
            grid.innerHTML = filtered.map(renderTeamCard).join('');
        }
    }

    // View team details
    async function viewTeam(teamId) {
        try {
            const response = await fetch(`/api/team-management/teams/${teamId}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            const data = await response.json();

            if (data.success) {
                currentTeam = data.data;
                showTeamDetails();
            }
        } catch (error) {
            console.error('Error loading team:', error);
        }
    }

    // Show team details view
    function showTeamDetails() {
        document.getElementById('teamsListView').style.display = 'none';
        document.getElementById('teamDetailsView').style.display = 'block';

        // Reset to overview tab
        document.querySelectorAll('.tab-btn[data-detail-tab]').forEach(b => b.classList.remove('active'));
        document.querySelector('.tab-btn[data-detail-tab="overview"]').classList.add('active');
        selectedDetailTab = 'overview';

        loadTeamDetailContent('overview');
    }

    // Show teams list
    function showTeamsList() {
        document.getElementById('teamDetailsView').style.display = 'none';
        document.getElementById('teamsListView').style.display = 'block';
        currentTeam = null;
    }

    // Load team detail content
    async function loadTeamDetailContent(tab) {
        const container = document.getElementById('teamDetailContent');

        switch (tab) {
            case 'overview':
                loadTeamOverview(container);
                break;
            case 'members':
                loadTeamMembers(container);
                break;
            case 'time-tracking':
                loadTeamTimeTracking(container);
                break;
            case 'recordings':
                loadTeamRecordings(container);
                break;
            case 'tasks':
                loadTeamTasks(container);
                break;
        }
    }

    // Load team overview
    async function loadTeamOverview(container) {
        try {
            const response = await fetch(`/api/team-management/teams/${currentTeam.id}/stats`, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            const data = await response.json();
            const stats = data.success ? data.data : { total_members: 0, today_attendance: 0, active_projects: 0, completed_tasks_this_week: 0 };

            container.innerHTML = `
                <div class="section-header">
                    <h2 class="section-title">${currentTeam.name}</h2>
                </div>
                <p style="color: var(--text-secondary); margin-bottom: 2rem;">${currentTeam.description || 'No description provided.'}</p>

                <!-- Stats Summary Grid -->
                <div class="summary-grid">
                    <div class="summary-card">
                        <div class="summary-header">
                            <span class="summary-label">Team Members</span>
                            <div class="summary-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                </svg>
                            </div>
                        </div>
                        <div class="summary-value">${stats.total_members}</div>
                    </div>

                    <div class="summary-card">
                        <div class="summary-header">
                            <span class="summary-label">Present Today</span>
                            <div class="summary-icon success">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="9 11 12 14 22 4"/>
                                    <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                                </svg>
                            </div>
                        </div>
                        <div class="summary-value">${stats.today_attendance}</div>
                    </div>

                    <div class="summary-card">
                        <div class="summary-header">
                            <span class="summary-label">Active Projects</span>
                            <div class="summary-icon info">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="summary-value">${stats.active_projects}</div>
                    </div>

                    <div class="summary-card">
                        <div class="summary-header">
                            <span class="summary-label">Tasks This Week</span>
                            <div class="summary-icon purple">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="9 11 12 14 22 4"/>
                                    <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                                </svg>
                            </div>
                        </div>
                        <div class="summary-value">${stats.completed_tasks_this_week}</div>
                    </div>
                </div>

                ${currentTeam.leader ? `
                <div class="section-header" style="margin-top: 2rem;">
                    <h2 class="section-title">Team Leader</h2>
                </div>
                <div class="leader-card">
                    <div class="leader-avatar">
                        ${currentTeam.leader.photo ? `<img src="${currentTeam.leader.photo}" alt="${currentTeam.leader.name}">` : currentTeam.leader.initials}
                    </div>
                    <div class="leader-info">
                        <div class="leader-name">${currentTeam.leader.name}</div>
                        <div style="font-size: 0.875rem; color: var(--text-secondary);">${currentTeam.leader.email}</div>
                    </div>
                </div>
                ` : ''}
            `;
        } catch (error) {
            console.error('Error loading overview:', error);
            container.innerHTML = '<p class="text-center text-muted">Error loading overview</p>';
        }
    }

    // Load team members
    async function loadTeamMembers(container) {
        // Reset pagination when loading new
        membersPagination = { page: 1, perPage: 10, total: 0, lastPage: 1, search: '' };

        container.innerHTML = `
            <div class="section-header">
                <h2 class="section-title">Team Members</h2>
                <div class="section-actions">
                    <div class="search-box">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/>
                            <path d="M21 21l-4.35-4.35"/>
                        </svg>
                        <input type="text" id="memberSearch" placeholder="Search members..." onkeyup="searchMembers(this.value)">
                    </div>
                    <select class="filter-select" id="membersPerPage" onchange="changeMembersPerPage(this.value)">
                        <option value="10">10 per page</option>
                        <option value="25">25 per page</option>
                        <option value="50">50 per page</option>
                    </select>
                    ${CFG.canEdit ? `
                    <button class="btn-primary" onclick="openAddMembersModal()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="8.5" cy="7" r="4"/>
                            <line x1="20" y1="8" x2="20" y2="14"/>
                            <line x1="23" y1="11" x2="17" y2="11"/>
                        </svg>
                        Add Members
                    </button>
                    ` : ''}
                </div>
            </div>
            <div id="membersContent">
                <div class="loading-container"><div class="loading-spinner"></div></div>
            </div>
        `;

        await refreshMembers();
    }

    let memberSearchTimeout = null;
    function searchMembers(value) {
        clearTimeout(memberSearchTimeout);
        memberSearchTimeout = setTimeout(() => {
            membersPagination.search = value;
            membersPagination.page = 1;
            refreshMembers();
        }, 300);
    }

    async function refreshMembers() {
        const container = document.getElementById('membersContent');
        
        if (!container) {
            console.error('membersContent container not found');
            return;
        }

        try {
            const searchParam = membersPagination.search ? `&search=${encodeURIComponent(membersPagination.search)}` : '';
            const url = `/api/team-management/teams/${currentTeam.id}/members?page=${membersPagination.page}&per_page=${membersPagination.perPage}${searchParam}`;
            console.log('Fetching members from:', url);
            
            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            const data = await response.json();
            console.log('Members API response:', data);

            if (data.success) {
                // Update pagination state
                if (data.pagination) {
                    membersPagination.total = data.pagination.total;
                    membersPagination.lastPage = data.pagination.last_page;
                    membersPagination.page = data.pagination.current_page;
                    console.log('Updated pagination:', membersPagination);
                }

                if (data.data.length > 0) {
                    container.innerHTML = `
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Member</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${data.data.map(member => `
                                        <tr>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                    <div class="member-avatar-sm">
                                                        ${member.photo ? `<img src="${member.photo}" alt="${member.name}" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">` : member.initials}
                                                    </div>
                                                    <span>${member.name}</span>
                                                </div>
                                            </td>
                                            <td>${member.email}</td>
                                            <td><span class="status-badge ${member.role}">${member.role === 'leader' ? 'Team Leader' : (member.role === 'co-leader' ? 'Co-Leader' : 'Member')}</span></td>
                                            <td>
                                                ${member.role !== 'leader' ? `
                                                <div class="team-actions" style="justify-content: flex-start;">
                                                    ${CFG.canEdit ? `
                                                    <button class="icon-btn" title="${member.role === 'member' ? 'Set as Co-Leader' : 'Set as Member'}" onclick="changeMemberRole(${member.id}, '${member.role}')">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                                        </svg>
                                                    </button>
                                                    <button class="icon-btn" title="Remove" onclick="removeMember(${member.id})">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <polyline points="3 6 5 6 21 6"/>
                                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                                        </svg>
                                                    </button>
                                                    ` : ''}
                                                </div>
                                                ` : '<span style="color: var(--text-muted);">—</span>'}
                                            </td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                        ${renderMembersPagination()}
                    `;
                } else {
                    container.innerHTML = `
                        <div class="empty-state">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                            <h3>No Members Found</h3>
                            <p>${membersPagination.search ? 'No members found matching your search.' : 'No members in this team yet.'}</p>
                        </div>
                    `;
                }
            }
        } catch (error) {
            console.error('Error loading members:', error);
            container.innerHTML = '<p class="text-center text-muted">Error loading members</p>';
        }
    }

    function renderMembersPagination() {
        const { page, perPage, total, lastPage } = membersPagination;
        if (total === 0) return '';

        const startRecord = (page - 1) * perPage + 1;
        const endRecord = Math.min(page * perPage, total);
        const showNavigation = lastPage > 1;

        let paginationHtml = `
            <div class="table-pagination">
                <div class="pagination-info">
                    Showing ${startRecord} to ${endRecord} of ${total} members
                </div>
                <div class="pagination-controls">
        `;

        if (showNavigation) {
            paginationHtml += `
                <button class="pagination-btn" onclick="goToMembersPage(${page - 1})" ${page === 1 ? 'disabled' : ''}>Previous</button>
                <div class="pagination-numbers">
            `;

            // Page numbers
            const maxVisiblePages = 5;
            let startPage = Math.max(1, page - Math.floor(maxVisiblePages / 2));
            let endPage = Math.min(lastPage, startPage + maxVisiblePages - 1);
            if (endPage - startPage < maxVisiblePages - 1) {
                startPage = Math.max(1, endPage - maxVisiblePages + 1);
            }

            for (let i = startPage; i <= endPage; i++) {
                paginationHtml += `<button class="pagination-btn ${i === page ? 'active' : ''}" onclick="goToMembersPage(${i})">${i}</button>`;
            }

            paginationHtml += `
                </div>
                <button class="pagination-btn" onclick="goToMembersPage(${page + 1})" ${page === lastPage ? 'disabled' : ''}>Next</button>
            `;
        }

        paginationHtml += `
                </div>
            </div>
        `;

        return paginationHtml;
    }

    function goToMembersPage(page) {
        membersPagination.page = page;
        refreshMembers();
    }

    function changeMembersPerPage(perPage) {
        membersPagination.perPage = parseInt(perPage);
        membersPagination.page = 1;
        refreshMembers();
    }

    // Get current week's Monday and Sunday
    function getWeekDates() {
        const today = new Date();
        const dayOfWeek = today.getDay();
        // Calculate Monday (day 1) - if Sunday (0), go back 6 days, otherwise go back (dayOfWeek - 1) days
        const mondayOffset = dayOfWeek === 0 ? -6 : 1 - dayOfWeek;
        const monday = new Date(today);
        monday.setDate(today.getDate() + mondayOffset);
        
        // Sunday is Monday + 6 days
        const sunday = new Date(monday);
        sunday.setDate(monday.getDate() + 6);
        
        return {
            start: monday.toISOString().split('T')[0],
            end: sunday.toISOString().split('T')[0]
        };
    }

    // Load team time tracking
    async function loadTeamTimeTracking(container) {
        const weekDates = getWeekDates();
        const startDate = weekDates.start;
        const endDate = weekDates.end;

        // Reset pagination when loading new
        timeTrackingPagination = { page: 1, perPage: 10, total: 0, lastPage: 1 };

        container.innerHTML = `
            <div class="section-header">
                <h2 class="section-title">Time Tracking</h2>
                <div class="section-actions">
                    <div class="date-range-filter">
                        <input type="date" class="date-input" id="ttStartDate" value="${startDate}" onchange="goToTimeTrackingPage(1)">
                        <span class="date-range-separator">to</span>
                        <input type="date" class="date-input" id="ttEndDate" value="${endDate}" onchange="goToTimeTrackingPage(1)">
                    </div>
                    <select class="filter-select" id="ttPerPage" onchange="changeTimeTrackingPerPage(this.value)">
                        <option value="10">10 per page</option>
                        <option value="25">25 per page</option>
                        <option value="50">50 per page</option>
                    </select>
                </div>
            </div>
            <div id="timeTrackingContent">
                <div class="loading-container"><div class="loading-spinner"></div></div>
            </div>
        `;

        await refreshTimeTracking();
    }

    async function refreshTimeTracking() {
        const startDate = document.getElementById('ttStartDate').value;
        const endDate = document.getElementById('ttEndDate').value;
        const container = document.getElementById('timeTrackingContent');

        if (!container) {
            console.error('timeTrackingContent container not found');
            return;
        }

        try {
            const url = `/api/team-management/teams/${currentTeam.id}/time-tracking?start_date=${startDate}&end_date=${endDate}&page=${timeTrackingPagination.page}&per_page=${timeTrackingPagination.perPage}`;
            console.log('Fetching time tracking from:', url);
            
            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            const data = await response.json();
            console.log('Time tracking API response:', data);

            if (data.success) {
                // Update pagination state
                if (data.pagination) {
                    timeTrackingPagination.total = data.pagination.total;
                    timeTrackingPagination.lastPage = data.pagination.last_page;
                    timeTrackingPagination.page = data.pagination.current_page;
                    console.log('Updated time tracking pagination:', timeTrackingPagination);
                }

                if (data.data.records.length > 0) {
                    container.innerHTML = `
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Date</th>
                                        <th>Time In</th>
                                        <th>Time Out</th>
                                        <th>Hours Worked</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${data.data.records.map(record => `
                                        <tr>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                    <div class="member-avatar-sm">${record.user_initials}</div>
                                                    <span>${record.user_name}</span>
                                                </div>
                                            </td>
                                            <td>${record.date}</td>
                                            <td>${record.time_in || '--'}</td>
                                            <td>${record.time_out || '--'}</td>
                                            <td><strong>${record.hours_worked}</strong></td>
                                            <td><span class="status-badge ${record.status}">${record.status}</span></td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                        ${renderTimeTrackingPagination()}
                    `;
                } else {
                    container.innerHTML = `
                        <div class="empty-state">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                            <h3>No Records Found</h3>
                            <p>No time tracking records found for this period.</p>
                        </div>
                    `;
                }
            }
        } catch (error) {
            console.error('Error loading time tracking:', error);
            container.innerHTML = '<p class="text-center text-muted">Error loading time tracking data</p>';
        }
    }

    function renderTimeTrackingPagination() {
        const { page, perPage, total, lastPage } = timeTrackingPagination;
        if (total === 0) return '';

        const startRecord = (page - 1) * perPage + 1;
        const endRecord = Math.min(page * perPage, total);
        const showNavigation = lastPage > 1;

        let paginationHtml = `
            <div class="table-pagination">
                <div class="pagination-info">
                    Showing ${startRecord} to ${endRecord} of ${total} records
                </div>
                <div class="pagination-controls">
        `;

        if (showNavigation) {
            paginationHtml += `
                <button class="pagination-btn" onclick="goToTimeTrackingPage(${page - 1})" ${page === 1 ? 'disabled' : ''}>Previous</button>
                <div class="pagination-numbers">
            `;

            // Page numbers
            const maxVisiblePages = 5;
            let startPage = Math.max(1, page - Math.floor(maxVisiblePages / 2));
            let endPage = Math.min(lastPage, startPage + maxVisiblePages - 1);
            if (endPage - startPage < maxVisiblePages - 1) {
                startPage = Math.max(1, endPage - maxVisiblePages + 1);
            }

            for (let i = startPage; i <= endPage; i++) {
                paginationHtml += `<button class="pagination-btn ${i === page ? 'active' : ''}" onclick="goToTimeTrackingPage(${i})">${i}</button>`;
            }

            paginationHtml += `
                </div>
                <button class="pagination-btn" onclick="goToTimeTrackingPage(${page + 1})" ${page === lastPage ? 'disabled' : ''}>Next</button>
            `;
        }

        paginationHtml += `
                </div>
            </div>
        `;

        return paginationHtml;
    }

    function goToTimeTrackingPage(page) {
        timeTrackingPagination.page = page;
        refreshTimeTracking();
    }

    function changeTimeTrackingPerPage(perPage) {
        timeTrackingPagination.perPage = parseInt(perPage);
        timeTrackingPagination.page = 1;
        refreshTimeTracking();
    }

    // Store recordings data grouped by user
    let teamRecordingsData = {};

    // Load team recordings
    async function loadTeamRecordings(container) {
        const weekDates = getWeekDates();
        const startDate = weekDates.start;
        const endDate = weekDates.end;

        container.innerHTML = `
            <div class="section-header">
                <h2 class="section-title">Screen Recordings</h2>
                <div class="section-actions">
                    <div class="date-range-filter">
                        <input type="date" class="date-input" id="recStartDate" value="${startDate}" onchange="refreshRecordings()">
                        <span class="date-range-separator">to</span>
                        <input type="date" class="date-input" id="recEndDate" value="${endDate}" onchange="refreshRecordings()">
                    </div>
                </div>
            </div>
            <div id="recordingsContent">
                <div class="loading-container"><div class="loading-spinner"></div></div>
            </div>
        `;

        await refreshRecordings();
    }

    async function refreshRecordings() {
        const startDate = document.getElementById('recStartDate').value;
        const endDate = document.getElementById('recEndDate').value;
        const container = document.getElementById('recordingsContent');

        try {
            const response = await fetch(`/api/team-management/teams/${currentTeam.id}/recordings?start_date=${startDate}&end_date=${endDate}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            const data = await response.json();

            if (data.success) {
                teamRecordingsData = {};
                // Store recordings by user_id for easy access
                data.data.forEach(userGroup => {
                    teamRecordingsData[userGroup.user_id] = userGroup;
                });

                if (data.data.length > 0) {
                    container.innerHTML = `
                        <div class="recordings-grouped-grid">
                            ${data.data.map(userGroup => `
                                <div class="employee-monitor-card">
                                    <div class="monitor-card-header">
                                        <div class="employee-info">
                                            <div class="employee-avatar">
                                                ${userGroup.user_photo ? `<img src="${userGroup.user_photo}" alt="${userGroup.user_name}" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">` : userGroup.user_initials}
                                            </div>
                                            <div>
                                                <div class="employee-name">${userGroup.user_name}</div>
                                                <div class="employee-meta">
                                                    <span>${userGroup.user_email}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="monitor-stats">
                                            <div class="stat-item">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <polygon points="23 7 16 12 23 17 23 7"/>
                                                    <rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>
                                                </svg>
                                                <span>${userGroup.total_videos} Videos</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="monitor-content active">
                                        <div class="media-grid" id="videos-${userGroup.user_id}">
                                            <div style="grid-column: 1 / -1; text-align: center; padding: 2rem; color: var(--text-muted);">Loading videos...</div>
                                        </div>
                                        ${userGroup.total_videos > 2 ? `
                                        <div class="view-more">
                                            <button class="btn-secondary" onclick="viewAllTeamVideos(${userGroup.user_id})">View All ${userGroup.total_videos} Videos</button>
                                        </div>
                                        ` : ''}
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    `;

                    // Render videos for each user
                    setTimeout(() => {
                        data.data.forEach(userGroup => {
                            renderTeamUserVideos(userGroup.user_id, userGroup.recordings);
                        });
                    }, 100);
                } else {
                    container.innerHTML = `
                        <div class="empty-state">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="23 7 16 12 23 17 23 7"/>
                                <rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>
                            </svg>
                            <h3>No Recordings Found</h3>
                            <p>No recordings found for this period.</p>
                        </div>
                    `;
                }
            }
        } catch (error) {
            console.error('Error loading recordings:', error);
            container.innerHTML = '<p class="text-center text-muted">Error loading recordings</p>';
        }
    }

    function renderTeamUserVideos(userId, videos) {
        const container = document.getElementById(`videos-${userId}`);
        if (!container) return;

        if (!videos || videos.length === 0) {
            container.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; padding: 2rem; color: var(--text-muted);">No videos available</div>';
            return;
        }

        // Limit to first 2 videos for display
        const videosToShow = videos.slice(0, 2);

        container.innerHTML = videosToShow.map((video) => {
            const safeUrl = (video.url || '').replace(/'/g, "\\'");
            const safeDateFull = (video.date_full || '').replace(/'/g, "\\'");
            
            return `
                <div class="media-item video-item" onclick="openTeamMediaViewer('video', ${video.id}, '${safeUrl}', '${safeDateFull}', ${userId})" onmouseenter="playVideoPreview(this)" onmouseleave="pauseVideoPreview(this)">
                    <div class="media-thumbnail">
                        <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='300' height='200'%3E%3Crect fill='%23e5e7eb' width='300' height='200'/%3E%3Ccircle cx='150' cy='100' r='30' fill='%235f61e6'/%3E%3Cpolygon points='140,90 140,110 160,100' fill='white'/%3E%3C/svg%3E" alt="Video">
                        <video class="video-preview" muted loop preload="metadata">
                            <source src="${safeUrl}" type="video/webm">
                            <source src="${safeUrl}" type="video/mp4">
                        </video>
                        <div class="media-overlay">
                            <div class="media-overlay-content">
                                <div class="play-button">
                                    <svg viewBox="0 0 24 24" fill="white">
                                        <polygon points="9 5 9 19 19 12"/>
                                    </svg>
                                </div>
                            </div>
                        </div>
                        <div class="video-duration">${video.duration_formatted || '00:00'}</div>
                    </div>
                    <div class="media-info">
                        <div class="media-time">${video.time || 'N/A'}</div>
                        <div class="media-date">${video.date || 'N/A'}</div>
                    </div>
                </div>
            `;
        }).join('');

        // Show/hide "View All" button
        const viewMoreContainer = container.closest('.monitor-content').querySelector('.view-more');
        if (viewMoreContainer) {
            if (videos.length > 2) {
                viewMoreContainer.style.display = 'block';
                const viewAllButton = viewMoreContainer.querySelector('button');
                if (viewAllButton) {
                    viewAllButton.textContent = `View All ${videos.length} Videos`;
                }
            } else {
                viewMoreContainer.style.display = 'none';
            }
        }
    }

    // View all videos for a team member
    function viewAllTeamVideos(userId) {
        const userGroup = teamRecordingsData[userId];
        if (!userGroup || !userGroup.recordings || userGroup.recordings.length === 0) {
            alert('No videos available for this team member');
            return;
        }

        const modal = document.getElementById('videoModal');
        const container = document.getElementById('recordingVideoContainer');
        const title = document.getElementById('videoModalTitle');
        
        if (!modal || !container || !title) {
            console.error('Video modal elements not found');
            return;
        }

        title.textContent = `All Videos - ${userGroup.user_name}`;
        
        // Create grid of all videos
        container.innerHTML = `
            <div class="all-videos-grid">
                ${userGroup.recordings.map((video) => {
                    const safeUrl = (video.url || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
                    const safeDateFull = (video.date_full || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
                    
                    return `
                        <div class="media-item video-item" style="cursor: pointer;" onclick="openTeamVideoViewer(${video.id}, '${safeUrl}', '${safeDateFull}', ${userId})" onmouseenter="playVideoPreview(this)" onmouseleave="pauseVideoPreview(this)">
                            <div class="media-thumbnail">
                                <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='300' height='200'%3E%3Crect fill='%23e5e7eb' width='300' height='200'/%3E%3Ccircle cx='150' cy='100' r='30' fill='%235f61e6'/%3E%3Cpolygon points='140,90 140,110 160,100' fill='white'/%3E%3C/svg%3E" alt="Video" style="width: 100%; height: 150px; object-fit: cover;">
                                <video class="video-preview" muted loop preload="metadata">
                                    <source src="${safeUrl}" type="video/webm">
                                    <source src="${safeUrl}" type="video/mp4">
                                </video>
                                <div class="media-overlay">
                                    <div class="media-overlay-content">
                                        <div class="play-button">
                                            <svg viewBox="0 0 24 24" fill="white">
                                                <polygon points="9 5 9 19 19 12"/>
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                                <div class="video-duration">${video.duration_formatted || '00:00'}</div>
                            </div>
                            <div class="media-info">
                                <div class="media-time">${video.time || 'N/A'}</div>
                                <div class="media-date">${video.date || 'N/A'}</div>
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
        `;
        
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    // Open media viewer for team recordings
    function openTeamMediaViewer(type, id, url, dateTime, userId = null) {
        const modal = document.getElementById('videoModal');
        const container = document.getElementById('recordingVideoContainer');
        const title = document.getElementById('videoModalTitle');
        
        if (!modal || !container || !title) {
            console.error('Video modal elements not found');
            return;
        }

        title.textContent = dateTime || 'Video Recording';
        container.innerHTML = `
            <video id="recordingVideo" controls style="width: 100%; border-radius: 8px;">
                <source src="${url}" type="video/webm">
                <source src="${url}" type="video/mp4">
                Your browser does not support video playback.
            </video>
        `;
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    // Open video viewer from "View All" grid
    function openTeamVideoViewer(id, url, dateTime, userId) {
        const modal = document.getElementById('videoModal');
        const container = document.getElementById('recordingVideoContainer');
        const title = document.getElementById('videoModalTitle');
        
        if (!modal || !container || !title) {
            console.error('Video modal elements not found');
            return;
        }

        title.textContent = dateTime || 'Video Recording';
        container.innerHTML = `
            <video id="recordingVideo" controls style="width: 100%; border-radius: 8px;">
                <source src="${url}" type="video/webm">
                <source src="${url}" type="video/mp4">
                Your browser does not support video playback.
            </video>
        `;
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    // Video preview functions
    function playVideoPreview(element) {
        const videoPreview = element.querySelector('.video-preview');
        if (videoPreview) {
            videoPreview.currentTime = 0;
            videoPreview.play().catch(error => {
                console.debug('Video preview autoplay prevented:', error);
            });
        }
    }

    function pauseVideoPreview(element) {
        const videoPreview = element.querySelector('.video-preview');
        if (videoPreview) {
            videoPreview.pause();
            videoPreview.currentTime = 0;
        }
    }

    // View task time tracking records
    async function viewTaskTimeTracking(taskId, taskTitle, projectTitle) {
        const modal = document.getElementById('taskTimeTrackingModal');
        const container = document.getElementById('taskTimeTrackingContent');
        const title = document.getElementById('taskTimeTrackingTitle');
        
        if (!modal || !container || !title) {
            console.error('Task time tracking modal elements not found');
            return;
        }

        title.textContent = `Time Tracking - ${taskTitle}`;
        container.innerHTML = '<div class="loading-container"><div class="loading-spinner"></div></div>';
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';

        try {
            const response = await fetch(`/api/team-management/teams/${currentTeam.id}/tasks/${taskId}/time-tracking`, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            const data = await response.json();

            if (data.success) {
                const timeTracking = data.data.time_tracking;
                const task = data.data.task;

                if (timeTracking.length > 0) {
                    container.innerHTML = `
                        <div style="margin-bottom: 1.5rem;">
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                <span style="font-weight: 600; color: var(--text-primary);">Task:</span>
                                <span style="color: var(--text-secondary);">${task.title}</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                <span style="font-weight: 600; color: var(--text-primary);">Project:</span>
                                <span style="color: var(--text-secondary);">${task.project_title}</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <span style="font-weight: 600; color: var(--text-primary);">Progress:</span>
                                <span class="task-progress-badge">${task.progress}%</span>
                                <div class="task-progress-bar" style="flex: 1; max-width: 200px; height: 6px; margin-left: 0.5rem;">
                                    <div class="task-progress-fill" style="width: ${task.progress}%;"></div>
                                </div>
                            </div>
                        </div>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Date</th>
                                        <th>Start Time</th>
                                        <th>End Time</th>
                                        <th>Hours Worked</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${timeTracking.map(record => `
                                        <tr>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                    <div class="member-avatar-sm">${record.user_initials}</div>
                                                    <span>${record.user_name}</span>
                                                </div>
                                            </td>
                                            <td>${record.date}</td>
                                            <td>${record.start_time || '--'}</td>
                                            <td>${record.end_time || '--'}</td>
                                            <td><strong>${record.hours_worked}</strong></td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                        ${timeTracking.length > 0 && timeTracking.some(r => r.notes) ? `
                        <div style="margin-top: 1.5rem;">
                            <h4 style="font-size: 0.9375rem; font-weight: 600; color: var(--text-primary); margin-bottom: 0.75rem;">Notes</h4>
                            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                ${timeTracking.filter(r => r.notes).map(record => `
                                    <div style="padding: 0.75rem; background: var(--bg-primary); border-radius: 8px; border: 1px solid var(--border);">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                            <span style="font-weight: 500; color: var(--text-primary);">${record.user_name}</span>
                                            <span style="font-size: 0.8125rem; color: var(--text-secondary);">${record.date}</span>
                                        </div>
                                        <p style="font-size: 0.875rem; color: var(--text-secondary); margin: 0; line-height: 1.5;">${record.notes}</p>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                        ` : ''}
                    `;
                } else {
                    container.innerHTML = `
                        <div class="empty-state">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                            <h3>No Time Tracking Records</h3>
                            <p>No time tracking records found for this task.</p>
                        </div>
                    `;
                }
            } else {
                container.innerHTML = `
                    <div class="empty-state">
                        <h3>Error</h3>
                        <p>${data.message || 'Failed to load time tracking records'}</p>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error loading task time tracking:', error);
            container.innerHTML = `
                <div class="empty-state">
                    <h3>Error</h3>
                    <p>Failed to load time tracking records. Please try again.</p>
                </div>
            `;
        }
    }

    // Close task time tracking modal
    function closeTaskTimeTrackingModal() {
        const modal = document.getElementById('taskTimeTrackingModal');
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    // Load team tasks grouped by employee
    async function loadTeamTasks(container) {
        container.innerHTML = `
            <div class="section-header">
                <h2 class="section-title">Team Tasks</h2>
            </div>
            <div id="tasksContent">
                <div class="loading-container"><div class="loading-spinner"></div></div>
            </div>
        `;

        try {
            const response = await fetch(`/api/team-management/teams/${currentTeam.id}/tasks`, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            const data = await response.json();
            const tasksContainer = document.getElementById('tasksContent');

            if (data.success && data.data.length > 0) {
                tasksContainer.innerHTML = `
                    <div class="recordings-grouped-grid">
                        ${data.data.map(userGroup => `
                            <div class="employee-monitor-card">
                                <div class="monitor-card-header">
                                    <div class="employee-info">
                                        <div class="employee-avatar">
                                            ${userGroup.user_photo ? `<img src="${userGroup.user_photo}" alt="${userGroup.user_name}" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">` : userGroup.user_initials}
                                        </div>
                                        <div>
                                            <div class="employee-name">${userGroup.user_name}</div>
                                            <div class="employee-meta">
                                                <span>${userGroup.user_email}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="monitor-stats">
                                        <div class="stat-item">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="9 11 12 14 22 4"/>
                                                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                                            </svg>
                                            <span>${userGroup.total_tasks} Tasks</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="monitor-content active">
                                    <div class="tasks-list-container">
                                        ${userGroup.tasks.map(task => `
                                            <div class="task-item-card" onclick="viewTaskTimeTracking(${task.id}, '${task.title.replace(/'/g, "\\'")}', '${task.project_title.replace(/'/g, "\\'")}')" style="cursor: pointer;">
                                                <div class="task-item-header">
                                                    <div class="task-title-section">
                                                        <h4 class="task-title">${task.title}</h4>
                                                        <span class="task-project-badge">${task.project_title}</span>
                                                    </div>
                                                    <div class="task-badges">
                                                        <span class="status-badge ${task.priority}">${task.priority}</span>
                                                        <span class="task-progress-badge">${task.progress}%</span>
                                                    </div>
                                                </div>
                                                ${task.description ? `
                                                <div class="task-description">
                                                    ${task.description.length > 100 ? task.description.substring(0, 100) + '...' : task.description}
                                                </div>
                                                ` : ''}
                                                <div class="task-footer">
                                                    <div class="task-progress-section">
                                                        <div class="task-progress-bar">
                                                            <div class="task-progress-fill" style="width: ${task.progress}%;"></div>
                                                        </div>
                                                        <span class="task-progress-text">${task.progress}%</span>
                                                    </div>
                                                    ${task.deadline ? `
                                                    <div class="task-deadline">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 14px; height: 14px; margin-right: 0.25rem;">
                                                            <circle cx="12" cy="12" r="10"/>
                                                            <polyline points="12 6 12 12 16 14"/>
                                                        </svg>
                                                        <span>${task.deadline}</span>
                                                    </div>
                                                    ` : ''}
                                                </div>
                                            </div>
                                        `).join('')}
                                    </div>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                `;
            } else {
                tasksContainer.innerHTML = `
                    <div class="empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="9 11 12 14 22 4"/>
                            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                        </svg>
                        <h3>No Tasks Found</h3>
                        <p>No tasks assigned to team members yet.</p>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error loading tasks:', error);
            document.getElementById('tasksContent').innerHTML = '<p class="text-center text-muted">Error loading tasks</p>';
        }
    }

    // Play recording
    function playRecording(recordingId, title) {
        const modal = document.getElementById('videoModal');
        const video = document.getElementById('recordingVideo');
        document.getElementById('videoModalTitle').textContent = title;

        video.src = `/api/team-management/teams/${currentTeam.id}/recordings/${recordingId}/view`;
        modal.classList.add('active');
    }

    // Close video modal
    function closeVideoModal() {
        const modal = document.getElementById('videoModal');
        const container = document.getElementById('recordingVideoContainer');
        const video = document.getElementById('recordingVideo');
        
        if (video) {
            video.pause();
            video.src = '';
        }
        
        // Reset container to just the video element
        if (container) {
            container.innerHTML = `
                <video id="recordingVideo" controls style="width: 100%; border-radius: 8px;">
                    Your browser does not support video playback.
                </video>
            `;
        }
        
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }

    // Open create team modal
    function openCreateTeamModal() {
        document.getElementById('teamModalTitle').textContent = 'Create Team';
        document.getElementById('saveTeamText').textContent = 'Create Team';
        document.getElementById('teamId').value = '';
        document.getElementById('teamForm').reset();
        document.getElementById('teamColor').value = '#5f61e6';
        document.getElementById('colorPreview').style.background = '#5f61e6';
        populateMemberSelection();
        document.getElementById('teamModal').classList.add('active');
    }

    // Edit team
    async function editTeam(teamId) {
        event.stopPropagation();

        try {
            const response = await fetch(`/api/team-management/teams/${teamId}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            const data = await response.json();

            if (data.success) {
                const team = data.data;
                document.getElementById('teamModalTitle').textContent = 'Edit Team';
                document.getElementById('saveTeamText').textContent = 'Save Changes';
                document.getElementById('teamId').value = team.id;
                document.getElementById('teamName').value = team.name;
                document.getElementById('teamDescription').value = team.description || '';
                document.getElementById('teamLeader').value = team.leader_id || '';
                document.getElementById('teamColor').value = team.color || '#5f61e6';
                document.getElementById('colorPreview').style.background = team.color || '#5f61e6';

                const memberIds = team.members.map(m => m.id);
                populateMemberSelection(team.leader_id, memberIds);

                document.getElementById('teamModal').classList.add('active');
            }
        } catch (error) {
            console.error('Error loading team for edit:', error);
        }
    }

    // Close team modal
    function closeTeamModal() {
        document.getElementById('teamModal').classList.remove('active');
    }

    // Save team
    async function saveTeam() {
        const teamId = document.getElementById('teamId').value;
        const formData = {
            name: document.getElementById('teamName').value,
            description: document.getElementById('teamDescription').value,
            leader_id: document.getElementById('teamLeader').value || null,
            color: document.getElementById('teamColor').value,
            member_ids: Array.from(document.querySelectorAll('#memberSelection input:checked')).map(cb => parseInt(cb.value))
        };

        const url = teamId ? `/api/team-management/teams/${teamId}` : '/api/team-management/teams';
        const method = teamId ? 'PUT' : 'POST';

        try {
            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(formData)
            });

            const data = await response.json();

            if (data.success) {
                closeTeamModal();
                loadTeams();
                alert(data.message);
            } else {
                alert(data.message || 'Error saving team');
            }
        } catch (error) {
            console.error('Error saving team:', error);
            alert('Error saving team');
        }
    }

    // Delete team
    async function deleteTeam(teamId) {
        event.stopPropagation();

        if (!confirm('Are you sure you want to delete this team? This action cannot be undone.')) {
            return;
        }

        try {
            const response = await fetch(`/api/team-management/teams/${teamId}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            const data = await response.json();

            if (data.success) {
                loadTeams();
                alert(data.message);
            } else {
                alert(data.message || 'Error deleting team');
            }
        } catch (error) {
            console.error('Error deleting team:', error);
            alert('Error deleting team');
        }
    }

    // Open add members modal
    async function openAddMembersModal() {
        document.getElementById('addMembersTeamId').value = currentTeam.id;

        // Load available users not in team
        try {
            const response = await fetch(`/api/team-management/users?exclude_team_id=${currentTeam.id}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            const data = await response.json();

            if (data.success) {
                const container = document.getElementById('availableMembersSelection');
                container.innerHTML = '';

                if (data.data.length === 0) {
                    container.innerHTML = '<p class="text-center text-muted" style="padding: 1rem;">All users are already members of this team.</p>';
                } else {
                    data.data.forEach(user => {
                        container.innerHTML += `
                            <label class="user-option">
                                <input type="checkbox" name="new_member_ids[]" value="${user.id}" onchange="this.parentElement.classList.toggle('selected')">
                                <div class="leader-avatar" style="width: 32px; height: 32px; font-size: 0.7rem;">
                                    ${user.photo ? `<img src="${user.photo}" alt="${user.name}">` : user.initials}
                                </div>
                                <div class="member-info">
                                    <div class="member-name">${user.name}</div>
                                    <div class="member-email">${user.email}</div>
                                </div>
                            </label>
                        `;
                    });
                }
            }
        } catch (error) {
            console.error('Error loading available users:', error);
        }

        document.getElementById('addMembersModal').classList.add('active');
    }

    // Close add members modal
    function closeAddMembersModal() {
        document.getElementById('addMembersModal').classList.remove('active');
    }

    // Add selected members
    async function addSelectedMembers() {
        const teamId = document.getElementById('addMembersTeamId').value;
        const memberIds = Array.from(document.querySelectorAll('#availableMembersSelection input:checked')).map(cb => parseInt(cb.value));
        const role = document.getElementById('newMemberRole').value;

        if (memberIds.length === 0) {
            alert('Please select at least one member to add.');
            return;
        }

        try {
            const response = await fetch(`/api/team-management/teams/${teamId}/members`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ member_ids: memberIds, role: role })
            });

            const data = await response.json();

            if (data.success) {
                closeAddMembersModal();
                // Refresh members list with pagination
                refreshMembers();
                alert(data.message);
            } else {
                alert(data.message || 'Error adding members');
            }
        } catch (error) {
            console.error('Error adding members:', error);
            alert('Error adding members');
        }
    }

    // Change member role
    async function changeMemberRole(memberId, currentRole) {
        const newRole = currentRole === 'member' ? 'co-leader' : 'member';
        const roleLabel = newRole === 'co-leader' ? 'Co-Leader' : 'Member';

        if (!confirm(`Change this member to ${roleLabel}?`)) {
            return;
        }

        try {
            const response = await fetch(`/api/team-management/teams/${currentTeam.id}/members/${memberId}/role`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ role: newRole })
            });

            const data = await response.json();

            if (data.success) {
                refreshMembers();
            } else {
                alert(data.message || 'Error updating role');
            }
        } catch (error) {
            console.error('Error updating role:', error);
            alert('Error updating role');
        }
    }

    // Remove member
    async function removeMember(memberId) {
        if (!confirm('Are you sure you want to remove this member from the team?')) {
            return;
        }

        try {
            const response = await fetch(`/api/team-management/teams/${currentTeam.id}/members/${memberId}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            const data = await response.json();

            if (data.success) {
                refreshMembers();
            } else {
                alert(data.message || 'Error removing member');
            }
        } catch (error) {
            console.error('Error removing member:', error);
            alert('Error removing member');
        }
    }

    if (typeof addSelectedMembers === 'function') window.addSelectedMembers = addSelectedMembers;
    if (typeof changeMemberRole === 'function') window.changeMemberRole = changeMemberRole;
    if (typeof changeMembersPerPage === 'function') window.changeMembersPerPage = changeMembersPerPage;
    if (typeof changeTimeTrackingPerPage === 'function') window.changeTimeTrackingPerPage = changeTimeTrackingPerPage;
    if (typeof closeAddMembersModal === 'function') window.closeAddMembersModal = closeAddMembersModal;
    if (typeof closeTaskTimeTrackingModal === 'function') window.closeTaskTimeTrackingModal = closeTaskTimeTrackingModal;
    if (typeof closeTeamModal === 'function') window.closeTeamModal = closeTeamModal;
    if (typeof closeVideoModal === 'function') window.closeVideoModal = closeVideoModal;
    if (typeof deleteTeam === 'function') window.deleteTeam = deleteTeam;
    if (typeof editTeam === 'function') window.editTeam = editTeam;
    if (typeof goToMembersPage === 'function') window.goToMembersPage = goToMembersPage;
    if (typeof goToTimeTrackingPage === 'function') window.goToTimeTrackingPage = goToTimeTrackingPage;
    if (typeof openAddMembersModal === 'function') window.openAddMembersModal = openAddMembersModal;
    if (typeof openCreateTeamModal === 'function') window.openCreateTeamModal = openCreateTeamModal;
    if (typeof openTeamMediaViewer === 'function') window.openTeamMediaViewer = openTeamMediaViewer;
    if (typeof openTeamVideoViewer === 'function') window.openTeamVideoViewer = openTeamVideoViewer;
    if (typeof refreshRecordings === 'function') window.refreshRecordings = refreshRecordings;
    if (typeof removeMember === 'function') window.removeMember = removeMember;
    if (typeof saveTeam === 'function') window.saveTeam = saveTeam;
    if (typeof showTeamsList === 'function') window.showTeamsList = showTeamsList;
    if (typeof stopPropagation === 'function') window.stopPropagation = stopPropagation;
    if (typeof toggle === 'function') window.toggle = toggle;
    if (typeof viewAllTeamVideos === 'function') window.viewAllTeamVideos = viewAllTeamVideos;
    if (typeof viewTaskTimeTracking === 'function') window.viewTaskTimeTracking = viewTaskTimeTracking;
    if (typeof viewTeam === 'function') window.viewTeam = viewTeam;
})();
