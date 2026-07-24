<?php

namespace App\Models;

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
        'is_read',
        'message_count',
        'last_message_at',
        'reopen_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'last_message_at' => 'datetime',
        'reopen_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function inbox(): BelongsTo
    {
        return $this->belongsTo(SharedInbox::class, 'shared_inbox_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(InboxMessage::class)->orderBy('sent_at');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(InboxConversationComment::class)->orderBy('created_at');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(InboxConversationActivity::class)->orderBy('created_at');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(InboxTag::class, 'inbox_conversation_tag')
            ->withTimestamps();
    }
}
