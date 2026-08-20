<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inbox_conversations', function (Blueprint $table) {
            $table->foreignId('lead_id')
                ->nullable()
                ->after('assigned_to')
                ->constrained('leads')
                ->nullOnDelete();
            $table->index(['lead_id', 'last_message_at'], 'inbox_conv_lead_idx');
        });
    }

    public function down(): void
    {
        Schema::table('inbox_conversations', function (Blueprint $table) {
            $table->dropIndex('inbox_conv_lead_idx');
            $table->dropConstrainedForeignId('lead_id');
        });
    }
};
