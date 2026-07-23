<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeBaseArticle extends Model
{
    use HasFactory;

    protected $table = 'knowledge_base_articles';

    protected $fillable = [
        'company_id',
        'user_id',
        'title',
        'excerpt',
        'content',
        'category',
        'visibility',
        'views',
    ];

    protected function casts(): array
    {
        return [
            'views' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
