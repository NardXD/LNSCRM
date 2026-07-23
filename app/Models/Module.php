<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Module extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'route',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get the companies that have access to this module.
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_modules')
            ->withPivot('is_enabled', 'granted_at')
            ->withTimestamps();
    }
}
