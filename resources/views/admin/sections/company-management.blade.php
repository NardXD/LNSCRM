<!-- Company Management Section -->
<div class="admin-section-card" id="companies">
    <div class="section-card-header">
        <div class="section-card-title">
            <div class="section-icon blue">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
            </div>
            <div>
                <h2 class="section-title">Companies</h2>
                <p class="section-subtitle">Create new companies and manage existing ones</p>
            </div>
        </div>
        <button type="button" class="btn-sm btn-primary" onclick="openCreateCompanyModal()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/>
                <line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Create Company
        </button>
    </div>

    <div class="section-card-body">
        <div class="admin-subsection">
            <div class="subsection-header">
                <h3 class="subsection-title">All Companies</h3>
                <div class="filter-group">
                    <select class="filter-select" id="companyStatusFilter" onchange="filterCompanies()">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="trial">Trial</option>
                        <option value="suspended">Suspended</option>
                    </select>
                    <div class="search-box">
                        <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                        <input type="text" class="search-input" placeholder="Search companies..." id="companySearch" oninput="filterCompanies()">
                    </div>
                </div>
            </div>
            <div class="table-container companies-table-wrap">
                @if($companies->isEmpty())
                <p class="empty-state" id="companiesEmptyState">No companies yet. Click "Create Company" to add one.</p>
                @else
                <table class="admin-table companies-table" id="companiesTable">
                    <colgroup>
                        <col class="col-company">
                        <col class="col-subdomain">
                        <col class="col-email">
                        <col class="col-status">
                        <col class="col-plan">
                        <col class="col-users">
                        <col class="col-created">
                        <col class="col-actions">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Company</th>
                            <th>Subdomain</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Plan</th>
                            <th>Users</th>
                            <th>Created</th>
                            <th class="col-actions-header"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody id="companiesTableBody">
                        @foreach($companies as $company)
                        <tr data-id="{{ $company->id }}" data-status="{{ $company->status }}">
                            <td class="cell-company">
                                <span class="company-name" title="{{ $company->name }}">{{ $company->name }}</span>
                            </td>
                            <td class="cell-subdomain">
                                <span class="subdomain-badge">{{ $company->subdomain }}</span>
                            </td>
                            <td class="cell-email">
                                <span class="cell-truncate" title="{{ $company->email }}">{{ $company->email }}</span>
                            </td>
                            <td class="cell-status">
                                <select class="status-select status-{{ $company->status }}" data-company-id="{{ $company->id }}" onchange="updateCompanyStatus({{ $company->id }}, this.value); this.className='status-select status-'+this.value;">
                                    <option value="trial" {{ $company->status === 'trial' ? 'selected' : '' }}>Trial</option>
                                    <option value="active" {{ $company->status === 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="suspended" {{ $company->status === 'suspended' ? 'selected' : '' }}>Suspended</option>
                                </select>
                            </td>
                            <td class="cell-plan">{{ $company->activeSubscription?->plan?->name ?? '—' }}</td>
                            <td class="cell-users">{{ $company->users_count }}</td>
                            <td class="cell-created">{{ $company->created_at->format('M j, Y') }}</td>
                            <td class="cell-actions">
                                <details class="row-actions-menu">
                                    <summary class="row-actions-trigger" aria-label="Actions for {{ $company->name }}">
                                        <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16" aria-hidden="true">
                                            <circle cx="12" cy="5" r="2"/>
                                            <circle cx="12" cy="12" r="2"/>
                                            <circle cx="12" cy="19" r="2"/>
                                        </svg>
                                    </summary>
                                    <div class="row-actions-dropdown">
                                        <form action="{{ route('admin.company-management.login-as-admin', $company) }}" method="POST" onsubmit="return confirm('Log in as the admin for {{ addslashes($company->name) }}?');">
                                            @csrf
                                            <button type="submit" class="row-action-item row-action-primary">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                                                Login as Admin
                                            </button>
                                        </form>
                                        <button type="button" class="row-action-item" data-row-action="manage-permissions" data-company-id="{{ $company->id }}" data-company-name="{{ $company->name }}">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                            Manage Permissions
                                        </button>
                                        <button type="button" class="row-action-item" data-row-action="view-history" data-company-id="{{ $company->id }}" data-company-name="{{ $company->name }}">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                            View History
                                        </button>
                                    </div>
                                </details>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Create Company Modal -->
<div id="createCompanyModal" class="modal">
    <div class="modal-content" style="max-width: 560px;">
        <div class="modal-header">
            <h3 class="modal-title">Create New Company</h3>
            <button type="button" class="modal-close" onclick="closeCreateCompanyModal()">&times;</button>
        </div>
        <form id="createCompanyForm" action="{{ route('admin.company-management.store') }}" method="POST">
            @csrf
            <div class="modal-body">
                <div class="form-group">
                    <label for="create_company">Company Name *</label>
                    <input type="text" id="create_company" name="company" class="form-control" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="create_first_name">First Name *</label>
                        <input type="text" id="create_first_name" name="first_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="create_last_name">Last Name *</label>
                        <input type="text" id="create_last_name" name="last_name" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="create_email">Admin Email *</label>
                    <input type="email" id="create_email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="create_password">Password *</label>
                    <input type="password" id="create_password" name="password" class="form-control" required minlength="8">
                </div>
                <div class="form-group">
                    <label for="create_password_confirmation">Confirm Password *</label>
                    <input type="password" id="create_password_confirmation" name="password_confirmation" class="form-control" required minlength="8">
                </div>
                <div class="form-group">
                    <label for="create_plan">Plan</label>
                    <select id="create_plan" name="plan" class="form-control">
                        <option value="free">Free</option>
                        <option value="gold">Gold</option>
                        <option value="platinum">Platinum</option>
                    </select>
                </div>
                @if(isset($modules) && $modules->isNotEmpty())
                <div class="form-group">
                    <label>Modules Access</label>
                    <p class="section-subtitle" style="margin-bottom: 0.5rem; font-size: 0.875rem;">Select which modules this company can access.</p>
                    <div class="modules-checkbox-grid">
                        <label class="module-checkbox-item check-all-item">
                            <input type="checkbox" id="checkAllCreateModules" onchange="toggleAllCreateModules(this.checked)">
                            <span><strong>Check all</strong></span>
                        </label>
                        @foreach($modules as $module)
                        <label class="module-checkbox-item">
                            <input type="checkbox" name="modules[]" value="{{ $module->slug }}" {{ in_array($module->slug, ['leave-management', 'team-management']) ? 'checked' : '' }}>
                            <span>{{ $module->name }}{{ in_array($module->slug, ['leave-management', 'team-management']) ? ' (included)' : '' }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-sm btn-secondary" onclick="closeCreateCompanyModal()">Cancel</button>
                <button type="submit" class="btn-sm btn-primary">Create Company</button>
            </div>
        </form>
    </div>
</div>

<!-- Company History Modal -->
<div id="historyModal" class="modal">
    <div class="modal-content" style="max-width: 560px;">
        <div class="modal-header">
            <h3 class="modal-title">History – <span id="historyCompanyName"></span></h3>
            <button type="button" class="modal-close" onclick="closeHistoryModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="historyList" class="history-timeline">
                <span class="loading-text">Loading history…</span>
            </div>
        </div>
    </div>
</div>

<!-- Manage Permission Modal -->
<div id="managePermissionModal" class="modal">
    <div class="modal-content" style="max-width: 560px;">
        <div class="modal-header">
            <h3 class="modal-title">Manage Modules – <span id="managePermissionCompanyName"></span></h3>
            <button type="button" class="modal-close" onclick="closeManagePermissionModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p class="section-subtitle" style="margin-bottom: 1rem;">Select which modules this company can access.</p>
            <div id="managePermissionModulesGrid" class="modules-checkbox-grid">
                <span class="loading-text">Loading modules…</span>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-sm btn-secondary" onclick="closeManagePermissionModal()">Cancel</button>
            <button type="button" class="btn-sm btn-primary" id="saveManagePermissionBtn" onclick="saveManagePermission()">Save Changes</button>
        </div>
    </div>
</div>
