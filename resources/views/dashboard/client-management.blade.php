@extends('layouts.app')

@section('title', 'Client Management')

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
                    <tbody id="clientsTableBody">
                        <!-- Data will be populated by JavaScript -->
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
                <button class="modal-tab" data-tab="documents">Documents</button>
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

                <!-- Documents Tab -->
                <div class="modal-tab-content" id="documentsTab">
                    <div class="documents-management">
                        <div class="documents-header">
                            <div>
                                <h3 class="form-section-title">Signed Documents</h3>
                                <p class="section-description">Fully executed contracts available for this client.</p>
                            </div>
                        </div>

                        <div class="documents-list-container" id="documentsListContainer">
                            <div class="empty-state" id="documentsEmptyState">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width: 48px; height: 48px; color: var(--text-muted); margin-bottom: 1rem;">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                    <line x1="16" y1="13" x2="8" y2="13"/>
                                    <line x1="16" y1="17" x2="8" y2="17"/>
                                </svg>
                                <p>No signed documents yet.</p>
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

@push('styles')
<style>
    .client-container {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    /* Header */
    .client-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.25rem;
    }

    .header-left,
    .header-right {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    .search-box {
        position: relative;
        min-width: 250px;
    }

    .search-icon {
        position: absolute;
        left: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
        width: 18px;
        height: 18px;
        pointer-events: none;
    }

    .search-input {
        width: 100%;
        padding: 0.625rem 0.75rem 0.625rem 2.5rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        background: var(--bg-card);
        color: var(--text-primary);
        transition: all 0.15s;
    }

    .search-input:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(95, 97, 230, 0.1);
    }

    .filter-select {
        padding: 0.625rem 0.75rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        background: var(--bg-card);
        color: var(--text-primary);
        cursor: pointer;
        transition: all 0.15s;
    }

    .filter-select:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(95, 97, 230, 0.1);
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

    .btn-danger {
        background: #fef2f2;
        color: #dc2626;
        border: 1px solid #fecaca;
    }

    .btn-danger:hover {
        background: #fee2e2;
        border-color: #f87171;
    }

    .btn-small {
        padding: 0.375rem 0.625rem;
        font-size: 0.8125rem;
    }

    .btn-primary svg, .btn-secondary svg, .btn-danger svg {
        width: 18px;
        height: 18px;
    }

    /* Stats Grid */
    .client-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
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
        margin-bottom: 0.25rem;
    }

    .stat-change {
        font-size: 0.8125rem;
        color: var(--text-secondary);
    }

    .stat-change.positive {
        color: #059669;
    }

    .visually-hidden {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }

    /* Status Tabs */
    .client-status-tabs {
        display: flex;
        gap: 0.25rem;
        padding: 0.5rem 0;
        margin-bottom: 0.5rem;
        border-bottom: 1px solid var(--border);
        flex-wrap: wrap;
    }

    .client-status-tab {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-secondary);
        background: none;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: background 0.15s, color 0.15s;
    }

    .client-status-tab:hover {
        color: var(--text-primary);
        background: var(--bg-secondary);
    }

    .client-status-tab.active {
        color: var(--primary);
        background: var(--bg-secondary);
    }

    /* Tables */
    .clients-section {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.5rem;
    }

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

    .table-checkbox {
        width: 18px;
        height: 18px;
        cursor: pointer;
        accent-color: var(--accent);
    }

    /* Client Cell */
    .client-cell {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .client-avatar {
        width: 40px;
        height: 40px;
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

    .client-info {
        display: flex;
        flex-direction: column;
    }

    .client-name {
        font-weight: 600;
        color: var(--text-primary);
    }

    .client-company {
        font-size: 0.8125rem;
        color: var(--text-secondary);
    }

    /* Status Badge */
    .status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 100px;
        font-size: 0.75rem;
        font-weight: 500;
        display: inline-block;
    }

    .status-badge.active {
        background: #d1fae5;
        color: #059669;
    }

    .status-badge.inactive {
        background: #e5e7eb;
        color: #374151;
    }

    .status-badge.prospect {
        background: #dbeafe;
        color: #2563eb;
    }

    .status-badge.lead {
        background: #fef3c7;
        color: #d97706;
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

    /* Mobile Card View */
    .clients-cards {
        display: none;
        flex-direction: column;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .client-card {
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.25rem;
        cursor: pointer;
        transition: all 0.15s;
    }

    .client-card:hover {
        border-color: var(--accent);
    }

    .card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--border);
    }

    .card-main {
        display: flex;
        align-items: center;
        gap: 0.75rem;
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

    /* Client Modal */
    .client-modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.75);
        z-index: 2000;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        opacity: 0;
        transition: opacity 0.2s;
    }

    /* Add Employee Modal - Higher z-index to appear above client modal */
    #addEmployeeModal,
    #addProjectModal {
        z-index: 3000;
    }

    .client-modal.active {
        display: flex;
        opacity: 1;
    }

    .client-modal-content {
        background: var(--bg-card);
        border-radius: 16px;
        max-width: 900px;
        width: 100%;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
        position: relative;
        transform: scale(0.95);
        transition: transform 0.2s;
        overflow: hidden;
    }

    .client-modal.active .client-modal-content {
        transform: scale(1);
    }

    .modal-close {
        position: absolute;
        top: 1rem;
        right: 1rem;
        width: 40px;
        height: 40px;
        background: rgba(0, 0, 0, 0.5);
        border: none;
        border-radius: 50%;
        color: white;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10;
        transition: background 0.15s;
    }

    .modal-close:hover {
        background: rgba(0, 0, 0, 0.7);
    }

    .modal-close svg {
        width: 20px;
        height: 20px;
    }

    .modal-header {
        padding: 1.5rem;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .modal-client-info {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .modal-client-avatar {
        flex-shrink: 0;
    }

    .avatar-initials-large {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: var(--accent);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 1.5rem;
    }

    .modal-client-name {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0 0 0.25rem 0;
    }

    .modal-client-industry {
        font-size: 0.875rem;
        color: var(--text-secondary);
        margin: 0;
    }

    .modal-tabs {
        display: flex;
        gap: 0.5rem;
        padding: 0 1.5rem;
        border-bottom: 1px solid var(--border);
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
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
        transition: all 0.15s;
        white-space: nowrap;
        -webkit-tap-highlight-color: transparent;
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

    .detail-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1.5rem;
    }

    .detail-item {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .detail-label {
        font-size: 0.75rem;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .detail-value {
        font-size: 0.875rem;
        color: var(--text-primary);
        font-weight: 500;
    }

    .detail-value.highlight {
        color: var(--accent);
        font-weight: 700;
    }

    .detail-value a {
        color: var(--accent);
        text-decoration: none;
    }

    .detail-value a:hover {
        text-decoration: underline;
    }

    /* Contacts List */
    .contacts-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .contact-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        background: var(--bg-primary);
        border-radius: 8px;
    }

    .contact-avatar {
        width: 48px;
        height: 48px;
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

    .contact-info {
        flex: 1;
    }

    .contact-name {
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
    }

    .contact-role {
        font-size: 0.8125rem;
        color: var(--text-secondary);
        margin-bottom: 0.25rem;
    }

    .contact-email {
        font-size: 0.8125rem;
        color: var(--text-muted);
    }

    /* Projects Management */
    .projects-management {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .projects-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .projects-list-container {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .projects-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .project-item {
        padding: 1rem;
        background: var(--bg-primary);
        border-radius: 8px;
        border-left: 3px solid var(--accent);
        cursor: pointer;
        transition: all 0.15s;
    }

    .project-item:hover {
        background: var(--bg-card);
        transform: translateX(4px);
    }

    .project-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 0.75rem;
    }

    .project-name {
        font-weight: 600;
        color: var(--text-primary);
        font-size: 1rem;
        margin: 0;
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

    .project-status-badge.on-hold {
        background: #fef3c7;
        color: #d97706;
    }

    .project-status-badge.completed {
        background: #dbeafe;
        color: #2563eb;
    }

    .project-meta {
        display: flex;
        gap: 1rem;
        font-size: 0.8125rem;
        color: var(--text-secondary);
        flex-wrap: wrap;
    }

    .project-description {
        font-size: 0.875rem;
        color: var(--text-secondary);
        margin-top: 0.5rem;
        line-height: 1.5;
    }

    .project-progress {
        margin-top: 0.75rem;
    }

    .progress-bar {
        width: 100%;
        height: 8px;
        background: var(--bg-primary);
        border-radius: 4px;
        overflow: hidden;
        margin-top: 0.5rem;
    }

    .progress-fill {
        height: 100%;
        background: var(--accent);
        transition: width 0.3s;
        border-radius: 4px;
    }

    /* Employees Management */
    .employees-management {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .employees-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .employees-list-container {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .employee-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 8px;
    }

    .employee-avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: var(--accent);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.875rem;
        flex-shrink: 0;
        position: relative;
        overflow: hidden;
    }

    .employee-avatar-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 50%;
    }

    .employee-avatar-fallback {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .employee-info {
        flex: 1;
        min-width: 0;
    }

    .employee-name {
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
    }

    .employee-email {
        font-size: 0.8125rem;
        color: var(--text-secondary);
        margin-bottom: 0.25rem;
    }

    .employee-department {
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    .employee-actions {
        display: flex;
        gap: 0.5rem;
        flex-shrink: 0;
    }

    .employees-search {
        margin-bottom: 1.5rem;
    }

    .employees-select-list {
        max-height: 400px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        margin-bottom: 1.5rem;
    }

    /* Portal Users Management */
    .portal-users-management {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .portal-users-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .section-description {
        font-size: 0.8125rem;
        color: var(--text-secondary);
        margin-top: 0.25rem;
        max-width: 500px;
    }

    .portal-login-url {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 1rem;
        background: var(--accent-light);
        border: 1px solid var(--accent);
        border-radius: 8px;
        flex-wrap: wrap;
    }

    .portal-login-label {
        font-size: 0.8125rem;
        font-weight: 500;
        color: var(--text-primary);
    }

    .portal-login-link {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex: 1;
    }

    .portal-login-link code {
        font-family: 'SF Mono', Monaco, monospace;
        font-size: 0.8125rem;
        color: var(--accent);
        background: white;
        padding: 0.375rem 0.75rem;
        border-radius: 4px;
        flex: 1;
        word-break: break-all;
    }

    .btn-icon {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: white;
        border: 1px solid var(--border);
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.15s;
        color: var(--text-secondary);
    }

    .btn-icon:hover {
        background: var(--bg-primary);
        color: var(--accent);
    }

    .btn-icon svg {
        width: 16px;
        height: 16px;
    }

    .portal-users-list-container {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .portal-user-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 8px;
        transition: all 0.15s;
    }

    .portal-user-item:hover {
        border-color: var(--accent);
        box-shadow: 0 2px 8px rgba(95, 97, 230, 0.1);
    }

    .portal-user-avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: #10b981;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.875rem;
        flex-shrink: 0;
    }

    .portal-user-info {
        flex: 1;
        min-width: 0;
    }

    .portal-user-name {
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
    }

    .portal-user-email {
        font-size: 0.8125rem;
        color: var(--text-secondary);
        margin-bottom: 0.25rem;
    }

    .portal-user-meta {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    .portal-user-position {
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }

    .portal-user-status {
        display: inline-flex;
        align-items: center;
        padding: 0.125rem 0.5rem;
        border-radius: 9999px;
        font-size: 0.6875rem;
        font-weight: 500;
        text-transform: uppercase;
    }

    .portal-user-status.active {
        background: #ecfdf5;
        color: #059669;
    }

    .portal-user-status.inactive {
        background: #fef2f2;
        color: #dc2626;
    }

    .portal-user-actions {
        display: flex;
        gap: 0.5rem;
        flex-shrink: 0;
    }

    /* Documents Management */
    .documents-management {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .documents-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .documents-list-container {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .document-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem;
        background: var(--bg-primary);
        border-radius: 8px;
        border-left: 3px solid #059669;
    }

    .document-info {
        flex: 1;
        min-width: 0;
    }

    .document-title {
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
    }

    .document-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        font-size: 0.8125rem;
        color: var(--text-muted);
    }

    .document-actions {
        flex-shrink: 0;
    }

    .btn-download-doc {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.5rem 0.875rem;
        background: var(--accent);
        color: #fff;
        border: none;
        border-radius: 6px;
        font-size: 0.8125rem;
        font-weight: 500;
        text-decoration: none;
        cursor: pointer;
        transition: opacity 0.15s;
    }

    .btn-download-doc:hover {
        opacity: 0.9;
        color: #fff;
    }

    .btn-download-doc svg {
        width: 16px;
        height: 16px;
    }

    .password-input-wrapper {
        position: relative;
    }

    .password-input-wrapper .form-input {
        padding-right: 2.5rem;
    }

    .password-toggle-btn {
        position: absolute;
        right: 0.5rem;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        cursor: pointer;
        color: var(--text-muted);
        padding: 0.25rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .password-toggle-btn:hover {
        color: var(--text-primary);
    }

    .password-toggle-btn svg {
        width: 18px;
        height: 18px;
    }

    .form-hint {
        font-size: 0.75rem;
        color: var(--text-muted);
        margin-top: 0.25rem;
    }

    .modal-subtitle {
        font-size: 0.875rem;
        color: var(--text-secondary);
        margin-top: 0.25rem;
    }

    .employee-select-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.15s;
    }

    .employee-select-item:hover {
        background: var(--bg-card);
        border-color: var(--accent);
    }

    .employee-select-item input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
        accent-color: var(--accent);
    }

    .employee-select-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--accent);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.8125rem;
        flex-shrink: 0;
        position: relative;
        overflow: hidden;
    }

    .employee-select-avatar-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 50%;
    }

    .employee-select-avatar-fallback {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .employee-select-info {
        flex: 1;
        min-width: 0;
    }

    .employee-select-name {
        font-weight: 500;
        color: var(--text-primary);
        margin-bottom: 0.125rem;
    }

    .employee-select-email {
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    /* Activity Timeline */
    .activity-timeline {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .timeline-item {
        display: flex;
        gap: 1rem;
        position: relative;
    }

    .timeline-item:not(:last-child)::before {
        content: '';
        position: absolute;
        left: 19px;
        top: 40px;
        bottom: -24px;
        width: 2px;
        background: var(--border);
    }

    .timeline-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--accent-light);
        color: var(--accent);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        z-index: 1;
    }

    .timeline-icon svg {
        width: 20px;
        height: 20px;
    }

    .timeline-content {
        flex: 1;
    }

    .timeline-text {
        font-size: 0.875rem;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
    }

    .timeline-time {
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    /* Notes */
    .notes-section {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .notes-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .note-item {
        padding: 1rem;
        background: var(--bg-primary);
        border-radius: 8px;
        border-left: 3px solid var(--accent);
        position: relative;
    }
    
    .note-item .icon-btn {
        opacity: 0.6;
        transition: opacity 0.15s;
    }
    
    .note-item:hover .icon-btn {
        opacity: 1;
    }

    .note-text {
        font-size: 0.875rem;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
        line-height: 1.5;
    }

    .note-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: 0.75rem;
        color: var(--text-muted);
        margin-top: 0.5rem;
        padding-top: 0.5rem;
        border-top: 1px solid var(--border);
    }
    
    .note-text {
        white-space: pre-wrap;
        word-wrap: break-word;
    }

    .notes-input {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .notes-textarea {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        font-family: inherit;
        resize: vertical;
        min-height: 100px;
        background: var(--bg-card);
        color: var(--text-primary);
    }

    .notes-textarea:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(95, 97, 230, 0.1);
    }

    /* Form Styles */
    .modal-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1.25rem;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .form-group.full-width {
        grid-column: 1 / -1;
    }

    .form-label {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
    }

    .form-label .required {
        color: #ef4444;
    }

    .form-input {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        background: var(--bg-card);
        color: var(--text-primary);
        font-family: inherit;
        transition: all 0.15s;
    }

    .form-input:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(95, 97, 230, 0.1);
    }

    .form-input::placeholder {
        color: var(--text-muted);
    }

    .form-input:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
        margin-top: 2rem;
        padding-top: 1.5rem;
        border-top: 1px solid var(--border);
    }

    /* Responsive */
    @media (min-width: 769px) {
        .table-container {
            display: block;
        }
        .clients-cards {
            display: none !important;
        }
    }

    @media (max-width: 768px) {
        .table-container {
            display: none !important;
        }
        .clients-cards {
            display: flex !important;
        }

        .client-header {
            flex-direction: column;
            align-items: stretch;
        }

        .header-left,
        .header-right {
            width: 100%;
        }

        .search-box {
            min-width: 100%;
        }

        .client-stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .table-pagination {
            flex-direction: column;
            align-items: stretch;
        }

        .pagination-controls {
            justify-content: center;
            width: 100%;
        }

        .client-modal-content {
            max-width: 100%;
            max-height: 100vh;
            border-radius: 0;
        }

        .detail-grid {
            grid-template-columns: 1fr;
        }

        .card-details {
            grid-template-columns: 1fr;
        }

        .form-grid {
            grid-template-columns: 1fr;
        }

        .form-actions {
            flex-direction: column-reverse;
        }

        .form-actions .btn-primary,
        .form-actions .btn-secondary {
            width: 100%;
        }
    }

    /* Contacts Management */
    .contacts-management {
        display: flex;
        flex-direction: column;
        gap: 2rem;
    }

    .contacts-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .contacts-count {
        font-size: 0.875rem;
        color: var(--text-secondary);
        padding: 0.5rem 1rem;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 6px;
    }

    .contacts-count span {
        font-weight: 500;
    }

    .contacts-list-container {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        max-height: 400px;
        overflow-y: auto;
        padding: 0.5rem;
    }

    .contact-item-form {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 8px;
        position: relative;
    }

    .contact-item-form .contact-avatar {
        width: 40px;
        height: 40px;
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

    .contact-item-form .contact-info {
        flex: 1;
        min-width: 0;
    }

    .contact-item-form .contact-name {
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
    }

    .contact-item-form .contact-role {
        font-size: 0.8125rem;
        color: var(--text-secondary);
        margin-bottom: 0.25rem;
    }

    .contact-item-form .contact-email,
    .contact-item-form .contact-phone {
        font-size: 0.8125rem;
        color: var(--text-muted);
    }

    .contact-item-form .contact-actions {
        display: flex;
        gap: 0.5rem;
        flex-shrink: 0;
    }

    .contact-item-form .icon-btn {
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
    }

    .contact-item-form .icon-btn:hover {
        background: var(--bg-card);
        border-color: #ef4444;
        color: #ef4444;
    }

    .empty-state {
        text-align: center;
        padding: 3rem 1rem;
        color: var(--text-muted);
    }

    .empty-state p {
        margin: 0;
        font-size: 0.875rem;
    }

    .add-contact-form {
        padding: 1.5rem;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 8px;
    }

    .form-section-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 1.25rem 0;
    }

    .form-help-text {
        font-size: 0.8125rem;
        color: var(--text-muted);
        margin: -1rem 0 1.25rem 0;
    }

    .contact-form-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.75rem;
        margin-top: 1rem;
        flex-wrap: wrap;
    }

    .contact-add-buttons {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    @media (max-width: 640px) {
        .contact-form-actions {
            flex-direction: column;
        }

        .contact-form-actions .btn-secondary,
        .contact-form-actions .btn-primary {
            width: 100%;
        }

        .contact-add-buttons {
            width: 100%;
            flex-direction: column;
        }

        .contact-add-buttons .btn-secondary,
        .contact-add-buttons .btn-primary {
            width: 100%;
        }
    }

    @media (max-width: 480px) {
        .client-stats-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@push('scripts')
<script>
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

            // Load documents when documents tab is clicked
            if (tabId === 'documents' && currentClientId) {
                loadClientDocuments(currentClientId);
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
    async function loadClientDocuments(clientId) {
        const container = document.getElementById('documentsListContainer');
        const emptyState = document.getElementById('documentsEmptyState');

        if (!container) return;

        container.querySelectorAll('.document-item').forEach(item => item.remove());
        emptyState.style.display = 'block';
        emptyState.innerHTML = '<p>Loading documents...</p>';

        try {
            const response = await fetch(`${apiBase}/clients/${clientId}/contracts`);
            const result = await response.json();

            if (!result.success) {
                emptyState.innerHTML = '<p>Failed to load documents.</p>';
                return;
            }

            renderDocumentsFromData(result.data || []);
        } catch (error) {
            console.error('Error loading documents:', error);
            emptyState.innerHTML = '<p>Failed to load documents.</p>';
        }
    }

    function renderDocumentsFromData(documents) {
        const container = document.getElementById('documentsListContainer');
        const emptyState = document.getElementById('documentsEmptyState');

        if (!container) return;

        container.querySelectorAll('.document-item').forEach(item => item.remove());

        if (!documents || documents.length === 0) {
            emptyState.style.display = 'block';
            emptyState.innerHTML = `
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width: 48px; height: 48px; color: var(--text-muted); margin-bottom: 1rem;">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="16" y1="13" x2="8" y2="13"/>
                    <line x1="16" y1="17" x2="8" y2="17"/>
                </svg>
                <p>No signed documents yet.</p>
            `;
            return;
        }

        emptyState.style.display = 'none';

        documents.forEach(doc => {
            const item = document.createElement('div');
            item.className = 'document-item';
            item.innerHTML = `
                <div class="document-info">
                    <div class="document-title">${doc.title || doc.contract_number}</div>
                    <div class="document-meta">
                        <span>${doc.contract_number}</span>
                        ${doc.signed_at ? `<span>Signed ${doc.signed_at}</span>` : ''}
                        ${doc.effective_date ? `<span>Effective ${doc.effective_date}</span>` : ''}
                    </div>
                </div>
                <div class="document-actions">
                    <a href="${doc.pdf_url}" class="btn-download-doc" target="_blank" rel="noopener">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="7 10 12 15 17 10"/>
                            <line x1="12" y1="15" x2="12" y2="3"/>
                        </svg>
                        Download PDF
                    </a>
                </div>
            `;
            container.appendChild(item);
        });
    }

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
        const url = new URL('{{ route("project-management") }}', window.location.origin);
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
</script>
@endpush

