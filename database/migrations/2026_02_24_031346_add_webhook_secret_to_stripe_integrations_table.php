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
        Schema::table('stripe_integrations', function (Blueprint $table) {
            $table->text('webhook_secret')->nullable()->after('publishable_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stripe_integrations', function (Blueprint $table) {
            $table->dropColumn('webhook_secret');
        });
    }
};
