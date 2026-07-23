<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeBaseFaq extends Model
{
    use HasFactory;

    protected $table = 'knowledge_base_faqs';

    protected $fillable = [
        'company_id',
        'question',
        'answer',
        'category',
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
}
