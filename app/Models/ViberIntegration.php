<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class ViberIntegration extends Model
{
    protected $fillable = [
        'company_id',
        'auth_token',
        'webhook_key',
        'bot_name',
        'bot_uri',
        'bot_avatar',
        'welcome_message',
        'is_active',
        'webhook_set_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'webhook_set_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function getDecryptedAuthToken(): ?string
    {
        if (! $this->auth_token) {
            return null;
        }

        try {
            return Crypt::decryptString($this->auth_token);
        } catch (\Throwable) {
            return $this->auth_token;
        }
    }

    public function webhookUrl(): string
    {
        return url('/webhooks/viber/'.$this->webhook_key);
    }
}
