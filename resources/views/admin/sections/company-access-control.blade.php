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

