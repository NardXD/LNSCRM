<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'client_id',
        'stripe_subscription_id',
        'stripe_customer_id',
        'stripe_price_id',
        'hosted_invoice_url',
        'product_name',
        'unit_amount',
        'currency',
        'interval',
        'interval_count',
        'status',
        'current_period_start',
        'current_period_end',
        'trial_end',
        'canceled_at',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'trial_end' => 'datetime',
        'canceled_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Map Stripe interval + interval_count to display string.
     */
    public function getBillingCycleDisplayAttribute(): string
    {
        $intervalCount = (int) $this->interval_count;
        $interval = $this->interval;

        return match (true) {
            $intervalCount === 1 && $interval === 'month' => 'Monthly',
            $intervalCount === 3 && $interval === 'month' => 'Quarterly',
            $intervalCount === 6 && $interval === 'month' => 'Semi-Annual',
            $intervalCount === 1 && $interval === 'year' => 'Annual',
            $intervalCount === 1 && $interval === 'week' => 'Weekly',
            $intervalCount === 1 && $interval === 'day' => 'Daily',
            default => "{$intervalCount} {$interval}(s)",
        };
    }

    /**
     * Amount in dollars (from cents).
     */
    public function getAmountAttribute(): float
    {
        return round($this->unit_amount / 100, 2);
    }
}
