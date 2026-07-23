@extends('layouts.app')

@section('title', 'Admin Control Panel')

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

@push('styles')
<style>
    /* Admin Sections Grid */
    .admin-sections-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 2rem;
        margin-top: 2rem;
    }

    .admin-section-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        overflow: hidden;
    }

    .section-card-header {
        padding: 1.5rem;
        border-bottom: 1px solid var(--border);
        background: var(--bg-primary);
    }

    .section-card-title {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .section-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .section-icon svg {
        width: 24px;
        height: 24px;
    }

    .section-icon.blue {
        background: #dbeafe;
        color: #2563eb;
    }

    .section-icon.green {
        background: #d1fae5;
        color: #059669;
    }

    .section-icon.purple {
        background: #ede9fe;
        color: #7c3aed;
    }

    .section-icon.orange {
        background: #fed7aa;
        color: #ea580c;
    }

    .section-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 0.25rem 0;
    }

    .section-subtitle {
        font-size: 0.875rem;
        color: var(--text-secondary);
        margin: 0;
    }

    .section-card-body {
        padding: 1.5rem;
    }

    /* Admin Subsections */
    .admin-subsection {
        margin-bottom: 2rem;
    }

    .admin-subsection:last-child {
        margin-bottom: 0;
    }

    .subsection-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
    }

    .subsection-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }

    /* Plans Grid */
    .plans-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1rem;
    }

    .plan-card {
        background: var(--bg-primary);
        border: 2px solid var(--border);
        border-radius: 8px;
        padding: 1.5rem;
        transition: all 0.15s;
    }

    .plan-card:hover {
        border-color: var(--accent);
        transform: translateY(-2px);
    }

    .plan-card.featured {
        border-color: var(--accent);
        background: var(--accent-light);
    }

    .plan-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 1rem;
    }

    .plan-name {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }

    .plan-badge {
        padding: 0.25rem 0.5rem;
        border-radius: 100px;
        font-size: 0.75rem;
        font-weight: 500;
        background: var(--accent);
        color: white;
    }

    .plan-price {
        font-size: 2rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0.5rem 0;
    }

    .plan-price span {
        font-size: 1rem;
        color: var(--text-secondary);
        font-weight: 400;
    }

    .plan-features {
        list-style: none;
        padding: 0;
        margin: 1rem 0;
    }

    .plan-features li {
        padding: 0.5rem 0;
        font-size: 0.875rem;
        color: var(--text-secondary);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .plan-features li svg {
        width: 16px;
        height: 16px;
        color: #059669;
        flex-shrink: 0;
    }

    .plan-actions {
        display: flex;
        gap: 0.5rem;
        margin-top: 1rem;
    }

    /* Admin Table */
    .table-container {
        overflow-x: auto;
        border: 1px solid var(--border);
        border-radius: 8px;
    }

    .admin-table {
        width: 100%;
        border-collapse: collapse;
    }

    .admin-table thead {
        background: var(--bg-primary);
    }

    .admin-table th {
        padding: 0.75rem 1rem;
        text-align: left;
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-secondary);
        border-bottom: 1px solid var(--border);
    }

    .admin-table td {
        padding: 1rem;
        border-bottom: 1px solid var(--border);
        font-size: 0.875rem;
        color: var(--text-primary);
    }

    .admin-table tbody tr:hover {
        background: var(--bg-primary);
    }

    .admin-table tbody tr:last-child td {
        border-bottom: none;
    }

    /* Status Badges */
    .status-badge {
        padding: 0.375rem 0.75rem;
        border-radius: 100px;
        font-size: 0.75rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
    }

    .status-badge.active {
        background: #d1fae5;
        color: #059669;
    }

    .status-badge.trial {
        background: #dbeafe;
        color: #2563eb;
    }

    .status-badge.expired {
        background: #fee2e2;
        color: #dc2626;
    }

    .status-badge.suspended {
        background: #fef3c7;
        color: #d97706;
    }

    /* Features Grid */
    .features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 1rem;
    }

    .feature-card {
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 1rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .feature-info {
        flex: 1;
    }

    .feature-name {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
        margin: 0 0 0.25rem 0;
    }

    .feature-desc {
        font-size: 0.75rem;
        color: var(--text-secondary);
        margin: 0;
    }

    /* Toggle Switch */
    .toggle-switch {
        position: relative;
        width: 44px;
        height: 24px;
    }

    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #cbd5e1;
        transition: 0.3s;
        border-radius: 24px;
    }

    .toggle-slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: 0.3s;
        border-radius: 50%;
    }

    .toggle-switch input:checked + .toggle-slider {
        background-color: var(--accent);
    }

    .toggle-switch input:checked + .toggle-slider:before {
        transform: translateX(20px);
    }

    /* Payments List */
    .payments-list {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .payment-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem;
        background: var(--bg-primary);
        border-radius: 8px;
        border: 1px solid var(--border);
    }

    .payment-info {
        flex: 1;
    }

    .payment-company {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
        margin: 0 0 0.25rem 0;
    }

    .payment-details {
        font-size: 0.75rem;
        color: var(--text-secondary);
        margin: 0;
    }

    .payment-amount {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-right: 1rem;
    }

    /* Roles List */
    .roles-list {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .role-card {
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 1rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .role-info {
        flex: 1;
    }

    .role-name {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
        margin: 0 0 0.25rem 0;
    }

    .role-users {
        font-size: 0.75rem;
        color: var(--text-secondary);
        margin: 0;
    }

    /* Access Logs */
    .access-logs {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        max-height: 400px;
        overflow-y: auto;
    }

    .log-item {
        padding: 0.75rem;
        background: var(--bg-primary);
        border-radius: 8px;
        border-left: 3px solid var(--accent);
    }

    .log-text {
        font-size: 0.875rem;
        color: var(--text-primary);
        margin: 0 0 0.25rem 0;
    }

    .log-meta {
        font-size: 0.75rem;
        color: var(--text-secondary);
        margin: 0;
    }

    /* Settings List */
    .settings-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .setting-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem;
        background: var(--bg-primary);
        border-radius: 8px;
        border: 1px solid var(--border);
    }

    .setting-info {
        flex: 1;
    }

    .setting-name {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
        margin: 0 0 0.25rem 0;
    }

    .setting-desc {
        font-size: 0.75rem;
        color: var(--text-secondary);
        margin: 0;
    }

    /* Health Metrics */
    .health-metrics {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }

    .health-metric {
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 1rem;
        text-align: center;
    }

    .health-metric-value {
        font-size: 1.5rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0.5rem 0;
    }

    .health-metric-label {
        font-size: 0.75rem;
        color: var(--text-secondary);
        margin: 0;
    }

    .health-metric.good .health-metric-value {
        color: #059669;
    }

    .health-metric.warning .health-metric-value {
        color: #d97706;
    }

    .health-metric.error .health-metric-value {
        color: #dc2626;
    }

    /* Support Actions Grid */
    .support-actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 1rem;
    }

    .support-action-card {
        background: var(--bg-primary);
        border: 2px solid var(--border);
        border-radius: 8px;
        padding: 1.5rem;
        cursor: pointer;
        transition: all 0.15s;
        text-align: left;
        display: flex;
        align-items: flex-start;
        gap: 1rem;
    }

    .support-action-card:hover {
        border-color: var(--accent);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .support-action-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .support-action-icon svg {
        width: 24px;
        height: 24px;
    }

    .support-action-icon.blue {
        background: #dbeafe;
        color: #2563eb;
    }

    .support-action-icon.red {
        background: #fee2e2;
        color: #dc2626;
    }

    .support-action-icon.orange {
        background: #fed7aa;
        color: #ea580c;
    }

    .support-action-icon.purple {
        background: #ede9fe;
        color: #7c3aed;
    }

    .support-action-content {
        flex: 1;
    }

    .support-action-content h4 {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 0.5rem 0;
    }

    .support-action-content p {
        font-size: 0.875rem;
        color: var(--text-secondary);
        margin: 0;
    }

    .support-badge {
        padding: 0.5rem 1rem;
        background: #d1fae5;
        color: #059669;
        border-radius: 100px;
        font-size: 0.875rem;
        font-weight: 500;
    }

    .badge-count {
        padding: 0.375rem 0.75rem;
        background: var(--accent-light);
        color: var(--accent);
        border-radius: 100px;
        font-size: 0.875rem;
        font-weight: 500;
    }

    /* Support Sessions */
    .support-sessions-list {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .support-session-card {
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-left: 4px solid var(--accent);
        border-radius: 8px;
        padding: 1rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .support-session-info {
        flex: 1;
    }

    .support-session-company {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 0.25rem 0;
    }

    .support-session-details {
        font-size: 0.75rem;
        color: var(--text-secondary);
        margin: 0;
    }

    .support-session-time {
        font-size: 0.75rem;
        color: var(--text-muted);
        margin-left: 1rem;
    }

    .support-session-actions {
        display: flex;
        gap: 0.5rem;
    }

    /* Support Actions Log */
    .support-actions-log {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        max-height: 400px;
        overflow-y: auto;
    }

    .support-action-log-item {
        padding: 1rem;
        background: var(--bg-primary);
        border-radius: 8px;
        border-left: 4px solid var(--accent);
        display: flex;
        align-items: flex-start;
        gap: 1rem;
    }

    .support-action-log-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .support-action-log-icon.bypass {
        background: #fee2e2;
        color: #dc2626;
    }

    .support-action-log-icon.grant {
        background: #d1fae5;
        color: #059669;
    }

    .support-action-log-icon.emergency {
        background: #fed7aa;
        color: #ea580c;
    }

    .support-action-log-content {
        flex: 1;
    }

    .support-action-log-text {
        font-size: 0.875rem;
        color: var(--text-primary);
        margin: 0 0 0.25rem 0;
    }

    .support-action-log-meta {
        font-size: 0.75rem;
        color: var(--text-secondary);
        margin: 0;
    }

    /* Alert Warning */
    .alert-warning {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        padding: 1rem;
        background: #fef3c7;
        border: 1px solid #fcd34d;
        border-radius: 8px;
        margin-bottom: 1.5rem;
    }

    .alert-warning svg {
        width: 24px;
        height: 24px;
        color: #d97706;
        flex-shrink: 0;
    }

    .alert-warning div {
        flex: 1;
        font-size: 0.875rem;
        color: #92400e;
    }

    .btn-danger {
        background: #dc2626;
        color: white;
    }

    .btn-danger:hover {
        background: #b91c1c;
    }

    .review-actions-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem;
        background: var(--bg-primary);
        border-radius: 8px;
        border: 1px solid var(--border);
        margin-bottom: 1rem;
    }

    /* Support Tickets */
    .support-tickets-list {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        max-height: 500px;
        overflow-y: auto;
    }

    .support-ticket-card {
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 1rem;
        cursor: pointer;
        transition: all 0.15s;
    }

    .support-ticket-card:hover {
        border-color: var(--accent);
        background: var(--accent-light);
    }

    .support-ticket-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.5rem;
    }

    .support-ticket-id {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-primary);
    }

    .support-ticket-status {
        padding: 0.25rem 0.5rem;
        border-radius: 100px;
        font-size: 0.75rem;
        font-weight: 500;
    }

    .support-ticket-status.open {
        background: #dbeafe;
        color: #2563eb;
    }

    .support-ticket-status.in-progress {
        background: #fef3c7;
        color: #d97706;
    }

    .support-ticket-status.resolved {
        background: #d1fae5;
        color: #059669;
    }

    .support-ticket-status.closed {
        background: #e5e7eb;
        color: #6b7280;
    }

    .support-ticket-subject {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
        margin: 0 0 0.25rem 0;
    }

    .support-ticket-meta {
        font-size: 0.75rem;
        color: var(--text-secondary);
        margin: 0;
    }

    /* Buttons */
    .btn-sm {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.15s;
        font-weight: 500;
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

    /* Form Controls */
    .form-group {
        margin-bottom: 1rem;
    }

    .form-group label {
        display: block;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }

    .form-control {
        width: 100%;
        padding: 0.625rem 0.75rem;
        border: 1px solid var(--border);
        border-radius: 6px;
        font-size: 0.875rem;
        color: var(--text-primary);
        background: var(--bg-card);
    }

    .form-control:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px var(--accent-light);
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }

    .filter-group {
        display: flex;
        gap: 0.5rem;
    }

    .filter-select {
        padding: 0.5rem 0.75rem;
        border: 1px solid var(--border);
        border-radius: 6px;
        font-size: 0.875rem;
        background: var(--bg-card);
        color: var(--text-primary);
        cursor: pointer;
    }

    .search-input {
        padding: 0.5rem 0.75rem;
        border: 1px solid var(--border);
        border-radius: 6px;
        font-size: 0.875rem;
        width: 200px;
    }

    .link-text {
        font-size: 0.875rem;
        color: var(--accent);
        text-decoration: none;
        font-weight: 500;
    }

    .link-text:hover {
        color: var(--accent-hover);
    }

    /* Modal */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        align-items: center;
        justify-content: center;
    }

    .modal.active {
        display: flex;
    }

    .modal-content {
        background: var(--bg-card);
        border-radius: 12px;
        width: 90%;
        max-width: 600px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    }

    .modal-content.modal-large {
        max-width: 900px;
    }

    .modal-header {
        padding: 1.5rem;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .modal-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        color: var(--text-secondary);
        cursor: pointer;
        padding: 0;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
    }

    .modal-close:hover {
        background: var(--bg-primary);
    }

    .modal-body {
        padding: 1.5rem;
    }

    .modal-footer {
        padding: 1.5rem;
        border-top: 1px solid var(--border);
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
    }

    /* Company Module Modal Styles */
    .company-info-header {
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--border);
    }

    .company-info-header h4 {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 0.25rem 0;
    }

    .company-module-subtitle {
        font-size: 0.875rem;
        color: var(--text-secondary);
        margin: 0;
    }

    .modules-selection-container {
        margin: 1.5rem 0;
    }

    .modules-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 1rem;
        max-height: 400px;
        overflow-y: auto;
        padding: 0.5rem;
    }

    .module-card {
        background: var(--bg-primary);
        border: 2px solid var(--border);
        border-radius: 8px;
        padding: 1rem;
        cursor: pointer;
        transition: all 0.15s;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .module-card:hover {
        border-color: var(--accent);
        background: var(--accent-light);
    }

    .module-card.selected {
        border-color: var(--accent);
        background: var(--accent-light);
    }

    .module-checkbox {
        width: 20px;
        height: 20px;
        border: 2px solid var(--border);
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        transition: all 0.15s;
    }

    .module-card.selected .module-checkbox {
        background: var(--accent);
        border-color: var(--accent);
    }

    .module-card.selected .module-checkbox::after {
        content: '✓';
        color: white;
        font-size: 14px;
        font-weight: bold;
    }

    .module-info {
        flex: 1;
        min-width: 0;
    }

    .module-name {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
        margin: 0 0 0.25rem 0;
    }

    .module-desc {
        font-size: 0.75rem;
        color: var(--text-secondary);
        margin: 0;
    }

    .module-actions-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem;
        background: var(--bg-primary);
        border-radius: 8px;
        border: 1px solid var(--border);
        margin-top: 1rem;
    }

    .module-count {
        font-size: 0.875rem;
        color: var(--text-secondary);
        font-weight: 500;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .plans-grid {
            grid-template-columns: 1fr;
        }

        .features-grid {
            grid-template-columns: 1fr;
        }

        .form-row {
            grid-template-columns: 1fr;
        }

        .table-container {
            overflow-x: scroll;
        }

        .health-metrics {
            grid-template-columns: 1fr;
        }

        .modules-grid {
            grid-template-columns: 1fr;
        }

        .module-actions-bar {
            flex-direction: column;
            gap: 0.75rem;
            align-items: stretch;
        }

        .support-actions-grid {
            grid-template-columns: 1fr;
        }

        .support-session-card {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .support-session-actions {
            width: 100%;
        }

        .review-actions-bar {
            flex-direction: column;
            gap: 0.75rem;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    // Sample Data
    const subscriptionPlans = [
        { id: 1, name: 'Basic', price: 29, period: 'month', features: ['5 Users', '10GB Storage', 'Email Support'], active: true, featured: false },
        { id: 2, name: 'Professional', price: 79, period: 'month', features: ['20 Users', '100GB Storage', 'Priority Support', 'API Access'], active: true, featured: true },
        { id: 3, name: 'Enterprise', price: 199, period: 'month', features: ['Unlimited Users', '1TB Storage', '24/7 Support', 'API Access', 'Custom Integrations'], active: true, featured: false }
    ];

    const companyBilling = [
        { id: 1, company: 'Acme Corporation', plan: 'Professional', status: 'active', cycle: 'Monthly', amount: 79, nextBilling: '2026-02-01' },
        { id: 2, company: 'TechStart Inc', plan: 'Enterprise', status: 'active', cycle: 'Yearly', amount: 2388, nextBilling: '2027-01-01' },
        { id: 3, company: 'BrandCo', plan: 'Basic', status: 'trial', cycle: 'Trial', amount: 0, nextBilling: '2026-01-20' },
        { id: 4, company: 'ShopNow', plan: 'Professional', status: 'active', cycle: 'Monthly', amount: 79, nextBilling: '2026-02-01' },
        { id: 5, company: 'CloudTech', plan: 'Basic', status: 'expired', cycle: 'Monthly', amount: 29, nextBilling: '-' }
    ];

    // Available modules list
    const availableModules = [
        { id: 'dashboard', name: 'Dashboard', description: 'Main dashboard and overview', route: 'dashboard' },
        { id: 'time-tracking', name: 'Time Tracking', description: 'Track employee time and attendance', route: 'time-tracking' },
        { id: 'user-management', name: 'User Management', description: 'Manage users and permissions', route: 'user-management' },
        { id: 'employee-monitoring', name: 'Employee Monitoring', description: 'Monitor employee activity', route: 'employee-monitoring' },
        { id: 'phone-system', name: 'Phone System', description: 'VoIP phone system integration', route: 'phone-system' },
        { id: 'payroll', name: 'Payroll', description: 'Automated payroll processing', route: 'payroll' },
        { id: 'project-management', name: 'Project Management', description: 'Project tracking and management', route: 'project-management' },
        { id: 'messaging', name: 'Messaging', description: 'Internal messaging system', route: 'messaging' },
        { id: 'billing', name: 'Billing & Payments', description: 'Invoice and payment management', route: 'billing' },
        { id: 'client-management', name: 'Client Management', description: 'CRM and client database', route: 'client-management' },
        { id: 'tickets', name: 'Tickets & Helpdesk', description: 'Support ticket system', route: 'tickets' },
        { id: 'knowledge-base', name: 'Knowledge Base', description: 'Documentation and knowledge base', route: 'knowledge-base' },
        { id: 'integrations', name: 'Integrations', description: 'Third-party integrations', route: 'integrations' },
        { id: 'quotation-builder', name: 'Quotation Builder', description: 'Create and manage quotations', route: 'quotation-builder' },
        { id: 'calendar', name: 'Calendar', description: 'Calendar and scheduling', route: 'calendar' },
        { id: 'email-tracking', name: 'Email Tracking', description: 'Track email opens and clicks', route: 'email-tracking' },
        { id: 'openai', name: 'AI Assistant', description: 'OpenAI integration', route: 'openai' }
    ];

    // Company module access (which modules each company can access)
    const companyModuleAccess = {
        1: ['dashboard', 'time-tracking', 'user-management', 'employee-monitoring', 'project-management', 'billing', 'client-management'], // Acme Corporation
        2: availableModules.map(m => m.id), // TechStart Inc - all modules
        3: ['dashboard', 'time-tracking', 'user-management', 'employee-monitoring'], // BrandCo - limited
        4: ['dashboard', 'time-tracking', 'user-management', 'project-management', 'billing', 'client-management', 'messaging'], // ShopNow
        5: ['dashboard', 'billing'] // CloudTech - expired, minimal access
    };

    let currentCompanyId = null;

    const recentPayments = [
        { id: 1, company: 'Acme Corporation', amount: 79, date: '2026-01-01', status: 'completed', method: 'Credit Card' },
        { id: 2, company: 'TechStart Inc', amount: 2388, date: '2026-01-01', status: 'completed', method: 'Bank Transfer' },
        { id: 3, company: 'ShopNow', amount: 79, date: '2025-12-28', status: 'completed', method: 'Credit Card' },
        { id: 4, company: 'BrandCo', amount: 0, date: '2025-12-25', status: 'trial', method: 'Trial' }
    ];

    const companies = [
        { id: 1, name: 'Acme Corporation' },
        { id: 2, name: 'TechStart Inc' },
        { id: 3, name: 'BrandCo' },
        { id: 4, name: 'ShopNow' },
        { id: 5, name: 'CloudTech' }
    ];

    const features = [
        { id: 1, name: 'Time Tracking', description: 'Track employee time and attendance', enabled: true },
        { id: 2, name: 'User Management', description: 'Manage users and permissions', enabled: true },
        { id: 3, name: 'Phone System', description: 'VoIP phone system integration', enabled: false },
        { id: 4, name: 'Payroll', description: 'Automated payroll processing', enabled: true },
        { id: 5, name: 'Project Management', description: 'Project tracking and management', enabled: true },
        { id: 6, name: 'Messaging', description: 'Internal messaging system', enabled: true },
        { id: 7, name: 'Billing', description: 'Invoice and payment management', enabled: true },
        { id: 8, name: 'Client Management', description: 'CRM and client database', enabled: true },
        { id: 9, name: 'Tickets', description: 'Support ticket system', enabled: false },
        { id: 10, name: 'Knowledge Base', description: 'Documentation and knowledge base', enabled: true },
        { id: 11, name: 'Integrations', description: 'Third-party integrations', enabled: false },
        { id: 12, name: 'AI Assistant', description: 'OpenAI integration', enabled: false }
    ];

    const roles = [
        { id: 1, name: 'Super Admin', users: 2, permissions: 'All' },
        { id: 2, name: 'Admin', users: 5, permissions: 'Most' },
        { id: 3, name: 'Manager', users: 12, permissions: 'Limited' },
        { id: 4, name: 'Employee', users: 45, permissions: 'Basic' }
    ];

    const accessLogs = [
        { id: 1, text: 'Company "Acme Corporation" accessed Time Tracking feature', time: '2 hours ago', type: 'feature' },
        { id: 2, text: 'User "john.doe@acme.com" logged in', time: '3 hours ago', type: 'login' },
        { id: 3, text: 'Permissions updated for "TechStart Inc"', time: '5 hours ago', type: 'permission' },
        { id: 4, text: 'Company "BrandCo" accessed Billing feature', time: '1 day ago', type: 'feature' },
        { id: 5, text: 'User "admin@techstart.com" logged in', time: '1 day ago', type: 'login' }
    ];

    const systemSettings = [
        { id: 1, name: 'Maintenance Mode', description: 'Enable maintenance mode for system updates', enabled: false },
        { id: 2, name: 'Email Notifications', description: 'Send email notifications for system events', enabled: true },
        { id: 3, name: 'Two-Factor Authentication', description: 'Require 2FA for all admin accounts', enabled: true },
        { id: 4, name: 'API Rate Limiting', description: 'Enable rate limiting for API requests', enabled: true },
        { id: 5, name: 'Auto Backup', description: 'Automatically backup database daily', enabled: true }
    ];

    const users = [
        { id: 1, name: 'John Doe', email: 'john@admin.com', role: 'Super Admin', company: 'System', status: 'active' },
        { id: 2, name: 'Jane Smith', email: 'jane@admin.com', role: 'Admin', company: 'System', status: 'active' },
        { id: 3, name: 'Bob Johnson', email: 'bob@acme.com', role: 'Manager', company: 'Acme Corporation', status: 'active' },
        { id: 4, name: 'Alice Brown', email: 'alice@techstart.com', role: 'Admin', company: 'TechStart Inc', status: 'active' }
    ];

    // Render Functions
    function renderPlans() {
        const grid = document.getElementById('plansGrid');
        grid.innerHTML = subscriptionPlans.map(plan => `
            <div class="plan-card ${plan.featured ? 'featured' : ''}">
                <div class="plan-header">
                    <h4 class="plan-name">${plan.name}</h4>
                    ${plan.featured ? '<span class="plan-badge">Popular</span>' : ''}
                </div>
                <div class="plan-price">
                    $${plan.price}<span>/${plan.period}</span>
                </div>
                <ul class="plan-features">
                    ${plan.features.map(f => `<li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>${f}</li>`).join('')}
                </ul>
                <div class="plan-actions">
                    <button class="btn-sm btn-secondary" onclick="editPlan(${plan.id})">Edit</button>
                    <button class="btn-sm btn-secondary" onclick="deletePlan(${plan.id})">Delete</button>
                </div>
            </div>
        `).join('');
    }

    function renderBillingTable() {
        const tbody = document.getElementById('billingTableBody');
        tbody.innerHTML = companyBilling.map(company => `
            <tr>
                <td><strong>${company.company}</strong></td>
                <td>${company.plan}</td>
                <td><span class="status-badge ${company.status}">${company.status.charAt(0).toUpperCase() + company.status.slice(1)}</span></td>
                <td>${company.cycle}</td>
                <td>$${company.amount.toLocaleString()}</td>
                <td>${company.nextBilling}</td>
                <td>
                    <button class="btn-sm btn-secondary" onclick="manageBilling(${company.id})">Manage</button>
                </td>
            </tr>
        `).join('');
    }

    function renderPayments() {
        const list = document.getElementById('paymentsList');
        list.innerHTML = recentPayments.map(payment => `
            <div class="payment-item">
                <div class="payment-info">
                    <div class="payment-company">${payment.company}</div>
                    <p class="payment-details">${payment.date} • ${payment.method}</p>
                </div>
                <div class="payment-amount">$${payment.amount.toLocaleString()}</div>
                <span class="status-badge ${payment.status}">${payment.status.charAt(0).toUpperCase() + payment.status.slice(1)}</span>
            </div>
        `).join('');
    }

    function renderCompanySelector() {
        const selector = document.getElementById('companySelector');
        selector.innerHTML = '<option value="">Select a company...</option>' + 
            companies.map(company => `<option value="${company.id}">${company.name}</option>`).join('');
    }

    function renderFeatures() {
        const grid = document.getElementById('featuresGrid');
        grid.innerHTML = features.map(feature => `
            <div class="feature-card">
                <div class="feature-info">
                    <div class="feature-name">${feature.name}</div>
                    <p class="feature-desc">${feature.description}</p>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" ${feature.enabled ? 'checked' : ''} onchange="toggleFeature(${feature.id}, this.checked)">
                    <span class="toggle-slider"></span>
                </label>
            </div>
        `).join('');
    }

    function renderRoles() {
        const list = document.getElementById('rolesList');
        list.innerHTML = roles.map(role => `
            <div class="role-card">
                <div class="role-info">
                    <div class="role-name">${role.name}</div>
                    <p class="role-users">${role.users} users • ${role.permissions} permissions</p>
                </div>
                <div>
                    <button class="btn-sm btn-secondary" onclick="editRole(${role.id})">Edit</button>
                    <button class="btn-sm btn-secondary" onclick="deleteRole(${role.id})">Delete</button>
                </div>
            </div>
        `).join('');
    }

    function renderAccessLogs() {
        const logs = document.getElementById('accessLogs');
        logs.innerHTML = accessLogs.map(log => `
            <div class="log-item">
                <div class="log-text">${log.text}</div>
                <p class="log-meta">${log.time}</p>
            </div>
        `).join('');
    }

    function renderSystemSettings() {
        const settings = document.getElementById('systemSettings');
        settings.innerHTML = systemSettings.map(setting => `
            <div class="setting-item">
                <div class="setting-info">
                    <div class="setting-name">${setting.name}</div>
                    <p class="setting-desc">${setting.description}</p>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" ${setting.enabled ? 'checked' : ''} onchange="toggleSetting(${setting.id}, this.checked)">
                    <span class="toggle-slider"></span>
                </label>
            </div>
        `).join('');
    }

    function renderUsers() {
        const tbody = document.getElementById('usersTableBody');
        tbody.innerHTML = users.map(user => `
            <tr>
                <td><strong>${user.name}</strong></td>
                <td>${user.email}</td>
                <td>${user.role}</td>
                <td>${user.company}</td>
                <td><span class="status-badge active">${user.status.charAt(0).toUpperCase() + user.status.slice(1)}</span></td>
                <td>
                    <button class="btn-sm btn-secondary" onclick="editUser(${user.id})">Edit</button>
                    <button class="btn-sm btn-secondary" onclick="deleteUser(${user.id})">Delete</button>
                </td>
            </tr>
        `).join('');
    }

    function renderHealthMetrics() {
        const metrics = document.getElementById('healthMetrics');
        metrics.innerHTML = `
            <div class="health-metric good">
                <div class="health-metric-value">99.9%</div>
                <p class="health-metric-label">Uptime</p>
            </div>
            <div class="health-metric good">
                <div class="health-metric-value">245ms</div>
                <p class="health-metric-label">Avg Response</p>
            </div>
            <div class="health-metric warning">
                <div class="health-metric-value">78%</div>
                <p class="health-metric-label">Storage Used</p>
            </div>
            <div class="health-metric good">
                <div class="health-metric-value">1,247</div>
                <p class="health-metric-label">Active Users</p>
            </div>
        `;
    }

    // Event Handlers
    function loadCompanyAccess() {
        const companyId = document.getElementById('companySelector').value;
        if (companyId) {
            document.getElementById('featureAccessSection').style.display = 'block';
            document.getElementById('rolePermissionsSection').style.display = 'block';
            renderFeatures();
            renderRoles();
        } else {
            document.getElementById('featureAccessSection').style.display = 'none';
            document.getElementById('rolePermissionsSection').style.display = 'none';
        }
    }

    function toggleFeature(id, enabled) {
        console.log(`Feature ${id} ${enabled ? 'enabled' : 'disabled'}`);
        // Add API call here
    }

    function toggleSetting(id, enabled) {
        console.log(`Setting ${id} ${enabled ? 'enabled' : 'disabled'}`);
        // Add API call here
    }

    function saveAccessSettings() {
        alert('Access settings saved successfully!');
        // Add API call here
    }

    function saveSystemSettings() {
        alert('System settings saved successfully!');
        // Add API call here
    }

    function openPlanModal() {
        document.getElementById('planModal').classList.add('active');
    }

    function closePlanModal() {
        document.getElementById('planModal').classList.remove('active');
    }

    function savePlan() {
        alert('Plan saved successfully!');
        closePlanModal();
        // Add API call here
    }

    function editPlan(id) {
        console.log('Edit plan:', id);
        openPlanModal();
    }

    function deletePlan(id) {
        if (confirm('Are you sure you want to delete this plan?')) {
            console.log('Delete plan:', id);
            // Add API call here
        }
    }

    function manageBilling(id) {
        currentCompanyId = id;
        const company = companyBilling.find(c => c.id === id);
        if (!company) return;

        // Set company name in modal
        document.getElementById('companyModuleName').textContent = company.company;

        // Load company's current module access
        const companyModules = companyModuleAccess[id] || [];
        
        // Render modules
        renderCompanyModules(companyModules);
        
        // Update module count
        updateModuleCount();
        
        // Open modal
        document.getElementById('companyModuleModal').classList.add('active');
    }

    function renderCompanyModules(selectedModules = []) {
        const grid = document.getElementById('modulesGrid');
        grid.innerHTML = availableModules.map(module => {
            const isSelected = selectedModules.includes(module.id);
            return `
                <div class="module-card ${isSelected ? 'selected' : ''}" data-module-id="${module.id}" onclick="toggleModule(this)">
                    <div class="module-checkbox"></div>
                    <div class="module-info">
                        <div class="module-name">${module.name}</div>
                        <p class="module-desc">${module.description}</p>
                    </div>
                </div>
            `;
        }).join('');
    }

    function toggleModule(cardElement) {
        cardElement.classList.toggle('selected');
        updateModuleCount();
    }

    function selectAllModules() {
        const cards = document.querySelectorAll('.module-card');
        cards.forEach(card => card.classList.add('selected'));
        updateModuleCount();
    }

    function deselectAllModules() {
        const cards = document.querySelectorAll('.module-card');
        cards.forEach(card => card.classList.remove('selected'));
        updateModuleCount();
    }

    function updateModuleCount() {
        const selectedCount = document.querySelectorAll('.module-card.selected').length;
        const totalCount = availableModules.length;
        document.getElementById('moduleCount').textContent = `${selectedCount} of ${totalCount} modules selected`;
    }

    function saveCompanyModules() {
        if (!currentCompanyId) return;

        const selectedCards = document.querySelectorAll('.module-card.selected');
        const selectedModules = Array.from(selectedCards).map(card => {
            return card.getAttribute('data-module-id');
        }).filter(Boolean);

        // Update company module access
        companyModuleAccess[currentCompanyId] = selectedModules;

        const company = companyBilling.find(c => c.id === currentCompanyId);
        const moduleNames = selectedModules.map(id => {
            const module = availableModules.find(m => m.id === id);
            return module ? module.name : id;
        }).join(', ');

        // Show success message
        alert(`Module access updated successfully for ${company?.company}!\n\nSelected modules: ${selectedModules.length}\n\nModules: ${moduleNames}`);

        // Close modal
        closeCompanyModuleModal();

        // Here you would typically make an API call to save the changes
        // Example: 
        // fetch('/api/companies/' + currentCompanyId + '/modules', {
        //     method: 'POST',
        //     headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        //     body: JSON.stringify({ modules: selectedModules })
        // })
    }

    function closeCompanyModuleModal() {
        document.getElementById('companyModuleModal').classList.remove('active');
        currentCompanyId = null;
    }

    function openRoleModal() {
        alert('Role creation modal would open here');
    }

    function editRole(id) {
        console.log('Edit role:', id);
    }

    function deleteRole(id) {
        if (confirm('Are you sure you want to delete this role?')) {
            console.log('Delete role:', id);
        }
    }

    function openUserModal() {
        alert('User creation modal would open here');
    }

    function editUser(id) {
        console.log('Edit user:', id);
    }

    function deleteUser(id) {
        if (confirm('Are you sure you want to delete this user?')) {
            console.log('Delete user:', id);
        }
    }

    function refreshSystemHealth() {
        renderHealthMetrics();
        alert('System health refreshed!');
    }

    // Support & Override Functions
    const supportTickets = [
        { id: 1, company: 'Acme Corporation', subject: 'Cannot access Time Tracking module', status: 'open', priority: 'high', created: '2 hours ago', type: 'module-access' },
        { id: 2, company: 'BrandCo', subject: 'Billing module showing error', status: 'in-progress', priority: 'medium', created: '5 hours ago', type: 'technical' },
        { id: 3, company: 'ShopNow', subject: 'Need temporary access to Project Management', status: 'open', priority: 'low', created: '1 day ago', type: 'access-request' },
        { id: 4, company: 'CloudTech', subject: 'User Management permissions issue', status: 'resolved', priority: 'high', created: '2 days ago', type: 'permissions' }
    ];

    const supportActionsLog = [
        { id: 1, type: 'emergency', company: 'Acme Corporation', action: 'Granted emergency full access', admin: 'John Doe', time: '2 hours ago', duration: '24 hours' },
        { id: 2, type: 'grant', company: 'BrandCo', action: 'Granted access to Phone System module', admin: 'Jane Smith', time: '5 hours ago', notes: 'Support request for testing' },
        { id: 3, type: 'bypass', company: 'ShopNow', action: 'Bypassed module restrictions for troubleshooting', admin: 'John Doe', time: '1 day ago', notes: 'Troubleshooting billing issue' },
        { id: 4, type: 'grant', company: 'CloudTech', action: 'Granted access to AI Assistant module', admin: 'Jane Smith', time: '2 days ago', notes: 'Trial period extension' }
    ];

    const activeSupportSessions = [
        { id: 1, company: 'Acme Corporation', type: 'Emergency Access', started: '2 hours ago', expires: '22 hours remaining', admin: 'John Doe' },
        { id: 2, company: 'BrandCo', type: 'Module Grant', started: '5 hours ago', expires: '19 hours remaining', admin: 'Jane Smith' }
    ];

    let currentReviewCompanyId = null;

    function openCompanyModuleReview() {
        document.getElementById('companyModuleReviewModal').classList.add('active');
        // Populate company selector if needed
    }

    function closeCompanyModuleReviewModal() {
        document.getElementById('companyModuleReviewModal').classList.remove('active');
        currentReviewCompanyId = null;
    }

    function openEmergencyAccess() {
        const select = document.getElementById('emergencyCompanySelect');
        select.innerHTML = '<option value="">Choose a company...</option>' + 
            companyBilling.map(company => `<option value="${company.id}">${company.company}</option>`).join('');
        document.getElementById('emergencyAccessModal').classList.add('active');
    }

    function closeEmergencyAccessModal() {
        document.getElementById('emergencyAccessModal').classList.remove('active');
        document.getElementById('emergencyAccessForm').reset();
    }

    function grantEmergencyAccess() {
        const companyId = document.getElementById('emergencyCompanySelect').value;
        const duration = document.getElementById('emergencyDuration').value;
        const reason = document.getElementById('emergencyReason').value;
        const notify = document.getElementById('emergencyNotify').checked;

        if (!companyId || !reason) {
            alert('Please fill in all required fields');
            return;
        }

        const company = companyBilling.find(c => c.id === parseInt(companyId));
        const durationText = duration === '1' ? '1 Hour' : duration === '4' ? '4 Hours' : duration === '24' ? '24 Hours' : '7 Days';

        // Log the action
        supportActionsLog.unshift({
            id: supportActionsLog.length + 1,
            type: 'emergency',
            company: company.company,
            action: `Granted emergency full access for ${durationText}`,
            admin: 'Current Admin',
            time: 'Just now',
            duration: durationText,
            reason: reason
        });

        // Add to active sessions
        activeSupportSessions.push({
            id: activeSupportSessions.length + 1,
            company: company.company,
            type: 'Emergency Access',
            started: 'Just now',
            expires: `${durationText} remaining`,
            admin: 'Current Admin'
        });

        alert(`Emergency access granted to ${company.company} for ${durationText}.\nReason: ${reason}\n${notify ? 'Company admin has been notified.' : ''}`);
        
        closeEmergencyAccessModal();
        renderSupportData();
    }

    function openSupportTickets() {
        renderSupportTickets();
        document.getElementById('supportTicketsModal').classList.add('active');
    }

    function closeSupportTicketsModal() {
        document.getElementById('supportTicketsModal').classList.remove('active');
    }

    function openBypassLog() {
        renderSupportActionsLog();
        // Could open in a modal or scroll to the log section
        document.getElementById('supportActionsLog').scrollIntoView({ behavior: 'smooth' });
    }

    function grantAllModulesForSupport() {
        const cards = document.querySelectorAll('#reviewModulesGrid .module-card');
        cards.forEach(card => card.classList.add('selected'));
        updateReviewModuleCount();
    }

    function revokeAllModulesForSupport() {
        const cards = document.querySelectorAll('#reviewModulesGrid .module-card');
        cards.forEach(card => card.classList.remove('selected'));
        updateReviewModuleCount();
    }

    function updateReviewModuleCount() {
        const selectedCount = document.querySelectorAll('#reviewModulesGrid .module-card.selected').length;
        const totalCount = availableModules.length;
        document.getElementById('reviewModuleCount').textContent = `${selectedCount} of ${totalCount} modules selected`;
    }

    function saveSupportModuleChanges() {
        if (!currentReviewCompanyId) {
            alert('Please select a company first');
            return;
        }

        const selectedCards = document.querySelectorAll('#reviewModulesGrid .module-card.selected');
        const selectedModules = Array.from(selectedCards).map(card => {
            return card.getAttribute('data-module-id');
        }).filter(Boolean);

        const notes = document.getElementById('supportNotes').value;

        // Update company module access
        companyModuleAccess[currentReviewCompanyId] = selectedModules;

        // Log the action
        const company = companyBilling.find(c => c.id === currentReviewCompanyId);
        supportActionsLog.unshift({
            id: supportActionsLog.length + 1,
            type: 'grant',
            company: company.company,
            action: `Modified module access (${selectedModules.length} modules)`,
            admin: 'Current Admin',
            time: 'Just now',
            notes: notes || 'No notes provided'
        });

        alert(`Module access updated for ${company.company}!\n\nSelected modules: ${selectedModules.length}\n\nThis action has been logged.`);
        
        closeCompanyModuleReviewModal();
        renderSupportData();
    }

    function renderSupportCompanies() {
        const tbody = document.getElementById('supportCompaniesTableBody');
        tbody.innerHTML = companyBilling.map(company => {
            const modules = companyModuleAccess[company.id] || [];
            const moduleNames = modules.map(id => {
                const module = availableModules.find(m => m.id === id);
                return module ? module.name : id;
            }).slice(0, 3).join(', ');
            const moreCount = modules.length > 3 ? ` +${modules.length - 3} more` : '';
            
            return `
                <tr>
                    <td><strong>${company.company}</strong></td>
                    <td>${company.plan}</td>
                    <td><span class="status-badge ${company.status}">${company.status.charAt(0).toUpperCase() + company.status.slice(1)}</span></td>
                    <td>${moduleNames}${moreCount}</td>
                    <td>2 days ago</td>
                    <td>
                        <button class="btn-sm btn-secondary" onclick="reviewCompanyModules(${company.id})">Review</button>
                        <button class="btn-sm btn-secondary" onclick="manageBilling(${company.id})">Manage</button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function reviewCompanyModules(companyId) {
        currentReviewCompanyId = companyId;
        const company = companyBilling.find(c => c.id === companyId);
        document.getElementById('reviewCompanyName').textContent = company.company;
        
        const companyModules = companyModuleAccess[companyId] || [];
        renderReviewModules(companyModules);
        updateReviewModuleCount();
        
        document.getElementById('companyModuleReviewModal').classList.add('active');
    }

    function renderReviewModules(selectedModules = []) {
        const grid = document.getElementById('reviewModulesGrid');
        grid.innerHTML = availableModules.map(module => {
            const isSelected = selectedModules.includes(module.id);
            return `
                <div class="module-card ${isSelected ? 'selected' : ''}" data-module-id="${module.id}" onclick="toggleModule(this)">
                    <div class="module-checkbox"></div>
                    <div class="module-info">
                        <div class="module-name">${module.name}</div>
                        <p class="module-desc">${module.description}</p>
                    </div>
                </div>
            `;
        }).join('');
    }

    function renderSupportSessions() {
        const list = document.getElementById('supportSessionsList');
        document.getElementById('activeSessionsCount').textContent = `${activeSupportSessions.length} active`;
        
        if (activeSupportSessions.length === 0) {
            list.innerHTML = '<p style="text-align: center; color: var(--text-secondary); padding: 2rem;">No active support sessions</p>';
            return;
        }
        
        list.innerHTML = activeSupportSessions.map(session => `
            <div class="support-session-card">
                <div class="support-session-info">
                    <div class="support-session-company">${session.company}</div>
                    <p class="support-session-details">${session.type} • Started ${session.started} • Expires in ${session.expires}</p>
                </div>
                <div class="support-session-time">By ${session.admin}</div>
                <div class="support-session-actions">
                    <button class="btn-sm btn-secondary" onclick="endSupportSession(${session.id})">End Session</button>
                </div>
            </div>
        `).join('');
    }

    function endSupportSession(sessionId) {
        if (confirm('Are you sure you want to end this support session?')) {
            const index = activeSupportSessions.findIndex(s => s.id === sessionId);
            if (index > -1) {
                activeSupportSessions.splice(index, 1);
                renderSupportSessions();
            }
        }
    }

    function renderSupportActionsLog() {
        const log = document.getElementById('supportActionsLog');
        log.innerHTML = supportActionsLog.map(action => {
            const iconClass = action.type === 'emergency' ? 'emergency' : action.type === 'grant' ? 'grant' : 'bypass';
            const iconSvg = action.type === 'emergency' 
                ? '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M12 8v4"/><path d="M12 16h.01"/>'
                : action.type === 'grant'
                ? '<polyline points="20 6 9 17 4 12"/>'
                : '<path d="M18 6L6 18M6 6l12 12"/>';
            
            return `
                <div class="support-action-log-item">
                    <div class="support-action-log-icon ${iconClass}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            ${iconSvg}
                        </svg>
                    </div>
                    <div class="support-action-log-content">
                        <div class="support-action-log-text">
                            <strong>${action.company}</strong>: ${action.action}
                        </div>
                        <p class="support-action-log-meta">
                            ${action.admin} • ${action.time}${action.notes ? ` • ${action.notes}` : ''}
                        </p>
                    </div>
                </div>
            `;
        }).join('');
    }

    function renderSupportTickets() {
        const list = document.getElementById('supportTicketsList');
        list.innerHTML = supportTickets.map(ticket => `
            <div class="support-ticket-card" onclick="viewSupportTicket(${ticket.id})">
                <div class="support-ticket-header">
                    <span class="support-ticket-id">#TKT-${ticket.id.toString().padStart(4, '0')}</span>
                    <span class="support-ticket-status ${ticket.status}">${ticket.status.replace('-', ' ').split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ')}</span>
                </div>
                <div class="support-ticket-subject">${ticket.subject}</div>
                <p class="support-ticket-meta">${ticket.company} • ${ticket.created} • Priority: ${ticket.priority}</p>
            </div>
        `).join('');
    }

    function viewSupportTicket(ticketId) {
        const ticket = supportTickets.find(t => t.id === ticketId);
        if (ticket) {
            alert(`Support Ticket #TKT-${ticket.id.toString().padStart(4, '0')}\n\nCompany: ${ticket.company}\nSubject: ${ticket.subject}\nStatus: ${ticket.status}\nPriority: ${ticket.priority}\nCreated: ${ticket.created}`);
        }
    }

    function filterSupportCompanies() {
        const search = document.getElementById('supportCompanySearch').value.toLowerCase();
        const moduleFilter = document.getElementById('supportModuleFilter').value;
        
        // Filter logic would go here
        renderSupportCompanies();
    }

    function filterSupportActions() {
        const filter = document.getElementById('supportActionFilter').value;
        // Filter logic would go here
        renderSupportActionsLog();
    }

    function filterTickets() {
        const statusFilter = document.getElementById('ticketStatusFilter').value;
        const search = document.getElementById('ticketSearch').value.toLowerCase();
        // Filter logic would go here
        renderSupportTickets();
    }

    function renderSupportData() {
        renderSupportCompanies();
        renderSupportSessions();
        renderSupportActionsLog();
        
        // Populate module filter
        const moduleFilter = document.getElementById('supportModuleFilter');
        moduleFilter.innerHTML = '<option value="all">All Modules</option>' + 
            availableModules.map(m => `<option value="${m.id}">${m.name}</option>`).join('');
    }

    // Filter handlers
    document.getElementById('billingFilter')?.addEventListener('change', function() {
        console.log('Filter billing by:', this.value);
        renderBillingTable();
    });

    document.getElementById('logFilter')?.addEventListener('change', function() {
        console.log('Filter logs by:', this.value);
        renderAccessLogs();
    });

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        renderPlans();
        renderBillingTable();
        renderPayments();
        renderCompanySelector();
        renderRoles();
        renderAccessLogs();
        renderSystemSettings();
        renderUsers();
        renderHealthMetrics();
        renderSupportData();
    });
</script>
@endpush

