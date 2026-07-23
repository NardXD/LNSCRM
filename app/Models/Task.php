<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'assigned_to',
        'title',
        'description',
        'priority',
        'deadline',
        'status',
        'progress',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'deadline' => 'date',
            'progress' => 'integer',
            'order' => 'integer',
        ];
    }

    /**
     * Get the project that owns the task.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user assigned to the task.
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get time tracking records for this task.
     */
    public function timeTrackingRecords(): HasMany
    {
        return $this->hasMany(TimeTracking::class);
    }

    /**
     * Get project time tracking records for this task.
     */
    public function projectTimeTracking(): HasMany
    {
        return $this->hasMany(ProjectTimeTracking::class);
    }
}
