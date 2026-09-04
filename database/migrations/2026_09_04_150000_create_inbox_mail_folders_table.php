<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbox_mail_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shared_inbox_id')->constrained('shared_inboxes')->cascadeOnDelete();
            $table->string('local_key', 32)->comment('Value stored in inbox_conversations.folder');
            $table->string('graph_folder_id')->comment('Outlook Graph mailFolder id, used for all Graph calls');
            $table->string('display_name');
            $table->string('parent_local_key', 32)->nullable();
            $table->string('well_known_name', 64)->nullable()->comment('Graph wellKnownName, e.g. inbox/drafts/sentitems');
            $table->string('status_default', 20)->default('open');
            $table->string('direction_default', 10)->default('inbound');
            $table->unsignedInteger('graph_total_count')->default(0);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['shared_inbox_id', 'local_key']);
            $table->unique(['shared_inbox_id', 'graph_folder_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_mail_folders');
    }
};
