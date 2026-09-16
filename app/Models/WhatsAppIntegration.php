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
        'phone_number_id',
        'waba_id',
        'access_token',
        'app_secret',
        'from_number',
        'webhook_key',
        'webhook_verify_token',
        'display_phone_number',
        'business_name',
        'welcome_message',
        'is_active',
        'webhook_set_at',
    ];

    protected $hidden = [
        'access_token',
        'app_secret',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'webhook_set_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function webhookUrl(): string
    {
        return url('/webhooks/whatsapp/'.$this->webhook_key);
    }

    public function isCloudConnected(): bool
    {
        return $this->is_active
            && (bool) $this->phone_number_id
            && (bool) $this->getDecryptedAccessToken();
    }

    public function getDecryptedAccessToken(): ?string
    {
        return $this->decryptValue($this->access_token);
    }

    public function getDecryptedAppSecret(): ?string
    {
        return $this->decryptValue($this->app_secret);
    }

    protected function decryptValue(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return $value;
        }
    }
}
