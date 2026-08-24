<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AddMessageTemplatesPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $companies = Company::all();

        if ($companies->isEmpty()) {
            $this->command?->warn('No companies found. Skipping message templates permission.');

            return;
        }

        $definition = [
            'name' => 'create_message_templates',
            'slug' => 'create_message_templates',
            'display_name' => 'Add Message Templates',
            'description' => 'Create, edit, and delete SMS and Facebook reply templates',
            'category' => 'main',
        ];

        foreach ($companies as $company) {
            $permission = Permission::firstOrCreate(
                [
                    'slug' => $definition['slug'],
                    'company_id' => $company->id,
                ],
                [
                    'name' => $definition['name'],
                    'display_name' => $definition['display_name'],
                    'description' => $definition['description'],
                    'category' => $definition['category'],
                ]
            );

            $roles = Role::where('company_id', $company->id)->get();
            foreach ($roles as $role) {
                $hasChannelAccess = $role->permissions()
                    ->whereIn('slug', ['view_sms', 'view_facebook'])
                    ->exists();
                if ($hasChannelAccess) {
                    $role->permissions()->syncWithoutDetaching([$permission->id]);
                }
            }
        }

        $this->command?->info('Message templates permission seeded.');
    }
}
