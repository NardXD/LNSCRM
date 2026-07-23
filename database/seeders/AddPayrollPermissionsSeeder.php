<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AddPayrollPermissionsSeeder extends Seeder
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
            // Time In/Out Module Permissions
            [
                'name' => 'view_time_in_out',
                'slug' => 'view_time_in_out',
                'display_name' => 'View Time In/Out',
                'description' => 'Access to time in/out tracking module',
                'category' => 'payroll',
            ],
            [
                'name' => 'edit_time_in_out',
                'slug' => 'edit_time_in_out',
                'display_name' => 'Edit Time In/Out',
                'description' => 'Edit time in/out records',
                'category' => 'payroll',
            ],
            [
                'name' => 'export_time_in_out',
                'slug' => 'export_time_in_out',
                'display_name' => 'Export Time In/Out',
                'description' => 'Export time in/out records',
                'category' => 'payroll',
            ],
            // Salary Computation Module Permissions
            [
                'name' => 'view_salary_computation',
                'slug' => 'view_salary_computation',
                'display_name' => 'View Salary Computation',
                'description' => 'Access to salary computation module',
                'category' => 'payroll',
            ],
            [
                'name' => 'edit_salary_computation',
                'slug' => 'edit_salary_computation',
                'display_name' => 'Edit Salary Computation',
                'description' => 'Edit salary computation records',
                'category' => 'payroll',
            ],
            [
                'name' => 'save_salary_computation',
                'slug' => 'save_salary_computation',
                'display_name' => 'Save Salary Computation',
                'description' => 'Save salary computation records',
                'category' => 'payroll',
            ],
            [
                'name' => 'calculate_salary_computation',
                'slug' => 'calculate_salary_computation',
                'display_name' => 'Calculate Salary Computation',
                'description' => 'Calculate salary computation',
                'category' => 'payroll',
            ],
            // Payroll Report Module Permissions
            [
                'name' => 'view_payroll_report',
                'slug' => 'view_payroll_report',
                'display_name' => 'View Payroll Report',
                'description' => 'Access to payroll report module',
                'category' => 'payroll',
            ],
            [
                'name' => 'view_payroll_sales_rep_report',
                'slug' => 'view_payroll_sales_rep_report',
                'display_name' => 'Payroll Report (Sales Rep)',
                'description' => 'Access to Payroll Report by Sales Rep page and related APIs',
                'category' => 'payroll',
            ],
            [
                'name' => 'generate_payroll_report',
                'slug' => 'generate_payroll_report',
                'display_name' => 'Generate Payroll Report',
                'description' => 'Generate payroll reports',
                'category' => 'payroll',
            ],
            [
                'name' => 'export_payroll_report',
                'slug' => 'export_payroll_report',
                'display_name' => 'Export Payroll Report',
                'description' => 'Export payroll reports to Excel',
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

            // Assign all new payroll permissions to the 'admin' role for this company
            try {
                $adminRole = Role::where('slug', 'admin')
                    ->where('company_id', $company->id)
                    ->first();

                if ($adminRole && ! empty($createdPermissions)) {
                    $adminRole->permissions()->syncWithoutDetaching($createdPermissions);
                    $this->command->info("Assigned payroll permissions to admin role for company: {$company->name}");
                } elseif (! $adminRole) {
                    $this->command->warn("Admin role not found for company: {$company->name}. Permissions created but not assigned.");
                }
            } catch (\Exception $e) {
                $this->command->error("Error assigning permissions to admin role for company {$company->id}: {$e->getMessage()}");
            }
        }

        $this->command->info('Payroll permissions created successfully for all companies!');
    }
}
