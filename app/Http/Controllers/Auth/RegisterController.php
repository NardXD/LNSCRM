<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Mail\WelcomeEmail;
use App\Services\AiSettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    public function __construct(protected AiSettingsService $aiSettings) {}

    /**
     * Show the registration form.
     */
    public function showRegistrationForm()
    {
        return view('auth.register');
    }

    /**
     * Handle a registration request.
     */
    public function register(RegisterRequest $request)
    {
        try {
            DB::beginTransaction();

            // Generate subdomain from company name
            $subdomain = $this->generateUniqueSubdomain($request->company);

            // Find or create plan based on selection
            $plan = $this->findOrCreatePlan($request->plan);

            // Create company
            $company = Company::create([
                'name' => $request->company,
                'subdomain' => $subdomain,
                'email' => $request->email,
                'status' => 'trial',
                'trial_ends_at' => now()->addDays(14),
            ]);

            // Create permissions for the company
            $permissions = $this->createCompanyPermissions($company);

            // Create Admin role for the company
            $adminRole = Role::create([
                'name' => 'Administrator',
                'slug' => 'admin',
                'description' => 'Full system access',
                'is_active' => true,
                'company_id' => $company->id,
            ]);

            // Assign all permissions to Admin role
            $adminRole->permissions()->sync($permissions->pluck('id')->toArray());

            // Create user with Admin role
            $user = User::create([
                'name' => trim($request->first_name.' '.$request->last_name),
                'email' => $request->email,
                'password' => $request->password,
                'company_id' => $company->id,
                'status' => 'active',
                'role_id' => $adminRole->id,
            ]);

            // Create subscription if plan is not free
            if ($plan->price > 0) {
                Subscription::create([
                    'company_id' => $company->id,
                    'plan_id' => $plan->id,
                    'status' => 'pending',
                    'starts_at' => now(),
                    'amount' => $plan->price,
                    'billing_cycle' => 'monthly',
                    'next_billing_date' => now()->addMonth(),
                ]);
            } else {
                // Free plan - create active subscription
                Subscription::create([
                    'company_id' => $company->id,
                    'plan_id' => $plan->id,
                    'status' => 'active',
                    'starts_at' => now(),
                    'amount' => 0,
                    'billing_cycle' => 'monthly',
                ]);
            }

            $this->aiSettings->autoConnectIfEnabled($company->id);

            DB::commit();

            // Send welcome email — failure must not break registration
            try {
                $loginUrl = $this->buildSubdomainUrl($subdomain, '/login');
                Mail::to($user->email)->send(new WelcomeEmail(
                    userName: $user->name,
                    userEmail: $user->email,
                    loginUrl: $loginUrl,
                    companyName: $company->name,
                ));
            } catch (\Exception $mailException) {
                \Illuminate\Support\Facades\Log::warning('Welcome email failed after registration', [
                    'user_id' => $user->id,
                    'error' => $mailException->getMessage(),
                ]);
            }

            // Build subdomain URL and redirect to subdomain login (root route)
            $subdomainUrl = $this->buildSubdomainUrl($subdomain, '/');

            return redirect($subdomainUrl)
                ->with('success', 'Registration successful! Please log in to access your dashboard.')
                ->with('registered_email', $request->email);
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => 'Registration failed. Please try again.']);
        }
    }

    /**
     * Generate a unique subdomain from company name.
     */
    private function generateUniqueSubdomain(string $companyName): string
    {
        // Convert to lowercase and replace spaces with hyphens
        $subdomain = Str::lower($companyName);
        $subdomain = Str::slug($subdomain, '-');

        // Remove any special characters
        $subdomain = preg_replace('/[^a-z0-9-]/', '', $subdomain);

        // Ensure it's not empty
        if (empty($subdomain)) {
            $subdomain = 'company-'.Str::random(6);
        }

        // Ensure uniqueness
        $originalSubdomain = $subdomain;
        $counter = 1;
        while (Company::where('subdomain', $subdomain)->exists()) {
            $subdomain = $originalSubdomain.'-'.$counter;
            $counter++;
        }

        return $subdomain;
    }

    /**
     * Find or create plan based on selection.
     */
    private function findOrCreatePlan(string $planSlug): Plan
    {
        $planMapping = [
            'free' => ['name' => 'Free', 'price' => 0.00, 'description' => 'Free plan with basic features'],
            'gold' => ['name' => 'Gold', 'price' => 149.00, 'description' => 'Perfect for growing real estate businesses'],
            'platinum' => ['name' => 'Platinum', 'price' => 399.00, 'description' => 'Ultimate solution for serious real estate professionals'],
        ];

        $planData = $planMapping[$planSlug] ?? $planMapping['free'];

        return Plan::firstOrCreate(
            ['name' => $planData['name']],
            [
                'name' => $planData['name'],
                'description' => $planData['description'],
                'price' => $planData['price'],
                'billing_cycle' => 'monthly',
                'is_featured' => $planSlug === 'gold',
                'is_active' => true,
                'max_users' => $planSlug === 'free' ? 1 : ($planSlug === 'gold' ? 10 : 40),
            ]
        );
    }

    /**
     * Build subdomain URL.
     */
    private function buildSubdomainUrl(string $subdomain, string $path = '/'): string
    {
        $baseUrl = config('app.url');
        $parsed = parse_url($baseUrl);
        $scheme = $parsed['scheme'] ?? (request()->secure() ? 'https' : 'http');
        $port = request()->getPort();

        // Handle different base URL formats
        if (str_contains($baseUrl, 'localhost')) {
            // For localhost, use subdomain.localhost format
            $host = $subdomain.'.localhost';
        } else {
            // For production domains, extract the domain and prepend subdomain
            $host = $subdomain.'.'.($parsed['host'] ?? 'localhost');
        }

        // Build URL with port if not default (skip for standard HTTP/HTTPS ports)
        $url = ($port && $port != 80 && $port != 443)
            ? "{$scheme}://{$host}:{$port}{$path}"
            : "{$scheme}://{$host}{$path}";

        return $url;
    }

    /**
     * Create sidebar permissions for a company.
     */
    private function createCompanyPermissions(Company $company): \Illuminate\Support\Collection
    {
        // Define sidebar permissions mapping (route => permission details)
        $sidebarPermissions = [
            'dashboard' => [
                'name' => 'view_dashboard',
                'slug' => 'view_dashboard',
                'display_name' => 'Dashboard',
                'description' => 'Access to dashboard',
                'category' => 'main',
            ],
            'time-tracking' => [
                'name' => 'view_time_tracking',
                'slug' => 'view_time_tracking',
                'display_name' => 'Time Tracking',
                'description' => 'Access to time tracking module',
                'category' => 'main',
            ],
            'user-management' => [
                'name' => 'view_user_management',
                'slug' => 'view_user_management',
                'display_name' => 'User & Access Management',
                'description' => 'Access to user management module',
                'category' => 'main',
            ],
            // User Management - Sub-module Permissions
            'view_user_roles_permissions' => [
                'name' => 'view_user_roles_permissions',
                'slug' => 'view_user_roles_permissions',
                'display_name' => 'View Roles & Permissions',
                'description' => 'Access to roles and permissions management tab',
                'category' => 'settings',
            ],
            'view_user_company_setup' => [
                'name' => 'view_user_company_setup',
                'slug' => 'view_user_company_setup',
                'display_name' => 'View Company Setup',
                'description' => 'Access to company setup management tab',
                'category' => 'settings',
            ],
            'view_user_employee_profile' => [
                'name' => 'view_user_employee_profile',
                'slug' => 'view_user_employee_profile',
                'display_name' => 'View Employee Profile',
                'description' => 'Access to employee profile management tab',
                'category' => 'settings',
            ],
            'view_user_departments' => [
                'name' => 'view_user_departments',
                'slug' => 'view_user_departments',
                'display_name' => 'View Departments',
                'description' => 'Access to departments management tab',
                'category' => 'settings',
            ],
            'view_user_role_based_access' => [
                'name' => 'view_user_role_based_access',
                'slug' => 'view_user_role_based_access',
                'display_name' => 'View Role Based Access',
                'description' => 'Access to role based access control tab',
                'category' => 'settings',
            ],
            'employee-monitoring' => [
                'name' => 'view_employee_monitoring',
                'slug' => 'view_employee_monitoring',
                'display_name' => 'Employee Monitoring',
                'description' => 'Access to employee monitoring module',
                'category' => 'main',
            ],
            'view_live_screen' => [
                'name' => 'view_live_screen',
                'slug' => 'view_live_screen',
                'display_name' => 'View Live Screen',
                'description' => 'Watch employee live screen streams while they are recording',
                'category' => 'employee_monitoring',
            ],
            'phone-system' => [
                'name' => 'view_phone_system',
                'slug' => 'view_phone_system',
                'display_name' => 'Phone System',
                'description' => 'Access to phone system module',
                'category' => 'main',
            ],
            'view_call_history' => [
                'name' => 'view_call_history',
                'slug' => 'view_call_history',
                'display_name' => 'View Call History',
                'description' => 'View persisted phone call history',
                'category' => 'phone',
            ],
            'manage_phone_contacts' => [
                'name' => 'manage_phone_contacts',
                'slug' => 'manage_phone_contacts',
                'display_name' => 'Manage Phone Contacts',
                'description' => 'Create and manage phone system contacts',
                'category' => 'phone',
            ],
            'view_sms' => [
                'name' => 'view_sms',
                'slug' => 'view_sms',
                'display_name' => 'View SMS',
                'description' => 'View SMS conversations in phone system',
                'category' => 'phone',
            ],
            'send_sms' => [
                'name' => 'send_sms',
                'slug' => 'send_sms',
                'display_name' => 'Send SMS',
                'description' => 'Send SMS messages from phone system',
                'category' => 'phone',
            ],
            'manage_twilio_numbers' => [
                'name' => 'manage_twilio_numbers',
                'slug' => 'manage_twilio_numbers',
                'display_name' => 'Manage Phone Numbers',
                'description' => 'Purchase and assign Infobip phone numbers',
                'category' => 'phone',
            ],
            'payroll' => [
                'name' => 'view_payroll',
                'slug' => 'view_payroll',
                'display_name' => 'Payroll',
                'description' => 'Access to payroll module',
                'category' => 'main',
            ],
            // Payroll - Time In/Out Permissions
            'view_time_in_out' => [
                'name' => 'view_time_in_out',
                'slug' => 'view_time_in_out',
                'display_name' => 'View Time In/Out',
                'description' => 'Access to time in/out tracking module',
                'category' => 'payroll',
            ],
            'edit_time_in_out' => [
                'name' => 'edit_time_in_out',
                'slug' => 'edit_time_in_out',
                'display_name' => 'Edit Time In/Out',
                'description' => 'Edit time in/out records',
                'category' => 'payroll',
            ],
            'export_time_in_out' => [
                'name' => 'export_time_in_out',
                'slug' => 'export_time_in_out',
                'display_name' => 'Export Time In/Out',
                'description' => 'Export time in/out records',
                'category' => 'payroll',
            ],
            // Payroll - Report Permissions
            'view_payroll_report' => [
                'name' => 'view_payroll_report',
                'slug' => 'view_payroll_report',
                'display_name' => 'View Payroll Report',
                'description' => 'Access to payroll report module',
                'category' => 'payroll',
            ],
            'view_payroll_sales_rep_report' => [
                'name' => 'view_payroll_sales_rep_report',
                'slug' => 'view_payroll_sales_rep_report',
                'display_name' => 'Payroll Report (Sales Rep)',
                'description' => 'Access to Payroll Report by Sales Rep page and related APIs',
                'category' => 'payroll',
            ],
            'view_pnl' => [
                'name' => 'view_pnl',
                'slug' => 'view_pnl',
                'display_name' => 'View P&L',
                'description' => 'Access to the P&L dashboard and invoice-basis report',
                'category' => 'payroll',
            ],
            'generate_payroll_report' => [
                'name' => 'generate_payroll_report',
                'slug' => 'generate_payroll_report',
                'display_name' => 'Generate Payroll Report',
                'description' => 'Generate payroll reports',
                'category' => 'payroll',
            ],
            'export_payroll_report' => [
                'name' => 'export_payroll_report',
                'slug' => 'export_payroll_report',
                'display_name' => 'Export Payroll Report',
                'description' => 'Export payroll reports to Excel',
                'category' => 'payroll',
            ],
            // Payroll - Saved for Wise
            'view_saved_for_wise' => [
                'name' => 'view_saved_for_wise',
                'slug' => 'view_saved_for_wise',
                'display_name' => 'Saved for Wise',
                'description' => 'Access to saved payroll reports for Wise transfers',
                'category' => 'payroll',
            ],
            // Payroll - Wise Recipients
            'view_wise_recipients' => [
                'name' => 'view_wise_recipients',
                'slug' => 'view_wise_recipients',
                'display_name' => 'Wise Recipients',
                'description' => 'Access to Wise Recipients & Employee Assignment page',
                'category' => 'payroll',
            ],
            'project-management' => [
                'name' => 'view_project_management',
                'slug' => 'view_project_management',
                'display_name' => 'Project Management',
                'description' => 'Access to project management module',
                'category' => 'main',
            ],
            'create_project_management' => [
                'name' => 'create_project_management',
                'slug' => 'create_project_management',
                'display_name' => 'Create Projects',
                'description' => 'Create new projects in project management',
                'category' => 'settings',
            ],
            'create_task_management' => [
                'name' => 'create_task_management',
                'slug' => 'create_task_management',
                'display_name' => 'Create Tasks',
                'description' => 'Create new tasks in project management',
                'category' => 'settings',
            ],
            'edit_project_management' => [
                'name' => 'edit_project_management',
                'slug' => 'edit_project_management',
                'display_name' => 'Edit Projects & Tasks',
                'description' => 'Edit projects and tasks in project management',
                'category' => 'settings',
            ],
            'delete_project_management' => [
                'name' => 'delete_project_management',
                'slug' => 'delete_project_management',
                'display_name' => 'Delete Projects & Tasks',
                'description' => 'Delete projects and tasks in project management',
                'category' => 'settings',
            ],
            'messaging' => [
                'name' => 'view_messaging',
                'slug' => 'view_messaging',
                'display_name' => 'Messaging',
                'description' => 'Access to messaging module',
                'category' => 'main',
            ],
            'inbox' => [
                'name' => 'view_inbox',
                'slug' => 'view_inbox',
                'display_name' => 'Inbox',
                'description' => 'Access to personal and shared Outlook inboxes',
                'category' => 'main',
            ],
            'viber' => [
                'name' => 'view_viber',
                'slug' => 'view_viber',
                'display_name' => 'Viber',
                'description' => 'Access to Viber Business conversations',
                'category' => 'main',
            ],
            'whatsapp' => [
                'name' => 'view_whatsapp',
                'slug' => 'view_whatsapp',
                'display_name' => 'WhatsApp',
                'description' => 'Access to WhatsApp Business conversations',
                'category' => 'main',
            ],
            'facebook' => [
                'name' => 'view_facebook',
                'slug' => 'view_facebook',
                'display_name' => 'Facebook & Instagram',
                'description' => 'Access to Facebook Messenger and Instagram Direct conversations',
                'category' => 'main',
            ],
            'create_inbox_tags' => [
                'name' => 'create_inbox_tags',
                'slug' => 'create_inbox_tags',
                'display_name' => 'Add Tags',
                'description' => 'Create and delete inbox tags',
                'category' => 'main',
            ],
            'create_inbox_templates' => [
                'name' => 'create_inbox_templates',
                'slug' => 'create_inbox_templates',
                'display_name' => 'Add Templates',
                'description' => 'Create, edit, and delete shared inbox templates',
                'category' => 'main',
            ],
            'create_inbox_rules' => [
                'name' => 'create_inbox_rules',
                'slug' => 'create_inbox_rules',
                'display_name' => 'Add Rules',
                'description' => 'Create, edit, and delete inbox automation rules',
                'category' => 'main',
            ],
            'billing' => [
                'name' => 'view_billing',
                'slug' => 'view_billing',
                'display_name' => 'Billing & Payments',
                'description' => 'Access to billing and payments module',
                'category' => 'main',
            ],
            'client-management' => [
                'name' => 'view_client_management',
                'slug' => 'view_client_management',
                'display_name' => 'Client Management',
                'description' => 'Access to client management module',
                'category' => 'main',
            ],
            'tickets' => [
                'name' => 'view_tickets',
                'slug' => 'view_tickets',
                'display_name' => 'Tickets & Helpdesk',
                'description' => 'Access to tickets and helpdesk module',
                'category' => 'main',
            ],
            'knowledge-base' => [
                'name' => 'view_knowledge_base',
                'slug' => 'view_knowledge_base',
                'display_name' => 'View Knowledge Base',
                'description' => 'Access to knowledge base module',
                'category' => 'main',
            ],
            'create_knowledge_base' => [
                'name' => 'create_knowledge_base',
                'slug' => 'create_knowledge_base',
                'display_name' => 'Create (Knowledge Base)',
                'description' => 'Create articles, FAQs, guides, and categories in Knowledge Base',
                'category' => 'main',
            ],
            'edit_knowledge_base' => [
                'name' => 'edit_knowledge_base',
                'slug' => 'edit_knowledge_base',
                'display_name' => 'Edit (Knowledge Base)',
                'description' => 'Edit articles, FAQs, guides in Knowledge Base',
                'category' => 'main',
            ],
            'delete_knowledge_base' => [
                'name' => 'delete_knowledge_base',
                'slug' => 'delete_knowledge_base',
                'display_name' => 'Delete (Knowledge Base)',
                'description' => 'Delete articles, FAQs, guides in Knowledge Base',
                'category' => 'main',
            ],
            'integrations' => [
                'name' => 'view_integrations',
                'slug' => 'view_integrations',
                'display_name' => 'Integrations',
                'description' => 'Access to integrations module',
                'category' => 'main',
            ],
            'quotation-builder' => [
                'name' => 'view_quotation_builder',
                'slug' => 'view_quotation_builder',
                'display_name' => 'Quotation Builder',
                'description' => 'Access to quotation builder module',
                'category' => 'main',
            ],
            'calendar' => [
                'name' => 'view_calendar',
                'slug' => 'view_calendar',
                'display_name' => 'Calendar',
                'description' => 'Access to calendar module',
                'category' => 'main',
            ],
            'email-tracking' => [
                'name' => 'view_email_tracking',
                'slug' => 'view_email_tracking',
                'display_name' => 'Email Tracking',
                'description' => 'Access to email tracking module',
                'category' => 'main',
            ],
            'openai' => [
                'name' => 'view_ai_assistant',
                'slug' => 'view_ai_assistant',
                'display_name' => 'AI Assistant',
                'description' => 'Access to AI assistant module',
                'category' => 'main',
            ],
            'admin-control' => [
                'name' => 'view_admin_control',
                'slug' => 'view_admin_control',
                'display_name' => 'Admin Control',
                'description' => 'Access to admin control panel',
                'category' => 'settings',
            ],
            'change-password' => [
                'name' => 'view_change_password',
                'slug' => 'view_change_password',
                'display_name' => 'Change Password',
                'description' => 'Access to change password module',
                'category' => 'main',
            ],
            // Team Management Permissions
            'team-management' => [
                'name' => 'view_team_management',
                'slug' => 'view_team_management',
                'display_name' => 'Team Management',
                'description' => 'Access to Team Management module',
                'category' => 'sidebar',
            ],
            'create_team_management' => [
                'name' => 'Create Team',
                'slug' => 'create_team_management',
                'display_name' => 'Create Team',
                'description' => 'Permission to create new teams',
                'category' => 'team_management',
            ],
            'edit_team_management' => [
                'name' => 'Edit Team',
                'slug' => 'edit_team_management',
                'display_name' => 'Edit Team',
                'description' => 'Permission to edit existing teams',
                'category' => 'team_management',
            ],
            'delete_team_management' => [
                'name' => 'Delete Team',
                'slug' => 'delete_team_management',
                'display_name' => 'Delete Team',
                'description' => 'Permission to delete teams',
                'category' => 'team_management',
            ],
            'manage_team_members' => [
                'name' => 'Manage Team Members',
                'slug' => 'manage_team_members',
                'display_name' => 'Manage Team Members',
                'description' => 'Permission to add/remove team members',
                'category' => 'team_management',
            ],
            'view_team_time_tracking' => [
                'name' => 'View Team Time Tracking',
                'slug' => 'view_team_time_tracking',
                'display_name' => 'View Team Time Tracking',
                'description' => 'Permission to view team members time tracking records',
                'category' => 'team_management',
            ],
            'view_team_recordings' => [
                'name' => 'View Team Recordings',
                'slug' => 'view_team_recordings',
                'display_name' => 'View Team Recordings',
                'description' => 'Permission to view team members screen recordings',
                'category' => 'team_management',
            ],
            // Leave Management Permissions
            'view_leave_management' => [
                'name' => 'view_leave_management',
                'slug' => 'view_leave_management',
                'display_name' => 'View Leave Management',
                'description' => 'Access to leave management module',
                'category' => 'Leave Management',
            ],
            'view_leave_stats' => [
                'name' => 'view_leave_stats',
                'slug' => 'view_leave_stats',
                'display_name' => 'View Leave Statistics',
                'description' => 'View leave statistics and dashboard',
                'category' => 'Leave Management',
            ],
            'create_leave_request' => [
                'name' => 'create_leave_request',
                'slug' => 'create_leave_request',
                'display_name' => 'Create Leave Request',
                'description' => 'Create new leave requests',
                'category' => 'Leave Management',
            ],
            'view_leave_credits' => [
                'name' => 'view_leave_credits',
                'slug' => 'view_leave_credits',
                'display_name' => 'View Leave Credits',
                'description' => 'View leave credits for users',
                'category' => 'Leave Management',
            ],
            'manage_leave_credits' => [
                'name' => 'manage_leave_credits',
                'slug' => 'manage_leave_credits',
                'display_name' => 'Manage Leave Credits',
                'description' => 'Add and manage leave credits for users',
                'category' => 'Leave Management',
            ],
            'view_leave_calendar' => [
                'name' => 'view_leave_calendar',
                'slug' => 'view_leave_calendar',
                'display_name' => 'View Leave Calendar',
                'description' => 'View leave calendar and employees on leave',
                'category' => 'Leave Management',
            ],
        ];

        $createdPermissions = collect();

        foreach ($sidebarPermissions as $route => $permissionData) {
            // Check if permission already exists for this company
            $permission = Permission::where('slug', $permissionData['slug'])
                ->where('company_id', $company->id)
                ->first();

            if (! $permission) {
                $permission = Permission::create([
                    'name' => $permissionData['name'],
                    'slug' => $permissionData['slug'],
                    'display_name' => $permissionData['display_name'],
                    'description' => $permissionData['description'],
                    'category' => $permissionData['category'],
                    'company_id' => $company->id,
                ]);
            }

            $createdPermissions->push($permission);
        }

        return $createdPermissions;
    }
}
