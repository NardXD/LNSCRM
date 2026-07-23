<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AddAdminUserManagementPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Adds role-based permissions for add/edit/delete on the admin user-management page.
     */
    public function run(): void
    {
        $companies = Company::all();

        if ($companies->isEmpty()) {
            $this->command->warn('No companies found. Skipping admin user-management permissions.');

            return;
        }

        $permissions = [
            ['slug' => 'admin_user_management_create_user', 'name' => 'admin_user_management_create_user', 'display_name' => 'Create Users (Admin)', 'category' => 'admin'],
            ['slug' => 'admin_user_management_edit_user', 'name' => 'admin_user_management_edit_user', 'display_name' => 'Edit Users (Admin)', 'category' => 'admin'],
            ['slug' => 'admin_user_management_delete_user', 'name' => 'admin_user_management_delete_user', 'display_name' => 'Delete Users (Admin)', 'category' => 'admin'],
            ['slug' => 'admin_user_management_create_role', 'name' => 'admin_user_management_create_role', 'display_name' => 'Create Roles (Admin)', 'category' => 'admin'],
            ['slug' => 'admin_user_management_edit_role', 'name' => 'admin_user_management_edit_role', 'display_name' => 'Edit Roles (Admin)', 'category' => 'admin'],
            ['slug' => 'admin_user_management_delete_role', 'name' => 'admin_user_management_delete_role', 'display_name' => 'Delete Roles (Admin)', 'category' => 'admin'],
            ['slug' => 'admin_user_management_create_permission', 'name' => 'admin_user_management_create_permission', 'display_name' => 'Create Permissions (Admin)', 'category' => 'admin'],
            ['slug' => 'admin_user_management_edit_permission', 'name' => 'admin_user_management_edit_permission', 'display_name' => 'Edit Permissions (Admin)', 'category' => 'admin'],
            ['slug' => 'admin_user_management_delete_permission', 'name' => 'admin_user_management_delete_permission', 'display_name' => 'Delete Permissions (Admin)', 'category' => 'admin'],
        ];

        foreach ($companies as $company) {
            $createdIds = [];
            foreach ($permissions as $p) {
                $permission = Permission::firstOrCreate(
                    [
                        'slug' => $p['slug'],
                        'company_id' => $company->id,
                    ],
                    [
                        'name' => $p['name'],
                        'display_name' => $p['display_name'],
                        'description' => 'Admin panel: '.$p['display_name'],
                        'category' => $p['category'],
                    ]
                );
                $createdIds[] = $permission->id;
            }

            $adminRole = Role::where('slug', 'admin')->where('company_id', $company->id)->first();
            if ($adminRole && ! empty($createdIds)) {
                $adminRole->permissions()->syncWithoutDetaching($createdIds);
            }
        }

        $this->command->info('Admin user-management permissions seeded.');
    }
}
