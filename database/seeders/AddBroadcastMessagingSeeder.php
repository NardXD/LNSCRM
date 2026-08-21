<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AddBroadcastMessagingSeeder extends Seeder
{
    public function run(): void
    {
        $module = Module::updateOrCreate(
            ['slug' => 'broadcast-messaging'],
            [
                'name' => 'Broadcast Messaging',
                'description' => 'Send bulk SMS and email broadcasts to leads, clients, and contacts',
                'route' => 'broadcast-messaging',
                'sort_order' => 11,
                'is_active' => true,
            ]
        );

        $permissionDefs = [
            [
                'slug' => 'view_broadcast_messaging',
                'display_name' => 'View Broadcast Messaging',
                'description' => 'Access to broadcast messaging history and details',
            ],
            [
                'slug' => 'send_broadcast_sms',
                'display_name' => 'Send SMS Broadcasts',
                'description' => 'Create and send bulk SMS broadcasts via Twilio',
            ],
            [
                'slug' => 'send_broadcast_email',
                'display_name' => 'Send Email Broadcasts',
                'description' => 'Create and send bulk email broadcasts via Microsoft 365',
            ],
        ];

        foreach (Company::all() as $company) {
            if (! $company->modules()->where('modules.id', $module->id)->exists()) {
                $company->modules()->attach($module->id, [
                    'is_enabled' => true,
                    'granted_at' => now(),
                ]);
            }

            $permissions = [];
            foreach ($permissionDefs as $def) {
                $permissions[] = Permission::firstOrCreate(
                    [
                        'slug' => $def['slug'],
                        'company_id' => $company->id,
                    ],
                    [
                        'name' => $def['slug'],
                        'display_name' => $def['display_name'],
                        'description' => $def['description'],
                        'category' => 'main',
                    ]
                );
            }

            $roles = Role::where('company_id', $company->id)->get();
            foreach ($roles as $role) {
                $hasRelated = $role->permissions()
                    ->whereIn('slug', [
                        'view_sms',
                        'send_sms',
                        'view_inbox',
                        'view_messaging',
                        'view_leads',
                        'view_client_management',
                        'view_admin_control',
                        'view_dashboard',
                    ])
                    ->exists();

                if (! $hasRelated) {
                    continue;
                }

                foreach ($permissions as $permission) {
                    if (! $role->permissions()->where('permissions.id', $permission->id)->exists()) {
                        $role->permissions()->attach($permission->id);
                    }
                }
            }
        }
    }
}
