<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AddKnowledgeBasePermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Adds create, edit, delete permissions for Knowledge Base (Role Based Access).
     */
    public function run(): void
    {
        $companies = Company::all();

        if ($companies->isEmpty()) {
            $this->command->warn('No companies found. Skipping Knowledge Base permissions.');

            return;
        }

        $permissions = [
            [
                'name' => 'create_knowledge_base',
                'slug' => 'create_knowledge_base',
                'display_name' => 'Create (Knowledge Base)',
                'description' => 'Create articles, FAQs, guides, and categories in Knowledge Base',
                'category' => 'main',
            ],
            [
                'name' => 'edit_knowledge_base',
                'slug' => 'edit_knowledge_base',
                'display_name' => 'Edit (Knowledge Base)',
                'description' => 'Edit articles, FAQs, guides in Knowledge Base',
                'category' => 'main',
            ],
            [
                'name' => 'delete_knowledge_base',
                'slug' => 'delete_knowledge_base',
                'display_name' => 'Delete (Knowledge Base)',
                'description' => 'Delete articles, FAQs, guides in Knowledge Base',
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

            $adminRole = Role::where('slug', 'admin')->where('company_id', $company->id)->first();
            if ($adminRole && ! empty($createdIds)) {
                $adminRole->permissions()->syncWithoutDetaching($createdIds);
            }
        }

        $this->command->info('Knowledge Base permissions (create, edit, delete) seeded.');
    }
}
