<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('knowledge_base_articles', function (Blueprint $table) {
            $table->string('category')->nullable()->change();
            $table->string('visibility', 20)->default('draft')->change();
        });

        DB::table('knowledge_base_articles')
            ->where('visibility', 'internal')
            ->update(['visibility' => 'published']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('knowledge_base_articles')
            ->where('visibility', 'published')
            ->update(['visibility' => 'internal']);

        DB::table('knowledge_base_articles')
            ->whereNull('category')
            ->update(['category' => '']);

        Schema::table('knowledge_base_articles', function (Blueprint $table) {
            $table->string('category')->nullable(false)->change();
            $table->string('visibility', 20)->default('internal')->change();
        });
    }
};
