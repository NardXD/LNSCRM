@extends('layouts.app')

@section('title', 'Admin Control Panel')

@push('styles')
    @vite(['resources/css/pages/admin-control.css'])
@endpush

@push('scripts')
    @vite(['resources/js/pages/admin-control.js'])
@endpush

@section('content')
    <div class="page-header">
        <h1 class="page-title">Admin Control Panel</h1>
        <p class="page-subtitle">Manage billing, company access, and system settings</p>
    </div>

    <!-- Admin Stats Overview -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Total Companies</span>
                <div class="stat-icon blue">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value">156</div>
            <div class="stat-change positive">+8 this month</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Active Subscriptions</span>
                <div class="stat-icon green">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value">142</div>
            <div class="stat-change positive">91% active rate</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Monthly Revenue</span>
                <div class="stat-icon purple">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="1" x2="12" y2="23"/>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value">$245,680</div>
            <div class="stat-change positive">+15.2% from last month</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Pending Approvals</span>
                <div class="stat-icon orange">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value">12</div>
            <div class="stat-change">Requires attention</div>
        </div>
    </div>

    <!-- Main Admin Sections -->
    <div class="admin-sections-grid">
        <!-- Billing Management Section -->
        <div class="admin-section-card" id="billing">
            <div class="section-card-header">
                <div class="section-card-title">
                    <div class="section-icon blue">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="1" x2="12" y2="23"/>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="section-title">Billing Management</h2>
                        <p class="section-subtitle">Control subscriptions, plans, and payments</p>
                    </div>
                </div>
            </div>

            <div class="section-card-body">
                <!-- Subscription Plans -->
                <div class="admin-subsection">
                    <div class="subsection-header">
                        <h3 class="subsection-title">Subscription Plans</h3>
                        <button class="btn-sm btn-primary" onclick="openPlanModal()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="5" x2="12" y2="19"/>
                                <line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                            Add Plan
                        </button>
                    </div>
                    <div class="plans-grid" id="plansGrid">
                        <!-- Plans will be populated by JavaScript -->
                    </div>
                </div>

                <!-- Company Billing Overview -->
                <div class="admin-subsection">
                    <div class="subsection-header">
                        <h3 class="subsection-title">Company Billing Overview</h3>
                        <div class="filter-group">
                            <select class="filter-select" id="billingFilter">
                                <option value="all">All Companies</option>
                                <option value="active">Active</option>
                                <option value="trial">Trial</option>
                                <option value="expired">Expired</option>
                                <option value="suspended">Suspended</option>
                            </select>
                            <input type="text" class="search-input" placeholder="Search companies..." id="billingSearch">
                        </div>
                    </div>
                    <div class="table-container">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Company</th>
                                    <th>Plan</th>
                                    <th>Status</th>
                                    <th>Billing Cycle</th>
                                    <th>Amount</th>
                                    <th>Next Billing</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="billingTableBody">
                                <!-- Billing data will be populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Payment History -->
                <div class="admin-subsection">
                    <div class="subsection-header">
                        <h3 class="subsection-title">Recent Payments</h3>
                        <a href="#" class="link-text">View All</a>
                    </div>
                    <div class="payments-list" id="paymentsList">
                        <!-- Payments will be populated by JavaScript -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Company Access Control Section -->
        <div class="admin-section-card" id="access">
            <div class="section-card-header">
                <div class="section-card-title">
                    <div class="section-icon green">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="section-title">Company Access Control</h2>
                        <p class="section-subtitle">Manage feature access and permissions</p>
                    </div>
                </div>
            </div>

            <div class="section-card-body">
                <!-- Company Selection -->
                <div class="admin-subsection">
                    <div class="subsection-header">
                        <h3 class="subsection-title">Select Company</h3>
                        <select class="filter-select" id="companySelector" onchange="loadCompanyAccess()">
                            <option value="">Select a company...</option>
                            <!-- Options will be populated by JavaScript -->
                        </select>
                    </div>
                </div>

                <!-- Feature Access Control -->
                <div class="admin-subsection" id="featureAccessSection" style="display: none;">
                    <div class="subsection-header">
                        <h3 class="subsection-title">Feature Access</h3>
                        <button class="btn-sm btn-secondary" onclick="saveAccessSettings()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                                <polyline points="17 21 17 13 7 13 7 21"/>
                                <polyline points="7 3 7 8 15 8"/>
                            </svg>
                            Save Changes
                        </button>
                    </div>
                    <div class="features-grid" id="featuresGrid">
                        <!-- Features will be populated by JavaScript -->
                    </div>
                </div>

                <!-- User Role Permissions -->
                <div class="admin-subsection" id="rolePermissionsSection" style="display: none;">
                    <div class="subsection-header">
                        <h3 class="subsection-title">Role-Based Permissions</h3>
                        <button class="btn-sm btn-primary" onclick="openRoleModal()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="5" x2="12" y2="19"/>
                                <line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                            Create Role
                        </button>
                    </div>
                    <div class="roles-list" id="rolesList">
                        <!-- Roles will be populated by JavaScript -->
                    </div>
                </div>

                <!-- Access Logs -->
                <div class="admin-subsection">
                    <div class="subsection-header">
                        <h3 class="subsection-title">Access Logs</h3>
                        <select class="filter-select" id="logFilter">
                            <option value="all">All Activities</option>
                            <option value="login">Logins</option>
                            <option value="permission">Permission Changes</option>
                            <option value="feature">Feature Access</option>
                        </select>
                    </div>
                    <div class="access-logs" id="accessLogs">
                        <!-- Logs will be populated by JavaScript -->
                    </div>
                </div>
            </div>
        </div>

        <!-- System Controls Section -->
        <div class="admin-section-card" id="system">
            <div class="section-card-header">
                <div class="section-card-title">
                    <div class="section-icon purple">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="3"/>
                            <path d="M12 1v6m0 6v6M5.64 5.64l4.24 4.24m4.24 4.24l4.24 4.24M1 12h6m6 0h6M5.64 18.36l4.24-4.24m4.24-4.24l4.24-4.24"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="section-title">System Controls</h2>
                        <p class="section-subtitle">System-wide settings and configurations</p>
                    </div>
                </div>
            </div>

            <div class="section-card-body">
                <!-- System Settings -->
                <div class="admin-subsection">
                    <div class="subsection-header">
                        <h3 class="subsection-title">System Settings</h3>
                        <button class="btn-sm btn-secondary" onclick="saveSystemSettings()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                                <polyline points="17 21 17 13 7 13 7 21"/>
                                <polyline points="7 3 7 8 15 8"/>
                            </svg>
                            Save Settings
                        </button>
                    </div>
                    <div class="settings-list" id="systemSettings">
                        <!-- Settings will be populated by JavaScript -->
                    </div>
                </div>

                <!-- User Management -->
                <div class="admin-subsection">
                    <div class="subsection-header">
                        <h3 class="subsection-title">User Management</h3>
                        <button class="btn-sm btn-primary" onclick="openUserModal()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="5" x2="12" y2="19"/>
                                <line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                            Add User
                        </button>
                    </div>
                    <div class="table-container">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Company</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="usersTableBody">
                                <!-- Users will be populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- System Health -->
                <div class="admin-subsection">
                    <div class="subsection-header">
                        <h3 class="subsection-title">System Health</h3>
                        <button class="btn-sm btn-secondary" onclick="refreshSystemHealth()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="23 4 23 10 17 10"/>
                                <polyline points="1 20 1 14 7 14"/>
                                <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
                            </svg>
                            Refresh
                        </button>
                    </div>
                    <div class="health-metrics" id="healthMetrics">
                        <!-- Health metrics will be populated by JavaScript -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Admin Support & Override Controls Section -->
        <div class="admin-section-card" id="support">
            <div class="section-card-header">
                <div class="section-card-title">
                    <div class="section-icon orange">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                            <path d="M9 12l2 2 4-4"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="section-title">Admin Support & Override Controls</h2>
                        <p class="section-subtitle">Bypass restrictions, provide support, and troubleshoot issues</p>
                    </div>
                </div>
            </div>

            <div class="section-card-body">
                <!-- Support Quick Actions -->
                <div class="admin-subsection">
                    <div class="subsection-header">
                        <h3 class="subsection-title">Quick Support Actions</h3>
                        <div class="support-badge">
                            <span class="badge-text">Admin Mode: Active</span>
                        </div>
                    </div>
                    <div class="support-actions-grid">
                        <button class="support-action-card" onclick="openCompanyModuleReview()">
                            <div class="support-action-icon blue">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                    <line x1="16" y1="13" x2="8" y2="13"/>
                                    <line x1="16" y1="17" x2="8" y2="17"/>
                                </svg>
                            </div>
                            <div class="support-action-content">
                                <h4>Review All Modules</h4>
                                <p>View and manage module access for all companies</p>
                            </div>
                        </button>
                        <button class="support-action-card" onclick="openEmergencyAccess()">
                            <div class="support-action-icon red">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                    <path d="M12 8v4"/>
                                    <path d="M12 16h.01"/>
                                </svg>
                            </div>
                            <div class="support-action-content">
                                <h4>Emergency Access</h4>
                                <p>Grant temporary full access for troubleshooting</p>
                            </div>
                        </button>
                        <button class="support-action-card" onclick="openSupportTickets()">
                            <div class="support-action-icon orange">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                    <polyline points="22,6 12,13 2,6"/>
                                </svg>
                            </div>
                            <div class="support-action-content">
                                <h4>Support Tickets</h4>
                                <p>View and manage support requests</p>
                            </div>
                        </button>
                        <button class="support-action-card" onclick="openBypassLog()">
                            <div class="support-action-icon purple">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                    <line x1="16" y1="13" x2="8" y2="13"/>
                                    <line x1="16" y1="17" x2="8" y2="17"/>
                                </svg>
                            </div>
                            <div class="support-action-content">
                                <h4>Bypass Audit Log</h4>
                                <p>Review all admin override actions</p>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Company Module Review -->
                <div class="admin-subsection">
                    <div class="subsection-header">
                        <h3 class="subsection-title">Company Module Access Review</h3>
                        <div class="filter-group">
                            <input type="text" class="search-input" placeholder="Search companies..." id="supportCompanySearch" onkeyup="filterSupportCompanies()">
                            <select class="filter-select" id="supportModuleFilter" onchange="filterSupportCompanies()">
                                <option value="all">All Modules</option>
                                <!-- Options will be populated by JavaScript -->
                            </select>
                        </div>
                    </div>
                    <div class="table-container">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Company</th>
                                    <th>Plan</th>
                                    <th>Status</th>
                                    <th>Modules Access</th>
                                    <th>Last Modified</th>
                                    <th>Support Actions</th>
                                </tr>
                            </thead>
                            <tbody id="supportCompaniesTableBody">
                                <!-- Support companies data will be populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Active Support Sessions -->
                <div class="admin-subsection">
                    <div class="subsection-header">
                        <h3 class="subsection-title">Active Support Sessions</h3>
                        <span class="badge-count" id="activeSessionsCount">0 active</span>
                    </div>
                    <div class="support-sessions-list" id="supportSessionsList">
                        <!-- Active support sessions will be populated by JavaScript -->
                    </div>
                </div>

                <!-- Recent Support Actions -->
                <div class="admin-subsection">
                    <div class="subsection-header">
                        <h3 class="subsection-title">Recent Support Actions</h3>
                        <select class="filter-select" id="supportActionFilter" onchange="filterSupportActions()">
                            <option value="all">All Actions</option>
                            <option value="bypass">Bypass Access</option>
                            <option value="module-grant">Module Grant</option>
                            <option value="emergency">Emergency Access</option>
                            <option value="troubleshoot">Troubleshooting</option>
                        </select>
                    </div>
                    <div class="support-actions-log" id="supportActionsLog">
                        <!-- Support actions log will be populated by JavaScript -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <!-- Plan Modal -->
    <div class="modal" id="planModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add/Edit Subscription Plan</h3>
                <button class="modal-close" onclick="closePlanModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="planForm">
                    <div class="form-group">
                        <label>Plan Name</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Price (Monthly)</label>
                            <input type="number" class="form-control" name="monthly_price" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label>Price (Yearly)</label>
                            <input type="number" class="form-control" name="yearly_price" step="0.01" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Features (comma-separated)</label>
                        <input type="text" class="form-control" name="features" placeholder="Feature 1, Feature 2, ...">
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_active"> Active Plan
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closePlanModal()">Cancel</button>
                <button class="btn-primary" onclick="savePlan()">Save Plan</button>
            </div>
        </div>
    </div>

    <!-- Company Module Access Modal -->
    <div class="modal" id="companyModuleModal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3 class="modal-title">Manage Company Module Access</h3>
                <button class="modal-close" onclick="closeCompanyModuleModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="company-info-header">
                    <h4 id="companyModuleName">Company Name</h4>
                    <p class="company-module-subtitle">Select which modules this company can access</p>
                </div>
                
                <div class="modules-selection-container">
                    <div class="modules-grid" id="modulesGrid">
                        <!-- Modules will be populated by JavaScript -->
                    </div>
                </div>

                <div class="module-actions-bar">
                    <button class="btn-sm btn-secondary" onclick="selectAllModules()">Select All</button>
                    <button class="btn-sm btn-secondary" onclick="deselectAllModules()">Deselect All</button>
                    <span class="module-count" id="moduleCount">0 modules selected</span>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeCompanyModuleModal()">Cancel</button>
                <button class="btn-primary" onclick="saveCompanyModules()">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- Emergency Access Modal -->
    <div class="modal" id="emergencyAccessModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Emergency Access Grant</h3>
                <button class="modal-close" onclick="closeEmergencyAccessModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="alert-warning">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        <path d="M12 8v4"/>
                        <path d="M12 16h.01"/>
                    </svg>
                    <div>
                        <strong>Warning:</strong> This will grant temporary full access to all modules. This action will be logged.
                    </div>
                </div>
                <form id="emergencyAccessForm">
                    <div class="form-group">
                        <label>Select Company</label>
                        <select class="form-control" id="emergencyCompanySelect" required>
                            <option value="">Choose a company...</option>
                            <!-- Options will be populated by JavaScript -->
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Duration</label>
                        <select class="form-control" id="emergencyDuration" required>
                            <option value="1">1 Hour</option>
                            <option value="4">4 Hours</option>
                            <option value="24">24 Hours</option>
                            <option value="168">7 Days</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Reason for Emergency Access</label>
                        <textarea class="form-control" id="emergencyReason" rows="3" placeholder="Describe why emergency access is needed..." required></textarea>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="emergencyNotify" checked> Notify company admin via email
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeEmergencyAccessModal()">Cancel</button>
                <button class="btn-primary btn-danger" onclick="grantEmergencyAccess()">Grant Emergency Access</button>
            </div>
        </div>
    </div>

    <!-- Company Module Review Modal -->
    <div class="modal" id="companyModuleReviewModal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3 class="modal-title">Review Company Module Access</h3>
                <button class="modal-close" onclick="closeCompanyModuleReviewModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="company-info-header">
                    <h4 id="reviewCompanyName">Company Name</h4>
                    <p class="company-module-subtitle">Review and modify module access for support purposes</p>
                </div>
                
                <div class="review-actions-bar">
                    <button class="btn-sm btn-secondary" onclick="grantAllModulesForSupport()">Grant All (Support)</button>
                    <button class="btn-sm btn-secondary" onclick="revokeAllModulesForSupport()">Revoke All</button>
                    <span class="module-count" id="reviewModuleCount">0 modules selected</span>
                </div>

                <div class="modules-selection-container">
                    <div class="modules-grid" id="reviewModulesGrid">
                        <!-- Modules will be populated by JavaScript -->
                    </div>
                </div>

                <div class="form-group" style="margin-top: 1.5rem;">
                    <label>Support Notes</label>
                    <textarea class="form-control" id="supportNotes" rows="3" placeholder="Add notes about this support action..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeCompanyModuleReviewModal()">Cancel</button>
                <button class="btn-primary" onclick="saveSupportModuleChanges()">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- Support Tickets Modal -->
    <div class="modal" id="supportTicketsModal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3 class="modal-title">Support Tickets</h3>
                <button class="modal-close" onclick="closeSupportTicketsModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="filter-group" style="margin-bottom: 1rem;">
                    <select class="filter-select" id="ticketStatusFilter" onchange="filterTickets()">
                        <option value="all">All Status</option>
                        <option value="open">Open</option>
                        <option value="in-progress">In Progress</option>
                        <option value="resolved">Resolved</option>
                        <option value="closed">Closed</option>
                    </select>
                    <input type="text" class="search-input" placeholder="Search tickets..." id="ticketSearch" onkeyup="filterTickets()">
                </div>
                <div class="support-tickets-list" id="supportTicketsList">
                    <!-- Tickets will be populated by JavaScript -->
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeSupportTicketsModal()">Close</button>
            </div>
        </div>
    </div>
@endsection
