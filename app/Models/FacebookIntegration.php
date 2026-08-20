<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class FacebookIntegration extends Model
{
    protected $fillable = [
        'company_id',
        'page_id',
        'page_access_token',
        'app_secret',
        'webhook_key',
        'webhook_verify_token',
        'page_name',
        'instagram_business_account_id',
        'instagram_username',
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

    public function webhookUrl(): string
    {
        return url('/webhooks/facebook/'.$this->webhook_key);
    }

    public function statusCallbackUrl(): string
    {
        return route('twilio.sms-status');
    }

    public function senderIdForChannel(string $channel): string
    {
        if ($channel === 'instagram' && $this->instagram_business_account_id) {
            return (string) $this->instagram_business_account_id;
        }

        return (string) $this->page_id;
    }

    public function getDecryptedPageAccessToken(): ?string
    {
        return $this->decryptValue($this->page_access_token);
    }

    public function getDecryptedAppSecret(): ?string
    {
        return $this->decryptValue($this->app_secret);
    }

    public function hasInstagramGraph(): bool
    {
        return $this->is_active
            && (bool) $this->instagram_business_account_id
            && (bool) $this->getDecryptedPageAccessToken();
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
