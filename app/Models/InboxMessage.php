<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InboxMessage extends Model
{
    protected $fillable = [
        'inbox_conversation_id',
        'source_conversation_id',
        'external_message_id',
        'direction',
        'from_name',
        'from_email',
        'to_emails',
        'cc_emails',
        'reply_to_emails',
        'subject',
        'body_html',
        'body_text',
        'attachments',
        'is_read',
        'sent_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'is_read' => 'boolean',
        'sent_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(InboxConversation::class, 'inbox_conversation_id');
    }
}
