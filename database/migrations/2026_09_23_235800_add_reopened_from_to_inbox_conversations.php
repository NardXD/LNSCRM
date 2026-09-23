<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inbox_conversations', function (Blueprint $table) {
            $table->string('reopened_from', 16)->nullable()->after('reopen_at');
            $table->index(['status', 'reopened_from']);
        });
    }

    public function down(): void
    {
        Schema::table('inbox_conversations', function (Blueprint $table) {
            $table->dropIndex(['status', 'reopened_from']);
            $table->dropColumn('reopened_from');
        });
    }
};
