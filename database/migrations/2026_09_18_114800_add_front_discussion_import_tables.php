<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('front_conversation_id')->nullable()->unique();
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->string('front_comment_id')->nullable()->unique();
        });

        Schema::table('front_integrations', function (Blueprint $table) {
            $table->json('last_discussion_import_stats')->nullable();
            $table->timestamp('last_discussion_import_at')->nullable();
            $table->boolean('last_discussion_import_dry_run')->default(false);
        });

        Schema::create('front_synced_discussions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('front_conversation_id');
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('front_updated_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'front_conversation_id'], 'front_synced_discussions_unique');
        });

        Schema::create('front_discussion_import_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->text('next_page_url')->nullable();
            $table->unsignedInteger('conversations_done')->default(0);
            $table->timestamps();

            $table->unique('company_id', 'front_discussion_progress_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('front_discussion_import_progress');
        Schema::dropIfExists('front_synced_discussions');

        Schema::table('front_integrations', function (Blueprint $table) {
            $table->dropColumn([
                'last_discussion_import_stats',
                'last_discussion_import_at',
                'last_discussion_import_dry_run',
            ]);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropUnique(['front_comment_id']);
            $table->dropColumn('front_comment_id');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropUnique(['front_conversation_id']);
            $table->dropColumn('front_conversation_id');
        });
    }
};
