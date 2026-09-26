<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbox_conversation_followers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inbox_conversation_id')->constrained('inbox_conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_subscribed')->default(true);
            $table->timestamps();

            $table->unique(['inbox_conversation_id', 'user_id'], 'icf_conv_user_unique');
            $table->index(['user_id', 'is_subscribed'], 'icf_user_subscribed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_conversation_followers');
    }
};
