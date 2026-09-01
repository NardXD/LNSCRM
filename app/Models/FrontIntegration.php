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
            return $this->api_token;
        }
    }

    public function isConnected(): bool
    {
        return $this->is_active && (bool) $this->api_token;
    }
}
