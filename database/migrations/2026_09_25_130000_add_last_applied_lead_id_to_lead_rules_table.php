<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_rules', function (Blueprint $table) {
            $table->foreignId('last_applied_lead_id')
                ->nullable()
                ->after('last_applied_at')
                ->constrained('leads')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lead_rules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_applied_lead_id');
        });
    }
};
