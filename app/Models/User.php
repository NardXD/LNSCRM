<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'company_id',
        'role_id',
        'department_id',
        'status',
        'phone',
        'address',
        'date_of_birth',
        'employment_date',
        'photo',
        'salary',
        'allowances',
        'client_invoice_amount',
        'twilio_number',
        'wise_account',
        'wise_currency',
        'required_work_hours',
        'recording_duration_minutes',
        'sales_rep_id',
        'sales_rep_commission_type',
        'sales_rep_commission_value',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'date_of_birth' => 'date',
            'employment_date' => 'date',
            'salary' => 'decimal:2',
            'allowances' => 'decimal:2',
            'client_invoice_amount' => 'decimal:2',
            'required_work_hours' => 'decimal:2',
            'recording_duration_minutes' => 'decimal:2',
            'sales_rep_commission_value' => 'decimal:2',
        ];
    }

    /**
     * Check if the user is an admin.
     * Checks both is_admin flag (for backward compatibility) and Admin role.
     */
    public function isAdmin(): bool
    {
        // Check is_admin flag for backward compatibility
        if ($this->is_admin === true) {
            return true;
        }

        // Check if user has Admin role (slug = 'admin') for their company
        if ($this->role_id && $this->role) {
            return $this->role->slug === 'admin' && $this->role->company_id === $this->company_id;
        }

        return false;
    }

    /**
     * Get the company that the user belongs to.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the primary role for the user (direct relationship).
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get all roles for the user (many-to-many relationship).
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_role');
    }

    /**
     * Get the department that the user belongs to.
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * External sales rep credited for this employee (commission is stored on the employee).
     */
    public function salesRep()
    {
        return $this->belongsTo(SalesRep::class, 'sales_rep_id');
    }

    /**
     * Get the user's primary role or first role from roles relationship.
     */
    public function getPrimaryRoleAttribute()
    {
        if ($this->role_id && $this->role) {
            return $this->role;
        }

        return $this->roles->first();
    }

    /**
     * Get all permissions for the user through their role(s).
     */
    public function permissions()
    {
        if (! $this->company_id) {
            return collect();
        }

        $permissions = collect();

        // Get permissions from primary role (if role belongs to same company)
        if ($this->role_id && $this->role && $this->role->company_id === $this->company_id) {
            $rolePermissions = $this->role->permissions()
                ->where('company_id', $this->company_id)
                ->get();
            $permissions = $permissions->merge($rolePermissions);
        }

        // Merge permissions from all roles (filtered by company)
        foreach ($this->roles as $role) {
            if ($role->company_id === $this->company_id) {
                $rolePermissions = $role->permissions()
                    ->where('company_id', $this->company_id)
                    ->get();
                $permissions = $permissions->merge($rolePermissions);
            }
        }

        return $permissions->unique('id');
    }

    /**
     * Check if user has a specific permission.
     */
    public function hasPermission(string $permissionSlug): bool
    {
        // Check if user has the permission through their role(s)
        return $this->permissions()
            ->where('slug', $permissionSlug)
            ->where('company_id', $this->company_id)
            ->isNotEmpty();
    }

    /**
     * Check if user has any of the given permissions.
     */
    public function hasAnyPermission(array $permissionSlugs): bool
    {
        foreach ($permissionSlugs as $slug) {
            if ($this->hasPermission($slug)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has all of the given permissions.
     */
    public function hasAllPermissions(array $permissionSlugs): bool
    {
        foreach ($permissionSlugs as $slug) {
            if (! $this->hasPermission($slug)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get user's permissions as array of slugs (cached for performance).
     */
    public function getPermissionSlugs(): array
    {
        return $this->permissions()
            ->where('company_id', $this->company_id)
            ->pluck('slug')
            ->toArray();
    }

    /**
     * Get the clients (companies) the user/employee is assigned to.
     */
    public function clients()
    {
        return $this->belongsToMany(Client::class, 'client_user');
    }

    /**
     * Get the projects the user is assigned to.
     */
    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_user');
    }

    /**
     * Get the tasks assigned to the user.
     */
    public function assignedTasks()
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    /**
     * Get the teams the user is a member of.
     */
    public function teams()
    {
        return $this->belongsToMany(Team::class, 'team_members')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    /**
     * Get the teams the user leads.
     */
    public function ledTeams()
    {
        return $this->hasMany(Team::class, 'leader_id');
    }

    /**
     * Check if user is a team leader of any team.
     */
    public function isTeamLeader(): bool
    {
        return $this->ledTeams()->exists();
    }

    /**
     * Check if user is a leader or co-leader of any team.
     */
    public function isTeamLeaderOrCoLeader(): bool
    {
        if ($this->isTeamLeader()) {
            return true;
        }

        return $this->teams()->wherePivot('role', 'co-leader')->exists();
    }

    /**
     * Get all teams where the user is a leader or co-leader.
     */
    public function managedTeams()
    {
        $ledTeamIds = $this->ledTeams()->pluck('id')->toArray();
        $coLeadTeamIds = $this->teams()->wherePivot('role', 'co-leader')->pluck('teams.id')->toArray();

        return Team::whereIn('id', array_unique(array_merge($ledTeamIds, $coLeadTeamIds)));
    }

    /**
     * Get the leave requests for the user.
     */
    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * Get the leave credits for the user.
     */
    public function leaveCredits()
    {
        return $this->hasMany(LeaveCredit::class);
    }

    /**
     * Get leave requests approved by this user (as team leader/admin).
     */
    public function approvedLeaveRequests()
    {
        return $this->hasMany(LeaveRequest::class, 'approved_by');
    }

    /**
     * Get the employee pay history (Wise payments sent).
     */
    public function payHistory()
    {
        return $this->hasMany(EmployeePayHistory::class);
    }

    /**
     * Get the user's calendar integrations (Google, Outlook).
     */
    public function calendarIntegrations()
    {
        return $this->hasMany(CalendarIntegration::class);
    }

    /**
     * Get the conversations the user participates in.
     */
    public function conversations()
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    /**
     * Get the messages sent by the user.
     */
    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Recorder API tokens issued for desktop/mobile clients.
     */
    public function recorderApiTokens()
    {
        return $this->hasMany(RecorderApiToken::class);
    }
}
