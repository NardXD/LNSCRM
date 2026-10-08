<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inbox_messages', function (Blueprint $table) {
            $table->text('bcc_emails')->nullable()->after('cc_emails');
        });

        Schema::table('scheduled_inbox_replies', function (Blueprint $table) {
            $table->text('bcc_emails')->nullable()->after('cc_emails');
        });
    }

    public function down(): void
    {
        Schema::table('inbox_messages', function (Blueprint $table) {
            $table->dropColumn('bcc_emails');
        });

        Schema::table('scheduled_inbox_replies', function (Blueprint $table) {
            $table->dropColumn('bcc_emails');
        });
    }
};
