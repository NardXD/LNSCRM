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
        'webhook_key',
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
        if (! $this->page_access_token) {
            return null;
        }

        try {
            return Crypt::decryptString($this->page_access_token);
        } catch (\Throwable) {
            return $this->page_access_token;
        }
    }
}
