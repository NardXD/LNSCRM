<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InboxConversation extends Model
{
    protected $fillable = [
        'company_id',
        'shared_inbox_id',
        'folder',
        'external_conversation_id',
        'subject',
        'snippet',
        'from_name',
        'from_email',
        'status',
        'assigned_to',
        'lead_id',
        'is_read',
        'message_count',
        'last_message_at',
        'reopen_at',
        'reopened_from',
        'merged_into_id',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'last_message_at' => 'datetime',
        'reopen_at' => 'datetime',
    ];

    /**
     * Move a held (archived or snoozed) thread back to Open and remember which hold it left.
     */
    public function applyOpenFromHold(): void
    {
        if ($this->status === 'archived') {
            $this->reopened_from = $this->reopen_at ? 'snoozed' : 'archived';
        }
        $this->status = 'open';
        $this->folder = 'inbox';
        $this->reopen_at = null;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function inbox(): BelongsTo
    {
        return $this->belongsTo(SharedInbox::class, 'shared_inbox_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }

    public function mergedConversations(): HasMany
    {
        return $this->hasMany(self::class, 'merged_into_id')->orderBy('last_message_at');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeNotMerged(Builder $query): Builder
    {
        return $query->whereNull('merged_into_id');
    }

    public function mergeRoot(): self
    {
        $current = $this;
        $guard = 0;
        while ($current->merged_into_id && $guard++ < 25) {
            $next = self::query()->find($current->merged_into_id);
            if (! $next) {
                break;
            }
            $current = $next;
        }

        return $current;
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(InboxMessage::class)->orderBy('sent_at');
    }

    /**
     * Same Outlook conversation across folders (inbox + sent, etc.).
     *
     * @return \Illuminate\Support\Collection<int, static>
     */
    public function relatedFolderConversations()
    {
        if (! $this->external_conversation_id) {
            return self::query()->whereKey($this->id)->get();
        }

        return self::query()
            ->where('shared_inbox_id', $this->shared_inbox_id)
            ->where('external_conversation_id', $this->external_conversation_id)
            ->where(function ($q) {
                $q->whereNull('merged_into_id')->orWhere('id', $this->id);
            })
            ->orderBy('id')
            ->get();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(InboxConversationComment::class)->orderBy('created_at');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(InboxConversationActivity::class)->orderBy('created_at');
    }

    public function scheduledReplies(): HasMany
    {
        return $this->hasMany(ScheduledInboxReply::class)->orderBy('send_at');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(InboxTag::class, 'inbox_conversation_tag')
            ->withTimestamps();
    }

    /**
     * Lead labels applied directly to this conversation (inbox UI or Front import)
     * before it has a matching Lead record. They graduate onto the lead when
     * the thread is saved or attached.
     */
    public function leadLabels(): BelongsToMany
    {
        return $this->belongsToMany(LeadLabel::class, 'inbox_conversation_lead_label')
            ->withTimestamps();
    }

    public function userReads(): HasMany
    {
        return $this->hasMany(InboxConversationUserRead::class, 'inbox_conversation_id');
    }

    /**
     * Teammates invited or auto-subscribed to this conversation (Front-style participants).
     */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'inbox_conversation_followers')
            ->withPivot(['is_subscribed'])
            ->withTimestamps()
            ->orderBy('users.name');
    }

    public function followerRows(): HasMany
    {
        return $this->hasMany(InboxConversationFollower::class, 'inbox_conversation_id');
    }
}
