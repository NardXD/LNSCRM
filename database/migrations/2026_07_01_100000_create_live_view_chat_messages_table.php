<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_view_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_view_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['live_view_session_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_view_chat_messages');
    }
};
