<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'client_id',
        'title',
        'client',
        'status',
        'deadline',
        'description',
        'progress',
    ];

    protected function casts(): array
    {
        return [
            'deadline' => 'date',
            'progress' => 'integer',
        ];
    }

    /**
     * Get the company that owns the project.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the client that owns the project.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the tasks for the project.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->orderBy('order');
    }

    /**
     * Get the team members (users) assigned to the project.
     */
    public function teamMembers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_user');
    }

    /**
     * Get time tracking records for this project.
     */
    public function timeTrackingRecords(): HasMany
    {
        return $this->hasMany(TimeTracking::class);
    }

    /**
     * Get project time tracking records for this project.
     */
    public function projectTimeTracking(): HasMany
    {
        return $this->hasMany(ProjectTimeTracking::class);
    }

    /**
     * Calculate the progress based on tasks.
     */
    public function calculateProgress(): int
    {
        $tasks = $this->tasks;

        if ($tasks->isEmpty()) {
            return 0;
        }

        $totalProgress = $tasks->sum('progress');

        return (int) round($totalProgress / $tasks->count());
    }

    /**
     * Get completed tasks count.
     */
    public function getCompletedTasksCountAttribute(): int
    {
        return $this->tasks()->where('status', 'done')->count();
    }

    /**
     * Get total tasks count.
     */
    public function getTotalTasksCountAttribute(): int
    {
        return $this->tasks()->count();
    }
}
