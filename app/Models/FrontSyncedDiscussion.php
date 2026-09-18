<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FrontSyncedDiscussion extends Model
{
    protected $fillable = [
        'company_id',
        'front_conversation_id',
        'conversation_id',
        'front_updated_at',
    ];

    protected $casts = [
        'front_updated_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
