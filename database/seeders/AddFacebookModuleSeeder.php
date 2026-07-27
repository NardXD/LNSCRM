<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AddFacebookModuleSeeder extends Seeder
{
    public function run(): void
    {
        $module = Module::updateOrCreate(
            ['slug' => 'facebook'],
            [
                'name' => 'Facebook & Instagram',
                'description' => 'Facebook Page Messenger and Instagram Direct messaging',
                'route' => 'facebook',
                'sort_order' => 11,
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
                    'slug' => 'view_facebook',
                    'company_id' => $company->id,
                ],
                [
                    'name' => 'view_facebook',
                    'display_name' => 'Facebook & Instagram',
                    'description' => 'Access to Facebook Messenger and Instagram Direct conversations',
                    'category' => 'main',
                ]
            );

            $roles = Role::where('company_id', $company->id)->get();
            foreach ($roles as $role) {
                $hasRelated = $role->permissions()
                    ->whereIn('slug', ['view_messaging', 'view_inbox', 'view_whatsapp', 'view_viber', 'view_integrations', 'view_admin_control', 'view_dashboard'])
                    ->exists();
                if ($hasRelated && ! $role->permissions()->where('permissions.id', $permission->id)->exists()) {
                    $role->permissions()->attach($permission->id);
                }
            }
        }
    }
}
