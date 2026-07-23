<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollPeriodInvoice extends Model
{
    protected $fillable = [
        'company_id',
        'period_start_date',
        'period_end_date',
        'invoice_ids',
        'converted_employee_ids',
        'employee_invoice_mapping',
        'conversion_details',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'period_start_date' => 'date',
            'period_end_date' => 'date',
            'invoice_ids' => 'array',
            'converted_employee_ids' => 'array',
            'employee_invoice_mapping' => 'array',
            'conversion_details' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
