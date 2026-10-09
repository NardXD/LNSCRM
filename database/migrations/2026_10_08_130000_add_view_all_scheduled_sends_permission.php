<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * "View All Scheduled Sends" permission for existing companies. Only the admin role
     * gets it by default; administrators assign it to other roles as needed.
     * New companies pick it up automatically via CompanyPermissionFactory.
     */
    public function up(): void
    {
        $now = now();
        $slug = 'view_all_scheduled_sends';

        foreach (DB::table('companies')->pluck('id') as $companyId) {
            $permissionId = (int) DB::table('permissions')
                ->where('company_id', $companyId)
                ->where('slug', $slug)
                ->value('id');

            if (! $permissionId) {
                $permissionId = DB::table('permissions')->insertGetId([
                    'name' => $slug,
                    'slug' => $slug,
                    'display_name' => 'View All Scheduled Sends',
                    'description' => 'See Send later emails scheduled by all users on the Scheduled Sends page, not just your own',
                    'category' => 'main',
                    'company_id' => $companyId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $adminRoleId = DB::table('roles')
                ->where('company_id', $companyId)
                ->where('slug', 'admin')
                ->value('id');

            if ($adminRoleId) {
                DB::table('role_permission')->insertOrIgnore([
                    'role_id' => $adminRoleId,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $permIds = DB::table('permissions')->where('slug', 'view_all_scheduled_sends')->pluck('id');

        if ($permIds->isNotEmpty()) {
            DB::table('role_permission')->whereIn('permission_id', $permIds)->delete();
            DB::table('permissions')->whereIn('id', $permIds)->delete();
        }
    }
};
