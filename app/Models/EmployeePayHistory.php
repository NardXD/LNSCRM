<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeePayHistory extends Model
{
    use HasFactory;

    protected $table = 'employee_pay_history';

    protected $fillable = [
        'company_id',
        'user_id',
        'payroll_report_id',
        'payroll_report_item_id',
        'amount',
        'currency',
        'wise_transfer_id',
        'period_start_date',
        'period_end_date',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'period_start_date' => 'date',
        'period_end_date' => 'date',
        'paid_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payrollReport()
    {
        return $this->belongsTo(PayrollReport::class);
    }

    public function payrollReportItem()
    {
        return $this->belongsTo(PayrollReportItem::class);
    }
}
