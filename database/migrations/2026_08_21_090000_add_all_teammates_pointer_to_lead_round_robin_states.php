<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_round_robin_states', function (Blueprint $table) {
            $table->foreignId('last_assigned_all_user_id')
                ->nullable()
                ->after('last_assigned_user_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lead_round_robin_states', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_assigned_all_user_id');
        });
    }
};
