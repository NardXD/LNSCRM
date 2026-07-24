<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AddViberModuleSeeder extends Seeder
{
    public function run(): void
    {
        $module = Module::updateOrCreate(
            ['slug' => 'viber'],
            [
                'name' => 'Viber',
                'description' => 'Viber Business chat, media, and customer conversations',
                'route' => 'viber',
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
                    'slug' => 'view_viber',
                    'company_id' => $company->id,
                ],
                [
                    'name' => 'view_viber',
                    'display_name' => 'Viber',
                    'description' => 'Access to Viber Business conversations',
                    'category' => 'main',
                ]
            );

            $roles = Role::where('company_id', $company->id)->get();
            foreach ($roles as $role) {
                $hasRelated = $role->permissions()
                    ->whereIn('slug', ['view_messaging', 'view_inbox', 'view_integrations', 'view_admin_control', 'view_dashboard'])
                    ->exists();
                if ($hasRelated && ! $role->permissions()->where('permissions.id', $permission->id)->exists()) {
                    $role->permissions()->attach($permission->id);
                }
            }
        }
    }
}
