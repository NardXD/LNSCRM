<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HiringQueueItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'job_title',
        'full_description',
        'source',
        'client_email',
        'notes',
        'status',
        'created_by',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(HiringCandidate::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(HiringQueueItemComment::class);
    }
}
