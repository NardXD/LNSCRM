<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_view_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 32)->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('admin_ip', 45)->nullable();
            $table->text('admin_user_agent')->nullable();
            $table->string('worker_ip', 45)->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'worker_id', 'status']);
            $table->index(['admin_id', 'status']);
        });

        Schema::create('webrtc_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('live_view_session_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('signal_type', 64);
            $table->json('payload');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['to_user_id', 'consumed_at', 'expires_at']);
            $table->index(['live_view_session_id', 'signal_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webrtc_signals');
        Schema::dropIfExists('live_view_sessions');
    }
};
