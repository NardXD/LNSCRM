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
            $table->json('employee_invoice_mapping')->nullable()->after('converted_employee_ids');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_period_invoices', function (Blueprint $table) {
            $table->dropColumn('employee_invoice_mapping');
        });
    }
};
