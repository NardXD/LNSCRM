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
                ->where('slug', 'create_lead_rules')
                ->exists();

            if (! $exists) {
                DB::table('permissions')->insert([
                    'name' => 'create_lead_rules',
                    'slug' => 'create_lead_rules',
                    'display_name' => 'Add Lead Rules',
                    'description' => 'Create, edit, and delete lead automation rules',
                    'category' => 'main',
                    'company_id' => $companyId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $permissionId = (int) DB::table('permissions')
                ->where('company_id', $companyId)
                ->where('slug', 'create_lead_rules')
                ->value('id');

            if (! $permissionId) {
                continue;
            }

            $sourceIds = DB::table('permissions')
                ->where('company_id', $companyId)
                ->whereIn('slug', ['create_inbox_rules', 'view_leads'])
                ->pluck('id');

            $roleIds = collect();
            if ($sourceIds->isNotEmpty()) {
                $roleIds = $roleIds->merge(
                    DB::table('role_permission')
                        ->whereIn('permission_id', $sourceIds)
                        ->distinct()
                        ->pluck('role_id')
                );
            }

            $adminRoleId = DB::table('roles')
                ->where('company_id', $companyId)
                ->where('slug', 'admin')
                ->value('id');
            if ($adminRoleId) {
                $roleIds->push($adminRoleId);
            }

            foreach ($roleIds->unique()->filter() as $roleId) {
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
            ->where('slug', 'create_lead_rules')
            ->pluck('id');

        if ($permIds->isNotEmpty()) {
            DB::table('role_permission')->whereIn('permission_id', $permIds)->delete();
            DB::table('permissions')->whereIn('id', $permIds)->delete();
        }
    }
};
