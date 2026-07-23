<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TwilioIntegration extends Model
{
    protected $fillable = [
        'company_id',
        'account_sid',
        'auth_token',
        'app_sid',
        'api_key',
        'api_secret',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the company that owns the Twilio integration.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
