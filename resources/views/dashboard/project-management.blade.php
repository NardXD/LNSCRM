@extends('layouts.app')

@section('title', 'Project Management')

@push('styles')
    @vite(['resources/css/pages/project-management.css'])
@endpush

@push('scripts')
<script>
    window.__projectManagementConfig = {
        currentUserId: @json(auth()->id()),
        currentUserName: @json(auth()->user()?->name ?? ''),
        userPermissions: @json(auth()->user()?->getPermissionSlugs() ?? []),
    };
</script>
    @vite(['resources/js/pages/project-management.js'])
@endpush

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
                        <tbody id="tasksTableBody" aria-busy="true">
                            @include('partials.skeleton-table-rows', ['rows' => 8, 'cols' => 7])
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
