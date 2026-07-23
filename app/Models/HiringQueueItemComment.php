<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HiringQueueItemComment extends Model
{
    protected $fillable = [
        'hiring_queue_item_id',
        'user_id',
        'content',
    ];

    public function hiringQueueItem(): BelongsTo
    {
        return $this->belongsTo(HiringQueueItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
