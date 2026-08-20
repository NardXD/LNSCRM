<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inbox_conversations', function (Blueprint $table) {
            $table->foreignId('merged_into_id')
                ->nullable()
                ->after('reopen_at')
                ->constrained('inbox_conversations')
                ->cascadeOnDelete();
        });

        Schema::table('inbox_messages', function (Blueprint $table) {
            $table->foreignId('source_conversation_id')
                ->nullable()
                ->after('inbox_conversation_id')
                ->constrained('inbox_conversations')
                ->nullOnDelete();
        });

        Schema::table('inbox_conversation_comments', function (Blueprint $table) {
            $table->foreignId('source_conversation_id')
                ->nullable()
                ->after('inbox_conversation_id')
                ->constrained('inbox_conversations')
                ->nullOnDelete();
        });

        Schema::table('inbox_conversation_activities', function (Blueprint $table) {
            $table->foreignId('source_conversation_id')
                ->nullable()
                ->after('inbox_conversation_id')
                ->constrained('inbox_conversations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inbox_conversation_activities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_conversation_id');
        });

        Schema::table('inbox_conversation_comments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_conversation_id');
        });

        Schema::table('inbox_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_conversation_id');
        });

        Schema::table('inbox_conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('merged_into_id');
        });
    }
};
