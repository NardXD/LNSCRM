<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbox_conversation_user_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inbox_conversation_id')->constrained('inbox_conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // When null, the user is considered unread.
            $table->timestamp('last_read_at')->nullable();
            $table->boolean('is_read')->default(false);

            $table->timestamps();

            $table->unique(['inbox_conversation_id', 'user_id'], 'icur_conv_user_unique');
            $table->index(['user_id', 'is_read'], 'icur_user_read_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_conversation_user_reads');
    }
};

