<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class FrontIntegration extends Model
{
    protected $fillable = [
        'company_id',
        'api_token',
        'verify_error',
        'verified_at',
        'is_active',
        'last_import_stats',
        'last_import_at',
        'last_import_dry_run',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_import_stats' => 'array',
        'last_import_at' => 'datetime',
        'last_import_dry_run' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function getDecryptedApiToken(): ?string
    {
        if (! $this->api_token) {
            return null;
        }

        try {
            return Crypt::decryptString($this->api_token);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * True only when a token is stored AND the last verification against Front's API succeeded.
     * A stored token that Front has rejected (or that failed to decrypt) is not "connected" —
     * the badge would otherwise keep claiming success while every real request fails.
     */
    public function isConnected(): bool
    {
        return $this->is_active && (bool) $this->api_token && $this->verify_error === null;
    }

    public function hasToken(): bool
    {
        return (bool) $this->api_token;
    }
}
