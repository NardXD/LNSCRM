<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inbox_messages', function (Blueprint $table) {
            // null = not hydrated yet; [] = none; [{id,name,...}] = file attachments from Graph
            $table->json('attachments')->nullable()->after('body_text');
        });
    }

    public function down(): void
    {
        Schema::table('inbox_messages', function (Blueprint $table) {
            $table->dropColumn('attachments');
        });
    }
};
