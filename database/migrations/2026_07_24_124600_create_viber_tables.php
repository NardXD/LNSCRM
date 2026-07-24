<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('viber_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->text('auth_token');
            $table->string('webhook_key', 64)->unique();
            $table->string('bot_name')->nullable();
            $table->string('bot_uri')->nullable();
            $table->string('bot_avatar', 1024)->nullable();
            $table->text('welcome_message')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('webhook_set_at')->nullable();
            $table->timestamps();

            $table->unique('company_id');
        });

        Schema::create('viber_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('viber_user_id', 64);
            $table->string('name')->nullable();
            $table->string('avatar', 1024)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('language', 16)->nullable();
            $table->string('country', 8)->nullable();
            $table->boolean('is_subscribed')->default(true);
            $table->unsignedInteger('unread_count')->default(0);
            $table->string('last_message_preview', 500)->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'viber_user_id']);
            $table->index(['company_id', 'last_message_at']);
        });

        Schema::create('viber_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('viber_conversation_id')->constrained('viber_conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction', 16); // inbound | outbound
            $table->string('message_token', 64)->nullable()->index();
            $table->string('type', 32)->default('text');
            $table->text('text')->nullable();
            $table->string('media_url', 2048)->nullable();
            $table->string('thumbnail_url', 2048)->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone', 64)->nullable();
            $table->string('sticker_id', 64)->nullable();
            $table->string('status', 32)->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['viber_conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('viber_messages');
        Schema::dropIfExists('viber_conversations');
        Schema::dropIfExists('viber_integrations');
    }
};
