<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AddLiveViewPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissionData = [
            'name' => 'view_live_screen',
            'slug' => 'view_live_screen',
            'display_name' => 'View Live Screen',
            'description' => 'Watch employee live screen streams while they are recording',
            'category' => 'employee_monitoring',
        ];

        Company::query()->each(function (Company $company) use ($permissionData) {
            $permission = Permission::firstOrCreate(
                ['slug' => $permissionData['slug'], 'company_id' => $company->id],
                array_merge($permissionData, ['company_id' => $company->id])
            );

            Role::query()
                ->where('company_id', $company->id)
                ->whereIn('name', ['Administrator', 'Company Admin', 'Manager', 'Admin'])
                ->each(function (Role $role) use ($permission) {
                    if (! $role->permissions()->where('permissions.id', $permission->id)->exists()) {
                        $role->permissions()->attach($permission->id);
                    }
                });
        });
    }
}
