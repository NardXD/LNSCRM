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
            $table->string('contact_email', 320)->nullable()->after('from_email');
            $table->boolean('auto_group_disabled')->default(false)->after('merged_into_id');
            $table->index(['shared_inbox_id', 'contact_email'], 'inbox_conv_contact_idx');
        });

        // Received threads: the counterparty is the sender. Sent/draft rows are filled by inbox:group-by-contact.
        DB::table('inbox_conversations')
            ->whereNull('contact_email')
            ->whereNotNull('from_email')
            ->whereNotIn('folder', ['sent', 'drafts'])
            ->update(['contact_email' => DB::raw('LOWER(TRIM(from_email))')]);
    }

    public function down(): void
    {
        Schema::table('inbox_conversations', function (Blueprint $table) {
            $table->dropIndex('inbox_conv_contact_idx');
            $table->dropColumn(['contact_email', 'auto_group_disabled']);
        });
    }
};
