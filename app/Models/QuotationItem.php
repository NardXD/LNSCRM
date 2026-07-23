<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'quotation_id',
        'item_name',
        'description',
        'quantity',
        'unit_price',
        'tax_percentage',
        'tax_amount',
        'total',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'tax_percentage' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    /**
     * Get the quotation that owns the item.
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * Calculate item total.
     */
    public function calculateTotal(): void
    {
        $subtotal = $this->quantity * $this->unit_price;
        $this->tax_amount = $subtotal * ($this->tax_percentage / 100);
        $this->total = $subtotal + $this->tax_amount;
    }
}
