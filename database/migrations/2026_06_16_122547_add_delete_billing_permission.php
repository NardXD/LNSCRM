<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add granular permission for deleting invoices in the billing module.
     * Grant it to admin roles by default; other roles can be assigned it via User Management.
     */
    public function up(): void
    {
        $now = now();

        $companyIds = DB::table('companies')->pluck('id');

        foreach ($companyIds as $companyId) {
            $exists = DB::table('permissions')
                ->where('company_id', $companyId)
                ->where('slug', 'delete_billing')
                ->exists();

            if (! $exists) {
                DB::table('permissions')->insert([
                    'name' => 'delete_billing',
                    'slug' => 'delete_billing',
                    'display_name' => 'Delete Invoices',
                    'description' => 'Delete invoices in the billing and payments module',
                    'category' => 'main',
                    'company_id' => $companyId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $newPermId = (int) DB::table('permissions')
                ->where('company_id', $companyId)
                ->where('slug', 'delete_billing')
                ->value('id');

            if (! $newPermId) {
                continue;
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
            ->where('slug', 'delete_billing')
            ->pluck('id');

        if ($permIds->isNotEmpty()) {
            DB::table('role_permission')->whereIn('permission_id', $permIds)->delete();
            DB::table('permissions')->whereIn('id', $permIds)->delete();
        }
    }
};
