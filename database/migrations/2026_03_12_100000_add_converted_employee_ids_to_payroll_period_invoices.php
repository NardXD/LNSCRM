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
        Schema::table('payroll_period_invoices', function (Blueprint $table) {
            $table->json('converted_employee_ids')->nullable()->after('invoice_ids');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_period_invoices', function (Blueprint $table) {
            $table->dropColumn('converted_employee_ids');
        });
    }
};
