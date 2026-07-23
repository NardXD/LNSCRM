<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeEmail;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    /**
     * Display the user management page.
     */
    public function index()
    {
        $users = User::with(['company', 'roles'])->orderBy('name')->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roles->first() ? $user->roles->first()->name : ($user->is_admin ? 'Admin' : 'User'),
                'role_id' => $user->roles->first() ? $user->roles->first()->id : null,
                'company' => $user->company ? $user->company->name : 'System',
                'status' => $user->status ?? 'active',
                'last_login' => $user->updated_at ? $user->updated_at->diffForHumans() : 'Never',
            ];
        })->values()->all();

        $roles = Role::where('is_active', true)->withCount('users')->get();
        $permissions = Permission::orderBy('category')->orderBy('name')->get();

        $user = Auth::user();
        $canCreateUser = $user->hasPermission('admin_user_management_create_user');
        $canEditUser = $user->hasPermission('admin_user_management_edit_user');
        $canDeleteUser = $user->hasPermission('admin_user_management_delete_user');
        $canCreateRole = $user->hasPermission('admin_user_management_create_role');
        $canEditRole = $user->hasPermission('admin_user_management_edit_role');
        $canDeleteRole = $user->hasPermission('admin_user_management_delete_role');
        $canCreatePermission = $user->hasPermission('admin_user_management_create_permission');
        $canEditPermission = $user->hasPermission('admin_user_management_edit_permission');
        $canDeletePermission = $user->hasPermission('admin_user_management_delete_permission');

        return view('admin.user-management', compact(
            'users', 'roles', 'permissions',
            'canCreateUser', 'canEditUser', 'canDeleteUser',
            'canCreateRole', 'canEditRole', 'canDeleteRole',
            'canCreatePermission', 'canEditPermission', 'canDeletePermission'
        ));
    }

    /**
     * API: Get all users
     */
    public function apiUsers()
    {
        $users = User::with(['company', 'roles'])->orderBy('name')->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roles->first() ? $user->roles->first()->name : ($user->is_admin ? 'Admin' : 'User'),
                'role_id' => $user->roles->first() ? $user->roles->first()->id : null,
                'company' => $user->company ? $user->company->name : 'System',
                'status' => $user->status ?? 'active',
                'last_login' => $user->updated_at ? $user->updated_at->diffForHumans() : 'Never',
            ];
        });

        return response()->json($users);
    }

    /**
     * API: Get single user
     */
    public function apiGetUser(User $user)
    {
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->roles->first() ? $user->roles->first()->name : ($user->is_admin ? 'Admin' : 'User'),
            'role_id' => $user->roles->first() ? $user->roles->first()->id : null,
            'company' => $user->company ? $user->company->name : 'System',
            'status' => $user->status ?? 'active',
            'last_login' => $user->updated_at ? $user->updated_at->diffForHumans() : 'Never',
        ]);
    }

    /**
     * API: Store a new user
     */
    public function apiStoreUser(Request $request)
    {
        if (! Auth::user()->hasPermission('admin_user_management_create_user')) {
            abort(403, 'You do not have permission to create users.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role_id' => 'nullable|exists:roles,id',
            'status' => 'nullable|in:active,inactive,suspended',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'status' => $validated['status'] ?? 'active',
        ]);

        if ($validated['role_id']) {
            $user->roles()->attach($validated['role_id']);
        }

        // Send welcome email
        try {
            Mail::to($user->email)->send(new WelcomeEmail(
                userName: $user->name,
                userEmail: $user->email,
                loginUrl: route('admin.login'),
                companyName: config('app.name'),
                temporaryPassword: $validated['password'],
            ));
        } catch (\Exception $e) {
            Log::warning('Welcome email failed for new admin user', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'User created successfully.',
            'data' => $user->load('roles'),
        ]);
    }

    /**
     * API: Update a user
     */
    public function apiUpdateUser(Request $request, User $user)
    {
        if (! Auth::user()->hasPermission('admin_user_management_edit_user')) {
            abort(403, 'You do not have permission to edit users.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8',
            'role_id' => 'nullable|exists:roles,id',
            'status' => 'nullable|in:active,inactive,suspended',
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->status = $validated['status'] ?? $user->status;

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        // Update role
        if (isset($validated['role_id'])) {
            $user->roles()->sync([$validated['role_id']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
            'data' => $user->load('roles'),
        ]);
    }

    /**
     * API: Delete a user
     */
    public function apiDestroyUser(User $user)
    {
        if (! Auth::user()->hasPermission('admin_user_management_delete_user')) {
            abort(403, 'You do not have permission to delete users.');
        }

        $user->roles()->detach();
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully.',
        ]);
    }

    /**
     * API: Get all roles
     */
    public function apiRoles()
    {
        $roles = Role::with('permissions')->withCount('users')->orderBy('name')->get()->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'users_count' => $role->users_count,
                'is_active' => $role->is_active,
                'permissions' => $role->permissions->pluck('id')->toArray(),
            ];
        });

        return response()->json($roles);
    }

    /**
     * API: Get single role with permissions
     */
    public function apiGetRole(Role $role)
    {
        return response()->json([
            'id' => $role->id,
            'name' => $role->name,
            'slug' => $role->slug,
            'description' => $role->description,
            'is_active' => $role->is_active,
            'permissions' => $role->permissions->pluck('id')->toArray(),
        ]);
    }

    /**
     * API: Store a new role
     */
    public function apiStoreRole(Request $request)
    {
        if (! Auth::user()->hasPermission('admin_user_management_create_role')) {
            abort(403, 'You do not have permission to create roles.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'slug' => 'nullable|string|max:255|unique:roles,slug',
            'description' => 'nullable|string',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $validated['slug'] = $validated['slug'] ?? \Str::slug($validated['name']);

        $role = Role::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
        ]);

        if (! empty($validated['permission_ids'])) {
            $role->permissions()->attach($validated['permission_ids']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully.',
            'data' => $role->load('permissions'),
        ]);
    }

    /**
     * API: Update a role
     */
    public function apiUpdateRole(Request $request, Role $role)
    {
        if (! Auth::user()->hasPermission('admin_user_management_edit_role')) {
            abort(403, 'You do not have permission to edit roles.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles')->ignore($role->id)],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('roles')->ignore($role->id)],
            'description' => 'nullable|string',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $role->name = $validated['name'];
        $role->slug = $validated['slug'] ?? $role->slug;
        $role->description = $validated['description'] ?? $role->description;
        $role->save();

        if (isset($validated['permission_ids'])) {
            $role->permissions()->sync($validated['permission_ids']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully.',
            'data' => $role->load('permissions'),
        ]);
    }

    /**
     * API: Delete a role
     */
    public function apiDestroyRole(Role $role)
    {
        if (! Auth::user()->hasPermission('admin_user_management_delete_role')) {
            abort(403, 'You do not have permission to delete roles.');
        }

        $role->permissions()->detach();
        $role->users()->detach();
        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully.',
        ]);
    }

    /**
     * API: Get all permissions
     */
    public function apiPermissions()
    {
        $permissions = Permission::orderBy('category')->orderBy('name')->get()->map(function ($permission) {
            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'slug' => $permission->slug,
                'display_name' => $permission->display_name,
                'description' => $permission->description,
                'category' => $permission->category,
            ];
        });

        return response()->json($permissions);
    }

    /**
     * API: Store a new permission
     */
    public function apiStorePermission(Request $request)
    {
        if (! Auth::user()->hasPermission('admin_user_management_create_permission')) {
            abort(403, 'You do not have permission to create permissions.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name',
            'slug' => 'nullable|string|max:255|unique:permissions,slug',
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:255',
        ]);

        $validated['slug'] = $validated['slug'] ?? \Str::slug($validated['name']);

        $permission = Permission::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Permission created successfully.',
            'data' => $permission,
        ]);
    }

    /**
     * API: Update a permission
     */
    public function apiUpdatePermission(Request $request, Permission $permission)
    {
        if (! Auth::user()->hasPermission('admin_user_management_edit_permission')) {
            abort(403, 'You do not have permission to edit permissions.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('permissions')->ignore($permission->id)],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('permissions')->ignore($permission->id)],
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:255',
        ]);

        $permission->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Permission updated successfully.',
            'data' => $permission,
        ]);
    }

    /**
     * API: Delete a permission
     */
    public function apiDestroyPermission(Permission $permission)
    {
        if (! Auth::user()->hasPermission('admin_user_management_delete_permission')) {
            abort(403, 'You do not have permission to delete permissions.');
        }

        $permission->roles()->detach();
        $permission->delete();

        return response()->json([
            'success' => true,
            'message' => 'Permission deleted successfully.',
        ]);
    }

    /**
     * API: Get stats
     */
    public function apiStats()
    {
        return response()->json([
            'total_users' => User::count(),
            'total_roles' => Role::count(),
            'total_permissions' => Permission::count(),
            'active_sessions' => 0, // This would require session tracking
        ]);
    }
}
