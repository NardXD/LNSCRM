<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inbox_conversations', function (Blueprint $table) {
            $table->string('folder', 32)->default('inbox')->after('shared_inbox_id');
            $table->index(['shared_inbox_id', 'folder', 'status'], 'inbox_conv_folder_idx');
        });

        // Allow same Outlook conversation id in different folders (inbox vs sent, etc.)
        Schema::table('inbox_conversations', function (Blueprint $table) {
            $table->dropUnique('inbox_conv_ext_unique');
            $table->unique(['shared_inbox_id', 'folder', 'external_conversation_id'], 'inbox_conv_folder_ext_unique');
        });

        // Expand status enum for clarity (MySQL)
        DB::statement("ALTER TABLE inbox_conversations MODIFY COLUMN status ENUM('open','archived','spam','trashed','drafts','sent') NOT NULL DEFAULT 'open'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE inbox_conversations MODIFY COLUMN status ENUM('open','archived','spam','trashed') NOT NULL DEFAULT 'open'");

        Schema::table('inbox_conversations', function (Blueprint $table) {
            $table->dropUnique('inbox_conv_folder_ext_unique');
            $table->unique(['shared_inbox_id', 'external_conversation_id'], 'inbox_conv_ext_unique');
            $table->dropIndex('inbox_conv_folder_idx');
            $table->dropColumn('folder');
        });
    }
};
