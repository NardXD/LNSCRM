<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'leader_id',
        'name',
        'description',
        'color',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the company that owns the team.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the team leader.
     */
    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    /**
     * Get the team members.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    /**
     * Get all members including the leader.
     */
    public function allMembers()
    {
        $members = $this->members;
        if ($this->leader && !$members->contains('id', $this->leader_id)) {
            $members->prepend($this->leader);
        }
        return $members;
    }


    /**
     * Get the count of active members.
     */
    public function getActiveMembersCountAttribute(): int
    {
        return $this->members()->where('users.status', 'active')->count();
    }

    /**
     * Get time tracking records for all team members.
     */
    public function getTeamTimeTrackingRecords($startDate = null, $endDate = null)
    {
        $memberIds = $this->members->pluck('id')->toArray();
        if ($this->leader_id) {
            $memberIds[] = $this->leader_id;
        }

        $query = TimeTracking::whereIn('user_id', array_unique($memberIds))
            ->where('company_id', $this->company_id);

        if ($startDate) {
            $query->where('date', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('date', '<=', $endDate);
        }

        return $query->orderBy('date', 'desc')->get();
    }

    /**
     * Get screen recordings for all team members.
     */
    public function getTeamScreenRecordings($startDate = null, $endDate = null)
    {
        $memberIds = $this->members->pluck('id')->toArray();
        if ($this->leader_id) {
            $memberIds[] = $this->leader_id;
        }

        $query = ScreenRecording::whereIn('user_id', array_unique($memberIds))
            ->where('company_id', $this->company_id);

        if ($startDate) {
            $query->where('date', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('date', '<=', $endDate);
        }

        return $query->orderBy('date', 'desc')->get();
    }

    /**
     * Check if a user is a member of this team (including leader).
     */
    public function hasMember(User $user): bool
    {
        if ($this->leader_id === $user->id) {
            return true;
        }
        return $this->members()->where('users.id', $user->id)->exists();
    }

    /**
     * Check if a user is the leader or co-leader of this team.
     */
    public function isLeaderOrCoLeader(User $user): bool
    {
        if ($this->leader_id === $user->id) {
            return true;
        }
        return $this->members()
            ->where('users.id', $user->id)
            ->wherePivot('role', 'co-leader')
            ->exists();
    }

    /**
     * Scope to filter teams by company.
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope to filter active teams.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
