<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_rules', function (Blueprint $table) {
            $table->timestamp('last_applied_at')->nullable()->after('created_by');
        });
    }

    public function down(): void
    {
        Schema::table('lead_rules', function (Blueprint $table) {
            $table->dropColumn('last_applied_at');
        });
    }
};
