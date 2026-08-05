<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Permission;
use Illuminate\Support\Collection;

class CompanyPermissionFactory
{
    /**
     * Create all default permissions for a new company.
     */
    public static function createForCompany(Company $company): Collection
    {
        $map = self::getMap();
        $createdPermissions = collect();

        foreach ($map as $permissionData) {
            $permission = Permission::where('slug', $permissionData['slug'])
                ->where('company_id', $company->id)
                ->first();

            if (! $permission) {
                $permission = Permission::create([
                    'name' => $permissionData['name'],
                    'slug' => $permissionData['slug'],
                    'display_name' => $permissionData['display_name'] ?? $permissionData['name'],
                    'description' => $permissionData['description'] ?? '',
                    'category' => $permissionData['category'] ?? 'main',
                    'company_id' => $company->id,
                ]);
            }

            $createdPermissions->push($permission);
        }

        return $createdPermissions;
    }

    /**
     * Get permission map. Merge from SidebarPermissionsSeeder + AddPayrollPermissionsSeeder + AddLeaveManagementPermissionsSeeder + RegisterController extras.
     */
    protected static function getMap(): array
    {
        $base = [
            'dashboard' => ['name' => 'view_dashboard', 'slug' => 'view_dashboard', 'display_name' => 'Dashboard', 'description' => 'Access to dashboard', 'category' => 'main'],
            'time-tracking' => ['name' => 'view_time_tracking', 'slug' => 'view_time_tracking', 'display_name' => 'Time Tracking', 'description' => 'Access to time tracking module', 'category' => 'main'],
            'user-management' => ['name' => 'view_user_management', 'slug' => 'view_user_management', 'display_name' => 'User & Access Management', 'description' => 'Access to user management module', 'category' => 'main'],
            'view_user_roles_permissions' => ['name' => 'view_user_roles_permissions', 'slug' => 'view_user_roles_permissions', 'display_name' => 'View Roles & Permissions', 'description' => 'Access to roles and permissions management tab', 'category' => 'settings'],
            'view_user_company_setup' => ['name' => 'view_user_company_setup', 'slug' => 'view_user_company_setup', 'display_name' => 'View Company Setup', 'description' => 'Access to company setup management tab', 'category' => 'settings'],
            'view_user_employee_profile' => ['name' => 'view_user_employee_profile', 'slug' => 'view_user_employee_profile', 'display_name' => 'View Employee Profile', 'description' => 'Access to employee profile management tab', 'category' => 'settings'],
            'view_user_departments' => ['name' => 'view_user_departments', 'slug' => 'view_user_departments', 'display_name' => 'View Departments', 'description' => 'Access to departments management tab', 'category' => 'settings'],
            'view_user_role_based_access' => ['name' => 'view_user_role_based_access', 'slug' => 'view_user_role_based_access', 'display_name' => 'View Role Based Access', 'description' => 'Access to role based access control tab', 'category' => 'settings'],
            'employee-monitoring' => ['name' => 'view_employee_monitoring', 'slug' => 'view_employee_monitoring', 'display_name' => 'Employee Monitoring', 'description' => 'Access to employee monitoring module', 'category' => 'main'],
            'view_live_screen' => ['name' => 'view_live_screen', 'slug' => 'view_live_screen', 'display_name' => 'View Live Screen', 'description' => 'Watch employee live screen streams while they are recording', 'category' => 'employee_monitoring'],
            'phone-system' => ['name' => 'view_phone_system', 'slug' => 'view_phone_system', 'display_name' => 'Phone System', 'description' => 'Access to phone system module', 'category' => 'main'],
            'view_call_history' => ['name' => 'view_call_history', 'slug' => 'view_call_history', 'display_name' => 'View Call History', 'description' => 'View persisted phone call history', 'category' => 'phone'],
            'manage_phone_contacts' => ['name' => 'manage_phone_contacts', 'slug' => 'manage_phone_contacts', 'display_name' => 'Manage Phone Contacts', 'description' => 'Create and manage phone system contacts', 'category' => 'phone'],
            'view_sms' => ['name' => 'view_sms', 'slug' => 'view_sms', 'display_name' => 'View SMS', 'description' => 'View SMS conversations via Infobip', 'category' => 'main'],
            'send_sms' => ['name' => 'send_sms', 'slug' => 'send_sms', 'display_name' => 'Send SMS', 'description' => 'Send SMS messages via Infobip', 'category' => 'main'],
            // Slug kept as manage_twilio_numbers for existing DB/role compatibility
            'manage_twilio_numbers' => ['name' => 'manage_twilio_numbers', 'slug' => 'manage_twilio_numbers', 'display_name' => 'Manage Phone Numbers', 'description' => 'Purchase and assign Infobip phone numbers', 'category' => 'phone'],
            'payroll' => ['name' => 'view_payroll', 'slug' => 'view_payroll', 'display_name' => 'Payroll', 'description' => 'Access to payroll module', 'category' => 'main'],
            'view_time_in_out' => ['name' => 'view_time_in_out', 'slug' => 'view_time_in_out', 'display_name' => 'View Time In/Out', 'description' => 'Access to time in/out tracking module', 'category' => 'payroll'],
            'edit_time_in_out' => ['name' => 'edit_time_in_out', 'slug' => 'edit_time_in_out', 'display_name' => 'Edit Time In/Out', 'description' => 'Edit time in/out records', 'category' => 'payroll'],
            'export_time_in_out' => ['name' => 'export_time_in_out', 'slug' => 'export_time_in_out', 'display_name' => 'Export Time In/Out', 'description' => 'Export time in/out records', 'category' => 'payroll'],
            'view_salary_computation' => ['name' => 'view_salary_computation', 'slug' => 'view_salary_computation', 'display_name' => 'View Salary Computation', 'description' => 'Access to salary computation module', 'category' => 'payroll'],
            'edit_salary_computation' => ['name' => 'edit_salary_computation', 'slug' => 'edit_salary_computation', 'display_name' => 'Edit Salary Computation', 'description' => 'Edit salary computation records', 'category' => 'payroll'],
            'save_salary_computation' => ['name' => 'save_salary_computation', 'slug' => 'save_salary_computation', 'display_name' => 'Save Salary Computation', 'description' => 'Save salary computation records', 'category' => 'payroll'],
            'calculate_salary_computation' => ['name' => 'calculate_salary_computation', 'slug' => 'calculate_salary_computation', 'display_name' => 'Calculate Salary Computation', 'description' => 'Calculate salary computation', 'category' => 'payroll'],
            'view_payroll_report' => ['name' => 'view_payroll_report', 'slug' => 'view_payroll_report', 'display_name' => 'View Payroll Report', 'description' => 'Access to payroll report module', 'category' => 'payroll'],
            'view_payroll_sales_rep_report' => ['name' => 'view_payroll_sales_rep_report', 'slug' => 'view_payroll_sales_rep_report', 'display_name' => 'Payroll Report (Sales Rep)', 'description' => 'Access to Payroll Report by Sales Rep page and related APIs', 'category' => 'payroll'],
            'view_pnl' => ['name' => 'view_pnl', 'slug' => 'view_pnl', 'display_name' => 'View P&L', 'description' => 'Access to the P&L dashboard and invoice-basis report', 'category' => 'payroll'],
            'generate_payroll_report' => ['name' => 'generate_payroll_report', 'slug' => 'generate_payroll_report', 'display_name' => 'Generate Payroll Report', 'description' => 'Generate payroll reports', 'category' => 'payroll'],
            'export_payroll_report' => ['name' => 'export_payroll_report', 'slug' => 'export_payroll_report', 'display_name' => 'Export Payroll Report', 'description' => 'Export payroll reports to Excel', 'category' => 'payroll'],
            'view_saved_for_wise' => ['name' => 'view_saved_for_wise', 'slug' => 'view_saved_for_wise', 'display_name' => 'Saved for Wise', 'description' => 'Access to saved payroll reports for Wise transfers', 'category' => 'payroll'],
            'view_wise_recipients' => ['name' => 'view_wise_recipients', 'slug' => 'view_wise_recipients', 'display_name' => 'Wise Recipients', 'description' => 'Access to Wise Recipients & Employee Assignment page', 'category' => 'payroll'],
            'project-management' => ['name' => 'view_project_management', 'slug' => 'view_project_management', 'display_name' => 'Project Management', 'description' => 'Access to project management module', 'category' => 'main'],
            'create_project_management' => ['name' => 'create_project_management', 'slug' => 'create_project_management', 'display_name' => 'Create Projects', 'description' => 'Create new projects in project management', 'category' => 'settings'],
            'create_task_management' => ['name' => 'create_task_management', 'slug' => 'create_task_management', 'display_name' => 'Create Tasks', 'description' => 'Create new tasks in project management', 'category' => 'settings'],
            'edit_project_management' => ['name' => 'edit_project_management', 'slug' => 'edit_project_management', 'display_name' => 'Edit Projects & Tasks', 'description' => 'Edit projects and tasks in project management', 'category' => 'settings'],
            'delete_project_management' => ['name' => 'delete_project_management', 'slug' => 'delete_project_management', 'display_name' => 'Delete Projects & Tasks', 'description' => 'Delete projects and tasks in project management', 'category' => 'settings'],
            'messaging' => ['name' => 'view_messaging', 'slug' => 'view_messaging', 'display_name' => 'Messaging', 'description' => 'Access to messaging module', 'category' => 'main'],
            'inbox' => ['name' => 'view_inbox', 'slug' => 'view_inbox', 'display_name' => 'Inbox', 'description' => 'Access to personal and shared Outlook inboxes', 'category' => 'main'],
            'viber' => ['name' => 'view_viber', 'slug' => 'view_viber', 'display_name' => 'Viber', 'description' => 'Access to Viber Business conversations', 'category' => 'main'],
            'whatsapp' => ['name' => 'view_whatsapp', 'slug' => 'view_whatsapp', 'display_name' => 'WhatsApp', 'description' => 'Access to WhatsApp Business conversations', 'category' => 'main'],
            'facebook' => ['name' => 'view_facebook', 'slug' => 'view_facebook', 'display_name' => 'Facebook & Instagram', 'description' => 'Access to Facebook Messenger and Instagram Direct conversations', 'category' => 'main'],
            'create_inbox_tags' => ['name' => 'create_inbox_tags', 'slug' => 'create_inbox_tags', 'display_name' => 'Add Tags', 'description' => 'Create and delete inbox tags', 'category' => 'main'],
            'create_inbox_templates' => ['name' => 'create_inbox_templates', 'slug' => 'create_inbox_templates', 'display_name' => 'Add Templates', 'description' => 'Create, edit, and delete shared inbox templates', 'category' => 'main'],
            'create_inbox_rules' => ['name' => 'create_inbox_rules', 'slug' => 'create_inbox_rules', 'display_name' => 'Add Rules', 'description' => 'Create, edit, and delete inbox automation rules', 'category' => 'main'],
            'billing' => ['name' => 'view_billing', 'slug' => 'view_billing', 'display_name' => 'Billing & Payments', 'description' => 'Access to billing and payments module', 'category' => 'main'],
            'delete_billing' => ['name' => 'delete_billing', 'slug' => 'delete_billing', 'display_name' => 'Delete Invoices', 'description' => 'Delete invoices in the billing and payments module', 'category' => 'main'],
            'client-management' => ['name' => 'view_client_management', 'slug' => 'view_client_management', 'display_name' => 'Client Management', 'description' => 'Access to client management module', 'category' => 'main'],
            'tickets' => ['name' => 'view_tickets', 'slug' => 'view_tickets', 'display_name' => 'Tickets & Helpdesk', 'description' => 'Access to tickets and helpdesk module', 'category' => 'main'],
            'knowledge-base' => ['name' => 'view_knowledge_base', 'slug' => 'view_knowledge_base', 'display_name' => 'View Knowledge Base', 'description' => 'Access to knowledge base module', 'category' => 'main'],
            'create_knowledge_base' => ['name' => 'create_knowledge_base', 'slug' => 'create_knowledge_base', 'display_name' => 'Create (Knowledge Base)', 'description' => 'Create articles, FAQs, guides, and categories in Knowledge Base', 'category' => 'main'],
            'edit_knowledge_base' => ['name' => 'edit_knowledge_base', 'slug' => 'edit_knowledge_base', 'display_name' => 'Edit (Knowledge Base)', 'description' => 'Edit articles, FAQs, guides in Knowledge Base', 'category' => 'main'],
            'delete_knowledge_base' => ['name' => 'delete_knowledge_base', 'slug' => 'delete_knowledge_base', 'display_name' => 'Delete (Knowledge Base)', 'description' => 'Delete articles, FAQs, guides in Knowledge Base', 'category' => 'main'],
            'integrations' => ['name' => 'view_integrations', 'slug' => 'view_integrations', 'display_name' => 'Integrations', 'description' => 'Access to integrations module', 'category' => 'main'],
            'quotation-builder' => ['name' => 'view_quotation_builder', 'slug' => 'view_quotation_builder', 'display_name' => 'Quotation Builder', 'description' => 'Access to quotation builder module', 'category' => 'main'],
            'contracts' => ['name' => 'view_contracts', 'slug' => 'view_contracts', 'display_name' => 'Contracts & E-Sign', 'description' => 'Access to contracts and e-signing module', 'category' => 'main'],
            'create_contracts' => ['name' => 'create_contracts', 'slug' => 'create_contracts', 'display_name' => 'Create Contracts', 'description' => 'Create and edit contracts', 'category' => 'main'],
            'send_contracts' => ['name' => 'send_contracts', 'slug' => 'send_contracts', 'display_name' => 'Send Contracts', 'description' => 'Send contracts for electronic signature', 'category' => 'main'],
            'delete_contracts' => ['name' => 'delete_contracts', 'slug' => 'delete_contracts', 'display_name' => 'Delete Contracts', 'description' => 'Delete draft or cancelled contracts', 'category' => 'main'],
            'calendar' => ['name' => 'view_calendar', 'slug' => 'view_calendar', 'display_name' => 'Calendar', 'description' => 'Access to calendar module', 'category' => 'main'],
            'email-tracking' => ['name' => 'view_email_tracking', 'slug' => 'view_email_tracking', 'display_name' => 'Email Tracking', 'description' => 'Access to email tracking module', 'category' => 'main'],
            'openai' => ['name' => 'view_ai_assistant', 'slug' => 'view_ai_assistant', 'display_name' => 'AI Assistant', 'description' => 'Access to AI assistant module', 'category' => 'main'],
            'admin-control' => ['name' => 'view_admin_control', 'slug' => 'view_admin_control', 'display_name' => 'Admin Control', 'description' => 'Access to admin control panel', 'category' => 'settings'],
            'change-password' => ['name' => 'view_change_password', 'slug' => 'view_change_password', 'display_name' => 'Change Password', 'description' => 'Access to change password module', 'category' => 'main'],
            'team-management' => ['name' => 'view_team_management', 'slug' => 'view_team_management', 'display_name' => 'Team Management', 'description' => 'Access to Team Management module', 'category' => 'sidebar'],
            'create_team_management' => ['name' => 'create_team_management', 'slug' => 'create_team_management', 'display_name' => 'Create Team', 'description' => 'Permission to create new teams', 'category' => 'team_management'],
            'edit_team_management' => ['name' => 'edit_team_management', 'slug' => 'edit_team_management', 'display_name' => 'Edit Team', 'description' => 'Permission to edit existing teams', 'category' => 'team_management'],
            'delete_team_management' => ['name' => 'delete_team_management', 'slug' => 'delete_team_management', 'display_name' => 'Delete Team', 'description' => 'Permission to delete teams', 'category' => 'team_management'],
            'manage_team_members' => ['name' => 'manage_team_members', 'slug' => 'manage_team_members', 'display_name' => 'Manage Team Members', 'description' => 'Permission to add/remove team members', 'category' => 'team_management'],
            'view_team_time_tracking' => ['name' => 'view_team_time_tracking', 'slug' => 'view_team_time_tracking', 'display_name' => 'View Team Time Tracking', 'description' => 'Permission to view team members time tracking records', 'category' => 'team_management'],
            'view_team_recordings' => ['name' => 'view_team_recordings', 'slug' => 'view_team_recordings', 'display_name' => 'View Team Recordings', 'description' => 'Permission to view team members screen recordings', 'category' => 'team_management'],
            'view_leave_management' => ['name' => 'view_leave_management', 'slug' => 'view_leave_management', 'display_name' => 'View Leave Management', 'description' => 'Access to leave management module', 'category' => 'Leave Management'],
            'view_leave_stats' => ['name' => 'view_leave_stats', 'slug' => 'view_leave_stats', 'display_name' => 'View Leave Statistics', 'description' => 'View leave statistics and dashboard', 'category' => 'Leave Management'],
            'create_leave_request' => ['name' => 'create_leave_request', 'slug' => 'create_leave_request', 'display_name' => 'Create Leave Request', 'description' => 'Create new leave requests', 'category' => 'Leave Management'],
            'view_leave_credits' => ['name' => 'view_leave_credits', 'slug' => 'view_leave_credits', 'display_name' => 'View Leave Credits', 'description' => 'View leave credits for users', 'category' => 'Leave Management'],
            'manage_leave_credits' => ['name' => 'manage_leave_credits', 'slug' => 'manage_leave_credits', 'display_name' => 'Manage Leave Credits', 'description' => 'Add and manage leave credits for users', 'category' => 'Leave Management'],
            'view_leave_calendar' => ['name' => 'view_leave_calendar', 'slug' => 'view_leave_calendar', 'display_name' => 'View Leave Calendar', 'description' => 'View leave calendar and employees on leave', 'category' => 'Leave Management'],
        ];

        return array_values($base);
    }
}
