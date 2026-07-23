<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_reps', function (Blueprint $table) {
            if (Schema::hasColumn('sales_reps', 'commission_type')) {
                $table->dropColumn(['commission_type', 'commission_value']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_reps', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_reps', 'commission_type')) {
                $table->string('commission_type', 20)->after('phone');
                $table->decimal('commission_value', 12, 2)->after('commission_type');
            }
        });
    }
};
