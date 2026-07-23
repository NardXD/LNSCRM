<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveViewChatMessage extends Model
{
    protected $fillable = [
        'live_view_session_id',
        'company_id',
        'sender_id',
        'body',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(LiveViewSession::class, 'live_view_session_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
