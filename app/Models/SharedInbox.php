<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SharedInbox extends Model
{
    public const TYPE_PERSONAL = 'personal';

    public const TYPE_SHARED = 'shared';

    protected $fillable = [
        'company_id',
        'outlook_mail_account_id',
        'created_by',
        'name',
        'email',
        'type',
        'color',
        'external_mailbox',
        'is_active',
        'last_synced_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(OutlookMailAccount::class, 'outlook_mail_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'shared_inbox_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function memberRows(): HasMany
    {
        return $this->hasMany(SharedInboxMember::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(InboxConversation::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(InboxRule::class);
    }

    public function isPersonal(): bool
    {
        return $this->type === self::TYPE_PERSONAL;
    }

    public function userCanAccess(User $user): bool
    {
        if ($user->company_id !== $this->company_id) {
            return false;
        }

        if ($this->isPersonal()) {
            return (int) $this->created_by === (int) $user->id;
        }

        return $this->members()->where('users.id', $user->id)->exists();
    }

    public function userIsAdmin(User $user): bool
    {
        if ($this->isPersonal()) {
            return (int) $this->created_by === (int) $user->id;
        }

        return $this->members()
            ->where('users.id', $user->id)
            ->wherePivot('role', 'admin')
            ->exists();
    }
}
