<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inbox_messages', function (Blueprint $table) {
            // The teammate who sent this message from the CRM. Shared inboxes send as
            // the shared mailbox address, so from_email can't tell teammates apart;
            // this powers Views → Sent ("mail I sent", personal and shared).
            $table->foreignId('sent_by_user_id')->nullable()->after('direction')->constrained('users')->nullOnDelete();
            $table->index(['sent_by_user_id', 'inbox_conversation_id'], 'inbox_msg_sent_by_idx');
        });

        // Backfill from queued/scheduled sends, which record who sent each message.
        DB::table('scheduled_inbox_replies')
            ->where('status', 'sent')
            ->whereNotNull('sent_message_id')
            ->whereNotNull('user_id')
            ->select(['id', 'user_id', 'sent_message_id'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('inbox_messages')
                        ->where('id', $row->sent_message_id)
                        ->whereNull('sent_by_user_id')
                        ->update(['sent_by_user_id' => $row->user_id]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('inbox_messages', function (Blueprint $table) {
            $table->dropIndex('inbox_msg_sent_by_idx');
            $table->dropConstrainedForeignId('sent_by_user_id');
        });
    }
};
