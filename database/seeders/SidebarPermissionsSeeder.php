<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class SidebarPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
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
            'employee-monitoring' => [
                'name' => 'view_employee_monitoring',
                'slug' => 'view_employee_monitoring',
                'display_name' => 'Employee Monitoring',
                'description' => 'Access to employee monitoring module',
                'category' => 'main',
            ],
            'phone-system' => [
                'name' => 'view_phone_system',
                'slug' => 'view_phone_system',
                'display_name' => 'Phone System',
                'description' => 'Access to phone system module',
                'category' => 'main',
            ],
            'payroll' => [
                'name' => 'view_payroll',
                'slug' => 'view_payroll',
                'display_name' => 'Payroll',
                'description' => 'Access to payroll module',
                'category' => 'main',
            ],
            'project-management' => [
                'name' => 'view_project_management',
                'slug' => 'view_project_management',
                'display_name' => 'Project Management',
                'description' => 'Access to project management module',
                'category' => 'main',
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
        ];

        // Get all companies and create permissions for each
        $companies = Company::all();

        foreach ($companies as $company) {
            foreach ($sidebarPermissions as $route => $permissionData) {
                // Check if permission already exists for this company
                $permission = Permission::where('slug', $permissionData['slug'])
                    ->where('company_id', $company->id)
                    ->first();

                if (! $permission) {
                    Permission::create([
                        'name' => $permissionData['name'],
                        'slug' => $permissionData['slug'],
                        'display_name' => $permissionData['display_name'],
                        'description' => $permissionData['description'],
                        'category' => $permissionData['category'],
                        'company_id' => $company->id,
                    ]);
                }
            }

            // Create or find Admin role and assign all permissions
            $adminRole = Role::firstOrCreate(
                [
                    'slug' => 'admin',
                    'company_id' => $company->id,
                ],
                [
                    'name' => 'Administrator',
                    'description' => 'Full system access',
                    'is_active' => true,
                ]
            );

            // Get all permissions for this company
            $allPermissions = Permission::where('company_id', $company->id)->get();
            $adminRole->permissions()->syncWithoutDetaching($allPermissions->pluck('id'));
        }

        $this->command->info('Sidebar permissions seeded successfully for all companies!');
    }
}
