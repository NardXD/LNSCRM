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
        Schema::table('users', function (Blueprint $table) {
            // Length of each screen recording clip, in minutes. Defaults to the
            // value currently used by the recorders (30 seconds = 0.5 minutes).
            $table->decimal('recording_duration_minutes', 5, 2)->default(0.5)->after('required_work_hours');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('recording_duration_minutes');
        });
    }
};
