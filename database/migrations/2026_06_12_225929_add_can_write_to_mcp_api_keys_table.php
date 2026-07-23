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
        Schema::table('mcp_api_keys', function (Blueprint $table) {
            $table->boolean('can_write')->default(false)->after('key_prefix');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mcp_api_keys', function (Blueprint $table) {
            $table->dropColumn('can_write');
        });
    }
};
