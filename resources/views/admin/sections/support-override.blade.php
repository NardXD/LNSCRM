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
