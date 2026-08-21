<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduled_inbox_replies', function (Blueprint $table) {
            $table->string('type', 20)->default('reply')->after('inbox_conversation_id');
            $table->string('subject')->nullable()->after('cc_emails');
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_inbox_replies', function (Blueprint $table) {
            $table->dropColumn(['type', 'subject']);
        });
    }
};
