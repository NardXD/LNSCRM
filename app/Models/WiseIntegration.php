<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WiseIntegration extends Model
{
    protected $fillable = [
        'company_id',
        'api_token',
        'profile_id',
        'is_sandbox',
        'is_active',
    ];

    protected $casts = [
        'is_sandbox' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
