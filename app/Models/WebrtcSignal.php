<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebrtcSignal extends Model
{
    protected $fillable = [
        'company_id',
        'live_view_session_id',
        'from_user_id',
        'from_type',
        'to_user_id',
        'to_type',
        'signal_type',
        'payload',
        'consumed_at',
        'expires_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'consumed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(LiveViewSession::class, 'live_view_session_id');
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }
}
