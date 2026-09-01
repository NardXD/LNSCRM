<?php

namespace App\Helpers;

/**
 * Helper class for sidebar menu items with permission mapping
 */
class SidebarHelper
{
    /**
     * Get all sidebar menu items with their permission mappings and module slugs
     */
    public static function getMenuItems(): array
    {
        return [
            [
                'route' => 'dashboard',
                'permission' => 'view_dashboard',
                'module_slug' => 'dashboard',
                'label' => 'Dashboard',
                'icon' => 'dashboard',
                'category' => 'main',
            ],
            [
                'route' => 'time-tracking',
                'permission' => 'view_time_tracking',
                'module_slug' => 'time-tracking',
                'label' => 'Time Tracking',
                'icon' => 'clock',
                'category' => 'main',
            ],
            [
                'route' => 'user-management',
                'permission' => 'view_user_management',
                'module_slug' => 'user-management',
                'label' => 'User Management',
                'icon' => 'users',
                'category' => 'main',
            ],
            [
                'route' => 'employee-monitoring',
                'permission' => 'view_employee_monitoring',
                'module_slug' => 'employee-monitoring',
                'label' => 'Employee Monitoring',
                'icon' => 'monitor',
                'category' => 'main',
            ],
            [
                'route' => 'payroll',
                'permission_any' => ['view_payroll', 'view_wise_recipients', 'view_pnl', 'view_payroll_report', 'view_payroll_sales_rep_report', 'generate_payroll_report'],
                'module_slugs' => ['payroll', 'pnl'],
                'label' => 'Payroll',
                'icon' => 'dollar-sign',
                'category' => 'main',
            ],
            [
                'route' => 'project-management',
                'permission' => 'view_project_management',
                'module_slug' => 'project-management',
                'label' => 'Project Management',
                'icon' => 'layers',
                'category' => 'main',
            ],
            [
                'route' => 'team-management',
                'permission' => 'view_team_management',
                'module_slug' => 'team-management',
                'label' => 'Team Management',
                'icon' => 'users',
                'category' => 'main',
            ],
            [
                'route' => 'leave-management',
                'permission' => 'view_leave_management',
                'module_slug' => 'leave-management',
                'label' => 'Leave Management',
                'icon' => 'calendar',
                'category' => 'main',
            ],
            [
                'route' => 'client-management',
                'permission' => 'view_client_management',
                'module_slug' => 'client-management',
                'label' => 'Client Management',
                'icon' => 'briefcase',
                'category' => 'main',
            ],
            [
                'route' => 'leads',
                'permission' => 'view_leads',
                'module_slug' => 'client-management',
                'label' => 'Leads',
                'icon' => 'user-plus',
                'category' => 'main',
            ],
            [
                'route' => 'lead-reports',
                'permission' => 'view_leads',
                'module_slug' => 'client-management',
                'label' => 'Lead Reports',
                'icon' => 'bar-chart',
                'category' => 'main',
            ],
            [
                'route' => 'hiring-queue',
                'permission' => 'view_client_management',
                'module_slug' => 'client-management',
                'label' => 'Hiring Queue',
                'icon' => 'clipboard-list',
                'category' => 'main',
            ],
            [
                'route' => 'quotation-builder',
                'permission' => 'view_quotation_builder',
                'permission_any' => ['view_quotation_builder', 'view_quotation_builder_microsoft_365_mail'],
                'module_slug' => 'quotation-builder',
                'label' => 'Quotation Builder',
                'icon' => 'file-text',
                'category' => 'main',
            ],
            [
                'route' => 'contracts',
                'permission' => 'view_contracts',
                'module_slug' => 'contracts',
                'label' => 'Contracts & E-Sign',
                'icon' => 'pen-tool',
                'category' => 'main',
            ],
            [
                'route' => 'twilio.call',
                'permission' => 'view_phone_system',
                'module_slug' => 'phone-system',
                'label' => 'Phone System',
                'icon' => 'phone',
                'category' => 'main',
            ],
            [
                'route' => 'messaging',
                'permission' => 'view_messaging',
                'module_slug' => 'messaging',
                'label' => 'Messaging',
                'icon' => 'message-circle',
                'category' => 'main',
            ],
            [
                'route' => 'inbox',
                'permission' => 'view_inbox',
                'module_slug' => 'inbox',
                'label' => 'Inbox',
                'icon' => 'inbox',
                'category' => 'main',
            ],
            [
                'route' => 'viber',
                'permission' => 'view_viber',
                'module_slug' => 'viber',
                'label' => 'Viber',
                'icon' => 'viber',
                'category' => 'main',
            ],
            [
                'route' => 'whatsapp',
                'permission' => 'view_whatsapp',
                'module_slug' => 'whatsapp',
                'label' => 'WhatsApp',
                'icon' => 'whatsapp',
                'category' => 'main',
            ],
            [
                'route' => 'facebook',
                'permission' => 'view_facebook',
                'module_slug' => 'facebook',
                'label' => 'Facebook',
                'icon' => 'facebook',
                'category' => 'main',
            ],
            [
                'route' => 'sms',
                'permission' => 'view_sms',
                'module_slug' => 'sms',
                'label' => 'SMS',
                'icon' => 'sms',
                'category' => 'main',
            ],
            [
                'route' => 'broadcast-messaging',
                'permission' => 'view_broadcast_messaging',
                'module_slug' => 'broadcast-messaging',
                'label' => 'Broadcast Messaging',
                'icon' => 'megaphone',
                'category' => 'main',
            ],
            [
                'route' => 'billing',
                'permission' => 'view_billing',
                'module_slug' => 'billing',
                'label' => 'Billing & Payments',
                'icon' => 'credit-card',
                'category' => 'main',
            ],
            [
                'route' => 'tickets',
                'permission' => 'view_tickets',
                'module_slug' => 'tickets',
                'label' => 'Tickets & Helpdesk',
                'icon' => 'mail',
                'category' => 'main',
            ],
            [
                'route' => 'knowledge-base',
                'permission' => 'view_knowledge_base',
                'module_slug' => 'knowledge-base',
                'label' => 'Knowledge Base',
                'icon' => 'book',
                'category' => 'main',
            ],
            [
                'route' => 'integrations',
                'permission' => 'view_integrations',
                'module_slug' => 'integrations',
                'label' => 'Integrations',
                'icon' => 'link',
                'category' => 'main',
            ],
            [
                'route' => 'calendar',
                'permission' => 'view_calendar',
                'module_slug' => 'calendar',
                'label' => 'Calendar',
                'icon' => 'calendar',
                'category' => 'main',
            ],
            // [
            //     'route' => 'email-tracking',
            //     'permission' => 'view_email_tracking',
            //     'module_slug' => 'email-tracking',
            //     'label' => 'Email Tracking',
            //     'icon' => 'mail',
            //     'category' => 'main',
            // ],
            [
                'route' => 'openai',
                'permission' => 'view_ai_assistant',
                'module_slug' => 'openai',
                'label' => 'AI Assistant',
                'icon' => 'zap',
                'category' => 'main',
            ],
            [
                'route' => 'change-password',
                'permission' => 'view_change_password',
                'module_slug' => 'change-password',
                'label' => 'Change Password',
                'icon' => 'key',
                'category' => 'main',
            ],
        ];
    }

    /**
     * Check if a user can access a module (permission + company module filter).
     *
     * @param  array<string>  $userPermissions
     * @param  array<string>|null  $companyModuleSlugs
     * @param  string|array<string>  $permission  Single permission or list (any match)
     * @param  string|array<string>|null  $moduleSlug  Single slug or list (any match)
     */
    public static function canAccessModule(array $userPermissions, ?array $companyModuleSlugs, string|array $permission, string|array|null $moduleSlug = null): bool
    {
        if (empty($userPermissions)) {
            return false;
        }

        $permissions = is_array($permission) ? $permission : [$permission];
        if (empty(array_intersect($permissions, $userPermissions))) {
            return false;
        }

        if ($companyModuleSlugs !== null && $moduleSlug !== null) {
            $slugs = is_array($moduleSlug) ? $moduleSlug : [$moduleSlug];
            if (empty(array_intersect($slugs, $companyModuleSlugs))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get filtered menu items based on user permissions and company modules
     *
     * @param  array<string>  $userPermissions  Permission slugs the user has
     * @param  array<string>|null  $companyModuleSlugs  Module slugs the company has access to (null = skip module filter, e.g. admin)
     */
    public static function getFilteredMenuItems(array $userPermissions = [], ?array $companyModuleSlugs = null): array
    {
        $items = self::getMenuItems();

        // If no permissions set, return empty array (fail secure)
        if (empty($userPermissions)) {
            return [];
        }

        return array_filter($items, function ($item) use ($userPermissions, $companyModuleSlugs) {
            if (isset($item['permission_any'])) {
                if (empty(array_intersect($item['permission_any'], $userPermissions))) {
                    return false;
                }
            } elseif (! in_array($item['permission'], $userPermissions)) {
                return false;
            }
            // When company has modules configured, only show items whose module is enabled for the company
            if ($companyModuleSlugs !== null) {
                if (isset($item['module_slugs']) && is_array($item['module_slugs'])) {
                    return ! empty(array_intersect($item['module_slugs'], $companyModuleSlugs));
                }
                if (isset($item['module_slug'])) {
                    return in_array($item['module_slug'], $companyModuleSlugs);
                }
            }

            return true;
        });
    }
}
