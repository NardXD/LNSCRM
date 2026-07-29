<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('phone_call_logs', function (Blueprint $table) {
            $table->string('recording_sid', 64)->nullable()->after('ended_at');
            $table->string('recording_url', 512)->nullable()->after('recording_sid');
            $table->string('recording_status', 32)->nullable()->after('recording_url');
            $table->unsignedInteger('recording_duration')->nullable()->after('recording_status');
        });
    }

    public function down(): void
    {
        Schema::table('phone_call_logs', function (Blueprint $table) {
            $table->dropColumn([
                'recording_sid',
                'recording_url',
                'recording_status',
                'recording_duration',
            ]);
        });
    }
};
