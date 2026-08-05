<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AddSmsModuleSeeder extends Seeder
{
    public function run(): void
    {
        $module = Module::updateOrCreate(
            ['slug' => 'sms'],
            [
                'name' => 'SMS',
                'description' => 'Infobip SMS conversations and outbound text messaging',
                'route' => 'sms',
                'sort_order' => 10,
                'is_active' => true,
            ]
        );

        foreach (Company::all() as $company) {
            if (! $company->modules()->where('modules.id', $module->id)->exists()) {
                $company->modules()->attach($module->id, [
                    'is_enabled' => true,
                    'granted_at' => now(),
                ]);
            }

            $viewPermission = Permission::firstOrCreate(
                [
                    'slug' => 'view_sms',
                    'company_id' => $company->id,
                ],
                [
                    'name' => 'view_sms',
                    'display_name' => 'View SMS',
                    'description' => 'View SMS conversations via Infobip',
                    'category' => 'main',
                ]
            );

            // Keep existing phone-category rows usable; upgrade display category when present
            if ($viewPermission->category !== 'main') {
                $viewPermission->update([
                    'category' => 'main',
                    'description' => 'View SMS conversations via Infobip',
                ]);
            }

            $sendPermission = Permission::firstOrCreate(
                [
                    'slug' => 'send_sms',
                    'company_id' => $company->id,
                ],
                [
                    'name' => 'send_sms',
                    'display_name' => 'Send SMS',
                    'description' => 'Send SMS messages via Infobip',
                    'category' => 'main',
                ]
            );

            $roles = Role::where('company_id', $company->id)->get();
            foreach ($roles as $role) {
                $hasRelated = $role->permissions()
                    ->whereIn('slug', [
                        'view_phone_system',
                        'view_messaging',
                        'view_inbox',
                        'view_whatsapp',
                        'view_viber',
                        'view_integrations',
                        'view_admin_control',
                        'view_dashboard',
                    ])
                    ->exists();

                if (! $hasRelated) {
                    continue;
                }

                foreach ([$viewPermission, $sendPermission] as $permission) {
                    if (! $role->permissions()->where('permissions.id', $permission->id)->exists()) {
                        $role->permissions()->attach($permission->id);
                    }
                }
            }
        }
    }
}
