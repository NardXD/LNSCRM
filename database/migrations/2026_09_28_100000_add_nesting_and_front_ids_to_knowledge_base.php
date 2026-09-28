<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_base_categories', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('type')
                ->constrained('knowledge_base_categories')->nullOnDelete();
            $table->string('front_category_id')->nullable()->after('parent_id');
            $table->unique(['company_id', 'front_category_id']);
        });

        Schema::table('knowledge_base_articles', function (Blueprint $table) {
            $table->string('front_article_id')->nullable()->after('user_id');
            $table->foreignId('category_id')->nullable()->after('front_article_id')
                ->constrained('knowledge_base_categories')->nullOnDelete();
            $table->unique(['company_id', 'front_article_id']);
        });

        DB::table('knowledge_base_articles')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->orderBy('id')
            ->each(function ($article) {
                $categoryId = DB::table('knowledge_base_categories')
                    ->where('company_id', $article->company_id)
                    ->where('type', 'article')
                    ->where('name', $article->category)
                    ->value('id');

                if ($categoryId) {
                    DB::table('knowledge_base_articles')->where('id', $article->id)->update(['category_id' => $categoryId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('knowledge_base_articles', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'front_article_id']);
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn('front_article_id');
        });

        Schema::table('knowledge_base_categories', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'front_category_id']);
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn('front_category_id');
        });
    }
};
