// User Management JavaScript
(function() {
    const API_BASE = '/admin/api/user-management';
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    // Global data storage
    let users = [];
    let roles = [];
    let permissions = [];
    let stats = {};

    // Helper function for API calls
    async function apiCall(url, method = 'GET', data = null) {
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN,
            },
        };

        if (data && (method === 'POST' || method === 'PUT' || method === 'PATCH')) {
            options.body = JSON.stringify(data);
        }

        try {
            const response = await fetch(API_BASE + url, options);
            const result = await response.json();
            
            if (!response.ok) {
                throw new Error(result.message || 'An error occurred');
            }
            
            return result;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }

    // Data Loading Functions
    async function loadUsers() {
        try {
            users = await apiCall('/users');
            renderUsers();
            updateStats();
        } catch (error) {
            console.error('Error loading users:', error);
        }
    }

    async function loadRoles() {
        try {
            roles = await apiCall('/roles');
            renderRoles();
            updateStats();
        } catch (error) {
            console.error('Error loading roles:', error);
        }
    }

    async function loadPermissions() {
        try {
            permissions = await apiCall('/permissions');
            renderPermissions();
            updateStats();
        } catch (error) {
            console.error('Error loading permissions:', error);
        }
    }

    async function loadStats() {
        try {
            stats = await apiCall('/stats');
            updateStatsDisplay();
        } catch (error) {
            console.error('Error loading stats:', error);
        }
    }

    function updateStats() {
        document.getElementById('totalUsersCount').textContent = users.length || 0;
        document.getElementById('totalRolesCount').textContent = roles.length || 0;
        document.getElementById('totalPermissionsCount').textContent = permissions.length || 0;
    }

    function updateStatsDisplay() {
        if (stats.total_users !== undefined) {
            document.getElementById('totalUsersCount').textContent = stats.total_users;
        }
        if (stats.total_roles !== undefined) {
            document.getElementById('totalRolesCount').textContent = stats.total_roles;
        }
        if (stats.total_permissions !== undefined) {
            document.getElementById('totalPermissionsCount').textContent = stats.total_permissions;
        }
        if (stats.active_sessions !== undefined) {
            document.getElementById('activeSessionsCount').textContent = stats.active_sessions;
        }
    }

    // Render Functions
    function renderUsers() {
        const tbody = document.getElementById('usersTableBody');
        if (!tbody) return;

        if (users.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 2rem; color: var(--text-secondary);">No users found</td></tr>';
            return;
        }

        tbody.innerHTML = users.map(user => `
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

    function renderRoles() {
        const grid = document.getElementById('rolesGrid');
        if (!grid) return;

        if (roles.length === 0) {
            grid.innerHTML = '<p style="text-align: center; padding: 2rem; color: var(--text-secondary);">No roles found</p>';
            return;
        }

        grid.innerHTML = roles.map(role => `
            <div class="role-card">
                <div class="role-card-header">
                    <div class="role-name">${role.name}</div>
                    <div class="role-actions">
                        <button class="btn-sm btn-secondary" onclick="editRole(${role.id})">Edit</button>
                        <button class="btn-sm btn-secondary" onclick="deleteRole(${role.id})">Delete</button>
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

    function renderPermissions() {
        const grid = document.getElementById('permissionsGrid');
        if (!grid) return;

        if (permissions.length === 0) {
            grid.innerHTML = '<p style="text-align: center; padding: 2rem; color: var(--text-secondary);">No permissions found</p>';
            return;
        }

        grid.innerHTML = permissions.map(permission => `
            <div class="permission-card">
                <div class="permission-info">
                    <div class="permission-name">${permission.display_name}</div>
                    <div class="permission-meta">${permission.name} • ${permission.category}</div>
                </div>
                <div class="role-actions">
                    <button class="btn-sm btn-secondary" onclick="editPermission(${permission.id})">Edit</button>
                    <button class="btn-sm btn-secondary" onclick="deletePermission(${permission.id})">Delete</button>
                </div>
            </div>
        `).join('');
    }

    // Tab Switching
    window.switchTab = function(tabName) {
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        
        document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
        document.getElementById(`${tabName}Tab`).classList.add('active');
    };

    // User Functions
    window.openUserModal = function(userId = null) {
        const modal = document.getElementById('userModal');
        const title = document.getElementById('userModalTitle');
        const form = document.getElementById('userForm');
        const roleSelect = document.getElementById('userRole');

        // Populate role select
        roleSelect.innerHTML = '<option value="">Select a role...</option>' +
            roles.map(role => `<option value="${role.id}">${role.name}</option>`).join('');

        if (userId) {
            title.textContent = 'Edit User';
            const user = users.find(u => u.id === userId);
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
    };

    window.closeUserModal = function() {
        document.getElementById('userModal').classList.remove('active');
        document.getElementById('userForm').reset();
    };

    window.saveUser = async function(event) {
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
                await apiCall(`/users/${userId}`, 'PUT', userData);
            } else {
                await apiCall('/users', 'POST', userData);
            }
            alert('User saved successfully!');
            closeUserModal();
            await loadUsers();
        } catch (error) {
            alert('Error saving user: ' + error.message);
        }
    };

    window.editUser = function(id) {
        openUserModal(id);
    };

    window.deleteUser = async function(id) {
        if (!confirm('Are you sure you want to delete this user?')) return;

        try {
            await apiCall(`/users/${id}`, 'DELETE');
            alert('User deleted successfully!');
            await loadUsers();
        } catch (error) {
            alert('Error deleting user: ' + error.message);
        }
    };

    // Role Functions
    window.openRoleModal = function(roleId = null) {
        const modal = document.getElementById('roleModal');
        const title = document.getElementById('roleModalTitle');
        const form = document.getElementById('roleForm');
        const checklist = document.getElementById('rolePermissionsChecklist');

        // Populate permissions checklist
        checklist.innerHTML = permissions.map(permission => `
            <div class="permission-checkbox-item">
                <input type="checkbox" id="perm_${permission.id}" value="${permission.id}" name="permissions[]">
                <label for="perm_${permission.id}">${permission.display_name} <small>(${permission.name})</small></label>
            </div>
        `).join('');

        if (roleId) {
            title.textContent = 'Edit Role';
            // Load role data - would need additional API call
        } else {
            title.textContent = 'Add New Role';
            form.reset();
            document.getElementById('roleId').value = '';
        }

        modal.classList.add('active');
    };

    window.closeRoleModal = function() {
        document.getElementById('roleModal').classList.remove('active');
        document.getElementById('roleForm').reset();
    };

    window.saveRole = async function(event) {
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
                await apiCall(`/roles/${roleId}`, 'PUT', roleData);
            } else {
                await apiCall('/roles', 'POST', roleData);
            }
            alert('Role saved successfully!');
            closeRoleModal();
            await loadRoles();
        } catch (error) {
            alert('Error saving role: ' + error.message);
        }
    };

    window.editRole = function(id) {
        openRoleModal(id);
    };

    window.deleteRole = async function(id) {
        if (!confirm('Are you sure you want to delete this role?')) return;

        try {
            await apiCall(`/roles/${id}`, 'DELETE');
            alert('Role deleted successfully!');
            await loadRoles();
        } catch (error) {
            alert('Error deleting role: ' + error.message);
        }
    };

    // Permission Functions
    window.openPermissionModal = function(permissionId = null) {
        const modal = document.getElementById('permissionModal');
        const title = document.getElementById('permissionModalTitle');
        const form = document.getElementById('permissionForm');

        if (permissionId) {
            title.textContent = 'Edit Permission';
            const permission = permissions.find(p => p.id === permissionId);
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
    };

    window.closePermissionModal = function() {
        document.getElementById('permissionModal').classList.remove('active');
        document.getElementById('permissionForm').reset();
    };

    window.savePermission = async function(event) {
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
                await apiCall(`/permissions/${permissionId}`, 'PUT', permissionData);
            } else {
                await apiCall('/permissions', 'POST', permissionData);
            }
            alert('Permission saved successfully!');
            closePermissionModal();
            await loadPermissions();
        } catch (error) {
            alert('Error saving permission: ' + error.message);
        }
    };

    window.editPermission = function(id) {
        openPermissionModal(id);
    };

    window.deletePermission = async function(id) {
        if (!confirm('Are you sure you want to delete this permission?')) return;

        try {
            await apiCall(`/permissions/${id}`, 'DELETE');
            alert('Permission deleted successfully!');
            await loadPermissions();
        } catch (error) {
            alert('Error deleting permission: ' + error.message);
        }
    };

    // Filter Functions
    window.filterUsers = function() {
        const search = document.getElementById('userSearch').value.toLowerCase();
        const filtered = users.filter(user => 
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
    };

    // Initialize
    document.addEventListener('DOMContentLoaded', async function() {
        await Promise.all([
            loadUsers(),
            loadRoles(),
            loadPermissions(),
            loadStats()
        ]);
    });
})();
