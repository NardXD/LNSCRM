<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facebook_conversations', function (Blueprint $table) {
            $table->string('extracted_phone')->nullable()->after('username');
            $table->string('extracted_email')->nullable()->after('extracted_phone');
        });
    }

    public function down(): void
    {
        Schema::table('facebook_conversations', function (Blueprint $table) {
            $table->dropColumn(['extracted_phone', 'extracted_email']);
        });
    }
};
