<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AddPhoneSystemPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $companies = Company::all();

        if ($companies->isEmpty()) {
            $this->command->warn('No companies found.');

            return;
        }

        $phonePermissions = [
            [
                'name' => 'view_call_history',
                'slug' => 'view_call_history',
                'display_name' => 'View Call History',
                'description' => 'View persisted phone call history',
                'category' => 'phone',
            ],
            [
                'name' => 'manage_phone_contacts',
                'slug' => 'manage_phone_contacts',
                'display_name' => 'Manage Phone Contacts',
                'description' => 'Create and manage phone system contacts',
                'category' => 'phone',
            ],
            [
                'name' => 'view_sms',
                'slug' => 'view_sms',
                'display_name' => 'View SMS',
                'description' => 'View SMS conversations in phone system',
                'category' => 'phone',
            ],
            [
                'name' => 'send_sms',
                'slug' => 'send_sms',
                'display_name' => 'Send SMS',
                'description' => 'Send SMS messages from phone system',
                'category' => 'phone',
            ],
            [
                'name' => 'manage_twilio_numbers',
                'slug' => 'manage_twilio_numbers',
                'display_name' => 'Manage Phone Numbers',
                'description' => 'Purchase and assign Infobip phone numbers (admin only)',
                'category' => 'phone',
            ],
        ];

        foreach ($companies as $company) {
            $createdIds = [];

            foreach ($phonePermissions as $permissionData) {
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
                $createdIds[] = $permission->id;
            }

            $adminRole = Role::query()
                ->where('slug', 'admin')
                ->where('company_id', $company->id)
                ->first();

            if ($adminRole) {
                $adminRole->permissions()->syncWithoutDetaching($createdIds);
            }

            $phoneRole = Role::query()
                ->where('company_id', $company->id)
                ->where(function ($q) {
                    $q->where('slug', 'like', '%phone%')
                        ->orWhere('name', 'like', '%phone%');
                })
                ->first();

            if ($phoneRole) {
                $nonAdminPerms = Permission::query()
                    ->where('company_id', $company->id)
                    ->whereIn('slug', ['view_call_history', 'manage_phone_contacts', 'view_sms', 'send_sms'])
                    ->pluck('id');
                $phoneRole->permissions()->syncWithoutDetaching($nonAdminPerms);
            }
        }

        $this->command->info('Phone system permissions created for all companies.');
    }
}
