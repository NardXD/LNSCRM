<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;
use App\Models\Company;

class AddProjectManagementPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = Company::all();

        $newPermissions = [
            [
                'name' => 'create_project_management',
                'slug' => 'create_project_management',
                'display_name' => 'Create Projects',
                'description' => 'Create new projects in project management',
                'category' => 'settings',
            ],
            [
                'name' => 'create_task_management',
                'slug' => 'create_task_management',
                'display_name' => 'Create Tasks',
                'description' => 'Create new tasks in project management',
                'category' => 'settings',
            ],
            [
                'name' => 'edit_project_management',
                'slug' => 'edit_project_management',
                'display_name' => 'Edit Projects & Tasks',
                'description' => 'Edit projects and tasks in project management',
                'category' => 'settings',
            ],
            [
                'name' => 'delete_project_management',
                'slug' => 'delete_project_management',
                'display_name' => 'Delete Projects & Tasks',
                'description' => 'Delete projects and tasks in project management',
                'category' => 'settings',
            ],
        ];

        foreach ($companies as $company) {
            foreach ($newPermissions as $permissionData) {
                Permission::firstOrCreate(
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
            }
        }

        $this->command->info('Project management permissions (create projects, create tasks, edit, delete) created successfully for all companies!');
    }
}

