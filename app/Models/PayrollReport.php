<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'period_start_date',
        'period_end_date',
        'total_amount',
        'currency',
        'status',
        'created_by',
    ];

    protected $casts = [
        'period_start_date' => 'date',
        'period_end_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(PayrollReportItem::class);
    }
}
