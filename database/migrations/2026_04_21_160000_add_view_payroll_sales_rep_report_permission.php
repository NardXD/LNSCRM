<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add granular permission for Payroll Report by Sales Rep (/payroll/sales-reps).
     * Grant to roles that already had payroll report access.
     */
    public function up(): void
    {
        $now = now();

        $companyIds = DB::table('companies')->pluck('id');

        foreach ($companyIds as $companyId) {
            $exists = DB::table('permissions')
                ->where('company_id', $companyId)
                ->where('slug', 'view_payroll_sales_rep_report')
                ->exists();

            if (! $exists) {
                DB::table('permissions')->insert([
                    'name' => 'view_payroll_sales_rep_report',
                    'slug' => 'view_payroll_sales_rep_report',
                    'display_name' => 'Payroll Report (Sales Rep)',
                    'description' => 'Access to Payroll Report by Sales Rep page and related APIs',
                    'category' => 'payroll',
                    'company_id' => $companyId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $newPermId = (int) DB::table('permissions')
                ->where('company_id', $companyId)
                ->where('slug', 'view_payroll_sales_rep_report')
                ->value('id');

            if (! $newPermId) {
                continue;
            }

            $sourcePermIds = DB::table('permissions')
                ->where('company_id', $companyId)
                ->whereIn('slug', ['view_payroll_report', 'generate_payroll_report'])
                ->pluck('id');

            $roleIds = DB::table('role_permission')
                ->whereIn('permission_id', $sourcePermIds)
                ->distinct()
                ->pluck('role_id');

            foreach ($roleIds as $roleId) {
                DB::table('role_permission')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $newPermId,
                ]);
            }

            $adminRoleId = DB::table('roles')
                ->where('company_id', $companyId)
                ->where('slug', 'admin')
                ->value('id');

            if ($adminRoleId) {
                DB::table('role_permission')->insertOrIgnore([
                    'role_id' => $adminRoleId,
                    'permission_id' => $newPermId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $permIds = DB::table('permissions')
            ->where('slug', 'view_payroll_sales_rep_report')
            ->pluck('id');

        if ($permIds->isNotEmpty()) {
            DB::table('role_permission')->whereIn('permission_id', $permIds)->delete();
            DB::table('permissions')->whereIn('id', $permIds)->delete();
        }
    }
};
