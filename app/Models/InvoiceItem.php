<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'description',
        'hours_worked',
        'net_pay',
        'quantity',
        'unit_price',
        'total',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'hours_worked' => 'decimal:2',
            'net_pay' => 'decimal:2',
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
