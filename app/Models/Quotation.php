<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Quotation extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'client_id',
        'user_id',
        'quotation_number',
        'quotation_date',
        'valid_until',
        'status',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'discount_type',
        'total',
        'internal_notes',
        'terms_conditions',
        'sent_at',
        'quote_type',
        'storage_tenant',
        'storage_alt_contact',
        'storage_units',
        'storage_terms',
        'storage_totals',
        'facility_code',
        'signature_path',
    ];

    protected function casts(): array
    {
        return [
            'quotation_date' => 'date',
            'valid_until' => 'date',
            'sent_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'storage_tenant' => 'array',
            'storage_alt_contact' => 'array',
            'storage_units' => 'array',
            'storage_terms' => 'array',
            'storage_totals' => 'array',
        ];
    }

    /**
     * Get the contracts generated from this quotation.
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function getSignatureDataUriAttribute(): ?string
    {
        if (! $this->signature_path || ! Storage::disk('local')->exists($this->signature_path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode(Storage::disk('local')->get($this->signature_path));
    }

    /**
     * Get the company that owns the quotation.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the client for the quotation.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the user who created the quotation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the items for the quotation.
     */
    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('sort_order');
    }

    /**
     * Get the status history for the quotation.
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(QuotationStatusHistory::class)->orderBy('created_at', 'desc');
    }

    /**
     * Generate next quotation number.
     */
    public static function generateQuotationNumber(): string
    {
        $year = now()->year;
        $lastQuotation = static::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        if ($lastQuotation) {
            $lastNumber = (int) substr($lastQuotation->quotation_number, -3);
            $nextNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $nextNumber = '001';
        }

        return "QT-{$year}-{$nextNumber}";
    }
}
