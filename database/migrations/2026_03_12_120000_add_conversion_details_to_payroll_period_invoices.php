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
            $table->json('conversion_details')->nullable()->after('employee_invoice_mapping');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_period_invoices', function (Blueprint $table) {
            $table->dropColumn('conversion_details');
        });
    }
};
