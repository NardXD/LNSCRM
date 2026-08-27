<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->timestamp('scheduled_status_at')->nullable()->after('follow_up_notified_day');
            $table->string('scheduled_status')->nullable()->after('scheduled_status_at');
            $table->string('scheduled_status_from')->nullable()->after('scheduled_status');
            $table->index(['scheduled_status_at']);
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['scheduled_status_at']);
            $table->dropColumn(['scheduled_status_at', 'scheduled_status', 'scheduled_status_from']);
        });
    }
};
