<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_inbox_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inbox_conversation_id')->constrained('inbox_conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shared_inbox_id')->nullable()->constrained('shared_inboxes')->nullOnDelete();
            $table->text('to_emails');
            $table->text('cc_emails')->nullable();
            $table->longText('body_html');
            $table->longText('body_text')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamp('send_at');
            $table->boolean('archive_after')->default(false);
            $table->string('status', 20)->default('pending');
            $table->text('error_message')->nullable();
            $table->foreignId('sent_message_id')->nullable()->constrained('inbox_messages')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'send_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_inbox_replies');
    }
};
