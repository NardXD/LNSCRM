<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shared_inboxes', function (Blueprint $table) {
            // Per-folder resume cursor for the full/backfill walk (see
            // OutlookMailService::syncInbox), keyed by local folder key:
            // {"inbox": {"next_link": "...", "fetched": 0, "backfill_done": false}, ...}
            // Lets a full sync survive interruption (timeout, token failure, network
            // error) and resume from where it left off instead of restarting at page 1
            // and immediately re-tripping the newest-first "already synced" short-circuit.
            $table->json('folder_sync_state')->nullable()->after('last_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('shared_inboxes', function (Blueprint $table) {
            $table->dropColumn('folder_sync_state');
        });
    }
};
