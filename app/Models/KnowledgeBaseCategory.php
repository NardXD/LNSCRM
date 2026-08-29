<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeBaseCategory extends Model
{
    protected $table = 'knowledge_base_categories';

    protected $fillable = [
        'company_id',
        'type',
        'name',
        'slug',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
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
