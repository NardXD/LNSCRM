<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('from_number', 32)->nullable();
            $table->string('webhook_key', 64)->unique();
            $table->string('display_phone_number', 32)->nullable();
            $table->string('business_name')->nullable();
            $table->text('welcome_message')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('webhook_set_at')->nullable();
            $table->timestamps();

            $table->unique('company_id');
        });

        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('wa_id', 32);
            $table->string('name')->nullable();
            $table->string('profile_name')->nullable();
            $table->string('phone', 32)->nullable();
            $table->boolean('is_subscribed')->default(true);
            $table->unsignedInteger('unread_count')->default(0);
            $table->string('last_message_preview', 500)->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('window_expires_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'wa_id']);
            $table->index(['company_id', 'last_message_at']);
        });

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_conversation_id')->constrained('whatsapp_conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction', 16); // inbound | outbound
            $table->string('wamid', 128)->nullable()->index();
            $table->string('type', 32)->default('text');
            $table->text('text')->nullable();
            $table->string('media_url', 2048)->nullable();
            $table->string('media_id', 128)->nullable();
            $table->string('mime_type', 128)->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone', 64)->nullable();
            $table->string('status', 32)->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['whatsapp_conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_conversations');
        Schema::dropIfExists('whatsapp_integrations');
    }
};
