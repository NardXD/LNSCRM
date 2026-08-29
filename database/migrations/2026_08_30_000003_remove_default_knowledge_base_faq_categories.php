<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Remove legacy default FAQ categories seeded before FAQs used optional categories.
     */
    public function up(): void
    {
        DB::table('knowledge_base_categories')
            ->where('type', 'faq')
            ->whereIn('slug', [
                'account',
                'billing',
                'features',
                'technical',
            ])
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
