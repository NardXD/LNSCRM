<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inbox_conversations', function (Blueprint $table) {
            $table->index(
                ['shared_inbox_id', 'folder', 'status', 'is_read'],
                'inbox_conv_unread_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('inbox_conversations', function (Blueprint $table) {
            $table->dropIndex('inbox_conv_unread_idx');
        });
    }
};
