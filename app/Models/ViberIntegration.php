<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ViberIntegration extends Model
{
    protected $fillable = [
        'company_id',
        'sender_id',
        'webhook_key',
        'bot_name',
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
        return url('/webhooks/viber/'.$this->webhook_key);
    }

    public function statusCallbackUrl(): string
    {
        return route('twilio.sms-status');
    }
}