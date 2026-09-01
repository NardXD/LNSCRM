<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreganiseIntegration extends Model
{
    protected $fillable = [
        'company_id',
        'business_code',
        'api_key',
        'webhook_key',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function baseUrl(): string
    {
        return 'https://'.trim($this->business_code).'.storeganise.com';
    }

    public function webhookUrl(): ?string
    {
        if (! $this->webhook_key) {
            return null;
        }

        return url('/webhooks/storeganise/'.$this->webhook_key);
    }
}
