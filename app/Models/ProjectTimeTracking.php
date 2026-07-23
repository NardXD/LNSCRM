<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTimeTracking extends Model
{
    use HasFactory;

    protected $table = 'project_time_tracking';

    protected $fillable = [
        'project_id',
        'task_id',
        'user_id',
        'company_id',
        'date',
        'start_time',
        'end_time',
        'hours_worked',
        'notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /**
     * Get the project associated with this time tracking record.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the task associated with this time tracking record.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the user that owns this time tracking record.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the company that owns this time tracking record.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Calculate hours worked in a readable format.
     */
    public function getHoursWorkedFormattedAttribute(): string
    {
        if (! $this->hours_worked || $this->hours_worked < 0) {
            return '--';
        }

        $totalSeconds = abs((int) $this->hours_worked);

        $hours = floor($totalSeconds / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);
        $seconds = $totalSeconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    /**
     * Get hours worked as decimal (e.g., 2.5 hours).
     */
    public function getHoursWorkedDecimalAttribute(): float
    {
        if (! $this->hours_worked || $this->hours_worked < 0) {
            return 0.0;
        }

        return round($this->hours_worked / 3600, 2);
    }
}
