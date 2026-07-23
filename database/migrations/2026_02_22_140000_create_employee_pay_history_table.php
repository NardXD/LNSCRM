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
        Schema::create('employee_pay_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('payroll_report_id')->constrained()->onDelete('cascade');
            $table->foreignId('payroll_report_item_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('wise_transfer_id')->nullable();
            $table->date('period_start_date')->nullable();
            $table->date('period_end_date')->nullable();
            $table->timestamp('paid_at');
            $table->timestamps();

            $table->unique('payroll_report_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_pay_history');
    }
};
