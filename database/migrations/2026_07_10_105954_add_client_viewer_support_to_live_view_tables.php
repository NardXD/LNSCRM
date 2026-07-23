<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('live_view_sessions', function (Blueprint $table) {
            $table->dropForeign(['admin_id']);
            $table->string('admin_type', 20)->default('user')->after('admin_id');
        });

        Schema::table('webrtc_signals', function (Blueprint $table) {
            $table->dropForeign(['from_user_id']);
            $table->dropForeign(['to_user_id']);
            $table->string('from_type', 20)->default('user')->after('from_user_id');
            $table->string('to_type', 20)->default('user')->after('to_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('webrtc_signals', function (Blueprint $table) {
            $table->dropColumn(['from_type', 'to_type']);
            $table->foreign('from_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('to_user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('live_view_sessions', function (Blueprint $table) {
            $table->dropColumn('admin_type');
            $table->foreign('admin_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
