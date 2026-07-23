<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('sales_rep_id')
                ->nullable()
                ->after('required_work_hours')
                ->constrained('sales_reps')
                ->nullOnDelete();
            $table->string('sales_rep_commission_type', 20)->nullable()->after('sales_rep_id');
            $table->decimal('sales_rep_commission_value', 12, 2)->nullable()->after('sales_rep_commission_type');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['sales_rep_id']);
            $table->dropColumn(['sales_rep_id', 'sales_rep_commission_type', 'sales_rep_commission_value']);
        });
    }
};
