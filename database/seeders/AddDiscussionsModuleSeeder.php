<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AddDiscussionsModuleSeeder extends Seeder
{
    public function run(): void
    {
        $module = Module::updateOrCreate(
            ['slug' => 'discussions'],
            [
                'name' => 'Discussions',
                'description' => 'Internal Front-style teammate discussions with assignment, tags, and rules',
                'route' => 'discussions',
                'sort_order' => 10,
                'is_active' => true,
            ]
        );

        $permissionDefs = [
            [
                'slug' => 'view_discussions',
                'name' => 'view_discussions',
                'display_name' => 'Discussions',
                'description' => 'Access to internal teammate discussions',
            ],
            [
                'slug' => 'create_discussion_tags',
                'name' => 'create_discussion_tags',
                'display_name' => 'Add Discussion Tags',
                'description' => 'Create tags used on discussions',
            ],
            [
                'slug' => 'create_discussion_rules',
                'name' => 'create_discussion_rules',
                'display_name' => 'Add Discussion Rules',
                'description' => 'Create, edit, and delete discussion automation rules',
            ],
        ];

        foreach (Company::all() as $company) {
            if (! $company->modules()->where('modules.id', $module->id)->exists()) {
                $company->modules()->attach($module->id, [
                    'is_enabled' => true,
                    'granted_at' => now(),
                ]);
            }

            $permissionIds = [];
            foreach ($permissionDefs as $def) {
                $permission = Permission::firstOrCreate(
                    [
                        'slug' => $def['slug'],
                        'company_id' => $company->id,
                    ],
                    [
                        'name' => $def['name'],
                        'display_name' => $def['display_name'],
                        'description' => $def['description'],
                        'category' => 'main',
                    ]
                );
                $permissionIds[] = $permission->id;
            }

            $roles = Role::where('company_id', $company->id)->get();
            foreach ($roles as $role) {
                $hasRelated = $role->permissions()
                    ->whereIn('slug', ['view_messaging', 'view_inbox', 'view_integrations', 'view_admin_control', 'view_dashboard'])
                    ->exists();
                if (! $hasRelated) {
                    continue;
                }
                foreach ($permissionIds as $permissionId) {
                    if (! $role->permissions()->where('permissions.id', $permissionId)->exists()) {
                        $role->permissions()->attach($permissionId);
                    }
                }
            }
        }
    }
}
