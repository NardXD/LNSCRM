<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facebook_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('page_id', 64);
            $table->text('page_access_token');
            $table->string('app_id', 64)->nullable();
            $table->text('app_secret')->nullable();
            $table->string('webhook_verify_token', 128);
            $table->string('webhook_key', 64)->unique();
            $table->string('page_name')->nullable();
            $table->string('instagram_business_account_id', 64)->nullable();
            $table->string('instagram_username')->nullable();
            $table->text('welcome_message')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('webhook_set_at')->nullable();
            $table->timestamps();

            $table->unique('company_id');
        });

        Schema::create('facebook_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 16); // messenger | instagram
            $table->string('peer_id', 64);
            $table->string('name')->nullable();
            $table->string('username')->nullable();
            $table->string('profile_pic', 2048)->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->string('last_message_preview', 500)->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'channel', 'peer_id']);
            $table->index(['company_id', 'last_message_at']);
        });

        Schema::create('facebook_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('facebook_conversation_id')->constrained('facebook_conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction', 16); // inbound | outbound
            $table->string('mid', 191)->nullable()->index();
            $table->string('type', 32)->default('text');
            $table->text('text')->nullable();
            $table->string('media_url', 2048)->nullable();
            $table->string('mime_type', 128)->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('status', 32)->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['facebook_conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_messages');
        Schema::dropIfExists('facebook_conversations');
        Schema::dropIfExists('facebook_integrations');
    }
};
