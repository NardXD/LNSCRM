<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeBaseGuide extends Model
{
    use HasFactory;

    protected $table = 'knowledge_base_guides';

    protected $fillable = [
        'company_id',
        'title',
        'excerpt',
        'category',
        'duration',
        'icon',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
