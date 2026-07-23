<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AddWiseAccessPermissionsSeeder extends Seeder
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
            [
                'name' => 'view_saved_for_wise',
                'slug' => 'view_saved_for_wise',
                'display_name' => 'Saved for Wise',
                'description' => 'Access to saved payroll reports for Wise transfers',
                'category' => 'payroll',
            ],
            [
                'name' => 'view_wise_recipients',
                'slug' => 'view_wise_recipients',
                'display_name' => 'Wise Recipients',
                'description' => 'Access to Wise Recipients & Employee Assignment page',
                'category' => 'payroll',
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

            try {
                $adminRole = Role::where('slug', 'admin')
                    ->where('company_id', $company->id)
                    ->first();

                if ($adminRole && ! empty($createdPermissions)) {
                    $adminRole->permissions()->syncWithoutDetaching($createdPermissions);
                    $this->command->info("Assigned Wise access permissions to admin role for company: {$company->name}");
                } elseif (! $adminRole) {
                    $this->command->warn("Admin role not found for company: {$company->name}. Permissions created but not assigned.");
                }
            } catch (\Exception $e) {
                $this->command->error("Error assigning permissions to admin role for company {$company->id}: {$e->getMessage()}");
            }
        }

        $this->command->info('Wise access permissions created successfully for all companies!');
    }
}
