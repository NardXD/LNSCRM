<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $moduleId = DB::table('modules')->where('slug', 'broadcast-messaging')->value('id');
        if (! $moduleId) {
            $moduleId = DB::table('modules')->insertGetId([
                'slug' => 'broadcast-messaging',
                'name' => 'Broadcast Messaging',
                'description' => 'Send bulk SMS and email broadcasts to leads, clients, and contacts',
                'route' => 'broadcast-messaging',
                'sort_order' => 11,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $permissions = [
            [
                'slug' => 'view_broadcast_messaging',
                'name' => 'view_broadcast_messaging',
                'display_name' => 'View Broadcast Messaging',
                'description' => 'Access to broadcast messaging history and details',
            ],
            [
                'slug' => 'send_broadcast_sms',
                'name' => 'send_broadcast_sms',
                'display_name' => 'Send SMS Broadcasts',
                'description' => 'Create and send bulk SMS broadcasts via Twilio',
            ],
            [
                'slug' => 'send_broadcast_email',
                'name' => 'send_broadcast_email',
                'display_name' => 'Send Email Broadcasts',
                'description' => 'Create and send bulk email broadcasts via Microsoft 365',
            ],
        ];

        $relatedSlugs = [
            'view_sms',
            'send_sms',
            'view_inbox',
            'view_messaging',
            'view_leads',
            'view_client_management',
            'view_admin_control',
        ];

        $companyIds = DB::table('companies')->pluck('id');

        foreach ($companyIds as $companyId) {
            $alreadyAttached = DB::table('company_modules')
                ->where('company_id', $companyId)
                ->where('module_id', $moduleId)
                ->exists();

            if (! $alreadyAttached) {
                DB::table('company_modules')->insert([
                    'company_id' => $companyId,
                    'module_id' => $moduleId,
                    'is_enabled' => true,
                    'granted_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $permissionIds = [];
            foreach ($permissions as $perm) {
                $existing = DB::table('permissions')
                    ->where('company_id', $companyId)
                    ->where('slug', $perm['slug'])
                    ->first();

                if (! $existing) {
                    $permissionIds[$perm['slug']] = DB::table('permissions')->insertGetId([
                        'name' => $perm['name'],
                        'slug' => $perm['slug'],
                        'display_name' => $perm['display_name'],
                        'description' => $perm['description'],
                        'category' => 'main',
                        'company_id' => $companyId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } else {
                    $permissionIds[$perm['slug']] = (int) $existing->id;
                }
            }

            $roleIds = collect();
            $relatedPermissionIds = DB::table('permissions')
                ->where('company_id', $companyId)
                ->whereIn('slug', $relatedSlugs)
                ->pluck('id');

            if ($relatedPermissionIds->isNotEmpty()) {
                $roleIds = $roleIds->merge(
                    DB::table('role_permission')
                        ->whereIn('permission_id', $relatedPermissionIds)
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
                foreach ($permissionIds as $permissionId) {
                    DB::table('role_permission')->insertOrIgnore([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $slugs = ['view_broadcast_messaging', 'send_broadcast_sms', 'send_broadcast_email'];
        $permIds = DB::table('permissions')->whereIn('slug', $slugs)->pluck('id');

        if ($permIds->isNotEmpty()) {
            DB::table('role_permission')->whereIn('permission_id', $permIds)->delete();
            DB::table('permissions')->whereIn('id', $permIds)->delete();
        }

        $moduleId = DB::table('modules')->where('slug', 'broadcast-messaging')->value('id');
        if ($moduleId) {
            DB::table('company_modules')->where('module_id', $moduleId)->delete();
            DB::table('modules')->where('id', $moduleId)->delete();
        }
    }
};
