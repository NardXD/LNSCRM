<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadScheduledEmail extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'company_id',
        'lead_id',
        'lead_rule_id',
        'inbox_template_id',
        'shared_inbox_id',
        'user_id',
        'send_at',
        'status',
        'error_message',
    ];

    protected $casts = [
        'send_at' => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(LeadRule::class, 'lead_rule_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(InboxTemplate::class, 'inbox_template_id');
    }

    public function inbox(): BelongsTo
    {
        return $this->belongsTo(SharedInbox::class, 'shared_inbox_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
