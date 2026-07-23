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
        Schema::create('billing_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->string('stripe_subscription_id')->nullable()->index();
            $table->string('stripe_customer_id')->nullable()->index();
            $table->string('stripe_price_id')->nullable();
            $table->string('product_name');
            $table->unsignedInteger('unit_amount'); // cents
            $table->string('currency', 3)->default('usd');
            $table->enum('interval', ['day', 'week', 'month', 'year'])->default('month');
            $table->unsignedInteger('interval_count')->default(1);
            $table->enum('status', [
                'incomplete',
                'incomplete_expired',
                'trialing',
                'active',
                'past_due',
                'canceled',
                'unpaid',
                'paused'
            ])->default('active');
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('trial_end')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_subscriptions');
    }
};
