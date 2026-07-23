<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationStatusHistory extends Model
{
    protected $fillable = [
        'quotation_id',
        'user_id',
        'status',
        'previous_status',
        'notes',
    ];

    /**
     * Get the quotation that owns the status history.
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * Get the user who changed the status.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
