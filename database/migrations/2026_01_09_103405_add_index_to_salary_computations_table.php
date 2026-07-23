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
        Schema::table('salary_computations', function (Blueprint $table) {
            try {
                $table->index(['user_id', 'period_start_date', 'period_end_date'], 'salary_comp_user_period_idx');
            } catch (\Exception $e) {
                // Index might already exist, ignore
            }
            
            try {
                $table->index('company_id');
            } catch (\Exception $e) {
                // Index might already exist, ignore
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('salary_computations', function (Blueprint $table) {
            $table->dropIndex('salary_comp_user_period_idx');
            $table->dropIndex('salary_computations_company_id_index');
        });
    }
};
