<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Independent "view reports" permissions for existing companies, decoupled from
     * view_leads/view_facebook so an admin can grant/revoke report access on its own.
     * New companies pick these up automatically via CompanyPermissionFactory.
     */
    public function up(): void
    {
        $now = now();
        $companyIds = DB::table('companies')->pluck('id');

        $reportPermissions = [
            'view_lead_reports' => [
                'display_name' => 'Lead Reports',
                'description' => 'Access to the lead reports page and its charts/exports',
                'grant_with' => 'view_leads',
            ],
            'view_facebook_reports' => [
                'display_name' => 'Facebook Reports',
                'description' => 'Access to the Facebook/Instagram reports page and its charts',
                'grant_with' => 'view_facebook',
            ],
        ];

        foreach ($companyIds as $companyId) {
            foreach ($reportPermissions as $slug => $meta) {
                $permissionId = (int) DB::table('permissions')
                    ->where('company_id', $companyId)
                    ->where('slug', $slug)
                    ->value('id');

                if (! $permissionId) {
                    $permissionId = DB::table('permissions')->insertGetId([
                        'name' => $slug,
                        'slug' => $slug,
                        'display_name' => $meta['display_name'],
                        'description' => $meta['description'],
                        'category' => 'main',
                        'company_id' => $companyId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                // Keep existing access working by default: any role that already sees the
                // base module (Leads / Facebook) also gets the new reports permission.
                $basePermissionId = (int) DB::table('permissions')
                    ->where('company_id', $companyId)
                    ->where('slug', $meta['grant_with'])
                    ->value('id');

                if ($basePermissionId) {
                    $roleIds = DB::table('role_permission')
                        ->where('permission_id', $basePermissionId)
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
    }

    public function down(): void
    {
        $permIds = DB::table('permissions')
            ->whereIn('slug', ['view_lead_reports', 'view_facebook_reports'])
            ->pluck('id');

        if ($permIds->isNotEmpty()) {
            DB::table('role_permission')->whereIn('permission_id', $permIds)->delete();
            DB::table('permissions')->whereIn('id', $permIds)->delete();
        }
    }
};
