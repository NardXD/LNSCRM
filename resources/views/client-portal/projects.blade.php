@extends('layouts.client-portal')

@section('title', 'Projects')

@section('content')
    <div class="page-header">
        <h1 class="page-title">Projects</h1>
        <p class="page-subtitle">View your projects, tasks, and time tracking</p>
    </div>

    <div class="projects-container">
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <span class="stat-value" id="totalProjects">0</span>
                    <span class="stat-label">Total Projects</span>
                </div>
            </div>
            <div class="stat-card stat-active">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <span class="stat-value" id="activeProjects">0</span>
                    <span class="stat-label">Active Projects</span>
                </div>
            </div>
            <div class="stat-card stat-completed">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <span class="stat-value" id="completedProjects">0</span>
                    <span class="stat-label">Completed</span>
                </div>
            </div>
            <div class="stat-card stat-hold">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="10" y1="15" x2="10" y2="9"/>
                        <line x1="14" y1="15" x2="14" y2="9"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <span class="stat-value" id="onHoldProjects">0</span>
                    <span class="stat-label">On Hold</span>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="content-tabs">
            <button class="content-tab active" data-tab="projects" onclick="switchTab('projects')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                </svg>
                Projects
            </button>
            <button class="content-tab" data-tab="timeTracking" onclick="switchTab('timeTracking')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12 6 12 12 16 14"/>
                </svg>
                Time Tracking
            </button>
        </div>

        <!-- Projects Tab Content -->
        <div class="tab-content active" id="projectsTab">
            <!-- Filters -->
            <div class="filters-bar">
                <div class="filters-left">
                    <select class="filter-select" id="statusFilter" onchange="loadProjects()">
                        <option value="all">All Status</option>
                        <option value="active">Active</option>
                        <option value="on-hold">On Hold</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
                <div class="filters-right">
                    <button class="btn-secondary" onclick="loadProjects()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="23 4 23 10 17 10"/>
                            <polyline points="1 20 1 14 7 14"/>
                            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
                        </svg>
                        Refresh
                    </button>
                    <button class="btn-primary" onclick="openCreateProjectModal()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        New Project
                    </button>
                </div>
            </div>

            <!-- Projects Grid -->
            <div class="projects-grid" id="projectsGrid">
                <div class="loading-state">
                    <div class="spinner"></div>
                    <span>Loading projects...</span>
                </div>
            </div>
        </div>

        <!-- Time Tracking Tab Content -->
        <div class="tab-content" id="timeTrackingTab">
            <!-- Date Range Filter -->
            <div class="filters-bar">
                <div class="filters-left">
                    <div class="date-range">
                        <label>From:</label>
                        <input type="date" id="startDate" class="date-input" onchange="loadTimeTrackingSummary()">
                    </div>
                    <div class="date-range">
                        <label>To:</label>
                        <input type="date" id="endDate" class="date-input" onchange="loadTimeTrackingSummary()">
                    </div>
                </div>
                <div class="filters-right">
                    <button class="btn-secondary" onclick="loadTimeTrackingSummary()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="23 4 23 10 17 10"/>
                            <polyline points="1 20 1 14 7 14"/>
                            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
                        </svg>
                        Refresh
                    </button>
                </div>
            </div>

            <!-- Time Tracking Summary -->
            <div class="time-tracking-summary" id="timeTrackingSummary">
                <div class="loading-state">
                    <div class="spinner"></div>
                    <span>Loading time tracking data...</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Project Modal -->
    <div class="modal" id="createProjectModal">
        <div class="modal-content" style="max-width: 600px;">
            <button class="modal-close" onclick="closeCreateProjectModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>

            <div class="modal-header" style="padding-right: 3rem;">
                <div class="project-header-info" style="margin-bottom: 0;">
                    <h2 class="project-title">New Project</h2>
                </div>
            </div>

            <div class="modal-body">
                <form id="createProjectForm" onsubmit="handleCreateProjectSubmit(event)">
                    <div class="form-group">
                        <label class="form-label">Project Title <span style="color: #ef4444;">*</span></label>
                        <input type="text" id="cpTitle" class="form-input" placeholder="Enter project title" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label">Status <span style="color: #ef4444;">*</span></label>
                            <select id="cpStatus" class="form-input" required>
                                <option value="">Select status</option>
                                <option value="active">Active</option>
                                <option value="on-hold">On Hold</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Deadline <span style="color: #ef4444;">*</span></label>
                            <input type="date" id="cpDeadline" class="form-input" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea id="cpDescription" class="form-input" rows="3" placeholder="Enter project description (optional)" style="resize: vertical;"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Team Members</label>
                        <select id="cpTeam" class="form-input" multiple style="min-height: 120px;">
                        </select>
                        <small style="font-size: 0.8125rem; color: var(--text-secondary); margin-top: 0.375rem; display: block;">Hold Ctrl/Cmd to select multiple team members</small>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 0.75rem; padding-top: 1rem; border-top: 1px solid var(--border);">
                        <button type="button" class="btn-secondary" onclick="closeCreateProjectModal()">Cancel</button>
                        <button type="submit" class="btn-primary" id="createProjectSubmitBtn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            Create Project
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Project Detail Modal -->
    <div class="modal" id="projectModal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeProjectModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>

            <div class="modal-header">
                <div class="project-header-info">
                    <h2 class="project-title" id="modalProjectTitle">Project Title</h2>
                    <span class="project-status" id="modalProjectStatus">Active</span>
                </div>
                <div class="project-progress-container">
                    <div class="progress-bar">
                        <div class="progress-fill" id="modalProjectProgress" style="width: 0%"></div>
                    </div>
                    <span class="progress-text" id="modalProjectProgressText">0%</span>
                </div>
            </div>

            <div class="modal-tabs">
                <button class="modal-tab active" data-tab="tasks" onclick="switchModalTab('tasks')">Tasks</button>
                <button class="modal-tab" data-tab="team" onclick="switchModalTab('team')">Team</button>
                <button class="modal-tab" data-tab="projectTime" onclick="switchModalTab('projectTime')">Time Tracking</button>
                <button class="modal-tab" data-tab="workNotes" onclick="switchModalTab('workNotes')">Work Notes</button>
            </div>

            <div class="modal-body">
                <!-- Tasks Tab -->
                <div class="modal-tab-content active" id="tasksTabContent">
                    <div class="kanban-board" id="kanbanBoard">
                        <div class="kanban-column" data-status="todo">
                            <div class="kanban-header">
                                <span class="kanban-title">To Do</span>
                                <span class="kanban-count" id="todoCount">0</span>
                            </div>
                            <div class="kanban-tasks" id="todoTasks"></div>
                        </div>
                        <div class="kanban-column" data-status="in-progress">
                            <div class="kanban-header">
                                <span class="kanban-title">In Progress</span>
                                <span class="kanban-count" id="inProgressCount">0</span>
                            </div>
                            <div class="kanban-tasks" id="inProgressTasks"></div>
                        </div>
                        <div class="kanban-column" data-status="review">
                            <div class="kanban-header">
                                <span class="kanban-title">Review</span>
                                <span class="kanban-count" id="reviewCount">0</span>
                            </div>
                            <div class="kanban-tasks" id="reviewTasks"></div>
                        </div>
                        <div class="kanban-column" data-status="done">
                            <div class="kanban-header">
                                <span class="kanban-title">Done</span>
                                <span class="kanban-count" id="doneCount">0</span>
                            </div>
                            <div class="kanban-tasks" id="doneTasks"></div>
                        </div>
                    </div>
                </div>

                <!-- Team Tab -->
                <div class="modal-tab-content" id="teamTabContent">
                    <div class="team-list" id="teamList"></div>
                </div>

                <!-- Project Time Tracking Tab -->
                <div class="modal-tab-content" id="projectTimeTabContent">
                    <div class="project-time-records" id="projectTimeRecords"></div>
                </div>

                <!-- Work Notes Tab -->
                <div class="modal-tab-content" id="workNotesTabContent">
                    <div class="work-notes-container" id="workNotesContainer"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
    .projects-container {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }

    .stat-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.25rem;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .stat-card.stat-active .stat-icon {
        background: #ecfdf5;
        color: #10b981;
    }

    .stat-card.stat-completed .stat-icon {
        background: #eff6ff;
        color: #3b82f6;
    }

    .stat-card.stat-hold .stat-icon {
        background: #fef3c7;
        color: #f59e0b;
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        background: var(--accent-light);
        color: var(--accent);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .stat-icon svg {
        width: 24px;
        height: 24px;
    }

    .stat-content {
        display: flex;
        flex-direction: column;
    }

    .stat-value {
        font-size: 1.5rem;
        font-weight: 600;
        color: var(--text-primary);
    }

    .stat-label {
        font-size: 0.8125rem;
        color: var(--text-secondary);
    }

    /* Content Tabs */
    .content-tabs {
        display: flex;
        gap: 0.5rem;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 0.5rem;
    }

    .content-tab {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.25rem;
        border: none;
        background: transparent;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.15s;
    }

    .content-tab:hover {
        background: var(--bg-primary);
        color: var(--text-primary);
    }

    .content-tab.active {
        background: var(--accent);
        color: white;
    }

    .content-tab svg {
        width: 18px;
        height: 18px;
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }

    /* Filters Bar */
    .filters-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1rem;
    }

    .filters-left, .filters-right {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    .filter-select, .date-input {
        padding: 0.5rem 0.75rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        background: var(--bg-card);
        color: var(--text-primary);
        cursor: pointer;
    }

    .filter-select:focus, .date-input:focus {
        outline: none;
        border-color: var(--accent);
    }

    .date-range {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .date-range label {
        font-size: 0.8125rem;
        color: var(--text-secondary);
    }

    /* Projects Grid */
    .projects-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 1.25rem;
    }

    .project-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.25rem;
        cursor: pointer;
        transition: all 0.15s;
    }

    .project-card:hover {
        border-color: var(--accent);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.1);
    }

    .project-card-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 1rem;
    }

    .project-card-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
    }

    .project-card-description {
        font-size: 0.8125rem;
        color: var(--text-secondary);
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .project-status-badge {
        padding: 0.25rem 0.625rem;
        border-radius: 9999px;
        font-size: 0.6875rem;
        font-weight: 500;
        text-transform: uppercase;
        flex-shrink: 0;
    }

    .project-status-badge.active {
        background: #ecfdf5;
        color: #059669;
    }

    .project-status-badge.on-hold {
        background: #fef3c7;
        color: #d97706;
    }

    .project-status-badge.completed {
        background: #eff6ff;
        color: #2563eb;
    }

    .project-progress {
        margin-bottom: 1rem;
    }

    .progress-bar {
        height: 6px;
        background: var(--border);
        border-radius: 3px;
        overflow: hidden;
        margin-bottom: 0.375rem;
    }

    .progress-fill {
        height: 100%;
        background: var(--accent);
        border-radius: 3px;
        transition: width 0.3s;
    }

    .progress-info {
        display: flex;
        justify-content: space-between;
        font-size: 0.75rem;
        color: var(--text-secondary);
    }

    .project-card-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-top: 1rem;
        border-top: 1px solid var(--border);
    }

    .project-meta {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .project-meta-item {
        display: flex;
        align-items: center;
        gap: 0.375rem;
        font-size: 0.8125rem;
        color: var(--text-secondary);
    }

    .project-meta-item svg {
        width: 14px;
        height: 14px;
    }

    .team-avatars {
        display: flex;
    }

    .team-avatar {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: var(--accent);
        color: white;
        font-size: 0.6875rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid var(--bg-card);
        margin-left: -8px;
        overflow: hidden;
    }

    .team-avatar:first-child {
        margin-left: 0;
    }

    .team-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .team-avatar.more {
        background: var(--border);
        color: var(--text-secondary);
    }

    /* Time Tracking Summary */
    .time-tracking-summary {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .summary-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1rem;
    }

    .summary-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.25rem;
    }

    .summary-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
    }

    .summary-card-title {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-primary);
    }

    .summary-card-value {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--accent);
    }

    .breakdown-list {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .breakdown-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.75rem;
        background: var(--bg-primary);
        border-radius: 8px;
    }

    .breakdown-item-info {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .breakdown-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: var(--accent);
        color: white;
        font-size: 0.75rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .breakdown-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .breakdown-name {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
    }

    .breakdown-hours {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--accent);
    }

    /* Modal */
    .modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }

    .modal.active {
        display: flex;
    }

    .modal-content {
        background: var(--bg-card);
        border-radius: 16px;
        max-width: 1100px;
        width: 100%;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
        position: relative;
    }

    .modal-close {
        position: absolute;
        top: 1rem;
        right: 1rem;
        width: 36px;
        height: 36px;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 8px;
        color: var(--text-secondary);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10;
    }

    .modal-close:hover {
        background: var(--border);
        color: var(--text-primary);
    }

    .modal-close svg {
        width: 18px;
        height: 18px;
    }

    .modal-header {
        padding: 1.5rem;
        border-bottom: 1px solid var(--border);
    }

    .project-header-info {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .project-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--text-primary);
    }

    .project-status {
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        text-transform: uppercase;
    }

    .project-status.active {
        background: #ecfdf5;
        color: #059669;
    }

    .project-status.on-hold {
        background: #fef3c7;
        color: #d97706;
    }

    .project-status.completed {
        background: #eff6ff;
        color: #2563eb;
    }

    .project-progress-container {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .project-progress-container .progress-bar {
        flex: 1;
        height: 8px;
        margin-bottom: 0;
    }

    .progress-text {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--accent);
        min-width: 40px;
    }

    .modal-tabs {
        display: flex;
        gap: 0.5rem;
        padding: 0 1.5rem;
        border-bottom: 1px solid var(--border);
    }

    .modal-tab {
        padding: 0.875rem 1rem;
        border: none;
        background: transparent;
        border-bottom: 2px solid transparent;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-secondary);
        cursor: pointer;
    }

    .modal-tab:hover {
        color: var(--text-primary);
    }

    .modal-tab.active {
        color: var(--accent);
        border-bottom-color: var(--accent);
    }

    .modal-body {
        flex: 1;
        overflow-y: auto;
        padding: 1.5rem;
    }

    .modal-tab-content {
        display: none;
    }

    .modal-tab-content.active {
        display: block;
    }

    /* Kanban Board */
    .kanban-board {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1rem;
        min-height: 400px;
    }

    .kanban-column {
        background: var(--bg-primary);
        border-radius: 12px;
        padding: 1rem;
        display: flex;
        flex-direction: column;
    }

    .kanban-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid var(--border);
    }

    .kanban-column[data-status="todo"] .kanban-header {
        border-bottom-color: #9ca3af;
    }

    .kanban-column[data-status="in-progress"] .kanban-header {
        border-bottom-color: #3b82f6;
    }

    .kanban-column[data-status="review"] .kanban-header {
        border-bottom-color: #f59e0b;
    }

    .kanban-column[data-status="done"] .kanban-header {
        border-bottom-color: #10b981;
    }

    .kanban-title {
        font-size: 0.8125rem;
        font-weight: 600;
        color: var(--text-primary);
    }

    .kanban-count {
        width: 24px;
        height: 24px;
        background: var(--border);
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-secondary);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .kanban-tasks {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .task-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 0.875rem;
    }

    .task-title {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }

    .task-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .task-priority {
        font-size: 0.6875rem;
        font-weight: 500;
        padding: 0.125rem 0.375rem;
        border-radius: 4px;
        text-transform: uppercase;
    }

    .task-priority.high {
        background: #fef2f2;
        color: #dc2626;
    }

    .task-priority.medium {
        background: #fef3c7;
        color: #d97706;
    }

    .task-priority.low {
        background: #ecfdf5;
        color: #059669;
    }

    .task-assignee {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: var(--accent);
        color: white;
        font-size: 0.625rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .task-assignee img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    /* Team List */
    .team-list {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 1rem;
    }

    .team-member-card {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        background: var(--bg-primary);
        border-radius: 8px;
    }

    .team-member-avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: var(--accent);
        color: white;
        font-size: 1rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        overflow: hidden;
    }

    .team-member-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .team-member-info {
        flex: 1;
    }

    .team-member-name {
        font-size: 0.9375rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
    }

    .team-member-email {
        font-size: 0.8125rem;
        color: var(--text-secondary);
    }

    /* Project Time Records */
    .project-time-records {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .time-record-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        background: var(--bg-primary);
        border-radius: 8px;
    }

    .time-record-user {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        min-width: 180px;
    }

    .time-record-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: var(--accent);
        color: white;
        font-size: 0.8125rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .time-record-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .time-record-info {
        flex: 1;
    }

    .time-record-date {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
    }

    .time-record-task {
        font-size: 0.8125rem;
        color: var(--text-secondary);
    }

    .time-record-hours {
        font-size: 1rem;
        font-weight: 600;
        color: var(--accent);
        min-width: 80px;
        text-align: right;
    }

    .time-record-notes {
        width: 100%;
        margin-top: 0.75rem;
        padding-top: 0.75rem;
        border-top: 1px solid var(--border);
    }

    .time-record-notes-label {
        font-size: 0.6875rem;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.375rem;
    }

    .time-record-notes-text {
        font-size: 0.8125rem;
        color: var(--text-secondary);
        line-height: 1.5;
        white-space: pre-wrap;
    }

    /* Work Notes */
    .work-notes-container {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .work-note-card {
        background: var(--bg-primary);
        border-radius: 12px;
        padding: 1.25rem;
        border-left: 4px solid var(--accent);
    }

    .work-note-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.75rem;
    }

    .work-note-user {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .work-note-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: var(--accent);
        color: white;
        font-size: 0.8125rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .work-note-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .work-note-user-info {
        display: flex;
        flex-direction: column;
    }

    .work-note-user-name {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-primary);
    }

    .work-note-task {
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    .work-note-meta {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 0.25rem;
    }

    .work-note-date {
        font-size: 0.8125rem;
        color: var(--text-secondary);
    }

    .work-note-hours {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--accent);
        background: var(--accent-light);
        padding: 0.125rem 0.5rem;
        border-radius: 9999px;
    }

    .work-note-content {
        font-size: 0.9375rem;
        color: var(--text-primary);
        line-height: 1.6;
        white-space: pre-wrap;
        padding: 1rem;
        background: var(--bg-card);
        border-radius: 8px;
        border: 1px solid var(--border);
    }

    .work-notes-empty {
        text-align: center;
        padding: 3rem;
        color: var(--text-muted);
    }

    .work-notes-empty svg {
        width: 48px;
        height: 48px;
        margin-bottom: 1rem;
        opacity: 0.5;
    }

    /* Loading & Empty States */
    .loading-state, .empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 3rem;
        color: var(--text-muted);
        text-align: center;
    }

    .spinner {
        width: 32px;
        height: 32px;
        border: 3px solid var(--border);
        border-top-color: var(--accent);
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-bottom: 1rem;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    /* Buttons */
    .btn-secondary {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
        cursor: pointer;
        transition: all 0.15s;
    }

    .btn-secondary:hover {
        background: var(--border);
    }

    .btn-secondary svg {
        width: 16px;
        height: 16px;
    }

    .btn-primary {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        background: var(--accent);
        border: none;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        color: white;
        cursor: pointer;
        transition: all 0.15s;
    }

    .btn-primary:hover {
        opacity: 0.9;
    }

    .btn-primary:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .btn-primary svg {
        width: 16px;
        height: 16px;
    }

    /* Form Elements */
    .form-group {
        margin-bottom: 1rem;
    }

    .form-label {
        display: block;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
        margin-bottom: 0.375rem;
    }

    .form-input {
        width: 100%;
        padding: 0.625rem 0.75rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        background: var(--bg-primary);
        color: var(--text-primary);
        box-sizing: border-box;
    }

    .form-input:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    }

    /* Responsive */
    @media (max-width: 1024px) {
        .kanban-board {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .projects-grid {
            grid-template-columns: 1fr;
        }

        .kanban-board {
            grid-template-columns: 1fr;
        }

        .filters-bar {
            flex-direction: column;
            align-items: stretch;
        }

        .filters-left, .filters-right {
            width: 100%;
        }

        .content-tabs {
            flex-direction: column;
        }

        .content-tab {
            justify-content: center;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const apiBase = '/client/api';
    let projectsData = [];
    let currentProjectId = null;

    // Initialize date range to current month
    document.addEventListener('DOMContentLoaded', function() {
        const today = new Date();
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
        const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);

        document.getElementById('startDate').value = firstDay.toISOString().split('T')[0];
        document.getElementById('endDate').value = lastDay.toISOString().split('T')[0];

        loadProjects();
    });

    // Switch main tabs
    function switchTab(tabName) {
        document.querySelectorAll('.content-tab').forEach(tab => {
            tab.classList.remove('active');
            if (tab.dataset.tab === tabName) {
                tab.classList.add('active');
            }
        });

        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
        });

        document.getElementById(tabName + 'Tab').classList.add('active');

        if (tabName === 'timeTracking') {
            loadTimeTrackingSummary();
        }
    }

    // Load projects
    async function loadProjects() {
        const grid = document.getElementById('projectsGrid');
        grid.innerHTML = '<div class="loading-state"><div class="spinner"></div><span>Loading projects...</span></div>';

        try {
            const status = document.getElementById('statusFilter').value;
            const response = await fetch(`${apiBase}/projects?status=${status}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                }
            });

            const data = await response.json();

            if (data.success) {
                projectsData = data.projects;
                updateStats(data.stats);
                renderProjects(data.projects);
            } else {
                grid.innerHTML = '<div class="empty-state">Failed to load projects</div>';
            }
        } catch (error) {
            console.error('Error loading projects:', error);
            grid.innerHTML = '<div class="empty-state">Error loading projects. Please try again.</div>';
        }
    }

    // Update stats
    function updateStats(stats) {
        document.getElementById('totalProjects').textContent = stats.total || 0;
        document.getElementById('activeProjects').textContent = stats.active || 0;
        document.getElementById('completedProjects').textContent = stats.completed || 0;
        document.getElementById('onHoldProjects').textContent = stats.on_hold || 0;
    }

    // Render projects
    function renderProjects(projects) {
        const grid = document.getElementById('projectsGrid');

        if (!projects || projects.length === 0) {
            grid.innerHTML = '<div class="empty-state">No projects found</div>';
            return;
        }

        grid.innerHTML = projects.map(project => {
            const teamAvatars = (project.team_members || []).slice(0, 3).map(member => {
                const initials = member.name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
                if (member.photo) {
                    return `<div class="team-avatar"><img src="${member.photo}" alt="${member.name}"></div>`;
                }
                return `<div class="team-avatar">${initials}</div>`;
            }).join('');

            const moreCount = (project.team_members || []).length - 3;
            const moreAvatar = moreCount > 0 ? `<div class="team-avatar more">+${moreCount}</div>` : '';

            return `
                <div class="project-card" onclick="openProject(${project.id})">
                    <div class="project-card-header">
                        <div>
                            <div class="project-card-title">${project.title}</div>
                            <div class="project-card-description">${project.description || 'No description'}</div>
                        </div>
                        <span class="project-status-badge ${project.status}">${project.status.replace('-', ' ')}</span>
                    </div>

                    <div class="project-progress">
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: ${project.progress}%"></div>
                        </div>
                        <div class="progress-info">
                            <span>${project.progress}% complete</span>
                            <span>${project.completed_tasks}/${project.total_tasks} tasks</span>
                        </div>
                    </div>

                    <div class="project-card-footer">
                        <div class="project-meta">
                            <div class="project-meta-item">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                    <line x1="16" y1="2" x2="16" y2="6"/>
                                    <line x1="8" y1="2" x2="8" y2="6"/>
                                    <line x1="3" y1="10" x2="21" y2="10"/>
                                </svg>
                                ${project.deadline || 'No deadline'}
                            </div>
                            <div class="project-meta-item">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12 6 12 12 16 14"/>
                                </svg>
                                ${project.total_hours}h
                            </div>
                        </div>
                        <div class="team-avatars">
                            ${teamAvatars}
                            ${moreAvatar}
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    // Open project modal
    async function openProject(projectId) {
        currentProjectId = projectId;
        const modal = document.getElementById('projectModal');

        try {
            const response = await fetch(`${apiBase}/projects/${projectId}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                }
            });

            const data = await response.json();

            if (data.success) {
                // Update modal header
                document.getElementById('modalProjectTitle').textContent = data.project.title;
                document.getElementById('modalProjectStatus').textContent = data.project.status.replace('-', ' ');
                document.getElementById('modalProjectStatus').className = `project-status ${data.project.status}`;
                document.getElementById('modalProjectProgress').style.width = `${data.project.progress}%`;
                document.getElementById('modalProjectProgressText').textContent = `${data.project.progress}%`;

                // Render tasks
                renderTasks(data.tasks_by_status);

                // Render team
                renderTeam(data.project.team_members);

                // Reset to tasks tab
                switchModalTab('tasks');

                // Show modal
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        } catch (error) {
            console.error('Error loading project:', error);
            alert('Error loading project details');
        }
    }

    // Close project modal
    function closeProjectModal() {
        document.getElementById('projectModal').classList.remove('active');
        document.body.style.overflow = '';
        currentProjectId = null;
    }

    // Switch modal tabs
    function switchModalTab(tabName) {
        document.querySelectorAll('.modal-tab').forEach(tab => {
            tab.classList.remove('active');
            if (tab.dataset.tab === tabName) {
                tab.classList.add('active');
            }
        });

        document.querySelectorAll('.modal-tab-content').forEach(content => {
            content.classList.remove('active');
        });

        document.getElementById(tabName + 'TabContent').classList.add('active');

        if (tabName === 'projectTime' && currentProjectId) {
            loadProjectTimeTracking(currentProjectId);
        }

        if (tabName === 'workNotes' && currentProjectId) {
            loadWorkNotes(currentProjectId);
        }
    }

    // Render tasks in kanban board
    function renderTasks(tasksByStatus) {
        const statuses = ['todo', 'in-progress', 'review', 'done'];

        statuses.forEach(status => {
            const tasks = tasksByStatus[status] || [];
            const container = document.getElementById(status.replace('-', '') === 'inprogress' ? 'inProgressTasks' : status + 'Tasks');
            const countEl = document.getElementById(status.replace('-', '') === 'inprogress' ? 'inProgressCount' : status + 'Count');

            if (!container) return;

            countEl.textContent = tasks.length;

            if (tasks.length === 0) {
                container.innerHTML = '<div class="empty-state" style="padding: 1rem; font-size: 0.8125rem;">No tasks</div>';
                return;
            }

            container.innerHTML = tasks.map(task => {
                const assignee = task.assigned_to;
                let assigneeHtml = '';
                if (assignee) {
                    const initials = assignee.name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
                    assigneeHtml = assignee.photo
                        ? `<div class="task-assignee"><img src="${assignee.photo}" alt="${assignee.name}"></div>`
                        : `<div class="task-assignee">${initials}</div>`;
                }

                return `
                    <div class="task-card">
                        <div class="task-title">${task.title}</div>
                        <div class="task-meta">
                            <span class="task-priority ${task.priority}">${task.priority}</span>
                            ${assigneeHtml}
                        </div>
                    </div>
                `;
            }).join('');
        });

        // Fix the in-progress ID
        const inProgressTasks = tasksByStatus['in-progress'] || [];
        const inProgressContainer = document.getElementById('inProgressTasks');
        const inProgressCount = document.getElementById('inProgressCount');

        if (inProgressContainer) {
            inProgressCount.textContent = inProgressTasks.length;

            if (inProgressTasks.length === 0) {
                inProgressContainer.innerHTML = '<div class="empty-state" style="padding: 1rem; font-size: 0.8125rem;">No tasks</div>';
            } else {
                inProgressContainer.innerHTML = inProgressTasks.map(task => {
                    const assignee = task.assigned_to;
                    let assigneeHtml = '';
                    if (assignee) {
                        const initials = assignee.name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
                        assigneeHtml = assignee.photo
                            ? `<div class="task-assignee"><img src="${assignee.photo}" alt="${assignee.name}"></div>`
                            : `<div class="task-assignee">${initials}</div>`;
                    }

                    return `
                        <div class="task-card">
                            <div class="task-title">${task.title}</div>
                            <div class="task-meta">
                                <span class="task-priority ${task.priority}">${task.priority}</span>
                                ${assigneeHtml}
                            </div>
                        </div>
                    `;
                }).join('');
            }
        }
    }

    // Render team members
    function renderTeam(members) {
        const container = document.getElementById('teamList');

        if (!members || members.length === 0) {
            container.innerHTML = '<div class="empty-state">No team members assigned</div>';
            return;
        }

        container.innerHTML = members.map(member => {
            const initials = member.name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
            const avatarContent = member.photo
                ? `<img src="${member.photo}" alt="${member.name}">`
                : initials;

            return `
                <div class="team-member-card">
                    <div class="team-member-avatar">${avatarContent}</div>
                    <div class="team-member-info">
                        <div class="team-member-name">${member.name}</div>
                        <div class="team-member-email">${member.email}</div>
                    </div>
                </div>
            `;
        }).join('');
    }

    // Load project time tracking
    async function loadProjectTimeTracking(projectId) {
        const container = document.getElementById('projectTimeRecords');
        container.innerHTML = '<div class="loading-state"><div class="spinner"></div><span>Loading time records...</span></div>';

        try {
            const response = await fetch(`${apiBase}/projects/${projectId}/time-tracking`, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                }
            });

            const data = await response.json();

            if (data.success) {
                renderProjectTimeRecords(data.records, data.summary);
            } else {
                container.innerHTML = '<div class="empty-state">Failed to load time records</div>';
            }
        } catch (error) {
            console.error('Error loading time tracking:', error);
            container.innerHTML = '<div class="empty-state">Error loading time records</div>';
        }
    }

    // Render project time records
    function renderProjectTimeRecords(records, summary) {
        const container = document.getElementById('projectTimeRecords');

        if (!records || records.length === 0) {
            container.innerHTML = '<div class="empty-state">No time records found</div>';
            return;
        }

        const summaryHtml = `
            <div class="summary-card" style="margin-bottom: 1.5rem;">
                <div class="summary-card-header">
                    <span class="summary-card-title">Total Hours</span>
                </div>
                <div class="summary-card-value">${summary.total_hours}h</div>
            </div>
        `;

        const recordsHtml = records.map(record => {
            const user = record.user;
            let avatarContent = '';
            if (user) {
                const initials = user.name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
                avatarContent = user.photo
                    ? `<img src="${user.photo}" alt="${user.name}">`
                    : initials;
            }

            const notesHtml = record.notes ? `
                <div class="time-record-notes">
                    <div class="time-record-notes-label">Notes</div>
                    <div class="time-record-notes-text">${escapeHtml(record.notes)}</div>
                </div>
            ` : '';

            return `
                <div class="time-record-item" style="flex-wrap: wrap;">
                    <div class="time-record-user">
                        <div class="time-record-avatar">${avatarContent}</div>
                        <span>${user?.name || 'Unknown'}</span>
                    </div>
                    <div class="time-record-info">
                        <div class="time-record-date">${record.date}</div>
                        <div class="time-record-task">${record.task?.title || 'No task'}</div>
                    </div>
                    <div class="time-record-hours">${record.hours_worked_formatted}</div>
                    ${notesHtml}
                </div>
            `;
        }).join('');

        container.innerHTML = summaryHtml + recordsHtml;
    }

    // Load work notes for a project
    async function loadWorkNotes(projectId) {
        const container = document.getElementById('workNotesContainer');
        container.innerHTML = '<div class="loading-state"><div class="spinner"></div><span>Loading work notes...</span></div>';

        try {
            const response = await fetch(`${apiBase}/projects/${projectId}/time-tracking`, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                }
            });

            const data = await response.json();

            if (data.success) {
                renderWorkNotes(data.records);
            } else {
                container.innerHTML = '<div class="empty-state">Failed to load work notes</div>';
            }
        } catch (error) {
            console.error('Error loading work notes:', error);
            container.innerHTML = '<div class="empty-state">Error loading work notes</div>';
        }
    }

    // Render work notes
    function renderWorkNotes(records) {
        const container = document.getElementById('workNotesContainer');

        // Filter records that have notes
        const notesRecords = records.filter(record => record.notes && record.notes.trim() !== '');

        if (notesRecords.length === 0) {
            container.innerHTML = `
                <div class="work-notes-empty">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                        <polyline points="10 9 9 9 8 9"/>
                    </svg>
                    <p>No work notes found for this project</p>
                    <p style="font-size: 0.8125rem; margin-top: 0.5rem;">Work notes are added when team members log their time.</p>
                </div>
            `;
            return;
        }

        container.innerHTML = notesRecords.map(record => {
            const user = record.user;
            let avatarContent = '';
            if (user) {
                const initials = user.name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
                avatarContent = user.photo
                    ? `<img src="${user.photo}" alt="${user.name}">`
                    : initials;
            }

            return `
                <div class="work-note-card">
                    <div class="work-note-header">
                        <div class="work-note-user">
                            <div class="work-note-avatar">${avatarContent}</div>
                            <div class="work-note-user-info">
                                <span class="work-note-user-name">${user?.name || 'Unknown'}</span>
                                <span class="work-note-task">${record.task?.title || 'General work'}</span>
                            </div>
                        </div>
                        <div class="work-note-meta">
                            <span class="work-note-date">${record.date}</span>
                            <span class="work-note-hours">${record.hours_worked_formatted}</span>
                        </div>
                    </div>
                    <div class="work-note-content">${escapeHtml(record.notes)}</div>
                </div>
            `;
        }).join('');
    }

    // Helper function to escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Load time tracking summary
    async function loadTimeTrackingSummary() {
        const container = document.getElementById('timeTrackingSummary');
        container.innerHTML = '<div class="loading-state"><div class="spinner"></div><span>Loading time tracking data...</span></div>';

        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;

        try {
            const response = await fetch(`${apiBase}/time-tracking/summary?start_date=${startDate}&end_date=${endDate}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                }
            });

            const data = await response.json();

            if (data.success) {
                renderTimeTrackingSummary(data);
            } else {
                container.innerHTML = '<div class="empty-state">Failed to load time tracking summary</div>';
            }
        } catch (error) {
            console.error('Error loading time tracking summary:', error);
            container.innerHTML = '<div class="empty-state">Error loading time tracking data</div>';
        }
    }

    // Render time tracking summary
    function renderTimeTrackingSummary(data) {
        const container = document.getElementById('timeTrackingSummary');

        const summaryCardsHtml = `
            <div class="summary-cards">
                <div class="summary-card">
                    <div class="summary-card-header">
                        <span class="summary-card-title">Total Hours</span>
                    </div>
                    <div class="summary-card-value">${data.summary.total_hours}h</div>
                    <div style="font-size: 0.8125rem; color: var(--text-secondary); margin-top: 0.5rem;">
                        ${data.summary.date_range.start} - ${data.summary.date_range.end}
                    </div>
                </div>
                <div class="summary-card">
                    <div class="summary-card-header">
                        <span class="summary-card-title">Time Records</span>
                    </div>
                    <div class="summary-card-value">${data.summary.total_records}</div>
                    <div style="font-size: 0.8125rem; color: var(--text-secondary); margin-top: 0.5rem;">
                        Logged entries
                    </div>
                </div>
            </div>
        `;

        // By Project breakdown
        let byProjectHtml = '';
        if (data.by_project && data.by_project.length > 0) {
            byProjectHtml = `
                <div class="summary-card">
                    <div class="summary-card-header">
                        <span class="summary-card-title">Hours by Project</span>
                    </div>
                    <div class="breakdown-list">
                        ${data.by_project.map(item => `
                            <div class="breakdown-item">
                                <div class="breakdown-item-info">
                                    <span class="breakdown-name">${item.project_title}</span>
                                </div>
                                <span class="breakdown-hours">${item.total_hours}h</span>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        }

        // By User breakdown
        let byUserHtml = '';
        if (data.by_user && data.by_user.length > 0) {
            byUserHtml = `
                <div class="summary-card">
                    <div class="summary-card-header">
                        <span class="summary-card-title">Hours by Team Member</span>
                    </div>
                    <div class="breakdown-list">
                        ${data.by_user.map(item => {
                            const initials = item.user_name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
                            const avatarContent = item.user_photo
                                ? `<img src="${item.user_photo}" alt="${item.user_name}">`
                                : initials;
                            return `
                                <div class="breakdown-item">
                                    <div class="breakdown-item-info">
                                        <div class="breakdown-avatar">${avatarContent}</div>
                                        <span class="breakdown-name">${item.user_name}</span>
                                    </div>
                                    <span class="breakdown-hours">${item.total_hours}h</span>
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
            `;
        }

        container.innerHTML = summaryCardsHtml + `
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                ${byProjectHtml}
                ${byUserHtml}
            </div>
        `;
    }

    // Close modal on outside click
    document.getElementById('projectModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeProjectModal();
        }
    });

    document.getElementById('createProjectModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeCreateProjectModal();
        }
    });

    // Close modals on Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (document.getElementById('projectModal').classList.contains('active')) {
                closeProjectModal();
            }
            if (document.getElementById('createProjectModal').classList.contains('active')) {
                closeCreateProjectModal();
            }
        }
    });

    // Create Project Modal
    let companyUsersData = [];

    async function openCreateProjectModal() {
        const modal = document.getElementById('createProjectModal');
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';

        await loadCompanyUsers();

        setTimeout(() => {
            document.getElementById('cpTitle').focus();
        }, 100);
    }

    function closeCreateProjectModal() {
        document.getElementById('createProjectModal').classList.remove('active');
        document.body.style.overflow = '';
        document.getElementById('createProjectForm').reset();
    }

    async function loadCompanyUsers() {
        if (companyUsersData.length > 0) {
            renderTeamSelect();
            return;
        }

        try {
            const response = await fetch(`${apiBase}/company-users`, {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
            });
            const data = await response.json();

            if (data.success) {
                companyUsersData = data.users;
                renderTeamSelect();
            }
        } catch (error) {
            console.error('Error loading users:', error);
        }
    }

    function renderTeamSelect() {
        const select = document.getElementById('cpTeam');
        select.innerHTML = companyUsersData.map(user =>
            `<option value="${user.id}">${user.name} (${user.initials})</option>`
        ).join('');
    }

    async function handleCreateProjectSubmit(event) {
        event.preventDefault();

        const btn = document.getElementById('createProjectSubmitBtn');
        btn.disabled = true;
        btn.textContent = 'Creating...';

        const teamSelect = document.getElementById('cpTeam');
        const selectedTeam = Array.from(teamSelect.selectedOptions).map(o => parseInt(o.value));

        const projectData = {
            title: document.getElementById('cpTitle').value.trim(),
            status: document.getElementById('cpStatus').value,
            deadline: document.getElementById('cpDeadline').value,
            description: document.getElementById('cpDescription').value.trim(),
            team: selectedTeam,
        };

        try {
            const response = await fetch(`${apiBase}/projects`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(projectData),
            });

            const result = await response.json();

            if (result.success) {
                closeCreateProjectModal();
                await loadProjects();
            } else {
                alert('Error: ' + (result.message || 'Could not create project.'));
            }
        } catch (error) {
            console.error('Error creating project:', error);
            alert('Error creating project. Please try again.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;"><polyline points="20 6 9 17 4 12"/></svg> Create Project';
        }
    }
</script>
@endpush
