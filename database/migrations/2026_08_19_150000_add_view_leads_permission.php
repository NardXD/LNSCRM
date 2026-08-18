<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $companyIds = DB::table('companies')->pluck('id');

        foreach ($companyIds as $companyId) {
            $exists = DB::table('permissions')
                ->where('company_id', $companyId)
                ->where('slug', 'view_leads')
                ->exists();

            if (! $exists) {
                DB::table('permissions')->insert([
                    'name' => 'view_leads',
                    'slug' => 'view_leads',
                    'display_name' => 'Leads',
                    'description' => 'Access to the leads module',
                    'category' => 'main',
                    'company_id' => $companyId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $permissionId = (int) DB::table('permissions')
                ->where('company_id', $companyId)
                ->where('slug', 'view_leads')
                ->value('id');

            if (! $permissionId) {
                continue;
            }

            $adminRoleId = DB::table('roles')
                ->where('company_id', $companyId)
                ->where('slug', 'admin')
                ->value('id');

            if ($adminRoleId) {
                DB::table('role_permission')->insertOrIgnore([
                    'role_id' => $adminRoleId,
                    'permission_id' => $permissionId,
                ]);
            }

            $clientPermId = (int) DB::table('permissions')
                ->where('company_id', $companyId)
                ->where('slug', 'view_client_management')
                ->value('id');

            if (! $clientPermId) {
                continue;
            }

            $roleIds = DB::table('role_permission')
                ->where('permission_id', $clientPermId)
                ->distinct()
                ->pluck('role_id');

            foreach ($roleIds as $roleId) {
                DB::table('role_permission')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $permIds = DB::table('permissions')
            ->where('slug', 'view_leads')
            ->pluck('id');

        if ($permIds->isNotEmpty()) {
            DB::table('role_permission')->whereIn('permission_id', $permIds)->delete();
            DB::table('permissions')->whereIn('id', $permIds)->delete();
        }
    }
};
