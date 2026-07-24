<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlook_mail_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'email']);
            $table->index(['company_id', 'user_id']);
        });

        Schema::create('shared_inboxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlook_mail_account_id')->nullable()->constrained('outlook_mail_accounts')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->enum('type', ['personal', 'shared'])->default('shared');
            $table->string('color', 20)->default('#5f61e6');
            $table->string('external_mailbox')->nullable()->comment('Shared mailbox address for Graph /users/{email}');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'type']);
        });

        Schema::create('shared_inbox_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shared_inbox_id')->constrained('shared_inboxes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['admin', 'member'])->default('member');
            $table->timestamps();

            $table->unique(['shared_inbox_id', 'user_id']);
        });

        Schema::create('inbox_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 20)->default('#64748b');
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });

        Schema::create('inbox_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shared_inbox_id')->constrained('shared_inboxes')->cascadeOnDelete();
            $table->string('external_conversation_id')->nullable()->comment('Outlook conversationId');
            $table->string('subject')->nullable();
            $table->string('snippet', 500)->nullable();
            $table->string('from_name')->nullable();
            $table->string('from_email')->nullable();
            $table->enum('status', ['open', 'archived', 'spam', 'trashed'])->default('open');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_read')->default(false);
            $table->unsignedInteger('message_count')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['shared_inbox_id', 'status', 'last_message_at'], 'inbox_conv_status_idx');
            $table->index(['company_id', 'assigned_to'], 'inbox_conv_assignee_idx');
            $table->unique(['shared_inbox_id', 'external_conversation_id'], 'inbox_conv_ext_unique');
        });

        Schema::create('inbox_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inbox_conversation_id')->constrained('inbox_conversations')->cascadeOnDelete();
            $table->string('external_message_id')->nullable();
            $table->enum('direction', ['inbound', 'outbound'])->default('inbound');
            $table->string('from_name')->nullable();
            $table->string('from_email')->nullable();
            $table->text('to_emails')->nullable();
            $table->text('cc_emails')->nullable();
            $table->string('subject')->nullable();
            $table->longText('body_html')->nullable();
            $table->longText('body_text')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['inbox_conversation_id', 'external_message_id'], 'inbox_msg_ext_unique');
            $table->index('sent_at');
        });

        Schema::create('inbox_conversation_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inbox_conversation_id')->constrained('inbox_conversations')->cascadeOnDelete();
            $table->foreignId('inbox_tag_id')->constrained('inbox_tags')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['inbox_conversation_id', 'inbox_tag_id'], 'inbox_conv_tag_unique');
        });

        Schema::create('inbox_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shared_inbox_id')->nullable()->constrained('shared_inboxes')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->json('conditions')->nullable()->comment('[{field, operator, value}]');
            $table->json('actions')->nullable()->comment('[{type, value}]');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'is_active', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_rules');
        Schema::dropIfExists('inbox_conversation_tag');
        Schema::dropIfExists('inbox_messages');
        Schema::dropIfExists('inbox_conversations');
        Schema::dropIfExists('inbox_tags');
        Schema::dropIfExists('shared_inbox_members');
        Schema::dropIfExists('shared_inboxes');
        Schema::dropIfExists('outlook_mail_accounts');
    }
};
