<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollReportItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_report_id',
        'user_id',
        'employee_name',
        'wise_account',
        'net_pay',
        'gross_pay',
        'base_salary',
        'hours_worked',
        'required_hours',
        'overtime_hours',
        'allowances',
        'deductions',
        'currency',
        'wise_status',
        'wise_transfer_id',
        'wise_error',
    ];

    protected $casts = [
        'net_pay' => 'decimal:2',
        'gross_pay' => 'decimal:2',
        'base_salary' => 'decimal:2',
        'hours_worked' => 'decimal:2',
        'required_hours' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'allowances' => 'decimal:2',
        'deductions' => 'decimal:2',
    ];

    public function payrollReport()
    {
        return $this->belongsTo(PayrollReport::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
