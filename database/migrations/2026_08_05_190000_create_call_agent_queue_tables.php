<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_agent_presences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16)->default('offline'); // offline | available | busy
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->string('current_call_sid', 64)->nullable();
            $table->timestamps();

            $table->unique('user_id');
            $table->index(['company_id', 'status', 'last_heartbeat_at']);
            $table->index('current_call_sid');
        });

        Schema::create('call_round_robin_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('last_assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_round_robin_states');
        Schema::dropIfExists('call_agent_presences');
    }
};
