<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadActivity extends Model
{
    public const CREATED = 'created';
    public const ASSIGNED = 'assigned';
    public const UNASSIGNED = 'unassigned';
    public const REASSIGNED = 'reassigned';
    public const STATUS_CHANGED = 'status_changed';
    public const UPDATED = 'updated';
    public const IDENTITY_ADDED = 'identity_added';
    public const IDENTITY_REMOVED = 'identity_removed';
    public const LABEL_ADDED = 'label_added';
    public const LABEL_REMOVED = 'label_removed';
    public const NOTE_ADDED = 'note_added';
    public const NOTE_REMOVED = 'note_removed';
    public const SNOOZED = 'snoozed';
    public const REOPENED = 'reopened';
    public const INBOX_ATTACHED = 'inbox_attached';
    public const INBOX_DETACHED = 'inbox_detached';

    protected $fillable = [
        'lead_id',
        'user_id',
        'action',
        'summary',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
