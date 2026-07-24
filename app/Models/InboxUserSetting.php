<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InboxUserSetting extends Model
{
    protected $fillable = [
        'user_id',
        'pinned_tag_ids',
    ];

    protected $casts = [
        'pinned_tag_ids' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
