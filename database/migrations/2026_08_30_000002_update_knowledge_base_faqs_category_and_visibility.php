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
        Schema::table('knowledge_base_faqs', function (Blueprint $table) {
            $table->string('category')->nullable()->change();
            $table->string('visibility', 20)->default('draft')->after('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('knowledge_base_faqs', function (Blueprint $table) {
            $table->dropColumn('visibility');
            $table->string('category')->nullable(false)->change();
        });
    }
};
