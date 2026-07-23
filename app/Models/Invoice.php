<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'client_id',
        'user_id',
        'quotation_id',
        'invoice_number',
        'invoice_date',
        'due_date',
        'status',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'total',
        'notes',
        'stripe_payment_url',
        'stripe_payment_link_id',
        'wise_payment_url',
        'wise_paid_at',
        'wise_transaction_id',
        'email_sent_at',
        'stripe_link_generated_at',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'email_sent_at' => 'datetime',
            'stripe_link_generated_at' => 'datetime',
            'wise_paid_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    public static function generateInvoiceNumber(): string
    {
        $year = now()->year;
        $lastInvoice = static::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        if ($lastInvoice) {
            $parts = explode('-', $lastInvoice->invoice_number);
            $lastNumber = isset($parts[2]) ? (int) $parts[2] : 0;
            $nextNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $nextNumber = '001';
        }

        return "INV-{$year}-{$nextNumber}";
    }
}
