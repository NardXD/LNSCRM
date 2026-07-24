<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inbox_rules', function (Blueprint $table) {
            $table->json('triggers')->nullable()->after('stop_processing');
        });
    }

    public function down(): void
    {
        Schema::table('inbox_rules', function (Blueprint $table) {
            $table->dropColumn('triggers');
        });
    }
};
