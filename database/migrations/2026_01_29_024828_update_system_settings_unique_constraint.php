<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            // Drop the old unique constraint on 'key' only
            $table->dropUnique(['key']);
            
            // Add composite unique constraint on (key, group)
            // This allows the same key to exist for different groups (companies)
            $table->unique(['key', 'group'], 'system_settings_key_group_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            // Drop the composite unique constraint
            $table->dropUnique('system_settings_key_group_unique');
            
            // Restore the old unique constraint on 'key' only
            $table->unique('key');
        });
    }
};
