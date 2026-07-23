<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StripeIntegration extends Model
{
    protected $fillable = [
        'company_id',
        'secret_key',
        'publishable_key',
        'webhook_secret',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the company that owns the Stripe integration.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
