<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
        'company_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the company that owns this role.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the permissions for the role.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }
    
    /**
     * Get the permissions for the role scoped to the role's company.
     */
    public function companyPermissions()
    {
        return $this->permissions()
            ->where('permissions.company_id', $this->company_id);
    }

    /**
     * Get the users that have this role as their primary role (direct relationship).
     */
    public function usersWithRole(): HasMany
    {
        return $this->hasMany(User::class, 'role_id');
    }

    /**
     * Get all users that have this role (many-to-many relationship).
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_role');
    }

    /**
     * Scope a query to only include roles for a specific company.
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}
