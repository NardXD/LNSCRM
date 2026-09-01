<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AddQuotationBuilderMicrosoft365MailPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $companies = Company::all();

        if ($companies->isEmpty()) {
            $this->command->warn('No companies found. Skipping quotation builder M365 mail permission.');

            return;
        }

        $permissionData = [
            'name' => 'view_quotation_builder_microsoft_365_mail',
            'slug' => 'view_quotation_builder_microsoft_365_mail',
            'display_name' => 'Microsoft 365 Mail',
            'description' => 'Access to configure Microsoft 365 mail for quotation builder',
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

        $this->command->info('Quotation Builder Microsoft 365 Mail permission created for all companies.');
    }
}
