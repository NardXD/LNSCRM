<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('modules')->updateOrInsert(
            ['slug' => 'contracts'],
            [
                'name' => 'Contracts & E-Sign',
                'description' => 'Create contracts and collect electronic signatures from clients',
                'route' => 'contracts',
                'is_active' => true,
                'sort_order' => 23,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $contractsModuleId = (int) DB::table('modules')->where('slug', 'contracts')->value('id');
        $quotationModuleId = (int) DB::table('modules')->where('slug', 'quotation-builder')->value('id');

        if ($contractsModuleId && $quotationModuleId) {
            $companyIds = DB::table('company_modules')
                ->where('module_id', $quotationModuleId)
                ->where('is_enabled', true)
                ->pluck('company_id');

            foreach ($companyIds as $companyId) {
                DB::table('company_modules')->updateOrInsert(
                    ['company_id' => $companyId, 'module_id' => $contractsModuleId],
                    ['is_enabled' => true, 'granted_at' => $now]
                );
            }
        }

        $permissions = [
            [
                'slug' => 'view_contracts',
                'name' => 'view_contracts',
                'display_name' => 'View Contracts',
                'description' => 'Access to contracts and e-signing module',
                'category' => 'main',
            ],
            [
                'slug' => 'create_contracts',
                'name' => 'create_contracts',
                'display_name' => 'Create Contracts',
                'description' => 'Create and edit contracts',
                'category' => 'main',
            ],
            [
                'slug' => 'send_contracts',
                'name' => 'send_contracts',
                'display_name' => 'Send Contracts',
                'description' => 'Send contracts for electronic signature',
                'category' => 'main',
            ],
            [
                'slug' => 'delete_contracts',
                'name' => 'delete_contracts',
                'display_name' => 'Delete Contracts',
                'description' => 'Delete draft or cancelled contracts',
                'category' => 'main',
            ],
        ];

        $companyIds = DB::table('companies')->pluck('id');

        foreach ($companyIds as $companyId) {
            $permissionIds = [];

            foreach ($permissions as $perm) {
                $exists = DB::table('permissions')
                    ->where('company_id', $companyId)
                    ->where('slug', $perm['slug'])
                    ->exists();

                if (! $exists) {
                    DB::table('permissions')->insert([
                        'name' => $perm['name'],
                        'slug' => $perm['slug'],
                        'display_name' => $perm['display_name'],
                        'description' => $perm['description'],
                        'category' => $perm['category'],
                        'company_id' => $companyId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $permissionIds[$perm['slug']] = (int) DB::table('permissions')
                    ->where('company_id', $companyId)
                    ->where('slug', $perm['slug'])
                    ->value('id');
            }

            $adminRoleId = DB::table('roles')
                ->where('company_id', $companyId)
                ->where('slug', 'admin')
                ->value('id');

            if ($adminRoleId) {
                foreach ($permissionIds as $permissionId) {
                    if ($permissionId) {
                        DB::table('role_permission')->insertOrIgnore([
                            'role_id' => $adminRoleId,
                            'permission_id' => $permissionId,
                        ]);
                    }
                }
            }

            $quotationPermId = (int) DB::table('permissions')
                ->where('company_id', $companyId)
                ->where('slug', 'view_quotation_builder')
                ->value('id');

            if ($quotationPermId) {
                $roleIds = DB::table('role_permission')
                    ->where('permission_id', $quotationPermId)
                    ->distinct()
                    ->pluck('role_id');

                foreach ($roleIds as $roleId) {
                    foreach (['view_contracts', 'create_contracts', 'send_contracts'] as $slug) {
                        $permId = $permissionIds[$slug] ?? null;
                        if ($permId) {
                            DB::table('role_permission')->insertOrIgnore([
                                'role_id' => $roleId,
                                'permission_id' => $permId,
                            ]);
                        }
                    }
                }
            }
        }
    }

    public function down(): void
    {
        $moduleId = DB::table('modules')->where('slug', 'contracts')->value('id');

        if ($moduleId) {
            DB::table('company_modules')->where('module_id', $moduleId)->delete();
        }

        DB::table('permissions')->whereIn('slug', [
            'view_contracts',
            'create_contracts',
            'send_contracts',
            'delete_contracts',
        ])->delete();

        DB::table('modules')->where('slug', 'contracts')->delete();
    }
};
