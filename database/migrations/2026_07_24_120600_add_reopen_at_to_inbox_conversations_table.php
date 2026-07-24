<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inbox_conversations', function (Blueprint $table) {
            $table->timestamp('reopen_at')->nullable()->after('last_message_at');
            $table->index('reopen_at');
        });
    }

    public function down(): void
    {
        Schema::table('inbox_conversations', function (Blueprint $table) {
            $table->dropIndex(['reopen_at']);
            $table->dropColumn('reopen_at');
        });
    }
};
