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
        Schema::table('pnl_manual_expenses', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->index(['company_id', 'client_id', 'expense_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pnl_manual_expenses', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropIndex(['company_id', 'client_id', 'expense_date']);
            $table->dropColumn('client_id');
        });
    }
};
