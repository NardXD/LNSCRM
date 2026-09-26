@extends('layouts.app')

@section('title', 'User & Access Management')

@push('styles')
    @vite(['resources/css/pages/user-management.css'])
@endpush

@push('scripts')
<script>
    window.__userManagementConfig = {
        rolesUrl: @json(route('api.user-management.roles')),
        clientsUrl: @json(route('api.user-management.clients')),
        twilioNumberOptionsUrl: @json(route('api.user-management.twilio-number-options')),
        departmentsUrl: @json(route('api.user-management.departments')),
        salesRepsIndexUrl: @json(route('api.user-management.sales-reps.index')),
        salesRepsStoreUrl: @json(route('api.user-management.sales-reps.store')),
        salesRepsBaseUrl: @json(url('/api/user-management/sales-reps')),
        companySettingsUpdateUrl: @json(route('api.user-management.company.settings.update')),
        permissionsUrl: @json(route('api.user-management.permissions')),
        employeesUrl: @json(route('api.user-management.employees')),
    };
</script>
    @vite(['resources/js/pages/user-management.js'])
@endpush

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

            <div class="roles-grid" id="rolesGrid" aria-busy="true">
                @for ($i = 0; $i < 4; $i++)
                    <div class="page-skel-role-card" aria-hidden="true">
                        <span class="page-skel-line w-55"></span>
                        <span class="page-skel-line w-80"></span>
                        <span class="page-skel-line w-40"></span>
                    </div>
                @endfor
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
                    <button type="button" class="pagination-btn" id="employeesPrevBtn" disabled>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="15 18 9 12 15 6"/>
                        </svg>
                        Previous
                    </button>
                    <div class="pagination-numbers" id="paginationNumbers">
                        <!-- Page numbers will be generated by JavaScript -->
                    </div>
                    <button type="button" class="pagination-btn" id="employeesNextBtn">
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
