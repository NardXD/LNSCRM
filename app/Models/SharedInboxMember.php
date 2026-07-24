<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharedInboxMember extends Model
{
    protected $fillable = [
        'shared_inbox_id',
        'user_id',
        'role',
    ];

    public function inbox(): BelongsTo
    {
        return $this->belongsTo(SharedInbox::class, 'shared_inbox_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
