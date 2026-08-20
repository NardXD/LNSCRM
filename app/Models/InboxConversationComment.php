<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InboxConversationComment extends Model
{
    protected $fillable = [
        'inbox_conversation_id',
        'source_conversation_id',
        'user_id',
        'body_html',
        'body_text',
        'mentioned_user_ids',
        'attachments',
    ];

    protected $casts = [
        'mentioned_user_ids' => 'array',
        'attachments' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(InboxConversation::class, 'inbox_conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
