<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inbox_conversation_comments', function (Blueprint $table) {
            $table->string('front_comment_id')->nullable()->unique();
            $table->string('imported_author_name')->nullable();
            $table->string('imported_author_email')->nullable();
        });

        Schema::table('front_integrations', function (Blueprint $table) {
            $table->json('last_comment_import_stats')->nullable();
            $table->timestamp('last_comment_import_at')->nullable();
            $table->boolean('last_comment_import_dry_run')->default(false);
        });

        Schema::create('front_synced_comment_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('front_conversation_id');
            $table->timestamp('front_updated_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'front_conversation_id'], 'front_synced_comment_conv_unique');
        });

        Schema::create('front_comment_import_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('front_inbox_id');
            $table->unsignedBigInteger('shared_inbox_id')->nullable();
            $table->text('next_page_url')->nullable();
            $table->unsignedInteger('conversations_done')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'front_inbox_id'], 'front_comment_progress_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('front_comment_import_progress');
        Schema::dropIfExists('front_synced_comment_conversations');

        Schema::table('front_integrations', function (Blueprint $table) {
            $table->dropColumn([
                'last_comment_import_stats',
                'last_comment_import_at',
                'last_comment_import_dry_run',
            ]);
        });

        Schema::table('inbox_conversation_comments', function (Blueprint $table) {
            $table->dropUnique(['front_comment_id']);
            $table->dropColumn([
                'front_comment_id',
                'imported_author_name',
                'imported_author_email',
            ]);
        });
    }
};
