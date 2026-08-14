<?php

namespace App\Http\Controllers;

use App\Mail\WelcomeEmail;
use App\Models\Client;
use App\Models\Company;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SalesRep;
use App\Models\User;
use App\Services\TwilioNumberAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    /**
     * Display the user management page.
     */
    public function index()
    {
        $user = Auth::user();

        // Ensure user exists
        if (! $user) {
            abort(403, 'Unauthorized');
        }

        $company = $user->company;

        // Get users from the same company (handle null company_id)
        $usersQuery = User::query();
        if ($user->company_id) {
            $usersQuery->where('company_id', $user->company_id);
        } else {
            // If no company_id, return empty or handle differently
            $usersQuery->where('id', 0); // Return no users
        }
        $users = $usersQuery->with(['role', 'roles'])
            ->orderBy('name')
            ->get();

        // Get roles for the company
        $rolesQuery = Role::where('is_active', true);
        if ($user->company_id) {
            $rolesQuery->where('company_id', $user->company_id);
        } else {
            $rolesQuery->where('id', 0); // Return no roles if no company
        }

        $roles = $rolesQuery->withCount(['usersWithRole' => function ($query) use ($user) {
            if ($user->company_id) {
                $query->where('company_id', $user->company_id);
            } else {
                $query->where('id', 0); // Return 0 count
            }
        }])
            ->with('permissions')
            ->get();

        // Get permissions for the company grouped by category (sidebar modules only for RBAC)
        $permissionsQuery = Permission::query();
        if ($user->company_id) {
            $permissionsQuery->where('company_id', $user->company_id);
        } else {
            $permissionsQuery->where('id', 0); // Return no permissions if no company
        }

        // Filter for sidebar permissions (category = 'main' or 'settings')
        $permissions = $permissionsQuery
            ->whereIn('category', ['main', 'settings'])
            ->orderBy('category')
            ->orderBy('display_name')
            ->get()
            ->groupBy('category');

        // Get company settings
        $companySettings = [
            'timezone' => $company->timezone ?? 'America/New_York',
            'date_format' => 'MM-DD-YYYY',
            'currency' => 'USD',
            'language' => 'en',
        ];

        // Load other settings from system_settings if available
        if ($company && $company->id) {
            $settings = \App\Models\SystemSetting::where('group', 'company_'.$company->id)
                ->whereNotIn('key', ['timezone']) // Exclude timezone as it's now in companies table
                ->pluck('value', 'key')
                ->toArray();
            $companySettings = array_merge($companySettings, $settings);
        }

        // Ensure all variables are set with defaults
        $users = $users ?? collect();
        $roles = $roles ?? collect();
        $permissions = $permissions ?? collect();
        $company = $company ?? null;
        $companySettings = $companySettings ?? [];

        return view('dashboard.user-management', compact(
            'users',
            'roles',
            'permissions',
            'company',
            'companySettings'
        ));
    }

    /**
     * API: Twilio numbers available for employee assignment
     */
    public function getTwilioNumberOptions(Request $request)
    {
        $user = Auth::user();
        $forEmployeeId = $request->integer('employee_id') ?: null;

        return response()->json([
            'success' => true,
            'data' => app(TwilioNumberAssignmentService::class)->optionsForCompany(
                (int) $user->company_id,
                $forEmployeeId
            ),
        ]);
    }

    /**
     * API: Get employees (users from same company)
     */
    public function getEmployees(Request $request)
    {
        $user = Auth::user();
        $perPage = $request->get('per_page', 10);
        $page = $request->get('page', 1);

        $query = User::where('company_id', $user->company_id)
            ->with(['role', 'roles', 'department', 'clients', 'salesRep']);

        // Search filter
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Role filter - use direct role_id field
        if ($request->has('role_id') && $request->role_id) {
            $query->where('role_id', $request->role_id);
        }

        $total = $query->count();
        $users = $query->orderBy('name')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(function ($user) {
                // Use direct role relationship first (company-specific), fallback to roles relationship or admin check
                $role = $user->role ?? $user->roles->first();

                return [
                    'id' => $user->id,
                    'employee_id' => 'EMP'.str_pad($user->id, 3, '0', STR_PAD_LEFT),
                    'name' => $user->name,
                    'initials' => $this->getInitials($user->name),
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'address' => $user->address,
                    'date_of_birth' => $user->date_of_birth ? $user->date_of_birth->format('Y-m-d') : null,
                    'employment_date' => $user->employment_date ? $user->employment_date->format('Y-m-d') : null,
                    'photo' => $user->photo ? asset('storage/'.$user->photo) : null,
                    'salary' => $user->salary,
                    'allowances' => $user->allowances ?? 0,
                    'client_invoice_amount' => $user->client_invoice_amount,
                    'twilio_number' => $user->twilio_number,
                    'wise_account' => $user->wise_account,
                    'wise_currency' => $user->wise_currency,
                    'required_work_hours' => $user->required_work_hours,
                    'recording_duration_minutes' => $user->recording_duration_minutes,
                    'role' => $role ? $role->name : ($user->is_admin ? 'Administrator' : 'Employee'),
                    'role_type' => $role ? strtolower($role->slug) : ($user->is_admin ? 'admin' : 'employee'),
                    'role_id' => $user->role_id ?? ($role ? $role->id : null),
                    'department' => $user->department ? $user->department->name : 'General',
                    'department_id' => $user->department_id,
                    'status' => $user->status ?? 'active',
                    'clients' => $user->clients->map(fn ($c) => $c->name)->values()->toArray(),
                    'client_ids' => $user->clients->pluck('id')->values()->toArray(),
                    'sales_rep_id' => $user->sales_rep_id,
                    'sales_rep_name' => $user->salesRep?->name,
                    'sales_rep_commission_type' => $user->sales_rep_commission_type,
                    'sales_rep_commission_value' => $user->sales_rep_commission_value !== null ? (float) $user->sales_rep_commission_value : null,
                ];
            });

        return response()->json([
            'data' => $users,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => ceil($total / $perPage),
        ]);
    }

    /**
     * API: List sales reps for the company (separate from employees).
     */
    public function getSalesReps(Request $request)
    {
        $user = Auth::user();
        $perPage = (int) $request->get('per_page', 10);
        $page = (int) $request->get('page', 1);

        $query = SalesRep::where('company_id', $user->company_id);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $total = $query->count();
        $rows = $query->orderBy('name')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(fn (SalesRep $rep) => [
                'id' => $rep->id,
                'name' => $rep->name,
                'initials' => $this->getInitials($rep->name),
                'email' => $rep->email,
                'phone' => $rep->phone,
            ]);

        return response()->json([
            'data' => $rows,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
        ]);
    }

    /**
     * API: Create a sales rep record.
     */
    public function storeSalesRep(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('sales_reps', 'email')->where(fn ($q) => $q->where('company_id', $user->company_id)),
            ],
            'phone' => 'nullable|string|max:255',
        ]);

        $rep = SalesRep::create([
            'company_id' => $user->company_id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Sales rep created.',
            'data' => $rep,
        ], 201);
    }

    /**
     * API: Update a sales rep.
     */
    public function updateSalesRep(Request $request, SalesRep $salesRep)
    {
        $user = Auth::user();

        if ($salesRep->company_id !== $user->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('sales_reps', 'email')
                    ->where(fn ($q) => $q->where('company_id', $user->company_id))
                    ->ignore($salesRep->id),
            ],
            'phone' => 'nullable|string|max:255',
        ]);

        $salesRep->fill($validated);
        $salesRep->save();

        return response()->json([
            'success' => true,
            'message' => 'Sales rep updated.',
            'data' => $salesRep->fresh(),
        ]);
    }

    /**
     * API: Delete a sales rep.
     */
    public function destroySalesRep(SalesRep $salesRep)
    {
        $user = Auth::user();

        if ($salesRep->company_id !== $user->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

        $salesRep->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sales rep removed.',
        ]);
    }

    /**
     * API: Get departments for the company
     */
    public function getDepartments(Request $request)
    {
        $user = Auth::user();

        $departments = Department::where('company_id', $user->company_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'description']);

        return response()->json([
            'success' => true,
            'data' => $departments,
        ]);
    }

    /**
     * API: Store a new department
     */
    public function storeDepartment(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $department = Department::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'company_id' => $user->company_id,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Department created successfully.',
            'data' => $department,
        ]);
    }

    /**
     * API: Update a department
     */
    public function updateDepartment(Request $request, Department $department)
    {
        $user = Auth::user();

        // Ensure department belongs to the same company
        if ($department->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $department->name = $validated['name'];
        $department->description = $validated['description'] ?? null;
        $department->save();

        return response()->json([
            'success' => true,
            'message' => 'Department updated successfully.',
            'data' => $department,
        ]);
    }

    /**
     * API: Delete a department
     */
    public function destroyDepartment(Department $department)
    {
        $user = Auth::user();

        // Ensure department belongs to the same company
        if ($department->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.',
            ], 403);
        }

        // Check if department has users
        $userCount = $department->users()->count();
        if ($userCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete department. There are {$userCount} employee(s) assigned to this department.",
            ], 422);
        }

        // Soft delete by setting is_active to false instead of actually deleting
        $department->is_active = false;
        $department->save();

        return response()->json([
            'success' => true,
            'message' => 'Department deleted successfully.',
        ]);
    }

    /**
     * API: Get roles with permission counts
     */
    public function getRoles()
    {
        $user = Auth::user();

        $rolesQuery = Role::where('is_active', true);
        if ($user->company_id) {
            $rolesQuery->where('company_id', $user->company_id);
        } else {
            $rolesQuery->where('id', 0);
        }

        $roles = $rolesQuery->with(['permissions'])
            ->withCount(['usersWithRole' => function ($query) use ($user) {
                if ($user->company_id) {
                    $query->where('company_id', $user->company_id);
                }
            }])
            ->get()
            ->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                    'description' => $role->description,
                    'users_count' => $role->users_with_role_count ?? 0,
                    'permissions_count' => $role->permissions->count(),
                    'permissions' => $role->permissions->pluck('display_name', 'id'),
                ];
            });

        return response()->json($roles);
    }

    /**
     * API: Create a new role
     */
    public function storeRole(Request $request)
    {
        $user = Auth::user();

        if (! $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'You must be associated with a company to create roles.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles')->where(function ($query) use ($user) {
                return $query->where('company_id', $user->company_id);
            })],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('roles')->where(function ($query) use ($user) {
                return $query->where('company_id', $user->company_id);
            })],
            'description' => 'nullable|string',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => ['exists:permissions,id', function ($attribute, $value, $fail) use ($user) {
                $permission = Permission::find($value);
                if ($permission && $permission->company_id !== $user->company_id) {
                    $fail('The selected permission does not belong to your company.');
                }
            }],
        ]);

        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']);
        $validated['is_active'] = true;
        $validated['company_id'] = $user->company_id;

        $role = Role::create($validated);

        if (! empty($validated['permission_ids'])) {
            // Ensure permissions belong to the same company as the role
            $permissionIds = Permission::whereIn('id', $validated['permission_ids'])
                ->where('company_id', $user->company_id)
                ->pluck('id')
                ->toArray();

            // Only attach permissions that belong to the same company
            if (! empty($permissionIds)) {
                $role->permissions()->syncWithoutDetaching($permissionIds);
            }
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
    public function updateRole(Request $request, Role $role)
    {
        $user = Auth::user();

        // Ensure role belongs to the user's company
        if ($role->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this role.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles')->where(function ($query) use ($user) {
                return $query->where('company_id', $user->company_id);
            })->ignore($role->id)],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('roles')->where(function ($query) use ($user) {
                return $query->where('company_id', $user->company_id);
            })->ignore($role->id)],
            'description' => 'nullable|string',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => ['exists:permissions,id', function ($attribute, $value, $fail) use ($user) {
                $permission = Permission::find($value);
                if ($permission && $permission->company_id !== $user->company_id) {
                    $fail('The selected permission does not belong to your company.');
                }
            }],
        ]);

        $role->name = $validated['name'];
        if (isset($validated['slug'])) {
            $role->slug = $validated['slug'];
        }
        if (isset($validated['description'])) {
            $role->description = $validated['description'];
        }
        $role->save();

        if (isset($validated['permission_ids'])) {
            // Ensure permissions belong to the same company
            $permissionIds = Permission::whereIn('id', $validated['permission_ids'])
                ->where('company_id', $user->company_id)
                ->pluck('id')
                ->toArray();
            $role->permissions()->sync($permissionIds);
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
    public function destroyRole(Role $role)
    {
        $user = Auth::user();

        // Ensure role belongs to the user's company
        if ($role->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this role.',
            ], 403);
        }

        // Don't allow deleting roles that have users assigned
        if ($role->users()->where('company_id', $user->company_id)->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete role that has users assigned.',
            ], 422);
        }

        $role->permissions()->detach();
        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully.',
        ]);
    }

    /**
     * API: Get permissions grouped by module
     */
    public function getPermissions()
    {
        $user = Auth::user();

        $permissionsQuery = Permission::query();
        if ($user->company_id) {
            $permissionsQuery->where('company_id', $user->company_id);
        } else {
            $permissionsQuery->where('id', 0);
        }

        // Filter for sidebar permissions (category = 'main', 'settings', 'payroll', 'team_management', 'sidebar', or 'Leave Management')
        $allPermissions = $permissionsQuery
            ->whereIn('category', ['main', 'settings', 'payroll', 'team_management', 'sidebar', 'Leave Management'])
            ->orderBy('display_name')
            ->get();

        // Map permissions to their modules (display name => [permission slugs])
        // Module slug links to company_modules for filtering which modules a company can use
        $moduleMapping = [
            'Dashboard' => ['view_dashboard', 'module_slug' => 'dashboard'],
            'Time Tracking' => ['view_time_tracking', 'module_slug' => 'time-tracking'],
            'User & Access Management' => [
                'view_user_management',
                'view_user_roles_permissions',
                'view_user_company_setup',
                'view_user_employee_profile',
                'view_user_departments',
                'view_user_role_based_access',
                'module_slug' => 'user-management',
            ],
            'Employee Monitoring' => ['view_employee_monitoring', 'view_live_screen', 'module_slug' => 'employee-monitoring'],
            'Phone System' => ['view_phone_system', 'module_slug' => 'phone-system'],
            'Employee Monitoring' => ['view_employee_monitoring', 'module_slug' => 'employee-monitoring'],
            'Phone System' => [
                'view_phone_system',
                'view_call_history',
                'manage_phone_contacts',
                'manage_twilio_numbers',
                'module_slug' => 'phone-system',
            ],
            'Payroll' => [
                'view_payroll',
                'view_time_in_out',
                'edit_time_in_out',
                'export_time_in_out',
                'view_payroll_report',
                'view_payroll_sales_rep_report',
                'generate_payroll_report',
                'export_payroll_report',
                'view_saved_for_wise',
                'view_wise_recipients',
                'module_slug' => 'payroll',
            ],
            'P&L' => ['view_pnl', 'module_slug' => 'pnl'],
            'Project Management' => [
                'view_project_management',
                'create_project_management',
                'create_task_management',
                'edit_project_management',
                'delete_project_management',
                'module_slug' => 'project-management',
            ],
            'Team Management' => [
                'view_team_management',
                'create_team_management',
                'edit_team_management',
                'delete_team_management',
                'manage_team_members',
                'view_team_time_tracking',
                'view_team_recordings',
                'module_slug' => 'team-management',
            ],
            'Messaging' => ['view_messaging', 'module_slug' => 'messaging'],
            'Inbox' => [
                'view_inbox',
                'create_inbox_tags',
                'create_inbox_templates',
                'create_inbox_rules',
                'module_slug' => 'inbox',
            ],
            'Viber' => ['view_viber', 'module_slug' => 'viber'],
            'WhatsApp' => ['view_whatsapp', 'module_slug' => 'whatsapp'],
            'Facebook & Instagram' => ['view_facebook', 'module_slug' => 'facebook'],
            'SMS' => ['view_sms', 'send_sms', 'module_slug' => 'sms'],
            'Billing & Payments' => ['view_billing', 'delete_billing', 'module_slug' => 'billing'],
            'Client Management' => ['view_client_management', 'module_slug' => 'client-management'],
            'Tickets & Helpdesk' => ['view_tickets', 'module_slug' => 'tickets'],
            'Knowledge Base' => [
                'view_knowledge_base',
                'create_knowledge_base',
                'edit_knowledge_base',
                'delete_knowledge_base',
                'module_slug' => 'knowledge-base',
            ],
            'Integrations' => ['view_integrations', 'module_slug' => 'integrations'],
            'Quotation Builder' => ['view_quotation_builder', 'module_slug' => 'quotation-builder'],
            'Contracts & E-Sign' => [
                'view_contracts',
                'create_contracts',
                'send_contracts',
                'delete_contracts',
                'module_slug' => 'contracts',
            ],
            'Calendar' => ['view_calendar', 'module_slug' => 'calendar'],
            'Email Tracking' => ['view_email_tracking', 'module_slug' => 'email-tracking'],
            'AI Assistant' => ['view_ai_assistant', 'module_slug' => 'openai'],
            'Admin Control' => ['view_admin_control', 'module_slug' => null],
            'Leave Management' => [
                'view_leave_management',
                'view_leave_stats',
                'create_leave_request',
                'view_leave_credits',
                'manage_leave_credits',
                'view_leave_calendar',
                'module_slug' => 'leave-management',
            ],
        ];

        // Get company's enabled module slugs - only show RBAC modules the company has access to
        $company = $user->company_id ? $user->company : null;
        $companyModuleSlugs = null;
        if ($user->company_id && ! $user->is_admin && $company) {
            $slugs = $company->modules()
                ->wherePivot('is_enabled', true)
                ->pluck('slug')
                ->toArray();
            // Only filter when company has explicit module config (backward compat: empty = show all)
            if (! empty($slugs)) {
                $companyModuleSlugs = $slugs;
            }
        }

        // Group permissions by module
        $permissionsByModule = [];

        foreach ($moduleMapping as $moduleName => $mapping) {
            $moduleSlug = $mapping['module_slug'] ?? null;
            $slugs = array_filter($mapping, fn ($v, $k) => $k !== 'module_slug' && is_string($v), ARRAY_FILTER_USE_BOTH);

            if ($moduleSlug === 'pnl' && ! $this->companyMayAssignPnlPermissions($user)) {
                continue;
            }

            // Skip modules the company doesn't have access to
            if ($companyModuleSlugs !== null) {
                if ($moduleSlug === null) {
                    continue; // Admin Control etc. - no module, hide from company RBAC
                }
                if (! in_array($moduleSlug, $companyModuleSlugs)) {
                    continue;
                }
            }
            $modulePermissions = $allPermissions->filter(function ($perm) use ($slugs) {
                return in_array($perm->slug, $slugs);
            })->map(function ($perm) {
                return [
                    'id' => $perm->id,
                    'name' => $perm->name,
                    'slug' => $perm->slug,
                    'display_name' => $perm->display_name,
                    'description' => $perm->description,
                    'category' => $perm->category,
                ];
            })->values()->toArray();

            if (! empty($modulePermissions)) {
                $permissionsByModule[$moduleName] = $modulePermissions;
            }
        }

        // Add any remaining permissions that don't match known modules to "Other"
        $matchedSlugs = collect($moduleMapping)->flatten()->toArray();
        $remainingPermissions = $allPermissions->filter(function ($perm) use ($matchedSlugs) {
            return ! in_array($perm->slug, $matchedSlugs);
        });

        if ($remainingPermissions->isNotEmpty()) {
            $permissionsByModule['Other'] = $remainingPermissions->map(function ($perm) {
                return [
                    'id' => $perm->id,
                    'name' => $perm->name,
                    'slug' => $perm->slug,
                    'display_name' => $perm->display_name,
                    'description' => $perm->description,
                    'category' => $perm->category,
                ];
            })->values()->toArray();
        }

        return response()->json($permissionsByModule);
    }

    /**
     * API: Get role permissions
     */
    public function getRolePermissions(Role $role)
    {
        $user = Auth::user();

        // Ensure role belongs to the user's company
        if ($role->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this role.',
            ], 403);
        }

        // Only get permissions that belong to the same company
        $permissions = $role->permissions()
            ->where('company_id', $user->company_id)
            ->orderBy('category')
            ->orderBy('display_name')
            ->get();

        if (! $this->companyMayAssignPnlPermissions($user)) {
            $permissions = $permissions->reject(fn ($p) => $p->slug === 'view_pnl')->values();
        }

        return response()->json([
            'role_id' => $role->id,
            'permissions' => $permissions,
        ]);
    }

    /**
     * API: Update role permissions
     */
    public function updateRolePermissions(Request $request, Role $role)
    {
        $user = Auth::user();

        // Ensure role belongs to the user's company
        if ($role->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this role.',
            ], 403);
        }

        $validated = $request->validate([
            'permission_ids' => 'required|array',
            'permission_ids.*' => ['exists:permissions,id', function ($attribute, $value, $fail) use ($user, $role) {
                $permission = Permission::find($value);
                if (! $permission) {
                    $fail('The selected permission does not exist.');

                    return;
                }
                if ($permission->company_id !== $user->company_id) {
                    $fail('The selected permission does not belong to your company.');

                    return;
                }
                if ($role->company_id !== $user->company_id) {
                    $fail('The role does not belong to your company.');

                    return;
                }
            }],
        ]);

        // Ensure all permissions belong to the same company as the role
        $permissionIds = Permission::whereIn('id', $validated['permission_ids'])
            ->where('company_id', $user->company_id)
            ->pluck('id')
            ->toArray();

        if (! $this->companyMayAssignPnlPermissions($user)) {
            $viewPnlId = Permission::where('slug', 'view_pnl')
                ->where('company_id', $user->company_id)
                ->value('id');
            if ($viewPnlId) {
                $permissionIds = array_values(array_diff($permissionIds, [(int) $viewPnlId]));
            }
        }

        // Get permission slugs to check if any payroll sub-module permissions are assigned
        $assignedPermissionSlugs = Permission::whereIn('id', $permissionIds)
            ->where('company_id', $user->company_id)
            ->pluck('slug')
            ->toArray();

        // Payroll sub-module permission slugs
        $payrollSubModuleSlugs = [
            'view_time_in_out',
            'edit_time_in_out',
            'export_time_in_out',
            'view_payroll_report',
            'view_payroll_sales_rep_report',
            'generate_payroll_report',
            'export_payroll_report',
            'view_saved_for_wise',
            'view_wise_recipients',
        ];

        // If any payroll sub-module permission is assigned, automatically add view_payroll
        $hasPayrollSubModule = ! empty(array_intersect($assignedPermissionSlugs, $payrollSubModuleSlugs));
        if ($hasPayrollSubModule) {
            $viewPayrollPermission = Permission::where('slug', 'view_payroll')
                ->where('company_id', $user->company_id)
                ->first();

            if ($viewPayrollPermission && ! in_array($viewPayrollPermission->id, $permissionIds)) {
                $permissionIds[] = $viewPayrollPermission->id;
            }
        }

        // User Management sub-module permission slugs
        $userManagementSubModuleSlugs = [
            'view_user_roles_permissions',
            'view_user_company_setup',
            'view_user_employee_profile',
            'view_user_departments',
            'view_user_role_based_access',
        ];

        // If any user management sub-module permission is assigned, automatically add view_user_management
        $hasUserManagementSubModule = ! empty(array_intersect($assignedPermissionSlugs, $userManagementSubModuleSlugs));
        if ($hasUserManagementSubModule) {
            $viewUserManagementPermission = Permission::where('slug', 'view_user_management')
                ->where('company_id', $user->company_id)
                ->first();

            if ($viewUserManagementPermission && ! in_array($viewUserManagementPermission->id, $permissionIds)) {
                $permissionIds[] = $viewUserManagementPermission->id;
            }
        }

        // Sync only company-scoped permissions
        $role->permissions()->sync($permissionIds);

        return response()->json([
            'success' => true,
            'message' => 'Permissions updated successfully.',
            'data' => $role->load('permissions'),
        ]);
    }

    /**
     * API: Create a new employee
     */
    public function storeEmployee(Request $request)
    {
        $user = Auth::user();

        $this->normalizeEmployeeSalesRepRequest($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role_id' => ['nullable', 'exists:roles,id', function ($attribute, $value, $fail) use ($user) {
                if ($value) {
                    $role = Role::find($value);
                    if ($role && $role->company_id !== $user->company_id) {
                        $fail('The selected role does not belong to your company.');
                    }
                }
            }],
            'department_id' => ['nullable', 'exists:departments,id', function ($attribute, $value, $fail) use ($user) {
                if ($value) {
                    $department = Department::find($value);
                    if ($department && $department->company_id !== $user->company_id) {
                        $fail('The selected department does not belong to your company.');
                    }
                }
            }],
            'status' => 'nullable|in:active,inactive,suspended',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'employment_date' => 'nullable|date',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'salary' => 'required|numeric|min:0',
            'allowances' => 'required|numeric|min:0',
            'client_invoice_amount' => 'required|numeric|min:0',
            'twilio_number' => 'nullable|string|max:255',
            'wise_account' => 'nullable|string|max:255',
            'required_work_hours' => 'required|numeric|min:0|max:999',
            'recording_duration_minutes' => 'nullable|numeric|min:0.1|max:120',
            'sales_rep_id' => [
                'nullable',
                'integer',
                Rule::exists('sales_reps', 'id')->where(fn ($q) => $q->where('company_id', $user->company_id)),
            ],
            'sales_rep_commission_type' => [
                Rule::requiredIf(fn () => $request->filled('sales_rep_id')),
                'nullable',
                'in:percent,usd',
            ],
            'sales_rep_commission_value' => [
                Rule::requiredIf(fn () => $request->filled('sales_rep_id')),
                'nullable',
                'numeric',
                'min:0',
            ],
            'client_ids' => 'nullable|array',
            'client_ids.*' => [
                'integer',
                Rule::exists('clients', 'id')->where(fn ($q) => $q->where('company_id', $user->company_id)),
            ],
        ]);

        if ($request->filled('sales_rep_id')
            && ($validated['sales_rep_commission_type'] ?? '') === 'percent'
            && (float) ($validated['sales_rep_commission_value'] ?? 0) > 100) {
            return response()->json([
                'success' => false,
                'message' => 'Commission percentage cannot exceed 100.',
            ], 422);
        }

        // Handle photo upload
        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('employees/photos', 'public');
        }

        $employee = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'company_id' => $user->company_id,
            'role_id' => $validated['role_id'] ?? null,
            'department_id' => $validated['department_id'] ?? null,
            'status' => $validated['status'] ?? 'active',
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'employment_date' => $validated['employment_date'] ?? null,
            'photo' => $photoPath,
            'salary' => $validated['salary'] ?? null,
            'allowances' => $validated['allowances'] ?? 0,
            'client_invoice_amount' => $validated['client_invoice_amount'],
            'wise_account' => $validated['wise_account'] ?? null,
            'required_work_hours' => $validated['required_work_hours'],
            'recording_duration_minutes' => $validated['recording_duration_minutes'] ?? 0.5,
            'sales_rep_id' => $validated['sales_rep_id'] ?? null,
            'sales_rep_commission_type' => $validated['sales_rep_commission_type'] ?? null,
            'sales_rep_commission_value' => $validated['sales_rep_commission_value'] ?? null,
        ]);

        // Also attach to roles relationship for backward compatibility
        if (! empty($validated['role_id'])) {
            $employee->roles()->syncWithoutDetaching([$validated['role_id']]);
        }

        $employee->clients()->sync($validated['client_ids'] ?? []);

        try {
            app(TwilioNumberAssignmentService::class)->assignToUser($employee, $request->input('twilio_number'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        }

        $employee->refresh();

        // Send welcome email
        try {
            $company = Company::find($user->company_id);
            $loginUrl = $company
                ? url('/login')
                : route('login');

            Mail::to($employee->email)->send(new WelcomeEmail(
                userName: $employee->name,
                userEmail: $employee->email,
                loginUrl: $loginUrl,
                companyName: $company->name ?? config('app.name'),
                temporaryPassword: $validated['password'],
            ));
        } catch (\Exception $e) {
            Log::warning('Welcome email failed for new employee', [
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Employee created successfully.',
            'data' => $employee->load(['roles', 'role']),
        ]);
    }

    /**
     * API: Update an employee
     */
    public function updateEmployee(Request $request, User $employee)
    {
        $user = Auth::user();

        // Ensure employee belongs to the same company
        if ($employee->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.',
            ], 403);
        }

        $this->normalizeEmployeeSalesRepRequest($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($employee->id)],
            'password' => 'nullable|string|min:8',
            'role_id' => ['nullable', 'exists:roles,id', function ($attribute, $value, $fail) use ($user) {
                if ($value) {
                    $role = Role::find($value);
                    if ($role && $role->company_id !== $user->company_id) {
                        $fail('The selected role does not belong to your company.');
                    }
                }
            }],
            'department_id' => ['nullable', 'exists:departments,id', function ($attribute, $value, $fail) use ($user) {
                if ($value) {
                    $department = Department::find($value);
                    if ($department && $department->company_id !== $user->company_id) {
                        $fail('The selected department does not belong to your company.');
                    }
                }
            }],
            'status' => 'nullable|in:active,inactive,suspended',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'employment_date' => 'nullable|date',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'salary' => 'required|numeric|min:0',
            'allowances' => 'required|numeric|min:0',
            'client_invoice_amount' => 'required|numeric|min:0',
            'twilio_number' => 'nullable|string|max:255',
            'wise_account' => 'nullable|string|max:255',
            'required_work_hours' => 'required|numeric|min:0|max:999',
            'recording_duration_minutes' => 'nullable|numeric|min:0.1|max:120',
            'sales_rep_id' => [
                'nullable',
                'integer',
                Rule::exists('sales_reps', 'id')->where(fn ($q) => $q->where('company_id', $user->company_id)),
            ],
            'sales_rep_commission_type' => [
                Rule::requiredIf(fn () => $request->filled('sales_rep_id')),
                'nullable',
                'in:percent,usd',
            ],
            'sales_rep_commission_value' => [
                Rule::requiredIf(fn () => $request->filled('sales_rep_id')),
                'nullable',
                'numeric',
                'min:0',
            ],
            'client_ids' => 'nullable|array',
            'client_ids.*' => [
                'integer',
                Rule::exists('clients', 'id')->where(fn ($q) => $q->where('company_id', $user->company_id)),
            ],
        ]);

        if ($request->filled('sales_rep_id')
            && ($validated['sales_rep_commission_type'] ?? '') === 'percent'
            && (float) ($validated['sales_rep_commission_value'] ?? 0) > 100) {
            return response()->json([
                'success' => false,
                'message' => 'Commission percentage cannot exceed 100.',
            ], 422);
        }

        $employee->name = $validated['name'];
        $employee->email = $validated['email'];

        // Fields that can be cleared - normalize empty strings to null
        // These fields are always updated, allowing users to clear them by leaving blank
        $employee->department_id = ! empty($validated['department_id']) ? $validated['department_id'] : null;
        $employee->phone = ! empty($validated['phone']) ? $validated['phone'] : null;
        $employee->address = ! empty($validated['address']) ? $validated['address'] : null;
        $employee->wise_account = ! empty($validated['wise_account']) ? $validated['wise_account'] : null;
        $employee->required_work_hours = $validated['required_work_hours'];

        if (array_key_exists('recording_duration_minutes', $validated) && $validated['recording_duration_minutes'] !== null) {
            $employee->recording_duration_minutes = $validated['recording_duration_minutes'];
        }

        // Fields that preserve existing if not provided
        $employee->status = $validated['status'] ?? $employee->status;
        $employee->date_of_birth = $validated['date_of_birth'] ?? $employee->date_of_birth;
        $employee->employment_date = $validated['employment_date'] ?? $employee->employment_date;

        // Required fields
        $employee->salary = $validated['salary'] ?? $employee->salary;
        $employee->allowances = $validated['allowances'] ?? 0;

        // Optional billing field
        $employee->client_invoice_amount = $validated['client_invoice_amount'];

        // Only update password if provided (not empty)
        if (! empty($validated['password'])) {
            $employee->password = Hash::make($validated['password']);
        }

        // Handle photo upload
        if ($request->hasFile('photo')) {
            // Delete old photo if exists
            if ($employee->photo && Storage::disk('public')->exists($employee->photo)) {
                Storage::disk('public')->delete($employee->photo);
            }
            $employee->photo = $request->file('photo')->store('employees/photos', 'public');
        }

        // Update primary role_id
        if (isset($validated['role_id'])) {
            $employee->role_id = $validated['role_id'];
            // Also sync to roles relationship for backward compatibility
            $employee->roles()->sync([$validated['role_id']]);
        }

        if ($request->filled('sales_rep_id')) {
            $employee->sales_rep_id = $validated['sales_rep_id'];
            $employee->sales_rep_commission_type = $validated['sales_rep_commission_type'] ?? null;
            $employee->sales_rep_commission_value = $validated['sales_rep_commission_value'] ?? null;
        } else {
            $employee->sales_rep_id = null;
            $employee->sales_rep_commission_type = null;
            $employee->sales_rep_commission_value = null;
        }

        $employee->save();

        // Only touch client assignments when the form submitted them, so other
        // flows updating an employee don't accidentally wipe assigned clients.
        if ($request->has('client_ids') || $request->boolean('clients_submitted')) {
            $employee->clients()->sync($validated['client_ids'] ?? []);
        }

        if ($request->has('twilio_number')) {
            try {
                app(TwilioNumberAssignmentService::class)->assignToUser($employee, $request->input('twilio_number'));
            } catch (\Illuminate\Validation\ValidationException $e) {
                return response()->json([
                    'success' => false,
                    'message' => collect($e->errors())->flatten()->first(),
                    'errors' => $e->errors(),
                ], 422);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Employee updated successfully.',
            'data' => $employee->fresh()->load(['roles', 'role']),
        ]);
    }

    /**
     * API: Delete an employee
     */
    public function destroyEmployee(User $employee)
    {
        $user = Auth::user();

        // Ensure employee belongs to the same company
        if ($employee->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.',
            ], 403);
        }

        // Prevent deleting yourself
        if ($employee->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account.',
            ], 422);
        }

        $employee->roles()->detach();
        $employee->delete();

        return response()->json([
            'success' => true,
            'message' => 'Employee deleted successfully.',
        ]);
    }

    /**
     * API: Get clients assigned to an employee
     */
    public function getEmployeeClients(User $employee)
    {
        $user = Auth::user();

        if ($employee->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.',
            ], 403);
        }

        $clients = $employee->clients()->orderBy('clients.name')->get(['clients.id', 'clients.name', 'clients.email', 'clients.industry', 'clients.status']);

        return response()->json([
            'success' => true,
            'data' => $clients,
        ]);
    }

    /**
     * API: List clients for the company (for assigning to employees).
     */
    public function getClientsList(): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();

        $clients = Client::query()
            ->where('company_id', $user->company_id)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'status']);

        return response()->json([
            'success' => true,
            'data' => $clients,
        ]);
    }

    /**
     * API: Update company settings
     */
    public function updateCompanySettings(Request $request)
    {
        $user = Auth::user();
        $company = $user->company;

        if (! $company) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found.',
            ], 404);
        }

        // Validate basic fields (logo validation handled separately for file upload)
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|nullable|email',
            'phone' => 'sometimes|nullable|string|max:255',
            'website' => 'sometimes|nullable|url|max:255',
            'address' => 'sometimes|nullable|string',
            'timezone' => 'sometimes|nullable|string|max:255',
            'date_format' => 'sometimes|nullable|string|max:255',
            'currency' => 'sometimes|nullable|string|max:10',
            'language' => 'sometimes|nullable|string|max:10',
        ]);

        // Validate logo separately if uploaded
        if ($request->hasFile('logo')) {
            $request->validate([
                'logo' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            ]);
        }

        // Handle logo removal
        if ($request->has('remove_logo') && $request->remove_logo == '1') {
            if ($company->logo && Storage::disk('public')->exists($company->logo)) {
                Storage::disk('public')->delete($company->logo);
            }
            $company->logo = null;
            $company->save();

            return response()->json([
                'success' => true,
                'message' => 'Logo removed successfully.',
                'data' => $company->fresh(),
            ]);
        }

        // Handle logo upload
        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($company->logo && Storage::disk('public')->exists($company->logo)) {
                Storage::disk('public')->delete($company->logo);
            }

            // Store new logo
            $logoPath = $request->file('logo')->store('companies/logos', 'public');
            $validated['logo'] = $logoPath;
        }

        // Update company basic info (including timezone)
        $companyFields = ['name', 'email', 'phone', 'website', 'address', 'logo', 'timezone'];
        $companyData = array_intersect_key($validated, array_flip($companyFields));

        // Always set timezone (default to America/New_York if not provided)
        if (! isset($companyData['timezone']) || empty($companyData['timezone'])) {
            $companyData['timezone'] = 'America/New_York';
        }

        if (! empty($companyData)) {
            $company->update($companyData);
        }

        // Update other company settings in system_settings
        $otherSettingsFields = ['date_format', 'currency', 'language'];
        foreach ($otherSettingsFields as $field) {
            if (isset($validated[$field]) && $validated[$field] !== null) {
                \App\Models\SystemSetting::setValue(
                    $field,
                    $validated[$field],
                    'string',
                    'company_'.$company->id,
                    ucfirst(str_replace('_', ' ', $field))
                );
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Company settings updated successfully.',
            'data' => $company->fresh(),
        ]);
    }

    /**
     * Treat empty sales rep selection as null so validation and storage stay consistent.
     */
    private function normalizeEmployeeSalesRepRequest(Request $request): void
    {
        $raw = $request->input('sales_rep_id');
        if ($raw === null || $raw === '' || $raw === '0') {
            $request->merge([
                'sales_rep_id' => null,
                'sales_rep_commission_type' => null,
                'sales_rep_commission_value' => null,
            ]);
        }
    }

    /**
     * P&L permissions are only exposed/assignable when the company has the pnl module enabled
     * (same rule as sidebar and CheckPermission when company_modules rows exist).
     */
    private function companyMayAssignPnlPermissions(?User $user): bool
    {
        if (! $user || ! $user->company_id) {
            return true;
        }

        $company = $user->company;
        if (! $company || ! $company->modules()->exists()) {
            return true;
        }

        return $company->hasModuleAccess('pnl');
    }

    /**
     * Helper: Get user initials
     */
    private function getInitials($name)
    {
        $words = explode(' ', $name);
        $initials = '';

        foreach ($words as $word) {
            if (! empty($word)) {
                $initials .= strtoupper($word[0]);
            }
        }

        return substr($initials, 0, 2);
    }
}
