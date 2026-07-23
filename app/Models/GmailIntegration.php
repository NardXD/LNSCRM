<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GmailIntegration extends Model
{
    protected $fillable = [
        'company_id',
        'email',
        'app_password',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the company that owns the Gmail integration.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
