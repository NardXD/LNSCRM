<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Remove legacy default article categories seeded before articles used optional categories.
     */
    public function up(): void
    {
        DB::table('knowledge_base_categories')
            ->where('type', 'article')
            ->whereIn('slug', [
                'getting-started',
                'features',
                'troubleshooting',
                'api',
            ])
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Defaults are no longer re-seeded for articles; nothing to restore automatically.
    }
};
