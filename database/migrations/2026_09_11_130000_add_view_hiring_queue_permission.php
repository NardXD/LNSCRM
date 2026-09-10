<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Independent "view hiring queue" permission for existing companies, decoupled from
     * view_client_management so an admin can grant/revoke hiring queue access on its own.
     * New companies pick this up automatically via CompanyPermissionFactory.
     */
    public function up(): void
    {
        $now = now();
        $companyIds = DB::table('companies')->pluck('id');

        foreach ($companyIds as $companyId) {
            $permissionId = (int) DB::table('permissions')
                ->where('company_id', $companyId)
                ->where('slug', 'view_hiring_queue')
                ->value('id');

            if (! $permissionId) {
                $permissionId = DB::table('permissions')->insertGetId([
                    'name' => 'view_hiring_queue',
                    'slug' => 'view_hiring_queue',
                    'display_name' => 'Hiring Queue',
                    'description' => 'Access to the hiring queue module',
                    'category' => 'main',
                    'company_id' => $companyId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // Keep existing access working by default: any role that already sees Client
            // Management also gets the new hiring queue permission.
            $clientPermId = (int) DB::table('permissions')
                ->where('company_id', $companyId)
                ->where('slug', 'view_client_management')
                ->value('id');

            if ($clientPermId) {
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
        }
    }

    public function down(): void
    {
        $permIds = DB::table('permissions')
            ->where('slug', 'view_hiring_queue')
            ->pluck('id');

        if ($permIds->isNotEmpty()) {
            DB::table('role_permission')->whereIn('permission_id', $permIds)->delete();
            DB::table('permissions')->whereIn('id', $permIds)->delete();
        }
    }
};
