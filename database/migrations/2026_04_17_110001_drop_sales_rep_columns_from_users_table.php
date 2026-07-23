<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'is_sales_rep')) {
                $table->dropColumn(['is_sales_rep', 'commission_type', 'commission_value']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'is_sales_rep')) {
                $table->boolean('is_sales_rep')->default(false)->after('required_work_hours');
                $table->string('commission_type', 20)->nullable()->after('is_sales_rep');
                $table->decimal('commission_value', 12, 2)->nullable()->after('commission_type');
            }
        });
    }
};
