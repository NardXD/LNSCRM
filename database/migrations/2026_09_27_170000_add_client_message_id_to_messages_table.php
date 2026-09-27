<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('client_message_id', 64)->nullable()->after('user_id');
            $table->unique(['conversation_id', 'user_id', 'client_message_id'], 'messages_client_message_unique');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropUnique('messages_client_message_unique');
            $table->dropColumn('client_message_id');
        });
    }
};
