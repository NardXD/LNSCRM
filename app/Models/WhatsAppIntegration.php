<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppIntegration extends Model
{
    protected $table = 'whatsapp_integrations';

    protected $fillable = [
        'company_id',
        'from_number',
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

    public function webhookUrl(): string
    {
        return url('/webhooks/whatsapp/'.$this->webhook_key);
    }

    public function statusCallbackUrl(): string
    {
        return route('twilio.sms-status');
    }
}
