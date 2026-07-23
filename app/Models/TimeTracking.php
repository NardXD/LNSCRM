<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimeTracking extends Model
{
    use HasFactory;

    protected $table = 'time_tracking_records';

    protected $fillable = [
        'user_id',
        'company_id',
        'project_id',
        'task_id',
        'date',
        'time_in',
        'time_out',
        'hours_worked',
        'status',
        'description',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    /**
     * Get the user that owns the time tracking record.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the company that owns the time tracking record.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the edit history for this time tracking record.
     */
    public function editHistory()
    {
        return $this->hasMany(TimeTrackingEditHistory::class, 'time_tracking_record_id');
    }

    /**
     * Get the project associated with this time tracking record.
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the task associated with this time tracking record.
     */
    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Calculate hours worked in a readable format.
     */
    public function getHoursWorkedFormattedAttribute(): string
    {
        if (! $this->hours_worked || $this->hours_worked < 0) {
            return '--';
        }

        // Ensure we're working with a positive value
        $totalSeconds = abs((int) $this->hours_worked);

        $hours = floor($totalSeconds / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);
        $seconds = $totalSeconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }
}
