<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveViewSession extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONNECTING = 'connecting';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ENDED = 'ended';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'company_id',
        'admin_id',
        'admin_type',
        'worker_id',
        'status',
        'started_at',
        'ended_at',
        'admin_ip',
        'admin_user_agent',
        'worker_ip',
        'failure_reason',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function adminClientUser(): BelongsTo
    {
        return $this->belongsTo(ClientUser::class, 'admin_id');
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'worker_id');
    }

    public function adminDisplayName(): ?string
    {
        return $this->admin_type === 'client'
            ? $this->adminClientUser?->name
            : $this->admin?->name;
    }
}
