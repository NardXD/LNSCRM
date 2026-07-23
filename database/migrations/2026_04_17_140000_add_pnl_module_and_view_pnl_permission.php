<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add P&L as a company module and view_pnl permission; mirror access from payroll where appropriate.
     */
    public function up(): void
    {
        $now = now();

        DB::table('modules')->updateOrInsert(
            ['slug' => 'pnl'],
            [
                'name' => 'P&L',
                'description' => 'Profit and loss from payroll conversion and billing invoices',
                'route' => 'pnl',
                'is_active' => true,
                'sort_order' => 22,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $pnlModuleId = (int) DB::table('modules')->where('slug', 'pnl')->value('id');
        $payrollModuleId = (int) DB::table('modules')->where('slug', 'payroll')->value('id');

        if ($pnlModuleId && $payrollModuleId) {
            $companyIds = DB::table('company_modules')
                ->where('module_id', $payrollModuleId)
                ->where('is_enabled', true)
                ->pluck('company_id');

            foreach ($companyIds as $companyId) {
                DB::table('company_modules')->updateOrInsert(
                    ['company_id' => $companyId, 'module_id' => $pnlModuleId],
                    ['is_enabled' => true, 'granted_at' => $now]
                );
            }
        }

        $companyIds = DB::table('companies')->pluck('id');

        foreach ($companyIds as $companyId) {
            $exists = DB::table('permissions')
                ->where('company_id', $companyId)
                ->where('slug', 'view_pnl')
                ->exists();

            if (! $exists) {
                DB::table('permissions')->insert([
                    'name' => 'view_pnl',
                    'slug' => 'view_pnl',
                    'display_name' => 'View P&L',
                    'description' => 'Access to the P&L dashboard and invoice-basis report',
                    'category' => 'payroll',
                    'company_id' => $companyId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $viewPnlId = (int) DB::table('permissions')
                ->where('company_id', $companyId)
                ->where('slug', 'view_pnl')
                ->value('id');

            if (! $viewPnlId) {
                continue;
            }

            $reportPermIds = DB::table('permissions')
                ->where('company_id', $companyId)
                ->whereIn('slug', ['view_payroll_report', 'generate_payroll_report'])
                ->pluck('id');

            $roleIds = DB::table('role_permission')
                ->whereIn('permission_id', $reportPermIds)
                ->distinct()
                ->pluck('role_id');

            foreach ($roleIds as $roleId) {
                DB::table('role_permission')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $viewPnlId,
                ]);
            }

            $adminRoleId = DB::table('roles')
                ->where('company_id', $companyId)
                ->where('slug', 'admin')
                ->value('id');

            if ($adminRoleId) {
                DB::table('role_permission')->insertOrIgnore([
                    'role_id' => $adminRoleId,
                    'permission_id' => $viewPnlId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $pnlModuleId = DB::table('modules')->where('slug', 'pnl')->value('id');

        if ($pnlModuleId) {
            DB::table('company_modules')->where('module_id', $pnlModuleId)->delete();
        }

        DB::table('permissions')->where('slug', 'view_pnl')->delete();
        DB::table('modules')->where('slug', 'pnl')->delete();
    }
};
