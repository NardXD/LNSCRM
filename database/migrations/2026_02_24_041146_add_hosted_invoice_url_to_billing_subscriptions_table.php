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
        Schema::table('billing_subscriptions', function (Blueprint $table) {
            $table->text('hosted_invoice_url')->nullable()->after('stripe_price_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('billing_subscriptions', function (Blueprint $table) {
            $table->dropColumn('hosted_invoice_url');
        });
    }
};
