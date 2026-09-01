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
        Schema::table('leads', function (Blueprint $table) {
            $table->string('storeganise_site_id', 128)->nullable()->after('scheduled_status_from');
            $table->string('storeganise_user_id', 128)->nullable()->after('storeganise_site_id');
            $table->timestamp('storeganise_pushed_at')->nullable()->after('storeganise_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'storeganise_site_id',
                'storeganise_user_id',
                'storeganise_pushed_at',
            ]);
        });
    }
};
