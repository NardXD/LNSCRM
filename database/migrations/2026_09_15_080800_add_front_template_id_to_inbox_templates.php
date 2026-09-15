<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inbox_templates', function (Blueprint $table) {
            $table->string('front_template_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('inbox_templates', function (Blueprint $table) {
            $table->dropUnique(['front_template_id']);
            $table->dropColumn('front_template_id');
        });
    }
};
