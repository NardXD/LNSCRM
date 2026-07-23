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
        Schema::table('time_tracking_records', function (Blueprint $table) {
            // Remove recording-related columns (if they exist)
            if (Schema::hasColumn('time_tracking_records', 'screen_recording_path')) {
                $table->dropColumn('screen_recording_path');
            }
            if (Schema::hasColumn('time_tracking_records', 'screen_recording_duration')) {
                $table->dropColumn('screen_recording_duration');
            }
            if (Schema::hasColumn('time_tracking_records', 'is_recording')) {
                $table->dropColumn('is_recording');
            }
            if (Schema::hasColumn('time_tracking_records', 'metadata')) {
                $table->dropColumn('metadata');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('time_tracking_records', function (Blueprint $table) {
            // Add back the columns if needed (for rollback)
            $table->string('screen_recording_path')->nullable();
            $table->integer('screen_recording_duration')->nullable();
            $table->boolean('is_recording')->default(false);
            $table->json('metadata')->nullable();
        });
    }
};
