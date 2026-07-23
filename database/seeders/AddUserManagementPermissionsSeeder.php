<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AddUserManagementPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = Company::all();

        if ($companies->isEmpty()) {
            $this->command->warn('No companies found. Please create a company first.');

            return;
        }

        $newPermissions = [
            // Roles & Permissions Tab
            [
                'name' => 'view_user_roles_permissions',
                'slug' => 'view_user_roles_permissions',
                'display_name' => 'View Roles & Permissions',
                'description' => 'Access to roles and permissions management tab',
                'category' => 'settings',
            ],
            // Company Setup Tab
            [
                'name' => 'view_user_company_setup',
                'slug' => 'view_user_company_setup',
                'display_name' => 'View Company Setup',
                'description' => 'Access to company setup management tab',
                'category' => 'settings',
            ],
            // Employee Profile Tab
            [
                'name' => 'view_user_employee_profile',
                'slug' => 'view_user_employee_profile',
                'display_name' => 'View Employee Profile',
                'description' => 'Access to employee profile management tab',
                'category' => 'settings',
            ],
            // Departments Tab
            [
                'name' => 'view_user_departments',
                'slug' => 'view_user_departments',
                'display_name' => 'View Departments',
                'description' => 'Access to departments management tab',
                'category' => 'settings',
            ],
            // Role Based Access Tab
            [
                'name' => 'view_user_role_based_access',
                'slug' => 'view_user_role_based_access',
                'display_name' => 'View Role Based Access',
                'description' => 'Access to role based access control tab',
                'category' => 'settings',
            ],
        ];

        foreach ($companies as $company) {
            $createdPermissions = [];

            foreach ($newPermissions as $permissionData) {
                try {
                    $permission = Permission::firstOrCreate(
                        [
                            'slug' => $permissionData['slug'],
                            'company_id' => $company->id,
                        ],
                        [
                            'name' => $permissionData['name'],
                            'display_name' => $permissionData['display_name'],
                            'description' => $permissionData['description'],
                            'category' => $permissionData['category'],
                        ]
                    );
                    $createdPermissions[] = $permission->id;
                } catch (\Exception $e) {
                    $this->command->error("Error creating permission {$permissionData['slug']} for company {$company->id}: {$e->getMessage()}");
                }
            }

            // Assign all new user management permissions to the 'admin' role for this company
            try {
                $adminRole = Role::where('slug', 'admin')
                    ->where('company_id', $company->id)
                    ->first();

                if ($adminRole && ! empty($createdPermissions)) {
                    $adminRole->permissions()->syncWithoutDetaching($createdPermissions);
                    $this->command->info("Assigned user management permissions to admin role for company: {$company->name}");
                } elseif (! $adminRole) {
                    $this->command->warn("Admin role not found for company: {$company->name}. Permissions created but not assigned.");
                }
            } catch (\Exception $e) {
                $this->command->error("Error assigning permissions to admin role for company {$company->id}: {$e->getMessage()}");
            }
        }

        $this->command->info('User management permissions created successfully for all companies!');
    }
}
