<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inbox_messages', function (Blueprint $table) {
            $table->text('reply_to_emails')->nullable()->after('cc_emails');
        });
    }

    public function down(): void
    {
        Schema::table('inbox_messages', function (Blueprint $table) {
            $table->dropColumn('reply_to_emails');
        });
    }
};
