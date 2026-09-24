<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class InboxTag extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'color',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(InboxConversation::class, 'inbox_conversation_tag')
            ->withTimestamps();
    }

    public function discussionConversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_inbox_tag', 'inbox_tag_id', 'conversation_id')
            ->withTimestamps();
    }
}
