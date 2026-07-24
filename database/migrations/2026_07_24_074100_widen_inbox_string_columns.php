<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Outlook display names / subjects can exceed Laravel's default string(255).
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE inbox_conversations MODIFY subject VARCHAR(998) NULL');
            DB::statement('ALTER TABLE inbox_conversations MODIFY from_name VARCHAR(500) NULL');
            DB::statement('ALTER TABLE inbox_conversations MODIFY from_email VARCHAR(320) NULL');
            DB::statement('ALTER TABLE inbox_conversations MODIFY external_conversation_id VARCHAR(512) NULL');

            DB::statement('ALTER TABLE inbox_messages MODIFY subject VARCHAR(998) NULL');
            DB::statement('ALTER TABLE inbox_messages MODIFY from_name VARCHAR(500) NULL');
            DB::statement('ALTER TABLE inbox_messages MODIFY from_email VARCHAR(320) NULL');
            DB::statement('ALTER TABLE inbox_messages MODIFY external_message_id VARCHAR(512) NULL');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE inbox_conversations MODIFY subject VARCHAR(255) NULL');
            DB::statement('ALTER TABLE inbox_conversations MODIFY from_name VARCHAR(255) NULL');
            DB::statement('ALTER TABLE inbox_conversations MODIFY from_email VARCHAR(255) NULL');
            DB::statement('ALTER TABLE inbox_conversations MODIFY external_conversation_id VARCHAR(255) NULL');

            DB::statement('ALTER TABLE inbox_messages MODIFY subject VARCHAR(255) NULL');
            DB::statement('ALTER TABLE inbox_messages MODIFY from_name VARCHAR(255) NULL');
            DB::statement('ALTER TABLE inbox_messages MODIFY from_email VARCHAR(255) NULL');
            DB::statement('ALTER TABLE inbox_messages MODIFY external_message_id VARCHAR(255) NULL');
        }
    }
};
