<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Marks a Front conversation as already scanned for tags as of a given Front
        // "updated_at", so a later run can skip re-labeling it unless Front says it
        // changed since. Never written for unmatched conversations, so those keep
        // being retried once the matching local conversation shows up.
        Schema::create('front_synced_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('front_conversation_id');
            $table->timestamp('front_updated_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'front_conversation_id'], 'front_synced_conv_unique');
        });

        // Where a paged, per-inbox import last left off. A row here means that inbox's
        // last run didn't finish; the next run resumes from next_page_url instead of
        // restarting at page 1. The row is deleted once an inbox's pagination completes.
        Schema::create('front_inbox_import_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('front_inbox_id');
            $table->unsignedBigInteger('shared_inbox_id')->nullable();
            $table->text('next_page_url')->nullable();
            $table->unsignedInteger('conversations_done')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'front_inbox_id'], 'front_inbox_progress_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('front_inbox_import_progress');
        Schema::dropIfExists('front_synced_conversations');
    }
};
