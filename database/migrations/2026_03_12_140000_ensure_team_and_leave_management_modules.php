<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Ensures team-management and leave-management modules exist and are active
     * so they appear in Create Company and Manage Permission modals.
     */
    public function up(): void
    {
        $modules = [
            [
                'slug' => 'team-management',
                'name' => 'Team Management',
                'description' => 'Manage teams and members',
                'route' => 'team-management',
                'is_active' => true,
                'sort_order' => 8,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'leave-management',
                'name' => 'Leave Management',
                'description' => 'Leave requests and credits',
                'route' => 'leave-management',
                'is_active' => true,
                'sort_order' => 9,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($modules as $module) {
            $exists = DB::table('modules')->where('slug', $module['slug'])->exists();

            if ($exists) {
                DB::table('modules')
                    ->where('slug', $module['slug'])
                    ->update([
                        'is_active' => true,
                        'name' => $module['name'],
                        'description' => $module['description'],
                        'route' => $module['route'],
                        'sort_order' => $module['sort_order'],
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('modules')->insert($module);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Do not remove - modules may be in use by companies
    }
};
