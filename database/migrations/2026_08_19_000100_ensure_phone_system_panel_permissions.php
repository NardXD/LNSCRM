<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Call History / Contacts / Numbers tabs on /twilio/call are gated by these
     * permissions. They were originally added only via seeder (often skipped on
     * live) and categorized as "phone", which hid them from User Management.
     */
    public function up(): void
    {
        $now = now();

        $permissions = [
            [
                'slug' => 'view_call_history',
                'name' => 'view_call_history',
                'display_name' => 'View Call History',
                'description' => 'View persisted phone call history',
            ],
            [
                'slug' => 'manage_phone_contacts',
                'name' => 'manage_phone_contacts',
                'display_name' => 'Manage Phone Contacts',
                'description' => 'Create and manage phone system contacts',
            ],
            [
                'slug' => 'manage_twilio_numbers',
                'name' => 'manage_twilio_numbers',
                'display_name' => 'Manage Twilio Numbers',
                'description' => 'Purchase and assign Twilio phone numbers',
            ],
        ];

        $agentSlugs = ['view_call_history', 'manage_phone_contacts'];
        $adminSlugs = ['view_call_history', 'manage_phone_contacts', 'manage_twilio_numbers'];

        $companyIds = DB::table('companies')->pluck('id');

        foreach ($companyIds as $companyId) {
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
                    DB::table('permissions')
                        ->where('id', $existing->id)
                        ->update([
                            'category' => 'main',
                            'display_name' => $perm['display_name'],
                            'description' => $perm['description'],
                            'updated_at' => $now,
                        ]);
                    $permissionIds[$perm['slug']] = (int) $existing->id;
                }
            }

            $this->attachSlugsToRoles(
                $this->roleIdsWithPermission($companyId, 'view_phone_system'),
                $permissionIds,
                $agentSlugs,
                $now
            );

            $adminRoleIds = DB::table('roles')
                ->where('company_id', $companyId)
                ->where(function ($query) {
                    $query->where('slug', 'admin')
                        ->orWhere('slug', 'like', '%admin%')
                        ->orWhere('name', 'like', '%admin%')
                        ->orWhere('name', 'like', '%owner%');
                })
                ->pluck('id');

            $this->attachSlugsToRoles($adminRoleIds, $permissionIds, $adminSlugs, $now);

            $numberManagerRoleIds = $this->roleIdsWithPermission($companyId, 'view_user_management')
                ->merge($this->roleIdsWithPermission($companyId, 'view_integrations'))
                ->unique()
                ->values();

            $this->attachSlugsToRoles($numberManagerRoleIds, $permissionIds, ['manage_twilio_numbers'], $now);
        }
    }

    public function down(): void
    {
        // Keep the permissions; they are required for the phone system UI.
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int>|array<int, int>  $roleIds
     * @param  array<string, int>  $permissionIds
     * @param  array<int, string>  $slugs
     */
    private function attachSlugsToRoles($roleIds, array $permissionIds, array $slugs, $now): void
    {
        foreach ($roleIds as $roleId) {
            foreach ($slugs as $slug) {
                $permissionId = $permissionIds[$slug] ?? null;
                if (! $permissionId) {
                    continue;
                }

                DB::table('role_permission')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function roleIdsWithPermission(int $companyId, string $slug)
    {
        $permissionId = (int) DB::table('permissions')
            ->where('company_id', $companyId)
            ->where('slug', $slug)
            ->value('id');

        if (! $permissionId) {
            return collect();
        }

        return DB::table('role_permission')
            ->where('permission_id', $permissionId)
            ->distinct()
            ->pluck('role_id');
    }
};
