<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class WhatsAppIntegration extends Model
{
    protected $table = 'whatsapp_integrations';

    protected $fillable = [
        'company_id',
        'access_token',
        'phone_number_id',
        'waba_id',
        'app_secret',
        'webhook_verify_token',
        'webhook_key',
        'display_phone_number',
        'business_name',
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

    public function getDecryptedAccessToken(): ?string
    {
        if (! $this->access_token) {
            return null;
        }

        try {
            return Crypt::decryptString($this->access_token);
        } catch (\Throwable) {
            return $this->access_token;
        }
    }

    public function getDecryptedAppSecret(): ?string
    {
        if (! $this->app_secret) {
            return null;
        }

        try {
            return Crypt::decryptString($this->app_secret);
        } catch (\Throwable) {
            return $this->app_secret;
        }
    }

    public function webhookUrl(): string
    {
        return url('/webhooks/whatsapp/'.$this->webhook_key);
    }
}
