<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InboxTemplate extends Model
{
    protected $fillable = [
        'company_id',
        'created_by',
        'name',
        'subject',
        'body_html',
        'body_text',
        'attachments',
        'front_template_id',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'last_used_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
