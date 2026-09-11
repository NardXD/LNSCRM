<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $companyIds = DB::table('companies')->pluck('id');

        $newPermissions = [
            [
                'slug' => 'view_contracts_microsoft_365_mail',
                'name' => 'view_contracts_microsoft_365_mail',
                'display_name' => 'Microsoft 365 Mail',
                'description' => 'Access to configure Microsoft 365 mail for contracts',
                'category' => 'contracts',
            ],
            [
                'slug' => 'view_contracts_email_template',
                'name' => 'view_contracts_email_template',
                'display_name' => 'Email Template',
                'description' => 'Access to configure the contracts email template',
                'category' => 'contracts',
            ],
        ];

        foreach ($companyIds as $companyId) {
            $contractsPermId = (int) DB::table('permissions')
                ->where('company_id', $companyId)
                ->where('slug', 'view_contracts')
                ->value('id');

            $roleIds = $contractsPermId
                ? DB::table('role_permission')->where('permission_id', $contractsPermId)->distinct()->pluck('role_id')
                : collect();

            $adminRoleId = DB::table('roles')
                ->where('company_id', $companyId)
                ->where('slug', 'admin')
                ->value('id');

            foreach ($newPermissions as $definition) {
                $permissionId = (int) DB::table('permissions')
                    ->where('company_id', $companyId)
                    ->where('slug', $definition['slug'])
                    ->value('id');

                if (! $permissionId) {
                    $permissionId = DB::table('permissions')->insertGetId([
                        'name' => $definition['name'],
                        'slug' => $definition['slug'],
                        'display_name' => $definition['display_name'],
                        'description' => $definition['description'],
                        'category' => $definition['category'],
                        'company_id' => $companyId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                foreach ($roleIds as $roleId) {
                    DB::table('role_permission')->insertOrIgnore([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                    ]);
                }

                if ($adminRoleId) {
                    DB::table('role_permission')->insertOrIgnore([
                        'role_id' => $adminRoleId,
                        'permission_id' => $permissionId,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $permIds = DB::table('permissions')
            ->whereIn('slug', ['view_contracts_microsoft_365_mail', 'view_contracts_email_template'])
            ->pluck('id');

        if ($permIds->isNotEmpty()) {
            DB::table('role_permission')->whereIn('permission_id', $permIds)->delete();
            DB::table('permissions')->whereIn('id', $permIds)->delete();
        }
    }
};
