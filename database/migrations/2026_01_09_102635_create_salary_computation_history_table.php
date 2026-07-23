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
        Schema::create('salary_computation_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_computation_id')->constrained('salary_computations')->onDelete('cascade');
            $table->foreignId('edited_by')->constrained('users')->onDelete('cascade');
            $table->decimal('old_required_hours', 8, 2)->nullable();
            $table->decimal('new_required_hours', 8, 2)->nullable();
            $table->decimal('old_deductions', 10, 2)->nullable();
            $table->decimal('new_deductions', 10, 2)->nullable();
            $table->text('old_deduction_details')->nullable();
            $table->text('new_deduction_details')->nullable();
            $table->decimal('old_gross_pay', 10, 2)->nullable();
            $table->decimal('new_gross_pay', 10, 2)->nullable();
            $table->decimal('old_net_pay', 10, 2)->nullable();
            $table->decimal('new_net_pay', 10, 2)->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
            
            $table->index('salary_computation_id');
            $table->index('edited_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_computation_history');
    }
};
