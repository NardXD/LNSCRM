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
        if (Schema::hasTable('salary_computations')) {
            return; // Table already exists
        }
        
        Schema::create('salary_computations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->date('period_start_date');
            $table->date('period_end_date');
            $table->decimal('base_salary', 10, 2)->nullable();
            $table->decimal('hours_worked', 8, 2)->default(0);
            $table->decimal('required_hours', 8, 2)->default(160); // Default 160 hours/month
            $table->decimal('overtime_hours', 8, 2)->default(0);
            $table->decimal('allowances', 10, 2)->default(0);
            $table->decimal('deductions', 10, 2)->default(0);
            $table->text('deduction_details')->nullable(); // JSON or text for deduction breakdown
            $table->decimal('gross_pay', 10, 2)->default(0);
            $table->decimal('net_pay', 10, 2)->default(0);
            $table->enum('status', ['draft', 'finalized', 'paid'])->default('draft');
            $table->timestamps();
            
            // Indexes added separately to avoid issues with existing tables
            // $table->index(['user_id', 'period_start_date', 'period_end_date'], 'salary_comp_user_period_idx');
            // $table->index('company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_computations');
    }
};
