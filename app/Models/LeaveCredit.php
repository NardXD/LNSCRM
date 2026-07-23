<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveCredit extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'user_id',
        'leave_type',
        'credits',
        'year',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'credits' => 'decimal:2',
            'year' => 'integer',
        ];
    }

    /**
     * Get the company that owns the leave credit.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user who owns the leave credit.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
