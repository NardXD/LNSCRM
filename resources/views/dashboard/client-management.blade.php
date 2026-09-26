@extends('layouts.app')

@section('title', 'Client Management')

@push('styles')
    @vite(['resources/css/pages/client-management.css'])
@endpush

@push('scripts')
<script>
    window.__clientManagementConfig = {
        projectManagementUrl: @json(route('project-management')),
    };
</script>
    @vite(['resources/js/pages/client-management.js'])
@endpush

@section('content')
    <div class="page-header">
        <h1 class="page-title">Client Management</h1>
        <p class="page-subtitle">Manage clients, contacts, and relationships</p>
    </div>

    <div class="client-container">
        <!-- Header Actions -->
        <div class="client-header">
            <div class="header-left">
                <div class="search-box">
                    <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="m21 21-4.35-4.35"/>
                    </svg>
                    <input type="text" class="search-input" placeholder="Search clients..." id="clientSearch">
                </div>
                <select class="filter-select visually-hidden" id="statusFilter" aria-hidden="true">
                    <option value="lead">Lead</option>
                    <option value="prospect">Prospect</option>
                    <option value="active" selected>Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                <select class="filter-select" id="industryFilter">
                    <option value="all">All Industries</option>
                    <option value="technology">Technology</option>
                    <option value="finance">Finance</option>
                    <option value="healthcare">Healthcare</option>
                    <option value="retail">Retail</option>
                    <option value="manufacturing">Manufacturing</option>
                </select>
            </div>
            <div class="header-right">
                <button class="btn-secondary" onclick="exportClients()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="7 10 12 15 17 10"/>
                        <line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                    Export
                </button>
                <button class="btn-primary" onclick="createClient()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    New Client
                </button>
            </div>
        </div>

        <!-- Client Stats -->
        <div class="client-stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Total Clients</span>
                    <div class="stat-icon blue">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-value">248</div>
                <div class="stat-change positive">+12 this month</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Active Clients</span>
                    <div class="stat-icon green">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-value">186</div>
                <div class="stat-change positive">75% of total</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">New This Month</span>
                    <div class="stat-icon purple">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="12 6 12 12 16 14"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-value">24</div>
                <div class="stat-change positive">+8.5% growth</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Total Revenue</span>
                    <div class="stat-icon orange">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="1" x2="12" y2="23"/>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-value">$2.4M</div>
                <div class="stat-change positive">+15.2% from last month</div>
            </div>
        </div>

        <!-- Status Tabs -->
        <div class="client-status-tabs" role="tablist">
            <button type="button" class="client-status-tab active" role="tab" data-status="active" id="statusTabActive">Active</button>
            <button type="button" class="client-status-tab" role="tab" data-status="lead" id="statusTabLead">Lead</button>
            <button type="button" class="client-status-tab" role="tab" data-status="prospect" id="statusTabProspect">Prospect</button>
            <button type="button" class="client-status-tab" role="tab" data-status="inactive" id="statusTabInactive">Inactive</button>
        </div>

        <!-- Clients Table -->
        <div class="clients-section">
            <div class="table-container">
                <table class="data-table" id="clientsTable">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" class="table-checkbox" id="selectAllClients">
                            </th>
                            <th>Client</th>
                            <th>Contact Person</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Industry</th>
                            <th>Status</th>
                            <th>Total Revenue</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="clientsTableBody" aria-busy="true">
                        @include('partials.skeleton-table-rows', ['rows' => 8, 'cols' => 9])
                    </tbody>
                </table>
            </div>

            <!-- Mobile Card View -->
            <div class="clients-cards" id="clientsCards">
                <!-- Cards will be populated by JavaScript -->
            </div>

            <!-- Pagination -->
            <div class="table-pagination">
                <div class="pagination-info">
                    <span id="paginationInfo">Showing 1 to 10 of 248 results</span>
                </div>
                <div class="pagination-controls">
                    <button class="pagination-btn" id="prevBtn" disabled>Previous</button>
                    <div class="pagination-numbers" id="paginationNumbers"></div>
                    <button class="pagination-btn" id="nextBtn">Next</button>
                </div>
            </div>
        </div>
    </div>

    <!-- New/Edit Client Modal -->
    <div class="client-modal" id="newClientModal">
        <div class="client-modal-content">
            <button class="modal-close" onclick="closeNewClientModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>

            <div class="modal-header">
                <h2 class="modal-title" id="newClientModalTitle">New Client</h2>
            </div>

            <div class="modal-tabs">
                <button class="modal-tab active" data-tab="clientInfo">Client Information</button>
                <button class="modal-tab" data-tab="contacts">Contacts</button>
            </div>

            <div class="modal-body">
                <form id="newClientForm" onsubmit="submitClientForm(event)">
                    <!-- Client Information Tab -->
                    <div class="modal-tab-content active" id="clientInfoTab">
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label for="clientName" class="form-label">Company Name <span class="required">*</span></label>
                                <input type="text" id="clientName" name="name" class="form-input" required placeholder="Enter company name">
                            </div>

                            <div class="form-group">
                                <label for="contactPerson" class="form-label">Primary Contact Person <span class="required">*</span></label>
                                <input type="text" id="contactPerson" name="contactPerson" class="form-input" required placeholder="Enter contact name">
                            </div>

                            <div class="form-group">
                                <label for="contactEmail" class="form-label">Primary Email <span class="required">*</span></label>
                                <input type="email" id="contactEmail" name="email" class="form-input" required placeholder="Enter email address">
                            </div>

                            <div class="form-group">
                                <label for="contactPhone" class="form-label">Primary Phone</label>
                                <input type="tel" id="contactPhone" name="phone" class="form-input" placeholder="Enter phone number">
                            </div>

                            <div class="form-group">
                                <label for="clientIndustry" class="form-label">Industry</label>
                                <select id="clientIndustry" name="industry" class="form-input">
                                    <option value="">Select industry</option>
                                    <option value="technology">Technology</option>
                                    <option value="finance">Finance</option>
                                    <option value="healthcare">Healthcare</option>
                                    <option value="retail">Retail</option>
                                    <option value="manufacturing">Manufacturing</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="clientStatus" class="form-label">Status <span class="required">*</span></label>
                                <select id="clientStatus" name="status" class="form-input" required>
                                    <option value="lead">Lead</option>
                                    <option value="prospect">Prospect</option>
                                    <option value="active" selected>Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="clientWebsite" class="form-label">Website</label>
                                <input type="url" id="clientWebsite" name="website" class="form-input" placeholder="https://example.com">
                            </div>

                            <div class="form-group">
                                <label for="clientRevenue" class="form-label">Initial Revenue</label>
                                <input type="number" id="clientRevenue" name="revenue" class="form-input" placeholder="0" min="0" step="0.01">
                            </div>

                            <div class="form-group full-width">
                                <label for="clientAddress" class="form-label">Address</label>
                                <textarea id="clientAddress" name="address" class="form-input" rows="3" placeholder="Enter full address"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Contacts Tab -->
                    <div class="modal-tab-content" id="contactsTab">
                        <div class="contacts-management">
                            <div class="contacts-header">
                                <h3 class="form-section-title">Contacts</h3>
                                <div class="contacts-count" id="contactsCount">
                                    <span id="contactsCountText">0 contacts added</span>
                                </div>
                            </div>

                            <div class="contacts-list-container" id="contactsListContainer">
                                <div class="empty-state" id="contactsEmptyState">
                                    <p>No contacts added yet. Add contacts below.</p>
                                </div>
                            </div>

                            <div class="add-contact-form">
                                <h3 class="form-section-title">Add Contact</h3>
                                <p class="form-help-text">You can add multiple contacts. Press Enter to quickly add a contact.</p>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="contactName" class="form-label">Name <span class="required">*</span></label>
                                        <input type="text" id="contactName" class="form-input" placeholder="Enter contact name" onkeypress="handleContactFormKeypress(event)">
                                    </div>

                                    <div class="form-group">
                                        <label for="contactRole" class="form-label">Role/Title</label>
                                        <input type="text" id="contactRole" class="form-input" placeholder="e.g., CEO, CTO, Manager" onkeypress="handleContactFormKeypress(event)">
                                    </div>

                                    <div class="form-group">
                                        <label for="contactEmailInput" class="form-label">Email</label>
                                        <input type="email" id="contactEmailInput" class="form-input" placeholder="Enter email address" onkeypress="handleContactFormKeypress(event)">
                                    </div>

                                    <div class="form-group">
                                        <label for="contactPhoneInput" class="form-label">Phone</label>
                                        <input type="tel" id="contactPhoneInput" class="form-input" placeholder="Enter phone number" onkeypress="handleContactFormKeypress(event)">
                                    </div>
                                </div>

                                <div class="contact-form-actions">
                                    <button type="button" class="btn-secondary" onclick="clearContactForm()">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="3 6 5 6 21 6"/>
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                        </svg>
                                        Clear
                                    </button>
                                    <div class="contact-add-buttons">
                                        <button type="button" class="btn-secondary" onclick="addContactToList(true)">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <line x1="12" y1="5" x2="12" y2="19"/>
                                                <line x1="5" y1="12" x2="19" y2="12"/>
                                            </svg>
                                            Add & Add Another
                                        </button>
                                        <button type="button" class="btn-primary" onclick="addContactToList(false)">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <line x1="12" y1="5" x2="12" y2="19"/>
                                                <line x1="5" y1="12" x2="19" y2="12"/>
                                            </svg>
                                            Add Contact
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-secondary" onclick="closeNewClientModal()">Cancel</button>
                        <button type="submit" class="btn-primary" id="submitClientBtn">
                            <span id="submitBtnText">Create Client</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Project Modal -->
    <div class="client-modal" id="addProjectModal">
        <div class="client-modal-content" style="max-width: 600px;">
            <button class="modal-close" onclick="closeAddProjectModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>

            <div class="modal-header">
                <h2 class="modal-title">New Project</h2>
            </div>

            <div class="modal-body">
                <form id="newProjectForm" onsubmit="submitProjectForm(event)">
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label for="projectTitle" class="form-label">Project Title <span class="required">*</span></label>
                            <input type="text" id="projectTitle" name="title" class="form-input" required placeholder="Enter project title">
                        </div>

                        <div class="form-group">
                            <label for="projectStatus" class="form-label">Status <span class="required">*</span></label>
                            <select id="projectStatus" name="status" class="form-input" required>
                                <option value="active" selected>Active</option>
                                <option value="on-hold">On Hold</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="projectDeadline" class="form-label">Deadline <span class="required">*</span></label>
                            <input type="date" id="projectDeadline" name="deadline" class="form-input" required>
                        </div>

                        <div class="form-group full-width">
                            <label for="projectDescription" class="form-label">Description</label>
                            <textarea id="projectDescription" name="description" class="form-input" rows="4" placeholder="Enter project description"></textarea>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-secondary" onclick="closeAddProjectModal()">Cancel</button>
                        <button type="submit" class="btn-primary">Create Project</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Employees Modal -->
    <div class="client-modal" id="addEmployeeModal">
        <div class="client-modal-content" style="max-width: 600px;">
            <button class="modal-close" onclick="closeAddEmployeeModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>

            <div class="modal-header">
                <h2 class="modal-title">Add Employees</h2>
            </div>

            <div class="modal-body">
                <div class="employees-search">
                    <input type="text" id="employeeSearchInput" class="form-input" placeholder="Search employees..." onkeyup="filterEmployeeList()">
                </div>

                <div class="employees-select-list" id="employeesSelectList">
                    <!-- Employees will be populated by JavaScript -->
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeAddEmployeeModal()">Cancel</button>
                    <button type="button" class="btn-primary" onclick="assignSelectedEmployees()">
                        Add Selected Employees
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Client Detail Modal -->
    <div class="client-modal" id="clientModal">
        <div class="client-modal-content">
            <button class="modal-close" onclick="closeClientModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>

            <div class="modal-header">
                <div class="modal-client-info">
                    <div class="modal-client-avatar">
                        <div class="avatar-initials-large" id="modalClientInitials">AC</div>
                    </div>
                    <div>
                        <h2 class="modal-client-name" id="modalClientName">Acme Corporation</h2>
                        <p class="modal-client-industry" id="modalClientIndustry">Technology</p>
                    </div>
                </div>
                <div class="modal-actions">
                    <button class="btn-secondary" onclick="editClient()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                        </svg>
                        Edit
                    </button>
                </div>
            </div>

            <div class="modal-tabs">
                <button class="modal-tab active" data-tab="overview">Overview</button>
                <button class="modal-tab" data-tab="contacts">Contacts</button>
                <button class="modal-tab" data-tab="projects">Projects</button>
                <button class="modal-tab" data-tab="employees">Employee List</button>
                <button class="modal-tab" data-tab="portalUsers">Portal Users</button>
                <button class="modal-tab" data-tab="notes">Notes</button>
            </div>

            <div class="modal-body">
                <!-- Overview Tab -->
                <div class="modal-tab-content active" id="overviewTab">
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="detail-label">Company Name</span>
                            <span class="detail-value" id="detailCompanyName">Acme Corporation</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Primary Contact</span>
                            <span class="detail-value" id="detailContactPerson">N/A</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Email</span>
                            <span class="detail-value" id="detailEmail">N/A</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Phone</span>
                            <span class="detail-value" id="detailPhone">N/A</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Industry</span>
                            <span class="detail-value" id="detailIndustry">Technology</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Status</span>
                            <span class="detail-value" id="detailStatus"><span class="status-badge active">Active</span></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Website</span>
                            <span class="detail-value" id="detailWebsite">N/A</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Address</span>
                            <span class="detail-value" id="detailAddress">N/A</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Total Revenue</span>
                            <span class="detail-value highlight" id="detailRevenue">$0</span>
                        </div>
                    </div>
                </div>

                <!-- Contacts Tab -->
                <div class="modal-tab-content" id="contactsTab">
                    <div class="contacts-list" id="contactsList">
                        <!-- Contacts will be populated by JavaScript -->
                    </div>
                </div>

                <!-- Projects Tab -->
                <div class="modal-tab-content" id="projectsTab">
                    <div class="projects-management">
                        <div class="projects-header">
                            <h3 class="form-section-title">Projects</h3>
                        </div>

                        <div class="projects-list-container" id="projectsListContainer">
                            <div class="empty-state" id="projectsEmptyState">
                                <p>No projects found for this client.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Employees Tab -->
                <div class="modal-tab-content" id="employeesTab">
                    <div class="employees-management">
                        <div class="employees-header">
                            <h3 class="form-section-title">Assigned Employees</h3>
                            <button type="button" class="btn-primary" onclick="openAddEmployeeModal()">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="12" y1="5" x2="12" y2="19"/>
                                    <line x1="5" y1="12" x2="19" y2="12"/>
                                </svg>
                                Add Employees
                            </button>
                        </div>

                        <div class="employees-list-container" id="employeesListContainer">
                            <div class="empty-state" id="employeesEmptyState">
                                <p>No employees assigned yet. Click "Add Employees" to assign employees to this client.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Portal Users Tab -->
                <div class="modal-tab-content" id="portalUsersTab">
                    <div class="portal-users-management">
                        <div class="portal-users-header">
                            <div>
                                <h3 class="form-section-title">Client Portal Users</h3>
                                <p class="section-description">Manage login credentials for client portal access. These users can view assigned employees and their monitoring data.</p>
                            </div>
                            <button type="button" class="btn-primary" onclick="openAddPortalUserModal()">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="12" y1="5" x2="12" y2="19"/>
                                    <line x1="5" y1="12" x2="19" y2="12"/>
                                </svg>
                                Add Portal User
                            </button>
                        </div>

                        <div class="portal-login-url">
                            <span class="portal-login-label">Client Portal Login URL:</span>
                            <div class="portal-login-link">
                                <code id="portalLoginUrl">{{ url('/client/login') }}</code>
                                <button type="button" class="btn-icon" onclick="copyPortalUrl()" title="Copy URL">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="portal-users-list-container" id="portalUsersListContainer">
                            <div class="empty-state" id="portalUsersEmptyState">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width: 48px; height: 48px; color: var(--text-muted); margin-bottom: 1rem;">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                </svg>
                                <p>No portal users created yet.</p>
                                <p style="font-size: 0.8125rem; color: var(--text-muted);">Create a portal user to allow this client to view their assigned employees.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notes Tab -->
                <div class="modal-tab-content" id="notesTab">
                    <div class="notes-section">
                        <div class="notes-list" id="notesList">
                            <!-- Notes will be populated by JavaScript -->
                        </div>
                        <div class="notes-input">
                            <textarea class="notes-textarea" id="notesTextarea" placeholder="Add a note..." maxlength="5000" rows="4"></textarea>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem;">
                                <span style="font-size: 0.75rem; color: var(--text-muted);" id="noteCharCount">0 / 5000 characters</span>
                                <button type="button" class="btn-primary" onclick="addNote()">Add Note</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Portal User Modal -->
    <div class="client-modal" id="addPortalUserModal">
        <div class="client-modal-content" style="max-width: 550px;">
            <button class="modal-close" onclick="closeAddPortalUserModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>

            <div class="modal-header">
                <h2 class="modal-title" id="portalUserModalTitle">Add Portal User</h2>
                <p class="modal-subtitle">Create login credentials for client portal access</p>
            </div>

            <div class="modal-body">
                <form id="portalUserForm" onsubmit="submitPortalUserForm(event)">
                    <input type="hidden" id="portalUserId" name="id">

                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label for="portalUserName" class="form-label">Full Name <span class="required">*</span></label>
                            <input type="text" id="portalUserName" name="name" class="form-input" required placeholder="Enter full name">
                        </div>

                        <div class="form-group full-width">
                            <label for="portalUserEmail" class="form-label">Email Address <span class="required">*</span></label>
                            <input type="email" id="portalUserEmail" name="email" class="form-input" required placeholder="Enter email address">
                            <span class="form-hint">This will be used as the login username</span>
                        </div>

                        <div class="form-group full-width">
                            <label for="portalUserPassword" class="form-label">Password <span class="required" id="passwordRequired">*</span></label>
                            <div class="password-input-wrapper">
                                <input type="password" id="portalUserPassword" name="password" class="form-input" required placeholder="Enter password" minlength="8">
                                <button type="button" class="password-toggle-btn" onclick="togglePortalUserPassword()">
                                    <svg id="portalPasswordEye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                </button>
                            </div>
                            <span class="form-hint" id="passwordHint">Minimum 8 characters</span>
                        </div>

                        <div class="form-group">
                            <label for="portalUserPhone" class="form-label">Phone Number</label>
                            <input type="text" id="portalUserPhone" name="phone" class="form-input" placeholder="Enter phone number">
                        </div>

                        <div class="form-group">
                            <label for="portalUserPosition" class="form-label">Position/Title</label>
                            <input type="text" id="portalUserPosition" name="position" class="form-input" placeholder="e.g., Account Manager">
                        </div>

                        <div class="form-group full-width" id="portalUserStatusGroup" style="display: none;">
                            <label for="portalUserStatus" class="form-label">Status</label>
                            <select id="portalUserStatus" name="status" class="form-input">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-secondary" onclick="closeAddPortalUserModal()">Cancel</button>
                        <button type="submit" class="btn-primary" id="submitPortalUserBtn">
                            <span id="submitPortalUserBtnText">Create Portal User</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
