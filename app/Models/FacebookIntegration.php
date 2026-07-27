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
        'app_id',
        'app_secret',
        'webhook_verify_token',
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
        return url('/webhooks/facebook/'.$this->webhook_key);
    }
}
