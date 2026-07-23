<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HiringCandidate extends Model
{
    use HasFactory;

    protected $fillable = [
        'hiring_queue_item_id',
        'name',
        'email',
        'phone',
        'interview_date',
        'notes',
        'status',
    ];

    protected $casts = [
        'interview_date' => 'date',
    ];

    public function hiringQueueItem(): BelongsTo
    {
        return $this->belongsTo(HiringQueueItem::class);
    }
}
