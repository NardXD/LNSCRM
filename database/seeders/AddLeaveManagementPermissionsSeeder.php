<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AddLeaveManagementPermissionsSeeder extends Seeder
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
            // Main Leave Management Permission
            [
                'name' => 'view_leave_management',
                'slug' => 'view_leave_management',
                'display_name' => 'View Leave Management',
                'description' => 'Access to leave management module',
                'category' => 'Leave Management',
            ],
            // Leave Stats Permission
            [
                'name' => 'view_leave_stats',
                'slug' => 'view_leave_stats',
                'display_name' => 'View Leave Statistics',
                'description' => 'View leave statistics and dashboard',
                'category' => 'Leave Management',
            ],
            // Create Leave Request Permission
            [
                'name' => 'create_leave_request',
                'slug' => 'create_leave_request',
                'display_name' => 'Create Leave Request',
                'description' => 'Create new leave requests',
                'category' => 'Leave Management',
            ],
            // View Leave Credits Permission
            [
                'name' => 'view_leave_credits',
                'slug' => 'view_leave_credits',
                'display_name' => 'View Leave Credits',
                'description' => 'View leave credits for users',
                'category' => 'Leave Management',
            ],
            // Manage Leave Credits Permission
            [
                'name' => 'manage_leave_credits',
                'slug' => 'manage_leave_credits',
                'display_name' => 'Manage Leave Credits',
                'description' => 'Add and manage leave credits for users',
                'category' => 'Leave Management',
            ],
            // View Leave Calendar Permission
            [
                'name' => 'view_leave_calendar',
                'slug' => 'view_leave_calendar',
                'display_name' => 'View Leave Calendar',
                'description' => 'View leave calendar and employees on leave',
                'category' => 'Leave Management',
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

            // Assign all new leave management permissions to the 'admin' role for this company
            try {
                $adminRole = Role::where('slug', 'admin')
                    ->where('company_id', $company->id)
                    ->first();

                if ($adminRole && ! empty($createdPermissions)) {
                    $adminRole->permissions()->syncWithoutDetaching($createdPermissions);
                    $this->command->info("Assigned leave management permissions to admin role for company: {$company->name}");
                } elseif (! $adminRole) {
                    $this->command->warn("Admin role not found for company: {$company->name}. Permissions created but not assigned.");
                }
            } catch (\Exception $e) {
                $this->command->error("Error assigning permissions to admin role for company {$company->id}: {$e->getMessage()}");
            }
        }

        $this->command->info('Leave management permissions created successfully for all companies!');
    }
}
