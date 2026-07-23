<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AddTeamManagementPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define team management permissions
        $teamManagementPermissions = [
            [
                'name' => 'View Team Management',
                'slug' => 'view_team_management',
                'description' => 'Access to view the Team Management module',
                'category' => 'sidebar',
                'display_name' => 'Team Management',
            ],
            [
                'name' => 'Create Team',
                'slug' => 'create_team_management',
                'description' => 'Permission to create new teams',
                'category' => 'team_management',
                'display_name' => 'Create Team',
            ],
            [
                'name' => 'Edit Team',
                'slug' => 'edit_team_management',
                'description' => 'Permission to edit existing teams',
                'category' => 'team_management',
                'display_name' => 'Edit Team',
            ],
            [
                'name' => 'Delete Team',
                'slug' => 'delete_team_management',
                'description' => 'Permission to delete teams',
                'category' => 'team_management',
                'display_name' => 'Delete Team',
            ],
            [
                'name' => 'View Team Time Tracking',
                'slug' => 'view_team_time_tracking',
                'description' => 'Permission to view team members time tracking records',
                'category' => 'team_management',
                'display_name' => 'View Team Time Tracking',
            ],
            [
                'name' => 'View Team Recordings',
                'slug' => 'view_team_recordings',
                'description' => 'Permission to view team members screen recordings',
                'category' => 'team_management',
                'display_name' => 'View Team Recordings',
            ],
            [
                'name' => 'Manage Team Members',
                'slug' => 'manage_team_members',
                'description' => 'Permission to add/remove team members',
                'category' => 'team_management',
                'display_name' => 'Manage Team Members',
            ],
        ];

        // Get all companies
        $companies = Company::all();

        foreach ($companies as $company) {
            $this->command->info("Adding team management permissions for company: {$company->name}");

            $createdPermissionIds = [];

            // Create permissions for this company
            foreach ($teamManagementPermissions as $permissionData) {
                $permission = Permission::firstOrCreate(
                    [
                        'slug' => $permissionData['slug'],
                        'company_id' => $company->id,
                    ],
                    [
                        'name' => $permissionData['name'],
                        'description' => $permissionData['description'],
                        'category' => $permissionData['category'],
                        'display_name' => $permissionData['display_name'],
                    ]
                );

                $createdPermissionIds[] = $permission->id;
            }

            // Find the Admin role for this company and add all team management permissions
            $adminRole = Role::where('company_id', $company->id)
                ->where(function ($query) {
                    $query->where('slug', 'admin')
                        ->orWhere('slug', 'administrator')
                        ->orWhere('name', 'like', '%admin%');
                })
                ->first();

            if ($adminRole) {
                // Add all new permissions to admin role
                $adminRole->permissions()->syncWithoutDetaching($createdPermissionIds);
                $this->command->info("  - Added permissions to Admin role");
            }

            // Find the Manager role and add view + manage permissions
            $managerRole = Role::where('company_id', $company->id)
                ->where(function ($query) {
                    $query->where('slug', 'manager')
                        ->orWhere('name', 'like', '%manager%');
                })
                ->first();

            if ($managerRole) {
                $managerPermissions = Permission::where('company_id', $company->id)
                    ->whereIn('slug', [
                        'view_team_management',
                        'view_team_time_tracking',
                        'view_team_recordings',
                    ])
                    ->pluck('id');

                $managerRole->permissions()->syncWithoutDetaching($managerPermissions);
                $this->command->info("  - Added view permissions to Manager role");
            }
        }

        $this->command->info('Team management permissions seeded successfully!');
    }
}
