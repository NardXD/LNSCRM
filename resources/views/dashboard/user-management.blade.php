@extends('layouts.app')

@section('title', 'User & Access Management')

@section('content')
    <div class="page-header">
        <h1 class="page-title">User & Access Management</h1>
        <p class="page-subtitle">Manage users, roles, permissions, and company settings</p>
    </div>

    <div class="management-container">
        <!-- Tabs Navigation -->
        <div class="management-tabs">
            @if(auth()->user()?->hasPermission('view_user_roles_permissions'))
                <button class="tab-btn active" data-tab="roles">Roles & Permissions</button>
            @endif
            @if(auth()->user()?->hasPermission('view_user_company_setup'))
                <button class="tab-btn {{ !auth()->user()?->hasPermission('view_user_roles_permissions') ? 'active' : '' }}" data-tab="company">Company Setup</button>
            @endif
            @if(auth()->user()?->hasPermission('view_user_employee_profile'))
                <button class="tab-btn {{ !auth()->user()?->hasPermission('view_user_roles_permissions') && !auth()->user()?->hasPermission('view_user_company_setup') ? 'active' : '' }}" data-tab="employees">Employee Profile</button>
                <button class="tab-btn" data-tab="salesReps">Sales Reps</button>
            @endif
            @if(auth()->user()?->hasPermission('view_user_departments'))
                <button class="tab-btn" data-tab="departments">Departments</button>
            @endif
            @if(auth()->user()?->hasPermission('view_user_role_based_access'))
                <button class="tab-btn {{ !auth()->user()?->hasPermission('view_user_roles_permissions') && !auth()->user()?->hasPermission('view_user_company_setup') && !auth()->user()?->hasPermission('view_user_employee_profile') ? 'active' : '' }}" data-tab="rbac">Role Based Access</button>
            @endif
        </div>

        <!-- Roles & Permissions Tab -->
        <div class="tab-content {{ auth()->user()?->hasPermission('view_user_roles_permissions') ? 'active' : '' }}" id="rolesTab">
            <div class="section-header">
                <h2 class="section-title">Roles & Permissions</h2>
                <button class="btn-primary" onclick="openRoleModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Add New Role
                </button>
            </div>

            <div class="roles-grid" id="rolesGrid">
                @forelse($roles ?? [] as $role)
                    @php
                        $badgeClass = match(strtolower($role->slug)) {
                            'administrator', 'admin' => 'admin',
                            'manager' => 'manager',
                            'employee' => 'employee',
                            default => 'employee'
                        };
                        $permissions = $role->permissions ?? collect();
                        $previewCount = min(3, $permissions->count());
                        $remaining = $permissions->count() - $previewCount;
                    @endphp
                    <div class="role-card" data-role-id="{{ $role->id }}">
                    <div class="role-header">
                        <div class="role-info">
                                <h3 class="role-name">{{ $role->name }}</h3>
                                <span class="role-badge {{ $badgeClass }}">{{ $role->name }}</span>
                        </div>
                        <div class="role-actions">
                                <button class="icon-btn" title="Edit" onclick="editRole({{ $role->id }})">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                            </button>
                                <button class="icon-btn" title="Delete" onclick="deleteRole({{ $role->id }})">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="3 6 5 6 21 6"/>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                        <p class="role-description">{{ $role->description ?: 'No description provided' }}</p>
                    <div class="role-stats">
                            <span class="stat-item">{{ $role->users_with_role_count ?? $role->users_count ?? 0 }} {{ Str::plural('User', $role->users_with_role_count ?? $role->users_count ?? 0) }}</span>
                            <span class="stat-item">{{ $permissions->count() }} {{ Str::plural('Permission', $permissions->count()) }}</span>
                    </div>
                        @if($permissions->count() > 0)
                    <div class="permissions-preview">
                                @foreach($permissions->take(3) as $perm)
                                    <span class="permission-tag">{{ $perm->display_name ?: $perm->name }}</span>
                                @endforeach
                                @if($remaining > 0)
                                    <span class="permission-tag">+{{ $remaining }} more</span>
                                @endif
                    </div>
                        @endif
                </div>
                @empty
                    <div class="empty-state">
                        <p>No roles found. Create your first role to get started.</p>
                        </div>
                @endforelse
            </div>
        </div>

        <!-- Company Setup Tab -->
        <div class="tab-content {{ !auth()->user()?->hasPermission('view_user_roles_permissions') && auth()->user()?->hasPermission('view_user_company_setup') ? 'active' : '' }}" id="companyTab">
            <div class="section-header">
                <h2 class="section-title">Company Setup</h2>
            </div>

            <div class="form-container">
                <div class="form-section">
                    <h3 class="form-section-title">Company Information</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="companyName" class="form-label">Company Name *</label>
                            <input type="text" id="companyName" class="form-input" value="{{ isset($company) && $company ? $company->name : '' }}" required>
                        </div>
                        <div class="form-group">
                            <label for="companyEmail" class="form-label">Company Email *</label>
                            <input type="email" id="companyEmail" class="form-input" value="{{ isset($company) && $company ? $company->email : '' }}" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="companyPhone" class="form-label">Phone Number</label>
                            <input type="tel" id="companyPhone" class="form-input" value="{{ isset($company) && $company ? $company->phone : '' }}">
                        </div>
                        <div class="form-group">
                            <label for="companyWebsite" class="form-label">Website</label>
                            <input type="url" id="companyWebsite" class="form-input" value="{{ isset($company) && $company ? $company->website : '' }}">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="companyAddress" class="form-label">Address</label>
                        <textarea id="companyAddress" class="form-textarea" rows="3">{{ isset($company) && $company ? $company->address : '' }}</textarea>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="form-section-title">Company Settings</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="timezone" class="form-label">Timezone</label>
                            <select id="timezone" class="form-input">
                                @php
                                    // Ensure default is America/New_York if not set, empty, or null
                                    $timezoneValue = $companySettings['timezone'] ?? null;
                                    $currentTimezone = (!empty($timezoneValue) && trim($timezoneValue) !== '') 
                                        ? trim($timezoneValue) 
                                        : 'America/New_York';
                                    $timezones = [
                                        // North America - US & Canada
                                        'America/New_York' => 'Eastern Time (ET) - US & Canada',
                                        'America/Chicago' => 'Central Time (CT) - US & Canada',
                                        'America/Denver' => 'Mountain Time (MT) - US & Canada',
                                        'America/Phoenix' => 'Mountain Time (MST) - Arizona',
                                        'America/Los_Angeles' => 'Pacific Time (PT) - US & Canada',
                                        'America/Anchorage' => 'Alaska Time (AKT)',
                                        'Pacific/Honolulu' => 'Hawaii Time (HST)',
                                        
                                        // North America - Mexico
                                        'America/Mexico_City' => 'Central Time - Mexico City',
                                        'America/Cancun' => 'Eastern Time - Cancun',
                                        'America/Tijuana' => 'Pacific Time - Tijuana',
                                        
                                        // North America - Canada
                                        'America/Toronto' => 'Eastern Time - Toronto',
                                        'America/Vancouver' => 'Pacific Time - Vancouver',
                                        'America/Winnipeg' => 'Central Time - Winnipeg',
                                        'America/Halifax' => 'Atlantic Time - Halifax',
                                        'America/St_Johns' => 'Newfoundland Time - St. John\'s',
                                        
                                        // Central & South America
                                        'America/Bogota' => 'Colombia Time (COT) - Bogota',
                                        'America/Lima' => 'Peru Time (PET) - Lima',
                                        'America/Caracas' => 'Venezuela Time (VET) - Caracas',
                                        'America/Santiago' => 'Chile Time (CLT) - Santiago',
                                        'America/Buenos_Aires' => 'Argentina Time (ART) - Buenos Aires',
                                        'America/Sao_Paulo' => 'Brasilia Time (BRT) - São Paulo',
                                        'America/Manaus' => 'Amazon Time (AMT) - Manaus',
                                        
                                        // Europe
                                        'Europe/London' => 'Greenwich Mean Time (GMT) - London',
                                        'Europe/Dublin' => 'Greenwich Mean Time (GMT) - Dublin',
                                        'Europe/Paris' => 'Central European Time (CET) - Paris',
                                        'Europe/Berlin' => 'Central European Time (CET) - Berlin',
                                        'Europe/Rome' => 'Central European Time (CET) - Rome',
                                        'Europe/Madrid' => 'Central European Time (CET) - Madrid',
                                        'Europe/Amsterdam' => 'Central European Time (CET) - Amsterdam',
                                        'Europe/Brussels' => 'Central European Time (CET) - Brussels',
                                        'Europe/Vienna' => 'Central European Time (CET) - Vienna',
                                        'Europe/Stockholm' => 'Central European Time (CET) - Stockholm',
                                        'Europe/Warsaw' => 'Central European Time (CET) - Warsaw',
                                        'Europe/Prague' => 'Central European Time (CET) - Prague',
                                        'Europe/Budapest' => 'Central European Time (CET) - Budapest',
                                        'Europe/Athens' => 'Eastern European Time (EET) - Athens',
                                        'Europe/Helsinki' => 'Eastern European Time (EET) - Helsinki',
                                        'Europe/Istanbul' => 'Turkey Time (TRT) - Istanbul',
                                        'Europe/Moscow' => 'Moscow Time (MSK)',
                                        'Europe/Kiev' => 'Eastern European Time (EET) - Kiev',
                                        
                                        // Middle East & Africa
                                        'Asia/Dubai' => 'Gulf Standard Time (GST) - Dubai',
                                        'Asia/Riyadh' => 'Arabia Standard Time (AST) - Riyadh',
                                        'Asia/Jerusalem' => 'Israel Time (IST) - Jerusalem',
                                        'Asia/Tehran' => 'Iran Time (IRST) - Tehran',
                                        'Africa/Cairo' => 'Eastern European Time (EET) - Cairo',
                                        'Africa/Johannesburg' => 'South Africa Time (SAST) - Johannesburg',
                                        'Africa/Lagos' => 'West Africa Time (WAT) - Lagos',
                                        'Africa/Nairobi' => 'East Africa Time (EAT) - Nairobi',
                                        
                                        // Asia - South Asia
                                        'Asia/Kolkata' => 'India Standard Time (IST) - Mumbai, New Delhi',
                                        'Asia/Karachi' => 'Pakistan Time (PKT) - Karachi',
                                        'Asia/Dhaka' => 'Bangladesh Time (BDT) - Dhaka',
                                        'Asia/Colombo' => 'Sri Lanka Time (SLST) - Colombo',
                                        
                                        // Asia - Southeast Asia
                                        'Asia/Bangkok' => 'Indochina Time (ICT) - Bangkok',
                                        'Asia/Jakarta' => 'Western Indonesia Time (WIB) - Jakarta',
                                        'Asia/Singapore' => 'Singapore Time (SGT)',
                                        'Asia/Manila' => 'Philippine Time (PHT) - Manila',
                                        'Asia/Kuala_Lumpur' => 'Malaysia Time (MYT) - Kuala Lumpur',
                                        'Asia/Ho_Chi_Minh' => 'Indochina Time (ICT) - Ho Chi Minh',
                                        
                                        // Asia - East Asia
                                        'Asia/Shanghai' => 'China Standard Time (CST) - Shanghai, Beijing',
                                        'Asia/Hong_Kong' => 'Hong Kong Time (HKT)',
                                        'Asia/Taipei' => 'Taiwan Time (TST) - Taipei',
                                        'Asia/Tokyo' => 'Japan Standard Time (JST) - Tokyo',
                                        'Asia/Seoul' => 'Korea Standard Time (KST) - Seoul',
                                        
                                        // Asia - Other
                                        'Asia/Ulaanbaatar' => 'Ulaanbaatar Time (ULAT)',
                                        'Asia/Vladivostok' => 'Vladivostok Time (VLAT)',
                                        
                                        // Australia & Pacific
                                        'Australia/Sydney' => 'Australian Eastern Time (AET) - Sydney',
                                        'Australia/Melbourne' => 'Australian Eastern Time (AET) - Melbourne',
                                        'Australia/Brisbane' => 'Australian Eastern Time (AET) - Brisbane',
                                        'Australia/Adelaide' => 'Australian Central Time (ACT) - Adelaide',
                                        'Australia/Perth' => 'Australian Western Time (AWT) - Perth',
                                        'Australia/Darwin' => 'Australian Central Time (ACT) - Darwin',
                                        'Pacific/Auckland' => 'New Zealand Time (NZST) - Auckland',
                                        'Pacific/Fiji' => 'Fiji Time (FJT)',
                                        
                                        // UTC
                                        'UTC' => 'Coordinated Universal Time (UTC)',
                                    ];
                                @endphp
                                @foreach($timezones as $tzValue => $tzLabel)
                                    <option value="{{ $tzValue }}" {{ $currentTimezone == $tzValue ? 'selected' : '' }}>{{ $tzLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="dateFormat" class="form-label">Date Format</label>
                            <select id="dateFormat" class="form-input">
                                <option value="MM-DD-YYYY" {{ ($companySettings['date_format'] ?? 'MM-DD-YYYY') == 'MM-DD-YYYY' ? 'selected' : '' }}>MM-DD-YYYY</option>
                                <option value="DD-MM-YYYY" {{ ($companySettings['date_format'] ?? '') == 'DD-MM-YYYY' ? 'selected' : '' }}>DD-MM-YYYY</option>
                                <option value="YYYY-MM-DD" {{ ($companySettings['date_format'] ?? '') == 'YYYY-MM-DD' ? 'selected' : '' }}>YYYY-MM-DD</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="currency" class="form-label">Currency</label>
                            <select id="currency" class="form-input">
                                <option value="USD" {{ ($companySettings['currency'] ?? 'USD') == 'USD' ? 'selected' : '' }}>USD ($)</option>
                                <option value="EUR" {{ ($companySettings['currency'] ?? '') == 'EUR' ? 'selected' : '' }}>EUR (€)</option>
                                <option value="GBP" {{ ($companySettings['currency'] ?? '') == 'GBP' ? 'selected' : '' }}>GBP (£)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="language" class="form-label">Language</label>
                            <select id="language" class="form-input">
                                <option value="en" {{ ($companySettings['language'] ?? 'en') == 'en' ? 'selected' : '' }}>English</option>
                                <option value="es" {{ ($companySettings['language'] ?? '') == 'es' ? 'selected' : '' }}>Spanish</option>
                                <option value="fr" {{ ($companySettings['language'] ?? '') == 'fr' ? 'selected' : '' }}>French</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="form-section-title">Company Logo</h3>
                    <div class="logo-upload">
                        <div class="logo-preview" id="logoPreview">
                            @if(isset($company) && $company && $company->logo)
                                <img src="{{ public_media_url($company->logo) }}" alt="Company Logo" id="logoImage">
                            @else
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" id="logoPlaceholder">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                    <circle cx="8.5" cy="8.5" r="1.5"/>
                                    <polyline points="21 15 16 10 5 21"/>
                                </svg>
                                <span id="logoText">No logo uploaded</span>
                            @endif
                        </div>
                    <div class="logo-upload-controls">
                        <input type="file" id="logoInput" name="logo" accept="image/*" style="display: none;" onchange="handleLogoPreview(this)">
                        <button class="btn-secondary" type="button" onclick="document.getElementById('logoInput').click()">Upload Logo</button>
                        @if(isset($company) && $company && $company->logo)
                            <button class="btn-secondary" type="button" onclick="removeLogo()" style="margin-left: 0.5rem;">Remove Logo</button>
                        @endif
                    </div>
                    <small class="form-help">Recommended: 200x200px, max 2MB. Supports: JPG, PNG, GIF, SVG</small>
                    </div>
                </div>

                <div class="form-actions">
                    <button class="btn-secondary" type="button" onclick="resetCompanyForm()">Cancel</button>
                    <button class="btn-primary" type="button" onclick="saveCompanySettings()">Save Changes</button>
                </div>
            </div>
        </div>

        <!-- Employee Profile Tab -->
        <div class="tab-content {{ !auth()->user()?->hasPermission('view_user_roles_permissions') && !auth()->user()?->hasPermission('view_user_company_setup') && auth()->user()?->hasPermission('view_user_employee_profile') ? 'active' : '' }}" id="employeesTab">
            <div class="section-header">
                <h2 class="section-title">Employee Profiles</h2>
                <button class="btn-primary" onclick="openEmployeeModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Add Employee
                </button>
            </div>

            <!-- Search Bar -->
            <div class="search-container" style="margin-bottom: 1.5rem;">
                <div class="search-input-wrapper" style="position: relative; max-width: 400px;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 18px; height: 18px; color: #6b7280; pointer-events: none;">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="m21 21-4.35-4.35"/>
                    </svg>
                    <input type="text" id="employeeSearch" class="form-input" placeholder="Search by name or email..." style="padding-left: 40px;">
                    <button id="clearSearch" class="clear-search-btn" style="display: none; position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; padding: 4px; color: #6b7280;" title="Clear search">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 18px; height: 18px;">
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Desktop Table View -->
            <div class="employees-table-container">
                <table class="employees-table" id="employeesTable">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" class="table-checkbox" id="selectAll">
                            </th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Department</th>
                            <th>Clients</th>
                            <th>Sales rep</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="employeesTableBody">
                        <!-- Data will be populated by JavaScript from API -->
                    </tbody>
                </table>
            </div>

            <!-- Mobile Card View -->
            <div class="employees-cards" id="employeesCards">
                <!-- Cards will be populated by JavaScript -->
            </div>

            <!-- Pagination -->
            <div class="table-pagination">
                <div class="pagination-info">
                    <span id="paginationInfo">Showing 1 to 10 of 157 results</span>
                </div>
                <div class="pagination-controls">
                    <button class="pagination-btn" id="prevBtn" disabled>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="15 18 9 12 15 6"/>
                        </svg>
                        Previous
                    </button>
                    <div class="pagination-numbers" id="paginationNumbers">
                        <!-- Page numbers will be generated by JavaScript -->
                    </div>
                    <button class="pagination-btn" id="nextBtn">
                        Next
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="9 18 15 12 9 6"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Sales Reps Tab -->
        <div class="tab-content" id="salesRepsTab">
            <div class="section-header">
                <h2 class="section-title">Sales reps</h2>
                <button type="button" class="btn-primary" onclick="openSalesRepModal(null)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Add sales rep
                </button>
            </div>
            <p class="page-subtitle" style="margin-top: -0.5rem; margin-bottom: 1.25rem;">Maintain your sales rep contacts here. Commission rates are set per employee in Employee Profile.</p>
            <div class="employees-table-container">
                <table class="employees-table" id="salesRepsTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="salesRepsTableBody">
                    </tbody>
                </table>
            </div>
            <div class="table-pagination" id="salesRepsPaginationWrap">
                <div class="pagination-info">
                    <span id="salesRepsPaginationInfo">Showing 0 results</span>
                </div>
                <div class="pagination-controls">
                    <button type="button" class="pagination-btn" id="salesRepsPrevBtn" disabled>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="15 18 9 12 15 6"/>
                        </svg>
                        Previous
                    </button>
                    <div class="pagination-numbers" id="salesRepsPaginationNumbers"></div>
                    <button type="button" class="pagination-btn" id="salesRepsNextBtn" disabled>
                        Next
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="9 18 15 12 9 6"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Departments Tab -->
        <div class="tab-content" id="departmentsTab">
            <div class="section-header">
                <h2 class="section-title">Departments</h2>
                <button class="btn-primary" onclick="openDepartmentModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Add Department
                </button>
            </div>

            <!-- Departments Management Section -->
            <div class="departments-section" style="margin-top: 1.5rem;">
                <div class="departments-grid" id="departmentsGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.75rem;">
                    <!-- Departments will be loaded here -->
                </div>
            </div>
        </div>

        <!-- Role Based Access Control Tab -->
        <div class="tab-content {{ !auth()->user()?->hasPermission('view_user_roles_permissions') && !auth()->user()?->hasPermission('view_user_company_setup') && !auth()->user()?->hasPermission('view_user_employee_profile') && auth()->user()?->hasPermission('view_user_role_based_access') ? 'active' : '' }}" id="rbacTab">
            <div class="rbac-wrapper">
                <!-- Role Selection Header -->
                <div class="rbac-header">
                    <div class="rbac-header-content">
                        <div class="role-selector-wrapper">
                            <label for="roleSelect" class="form-label">Select Role to Manage Permissions</label>
                            <select id="roleSelect" class="form-input rbac-role-select">
                                <option value="">Choose a role...</option>
                                @foreach($roles ?? [] as $role)
                                    <option value="{{ $role->id }}">{{ $role->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="rbac-stats" id="rbacStats" style="display: none;">
                            <div class="stat-badge">
                                <span class="stat-label">Selected:</span>
                                <span class="stat-value" id="selectedCount">0</span>
                                <span class="stat-label">/</span>
                                <span class="stat-value" id="totalCount">0</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Permissions Content -->
                <div class="rbac-content-wrapper" id="rbacContentWrapper" style="display: none;">
                    <div class="rbac-toolbar">
                        <div class="rbac-toolbar-left">
                            <button class="btn-toolbar" type="button" onclick="selectAllPermissions()" id="selectAllBtn">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="9 11 12 14 22 4"/>
                                    <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                                </svg>
                                Select All
                            </button>
                            <button class="btn-toolbar" type="button" onclick="deselectAllPermissions()" id="deselectAllBtn">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                    <path d="M9 9h6v6H9z"/>
                                </svg>
                                Deselect All
                            </button>
                            <button class="btn-toolbar" type="button" onclick="resetRolePermissions()" id="resetBtn">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="1 4 1 10 7 10"/>
                                    <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/>
                                </svg>
                                Reset
                            </button>
                        </div>
                        <div class="rbac-toolbar-right">
                            <button class="btn-primary btn-save-permissions" type="button" onclick="saveRolePermissions()" id="saveBtn">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                                    <polyline points="17 21 17 13 7 13 7 21"/>
                                    <polyline points="7 3 7 8 15 8"/>
                                </svg>
                                Save Permissions
                            </button>
                        </div>
                    </div>

                    <div class="permissions-container" id="permissionsGrid">
                        <!-- Permissions will be loaded here -->
                    </div>
                </div>

                <!-- Empty State -->
                <div class="rbac-empty-state" id="rbacEmptyState">
                    <div class="empty-state-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                    </div>
                    <h3 class="empty-state-title">Select a Role to Manage Permissions</h3>
                    <p class="empty-state-description">Choose a role from the dropdown above to configure which sidebar modules and features users with that role can access.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Role Modal -->
    <div class="modal" id="roleModal">
        <div class="modal-overlay" onclick="closeRoleModal()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="roleModalTitle">Add New Role</h3>
                <button class="modal-close" onclick="closeRoleModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <form id="roleForm">
                    <input type="hidden" id="roleId" name="id">
                    <div class="form-group">
                        <label for="roleName" class="form-label">Role Name *</label>
                        <input type="text" id="roleName" name="name" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="roleSlug" class="form-label">Slug</label>
                        <input type="text" id="roleSlug" name="slug" class="form-input" placeholder="Auto-generated if empty">
                    </div>
                    <div class="form-group">
                        <label for="roleDescription" class="form-label">Description</label>
                        <textarea id="roleDescription" name="description" class="form-textarea" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" type="button" onclick="closeRoleModal()">Cancel</button>
                <button class="btn-primary" type="button" onclick="saveRole()">Save Role</button>
            </div>
        </div>
    </div>

    <!-- Employee Modal -->
    <div class="modal" id="employeeModal">
        <div class="modal-overlay" onclick="closeEmployeeModal()"></div>
        <div class="modal-content employee-wizard">
            <div class="modal-header">
                <h3 class="modal-title" id="employeeModalTitle">Add New Employee</h3>
                <button class="modal-close" onclick="closeEmployeeModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>

            <div class="wizard-steps" id="employeeWizardSteps">
                <button type="button" class="wizard-step active" data-step="1" onclick="goToEmployeeStep(1)">
                    <span class="wizard-step-num">1</span>
                    <span class="wizard-step-label">Account</span>
                </button>
                <button type="button" class="wizard-step" data-step="2" onclick="goToEmployeeStep(2)">
                    <span class="wizard-step-num">2</span>
                    <span class="wizard-step-label">Personal</span>
                </button>
                <button type="button" class="wizard-step" data-step="3" onclick="goToEmployeeStep(3)">
                    <span class="wizard-step-num">3</span>
                    <span class="wizard-step-label">Compensation</span>
                </button>
                <button type="button" class="wizard-step" data-step="4" onclick="goToEmployeeStep(4)">
                    <span class="wizard-step-num">4</span>
                    <span class="wizard-step-label">Clients</span>
                </button>
            </div>

            <div class="modal-body">
                <form id="employeeForm" enctype="multipart/form-data">
                    <input type="hidden" id="employeeId" name="id">

                    <!-- Step 1: Account & Role -->
                    <div class="wizard-panel active" data-step="1">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="employeeName" class="form-label">Full Name *</label>
                                <input type="text" id="employeeName" name="name" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label for="employeeEmail" class="form-label">Email Address *</label>
                                <input type="email" id="employeeEmail" name="email" class="form-input" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="employeePassword" class="form-label">Password <span id="passwordRequired">*</span></label>
                                <input type="password" id="employeePassword" name="password" class="form-input">
                                <small class="form-help" id="passwordHelp">Leave blank to keep current password when editing</small>
                            </div>
                            <div class="form-group">
                                <label for="employeePhone" class="form-label">Phone Number</label>
                                <input type="tel" id="employeePhone" name="phone" class="form-input" placeholder="+1 (555) 123-4567">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="employeeRole" class="form-label">Role *</label>
                                <select id="employeeRole" name="role_id" class="form-input" required>
                                    <option value="">Select a role...</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="employeeStatus" class="form-label">Status *</label>
                                <select id="employeeStatus" name="status" class="form-input" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Personal -->
                    <div class="wizard-panel" data-step="2">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="employeeDepartment" class="form-label">Department</label>
                                <select id="employeeDepartment" name="department_id" class="form-input">
                                    <option value="">Select a department...</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="employeeDateOfBirth" class="form-label">Date of Birth</label>
                                <input type="date" id="employeeDateOfBirth" name="date_of_birth" class="form-input">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="employeeEmploymentDate" class="form-label">Employment Date</label>
                                <input type="date" id="employeeEmploymentDate" name="employment_date" class="form-input">
                            </div>
                            <div class="form-group">
                                <label for="employeeAddress" class="form-label">Address</label>
                                <textarea id="employeeAddress" name="address" class="form-textarea" rows="2"></textarea>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="employeePhoto" class="form-label">Photo</label>
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <div id="employeePhotoPreview">
                                    <span id="employeePhotoPlaceholder">No photo</span>
                                </div>
                                <div style="flex: 1;">
                                    <input type="file" id="employeePhoto" name="photo" accept="image/*" class="form-input" style="padding: 0.5rem;" onchange="handleEmployeePhotoPreview(this)">
                                    <small class="form-help">Max 2MB. Supported: JPG, PNG, GIF</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Compensation -->
                    <div class="wizard-panel" data-step="3">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="employeeSalary" class="form-label">Salary *</label>
                                <input type="number" id="employeeSalary" name="salary" class="form-input" step="0.01" min="0" required placeholder="0.00">
                            </div>
                            <div class="form-group">
                                <label for="employeeAllowances" class="form-label">Allowances *</label>
                                <input type="number" id="employeeAllowances" name="allowances" class="form-input" step="0.01" min="0" required placeholder="0.00">
                                <small class="form-help">Monthly allowances amount</small>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="employeeClientInvoiceAmount" class="form-label">Client Invoice Amount *</label>
                                <input type="number" id="employeeClientInvoiceAmount" name="client_invoice_amount" class="form-input" step="0.01" min="0" required placeholder="0.00">
                                <small class="form-help">Amount to invoice clients for this employee's work</small>
                            </div>
                            <div class="form-group">
                                <label for="employeeRequiredWorkHours" class="form-label">Required Work Hours *</label>
                                <input type="number" id="employeeRequiredWorkHours" name="required_work_hours" class="form-input" step="0.1" min="0" max="999" required placeholder="160">
                                <small class="form-help">Expected monthly work hours (e.g. 160 for full-time)</small>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="employeeRecordingDuration" class="form-label">Recording Length (minutes)</label>
                                <input type="number" id="employeeRecordingDuration" name="recording_duration_minutes" class="form-input" step="any" min="0.1" max="120" placeholder="0.5" oninput="updateEmployeeRecordingSecondsHint()">
                                <small class="form-help">Length of each screen recording clip. Default is 0.5 (30 seconds). <span id="employeeRecordingSecondsHint"></span></small>
                            </div>
                            <div class="form-group">
                                <label for="employeeTwilioNumber" class="form-label">Phone System Number</label>
                                <select id="employeeTwilioNumber" name="twilio_number" class="form-input">
                                    <option value="">No phone system number</option>
                                </select>
                                <small class="form-help">Used as caller ID for outbound calls. The same number can be assigned to multiple employees.</small>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="employeeTwilioSmsNumber" class="form-label">SMS Number</label>
                                <select id="employeeTwilioSmsNumber" name="twilio_sms_number" class="form-input">
                                    <option value="">No SMS number</option>
                                </select>
                                <small class="form-help">Used as the From number for SMS. Can match the phone system number, and can be shared with other employees.</small>
                            </div>
                            <div class="form-group">
                                <label for="employeeWiseAccount" class="form-label">Wise Account</label>
                                <input type="text" id="employeeWiseAccount" name="wise_account" class="form-input" placeholder="Email or account identifier" readonly>
                                <small class="form-help">Managed in Wise Recipients. Assign recipient IDs there.</small>
                            </div>
                        </div>
                        <div class="form-group" style="border-top: 1px solid var(--border); padding-top: 1rem; margin-top: 0.25rem;">
                            <label for="employeeSalesRepId" class="form-label">Sales rep (optional)</label>
                            <select id="employeeSalesRepId" name="sales_rep_id" class="form-input" onchange="toggleEmployeeSalesCommission()">
                                <option value="">None</option>
                            </select>
                            <small class="form-help">Choose a rep from the Sales Reps tab, then set their commission for this employee below.</small>
                            <div id="employeeSalesRepCommissionWrap" style="display: none; margin-top: 1rem;">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="employeeSalesRepCommissionType" class="form-label">Commission type *</label>
                                        <select id="employeeSalesRepCommissionType" class="form-input">
                                            <option value="percent">Percentage (%)</option>
                                            <option value="usd">Fixed amount (USD)</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="employeeSalesRepCommissionValue" class="form-label">Commission value *</label>
                                        <input type="number" id="employeeSalesRepCommissionValue" class="form-input" step="0.01" min="0" placeholder="0">
                                        <small class="form-help" id="employeeSalesRepCommissionHelp">Percent of revenue (0–100) or fixed USD.</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: Clients -->
                    <div class="wizard-panel" data-step="4">
                        <div class="form-group" style="margin-bottom: 0.75rem;">
                            <label class="form-label">Assigned Clients</label>
                            <small class="form-help" style="margin-top: 0;">Select the clients this employee works with.</small>
                        </div>
                        <input type="text" id="employeeClientSearch" class="form-input" placeholder="Search clients..." style="margin-bottom: 0.75rem;" oninput="filterEmployeeClientOptions(this.value)">
                        <div id="employeeClientList" class="client-picker">
                            <p class="client-picker-empty">Loading clients...</p>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer wizard-footer">
                <button class="btn-secondary" type="button" id="employeeBackBtn" onclick="prevEmployeeStep()" style="margin-right: auto;">Back</button>
                <button class="btn-secondary" type="button" onclick="closeEmployeeModal()">Cancel</button>
                <button class="btn-primary" type="button" id="employeeNextBtn" onclick="nextEmployeeStep()">Next</button>
                <button class="btn-primary" type="button" id="employeeSaveBtn" onclick="saveEmployee()" style="display: none;">Save Employee</button>
            </div>
        </div>
    </div>

    <!-- Sales rep contact (commission is configured on each employee) -->
    <div class="modal" id="salesRepModal">
        <div class="modal-overlay" onclick="closeSalesRepModal()"></div>
        <div class="modal-content" style="max-width: 520px;">
            <div class="modal-header">
                <h3 class="modal-title" id="salesRepModalTitle">Add sales rep</h3>
                <button type="button" class="modal-close" onclick="closeSalesRepModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="salesRepRecordId" value="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="salesRepName" class="form-label">Full name *</label>
                        <input type="text" id="salesRepName" class="form-input" required maxlength="255" autocomplete="name">
                    </div>
                    <div class="form-group">
                        <label for="salesRepEmail" class="form-label">Email *</label>
                        <input type="email" id="salesRepEmail" class="form-input" required maxlength="255" autocomplete="email">
                    </div>
                </div>
                <div class="form-group">
                    <label for="salesRepPhone" class="form-label">Phone</label>
                    <input type="tel" id="salesRepPhone" class="form-input" maxlength="255" placeholder="Optional">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeSalesRepModal()">Cancel</button>
                <button type="button" class="btn-primary" onclick="saveSalesRepRecord()">Save</button>
            </div>
        </div>
    </div>

    <!-- Department Modal -->
    <div class="modal" id="departmentModal">
        <div class="modal-overlay" onclick="closeDepartmentModal()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="departmentModalTitle">Add New Department</h3>
                <button class="modal-close" onclick="closeDepartmentModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <form id="departmentForm">
                    <input type="hidden" id="departmentId" name="id">
                    <div class="form-group">
                        <label for="departmentName" class="form-label">Department Name *</label>
                        <input type="text" id="departmentName" name="name" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="departmentDescription" class="form-label">Description</label>
                        <textarea id="departmentDescription" name="description" class="form-textarea" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" type="button" onclick="closeDepartmentModal()">Cancel</button>
                <button class="btn-primary" type="button" onclick="saveDepartment()">Save Department</button>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
    .management-container {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        overflow: hidden;
    }

    /* Tabs */
    .management-tabs {
        display: flex;
        border-bottom: 1px solid var(--border);
        background: var(--bg-primary);
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
    }

    .management-tabs::-webkit-scrollbar {
        height: 4px;
    }

    .management-tabs::-webkit-scrollbar-track {
        background: var(--bg-primary);
    }

    .management-tabs::-webkit-scrollbar-thumb {
        background: var(--border);
        border-radius: 2px;
    }

    .tab-btn {
        padding: 1rem 1.5rem;
        background: none;
        border: none;
        border-bottom: 2px solid transparent;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.15s;
        white-space: nowrap;
        -webkit-tap-highlight-color: transparent;
        touch-action: manipulation;
    }

    .tab-btn:hover {
        color: var(--accent);
        background: var(--bg-card);
    }

    .tab-btn.active {
        color: var(--accent);
        border-bottom-color: var(--accent);
        background: var(--bg-card);
    }

    .tab-content {
        display: none;
        padding: 2rem;
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
    }

    .section-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--text-primary);
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

    /* Roles Grid */
    .roles-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
    }

    .role-card {
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.5rem;
        transition: all 0.15s;
    }

    .role-card:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    .role-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 0.75rem;
    }

    .role-info {
        flex: 1;
    }

    .role-name {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }

    .role-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 100px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .role-badge.admin {
        background: #fee2e2;
        color: #dc2626;
    }

    .role-badge.manager {
        background: #dbeafe;
        color: #2563eb;
    }

    .role-badge.employee {
        background: #d1fae5;
        color: #059669;
    }

    .role-actions {
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
        touch-action: manipulation;
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

    .role-description {
        font-size: 0.875rem;
        color: var(--text-secondary);
        margin-bottom: 1rem;
    }

    .role-stats {
        display: flex;
        gap: 1rem;
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--border);
    }

    .stat-item {
        font-size: 0.8125rem;
        color: var(--text-muted);
    }

    .permissions-preview {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .permission-tag {
        padding: 0.25rem 0.625rem;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 4px;
        font-size: 0.75rem;
        color: var(--text-secondary);
    }

    /* Form Styles */
    .form-container {
        max-width: 800px;
    }

    .form-section {
        margin-bottom: 2rem;
        padding-bottom: 2rem;
        border-bottom: 1px solid var(--border);
    }

    .form-section:last-child {
        border-bottom: none;
    }

    .form-section-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 1.5rem;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }

    .form-group {
        margin-bottom: 1rem;
    }

    .form-label {
        display: block;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }

    .form-input, .form-textarea {
        width: 100%;
        padding: 0.625rem 0.75rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        background: var(--bg-card);
        color: var(--text-primary);
        transition: all 0.15s;
        font-family: inherit;
    }

    .form-input:focus, .form-textarea:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(95, 97, 230, 0.1);
    }

    .form-textarea {
        resize: vertical;
    }

    .logo-upload {
        display: flex;
        align-items: center;
        gap: 1.5rem;
    }

    .logo-preview {
        width: 120px;
        height: 120px;
        border: 2px dashed var(--border);
        border-radius: 8px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        color: var(--text-muted);
    }

    .logo-preview svg {
        width: 40px;
        height: 40px;
    }

    .logo-preview img {
        max-width: 100%;
        max-height: 120px;
        object-fit: contain;
        border-radius: 8px;
    }

    .logo-upload-controls {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .form-actions {
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
        margin-top: 2rem;
    }

    /* Employees Table */
    .employees-table-container {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        position: relative;
    }

    .employees-table {
        width: 100%;
        border-collapse: collapse;
    }

    .employees-table thead {
        background: var(--bg-primary);
    }

    .employees-table th {
        padding: 0.875rem 1rem;
        text-align: left;
        font-size: 0.8125rem;
        font-weight: 600;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 2px solid var(--border);
    }

    .employees-table td {
        padding: 1rem;
        font-size: 0.875rem;
        color: var(--text-primary);
        border-bottom: 1px solid var(--border);
    }

    .employees-table tbody tr:hover {
        background: var(--bg-primary);
    }

    .table-checkbox {
        width: 18px;
        height: 18px;
        cursor: pointer;
        accent-color: var(--accent);
    }

    /* Mobile Card View */
    .employees-cards {
        display: none;
        flex-direction: column;
        gap: 1rem;
    }

    @media (min-width: 769px) {
        .employees-table-container {
            display: block;
        }
        .employees-cards {
            display: none !important;
        }
    }

    @media (max-width: 768px) {
        .employees-table-container {
            display: none !important;
        }
        .employees-cards {
            display: flex !important;
        }
    }

    .employee-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.25rem;
    }

    .employee-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--border);
    }

    .employee-card-main {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .employee-card-info {
        flex: 1;
    }

    .employee-card-actions {
        display: flex;
        gap: 0.5rem;
    }

    .employee-card-details {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
        margin-top: 1rem;
    }

    .employee-card-detail {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .employee-card-label {
        font-size: 0.75rem;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .employee-card-value {
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
        margin-top: 1.5rem;
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
        display: flex;
        align-items: center;
        gap: 0.5rem;
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

    .pagination-btn svg {
        width: 18px;
        height: 18px;
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

    .employee-cell {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .employee-avatar {
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
        overflow: hidden;
        position: relative;
    }
    
    .employee-avatar[style*="background-image"] {
        background-color: var(--bg-primary);
    }
    
    .employee-avatar[style*="background-image"]::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        border-radius: 50%;
        border: 2px solid var(--border);
    }

    .employee-name {
        font-weight: 500;
        color: var(--text-primary);
    }

    .employee-id {
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    .status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 100px;
        font-size: 0.75rem;
        font-weight: 500;
    }

    .status-badge.active {
        background: #d1fae5;
        color: #059669;
    }

    .status-badge.inactive {
        background: #fee2e2;
        color: #dc2626;
    }

    .table-actions {
        display: flex;
        gap: 0.5rem;
    }

    /* RBAC */
    .rbac-wrapper {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .rbac-header {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.5rem;
    }

    .rbac-header-content {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 2rem;
        flex-wrap: wrap;
    }

    .role-selector-wrapper {
        flex: 1;
        min-width: 280px;
    }

    .role-selector-wrapper .form-label {
        display: block;
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }

    .rbac-role-select {
        width: 100%;
        padding: 0.75rem 1rem;
        font-size: 0.9375rem;
    }

    .rbac-stats {
        display: flex;
        align-items: center;
    }

    .stat-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.625rem 1rem;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
    }

    .stat-label {
        color: var(--text-secondary);
        font-weight: 500;
    }

    .stat-value {
        color: var(--text-primary);
        font-weight: 600;
    }

    .rbac-content-wrapper {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .rbac-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem 1.5rem;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 12px;
        flex-wrap: wrap;
    }

    .rbac-toolbar-left,
    .rbac-toolbar-right {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    .btn-toolbar {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.625rem 1rem;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
        cursor: pointer;
        transition: all 0.15s;
    }

    .btn-toolbar:hover {
        background: var(--bg-primary);
        border-color: var(--accent);
        color: var(--accent);
    }

    .btn-toolbar svg {
        width: 16px;
        height: 16px;
    }

    .btn-save-permissions {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-save-permissions svg {
        width: 18px;
        height: 18px;
    }

    .permissions-container {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .permission-category {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        overflow: hidden;
    }

    .permission-category-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem 1.5rem;
        background: var(--bg-primary);
        border-bottom: 1px solid var(--border);
    }

    .permission-category-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .permission-category-title svg {
        width: 20px;
        height: 20px;
        color: var(--accent);
    }

    .permission-category-actions {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-category-action {
        padding: 0.375rem 0.75rem;
        background: transparent;
        border: 1px solid var(--border);
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 500;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.15s;
    }

    .btn-category-action:hover {
        background: var(--bg-card);
        border-color: var(--accent);
        color: var(--accent);
    }

    .permission-group {
        padding: 1.5rem;
    }

    .permission-group-title {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 1rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid var(--border);
    }

    .permission-list {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 0.75rem;
    }

    .permission-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.15s;
        border: 1px solid transparent;
    }

    .permission-item:hover {
        background: var(--bg-primary);
        border-color: var(--border);
    }

    .permission-item.checked {
        background: rgba(95, 97, 230, 0.05);
        border-color: var(--accent);
    }

    .permission-item input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
        accent-color: var(--accent);
        flex-shrink: 0;
    }

    .permission-item-label {
        font-size: 0.875rem;
        color: var(--text-primary);
        font-weight: 500;
        flex: 1;
        user-select: none;
    }

    .permission-item.checked .permission-item-label {
        color: var(--accent);
    }

    .rbac-empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 4rem 2rem;
        text-align: center;
    }

    .empty-state-icon {
        width: 80px;
        height: 80px;
        margin-bottom: 1.5rem;
        color: var(--text-muted);
    }

    .empty-state-icon svg {
        width: 100%;
        height: 100%;
    }

    .empty-state-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }

    .empty-state-description {
        font-size: 0.875rem;
        color: var(--text-secondary);
        max-width: 500px;
        line-height: 1.6;
    }

    /* Responsive - Tablet */
    @media (max-width: 1024px) {
        .roles-grid {
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        }

        .permission-list {
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        }

        .rbac-header-content {
            flex-wrap: wrap;
        }

        .role-selector-wrapper {
            min-width: 240px;
        }
    }

    /* Responsive - Mobile */
    @media (max-width: 768px) {
        .management-container {
            border-radius: 8px;
            margin: 0;
            border-left: none;
            border-right: none;
        }

        .management-tabs {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
        }

        .management-tabs::-webkit-scrollbar {
            display: none;
        }

        .tab-btn {
            padding: 0.875rem 1rem;
            font-size: 0.8125rem;
            min-width: max-content;
        }

        .tab-content {
            padding: 1.25rem;
        }

        .section-header {
            flex-direction: column;
            align-items: stretch;
            gap: 1rem;
        }

        .section-title {
            font-size: 1.125rem;
        }

        .btn-primary {
            width: 100%;
            justify-content: center;
        }

        .roles-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .role-card {
            padding: 1.25rem;
        }

        .role-header {
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .role-actions {
            width: 100%;
            justify-content: flex-end;
        }

        .role-stats {
            flex-wrap: wrap;
        }

        .permissions-preview {
            gap: 0.375rem;
        }

        .permission-tag {
            font-size: 0.6875rem;
            padding: 0.25rem 0.5rem;
        }

        .form-container {
            max-width: 100%;
        }

        .form-section {
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
        }

        .form-section-title {
            font-size: 1rem;
            margin-bottom: 1.25rem;
        }

        .form-row {
            grid-template-columns: 1fr;
            gap: 0;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            font-size: 0.8125rem;
        }

        .form-input,
        .form-textarea {
            padding: 0.625rem;
            font-size: 0.8125rem;
        }

        .logo-upload {
            flex-direction: column;
            align-items: stretch;
            gap: 1rem;
        }

        .logo-preview {
            width: 100%;
            max-width: 200px;
            margin: 0 auto;
        }

        .form-actions {
            flex-direction: column-reverse;
            gap: 0.75rem;
        }

        .form-actions .btn-primary,
        .form-actions .btn-secondary {
            width: 100%;
            justify-content: center;
        }

        .employees-table-container {
            display: none;
        }

        .employees-cards {
            display: flex;
        }

        .employee-card-details {
            grid-template-columns: 1fr;
        }

        .table-pagination {
            flex-direction: column;
            align-items: stretch;
            gap: 1rem;
        }

        .pagination-controls {
            justify-content: center;
            width: 100%;
        }

        .pagination-btn {
            flex: 1;
            justify-content: center;
            max-width: 150px;
        }

        .pagination-numbers {
            justify-content: center;
            flex: 1;
        }

        .rbac-header {
            padding: 1.25rem;
        }

        .rbac-header-content {
            flex-direction: column;
            align-items: stretch;
            gap: 1rem;
        }

        .role-selector-wrapper {
            min-width: 100%;
        }

        .rbac-toolbar {
            flex-direction: column;
            align-items: stretch;
            gap: 0.75rem;
            padding: 1rem;
        }

        .rbac-toolbar-left,
        .rbac-toolbar-right {
            width: 100%;
            justify-content: stretch;
        }

        .btn-toolbar {
            flex: 1;
            justify-content: center;
        }

        .btn-save-permissions {
            width: 100%;
            justify-content: center;
        }

        .permission-list {
            grid-template-columns: 1fr;
        }

        .permission-category-header {
            flex-direction: column;
            align-items: stretch;
            gap: 0.75rem;
            padding: 1rem;
        }

        .permission-category-actions {
            width: 100%;
            justify-content: flex-end;
        }

        .empty-state-icon {
            width: 60px;
            height: 60px;
        }

        .empty-state-title {
            font-size: 1.125rem;
        }
    }

    /* Responsive - Small Mobile */
    @media (max-width: 480px) {
        .management-container {
            margin: 0;
            border-left: none;
            border-right: none;
            border-radius: 0;
        }

        .tab-content {
            padding: 1rem;
        }

        .management-tabs {
            padding: 0.5rem;
        }

        .tab-btn {
            padding: 0.75rem 0.875rem;
            font-size: 0.75rem;
        }

        .section-title {
            font-size: 1rem;
        }

        .role-card {
            padding: 1rem;
        }

        .role-name {
            font-size: 1rem;
        }

        .role-badge {
            font-size: 0.6875rem;
            padding: 0.25rem 0.625rem;
        }

        .role-description {
            font-size: 0.8125rem;
        }

        .form-section {
            margin-bottom: 1.25rem;
            padding-bottom: 1.25rem;
        }

        .form-section-title {
            font-size: 0.9375rem;
            margin-bottom: 1rem;
        }

        .logo-preview {
            width: 100%;
            height: 100px;
        }

        .logo-preview svg {
            width: 32px;
            height: 32px;
        }

        .employee-card {
            padding: 1rem;
        }

        .employee-card-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .employee-card-actions {
            width: 100%;
            justify-content: flex-end;
        }

        .pagination-btn {
            font-size: 0.8125rem;
            padding: 0.5rem 0.75rem;
        }

        .pagination-number {
            min-width: 32px;
            height: 32px;
            font-size: 0.8125rem;
        }

        .pagination-info {
            text-align: center;
            font-size: 0.8125rem;
        }

        .rbac-sidebar {
            padding: 0.75rem;
        }

        .category-item {
            padding: 0.5rem 0.75rem;
            font-size: 0.75rem;
        }

        .permission-group {
            padding: 1rem;
        }

        .permission-item {
            font-size: 0.75rem;
        }

        .permission-item input[type="checkbox"] {
            width: 16px;
            height: 16px;
        }

        .modal-content {
            width: 98%;
            max-width: none;
            max-height: 95vh;
        }

        .modal-header,
        .modal-body,
        .modal-footer {
            padding: 1rem;
        }

        .modal-title {
            font-size: 1.125rem;
        }
    }

    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }

    .modal.active {
        display: flex;
    }

    .modal-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(4px);
    }

    .modal-content {
        position: relative;
        background: var(--bg-card);
        border-radius: 12px;
        width: 95%;
        max-width: none;
        max-height: 90vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        z-index: 1001;
    }

    .modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1.5rem;
        border-bottom: 1px solid var(--border);
    }

    .modal-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }

    .modal-close {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: none;
        border: none;
        border-radius: 6px;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.15s;
    }

    .modal-close:hover {
        background: var(--bg-primary);
        color: var(--text-primary);
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

    .modal-footer {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 1rem;
        padding: 1.5rem;
        border-top: 1px solid var(--border);
    }

    .form-help {
        display: block;
        font-size: 0.75rem;
        color: var(--text-muted);
        margin-top: 0.25rem;
    }

    /* Employee Wizard Modal */
    .employee-wizard {
        width: 95%;
        max-width: 640px;
    }

    .wizard-steps {
        display: flex;
        gap: 0.5rem;
        padding: 1rem 1.5rem;
        border-bottom: 1px solid var(--border);
        overflow-x: auto;
    }

    .wizard-step {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex: 1;
        min-width: max-content;
        padding: 0;
        background: none;
        border: none;
        cursor: pointer;
        color: var(--text-muted);
        font-family: inherit;
    }

    .wizard-step-num {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 26px;
        height: 26px;
        flex-shrink: 0;
        border-radius: 999px;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        font-size: 0.8125rem;
        font-weight: 600;
        transition: all 0.15s;
    }

    .wizard-step-label {
        font-size: 0.8125rem;
        font-weight: 500;
        white-space: nowrap;
    }

    .wizard-step.active .wizard-step-num {
        background: var(--accent);
        border-color: var(--accent);
        color: #fff;
    }

    .wizard-step.active .wizard-step-label {
        color: var(--text-primary);
    }

    .wizard-step.completed .wizard-step-num {
        background: rgba(95, 97, 230, 0.15);
        border-color: var(--accent);
        color: var(--accent);
    }

    .wizard-panel {
        display: none;
    }

    .wizard-panel.active {
        display: block;
    }

    .client-picker {
        border: 1px solid var(--border);
        border-radius: 8px;
        max-height: 260px;
        overflow-y: auto;
        background: var(--bg-primary);
    }

    .client-picker-option {
        display: flex;
        align-items: center;
        gap: 0.625rem;
        padding: 0.625rem 0.875rem;
        cursor: pointer;
        border-bottom: 1px solid var(--border);
        font-size: 0.875rem;
        color: var(--text-primary);
    }

    .client-picker-option:last-child {
        border-bottom: none;
    }

    .client-picker-option:hover {
        background: var(--bg-card);
    }

    .client-picker-option input[type="checkbox"] {
        width: 16px;
        height: 16px;
        flex-shrink: 0;
        accent-color: var(--accent);
    }

    .client-picker-option .client-picker-email {
        color: var(--text-muted);
        font-size: 0.75rem;
    }

    .client-picker-empty {
        margin: 0;
        padding: 1.25rem;
        text-align: center;
        font-size: 0.8125rem;
        color: var(--text-muted);
    }

    /* Employee Photo Preview */
    #employeePhotoPreview {
        width: 100px;
        height: 100px;
        border: 2px dashed var(--border);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        background: var(--bg-primary);
    }

    #employeePhotoPreview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    #employeePhotoPreview span {
        color: var(--text-muted);
        font-size: 0.75rem;
        text-align: center;
        padding: 0.5rem;
    }

    .empty-state {
        text-align: center;
        padding: 3rem 1rem;
        color: var(--text-secondary);
    }

    .empty-state p {
        margin: 0;
        font-size: 0.875rem;
    }

    /* Responsive - Extra Small */
    @media (max-width: 360px) {
        .tab-btn {
            padding: 0.625rem 0.75rem;
            font-size: 0.6875rem;
        }

        .tab-content {
            padding: 0.75rem;
        }

        .role-card {
            padding: 0.875rem;
        }

        .employees-table {
            min-width: 550px;
        }

        .btn-primary,
        .btn-secondary {
            padding: 0.75rem 1rem;
            font-size: 0.8125rem;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    // Tab switching
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const tabId = this.dataset.tab;
            
            // Update buttons
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            // Update content
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.getElementById(tabId + 'Tab').classList.add('active');
            
            // Load data for specific tabs
            if (tabId === 'departments') {
                loadDepartments();
            } else if (tabId === 'employees' && employeesData.length === 0) {
                loadEmployees(1, currentSearchTerm);
            } else if (tabId === 'salesReps') {
                loadSalesReps(salesRepsMeta.currentPage || 1);
            }
        });
    });

    // Category switching with permission filtering
    document.querySelectorAll('.category-item').forEach(item => {
        item.addEventListener('click', function() {
            const category = this.dataset.category;
            document.querySelectorAll('.category-item').forEach(i => i.classList.remove('active'));
            this.classList.add('active');
            
            // Filter permissions by category
            document.querySelectorAll('.permission-group').forEach(group => {
                if (category === 'all' || group.dataset.category === category) {
                    group.style.display = 'block';
                } else {
                    group.style.display = 'none';
                }
            });
        });
    });

    // Role management functions
    async function openRoleModal(roleId = null) {
        const modal = document.getElementById('roleModal');
        const form = document.getElementById('roleForm');
        const title = document.getElementById('roleModalTitle');
        
        // Reset form
        form.reset();
        document.getElementById('roleId').value = '';
        
        if (roleId) {
            // Edit mode - load role data
            title.textContent = 'Edit Role';
            try {
                const response = await fetch(`{{ route('api.user-management.roles') }}`);
                const roles = await response.json();
                const role = roles.find(r => r.id === parseInt(roleId));
                
                if (role) {
                    document.getElementById('roleId').value = role.id;
                    document.getElementById('roleName').value = role.name;
                    document.getElementById('roleSlug').value = role.slug || '';
                    document.getElementById('roleSlug').dataset.autoGenerated = 'false';
                    document.getElementById('roleDescription').value = role.description || '';
                } else {
                    alert('Role not found');
                    return;
                }
            } catch (error) {
                console.error('Error loading role:', error);
                alert('Failed to load role data');
                return;
            }
        } else {
            title.textContent = 'Add New Role';
        }
        
        modal.classList.add('active');
    }
    
    function closeRoleModal() {
        document.getElementById('roleModal').classList.remove('active');
    }
    
    async function saveRole() {
        const form = document.getElementById('roleForm');
        
        // Validate required fields
        const roleName = document.getElementById('roleName');
        if (!roleName.value || roleName.value.trim() === '') {
            alert('Role Name is required');
            roleName.focus();
            return;
        }
        
        // Check HTML5 validation
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        const formData = new FormData(form);
        const roleId = formData.get('id');
        
        const data = {
            name: formData.get('name'),
            slug: formData.get('slug') || null,
            description: formData.get('description') || null
        };
        
        try {
            const url = roleId 
                ? `/api/user-management/roles/${roleId}`
                : `/api/user-management/roles`;
            
            const response = await fetch(url, {
                method: roleId ? 'PUT' : 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                alert(roleId ? 'Role updated successfully!' : 'Role created successfully!');
                closeRoleModal();
                location.reload();
            } else {
                const errors = result.errors ? Object.values(result.errors).flat().join('\n') : (result.message || 'Failed to save role');
                alert(errors);
            }
        } catch (error) {
            console.error('Error saving role:', error);
            alert('Failed to save role. Please try again.');
        }
    }

    function editRole(roleId) {
        openRoleModal(roleId);
    }

    async function deleteRole(roleId) {
        if (!confirm('Are you sure you want to delete this role?')) return;
        
        try {
            const response = await fetch(`/api/user-management/roles/${roleId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Content-Type': 'application/json'
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                alert('Role deleted successfully');
                location.reload();
            } else {
                alert(data.message || 'Failed to delete role');
            }
        } catch (error) {
            console.error('Error deleting role:', error);
            alert('Failed to delete role');
        }
    }

    // Employee wizard state
    const EMPLOYEE_WIZARD_LAST_STEP = 4;
    let employeeWizardStep = 1;
    let employeeClientsCache = [];

    function focusEmployeeField(el) {
        if (!el) return;
        const panel = el.closest('.wizard-panel');
        if (panel && panel.dataset.step) {
            goToEmployeeStep(parseInt(panel.dataset.step, 10));
        }
        el.focus();
    }

    function updateEmployeeWizardUI() {
        document.querySelectorAll('#employeeModal .wizard-panel').forEach(panel => {
            panel.classList.toggle('active', parseInt(panel.dataset.step, 10) === employeeWizardStep);
        });
        document.querySelectorAll('#employeeWizardSteps .wizard-step').forEach(step => {
            const stepNum = parseInt(step.dataset.step, 10);
            step.classList.toggle('active', stepNum === employeeWizardStep);
            step.classList.toggle('completed', stepNum < employeeWizardStep);
        });

        const backBtn = document.getElementById('employeeBackBtn');
        const nextBtn = document.getElementById('employeeNextBtn');
        const saveBtn = document.getElementById('employeeSaveBtn');
        backBtn.style.visibility = employeeWizardStep === 1 ? 'hidden' : 'visible';
        const onLast = employeeWizardStep === EMPLOYEE_WIZARD_LAST_STEP;
        nextBtn.style.display = onLast ? 'none' : 'inline-flex';
        saveBtn.style.display = onLast ? 'inline-flex' : 'none';

        const body = document.querySelector('#employeeModal .modal-body');
        if (body) body.scrollTop = 0;
    }

    function validateEmployeeStep(step) {
        const panel = document.querySelector(`#employeeModal .wizard-panel[data-step="${step}"]`);
        if (!panel) return true;
        const fields = panel.querySelectorAll('input, select, textarea');
        for (const field of fields) {
            if (field.offsetParent === null && field.type !== 'hidden') {
                // Skip fields hidden via display:none (e.g. collapsed commission inputs)
                if (field.closest('[style*="display: none"]')) continue;
            }
            if (!field.checkValidity()) {
                field.reportValidity();
                return false;
            }
        }
        return true;
    }

    function goToEmployeeStep(step) {
        employeeWizardStep = Math.min(Math.max(step, 1), EMPLOYEE_WIZARD_LAST_STEP);
        updateEmployeeWizardUI();
    }

    function nextEmployeeStep() {
        if (!validateEmployeeStep(employeeWizardStep)) return;
        if (employeeWizardStep < EMPLOYEE_WIZARD_LAST_STEP) {
            goToEmployeeStep(employeeWizardStep + 1);
        }
    }

    function prevEmployeeStep() {
        if (employeeWizardStep > 1) {
            goToEmployeeStep(employeeWizardStep - 1);
        }
    }

    async function loadEmployeeClientOptions(selectedIds = []) {
        const list = document.getElementById('employeeClientList');
        const selected = (selectedIds || []).map(id => String(id));
        list.innerHTML = '<p class="client-picker-empty">Loading clients...</p>';
        try {
            const response = await fetch('{{ route("api.user-management.clients") }}');
            const data = await response.json();
            employeeClientsCache = data.data || [];
            if (employeeClientsCache.length === 0) {
                list.innerHTML = '<p class="client-picker-empty">No clients found. Add clients in Client Management first.</p>';
                return;
            }
            list.innerHTML = '';
            employeeClientsCache.forEach(client => {
                const label = document.createElement('label');
                label.className = 'client-picker-option';
                label.dataset.search = `${client.name} ${client.email || ''}`.toLowerCase();
                const isChecked = selected.includes(String(client.id));
                label.innerHTML = `
                    <input type="checkbox" name="client_ids[]" value="${client.id}" ${isChecked ? 'checked' : ''}>
                    <span>
                        ${escapeHtml(client.name)}
                        ${client.email ? `<span class="client-picker-email"> · ${escapeHtml(client.email)}</span>` : ''}
                    </span>
                `;
                list.appendChild(label);
            });
        } catch (error) {
            console.error('Error loading clients:', error);
            list.innerHTML = '<p class="client-picker-empty">Failed to load clients.</p>';
        }
    }

    function filterEmployeeClientOptions(term) {
        const needle = (term || '').toLowerCase().trim();
        document.querySelectorAll('#employeeClientList .client-picker-option').forEach(opt => {
            opt.style.display = opt.dataset.search.includes(needle) ? 'flex' : 'none';
        });
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    }

    function updateEmployeeRecordingSecondsHint() {
        const input = document.getElementById('employeeRecordingDuration');
        const hint = document.getElementById('employeeRecordingSecondsHint');
        if (!input || !hint) return;
        const minutes = parseFloat(input.value);
        if (isNaN(minutes) || minutes <= 0) {
            hint.textContent = '';
            return;
        }
        const seconds = Math.round(minutes * 60);
        hint.textContent = `= ${seconds} second${seconds === 1 ? '' : 's'}.`;
        hint.style.color = 'var(--accent)';
    }

    async function loadTwilioNumberOptions(employeeId = null, selectedVoice = '', selectedSms = '') {
        const voiceSelect = document.getElementById('employeeTwilioNumber');
        const smsSelect = document.getElementById('employeeTwilioSmsNumber');
        if (!voiceSelect || !smsSelect) return;

        const params = new URLSearchParams();
        if (employeeId) {
            params.set('employee_id', employeeId);
        }

        try {
            const response = await fetch(`{{ route('api.user-management.twilio-number-options') }}?${params}`);
            const result = await response.json();
            const numbers = (result.success && result.data) ? result.data : [];

            fillTwilioNumberSelect(voiceSelect, 'No phone system number', numbers, employeeId, selectedVoice, 'assigned_user_id');
            fillTwilioNumberSelect(smsSelect, 'No SMS number', numbers, employeeId, selectedSms, 'sms_assigned_user_id');
        } catch (error) {
            console.error('Error loading Twilio numbers:', error);
        }
    }

    function fillTwilioNumberSelect(select, emptyLabel, numbers, employeeId, selectedNumber, assignedKey) {
        select.innerHTML = `<option value="">${emptyLabel}</option>`;
        const seen = new Set();
        numbers.forEach(number => {
            if (seen.has(number.phone_number)) {
                return;
            }
            seen.add(number.phone_number);
            const option = document.createElement('option');
            option.value = number.phone_number;
            const users = assignedKey === 'sms_assigned_user_id'
                ? (number.sms_users || [])
                : (number.voice_users || []);
            const names = users.map((u) => u.name).filter(Boolean);
            option.textContent = number.friendly_name
                ? `${number.friendly_name} (${number.phone_number})`
                : number.phone_number;
            if (names.length) {
                option.textContent += ` — ${names.join(', ')}`;
            }
            select.appendChild(option);
        });
        if (selectedNumber && !seen.has(selectedNumber)) {
            const option = document.createElement('option');
            option.value = selectedNumber;
            option.textContent = selectedNumber;
            select.appendChild(option);
        }
        if (selectedNumber) {
            select.value = selectedNumber;
        }
    }

    // Employee management functions
    async function openEmployeeModal(employeeId = null) {
        const modal = document.getElementById('employeeModal');
        const form = document.getElementById('employeeForm');
        const title = document.getElementById('employeeModalTitle');
        const passwordInput = document.getElementById('employeePassword');
        const passwordRequired = document.getElementById('passwordRequired');
        const passwordHelp = document.getElementById('passwordHelp');
        
        // Reset form
        form.reset();
        document.getElementById('employeeId').value = '';
        
        // Load roles for dropdown
        try {
            const rolesResponse = await fetch('{{ route("api.user-management.roles") }}');
            const roles = await rolesResponse.json();
            const roleSelect = document.getElementById('employeeRole');
            roleSelect.innerHTML = '<option value="">Select a role...</option>';
            roles.forEach(role => {
                const option = document.createElement('option');
                option.value = role.id;
                option.textContent = role.name;
                roleSelect.appendChild(option);
            });
        } catch (error) {
            console.error('Error loading roles:', error);
        }

        // Load departments for dropdown
        try {
            const departmentsResponse = await fetch('{{ route("api.user-management.departments") }}');
            const departmentsData = await departmentsResponse.json();
            const departmentSelect = document.getElementById('employeeDepartment');
            departmentSelect.innerHTML = '<option value="">Select a department...</option>';
            if (departmentsData.success && departmentsData.data) {
                departmentsData.data.forEach(department => {
                    const option = document.createElement('option');
                    option.value = department.id;
                    option.textContent = department.name;
                    departmentSelect.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error loading departments:', error);
        }

        await populateEmployeeSalesRepSelect();

        // Reset wizard to first step and clear client search
        employeeWizardStep = 1;
        const clientSearch = document.getElementById('employeeClientSearch');
        if (clientSearch) clientSearch.value = '';

        if (employeeId) {
            // Edit mode
            title.textContent = 'Edit Employee';
            passwordInput.removeAttribute('required');
            passwordRequired.style.display = 'none';
            passwordHelp.style.display = 'block';
            
            try {
                const emp = employeesData.find(e => e.id === employeeId);
                if (emp) {
                    document.getElementById('employeeId').value = emp.id;
                    document.getElementById('employeeName').value = emp.name || '';
                    document.getElementById('employeeEmail').value = emp.email || '';
                    document.getElementById('employeePhone').value = emp.phone || '';
                    document.getElementById('employeeAddress').value = emp.address || '';
                    document.getElementById('employeeDateOfBirth').value = emp.date_of_birth || '';
                    document.getElementById('employeeEmploymentDate').value = emp.employment_date || '';
                    document.getElementById('employeeSalary').value = emp.salary || '0';
                    document.getElementById('employeeAllowances').value = emp.allowances ?? '0';
                    document.getElementById('employeeClientInvoiceAmount').value = emp.client_invoice_amount ?? '0';
                    document.getElementById('employeeRequiredWorkHours').value = emp.required_work_hours ?? '';
                    document.getElementById('employeeRecordingDuration').value = emp.recording_duration_minutes ?? '0.5';
                    updateEmployeeRecordingSecondsHint();
                    await loadTwilioNumberOptions(employeeId, emp.twilio_number || '', emp.twilio_sms_number || '');
                    document.getElementById('employeeWiseAccount').value = emp.wise_account || '';
                    document.getElementById('employeeDepartment').value = emp.department_id || '';
                    document.getElementById('employeeStatus').value = emp.status || 'active';
                    document.getElementById('employeeRole').value = emp.role_id || '';
                    document.getElementById('employeeSalesRepId').value = emp.sales_rep_id ? String(emp.sales_rep_id) : '';
                    document.getElementById('employeeSalesRepCommissionType').value = emp.sales_rep_commission_type === 'usd' ? 'usd' : 'percent';
                    document.getElementById('employeeSalesRepCommissionValue').value = emp.sales_rep_commission_value != null && emp.sales_rep_commission_value !== ''
                        ? String(emp.sales_rep_commission_value) : '';
                    toggleEmployeeSalesCommission();
                    
                    // Load photo if exists
                    if (emp.photo) {
                        const preview = document.getElementById('employeePhotoPreview');
                        preview.innerHTML = '';
                        const img = document.createElement('img');
                        img.src = emp.photo;
                        img.style.width = '100%';
                        img.style.height = '100%';
                        img.style.objectFit = 'cover';
                        preview.appendChild(img);
                    }

                    await loadEmployeeClientOptions(emp.client_ids || []);
                }
            } catch (error) {
                console.error('Error loading employee:', error);
                alert('Failed to load employee data');
                return;
            }
        } else {
            // Add mode
            title.textContent = 'Add New Employee';
            passwordInput.setAttribute('required', 'required');
            passwordRequired.style.display = 'inline';
            passwordHelp.style.display = 'none';
            
            // Reset photo preview
            const preview = document.getElementById('employeePhotoPreview');
            preview.innerHTML = '<span id="employeePhotoPlaceholder">No photo</span>';
            document.getElementById('employeeRecordingDuration').value = '0.5';
            updateEmployeeRecordingSecondsHint();
            document.getElementById('employeeSalesRepId').value = '';
            document.getElementById('employeeSalesRepCommissionType').value = 'percent';
            document.getElementById('employeeSalesRepCommissionValue').value = '';
            toggleEmployeeSalesCommission();
            await loadEmployeeClientOptions([]);
            await loadTwilioNumberOptions();
        }

        updateEmployeeWizardUI();
        modal.classList.add('active');
    }

    async function populateEmployeeSalesRepSelect() {
        const sel = document.getElementById('employeeSalesRepId');
        if (!sel) return;
        const previous = sel.value;
        sel.innerHTML = '<option value="">None</option>';
        try {
            const r = await fetch(`{{ route('api.user-management.sales-reps.index') }}?per_page=500`);
            const data = await r.json();
            (data.data || []).forEach(rep => {
                const opt = document.createElement('option');
                opt.value = rep.id;
                opt.textContent = `${rep.name} (${rep.email})`;
                sel.appendChild(opt);
            });
            if (previous) {
                sel.value = previous;
            }
        } catch (e) {
            console.error('Error loading sales reps for employee form:', e);
        }
    }

    function toggleEmployeeSalesCommission() {
        const sel = document.getElementById('employeeSalesRepId');
        const wrap = document.getElementById('employeeSalesRepCommissionWrap');
        if (!sel || !wrap) return;
        const on = !!sel.value;
        wrap.style.display = on ? 'block' : 'none';
        if (!on) {
            document.getElementById('employeeSalesRepCommissionType').value = 'percent';
            document.getElementById('employeeSalesRepCommissionValue').value = '';
        }
        updateEmployeeSalesRepCommissionHelp();
    }

    function updateEmployeeSalesRepCommissionHelp() {
        const help = document.getElementById('employeeSalesRepCommissionHelp');
        const typeEl = document.getElementById('employeeSalesRepCommissionType');
        if (!help || !typeEl) return;
        help.textContent = typeEl.value === 'usd'
            ? 'Fixed USD amount for this employee (per your policy).'
            : 'Percent of revenue for this employee (0–100).';
    }
    
    function closeEmployeeModal() {
        document.getElementById('employeeModal').classList.remove('active');
    }
    
    // Employee photo preview handler
    function handleEmployeePhotoPreview(input) {
        const preview = document.getElementById('employeePhotoPreview');
        const placeholder = document.getElementById('employeePhotoPlaceholder');
        
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.innerHTML = '';
                const img = document.createElement('img');
                img.src = e.target.result;
                img.style.width = '100%';
                img.style.height = '100%';
                img.style.objectFit = 'cover';
                preview.appendChild(img);
            };
            reader.readAsDataURL(input.files[0]);
        } else {
            if (!preview.querySelector('img')) {
                preview.innerHTML = '<span id="employeePhotoPlaceholder" style="color: var(--text-muted); font-size: 0.75rem; text-align: center; padding: 0.5rem;">No photo</span>';
            }
        }
    }
    
    async function saveEmployee() {
        const form = document.getElementById('employeeForm');
        const formData = new FormData(form);
        const employeeId = formData.get('id');
        
        // Validate required fields
        const name = document.getElementById('employeeName');
        const email = document.getElementById('employeeEmail');
        const password = document.getElementById('employeePassword');
        const salary = document.getElementById('employeeSalary');
        const allowances = document.getElementById('employeeAllowances');
        const roleId = document.getElementById('employeeRole');
        const status = document.getElementById('employeeStatus');
        
        // Check HTML5 validation - jump to the step holding the first invalid field
        const firstInvalid = form.querySelector(':invalid');
        if (firstInvalid) {
            focusEmployeeField(firstInvalid);
            firstInvalid.reportValidity();
            return;
        }
        
        // Manual validation for required fields
        if (!name.value || name.value.trim() === '') {
            alert('Full Name is required');
            focusEmployeeField(name);
            return;
        }
        
        if (!email.value || email.value.trim() === '') {
            alert('Email Address is required');
            focusEmployeeField(email);
            return;
        }
        
        // Email format validation
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email.value)) {
            alert('Please enter a valid email address');
            focusEmployeeField(email);
            return;
        }
        
        // Password required when creating new employee
        if (!employeeId && (!password.value || password.value.trim() === '')) {
            alert('Password is required when creating a new employee');
            focusEmployeeField(password);
            return;
        }
        
        if (!salary.value || salary.value.trim() === '' || parseFloat(salary.value) < 0) {
            alert('Salary is required and must be 0 or greater');
            focusEmployeeField(salary);
            return;
        }
        
        if (!allowances.value || allowances.value.trim() === '' || parseFloat(allowances.value) < 0) {
            alert('Allowances is required and must be 0 or greater');
            focusEmployeeField(allowances);
            return;
        }

        const requiredWorkHours = document.getElementById('employeeRequiredWorkHours');
        if (!requiredWorkHours.value || requiredWorkHours.value.trim() === '' || parseFloat(requiredWorkHours.value) < 0) {
            alert('Required work hours is required and must be 0 or greater');
            focusEmployeeField(requiredWorkHours);
            return;
        }
        
        if (!roleId.value || roleId.value.trim() === '') {
            alert('Role is required');
            focusEmployeeField(roleId);
            return;
        }
        
        if (!status.value || status.value.trim() === '') {
            alert('Status is required');
            focusEmployeeField(status);
            return;
        }

        const srId = document.getElementById('employeeSalesRepId')?.value?.trim() || '';
        const srCommType = document.getElementById('employeeSalesRepCommissionType')?.value;
        const srCommVal = document.getElementById('employeeSalesRepCommissionValue')?.value;
        if (srId) {
            if (srCommVal === '' || srCommVal === null || parseFloat(srCommVal) < 0) {
                alert('Enter a commission value for this employee when a sales rep is selected');
                focusEmployeeField(document.getElementById('employeeSalesRepCommissionValue'));
                return;
            }
            if (srCommType === 'percent' && parseFloat(srCommVal) > 100) {
                alert('Commission percentage cannot exceed 100');
                focusEmployeeField(document.getElementById('employeeSalesRepCommissionValue'));
                return;
            }
        }

        // Build FormData for file upload support
        const submitFormData = new FormData();
        
        // Add all form fields
        submitFormData.append('name', formData.get('name') || '');
        submitFormData.append('email', formData.get('email') || '');
        
        // Password handling: only send if it has a value
        // For new employees, password is required (already validated above)
        // For editing, if password is empty, don't send it (preserves existing password)
        const passwordValue = formData.get('password');
        if (passwordValue && passwordValue.trim() !== '') {
            submitFormData.append('password', passwordValue);
        } else if (!employeeId) {
            // New employee without password shouldn't happen (validation already caught this)
            // But if we reach here, send empty to trigger backend validation error
            submitFormData.append('password', '');
        }
        // For editing existing employees: if password is empty, don't append it (backend will preserve existing password)
        
        submitFormData.append('phone', formData.get('phone') || '');
        submitFormData.append('address', formData.get('address') || '');
        submitFormData.append('date_of_birth', formData.get('date_of_birth') || '');
        submitFormData.append('employment_date', formData.get('employment_date') || '');
        submitFormData.append('salary', formData.get('salary') || '0');
        submitFormData.append('allowances', formData.get('allowances') || '0');
        submitFormData.append('client_invoice_amount', formData.get('client_invoice_amount') ?? '0');
        submitFormData.append('required_work_hours', formData.get('required_work_hours') || '');
        submitFormData.append('recording_duration_minutes', formData.get('recording_duration_minutes') || '0.5');
        submitFormData.append('twilio_number', formData.get('twilio_number') || '');
        submitFormData.append('twilio_sms_number', formData.get('twilio_sms_number') || '');
        submitFormData.append('wise_account', formData.get('wise_account') || '');
        submitFormData.append('department_id', formData.get('department_id') || '');
        submitFormData.append('role_id', formData.get('role_id') || '');
        submitFormData.append('status', formData.get('status') || 'active');
        submitFormData.append('sales_rep_id', srId);
        if (srId) {
            submitFormData.append('sales_rep_commission_type', srCommType || 'percent');
            submitFormData.append('sales_rep_commission_value', srCommVal);
        }

        // Assigned clients (flag so the backend syncs even when none are selected)
        submitFormData.append('clients_submitted', '1');
        document.querySelectorAll('#employeeClientList input[name="client_ids[]"]:checked')
            .forEach(cb => submitFormData.append('client_ids[]', cb.value));
        
        // Append photo if selected
        const photoInput = document.getElementById('employeePhoto');
        if (photoInput.files && photoInput.files[0]) {
            submitFormData.append('photo', photoInput.files[0]);
        }
        
        try {
            const url = employeeId
                ? `/api/user-management/employees/${employeeId}`
                : `/api/user-management/employees`;
            
            // Use POST for file uploads, add _method for updates
            if (employeeId) {
                submitFormData.append('_method', 'PUT');
            }
            
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'application/json'
                },
                body: submitFormData
            });
            
            const result = await response.json();
            
            if (result.success) {
                alert(employeeId ? 'Employee updated successfully!' : 'Employee created successfully!');
                closeEmployeeModal();
                // Reload employees to show updated avatar
                if (employeeId) {
                    loadEmployees(employeesMeta.currentPage, currentSearchTerm);
                } else {
                    loadEmployees(1, currentSearchTerm);
                }
                if (document.getElementById('salesRepsTab')?.classList.contains('active')) {
                    loadSalesReps(salesRepsMeta.currentPage || 1);
                }
            } else {
                const errors = result.errors ? Object.values(result.errors).flat().join('\n') : (result.message || 'Failed to save employee');
                alert(errors);
            }
        } catch (error) {
            console.error('Error saving employee:', error);
            alert('Failed to save employee. Please try again.');
        }
    }

    function viewEmployee(employeeId) {
        // Open in edit mode for viewing
        openEmployeeModal(employeeId);
    }

    function editEmployee(employeeId) {
        openEmployeeModal(employeeId);
    }
    
    // Permission search filter
    function filterPermissions(searchTerm) {
        const term = searchTerm.toLowerCase();
        document.querySelectorAll('.permission-item').forEach(item => {
            const text = item.textContent.toLowerCase();
            const group = item.closest('.permission-group');
            if (text.includes(term)) {
                item.style.display = 'flex';
                group.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
        
        // Hide empty groups
        document.querySelectorAll('.permission-group').forEach(group => {
            const visibleItems = group.querySelectorAll('.permission-item[style*="display: flex"]');
            if (visibleItems.length === 0 && searchTerm) {
                group.style.display = 'none';
            }
        });
    }

    async function deleteEmployee(employeeId) {
        if (!confirm('Are you sure you want to delete this employee?')) return;
        
        try {
            const response = await fetch(`/api/user-management/employees/${employeeId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Content-Type': 'application/json'
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                alert('Employee deleted successfully');
                loadEmployees(employeesMeta.currentPage, currentSearchTerm);
            } else {
                alert(data.message || 'Failed to delete employee');
            }
        } catch (error) {
            console.error('Error deleting employee:', error);
            alert('Failed to delete employee');
        }
    }

    // Logo preview handler
    function handleLogoPreview(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('logoPreview');
                const placeholder = document.getElementById('logoPlaceholder');
                const text = document.getElementById('logoText');
                const existingImg = document.getElementById('logoImage');
                
                if (existingImg) {
                    existingImg.src = e.target.result;
                } else {
                    if (placeholder) placeholder.style.display = 'none';
                    if (text) text.style.display = 'none';
                    const img = document.createElement('img');
                    img.id = 'logoImage';
                    img.src = e.target.result;
                    img.alt = 'Company Logo';
                    img.style.maxWidth = '100%';
                    img.style.maxHeight = '100%';
                    img.style.objectFit = 'contain';
                    preview.appendChild(img);
                }
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    async function removeLogo() {
        if (!confirm('Are you sure you want to remove the company logo?')) return;
        
        try {
            const formData = new FormData();
            formData.append('remove_logo', '1');
            
            const response = await fetch('{{ route("api.user-management.company.settings.update") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'application/json'
                },
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                // Remove preview
                const preview = document.getElementById('logoPreview');
                const img = document.getElementById('logoImage');
                const placeholder = document.getElementById('logoPlaceholder');
                const text = document.getElementById('logoText');
                
                if (img) {
                    img.remove();
                }
                // Create placeholder if doesn't exist
                if (!placeholder) {
                    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                    svg.id = 'logoPlaceholder';
                    svg.setAttribute('viewBox', '0 0 24 24');
                    svg.setAttribute('fill', 'none');
                    svg.setAttribute('stroke', 'currentColor');
                    svg.setAttribute('stroke-width', '2');
                    svg.innerHTML = '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>';
                    preview.appendChild(svg);
                    
                    const span = document.createElement('span');
                    span.id = 'logoText';
                    span.textContent = 'No logo uploaded';
                    preview.appendChild(span);
                } else {
                    placeholder.style.display = 'block';
                    if (text) text.style.display = 'block';
                }
                
                // Remove remove button
                const removeBtn = document.querySelector('button[onclick="removeLogo()"]');
                if (removeBtn) removeBtn.remove();
                
                document.getElementById('logoInput').value = '';
                alert('Logo removed successfully!');
                location.reload();
            } else {
                alert(data.message || 'Failed to remove logo');
            }
        } catch (error) {
            console.error('Error removing logo:', error);
            alert('Failed to remove logo');
        }
    }

    // Company settings functions
    async function saveCompanySettings() {
        // Validate required fields
        const companyName = document.getElementById('companyName');
        const companyEmail = document.getElementById('companyEmail');
        
        // Check HTML5 validation first
        const companyForm = document.querySelector('#companyTab form') || companyName.closest('.form-container');
        if (companyForm && companyForm.checkValidity && !companyForm.checkValidity()) {
            // Try to find the form element or use individual field validation
            if (companyName.closest('form')) {
                companyName.closest('form').reportValidity();
            } else {
                companyName.reportValidity();
                companyEmail.reportValidity();
            }
            return;
        }
        
        // Manual validation for required fields
        if (!companyName.value || companyName.value.trim() === '') {
            alert('Company Name is required');
            companyName.focus();
            return;
        }
        
        if (!companyEmail.value || companyEmail.value.trim() === '') {
            alert('Company Email is required');
            companyEmail.focus();
            return;
        }
        
        // Email format validation
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(companyEmail.value)) {
            alert('Please enter a valid email address');
            companyEmail.focus();
            return;
        }
        
        const formData = new FormData();
        formData.append('name', companyName.value);
        formData.append('email', companyEmail.value);
        formData.append('phone', document.getElementById('companyPhone').value);
        formData.append('website', document.getElementById('companyWebsite').value);
        formData.append('address', document.getElementById('companyAddress').value);
        formData.append('timezone', document.getElementById('timezone').value);
        formData.append('date_format', document.getElementById('dateFormat').value);
        formData.append('currency', document.getElementById('currency').value);
        formData.append('language', document.getElementById('language').value);
        
        // Append logo if selected
        const logoInput = document.getElementById('logoInput');
        if (logoInput.files && logoInput.files[0]) {
            formData.append('logo', logoInput.files[0]);
        }

        try {
            const response = await fetch('{{ route("api.user-management.company.settings.update") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'application/json'
                },
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                alert('Company settings saved successfully!');
                // Reload to show new logo
                if (logoInput.files && logoInput.files[0]) {
                    location.reload();
                }
            } else {
                alert(data.message || 'Failed to save company settings');
            }
        } catch (error) {
            console.error('Error saving company settings:', error);
            alert('Failed to save company settings');
        }
    }

    function resetCompanyForm() {
        if (confirm('Are you sure you want to reset the form? All changes will be lost.')) {
            location.reload();
        }
    }

    // RBAC functions
    let selectedRoleId = null;
    let allSidebarPermissions = {};
    let totalPermissions = 0;

    document.getElementById('roleSelect')?.addEventListener('change', async function() {
        selectedRoleId = this.value;
        const emptyState = document.getElementById('rbacEmptyState');
        const contentWrapper = document.getElementById('rbacContentWrapper');
        const stats = document.getElementById('rbacStats');
        
        if (selectedRoleId) {
            emptyState.style.display = 'none';
            contentWrapper.style.display = 'flex';
            stats.style.display = 'flex';
            await loadSidebarPermissions();
            await loadRolePermissions(selectedRoleId);
            updateStats();
        } else {
            emptyState.style.display = 'flex';
            contentWrapper.style.display = 'none';
            stats.style.display = 'none';
            resetRolePermissions();
        }
    });

    // Restore selected role after page reload
    // Wait for the page to be fully loaded and role select to be populated
    let restoreAttempts = 0;
    const maxRestoreAttempts = 20; // Try for up to 4 seconds (20 * 200ms)
    
    function restoreSelectedRole() {
        const storedRoleId = sessionStorage.getItem('selectedRoleId');
        if (!storedRoleId) {
            return false;
        }

        if (restoreAttempts >= maxRestoreAttempts) {
            console.warn('Could not restore selected role after multiple attempts');
            sessionStorage.removeItem('selectedRoleId');
            return false;
        }

        restoreAttempts++;

        // First, switch to the RBAC tab
        const rbacTabBtn = document.querySelector('.tab-btn[data-tab="rbac"]');
        if (rbacTabBtn && !rbacTabBtn.classList.contains('active')) {
            // Trigger click to switch tab
            rbacTabBtn.click();
        }

        const roleSelect = document.getElementById('roleSelect');
        if (!roleSelect || roleSelect.options.length === 0) {
            // If roleSelect doesn't exist yet or has no options, try again after a short delay
            setTimeout(restoreSelectedRole, 200);
            return false;
        }

        // Check if the option exists in the select
        const optionExists = Array.from(roleSelect.options).some(option => option.value === storedRoleId);
        if (optionExists) {
            roleSelect.value = storedRoleId;
            // Use a small delay before triggering change to ensure tab is active
            setTimeout(() => {
                // Trigger change event to load permissions
                const changeEvent = new Event('change', { bubbles: true });
                roleSelect.dispatchEvent(changeEvent);
                // Clear the stored role ID after restoring
                sessionStorage.removeItem('selectedRoleId');
                restoreAttempts = 0; // Reset attempts
            }, 100);
            return true;
        } else {
            // If option doesn't exist yet, try again after a short delay
            setTimeout(restoreSelectedRole, 200);
            return false;
        }
    }

    // Try to restore after page is fully loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(restoreSelectedRole, 500);
        });
    } else {
        setTimeout(restoreSelectedRole, 500);
    }

    async function loadSidebarPermissions() {
        try {
            const response = await fetch('{{ route("api.user-management.permissions") }}');
            const data = await response.json();
            allSidebarPermissions = data;
            
            // Count total permissions
            totalPermissions = 0;
            for (const permissions of Object.values(data)) {
                if (Array.isArray(permissions)) {
                    totalPermissions += permissions.length;
                }
            }
            
            // Render permissions container
            const container = document.getElementById('permissionsGrid');
            container.innerHTML = '';
            
            // Module icon (generic folder icon)
            const moduleIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>';
            
            for (const [moduleName, permissions] of Object.entries(data)) {
                if (permissions && permissions.length > 0) {
                    const moduleDiv = document.createElement('div');
                    moduleDiv.className = 'permission-category';
                    moduleDiv.dataset.module = moduleName;
                    
                    // Module header
                    const headerDiv = document.createElement('div');
                    headerDiv.className = 'permission-category-header';
                    
                    const titleDiv = document.createElement('div');
                    titleDiv.className = 'permission-category-title';
                    titleDiv.innerHTML = moduleIcon;
                    titleDiv.innerHTML += moduleName;
                    
                    const actionsDiv = document.createElement('div');
                    actionsDiv.className = 'permission-category-actions';
                    
                    const selectAllBtn = document.createElement('button');
                    selectAllBtn.type = 'button';
                    selectAllBtn.className = 'btn-category-action';
                    selectAllBtn.textContent = 'Select All';
                    selectAllBtn.onclick = () => selectModulePermissions(moduleName, true);
                    
                    const deselectAllBtn = document.createElement('button');
                    deselectAllBtn.type = 'button';
                    deselectAllBtn.className = 'btn-category-action';
                    deselectAllBtn.textContent = 'Deselect All';
                    deselectAllBtn.onclick = () => selectModulePermissions(moduleName, false);
                    
                    actionsDiv.appendChild(selectAllBtn);
                    actionsDiv.appendChild(deselectAllBtn);
                    headerDiv.appendChild(titleDiv);
                    headerDiv.appendChild(actionsDiv);
                    
                    // Permission group
                    const groupDiv = document.createElement('div');
                    groupDiv.className = 'permission-group';
                    
                    // For Payroll module, organize permissions into sub-categories
                    if (moduleName === 'Payroll') {
                        // Define sub-categories for Payroll
                        const payrollSubCategories = {
                            'Time In/Out': ['view_time_in_out', 'edit_time_in_out', 'export_time_in_out'],
                            'Payroll Report': [
                                'view_payroll_report',
                                'view_payroll_sales_rep_report',
                                'generate_payroll_report',
                                'export_payroll_report',
                            ],
                            'Saved for Wise': ['view_saved_for_wise'],
                            'Wise Recipients': ['view_wise_recipients'],
                        };
                        
                        // Add main view_payroll permission first
                        const viewPayroll = permissions.find(p => p.slug === 'view_payroll');
                        if (viewPayroll) {
                            const label = createPermissionItem(viewPayroll, moduleName);
                            groupDiv.appendChild(label);
                        }
                        
                        // Add sub-categories
                        for (const [subCategoryName, slugs] of Object.entries(payrollSubCategories)) {
                            const subCategoryPerms = permissions.filter(p => slugs.includes(p.slug));
                            if (subCategoryPerms.length > 0) {
                                // Sub-category title
                                const subCategoryTitle = document.createElement('div');
                                subCategoryTitle.className = 'permission-group-title';
                                subCategoryTitle.style.marginTop = '1rem';
                                subCategoryTitle.style.marginBottom = '0.5rem';
                                subCategoryTitle.textContent = subCategoryName;
                                groupDiv.appendChild(subCategoryTitle);
                                
                                // Sub-category permissions list
                                const subCategoryList = document.createElement('div');
                                subCategoryList.className = 'permission-list';
                                subCategoryList.style.marginLeft = '1rem';
                                
                                subCategoryPerms.forEach(perm => {
                                    const label = createPermissionItem(perm, moduleName);
                                    subCategoryList.appendChild(label);
                                });
                                
                                groupDiv.appendChild(subCategoryList);
                            }
                        }
                        
                        // Add any remaining permissions (shouldn't happen, but just in case)
                        const remainingPerms = permissions.filter(p => 
                            p.slug !== 'view_payroll' && 
                            !Object.values(payrollSubCategories).flat().includes(p.slug)
                        );
                        if (remainingPerms.length > 0) {
                            const orphanList = document.createElement('div');
                            orphanList.className = 'permission-list';
                            orphanList.style.marginTop = '0.75rem';
                            remainingPerms.forEach(perm => {
                                orphanList.appendChild(createPermissionItem(perm, moduleName));
                            });
                            groupDiv.appendChild(orphanList);
                        }
                    } else if (moduleName === 'Leave Management') {
                        // For Leave Management module, organize permissions into sub-categories
                        const leaveSubCategories = {
                            'Access': ['view_leave_management'],
                            'Statistics': ['view_leave_stats'],
                            'Requests': ['create_leave_request'],
                            'Credits': ['view_leave_credits', 'manage_leave_credits'],
                            'Calendar': ['view_leave_calendar'],
                        };
                        
                        // Add sub-categories
                        for (const [subCategoryName, slugs] of Object.entries(leaveSubCategories)) {
                            const subCategoryPerms = permissions.filter(p => slugs.includes(p.slug));
                            if (subCategoryPerms.length > 0) {
                                // Sub-category title
                                const subCategoryTitle = document.createElement('div');
                                subCategoryTitle.className = 'permission-group-title';
                                subCategoryTitle.style.marginTop = '1rem';
                                subCategoryTitle.style.marginBottom = '0.5rem';
                                subCategoryTitle.textContent = subCategoryName;
                                groupDiv.appendChild(subCategoryTitle);
                                
                                // Sub-category permissions list
                                const subCategoryList = document.createElement('div');
                                subCategoryList.className = 'permission-list';
                                subCategoryList.style.marginLeft = '1rem';
                                
                                subCategoryPerms.forEach(perm => {
                                    const label = createPermissionItem(perm, moduleName);
                                    subCategoryList.appendChild(label);
                                });
                                
                                groupDiv.appendChild(subCategoryList);
                            }
                        }
                        
                        // Add any remaining permissions that don't match sub-categories
                        const allSubCategorySlugs = Object.values(leaveSubCategories).flat();
                        const remainingPerms = permissions.filter(p => 
                            !allSubCategorySlugs.includes(p.slug)
                        );
                        if (remainingPerms.length > 0) {
                            remainingPerms.forEach(perm => {
                                const label = createPermissionItem(perm, moduleName);
                                groupDiv.appendChild(label);
                            });
                        }
                    } else {
                        // For other modules, use the standard flat list
                        const listDiv = document.createElement('div');
                        listDiv.className = 'permission-list';
                        listDiv.dataset.module = moduleName;
                        
                        permissions.forEach(perm => {
                            const label = createPermissionItem(perm, moduleName);
                            listDiv.appendChild(label);
                        });
                        
                        groupDiv.appendChild(listDiv);
                    }
                    
                    moduleDiv.appendChild(headerDiv);
                    moduleDiv.appendChild(groupDiv);
                    container.appendChild(moduleDiv);
                }
            }
            
            if (container.innerHTML === '') {
                container.innerHTML = '<div class="empty-state"><p>No sidebar permissions found. Please run the permissions seeder.</p></div>';
            }
            
            document.getElementById('totalCount').textContent = totalPermissions;
        } catch (error) {
            console.error('Error loading sidebar permissions:', error);
            document.getElementById('permissionsGrid').innerHTML = '<div class="empty-state"><p>Failed to load permissions. Please try again.</p></div>';
        }
    }
    
    // Helper function to create permission item
    function createPermissionItem(perm, moduleName) {
        const label = document.createElement('label');
        label.className = 'permission-item';
        label.onclick = (e) => {
            if (e.target.type !== 'checkbox') {
                e.preventDefault();
                const checkbox = label.querySelector('input[type="checkbox"]');
                checkbox.checked = !checkbox.checked;
                checkbox.dispatchEvent(new Event('change'));
            }
        };
        
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.className = 'permission-checkbox';
        checkbox.dataset.permissionId = perm.id;
        checkbox.dataset.permissionSlug = perm.slug;
        checkbox.dataset.permissionName = perm.display_name || perm.name;
        checkbox.dataset.module = moduleName;
        checkbox.dataset.category = perm.category || 'other';
        checkbox.addEventListener('change', function() {
            label.classList.toggle('checked', this.checked);
            updateStats();
            
            // Automatically check view_payroll if any payroll sub-module permission is checked
            const payrollSubModuleSlugs = [
                'view_time_in_out',
                'edit_time_in_out',
                'export_time_in_out',
                'view_payroll_report',
                'generate_payroll_report',
                'export_payroll_report',
                'view_saved_for_wise',
                'view_wise_recipients',
            ];
            
            if (payrollSubModuleSlugs.includes(perm.slug) && this.checked) {
                // Find and check the view_payroll checkbox
                const viewPayrollCheckbox = document.querySelector('.permission-checkbox[data-permission-slug="view_payroll"]');
                if (viewPayrollCheckbox && !viewPayrollCheckbox.checked) {
                    viewPayrollCheckbox.checked = true;
                    const viewPayrollLabel = viewPayrollCheckbox.closest('.permission-item');
                    if (viewPayrollLabel) {
                        viewPayrollLabel.classList.add('checked');
                    }
                    updateStats();
                }
            }

            // Automatically check view_user_management if any user management sub-module permission is checked
            const userManagementSubModuleSlugs = [
                'view_user_roles_permissions',
                'view_user_company_setup',
                'view_user_employee_profile',
                'view_user_departments',
                'view_user_role_based_access',
            ];
            
            if (userManagementSubModuleSlugs.includes(perm.slug) && this.checked) {
                // Find and check the view_user_management checkbox
                const viewUserManagementCheckbox = document.querySelector('.permission-checkbox[data-permission-slug="view_user_management"]');
                if (viewUserManagementCheckbox && !viewUserManagementCheckbox.checked) {
                    viewUserManagementCheckbox.checked = true;
                    const viewUserManagementLabel = viewUserManagementCheckbox.closest('.permission-item');
                    if (viewUserManagementLabel) {
                        viewUserManagementLabel.classList.add('checked');
                    }
                    updateStats();
                }
            }
        });
        
        const span = document.createElement('span');
        span.className = 'permission-item-label';
        span.textContent = perm.display_name || perm.name;
        
        label.appendChild(checkbox);
        label.appendChild(span);
        
        return label;
    }

    function selectModulePermissions(moduleName, select) {
        const checkboxes = document.querySelectorAll(`.permission-checkbox[data-module="${moduleName}"]`);
        checkboxes.forEach(cb => {
            cb.checked = select;
            const label = cb.closest('.permission-item');
            if (label) {
                label.classList.toggle('checked', select);
            }
        });
        updateStats();
    }

    function selectCategoryPermissions(category, select) {
        const checkboxes = document.querySelectorAll(`.permission-checkbox[data-category="${category}"]`);
        checkboxes.forEach(cb => {
            cb.checked = select;
            const label = cb.closest('.permission-item');
            if (label) {
                label.classList.toggle('checked', select);
            }
        });
        updateStats();
    }

    function selectModulePermissions(moduleName, select) {
        const checkboxes = document.querySelectorAll(`.permission-checkbox[data-module="${moduleName}"]`);
        checkboxes.forEach(cb => {
            cb.checked = select;
            const label = cb.closest('.permission-item');
            if (label) {
                label.classList.toggle('checked', select);
            }
        });
        updateStats();
    }

    function selectCategoryPermissions(category, select) {
        const checkboxes = document.querySelectorAll(`.permission-checkbox[data-category="${category}"]`);
        checkboxes.forEach(cb => {
            cb.checked = select;
            const label = cb.closest('.permission-item');
            if (label) {
                label.classList.toggle('checked', select);
            }
        });
        updateStats();
    }

    function updateStats() {
        const checked = document.querySelectorAll('.permission-checkbox:checked').length;
        document.getElementById('selectedCount').textContent = checked;
    }

    async function loadRolePermissions(roleId) {
        try {
            const response = await fetch(`/api/user-management/roles/${roleId}/permissions`);
            const data = await response.json();

            // Uncheck all permissions first
            document.querySelectorAll('.permission-checkbox').forEach(cb => {
                cb.checked = false;
                const label = cb.closest('.permission-item');
                if (label) {
                    label.classList.remove('checked');
                }
            });

            // Check permissions for this role
            if (data.permissions) {
                data.permissions.forEach(perm => {
                    const checkbox = document.querySelector(`.permission-checkbox[data-permission-id="${perm.id}"]`);
                    if (checkbox) {
                        checkbox.checked = true;
                        const label = checkbox.closest('.permission-item');
                        if (label) {
                            label.classList.add('checked');
                        }
                    }
                });
            }
            updateStats();
        } catch (error) {
            console.error('Error loading role permissions:', error);
        }
    }
    
    function selectAllPermissions() {
        document.querySelectorAll('.permission-checkbox').forEach(cb => {
            cb.checked = true;
            const label = cb.closest('.permission-item');
            if (label) {
                label.classList.add('checked');
            }
        });
        updateStats();
    }
    
    function deselectAllPermissions() {
        document.querySelectorAll('.permission-checkbox').forEach(cb => {
            cb.checked = false;
            const label = cb.closest('.permission-item');
            if (label) {
                label.classList.remove('checked');
            }
        });
        updateStats();
    }

    function resetRolePermissions() {
        if (selectedRoleId) {
            loadRolePermissions(selectedRoleId);
        } else {
            deselectAllPermissions();
        }
    }

    async function saveRolePermissions() {
        const roleId = document.getElementById('roleSelect').value;
        if (!roleId) {
            alert('Please select a role first');
            return;
        }

        const permissionIds = Array.from(document.querySelectorAll('.permission-checkbox:checked'))
            .map(cb => parseInt(cb.dataset.permissionId));

        try {
            const response = await fetch(`/api/user-management/roles/${roleId}/permissions`, {
                method: 'PUT',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ permission_ids: permissionIds })
            });

            const data = await response.json();

            if (data.success) {
                // Store the selected role ID in sessionStorage before reloading
                sessionStorage.setItem('selectedRoleId', roleId);
                alert('Permissions saved successfully!');
                location.reload();
            } else {
                alert(data.message || 'Failed to save permissions');
            }
        } catch (error) {
            console.error('Error saving permissions:', error);
            alert('Failed to save permissions');
        }
    }

    // Employee Data - Loaded from API
    let employeesData = [];
    let employeesMeta = {
        currentPage: 1,
        lastPage: 1,
        perPage: 10,
        total: 0
    };
    let currentSearchTerm = '';

    let salesRepsData = [];
    let salesRepsMeta = { currentPage: 1, lastPage: 1, perPage: 10, total: 0 };

    function formatEmployeeSalesCommission(emp) {
        if (!emp || !emp.sales_rep_id || !emp.sales_rep_commission_type) return '';
        if (emp.sales_rep_commission_type === 'usd') {
            return '$' + parseFloat(emp.sales_rep_commission_value || 0).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 }) + ' USD';
        }
        return parseFloat(emp.sales_rep_commission_value || 0).toLocaleString(undefined, { maximumFractionDigits: 2 }) + '%';
    }

    async function loadSalesReps(page = 1) {
        try {
            const url = `{{ route('api.user-management.sales-reps.index') }}?page=${page}&per_page=${salesRepsMeta.perPage}`;
            const response = await fetch(url);
            const data = await response.json();
            salesRepsData = data.data || [];
            salesRepsMeta = {
                currentPage: data.current_page || 1,
                lastPage: data.last_page || 1,
                perPage: data.per_page || 10,
                total: data.total || 0,
            };
            renderSalesRepsTable();
            renderSalesRepsPagination();
        } catch (error) {
            console.error('Error loading sales reps:', error);
            alert('Failed to load sales reps.');
        }
    }

    function renderSalesRepsTable() {
        const tbody = document.getElementById('salesRepsTableBody');
        if (!tbody) return;
        if (!salesRepsData.length) {
            tbody.innerHTML = '<tr><td colspan="4" class="empty-state" style="padding: 2rem;">No sales reps yet. Add contacts here, then assign them and set commission on each employee.</td></tr>';
            return;
        }
        tbody.innerHTML = salesRepsData.map(rep => `
            <tr>
                <td>
                    <div class="employee-cell">
                        <div class="employee-avatar">${rep.initials || getInitials(rep.name)}</div>
                        <div>
                            <div class="employee-name">${rep.name}</div>
                            <div class="employee-id">ID: SR-${String(rep.id).padStart(4, '0')}</div>
                        </div>
                    </div>
                </td>
                <td>${rep.email}</td>
                <td>${rep.phone ? rep.phone : '—'}</td>
                <td>
                    <div class="table-actions">
                        <button type="button" class="icon-btn" title="Edit" onclick="openSalesRepModal(${rep.id})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                            </svg>
                        </button>
                        <button type="button" class="icon-btn" title="Delete" onclick="deleteSalesRepRecord(${rep.id})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"/>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                            </svg>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    function renderSalesRepsPagination() {
        const info = document.getElementById('salesRepsPaginationInfo');
        const numbers = document.getElementById('salesRepsPaginationNumbers');
        const prevBtn = document.getElementById('salesRepsPrevBtn');
        const nextBtn = document.getElementById('salesRepsNextBtn');
        if (!info || !numbers || !prevBtn || !nextBtn) return;

        const start = salesRepsMeta.total > 0 ? ((salesRepsMeta.currentPage - 1) * salesRepsMeta.perPage) + 1 : 0;
        const end = Math.min(salesRepsMeta.currentPage * salesRepsMeta.perPage, salesRepsMeta.total);
        info.textContent = salesRepsMeta.total > 0
            ? `Showing ${start} to ${end} of ${salesRepsMeta.total} results`
            : 'No sales reps';

        prevBtn.disabled = salesRepsMeta.currentPage <= 1;
        nextBtn.disabled = salesRepsMeta.currentPage >= salesRepsMeta.lastPage;

        let html = '';
        const maxVisible = 5;
        const currentPage = salesRepsMeta.currentPage;
        const totalPages = salesRepsMeta.lastPage;
        let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
        let endPage = Math.min(totalPages, startPage + maxVisible - 1);
        if (endPage - startPage < maxVisible - 1) {
            startPage = Math.max(1, endPage - maxVisible + 1);
        }
        if (startPage > 1) {
            html += `<button type="button" class="pagination-number" data-sr-page="1">1</button>`;
            if (startPage > 2) {
                html += `<span class="pagination-number ellipsis">...</span>`;
            }
        }
        for (let i = startPage; i <= endPage; i++) {
            html += `<button type="button" class="pagination-number ${i === currentPage ? 'active' : ''}" data-sr-page="${i}">${i}</button>`;
        }
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                html += `<span class="pagination-number ellipsis">...</span>`;
            }
            html += `<button type="button" class="pagination-number" data-sr-page="${totalPages}">${totalPages}</button>`;
        }
        numbers.innerHTML = html;
        numbers.querySelectorAll('[data-sr-page]').forEach(btn => {
            btn.addEventListener('click', () => loadSalesReps(parseInt(btn.getAttribute('data-sr-page'), 10)));
        });
    }

    function openSalesRepModal(repId = null) {
        const modal = document.getElementById('salesRepModal');
        const title = document.getElementById('salesRepModalTitle');
        const idField = document.getElementById('salesRepRecordId');
        document.getElementById('salesRepName').value = '';
        document.getElementById('salesRepEmail').value = '';
        document.getElementById('salesRepPhone').value = '';
        idField.value = '';

        if (repId) {
            title.textContent = 'Edit sales rep';
            idField.value = String(repId);
            const rep = salesRepsData.find(r => r.id === repId);
            if (rep) {
                document.getElementById('salesRepName').value = rep.name || '';
                document.getElementById('salesRepEmail').value = rep.email || '';
                document.getElementById('salesRepPhone').value = rep.phone || '';
            }
        } else {
            title.textContent = 'Add sales rep';
        }
        modal.classList.add('active');
    }

    function closeSalesRepModal() {
        document.getElementById('salesRepModal')?.classList.remove('active');
    }

    async function saveSalesRepRecord() {
        const name = document.getElementById('salesRepName').value.trim();
        const email = document.getElementById('salesRepEmail').value.trim();
        const phone = document.getElementById('salesRepPhone').value.trim();
        if (!name || !email) {
            alert('Name and email are required');
            return;
        }
        const id = document.getElementById('salesRepRecordId').value;
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const body = {
            name,
            email,
            phone: phone || null,
        };
        try {
            const url = id
                ? `{{ url('/api/user-management/sales-reps') }}/${id}`
                : "{{ route('api.user-management.sales-reps.store') }}";
            const response = await fetch(url, {
                method: id ? 'PUT' : 'POST',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(body),
            });
            const result = await response.json();
            if (result.success) {
                alert(id ? 'Sales rep updated.' : 'Sales rep created.');
                closeSalesRepModal();
                loadSalesReps(salesRepsMeta.currentPage);
            } else {
                const msg = result.errors ? Object.values(result.errors).flat().join('\n') : (result.message || 'Failed to save');
                alert(msg);
            }
        } catch (e) {
            console.error(e);
            alert('Failed to save sales rep');
        }
    }

    async function deleteSalesRepRecord(repId) {
        if (!confirm('Delete this sales rep? This cannot be undone.')) return;
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        try {
            const response = await fetch(`{{ url('/api/user-management/sales-reps') }}/${repId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json',
                },
            });
            const result = await response.json();
            if (result.success) {
                loadSalesReps(salesRepsMeta.currentPage);
            } else {
                alert(result.message || 'Failed to delete');
            }
        } catch (e) {
            console.error(e);
            alert('Failed to delete sales rep');
        }
    }

    document.getElementById('employeeSalesRepCommissionType')?.addEventListener('change', updateEmployeeSalesRepCommissionHelp);

    document.getElementById('salesRepsPrevBtn')?.addEventListener('click', () => {
        if (salesRepsMeta.currentPage > 1) loadSalesReps(salesRepsMeta.currentPage - 1);
    });
    document.getElementById('salesRepsNextBtn')?.addEventListener('click', () => {
        if (salesRepsMeta.currentPage < salesRepsMeta.lastPage) loadSalesReps(salesRepsMeta.currentPage + 1);
    });

    // Load employees from API
    async function loadEmployees(page = 1, search = '') {
        try {
            let url = `{{ route('api.user-management.employees') }}?page=${page}&per_page=${employeesMeta.perPage}`;
            if (search && search.trim()) {
                url += `&search=${encodeURIComponent(search.trim())}`;
            }
            
            const response = await fetch(url);
            const data = await response.json();
            
            employeesData = data.data || [];
            employeesMeta = {
                currentPage: data.current_page || 1,
                lastPage: data.last_page || 1,
                perPage: data.per_page || 10,
                total: data.total || 0
            };
            
            updateView();
        } catch (error) {
            console.error('Error loading employees:', error);
            alert('Failed to load employees. Please refresh the page.');
        }
    }

    // Render Functions
    function renderTable() {
        const tbody = document.getElementById('employeesTableBody');

        tbody.innerHTML = employeesData.map(emp => `
            <tr>
                <td><input type="checkbox" class="table-checkbox" data-id="${emp.id}"></td>
                <td>
                    <div class="employee-cell">
                        <div class="employee-avatar" ${emp.photo ? `style="background-image: url('${emp.photo}'); background-size: cover; background-position: center;"` : ''}>
                            ${emp.photo ? '' : (emp.initials || getInitials(emp.name))}
                        </div>
                        <div>
                            <div class="employee-name">${emp.name}</div>
                            <div class="employee-id">ID: ${emp.employee_id || emp.id}</div>
                        </div>
                    </div>
                </td>
                <td>${emp.email}</td>
                <td><span class="role-badge ${emp.role_type || 'employee'}">${emp.role}</span></td>
                <td>${emp.department || 'General'}</td>
                <td>${(emp.clients && emp.clients.length) ? emp.clients.join(', ') : '—'}</td>
                <td>${emp.sales_rep_id ? `<span style="font-weight:500">${emp.sales_rep_name || '—'}</span><br><small style="color:var(--text-muted);">${formatEmployeeSalesCommission(emp)}</small>` : '—'}</td>
                <td><span class="status-badge ${emp.status}">${emp.status === 'active' ? 'Active' : emp.status === 'inactive' ? 'Inactive' : 'Suspended'}</span></td>
                <td>
                    <div class="table-actions">
                        <button class="icon-btn" title="View" onclick="viewEmployee(${emp.id})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                        <button class="icon-btn" title="Edit" onclick="editEmployee(${emp.id})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                            </svg>
                        </button>
                        <button class="icon-btn" title="Delete" onclick="deleteEmployee(${emp.id})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"/>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                            </svg>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    function renderCards() {
        const container = document.getElementById('employeesCards');

        container.innerHTML = employeesData.map(emp => `
            <div class="employee-card">
                <div class="employee-card-header">
                    <div class="employee-card-main">
                        <input type="checkbox" class="table-checkbox" data-id="${emp.id}">
                        <div class="employee-avatar" ${emp.photo ? `style="background-image: url('${emp.photo}'); background-size: cover; background-position: center;"` : ''}>
                            ${emp.photo ? '' : (emp.initials || getInitials(emp.name))}
                        </div>
                        <div class="employee-card-info">
                            <div class="employee-name">${emp.name}</div>
                            <div class="employee-id">ID: ${emp.employee_id || emp.id}</div>
                        </div>
                    </div>
                    <div class="employee-card-actions">
                        <button class="icon-btn" title="View" onclick="viewEmployee(${emp.id})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                        <button class="icon-btn" title="Edit" onclick="editEmployee(${emp.id})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                            </svg>
                        </button>
                        <button class="icon-btn" title="Delete" onclick="deleteEmployee(${emp.id})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"/>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="employee-card-details">
                    <div class="employee-card-detail">
                        <span class="employee-card-label">Email</span>
                        <span class="employee-card-value">${emp.email}</span>
                    </div>
                    <div class="employee-card-detail">
                        <span class="employee-card-label">Role</span>
                        <span class="employee-card-value"><span class="role-badge ${emp.role_type || 'employee'}">${emp.role}</span></span>
                    </div>
                    <div class="employee-card-detail">
                        <span class="employee-card-label">Department</span>
                        <span class="employee-card-value">${emp.department || 'General'}</span>
                    </div>
                    <div class="employee-card-detail">
                        <span class="employee-card-label">Clients</span>
                        <span class="employee-card-value">${(emp.clients && emp.clients.length) ? emp.clients.join(', ') : 'None'}</span>
                    </div>
                    <div class="employee-card-detail">
                        <span class="employee-card-label">Sales rep</span>
                        <span class="employee-card-value">${emp.sales_rep_id ? `${emp.sales_rep_name || '—'} (${formatEmployeeSalesCommission(emp)})` : '—'}</span>
                    </div>
                    <div class="employee-card-detail">
                        <span class="employee-card-label">Status</span>
                        <span class="employee-card-value"><span class="status-badge ${emp.status}">${emp.status === 'active' ? 'Active' : emp.status === 'inactive' ? 'Inactive' : 'Suspended'}</span></span>
                    </div>
                </div>
            </div>
        `).join('');
    }

    // Helper function to get initials
    function getInitials(name) {
        const words = name.split(' ');
        let initials = '';
        words.forEach(word => {
            if (word) initials += word[0].toUpperCase();
        });
        return initials.substring(0, 2);
    }

    function renderPagination() {
        const info = document.getElementById('paginationInfo');
        const numbers = document.getElementById('paginationNumbers');
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');

        const start = employeesMeta.total > 0 ? ((employeesMeta.currentPage - 1) * employeesMeta.perPage) + 1 : 0;
        const end = Math.min(employeesMeta.currentPage * employeesMeta.perPage, employeesMeta.total);
        info.textContent = `Showing ${start} to ${end} of ${employeesMeta.total} results`;

        // Update buttons
        prevBtn.disabled = employeesMeta.currentPage === 1;
        nextBtn.disabled = employeesMeta.currentPage === employeesMeta.lastPage;

        // Generate page numbers
        let html = '';
        const maxVisible = 5;
        const currentPage = employeesMeta.currentPage;
        const totalPages = employeesMeta.lastPage;
        let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
        let endPage = Math.min(totalPages, startPage + maxVisible - 1);

        if (endPage - startPage < maxVisible - 1) {
            startPage = Math.max(1, endPage - maxVisible + 1);
        }

        if (startPage > 1) {
            html += `<button class="pagination-number" data-page="1">1</button>`;
            if (startPage > 2) {
                html += `<span class="pagination-number ellipsis">...</span>`;
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            html += `<button class="pagination-number ${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</button>`;
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                html += `<span class="pagination-number ellipsis">...</span>`;
            }
            html += `<button class="pagination-number" data-page="${totalPages}">${totalPages}</button>`;
        }

        numbers.innerHTML = html;

        // Add event listeners
        numbers.querySelectorAll('.pagination-number:not(.ellipsis)').forEach(btn => {
            btn.addEventListener('click', () => {
                loadEmployees(parseInt(btn.dataset.page), currentSearchTerm);
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
        if (employeesMeta.currentPage > 1) {
            loadEmployees(employeesMeta.currentPage - 1, currentSearchTerm);
        }
    });

    document.getElementById('nextBtn').addEventListener('click', () => {
        if (employeesMeta.currentPage < employeesMeta.lastPage) {
            loadEmployees(employeesMeta.currentPage + 1, currentSearchTerm);
        }
    });

    // Select All Checkbox
    document.getElementById('selectAll')?.addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.table-checkbox:not(#selectAll)');
        checkboxes.forEach(cb => cb.checked = this.checked);
    });

    // Search functionality
    const employeeSearchInput = document.getElementById('employeeSearch');
    const clearSearchBtn = document.getElementById('clearSearch');
    let searchTimeout;

    if (employeeSearchInput) {
        employeeSearchInput.addEventListener('input', function() {
            const searchValue = this.value;
            currentSearchTerm = searchValue;
            
            // Show/hide clear button
            if (clearSearchBtn) {
                clearSearchBtn.style.display = searchValue ? 'block' : 'none';
            }
            
            // Clear previous timeout
            clearTimeout(searchTimeout);
            
            // Debounce search - wait 500ms after user stops typing
            searchTimeout = setTimeout(() => {
                loadEmployees(1, searchValue);
            }, 500);
        });

        // Clear search button
        if (clearSearchBtn) {
            clearSearchBtn.addEventListener('click', function() {
                employeeSearchInput.value = '';
                currentSearchTerm = '';
                this.style.display = 'none';
                loadEmployees(1, '');
            });
        }
    }

    // Window Resize Handler
    window.addEventListener('resize', updateView);

    // Department Management Functions
    let departmentsData = [];

    async function loadDepartments() {
        try {
            const response = await fetch('{{ route("api.user-management.departments") }}');
            const data = await response.json();
            
            if (data.success) {
                departmentsData = data.data || [];
                renderDepartments();
            }
        } catch (error) {
            console.error('Error loading departments:', error);
        }
    }

    function renderDepartments() {
        const grid = document.getElementById('departmentsGrid');
        if (!grid) return;

        if (departmentsData.length === 0) {
            grid.innerHTML = '<div class="empty-state" style="grid-column: 1 / -1; text-align: center; padding: 2rem; color: var(--text-muted);">No departments found. Create your first department to get started.</div>';
            return;
        }

        grid.innerHTML = departmentsData.map(dept => `
            <div class="department-card" style="padding: 0.75rem; background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 6px; display: flex; align-items: center; justify-content: space-between;">
                <div style="flex: 1; min-width: 0;">
                    <div style="font-weight: 600; color: var(--text-primary); margin-bottom: 0.25rem; font-size: 0.8125rem; word-wrap: break-word;">${dept.name}</div>
                    ${dept.description ? `<div style="font-size: 0.75rem; color: var(--text-muted); word-wrap: break-word; line-height: 1.4;">${dept.description}</div>` : ''}
                </div>
                <div style="display: flex; gap: 0.375rem; margin-left: 0.75rem; flex-shrink: 0;">
                    <button class="icon-btn" title="Edit" onclick="editDepartment(${dept.id})" style="padding: 0.4375rem;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 15px; height: 15px;">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                        </svg>
                    </button>
                    <button class="icon-btn" title="Delete" onclick="deleteDepartment(${dept.id})" style="padding: 0.4375rem;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 15px; height: 15px;">
                            <polyline points="3 6 5 6 21 6"/>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        </svg>
                    </button>
                </div>
            </div>
        `).join('');
    }

    async function openDepartmentModal(departmentId = null) {
        const modal = document.getElementById('departmentModal');
        const title = document.getElementById('departmentModalTitle');
        const form = document.getElementById('departmentForm');
        
        form.reset();
        document.getElementById('departmentId').value = '';
        
        if (departmentId) {
            title.textContent = 'Edit Department';
            try {
                const dept = departmentsData.find(d => d.id === departmentId);
                if (dept) {
                    document.getElementById('departmentId').value = dept.id;
                    document.getElementById('departmentName').value = dept.name || '';
                    document.getElementById('departmentDescription').value = dept.description || '';
                } else {
                    alert('Department not found');
                    return;
                }
            } catch (error) {
                console.error('Error loading department:', error);
                alert('Failed to load department data');
                return;
            }
        } else {
            title.textContent = 'Add New Department';
        }
        
        modal.classList.add('active');
    }

    function closeDepartmentModal() {
        document.getElementById('departmentModal').classList.remove('active');
    }

    async function saveDepartment() {
        const form = document.getElementById('departmentForm');
        const departmentName = document.getElementById('departmentName');
        
        if (!departmentName.value || departmentName.value.trim() === '') {
            alert('Department Name is required');
            departmentName.focus();
            return;
        }
        
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        const formData = new FormData(form);
        const departmentId = formData.get('id');
        
        const data = {
            name: formData.get('name'),
            description: formData.get('description') || null
        };
        
        try {
            const url = departmentId 
                ? `/api/user-management/departments/${departmentId}`
                : `/api/user-management/departments`;
            
            const response = await fetch(url, {
                method: departmentId ? 'PUT' : 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                alert(departmentId ? 'Department updated successfully!' : 'Department created successfully!');
                closeDepartmentModal();
                await loadDepartments();
                // Reload departments dropdown in employee modal if it's open
                const employeeModal = document.getElementById('employeeModal');
                if (employeeModal && employeeModal.classList.contains('active')) {
                    // Reload departments in employee form
                    const departmentsResponse = await fetch('{{ route("api.user-management.departments") }}');
                    const departmentsData = await departmentsResponse.json();
                    const departmentSelect = document.getElementById('employeeDepartment');
                    if (departmentSelect && departmentsData.success && departmentsData.data) {
                        const currentValue = departmentSelect.value;
                        departmentSelect.innerHTML = '<option value="">Select a department...</option>';
                        departmentsData.data.forEach(department => {
                            const option = document.createElement('option');
                            option.value = department.id;
                            option.textContent = department.name;
                            if (department.id == currentValue) {
                                option.selected = true;
                            }
                            departmentSelect.appendChild(option);
                        });
                    }
                }
            } else {
                const errors = result.errors ? Object.values(result.errors).flat().join('\n') : (result.message || 'Failed to save department');
                alert(errors);
            }
        } catch (error) {
            console.error('Error saving department:', error);
            alert('Failed to save department. Please try again.');
        }
    }

    function editDepartment(id) {
        openDepartmentModal(id);
    }

    async function deleteDepartment(id) {
        if (!confirm('Are you sure you want to delete this department? Employees assigned to this department will need to be reassigned.')) {
            return;
        }
        
        try {
            const response = await fetch(`/api/user-management/departments/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'application/json'
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                alert('Department deleted successfully');
                await loadDepartments();
                // Reload departments dropdown in employee modal if it's open
                const employeeModal = document.getElementById('employeeModal');
                if (employeeModal && employeeModal.classList.contains('active')) {
                    const departmentsResponse = await fetch('{{ route("api.user-management.departments") }}');
                    const departmentsData = await departmentsResponse.json();
                    const departmentSelect = document.getElementById('employeeDepartment');
                    if (departmentSelect && departmentsData.success && departmentsData.data) {
                        const currentValue = departmentSelect.value;
                        departmentSelect.innerHTML = '<option value="">Select a department...</option>';
                        departmentsData.data.forEach(department => {
                            const option = document.createElement('option');
                            option.value = department.id;
                            option.textContent = department.name;
                            if (department.id == currentValue) {
                                option.selected = true;
                            }
                            departmentSelect.appendChild(option);
                        });
                    }
                }
            } else {
                alert(data.message || 'Failed to delete department');
            }
        } catch (error) {
            console.error('Error deleting department:', error);
            alert('Failed to delete department. Please try again.');
        }
    }

    // Initialize - Load employees and departments when respective tabs are active
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const tabId = this.dataset.tab;
            if (tabId === 'employees') {
                if (employeesData.length === 0) {
                    loadEmployees(1, currentSearchTerm);
                }
            } else if (tabId === 'departments') {
                loadDepartments();
            }
        });
    });

    // Load employees if employees tab is initially active
    if (document.getElementById('employeesTab').classList.contains('active')) {
        loadEmployees(1, currentSearchTerm);
    }

    // Load departments if departments tab is initially active
    if (document.getElementById('departmentsTab') && document.getElementById('departmentsTab').classList.contains('active')) {
        loadDepartments();
    }
    
    // Close modals on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeRoleModal();
            closeEmployeeModal();
            closeDepartmentModal();
        }
    });
    
    // Auto-generate slug from role name
    document.getElementById('roleName')?.addEventListener('input', function() {
        const slugInput = document.getElementById('roleSlug');
        if (!slugInput.value || slugInput.dataset.autoGenerated === 'true') {
            const slug = this.value.toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-|-$/g, '');
            slugInput.value = slug;
            slugInput.dataset.autoGenerated = 'true';
        }
    });
    
    document.getElementById('roleSlug')?.addEventListener('input', function() {
        this.dataset.autoGenerated = 'false';
    });
</script>
@endpush

