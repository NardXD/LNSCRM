<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AddWhatsAppModuleSeeder extends Seeder
{
    public function run(): void
    {
        $module = Module::updateOrCreate(
            ['slug' => 'whatsapp'],
            [
                'name' => 'WhatsApp',
                'description' => 'WhatsApp messaging via Infobip — chat, media, and customer conversations',
                'route' => 'whatsapp',
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

            $permission = Permission::firstOrCreate(
                [
                    'slug' => 'view_whatsapp',
                    'company_id' => $company->id,
                ],
                [
                    'name' => 'view_whatsapp',
                    'display_name' => 'WhatsApp',
                    'description' => 'Access to WhatsApp Business conversations',
                    'category' => 'main',
                ]
            );

            $roles = Role::where('company_id', $company->id)->get();
            foreach ($roles as $role) {
                $hasRelated = $role->permissions()
                    ->whereIn('slug', ['view_messaging', 'view_inbox', 'view_viber', 'view_integrations', 'view_admin_control', 'view_dashboard'])
                    ->exists();
                if ($hasRelated && ! $role->permissions()->where('permissions.id', $permission->id)->exists()) {
                    $role->permissions()->attach($permission->id);
                }
            }
        }
    }
}
