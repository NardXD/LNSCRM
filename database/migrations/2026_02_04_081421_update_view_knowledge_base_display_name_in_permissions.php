<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('permissions')
            ->where('slug', 'view_knowledge_base')
            ->update(['display_name' => 'View Knowledge Base']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('permissions')
            ->where('slug', 'view_knowledge_base')
            ->update(['display_name' => 'Knowledge Base']);
    }
};
