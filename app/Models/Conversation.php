<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class Conversation extends Model
{
    public const KIND_CHAT = 'chat';

    public const KIND_DISCUSSION = 'discussion';

    public const STATUS_OPEN = 'open';

    public const STATUS_ARCHIVED = 'archived';

    protected static function booted(): void
    {
        static::deleting(function (Conversation $conversation) {
            // Delete message attachments (Message model's deleting event handles each)
            $conversation->messages()->each(fn ($m) => $m->delete());

            // Delete group/conversation avatar photo
            if ($conversation->photo && Storage::disk('public')->exists($conversation->photo)) {
                Storage::disk('public')->delete($conversation->photo);
            }
        });
    }

    protected $fillable = [
        'company_id',
        'type',
        'kind',
        'status',
        'name',
        'photo',
        'created_by',
        'assigned_to',
        'reopen_at',
        'shared_inbox_id',
        'moved_to_shared_at',
        'front_conversation_id',
    ];

    protected $casts = [
        'reopen_at' => 'datetime',
        'moved_to_shared_at' => 'datetime',
    ];

    /**
     * Get the company that owns the conversation.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user who created the conversation.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function sharedInbox(): BelongsTo
    {
        return $this->belongsTo(SharedInbox::class, 'shared_inbox_id');
    }

    /**
     * Get the participants in the conversation.
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->using(ConversationParticipant::class)
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(InboxTag::class, 'conversation_inbox_tag', 'conversation_id', 'inbox_tag_id')
            ->withTimestamps();
    }

    /**
     * Get the messages in the conversation.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    /**
     * Get the latest message in the conversation (for preview).
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany('created_at');
    }

    public function isDiscussion(): bool
    {
        return ($this->kind ?? self::KIND_CHAT) === self::KIND_DISCUSSION;
    }

    public function applySnoozeReopenIfDue(): bool
    {
        if (! $this->reopen_at || $this->reopen_at->isFuture()) {
            return false;
        }

        $this->status = self::STATUS_OPEN;
        $this->reopen_at = null;
        $this->save();

        return true;
    }

    /**
     * Scope to filter conversations by company.
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeDiscussions($query)
    {
        return $query->where('kind', self::KIND_DISCUSSION);
    }

    public function scopeChats($query)
    {
        return $query->where(function ($q) {
            $q->where('kind', self::KIND_CHAT)
                ->orWhereNull('kind');
        });
    }
}
