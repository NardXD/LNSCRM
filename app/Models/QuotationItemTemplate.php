<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItemTemplate extends Model
{
    protected $fillable = [
        'company_id',
        'item_name',
        'description',
        'default_quantity',
        'default_unit_price',
        'default_tax_percentage',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'default_quantity' => 'decimal:2',
        'default_unit_price' => 'decimal:2',
        'default_tax_percentage' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
