@extends('layouts.app')

@section('title', 'User Management - Admin')

@section('content')
    <div class="page-header">
        <h1 class="page-title">Admin User Management</h1>
        <p class="page-subtitle">Manage admin users, roles, and permissions</p>
    </div>

    <!-- Admin Stats Overview -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Total Admin Users</span>
                <div class="stat-icon blue">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value" id="totalUsersCount">{{ count($users ?? []) }}</div>
            <div class="stat-change positive">Active admins</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Total Roles</span>
                <div class="stat-icon green">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value" id="totalRolesCount">{{ count($roles ?? []) }}</div>
            <div class="stat-change positive">Custom roles</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Total Permissions</span>
                <div class="stat-icon purple">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        <path d="M9 12l2 2 4-4"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value" id="totalPermissionsCount">{{ count($permissions ?? []) }}</div>
            <div class="stat-change positive">Available permissions</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Active Sessions</span>
                <div class="stat-icon orange">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value" id="activeSessionsCount">0</div>
            <div class="stat-change">Currently logged in</div>
        </div>
    </div>

    <!-- User Management Tabs -->
    <div class="admin-user-management-container">
        <div class="management-tabs">
            <button class="tab-btn active" data-tab="users" onclick="switchTab('users')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
                Users
            </button>
            <button class="tab-btn" data-tab="roles" onclick="switchTab('roles')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
                Roles
            </button>
            <button class="tab-btn" data-tab="permissions" onclick="switchTab('permissions')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    <path d="M9 12l2 2 4-4"/>
                </svg>
                Permissions
            </button>
        </div>

        <!-- Users Tab -->
        <div class="tab-content active" id="usersTab">
            <div class="section-header">
                <h2 class="section-title">Admin Users</h2>
                <div class="header-actions">
                    <div class="search-box">
                        <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/>
                            <path d="m21 21-4.35-4.35"/>
                        </svg>
                        <input type="text" class="search-input" placeholder="Search users..." id="userSearch" onkeyup="filterUsers()">
                    </div>
                    @if($canCreateUser ?? true)
                    <button class="btn-primary" onclick="openUserModal()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Add New User
                    </button>
                    @endif
                </div>
            </div>

            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody">
                        @foreach($users ?? [] as $user)
                            <tr>
                                <td><strong>{{ $user['name'] }}</strong></td>
                                <td>{{ $user['email'] }}</td>
                                <td>{{ $user['role'] }}</td>
                                <td><span class="status-badge {{ $user['status'] }}">{{ ucfirst($user['status']) }}</span></td>
                                <td>{{ $user['last_login'] }}</td>
                                <td>
                                    @if($canEditUser ?? true)
                                    <button class="btn-sm btn-secondary" onclick="editUser({{ $user['id'] }})">Edit</button>
                                    @endif
                                    @if($canDeleteUser ?? true)
                                    <button class="btn-sm btn-secondary" onclick="deleteUser({{ $user['id'] }})">Delete</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Roles Tab -->
        <div class="tab-content" id="rolesTab">
            <div class="section-header">
                <h2 class="section-title">Roles</h2>
                @if($canCreateRole ?? true)
                <button class="btn-primary" onclick="openRoleModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Add New Role
                </button>
                @endif
            </div>

            <div class="roles-grid" id="rolesGrid">
                @foreach($roles ?? [] as $role)
                    <div class="role-card">
                        <div class="role-card-header">
                            <div class="role-name">{{ $role->name }}</div>
                            <div class="role-actions">
                                @if($canEditRole ?? true)
                                <button class="btn-sm btn-secondary" onclick="editRole({{ $role->id }})">Edit</button>
                                @endif
                                @if($canDeleteRole ?? true)
                                <button class="btn-sm btn-secondary" onclick="deleteRole({{ $role->id }})">Delete</button>
                                @endif
                            </div>
                        </div>
                        <div class="role-description">{{ $role->description ?? 'No description' }}</div>
                        <div class="role-meta">
                            <span>{{ $role->users_count ?? 0 }} users</span>
                            <span>•</span>
                            <span>{{ $role->is_active ? 'Active' : 'Inactive' }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Permissions Tab -->
        <div class="tab-content" id="permissionsTab">
            <div class="section-header">
                <h2 class="section-title">Permissions</h2>
                @if($canCreatePermission ?? true)
                <button class="btn-primary" onclick="openPermissionModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Add New Permission
                </button>
                @endif
            </div>

            <div class="permissions-grid" id="permissionsGrid">
                @foreach($permissions ?? [] as $permission)
                    <div class="permission-card">
                        <div class="permission-info">
                            <div class="permission-name">{{ $permission->display_name }}</div>
                            <div class="permission-meta">{{ $permission->name }} • {{ $permission->category }}</div>
                        </div>
                        <div class="role-actions">
                            @if($canEditPermission ?? true)
                            <button class="btn-sm btn-secondary" onclick="editPermission({{ $permission->id }})">Edit</button>
                            @endif
                            @if($canDeletePermission ?? true)
                            <button class="btn-sm btn-secondary" onclick="deletePermission({{ $permission->id }})">Delete</button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- User Modal -->
    <div class="modal" id="userModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="userModalTitle">Add New User</h3>
                <button class="modal-close" onclick="closeUserModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <form id="userForm" onsubmit="saveUser(event)">
                    <input type="hidden" id="userId" name="id">
                    <div class="form-group">
                        <label for="userName">Full Name *</label>
                        <input type="text" id="userName" name="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="userEmail">Email Address *</label>
                        <input type="email" id="userEmail" name="email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="userRole">Role *</label>
                        <select id="userRole" name="role" class="form-control" required>
                            <option value="">Select a role...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="userStatus">Status</label>
                        <select id="userStatus" name="status" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="userPassword">Password</label>
                        <input type="password" id="userPassword" name="password" class="form-control" placeholder="Leave blank to keep current">
                        <small class="form-text">Only required for new users</small>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn-secondary" onclick="closeUserModal()">Cancel</button>
                        <button type="submit" class="btn-primary">Save User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Role Modal -->
    <div class="modal" id="roleModal">
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
                <form id="roleForm" onsubmit="saveRole(event)">
                    <input type="hidden" id="roleId" name="id">
                    <div class="form-group">
                        <label for="roleName">Role Name *</label>
                        <input type="text" id="roleName" name="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="roleDescription">Description</label>
                        <textarea id="roleDescription" name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Permissions</label>
                        <div class="permissions-checklist" id="rolePermissionsChecklist">
                            <!-- Permissions will be rendered here -->
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn-secondary" onclick="closeRoleModal()">Cancel</button>
                        <button type="submit" class="btn-primary">Save Role</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Permission Modal -->
    <div class="modal" id="permissionModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="permissionModalTitle">Add New Permission</h3>
                <button class="modal-close" onclick="closePermissionModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <form id="permissionForm" onsubmit="savePermission(event)">
                    <input type="hidden" id="permissionId" name="id">
                    <div class="form-group">
                        <label for="permissionName">Permission Name *</label>
                        <input type="text" id="permissionName" name="name" class="form-control" placeholder="e.g., users.create" required>
                    </div>
                    <div class="form-group">
                        <label for="permissionDisplayName">Display Name *</label>
                        <input type="text" id="permissionDisplayName" name="display_name" class="form-control" placeholder="e.g., Create Users" required>
                    </div>
                    <div class="form-group">
                        <label for="permissionDescription">Description</label>
                        <textarea id="permissionDescription" name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="permissionCategory">Category</label>
                        <select id="permissionCategory" name="category" class="form-control">
                            <option value="users">Users</option>
                            <option value="roles">Roles</option>
                            <option value="permissions">Permissions</option>
                            <option value="billing">Billing</option>
                            <option value="system">System</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn-secondary" onclick="closePermissionModal()">Cancel</button>
                        <button type="submit" class="btn-primary">Save Permission</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    @include('admin.partials.styles')
    <style>
        .admin-user-management-container {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            margin-top: 2rem;
        }

        .management-tabs {
            display: flex;
            gap: 0.5rem;
            padding: 1rem;
            border-bottom: 1px solid var(--border);
            background: var(--bg-primary);
        }

        .management-tabs .tab-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: transparent;
            border: none;
            border-radius: 8px;
            color: var(--text-secondary);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .management-tabs .tab-btn svg {
            width: 18px;
            height: 18px;
        }

        .management-tabs .tab-btn:hover {
            background: var(--accent-light);
            color: var(--accent);
        }

        .management-tabs .tab-btn.active {
            background: var(--accent);
            color: white;
        }

        .tab-content {
            display: none;
            padding: 2rem;
        }

        .tab-content.active {
            display: block;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }

        .table-container {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table thead {
            background: var(--bg-primary);
        }

        .data-table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--text-primary);
            border-bottom: 2px solid var(--border);
        }

        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border);
        }

        .data-table tbody tr:hover {
            background: var(--bg-primary);
        }

        .roles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .role-card {
            background: var(--bg-primary);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1.5rem;
        }

        .role-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .role-name {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .role-description {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }

        .role-meta {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .role-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .permissions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1rem;
        }

        .permission-card {
            background: var(--bg-primary);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .permission-info {
            flex: 1;
        }

        .permission-name {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .permission-meta {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .permissions-checklist {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1rem;
        }

        .permission-category-section {
            margin-bottom: 1.5rem;
        }

        .permission-category-section:last-child {
            margin-bottom: 0;
        }

        .permission-category-header {
            padding: 0.5rem 0.75rem;
            background: var(--bg-primary);
            border-radius: 6px;
            margin-bottom: 0.75rem;
            font-size: 0.875rem;
            color: var(--text-primary);
            border-left: 3px solid var(--accent);
        }

        .permission-category-items {
            margin-left: 0.5rem;
        }

        .permission-checkbox-item {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            border-radius: 6px;
            margin-bottom: 0.5rem;
        }

        .permission-checkbox-item:hover {
            background: var(--bg-primary);
        }

        .permission-checkbox-item input[type="checkbox"] {
            margin-right: 0.75rem;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .permission-checkbox-item label {
            cursor: pointer;
            flex: 1;
            font-size: 0.875rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-primary);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.875rem;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent);
        }

        .form-text {
            display: block;
            margin-top: 0.25rem;
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
        }

        /* Responsive Styles */
        @media (max-width: 768px) {
            .admin-user-management-container {
                margin-top: 1rem;
            }

            .management-tabs {
                flex-wrap: wrap;
                padding: 0.75rem;
            }

            .management-tabs .tab-btn {
                flex: 1;
                min-width: calc(50% - 0.25rem);
                padding: 0.625rem 1rem;
                font-size: 0.875rem;
            }

            .tab-content {
                padding: 1rem;
            }

            .section-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .header-actions {
                width: 100%;
                flex-direction: column;
            }

            .search-box {
                width: 100%;
            }

            .search-input {
                width: 100% !important;
            }

            .data-table {
                font-size: 0.8125rem;
            }

            .data-table th,
            .data-table td {
                padding: 0.75rem 0.5rem;
            }

            .roles-grid {
                grid-template-columns: 1fr;
            }

            .permissions-grid {
                grid-template-columns: 1fr;
            }

            .role-card {
                padding: 1rem;
            }

            .role-card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }

            .role-actions {
                width: 100%;
            }

            .role-actions .btn-sm {
                flex: 1;
            }

            .permission-card {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }

            .permissions-checklist {
                max-height: 250px;
            }

            .modal-actions {
                flex-direction: column;
            }

            .modal-actions .btn-primary,
            .modal-actions .btn-secondary {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .management-tabs .tab-btn {
                min-width: 100%;
                font-size: 0.8125rem;
            }

            .data-table {
                font-size: 0.75rem;
            }

            .data-table th,
            .data-table td {
                padding: 0.5rem 0.25rem;
            }
        }
    </style>
@endpush

@push('scripts')
    @include('admin.partials.scripts')
    <script>
        // Initialize data from server-side rendering for instant display
        window.initialUserManagementData = {
            users: @json($users ?? []),
            roles: @json($roles ?? []),
            permissions: @json($permissions ?? []),
            canCreateUser: @json($canCreateUser ?? true),
            canEditUser: @json($canEditUser ?? true),
            canDeleteUser: @json($canDeleteUser ?? true),
            canCreateRole: @json($canCreateRole ?? true),
            canEditRole: @json($canEditRole ?? true),
            canDeleteRole: @json($canDeleteRole ?? true),
            canCreatePermission: @json($canCreatePermission ?? true),
            canEditPermission: @json($canEditPermission ?? true),
            canDeletePermission: @json($canDeletePermission ?? true),
        };

        // Global data storage
        let userManagementUsers = [];
        let userManagementRoles = [];
        let userManagementPermissions = [];
        let userManagementStats = {};

        // Data Loading Functions
        async function loadUserManagementUsers() {
            try {
                userManagementUsers = await apiCall('/user-management/users');
            } catch (error) {
                console.error('Error loading users:', error);
                // Keep the server-side rendered data if API fails
                if (window.initialUserManagementData && window.initialUserManagementData.users) {
                    userManagementUsers = window.initialUserManagementData.users;
                }
            }
        }

        async function loadUserManagementRoles() {
            try {
                userManagementRoles = await apiCall('/user-management/roles');
            } catch (error) {
                console.error('Error loading roles:', error);
                // Keep the server-side rendered data if API fails
                if (window.initialUserManagementData && window.initialUserManagementData.roles) {
                    userManagementRoles = window.initialUserManagementData.roles;
                }
            }
        }

        async function loadUserManagementPermissions() {
            try {
                const response = await apiCall('/user-management/permissions');
                // Handle both array response and object with data property
                if (Array.isArray(response)) {
                    userManagementPermissions = response;
                } else if (response && Array.isArray(response.data)) {
                    userManagementPermissions = response.data;
                } else if (response && response.success && Array.isArray(response.data)) {
                    userManagementPermissions = response.data;
                } else {
                    userManagementPermissions = [];
                }
            } catch (error) {
                console.error('Error loading permissions:', error);
                // Keep the server-side rendered data if API fails
                if (window.initialUserManagementData && window.initialUserManagementData.permissions) {
                    userManagementPermissions = Array.isArray(window.initialUserManagementData.permissions) 
                        ? window.initialUserManagementData.permissions 
                        : [];
                } else {
                    userManagementPermissions = [];
                }
            }
        }

        async function loadUserManagementStats() {
            try {
                userManagementStats = await apiCall('/user-management/stats');
                updateUserManagementStatsDisplay();
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        }

        function updateUserManagementStatsDisplay() {
            if (userManagementStats.total_users !== undefined) {
                document.getElementById('totalUsersCount').textContent = userManagementStats.total_users;
            }
            if (userManagementStats.total_roles !== undefined) {
                document.getElementById('totalRolesCount').textContent = userManagementStats.total_roles;
            }
            if (userManagementStats.total_permissions !== undefined) {
                document.getElementById('totalPermissionsCount').textContent = userManagementStats.total_permissions;
            }
            if (userManagementStats.active_sessions !== undefined) {
                document.getElementById('activeSessionsCount').textContent = userManagementStats.active_sessions;
            }
        }

        // Render Functions
        function renderUserManagementUsers() {
            const tbody = document.getElementById('usersTableBody');
            if (!tbody) return;

            if (userManagementUsers.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 2rem; color: var(--text-secondary);">No users found</td></tr>';
                return;
            }

            const canEdit = window.initialUserManagementData.canEditUser !== false;
            const canDelete = window.initialUserManagementData.canDeleteUser !== false;
            tbody.innerHTML = userManagementUsers.map(user => `
                <tr>
                    <td><strong>${user.name}</strong></td>
                    <td>${user.email}</td>
                    <td>${user.role}</td>
                    <td><span class="status-badge ${user.status}">${user.status.charAt(0).toUpperCase() + user.status.slice(1)}</span></td>
                    <td>${user.last_login || 'Never'}</td>
                    <td>
                        ${canEdit ? `<button class="btn-sm btn-secondary" onclick="editUser(${user.id})">Edit</button>` : ''}
                        ${canDelete ? `<button class="btn-sm btn-secondary" onclick="deleteUser(${user.id})">Delete</button>` : ''}
                    </td>
                </tr>
            `).join('');
        }

        function renderUserManagementRoles() {
            const grid = document.getElementById('rolesGrid');
            if (!grid) return;

            if (userManagementRoles.length === 0) {
                grid.innerHTML = '<p style="text-align: center; padding: 2rem; color: var(--text-secondary);">No roles found</p>';
                return;
            }

            const canEdit = window.initialUserManagementData.canEditRole !== false;
            const canDelete = window.initialUserManagementData.canDeleteRole !== false;
            grid.innerHTML = userManagementRoles.map(role => `
                <div class="role-card">
                    <div class="role-card-header">
                        <div class="role-name">${role.name}</div>
                        <div class="role-actions">
                            ${canEdit ? `<button class="btn-sm btn-secondary" onclick="editRole(${role.id})">Edit</button>` : ''}
                            ${canDelete ? `<button class="btn-sm btn-secondary" onclick="deleteRole(${role.id})">Delete</button>` : ''}
                        </div>
                    </div>
                    <div class="role-description">${role.description || 'No description'}</div>
                    <div class="role-meta">
                        <span>${role.users_count || 0} users</span>
                        <span>•</span>
                        <span>${role.is_active ? 'Active' : 'Inactive'}</span>
                    </div>
                </div>
            `).join('');
        }

        function renderUserManagementPermissions() {
            const grid = document.getElementById('permissionsGrid');
            if (!grid) return;

            if (userManagementPermissions.length === 0) {
                grid.innerHTML = '<p style="text-align: center; padding: 2rem; color: var(--text-secondary);">No permissions found</p>';
                return;
            }

            const canEdit = window.initialUserManagementData.canEditPermission !== false;
            const canDelete = window.initialUserManagementData.canDeletePermission !== false;
            grid.innerHTML = userManagementPermissions.map(permission => `
                <div class="permission-card">
                    <div class="permission-info">
                        <div class="permission-name">${permission.display_name}</div>
                        <div class="permission-meta">${permission.name} • ${permission.category}</div>
                    </div>
                    <div class="role-actions">
                        ${canEdit ? `<button class="btn-sm btn-secondary" onclick="editPermission(${permission.id})">Edit</button>` : ''}
                        ${canDelete ? `<button class="btn-sm btn-secondary" onclick="deletePermission(${permission.id})">Delete</button>` : ''}
                    </div>
                </div>
            `).join('');
        }

        // Tab Switching
        function switchTab(tabName) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
            document.getElementById(`${tabName}Tab`).classList.add('active');
        }

        // User Functions
        function openUserModal(userId = null) {
            const modal = document.getElementById('userModal');
            const title = document.getElementById('userModalTitle');
            const form = document.getElementById('userForm');
            const roleSelect = document.getElementById('userRole');

            // Populate role select
            roleSelect.innerHTML = '<option value="">Select a role...</option>' +
                userManagementRoles.map(role => `<option value="${role.id}">${role.name}</option>`).join('');

            if (userId) {
                title.textContent = 'Edit User';
                const user = userManagementUsers.find(u => u.id === userId);
                if (user) {
                    document.getElementById('userId').value = user.id;
                    document.getElementById('userName').value = user.name;
                    document.getElementById('userEmail').value = user.email;
                    document.getElementById('userRole').value = user.role_id || '';
                    document.getElementById('userStatus').value = user.status;
                    document.getElementById('userPassword').required = false;
                }
            } else {
                title.textContent = 'Add New User';
                form.reset();
                document.getElementById('userId').value = '';
                document.getElementById('userPassword').required = true;
            }

            modal.classList.add('active');
        }

        function closeUserModal() {
            document.getElementById('userModal').classList.remove('active');
            document.getElementById('userForm').reset();
        }

        async function saveUser(event) {
            if (event) event.preventDefault();

            const form = document.getElementById('userForm');
            const formData = new FormData(form);
            const userId = formData.get('id');

            const userData = {
                name: formData.get('name'),
                email: formData.get('email'),
                role_id: formData.get('role') ? parseInt(formData.get('role')) : null,
                status: formData.get('status'),
            };

            if (formData.get('password')) {
                userData.password = formData.get('password');
            }

            try {
                if (userId) {
                    await apiCall(`/user-management/users/${userId}`, 'PUT', userData);
                } else {
                    await apiCall('/user-management/users', 'POST', userData);
                }
                alert('User saved successfully!');
                closeUserModal();
                await loadUserManagementUsers();
                renderUserManagementUsers();
            } catch (error) {
                alert('Error saving user: ' + error.message);
            }
        }

        function editUser(id) {
            openUserModal(id);
        }

        async function deleteUser(id) {
            if (!confirm('Are you sure you want to delete this user?')) return;

            try {
                await apiCall(`/user-management/users/${id}`, 'DELETE');
                alert('User deleted successfully!');
                await loadUserManagementUsers();
                renderUserManagementUsers();
            } catch (error) {
                alert('Error deleting user: ' + error.message);
            }
        }

        // Role Functions
        async function openRoleModal(roleId = null) {
            const modal = document.getElementById('roleModal');
            const title = document.getElementById('roleModalTitle');
            const form = document.getElementById('roleForm');
            const checklist = document.getElementById('rolePermissionsChecklist');

            // Always reload permissions to ensure we have the latest data
            await loadUserManagementPermissions();

            // Group permissions by category
            const permissionsByCategory = {};
            userManagementPermissions.forEach(permission => {
                const category = permission.category || 'Other';
                if (!permissionsByCategory[category]) {
                    permissionsByCategory[category] = [];
                }
                permissionsByCategory[category].push(permission);
            });

            // Render permissions grouped by category
            let html = '';
            // Sort categories, but put "Leave Management" near the top for visibility
            const sortedCategories = Object.keys(permissionsByCategory).sort((a, b) => {
                if (a === 'Leave Management') return -1;
                if (b === 'Leave Management') return 1;
                return a.localeCompare(b);
            });
            
            sortedCategories.forEach(category => {
                // Use category name as-is (already formatted)
                const categoryName = category || 'Other';
                const categoryPerms = permissionsByCategory[category];
                
                html += `
                    <div class="permission-category-section">
                        <div class="permission-category-header">
                            <strong>${categoryName}</strong>
                            <span style="font-size: 0.75rem; color: var(--text-secondary); margin-left: 0.5rem;">
                                (${categoryPerms.length} permission${categoryPerms.length !== 1 ? 's' : ''})
                            </span>
                        </div>
                        <div class="permission-category-items">
                            ${categoryPerms.map(permission => `
                                <div class="permission-checkbox-item">
                                    <input type="checkbox" id="perm_${permission.id}" value="${permission.id}" name="permissions[]">
                                    <label for="perm_${permission.id}">${permission.display_name} <small>(${permission.name})</small></label>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
            });

            if (html === '') {
                html = '<div class="empty-state"><p>No permissions found. Please refresh the page.</p></div>';
            }

            checklist.innerHTML = html;

            if (roleId) {
                title.textContent = 'Edit Role';
                
                // Load role data
                try {
                    const roleData = await apiCall(`/user-management/roles/${roleId}`);
                    document.getElementById('roleId').value = roleData.id;
                    document.getElementById('roleName').value = roleData.name;
                    document.getElementById('roleDescription').value = roleData.description || '';
                    
                    // Check the permissions that belong to this role
                    if (roleData.permissions && roleData.permissions.length > 0) {
                        roleData.permissions.forEach(permissionId => {
                            const checkbox = document.getElementById(`perm_${permissionId}`);
                            if (checkbox) {
                                checkbox.checked = true;
                            }
                        });
                    }
                } catch (error) {
                    console.error('Error loading role:', error);
                    alert('Error loading role data: ' + error.message);
                    return;
                }
            } else {
                title.textContent = 'Add New Role';
                form.reset();
                document.getElementById('roleId').value = '';
            }

            modal.classList.add('active');
        }

        function closeRoleModal() {
            document.getElementById('roleModal').classList.remove('active');
            document.getElementById('roleForm').reset();
        }

        async function saveRole(event) {
            if (event) event.preventDefault();

            const form = document.getElementById('roleForm');
            const formData = new FormData(form);
            const roleId = formData.get('id');

            const selectedPermissions = Array.from(form.querySelectorAll('input[name="permissions[]"]:checked'))
                .map(cb => parseInt(cb.value));

            const roleData = {
                name: formData.get('name'),
                description: formData.get('description'),
                permission_ids: selectedPermissions,
            };

            try {
                if (roleId) {
                    await apiCall(`/user-management/roles/${roleId}`, 'PUT', roleData);
                } else {
                    await apiCall('/user-management/roles', 'POST', roleData);
                }
                alert('Role saved successfully!');
                closeRoleModal();
                await loadUserManagementRoles();
                renderUserManagementRoles();
            } catch (error) {
                alert('Error saving role: ' + error.message);
            }
        }

        async function editRole(id) {
            await openRoleModal(id);
        }

        async function deleteRole(id) {
            if (!confirm('Are you sure you want to delete this role?')) return;

            try {
                await apiCall(`/user-management/roles/${id}`, 'DELETE');
                alert('Role deleted successfully!');
                await loadUserManagementRoles();
                renderUserManagementRoles();
            } catch (error) {
                alert('Error deleting role: ' + error.message);
            }
        }

        // Permission Functions
        function openPermissionModal(permissionId = null) {
            const modal = document.getElementById('permissionModal');
            const title = document.getElementById('permissionModalTitle');
            const form = document.getElementById('permissionForm');

            if (permissionId) {
                title.textContent = 'Edit Permission';
                const permission = userManagementPermissions.find(p => p.id === permissionId);
                if (permission) {
                    document.getElementById('permissionId').value = permission.id;
                    document.getElementById('permissionName').value = permission.name;
                    document.getElementById('permissionDisplayName').value = permission.display_name;
                    document.getElementById('permissionDescription').value = permission.description || '';
                    document.getElementById('permissionCategory').value = permission.category;
                }
            } else {
                title.textContent = 'Add New Permission';
                form.reset();
                document.getElementById('permissionId').value = '';
            }

            modal.classList.add('active');
        }

        function closePermissionModal() {
            document.getElementById('permissionModal').classList.remove('active');
            document.getElementById('permissionForm').reset();
        }

        async function savePermission(event) {
            if (event) event.preventDefault();

            const form = document.getElementById('permissionForm');
            const formData = new FormData(form);
            const permissionId = formData.get('id');

            const permissionData = {
                name: formData.get('name'),
                display_name: formData.get('display_name'),
                description: formData.get('description'),
                category: formData.get('category'),
            };

            try {
                if (permissionId) {
                    await apiCall(`/user-management/permissions/${permissionId}`, 'PUT', permissionData);
                } else {
                    await apiCall('/user-management/permissions', 'POST', permissionData);
                }
                alert('Permission saved successfully!');
                closePermissionModal();
                await loadUserManagementPermissions();
                renderUserManagementPermissions();
            } catch (error) {
                alert('Error saving permission: ' + error.message);
            }
        }

        function editPermission(id) {
            openPermissionModal(id);
        }

        async function deletePermission(id) {
            if (!confirm('Are you sure you want to delete this permission?')) return;

            try {
                await apiCall(`/user-management/permissions/${id}`, 'DELETE');
                alert('Permission deleted successfully!');
                await loadUserManagementPermissions();
                renderUserManagementPermissions();
            } catch (error) {
                alert('Error deleting permission: ' + error.message);
            }
        }

        // Filter Functions
        function filterUsers() {
            const search = document.getElementById('userSearch').value.toLowerCase();
            const filtered = userManagementUsers.filter(user => 
                user.name.toLowerCase().includes(search) ||
                user.email.toLowerCase().includes(search) ||
                user.role.toLowerCase().includes(search)
            );
            
            const tbody = document.getElementById('usersTableBody');
            if (filtered.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 2rem; color: var(--text-secondary);">No users found</td></tr>';
                return;
            }

            tbody.innerHTML = filtered.map(user => `
                <tr>
                    <td><strong>${user.name}</strong></td>
                    <td>${user.email}</td>
                    <td>${user.role}</td>
                    <td><span class="status-badge ${user.status}">${user.status.charAt(0).toUpperCase() + user.status.slice(1)}</span></td>
                    <td>${user.last_login || 'Never'}</td>
                    <td>
                        <button class="btn-sm btn-secondary" onclick="editUser(${user.id})">Edit</button>
                        <button class="btn-sm btn-secondary" onclick="deleteUser(${user.id})">Delete</button>
                    </td>
                </tr>
            `).join('');
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', async function() {
            // If we have initial data from server-side rendering, store it but don't render yet
            // Server-side rendering already populated the HTML, so we just store the data for JS use
            if (window.initialUserManagementData) {
                userManagementUsers = window.initialUserManagementData.users || [];
                userManagementRoles = window.initialUserManagementData.roles || [];
                // Don't use server-side permissions - always load fresh from API
                userManagementPermissions = [];
            }
            
            // Load data from API in background to refresh/update
            await Promise.all([
                loadUserManagementUsers(),
                loadUserManagementRoles(),
                loadUserManagementPermissions(),
                loadUserManagementStats()
            ]);

            // Re-render everything with fresh API data (this will update the display)
            renderUserManagementUsers();
            renderUserManagementRoles();
            renderUserManagementPermissions();
        });
    </script>
@endpush
