<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OutlookMailAccount extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
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

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function inboxes(): HasMany
    {
        return $this->hasMany(SharedInbox::class);
    }

    public function needsRefresh(): bool
    {
        if (! $this->token_expires_at) {
            return false;
        }

        return $this->token_expires_at->copy()->subMinutes(5)->isPast();
    }
}
