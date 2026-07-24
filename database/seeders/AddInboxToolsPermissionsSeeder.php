<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AddInboxToolsPermissionsSeeder extends Seeder
{
    /**
     * Adds permissions for creating inbox tags, templates, and rules.
     */
    public function run(): void
    {
        $companies = Company::all();

        if ($companies->isEmpty()) {
            $this->command?->warn('No companies found. Skipping Inbox tools permissions.');

            return;
        }

        $permissions = [
            [
                'name' => 'create_inbox_tags',
                'slug' => 'create_inbox_tags',
                'display_name' => 'Add Tags',
                'description' => 'Create and delete inbox tags',
                'category' => 'main',
            ],
            [
                'name' => 'create_inbox_templates',
                'slug' => 'create_inbox_templates',
                'display_name' => 'Add Templates',
                'description' => 'Create, edit, and delete shared inbox templates',
                'category' => 'main',
            ],
            [
                'name' => 'create_inbox_rules',
                'slug' => 'create_inbox_rules',
                'display_name' => 'Add Rules',
                'description' => 'Create, edit, and delete inbox automation rules',
                'category' => 'main',
            ],
        ];

        foreach ($companies as $company) {
            $createdIds = [];
            foreach ($permissions as $p) {
                $permission = Permission::firstOrCreate(
                    [
                        'slug' => $p['slug'],
                        'company_id' => $company->id,
                    ],
                    [
                        'name' => $p['name'],
                        'display_name' => $p['display_name'],
                        'description' => $p['description'],
                        'category' => $p['category'],
                    ]
                );
                $createdIds[] = $permission->id;
            }

            // Grant to roles that already have Inbox access (incl. admin-like roles).
            $roles = Role::where('company_id', $company->id)->get();
            foreach ($roles as $role) {
                $hasInbox = $role->permissions()
                    ->where('slug', 'view_inbox')
                    ->exists();
                if ($hasInbox && ! empty($createdIds)) {
                    $role->permissions()->syncWithoutDetaching($createdIds);
                }
            }
        }

        $this->command?->info('Inbox tools permissions (tags, templates, rules) seeded.');
    }
}
