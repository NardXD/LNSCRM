<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class KnowledgeBaseCategory extends Model
{
    public const PATH_SEPARATOR = ' › ';

    protected $table = 'knowledge_base_categories';

    protected $fillable = [
        'company_id',
        'type',
        'parent_id',
        'front_category_id',
        'name',
        'slug',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Full "Parent › Child" names keyed by category id.
     *
     * @return array<int, string>
     */
    public static function pathNames(int $companyId, string $type = 'article'): array
    {
        $rows = static::query()
            ->where('company_id', $companyId)
            ->where('type', $type)
            ->get(['id', 'parent_id', 'name'])
            ->keyBy('id');

        $paths = [];
        foreach ($rows as $id => $row) {
            $parts = [];
            $seen = [];
            $current = $row;
            while ($current && ! isset($seen[$current->id])) {
                $seen[$current->id] = true;
                array_unshift($parts, $current->name);
                $current = $current->parent_id ? $rows->get($current->parent_id) : null;
            }
            $paths[(int) $id] = implode(self::PATH_SEPARATOR, $parts);
        }

        return $paths;
    }

    /**
     * Ids of the category and every category nested under it.
     *
     * @return list<int>
     */
    public static function subtreeIds(int $companyId, int $categoryId, string $type = 'article'): array
    {
        $childrenByParent = static::query()
            ->where('company_id', $companyId)
            ->where('type', $type)
            ->whereNotNull('parent_id')
            ->get(['id', 'parent_id'])
            ->groupBy('parent_id');

        $ids = [];
        $queue = [$categoryId];
        while ($queue !== []) {
            $id = array_shift($queue);
            if (in_array($id, $ids, true)) {
                continue;
            }
            $ids[] = $id;
            foreach ($childrenByParent->get($id, collect()) as $child) {
                $queue[] = (int) $child->id;
            }
        }

        return $ids;
    }

    public static function uniqueSlug(int $companyId, string $type, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'category-'.substr(sha1($name), 0, 8);
        }

        $slug = $base;
        $n = 2;
        while (static::query()
            ->where('company_id', $companyId)
            ->where('type', $type)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }

    public static function nextSortOrder(int $companyId, string $type, ?int $parentId): int
    {
        return (int) static::query()
            ->where('company_id', $companyId)
            ->where('type', $type)
            ->where('parent_id', $parentId)
            ->max('sort_order') + 1;
    }

    /**
     * Keep each article's text `category` column equal to its category's full path.
     */
    public static function syncArticlePaths(int $companyId): void
    {
        $paths = static::pathNames($companyId);

        KnowledgeBaseArticle::query()
            ->where('company_id', $companyId)
            ->get(['id', 'category_id', 'category'])
            ->each(function (KnowledgeBaseArticle $article) use ($paths) {
                $path = $article->category_id ? ($paths[$article->category_id] ?? null) : null;
                if ($article->category !== $path) {
                    KnowledgeBaseArticle::query()->whereKey($article->id)->toBase()->update(['category' => $path]);
                }
            });
    }

    /**
     * Ensure default categories exist for a company (when none exist for that type).
     */
    public static function ensureDefaultsForCompany(int $companyId): void
    {
        $defaults = [
            'guide' => [
                ['name' => 'Getting Started', 'slug' => 'getting-started'],
                ['name' => 'Features', 'slug' => 'features'],
                ['name' => 'Troubleshooting', 'slug' => 'troubleshooting'],
                ['name' => 'API Documentation', 'slug' => 'api'],
            ],
        ];

        foreach ($defaults as $type => $items) {
            if (static::where('company_id', $companyId)->where('type', $type)->exists()) {
                continue;
            }
            $sort = 0;
            foreach ($items as $item) {
                static::create([
                    'company_id' => $companyId,
                    'type' => $type,
                    'name' => $item['name'],
                    'slug' => $item['slug'],
                    'sort_order' => $sort++,
                ]);
            }
        }
    }
}
