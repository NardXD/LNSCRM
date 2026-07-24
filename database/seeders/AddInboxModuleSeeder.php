<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AddInboxModuleSeeder extends Seeder
{
    public function run(): void
    {
        $module = Module::updateOrCreate(
            ['slug' => 'inbox'],
            [
                'name' => 'Inbox',
                'description' => 'Personal and shared Outlook inboxes with assignment, tags, and rules',
                'route' => 'inbox',
                'sort_order' => 10,
                'is_active' => true,
            ]
        );

        foreach (Company::all() as $company) {
            if (! $company->modules()->where('modules.id', $module->id)->exists()) {
                $company->modules()->attach($module->id, [
                    'is_enabled' => true,
                    'granted_at' => now(),
                ]);
            }

            $permission = Permission::firstOrCreate(
                [
                    'slug' => 'view_inbox',
                    'company_id' => $company->id,
                ],
                [
                    'name' => 'view_inbox',
                    'display_name' => 'Inbox',
                    'description' => 'Access to personal and shared Outlook inboxes',
                    'category' => 'main',
                ]
            );

            // Grant to admin-like roles that already have messaging or integrations
            $roles = Role::where('company_id', $company->id)->get();
            foreach ($roles as $role) {
                $hasRelated = $role->permissions()
                    ->whereIn('slug', ['view_messaging', 'view_integrations', 'view_admin_control', 'view_dashboard'])
                    ->exists();
                if ($hasRelated && ! $role->permissions()->where('permissions.id', $permission->id)->exists()) {
                    $role->permissions()->attach($permission->id);
                }
            }
        }
    }
}
