<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AddQuotationBuilderEmailTemplatePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $companies = Company::all();

        if ($companies->isEmpty()) {
            $this->command->warn('No companies found. Skipping quotation builder email template permission.');

            return;
        }

        $permissionData = [
            'name' => 'view_quotation_builder_email_template',
            'slug' => 'view_quotation_builder_email_template',
            'display_name' => 'Email Template',
            'description' => 'Access to configure the quotation builder email template',
            'category' => 'quotation-builder',
        ];

        foreach ($companies as $company) {
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

            $adminRole = Role::where('slug', 'admin')
                ->where('company_id', $company->id)
                ->first();

            if ($adminRole) {
                $adminRole->permissions()->syncWithoutDetaching([$permission->id]);
            }
        }

        $this->command->info('Quotation Builder email template permission created for all companies.');
    }
}
