<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inbox_user_settings', function (Blueprint $table) {
            $table->json('sidebar_label_ids')->nullable()->after('pinned_tag_ids');
        });
    }

    public function down(): void
    {
        Schema::table('inbox_user_settings', function (Blueprint $table) {
            $table->dropColumn('sidebar_label_ids');
        });
    }
};
