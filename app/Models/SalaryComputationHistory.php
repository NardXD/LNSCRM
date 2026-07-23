<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalaryComputationHistory extends Model
{
    use HasFactory;

    protected $table = 'salary_computation_history';

    protected $fillable = [
        'salary_computation_id',
        'edited_by',
        'old_required_hours',
        'new_required_hours',
        'old_deductions',
        'new_deductions',
        'old_deduction_details',
        'new_deduction_details',
        'old_gross_pay',
        'new_gross_pay',
        'old_net_pay',
        'new_net_pay',
        'reason',
    ];

    protected $casts = [
        'old_required_hours' => 'decimal:2',
        'new_required_hours' => 'decimal:2',
        'old_deductions' => 'decimal:2',
        'new_deductions' => 'decimal:2',
        'old_gross_pay' => 'decimal:2',
        'new_gross_pay' => 'decimal:2',
        'old_net_pay' => 'decimal:2',
        'new_net_pay' => 'decimal:2',
        'old_deduction_details' => 'array',
        'new_deduction_details' => 'array',
    ];

    /**
     * Get the salary computation that was edited.
     */
    public function salaryComputation()
    {
        return $this->belongsTo(SalaryComputation::class, 'salary_computation_id');
    }

    /**
     * Get the user who made the edit.
     */
    public function editedByUser()
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
}
