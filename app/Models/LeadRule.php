<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadRule extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'priority',
        'is_active',
        'stop_processing',
        'triggers',
        'conditions',
        'actions',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'stop_processing' => 'boolean',
        'triggers' => 'array',
        'conditions' => 'array',
        'actions' => 'array',
        'priority' => 'integer',
        'last_applied_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
