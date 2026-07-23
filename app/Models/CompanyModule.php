<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyModule extends Model
{
    use HasFactory;

    protected $table = 'company_modules';

    protected $fillable = [
        'company_id',
        'module_id',
        'is_enabled',
        'granted_at',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'granted_at' => 'datetime',
    ];

    /**
     * Get the company.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the module.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
