<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledInboxReply extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const TYPE_REPLY = 'reply';

    public const TYPE_COMPOSE = 'compose';

    protected $fillable = [
        'inbox_conversation_id',
        'user_id',
        'shared_inbox_id',
        'type',
        'to_emails',
        'cc_emails',
        'subject',
        'body_html',
        'body_text',
        'attachments',
        'send_at',
        'archive_after',
        'status',
        'error_message',
        'sent_message_id',
        'sent_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'send_at' => 'datetime',
        'sent_at' => 'datetime',
        'archive_after' => 'boolean',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(InboxConversation::class, 'inbox_conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function inbox(): BelongsTo
    {
        return $this->belongsTo(SharedInbox::class, 'shared_inbox_id');
    }

    public function sentMessage(): BelongsTo
    {
        return $this->belongsTo(InboxMessage::class, 'sent_message_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCompose(): bool
    {
        return $this->type === self::TYPE_COMPOSE;
    }
}
