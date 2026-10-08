<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Independent "Phone Reports" permission for existing companies. Roles that can already
     * see call history (and the admin role) get it so current access keeps working.
     * New companies pick it up automatically via CompanyPermissionFactory.
     */
    public function up(): void
    {
        $now = now();
        $slug = 'view_phone_reports';

        foreach (DB::table('companies')->pluck('id') as $companyId) {
            $permissionId = (int) DB::table('permissions')
                ->where('company_id', $companyId)
                ->where('slug', $slug)
                ->value('id');

            if (! $permissionId) {
                $permissionId = DB::table('permissions')->insertGetId([
                    'name' => $slug,
                    'slug' => $slug,
                    'display_name' => 'Phone Reports',
                    'description' => 'Access to the phone system reports page: call durations, per-user totals and recordings',
                    'category' => 'main',
                    'company_id' => $companyId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $basePermissionId = (int) DB::table('permissions')
                ->where('company_id', $companyId)
                ->where('slug', 'view_call_history')
                ->value('id');

            $roleIds = $basePermissionId
                ? DB::table('role_permission')->where('permission_id', $basePermissionId)->distinct()->pluck('role_id')
                : collect();

            $adminRoleId = DB::table('roles')
                ->where('company_id', $companyId)
                ->where('slug', 'admin')
                ->value('id');

            if ($adminRoleId) {
                $roleIds->push($adminRoleId);
            }

            foreach ($roleIds->unique() as $roleId) {
                DB::table('role_permission')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $permIds = DB::table('permissions')->where('slug', 'view_phone_reports')->pluck('id');

        if ($permIds->isNotEmpty()) {
            DB::table('role_permission')->whereIn('permission_id', $permIds)->delete();
            DB::table('permissions')->whereIn('id', $permIds)->delete();
        }
    }
};
