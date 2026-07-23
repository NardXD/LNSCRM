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
        Schema::create('payroll_report_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_report_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('employee_name');
            $table->string('wise_account')->nullable();
            $table->decimal('net_pay', 12, 2)->default(0);
            $table->decimal('gross_pay', 12, 2)->default(0);
            $table->decimal('base_salary', 12, 2)->default(0);
            $table->decimal('hours_worked', 8, 2)->default(0);
            $table->decimal('required_hours', 8, 2)->default(0);
            $table->decimal('overtime_hours', 8, 2)->default(0);
            $table->decimal('allowances', 12, 2)->default(0);
            $table->decimal('deductions', 12, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->enum('wise_status', ['pending', 'sent', 'failed'])->default('pending');
            $table->string('wise_transfer_id')->nullable();
            $table->text('wise_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_report_items');
    }
};
