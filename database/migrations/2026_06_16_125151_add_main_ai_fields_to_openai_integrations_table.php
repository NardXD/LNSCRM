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
        Schema::table('openai_integrations', function (Blueprint $table) {
            $table->boolean('uses_main_ai')->default(false)->after('is_active');
            $table->unsignedBigInteger('token_limit')->nullable()->after('uses_main_ai');
            $table->unsignedBigInteger('tokens_used')->default(0)->after('token_limit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('openai_integrations', function (Blueprint $table) {
            $table->dropColumn(['uses_main_ai', 'token_limit', 'tokens_used']);
        });
    }
};
