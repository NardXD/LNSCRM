<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('kind', 32)->default('chat')->after('type');
            $table->string('status', 32)->default('open')->after('kind');
            $table->foreignId('assigned_to')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('reopen_at')->nullable()->after('assigned_to');
            $table->foreignId('shared_inbox_id')->nullable()->after('reopen_at')->constrained('shared_inboxes')->nullOnDelete();
            $table->timestamp('moved_to_shared_at')->nullable()->after('shared_inbox_id');

            $table->index(['company_id', 'kind', 'status'], 'conversations_company_kind_status_idx');
            $table->index(['company_id', 'kind', 'assigned_to'], 'conversations_company_kind_assignee_idx');
            $table->index(['company_id', 'kind', 'reopen_at'], 'conversations_company_kind_snooze_idx');
            $table->index(['shared_inbox_id', 'kind', 'status'], 'conversations_shared_inbox_kind_status_idx');
        });

        DB::table('conversations')
            ->whereNotNull('front_conversation_id')
            ->update(['kind' => 'discussion', 'status' => 'open']);

        Schema::create('conversation_inbox_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inbox_tag_id')->constrained('inbox_tags')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['conversation_id', 'inbox_tag_id'], 'conversation_inbox_tag_unique');
        });

        Schema::create('discussion_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shared_inbox_id')->nullable()->constrained('shared_inboxes')->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->boolean('stop_processing')->default(false);
            $table->json('triggers')->nullable();
            $table->json('conditions')->nullable();
            $table->json('actions')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'is_active', 'priority'], 'discussion_rules_company_active_priority_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discussion_rules');
        Schema::dropIfExists('conversation_inbox_tag');

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex('conversations_company_kind_status_idx');
            $table->dropIndex('conversations_company_kind_assignee_idx');
            $table->dropIndex('conversations_company_kind_snooze_idx');
            $table->dropIndex('conversations_shared_inbox_kind_status_idx');
            $table->dropConstrainedForeignId('shared_inbox_id');
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn(['kind', 'status', 'reopen_at', 'moved_to_shared_at']);
        });
    }
};
