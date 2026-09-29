<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduled_inbox_replies', function (Blueprint $table) {
            // The Outlook-native draft (if any) this reply was composed from, so it
            // can be deleted after a successful send — mirrors what the immediate
            // "Send" path already did before immediate sends were queued too.
            $table->string('draft_message_id', 512)->nullable()->after('attachments');

            // True when this row exists only to move the Graph API send call off the
            // request thread (send_at ~= created_at), not because the user asked to
            // schedule it for later. Used to pick the right activity-log wording and
            // to distinguish "sending" from "scheduled" in the UI.
            $table->boolean('is_immediate')->default(false)->after('send_at');
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_inbox_replies', function (Blueprint $table) {
            $table->dropColumn(['draft_message_id', 'is_immediate']);
        });
    }
};
