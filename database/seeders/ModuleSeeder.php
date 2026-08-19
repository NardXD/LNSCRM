<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $modules = [
            ['slug' => 'dashboard', 'name' => 'Dashboard', 'description' => 'Main dashboard and overview', 'route' => 'dashboard', 'sort_order' => 1],
            ['slug' => 'time-tracking', 'name' => 'Time Tracking', 'description' => 'Track employee time and attendance', 'route' => 'time-tracking', 'sort_order' => 2],
            ['slug' => 'user-management', 'name' => 'User Management', 'description' => 'Manage users and permissions', 'route' => 'user-management', 'sort_order' => 3],
            ['slug' => 'employee-monitoring', 'name' => 'Employee Monitoring', 'description' => 'Monitor employee activity', 'route' => 'employee-monitoring', 'sort_order' => 4],
            ['slug' => 'phone-system', 'name' => 'Phone System', 'description' => 'VoIP phone system integration', 'route' => 'phone-system', 'sort_order' => 5],
            ['slug' => 'payroll', 'name' => 'Payroll', 'description' => 'Automated payroll processing', 'route' => 'payroll', 'sort_order' => 6],
            ['slug' => 'pnl', 'name' => 'P&L', 'description' => 'Profit and loss from payroll conversion and billing invoices', 'route' => 'pnl', 'sort_order' => 22],
            ['slug' => 'project-management', 'name' => 'Project Management', 'description' => 'Project tracking and management', 'route' => 'project-management', 'sort_order' => 7],
            ['slug' => 'team-management', 'name' => 'Team Management', 'description' => 'Manage teams and members', 'route' => 'team-management', 'sort_order' => 8],
            ['slug' => 'leave-management', 'name' => 'Leave Management', 'description' => 'Leave requests and credits', 'route' => 'leave-management', 'sort_order' => 9],
            ['slug' => 'messaging', 'name' => 'Messaging', 'description' => 'Internal messaging system', 'route' => 'messaging', 'sort_order' => 10],
            ['slug' => 'inbox', 'name' => 'Inbox', 'description' => 'Personal and shared Outlook inboxes with assignment, tags, and rules', 'route' => 'inbox', 'sort_order' => 10],
            ['slug' => 'viber', 'name' => 'Viber', 'description' => 'Viber Business chat, media, and customer conversations', 'route' => 'viber', 'sort_order' => 10],
            ['slug' => 'whatsapp', 'name' => 'WhatsApp', 'description' => 'WhatsApp Business Cloud API chat, media, and customer conversations', 'route' => 'whatsapp', 'sort_order' => 10],
            ['slug' => 'sms', 'name' => 'SMS', 'description' => 'Twilio SMS conversations and outbound text messaging', 'route' => 'sms', 'sort_order' => 10],
            ['slug' => 'facebook', 'name' => 'Facebook & Instagram', 'description' => 'Twilio Facebook Messenger and Instagram Direct messaging', 'route' => 'facebook', 'sort_order' => 11],
            ['slug' => 'billing', 'name' => 'Billing & Payments', 'description' => 'Invoice and payment management', 'route' => 'billing', 'sort_order' => 12],
            ['slug' => 'client-management', 'name' => 'Client Management', 'description' => 'CRM and client database', 'route' => 'client-management', 'sort_order' => 12],
            ['slug' => 'tickets', 'name' => 'Tickets & Helpdesk', 'description' => 'Support ticket system', 'route' => 'tickets', 'sort_order' => 13],
            ['slug' => 'knowledge-base', 'name' => 'Knowledge Base', 'description' => 'Documentation and knowledge base', 'route' => 'knowledge-base', 'sort_order' => 14],
            ['slug' => 'integrations', 'name' => 'Integrations', 'description' => 'Third-party integrations', 'route' => 'integrations', 'sort_order' => 15],
            ['slug' => 'quotation-builder', 'name' => 'Quotation Builder', 'description' => 'Create and manage quotations', 'route' => 'quotation-builder', 'sort_order' => 16],
            ['slug' => 'contracts', 'name' => 'Contracts & E-Sign', 'description' => 'Create contracts and collect electronic signatures', 'route' => 'contracts', 'sort_order' => 23],
            ['slug' => 'calendar', 'name' => 'Calendar', 'description' => 'Calendar and scheduling', 'route' => 'calendar', 'sort_order' => 17],
            ['slug' => 'email-tracking', 'name' => 'Email Tracking', 'description' => 'Track email opens and clicks', 'route' => 'email-tracking', 'sort_order' => 18],
            ['slug' => 'openai', 'name' => 'AI Assistant', 'description' => 'OpenAI integration', 'route' => 'openai', 'sort_order' => 19],
            ['slug' => 'change-password', 'name' => 'Change Password', 'description' => 'Change your account password', 'route' => 'change-password', 'sort_order' => 20],
        ];

        foreach ($modules as $module) {
            Module::updateOrCreate(
                ['slug' => $module['slug']],
                array_merge($module, ['is_active' => true])
            );
        }
    }
}
