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
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('quotation_number')->unique();
            $table->date('quotation_date');
            $table->date('valid_until');
            $table->enum('status', ['draft', 'sent', 'accepted', 'rejected', 'expired'])->default('draft');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->enum('discount_type', ['amount', 'percent'])->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->text('internal_notes')->nullable();
            $table->text('terms_conditions')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['client_id']);
            $table->index(['quotation_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
