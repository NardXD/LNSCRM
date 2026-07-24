<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbox_conversation_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inbox_conversation_id')->constrained('inbox_conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->longText('body_html');
            $table->longText('body_text')->nullable();
            $table->json('mentioned_user_ids')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamps();

            $table->index(['inbox_conversation_id', 'created_at'], 'inbox_comments_conv_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_conversation_comments');
    }
};
