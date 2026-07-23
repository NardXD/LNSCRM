<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalaryComputation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'company_id',
        'period_start_date',
        'period_end_date',
        'base_salary',
        'hours_worked',
        'required_hours',
        'overtime_hours',
        'allowances',
        'deductions',
        'deduction_details',
        'gross_pay',
        'net_pay',
        'status',
    ];

    protected $casts = [
        'period_start_date' => 'date',
        'period_end_date' => 'date',
        'base_salary' => 'decimal:2',
        'hours_worked' => 'decimal:2',
        'required_hours' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'allowances' => 'decimal:2',
        'deductions' => 'decimal:2',
        'gross_pay' => 'decimal:2',
        'net_pay' => 'decimal:2',
        'deduction_details' => 'array',
    ];

    /**
     * Get the user that owns the salary computation.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the company that owns the salary computation.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the edit history for this salary computation.
     */
    public function editHistory()
    {
        return $this->hasMany(SalaryComputationHistory::class, 'salary_computation_id');
    }
}
