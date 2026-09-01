<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('front_integrations', function (Blueprint $table) {
            $table->text('verify_error')->nullable()->after('api_token');
            $table->timestamp('verified_at')->nullable()->after('verify_error');
        });
    }

    public function down(): void
    {
        Schema::table('front_integrations', function (Blueprint $table) {
            $table->dropColumn(['verify_error', 'verified_at']);
        });
    }
};
