<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hiring_queue_items', function (Blueprint $table) {
            $table->string('client_email')->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('hiring_queue_items', function (Blueprint $table) {
            $table->dropColumn('client_email');
        });
    }
};
