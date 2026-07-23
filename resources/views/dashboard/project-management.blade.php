@extends('layouts.app')

@section('title', 'Project Management')

@section('content')
    <div class="page-header">
        <h1 class="page-title">Project Management</h1>
        <p class="page-subtitle">Manage projects, tasks, assignments, and track progress</p>
    </div>

    <div class="project-container">
        <!-- Tabs Navigation -->
        <div class="project-tabs">
            <button class="tab-btn active" data-tab="projects">Projects</button>
            <button class="tab-btn" data-tab="tasks">Tasks</button>
            <button class="tab-btn" data-tab="time-tracking">Time Tracking</button>
        </div>

        <!-- Projects Tab -->
        <div class="tab-content active" id="projectsTab">
            <div class="section-header">
                <h2 class="section-title">Projects</h2>
                @if(auth()->user()?->hasPermission('create_project_management'))
                <button class="btn-primary" onclick="openProjectModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    New Project
                </button>
                @endif
            </div>

            <!-- Project Stats -->
            <div class="project-stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-label">Total Projects</span>
                        <div class="stat-icon blue">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                                <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                                <line x1="12" y1="22.08" x2="12" y2="12"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value" id="statTotalProjects">0</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-label">Active Projects</span>
                        <div class="stat-icon green">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value" id="statActiveProjects">0</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-label">Completed</span>
                        <div class="stat-icon purple">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value" id="statCompletedProjects">0</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-label">On Hold</span>
                        <div class="stat-icon orange">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value" id="statOnHoldProjects">0</div>
                </div>
            </div>

            <!-- Projects Grid -->
            <div class="projects-grid" id="projectsGrid">
                <!-- Projects will be populated by JavaScript -->
            </div>
        </div>

        <!-- Tasks Tab -->
        <div class="tab-content" id="tasksTab">
            <div class="section-header">
                <h2 class="section-title">Tasks</h2>
                <div class="section-actions">
                    <select class="filter-select" id="taskProjectFilter">
                        <option value="all">All Projects</option>
                        <option value="1">Website Redesign</option>
                        <option value="2">Mobile App Development</option>
                        <option value="3">Marketing Campaign</option>
                    </select>
                    @if(auth()->user()?->hasPermission('create_task_management'))
                    <button class="btn-primary" onclick="openTaskModal()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        New Task
                    </button>
                    @endif
                </div>
            </div>

            <!-- Status Tabs -->
            <div class="task-status-tabs">
                <button class="status-tab-btn active" data-status="all">All</button>
                <button class="status-tab-btn" data-status="todo">To Do</button>
                <button class="status-tab-btn" data-status="in-progress">In Progress</button>
                <button class="status-tab-btn" data-status="review">In Review</button>
                <button class="status-tab-btn" data-status="done">Done</button>
            </div>

            <!-- Tasks Table -->
            <div class="tasks-section">
                <div class="table-container">
                    <table class="data-table" id="tasksTable">
                        <thead>
                            <tr>
                                <th>Task</th>
                                <th>Project</th>
                                <th>Assigned To</th>
                                <th>Priority</th>
                                <th>Deadline</th>
                                <th>Status</th>
                                <th>Progress</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tasksTableBody">
                            <!-- Data will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card View -->
                <div class="tasks-cards" id="tasksCards">
                    <!-- Cards will be populated by JavaScript -->
                </div>

                <!-- Pagination -->
                <div class="table-pagination">
                    <div class="pagination-info">
                        <span id="tasksPaginationInfo">Showing 1 to 10 of 0 tasks</span>
                    </div>
                    <div class="pagination-controls">
                        <button class="pagination-btn" id="tasksPrevBtn" disabled>Previous</button>
                        <div class="pagination-numbers" id="tasksPaginationNumbers"></div>
                        <button class="pagination-btn" id="tasksNextBtn" disabled>Next</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Time Tracking Tab -->
        <div class="tab-content" id="timeTrackingTab">
            <div class="section-header">
                <h2 class="section-title">Time Tracking</h2>
                <div class="section-actions">
                    <select class="filter-select" id="timeProjectFilter">
                        <option value="all">All Projects</option>
                        <option value="1">Website Redesign</option>
                        <option value="2">Mobile App Development</option>
                        <option value="3">Marketing Campaign</option>
                    </select>
                    <input type="date" class="date-input" id="timeDateFilter" value="{{ date('Y-m-d') }}">
                    <button class="btn-primary" onclick="exportTimeTracking()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="7 10 12 15 17 10"/>
                            <line x1="12" y1="15" x2="12" y2="3"/>
                        </svg>
                        Export
                    </button>
                </div>
            </div>

            <!-- Time Tracking Summary -->
            <div class="time-summary-grid">
                <div class="summary-card">
                    <div class="summary-header">
                        <span class="summary-label">Total Hours Today</span>
                        <div class="summary-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                        </div>
                    </div>
                    <div class="summary-value">00:00:00</div>
                </div>

                <div class="summary-card">
                    <div class="summary-header">
                        <span class="summary-label">This Week</span>
                        <div class="summary-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                <line x1="16" y1="2" x2="16" y2="6"/>
                                <line x1="8" y1="2" x2="8" y2="6"/>
                                <line x1="3" y1="10" x2="21" y2="10"/>
                            </svg>
                        </div>
                    </div>
                    <div class="summary-value">00:00:00</div>
                </div>

                <div class="summary-card">
                    <div class="summary-header">
                        <span class="summary-label">This Month</span>
                        <div class="summary-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                <line x1="16" y1="2" x2="16" y2="6"/>
                                <line x1="8" y1="2" x2="8" y2="6"/>
                                <line x1="3" y1="10" x2="21" y2="10"/>
                            </svg>
                        </div>
                    </div>
                    <div class="summary-value">00:00:00</div>
                </div>
            </div>

            <!-- Time Entries Table -->
            <div class="time-tracking-section">
                <div class="table-container">
                    <table class="data-table" id="timeTrackingTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Project</th>
                                <th>Task</th>
                                <th>Employee</th>
                                <th>Hours</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="timeTrackingTableBody">
                            <!-- Data will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card View -->
                <div class="time-tracking-cards" id="timeTrackingCards">
                    <!-- Cards will be populated by JavaScript -->
                </div>

                <!-- Pagination -->
                <div class="table-pagination">
                    <div class="pagination-info">
                        <span id="timeTrackingPaginationInfo">Showing 1 to 10 of 0 entries</span>
                    </div>
                    <div class="pagination-controls">
                        <button class="pagination-btn" id="timeTrackingPrevBtn" disabled>Previous</button>
                        <div class="pagination-numbers" id="timeTrackingPaginationNumbers"></div>
                        <button class="pagination-btn" id="timeTrackingNextBtn" disabled>Next</button>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Stop Task Modal -->
    <div class="modal-overlay" id="stopTaskModal">
        <div class="modal-container">
            <div class="modal-header">
                <h2 class="modal-title">Stop Task</h2>
                <button class="modal-close" onclick="closeStopTaskModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <form id="stopTaskForm" onsubmit="handleStopTaskSubmit(event)">
                    <input type="hidden" id="stopTaskId" name="task_id">
                    <input type="hidden" id="stopTimeTrackingId" name="time_tracking_id">
                    
                    <div class="form-group">
                        <label class="form-label" style="margin-bottom: 0.75rem;">Task</label>
                        <div style="padding: 0.75rem; background: var(--bg-primary); border-radius: 8px; border: 1px solid var(--border);">
                            <div style="font-weight: 600; color: var(--text-primary);" id="stopTaskTitle"></div>
                            <div style="font-size: 0.875rem; color: var(--text-secondary); margin-top: 0.25rem;" id="stopTaskProject"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="stopTaskNotes" class="form-label">Notes <span class="required">*</span></label>
                        <textarea id="stopTaskNotes" name="notes" class="form-textarea" rows="4" placeholder="Enter notes about what was accomplished..." required></textarea>
                        <small class="form-hint">Please describe what was completed during this time period</small>
                    </div>

                    <div class="form-group">
                        <label for="stopTaskProgress" class="form-label">Update Progress (%) <span class="required">*</span></label>
                        <input type="number" id="stopTaskProgress" name="progress" class="form-input" min="0" max="100" value="0" required>
                        <small class="form-hint">Current task progress: <span id="stopTaskCurrentProgress">0</span>%</small>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn-secondary" onclick="closeStopTaskModal()">Cancel</button>
                        <button type="submit" class="btn-primary">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="6" y="6" width="12" height="12" rx="2"/>
                                <rect x="9" y="9" width="6" height="6"/>
                            </svg>
                            Stop Task
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Task Modal -->
    <div class="modal-overlay" id="editTaskModal">
        <div class="modal-container">
            <div class="modal-header">
                <h2 class="modal-title">Edit Task</h2>
                <button class="modal-close" onclick="closeEditTaskModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <form id="editTaskForm" onsubmit="handleEditTaskSubmit(event)">
                    <input type="hidden" id="editTaskId" name="task_id">
                    <div class="form-group">
                        <label for="editTaskProject" class="form-label">Project <span class="required">*</span></label>
                        <select id="editTaskProject" name="project_id" class="form-select" required>
                            <option value="">Select project</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="editTaskTitle" class="form-label">Task Title <span class="required">*</span></label>
                        <input type="text" id="editTaskTitle" name="title" class="form-input" placeholder="Enter task title" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="editTaskPriority" class="form-label">Priority <span class="required">*</span></label>
                            <select id="editTaskPriority" name="priority" class="form-select" required>
                                <option value="">Select priority</option>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="editTaskStatus" class="form-label">Status</label>
                            <select id="editTaskStatus" name="status" class="form-select">
                                <option value="todo">To Do</option>
                                <option value="in-progress">In Progress</option>
                                <option value="review">In Review</option>
                                <option value="done">Done</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="editTaskAssignedTo" class="form-label">Assigned To</label>
                            <select id="editTaskAssignedTo" name="assigned_to" class="form-select">
                                <option value="">Unassigned</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="editTaskDeadline" class="form-label">Deadline</label>
                            <input type="date" id="editTaskDeadline" name="deadline" class="form-input">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="editTaskDescription" class="form-label">Description</label>
                        <textarea id="editTaskDescription" name="description" class="form-textarea" rows="4" placeholder="Enter task description (optional)"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="editTaskProgress" class="form-label">Progress (%)</label>
                        <input type="number" id="editTaskProgress" name="progress" class="form-input" min="0" max="100" value="0">
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn-secondary" onclick="closeEditTaskModal()">Cancel</button>
                        <button type="submit" class="btn-primary">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            Update Task
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- New Task Modal -->
    <div class="modal-overlay" id="taskModal">
        <div class="modal-container">
            <div class="modal-header">
                <h2 class="modal-title">New Task</h2>
                <button class="modal-close" onclick="closeTaskModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <form id="taskForm" onsubmit="handleTaskSubmit(event)">
                    <div class="form-group">
                        <label for="taskProject" class="form-label">Project <span class="required">*</span></label>
                        <select id="taskProject" name="project_id" class="form-select" required>
                            <option value="">Select project</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="taskTitle" class="form-label">Task Title <span class="required">*</span></label>
                        <input type="text" id="taskTitle" name="title" class="form-input" placeholder="Enter task title" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="taskPriority" class="form-label">Priority <span class="required">*</span></label>
                            <select id="taskPriority" name="priority" class="form-select" required>
                                <option value="">Select priority</option>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="taskStatus" class="form-label">Status</label>
                            <select id="taskStatus" name="status" class="form-select">
                                <option value="todo">To Do</option>
                                <option value="in-progress">In Progress</option>
                                <option value="review">In Review</option>
                                <option value="done">Done</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="taskAssignedTo" class="form-label">Assigned To</label>
                            <select id="taskAssignedTo" name="assigned_to" class="form-select">
                                <option value="">Unassigned</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="taskDeadline" class="form-label">Deadline</label>
                            <input type="date" id="taskDeadline" name="deadline" class="form-input">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="taskDescription" class="form-label">Description</label>
                        <textarea id="taskDescription" name="description" class="form-textarea" rows="4" placeholder="Enter task description (optional)"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="taskProgress" class="form-label">Progress (%)</label>
                        <input type="number" id="taskProgress" name="progress" class="form-input" min="0" max="100" value="0">
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn-secondary" onclick="closeTaskModal()">Cancel</button>
                        <button type="submit" class="btn-primary">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            Create Task
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Project Modal -->
    <div class="modal-overlay" id="editProjectModal">
        <div class="modal-container">
            <div class="modal-header">
                <h2 class="modal-title">Edit Project</h2>
                <button class="modal-close" onclick="closeEditProjectModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <form id="editProjectForm" onsubmit="handleEditProjectSubmit(event)">
                    <input type="hidden" id="editProjectId" name="project_id">
                    <div class="form-group">
                        <label for="editProjectTitle" class="form-label">Project Title <span class="required">*</span></label>
                        <input type="text" id="editProjectTitle" name="title" class="form-input" placeholder="Enter project title" required>
                    </div>

                    <div class="form-group">
                        <label for="editProjectClient" class="form-label">Client Name <span class="required">*</span></label>
                        <input type="hidden" id="editProjectClientId" name="client_id">
                        <div class="client-autocomplete-container">
                            <input type="text" id="editProjectClient" name="client" class="form-input" placeholder="Enter client name" required autocomplete="off">
                            <div class="client-autocomplete-dropdown" id="editProjectClientDropdown"></div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="editProjectStatus" class="form-label">Status <span class="required">*</span></label>
                            <select id="editProjectStatus" name="status" class="form-select" required>
                                <option value="">Select status</option>
                                <option value="active">Active</option>
                                <option value="on-hold">On Hold</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="editProjectDeadline" class="form-label">Deadline <span class="required">*</span></label>
                            <input type="date" id="editProjectDeadline" name="deadline" class="form-input" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="editProjectDescription" class="form-label">Description</label>
                        <textarea id="editProjectDescription" name="description" class="form-textarea" rows="4" placeholder="Enter project description (optional)"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="editProjectTeam" class="form-label">Team Members</label>
                        <select id="editProjectTeam" name="team[]" class="form-select" multiple size="6" style="height: auto; min-height: 120px;">
                        </select>
                        <small class="form-hint">Hold Ctrl (Windows) or Cmd (Mac) to select multiple members</small>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn-secondary" onclick="closeEditProjectModal()">Cancel</button>
                        <button type="submit" class="btn-primary">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            Update Project
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- New Project Modal -->
    <div class="modal-overlay" id="projectModal">
        <div class="modal-container">
            <div class="modal-header">
                <h2 class="modal-title">New Project</h2>
                <button class="modal-close" onclick="closeProjectModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <form id="projectForm" onsubmit="handleProjectSubmit(event)">
                    <div class="form-group">
                        <label for="projectTitle" class="form-label">Project Title <span class="required">*</span></label>
                        <input type="text" id="projectTitle" name="title" class="form-input" placeholder="Enter project title" required>
                    </div>

                    <div class="form-group">
                        <label for="projectClient" class="form-label">Client Name <span class="required">*</span></label>
                        <input type="hidden" id="projectClientId" name="client_id">
                        <div class="client-autocomplete-container">
                            <input type="text" id="projectClient" name="client" class="form-input" placeholder="Enter client name" required autocomplete="off">
                            <div class="client-autocomplete-dropdown" id="projectClientDropdown"></div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="projectStatus" class="form-label">Status <span class="required">*</span></label>
                            <select id="projectStatus" name="status" class="form-select" required>
                                <option value="">Select status</option>
                                <option value="active">Active</option>
                                <option value="on-hold">On Hold</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="projectDeadline" class="form-label">Deadline <span class="required">*</span></label>
                            <input type="date" id="projectDeadline" name="deadline" class="form-input" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="projectDescription" class="form-label">Description</label>
                        <textarea id="projectDescription" name="description" class="form-textarea" rows="4" placeholder="Enter project description (optional)"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="projectTeam" class="form-label">Team Members</label>
                        <div class="team-select-container">
                            <select id="projectTeam" name="team[]" class="form-select" multiple>
                                <option value="JD">John Doe (JD)</option>
                                <option value="JS">Jane Smith (JS)</option>
                                <option value="MJ">Mike Johnson (MJ)</option>
                                <option value="SW">Sarah Williams (SW)</option>
                                <option value="DB">David Brown (DB)</option>
                                <option value="ED">Emily Davis (ED)</option>
                                <option value="RM">Robert Miller (RM)</option>
                                <option value="LW">Lisa Wilson (LW)</option>
                            </select>
                            <small class="form-hint">Hold Ctrl/Cmd to select multiple team members</small>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn-secondary" onclick="closeProjectModal()">Cancel</button>
                        <button type="submit" class="btn-primary">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            Create Project
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
    .project-container {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    /* Tabs */
    .project-tabs {
        display: flex;
        gap: 0.5rem;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 0.5rem;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .tab-btn {
        flex: 1;
        min-width: 150px;
        padding: 0.75rem 1.25rem;
        border: none;
        background: transparent;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.15s;
        white-space: nowrap;
        -webkit-tap-highlight-color: transparent;
    }

    .tab-btn:hover {
        background: var(--bg-primary);
        color: var(--text-primary);
    }

    .tab-btn.active {
        background: var(--accent);
        color: white;
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    /* Section Header */
    .section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .section-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--text-primary);
    }

    .section-actions {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    .subsection-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 1.5rem;
    }

    /* Buttons */
    .btn-primary, .btn-secondary {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.625rem 1.25rem;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.15s;
        border: none;
        -webkit-tap-highlight-color: transparent;
    }

    .btn-primary {
        background: var(--accent);
        color: white;
    }

    .btn-primary:hover {
        background: var(--accent-hover);
    }

    .btn-secondary {
        background: var(--bg-primary);
        color: var(--text-primary);
        border: 1px solid var(--border);
    }

    .btn-secondary:hover {
        background: var(--border);
    }

    .btn-primary svg, .btn-secondary svg {
        width: 18px;
        height: 18px;
    }

    /* Filters */
    .filter-select, .date-input {
        padding: 0.625rem 0.75rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        background: var(--bg-card);
        color: var(--text-primary);
        cursor: pointer;
        transition: all 0.15s;
    }

    .filter-select:focus, .date-input:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(95, 97, 230, 0.1);
    }

    /* Project Stats */
    .project-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.5rem;
    }

    .stat-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
    }

    .stat-label {
        font-size: 0.875rem;
        color: var(--text-secondary);
    }

    .stat-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .stat-icon.blue {
        background: #dbeafe;
        color: #2563eb;
    }

    .stat-icon.green {
        background: #d1fae5;
        color: #059669;
    }

    .stat-icon.purple {
        background: #ede9fe;
        color: #7c3aed;
    }

    .stat-icon.orange {
        background: #fed7aa;
        color: #ea580c;
    }

    .stat-icon svg {
        width: 20px;
        height: 20px;
    }

    .stat-value {
        font-size: 1.875rem;
        font-weight: 700;
        color: var(--text-primary);
    }

    /* Projects Grid */
    .projects-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 1.5rem;
    }

    .project-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.5rem;
        transition: all 0.15s;
        cursor: pointer;
    }

    .project-card:hover {
        border-color: var(--accent);
        box-shadow: 0 4px 12px rgba(95, 97, 230, 0.1);
    }

    .project-card-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 1rem;
    }

    .project-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
    }

    .project-client {
        font-size: 0.875rem;
        color: var(--text-secondary);
    }

    .project-status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 100px;
        font-size: 0.75rem;
        font-weight: 500;
    }

    .project-status-badge.active {
        background: #d1fae5;
        color: #059669;
    }

    .project-status-badge.completed {
        background: #dbeafe;
        color: #2563eb;
    }

    .project-status-badge.on-hold {
        background: #fef3c7;
        color: #d97706;
    }

    .project-meta {
        display: flex;
        align-items: center;
        gap: 1.5rem;
        margin-bottom: 1rem;
        font-size: 0.875rem;
        color: var(--text-secondary);
    }

    .project-meta-item {
        display: flex;
        align-items: center;
        gap: 0.375rem;
    }

    .project-meta-item svg {
        width: 16px;
        height: 16px;
    }

    .project-progress-section {
        margin-top: 1rem;
    }

    .project-progress-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.75rem;
    }

    .project-progress-label {
        font-size: 0.875rem;
        color: var(--text-secondary);
        font-weight: 500;
    }

    .project-progress-value {
        font-size: 0.875rem;
        color: var(--text-primary);
        font-weight: 600;
    }

    .project-progress-bar {
        width: 100%;
        height: 8px;
        background: var(--bg-primary);
        border-radius: 4px;
        overflow: hidden;
    }

    .project-progress-fill {
        height: 100%;
        background: var(--accent);
        border-radius: 4px;
        transition: width 0.3s;
    }

    .project-team {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid var(--border);
    }

    .team-label {
        font-size: 0.75rem;
        color: var(--text-muted);
        margin-right: 0.5rem;
    }

    .team-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: var(--accent);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.75rem;
        border: 2px solid var(--bg-card);
        margin-left: -8px;
    }

    .team-avatar:first-child {
        margin-left: 0;
    }

    /* Tables */
    .table-container {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        margin-bottom: 1.5rem;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
    }

    .data-table thead {
        background: var(--bg-primary);
    }

    .data-table th {
        padding: 0.875rem 1rem;
        text-align: left;
        font-size: 0.8125rem;
        font-weight: 600;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 2px solid var(--border);
        white-space: nowrap;
    }

    .data-table td {
        padding: 1rem;
        font-size: 0.875rem;
        color: var(--text-primary);
        border-bottom: 1px solid var(--border);
    }

    .data-table tbody tr:hover {
        background: var(--bg-primary);
        cursor: pointer;
    }

    /* Tasks Section */
    .tasks-section {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.5rem;
    }

    /* Status Tabs */
    .task-status-tabs {
        display: flex;
        gap: 0.5rem;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 0.5rem;
        margin-bottom: 1.5rem;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .status-tab-btn {
        flex: 1;
        min-width: 100px;
        padding: 0.625rem 1rem;
        border: none;
        background: transparent;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.15s;
        white-space: nowrap;
        -webkit-tap-highlight-color: transparent;
    }

    .status-tab-btn:hover {
        background: var(--bg-primary);
        color: var(--text-primary);
    }

    .status-tab-btn.active {
        background: var(--accent);
        color: white;
    }

    /* Pagination */
    .table-pagination {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
        padding-top: 1.5rem;
        border-top: 1px solid var(--border);
    }

    .pagination-info {
        font-size: 0.875rem;
        color: var(--text-secondary);
    }

    .pagination-controls {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .pagination-btn {
        padding: 0.625rem 1rem;
        border: 1px solid var(--border);
        background: var(--bg-card);
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
        cursor: pointer;
        transition: all 0.15s;
        -webkit-tap-highlight-color: transparent;
    }

    .pagination-btn:hover:not(:disabled) {
        background: var(--bg-primary);
        border-color: var(--accent);
        color: var(--accent);
    }

    .pagination-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .pagination-numbers {
        display: flex;
        align-items: center;
        gap: 0.375rem;
        flex-wrap: wrap;
    }

    .pagination-number {
        min-width: 36px;
        height: 36px;
        padding: 0 0.5rem;
        border: 1px solid var(--border);
        background: var(--bg-card);
        border-radius: 8px;
        font-size: 0.875rem;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.15s;
        display: flex;
        align-items: center;
        justify-content: center;
        -webkit-tap-highlight-color: transparent;
    }

    .pagination-number:hover:not(.active):not(.ellipsis) {
        background: var(--bg-primary);
        border-color: var(--accent);
        color: var(--accent);
    }

    .pagination-number.active {
        background: var(--accent);
        border-color: var(--accent);
        color: white;
    }

    .pagination-number.ellipsis {
        border: none;
        background: none;
        cursor: default;
        min-width: auto;
        padding: 0 0.25rem;
    }

    /* Priority Badge */
    .priority-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 100px;
        font-size: 0.75rem;
        font-weight: 500;
    }

    .priority-badge.high {
        background: #fee2e2;
        color: #dc2626;
    }

    .priority-badge.medium {
        background: #fef3c7;
        color: #d97706;
    }

    .priority-badge.low {
        background: #d1fae5;
        color: #059669;
    }

    /* Status Badge */
    .status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 100px;
        font-size: 0.75rem;
        font-weight: 500;
        display: inline-block;
    }

    .status-badge.todo {
        background: #e5e7eb;
        color: #374151;
    }

    .status-badge.in-progress {
        background: #dbeafe;
        color: #2563eb;
    }

    .status-badge.review {
        background: #fef3c7;
        color: #d97706;
    }

    .status-badge.done {
        background: #d1fae5;
        color: #059669;
    }

    /* Progress Bar */
    .progress-bar-inline {
        width: 100px;
        height: 6px;
        background: var(--bg-primary);
        border-radius: 3px;
        overflow: hidden;
    }

    .progress-fill-inline {
        height: 100%;
        background: var(--accent);
        border-radius: 3px;
        transition: width 0.3s;
    }

    /* Employee Cell */
    .employee-cell {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .employee-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: var(--accent);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.875rem;
        flex-shrink: 0;
    }

    .employee-name {
        font-weight: 500;
        color: var(--text-primary);
    }

    /* Actions */
    .table-actions {
        display: flex;
        gap: 0.5rem;
    }

    .icon-btn {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: none;
        border: 1px solid var(--border);
        border-radius: 6px;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.15s;
        -webkit-tap-highlight-color: transparent;
    }

    .icon-btn:hover {
        background: var(--bg-primary);
        border-color: var(--accent);
        color: var(--accent);
    }

    .icon-btn svg {
        width: 16px;
        height: 16px;
    }

    /* Time Summary */
    .time-summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .summary-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.5rem;
    }

    .summary-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
    }

    .summary-label {
        font-size: 0.875rem;
        color: var(--text-secondary);
    }

    .summary-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        background: var(--accent-light);
        color: var(--accent);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .summary-icon svg {
        width: 18px;
        height: 18px;
    }

    .summary-value {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--text-primary);
    }

    /* Time Tracking Section */
    .time-tracking-section {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.5rem;
    }


    /* Mobile Card Views */
    .tasks-cards,
    .time-tracking-cards {
        display: none;
        flex-direction: column;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .task-card,
    .time-tracking-card {
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.25rem;
    }

    .card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--border);
    }

    .card-title {
        font-weight: 600;
        color: var(--text-primary);
    }

    .card-details {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
    }

    .card-detail {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .card-label {
        font-size: 0.75rem;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .card-value {
        font-size: 0.875rem;
        color: var(--text-primary);
        font-weight: 500;
    }

    /* Responsive */
    @media (min-width: 769px) {
        .table-container {
            display: block;
        }
        .tasks-cards,
        .time-tracking-cards {
            display: none !important;
        }
    }

    @media (max-width: 768px) {
        .table-container {
            display: none !important;
        }
        .tasks-cards,
        .time-tracking-cards {
            display: flex !important;
        }

        .project-tabs {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .tab-btn {
            min-width: 120px;
            font-size: 0.8125rem;
            padding: 0.625rem 1rem;
        }

        .section-header {
            flex-direction: column;
            align-items: stretch;
        }

        .section-actions {
            width: 100%;
        }

        .filter-select,
        .date-input {
            flex: 1;
            min-width: 0;
        }

        .project-stats-grid,
        .time-summary-grid {
            grid-template-columns: 1fr;
        }

        .projects-grid {
            grid-template-columns: 1fr;
        }

        .card-details {
            grid-template-columns: 1fr;
        }

        .task-status-tabs {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .status-tab-btn {
            min-width: 80px;
            font-size: 0.8125rem;
            padding: 0.5rem 0.75rem;
        }

        .table-pagination {
            flex-direction: column;
            align-items: stretch;
        }

        .pagination-controls {
            justify-content: center;
            width: 100%;
        }
    }

    @media (max-width: 480px) {
        .tab-btn {
            min-width: 100px;
            font-size: 0.75rem;
            padding: 0.5rem 0.75rem;
        }
    }

    /* Pulse Animation for Active Time Tracking */
    @keyframes pulse {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: 0.5;
        }
    }

    /* Modal Styles */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(4px);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        opacity: 0;
        transition: opacity 0.2s ease;
    }

    .modal-overlay.active {
        display: flex;
        opacity: 1;
    }

    .modal-container {
        background: var(--bg-card);
        border-radius: 16px;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        width: 100%;
        max-width: 600px;
        max-height: 90vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        transform: scale(0.95);
        transition: transform 0.2s ease;
    }

    .modal-overlay.active .modal-container {
        transform: scale(1);
    }

    .modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1.5rem;
        border-bottom: 1px solid var(--border);
    }

    .modal-title {
        font-size: 1.5rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }

    .modal-close {
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: none;
        border: 1px solid var(--border);
        border-radius: 8px;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.15s;
        -webkit-tap-highlight-color: transparent;
    }

    .modal-close:hover {
        background: var(--bg-primary);
        border-color: var(--accent);
        color: var(--accent);
    }

    .modal-close svg {
        width: 20px;
        height: 20px;
    }

    .modal-body {
        padding: 1.5rem;
        overflow-y: auto;
        flex: 1;
    }

    /* Form Styles */
    /* Client Autocomplete */
    .client-autocomplete-container {
        position: relative;
    }

    .client-autocomplete-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 8px;
        margin-top: 4px;
        max-height: 200px;
        overflow-y: auto;
        z-index: 1000;
        display: none;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .client-autocomplete-dropdown.active {
        display: block;
    }

    .client-autocomplete-item {
        padding: 0.75rem 1rem;
        cursor: pointer;
        transition: background-color 0.15s;
        border-bottom: 1px solid var(--border);
    }

    .client-autocomplete-item:last-child {
        border-bottom: none;
    }

    .client-autocomplete-item:hover,
    .client-autocomplete-item.selected {
        background: var(--bg-primary);
    }

    .client-autocomplete-item-name {
        font-size: 0.875rem;
        color: var(--text-primary);
        font-weight: 500;
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }

    .form-label {
        display: block;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }

    .required {
        color: #dc2626;
    }

    .form-input,
    .form-select,
    .form-textarea {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        background: var(--bg-card);
        color: var(--text-primary);
        transition: all 0.15s;
        font-family: inherit;
    }

    .form-input:focus,
    .form-select:focus,
    .form-textarea:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(95, 97, 230, 0.1);
    }

    .form-textarea {
        resize: vertical;
        min-height: 100px;
    }

    .form-select[multiple] {
        min-height: 120px;
        padding: 0.5rem;
    }

    .form-select[multiple] option {
        padding: 0.5rem;
        border-radius: 4px;
        margin: 0.25rem 0;
    }

    .team-select-container {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .form-hint {
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    .modal-footer {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.75rem;
        padding: 1.5rem;
        border-top: 1px solid var(--border);
        margin-top: auto;
    }

    .modal-footer .btn-primary,
    .modal-footer .btn-secondary {
        min-width: 120px;
    }

    @media (max-width: 768px) {
        .modal-container {
            max-width: 100%;
            max-height: 95vh;
            border-radius: 12px 12px 0 0;
            margin-top: auto;
        }

        .modal-overlay {
            align-items: flex-end;
        }

        .form-row {
            grid-template-columns: 1fr;
        }

        .modal-footer {
            flex-direction: column-reverse;
        }

        .modal-footer .btn-primary,
        .modal-footer .btn-secondary {
            width: 100%;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    // Current user information
    const currentUserId = @json(auth()->id());
    const currentUserName = @json(auth()->user()?->name ?? '');
    const userPermissions = @json(auth()->user()?->getPermissionSlugs() ?? []);
    
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

</script>
@endpush

