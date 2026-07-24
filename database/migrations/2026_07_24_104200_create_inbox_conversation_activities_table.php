<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbox_conversation_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inbox_conversation_id')->constrained('inbox_conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50);
            $table->string('summary', 500);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['inbox_conversation_id', 'created_at'], 'inbox_activities_conv_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_conversation_activities');
    }
};
