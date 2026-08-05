<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallAgentPresence extends Model
{
    public const STATUS_OFFLINE = 'offline';

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_BUSY = 'busy';

    protected $fillable = [
        'company_id',
        'user_id',
        'status',
        'last_heartbeat_at',
        'current_call_sid',
    ];

    protected function casts(): array
    {
        return [
            'last_heartbeat_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
