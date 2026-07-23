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
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'stripe_payment_link_id')) {
                $table->string('stripe_payment_link_id')->nullable();
            }
            if (! Schema::hasColumn('invoices', 'email_sent_at')) {
                $table->timestamp('email_sent_at')->nullable();
            }
            if (! Schema::hasColumn('invoices', 'stripe_link_generated_at')) {
                $table->timestamp('stripe_link_generated_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            foreach (['email_sent_at', 'stripe_link_generated_at'] as $column) {
                if (Schema::hasColumn('invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
