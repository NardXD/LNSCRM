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
                'category' => 'main',
            ],
            [
                'name' => 'manage_phone_contacts',
                'slug' => 'manage_phone_contacts',
                'display_name' => 'Manage Phone Contacts',
                'description' => 'Create and manage phone system contacts',
                'category' => 'main',
            ],
            [
                'name' => 'view_sms',
                'slug' => 'view_sms',
                'display_name' => 'View SMS',
                'description' => 'View SMS conversations in phone system',
                'category' => 'main',
            ],
            [
                'name' => 'send_sms',
                'slug' => 'send_sms',
                'display_name' => 'Send SMS',
                'description' => 'Send SMS messages from phone system',
                'category' => 'main',
            ],
            [
                'name' => 'manage_twilio_numbers',
                'slug' => 'manage_twilio_numbers',
                'display_name' => 'Manage Twilio Numbers',
                'description' => 'Purchase and assign Twilio phone numbers (admin only)',
                'category' => 'main',
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

            $adminRoles = Role::query()
                ->where('company_id', $company->id)
                ->where(function ($q) {
                    $q->where('slug', 'admin')
                        ->orWhere('slug', 'like', '%admin%')
                        ->orWhere('name', 'like', '%admin%')
                        ->orWhere('name', 'like', '%owner%');
                })
                ->get();

            foreach ($adminRoles as $adminRole) {
                $adminRole->permissions()->syncWithoutDetaching($createdIds);
            }

            $agentPermIds = Permission::query()
                ->where('company_id', $company->id)
                ->whereIn('slug', ['view_call_history', 'manage_phone_contacts', 'view_sms', 'send_sms'])
                ->pluck('id');

            $phoneRoles = Role::query()
                ->where('company_id', $company->id)
                ->where(function ($q) {
                    $q->where('slug', 'like', '%phone%')
                        ->orWhere('name', 'like', '%phone%');
                })
                ->get();

            $phoneSystemPermission = Permission::query()
                ->where('company_id', $company->id)
                ->where('slug', 'view_phone_system')
                ->first();

            if ($phoneSystemPermission) {
                $phoneRoles = $phoneRoles->merge(
                    Role::query()
                        ->where('company_id', $company->id)
                        ->whereHas('permissions', fn ($q) => $q->where('permissions.id', $phoneSystemPermission->id))
                        ->get()
                )->unique('id');
            }

            foreach ($phoneRoles as $phoneRole) {
                $phoneRole->permissions()->syncWithoutDetaching($agentPermIds);
            }
        }

        $this->command->info('Phone system permissions created for all companies.');
    }
}
