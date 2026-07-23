<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarIntegration extends Model
{
    public const PROVIDER_GOOGLE = 'google';

    public const PROVIDER_OUTLOOK = 'outlook';

    protected $fillable = [
        'user_id',
        'provider',
        'email',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'is_active',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'is_active' => 'boolean',
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    /**
     * Get the user that owns the calendar integration.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        if (! $this->token_expires_at) {
            return false;
        }

        return $this->token_expires_at->isPast();
    }

    public function needsRefresh(): bool
    {
        if (! $this->token_expires_at) {
            return false;
        }

        return $this->token_expires_at->subMinutes(5)->isPast();
    }
}
