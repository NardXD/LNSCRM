<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BroadcastCampaign extends Model
{
    public const TYPE_SMS = 'sms';

    public const TYPE_EMAIL = 'email';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'company_id',
        'created_by',
        'name',
        'type',
        'status',
        'sender_label',
        'from_number',
        'shared_inbox_id',
        'subject',
        'body',
        'attachments',
        'recipient_count',
        'sent_count',
        'delivered_count',
        'failed_count',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'attachments' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function inbox(): BelongsTo
    {
        return $this->belongsTo(SharedInbox::class, 'shared_inbox_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(BroadcastCampaignRecipient::class);
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $query = $this->where($field ?? $this->getRouteKeyName(), $value);

        if ($user = auth()->user()) {
            $query->where('company_id', $user->company_id);
        }

        return $query->firstOrFail();
    }

    public function isSms(): bool
    {
        return $this->type === self::TYPE_SMS;
    }

    public function isEmail(): bool
    {
        return $this->type === self::TYPE_EMAIL;
    }

    public function refreshCounts(): void
    {
        $sent = $this->recipients()
            ->whereIn('status', [
                BroadcastCampaignRecipient::STATUS_SENT,
                BroadcastCampaignRecipient::STATUS_DELIVERED,
            ])
            ->count();
        $delivered = $this->recipients()
            ->where('status', BroadcastCampaignRecipient::STATUS_DELIVERED)
            ->count();
        $failed = $this->recipients()
            ->whereIn('status', [
                BroadcastCampaignRecipient::STATUS_FAILED,
                BroadcastCampaignRecipient::STATUS_UNDELIVERED,
            ])
            ->count();
        $pending = $this->recipients()
            ->whereIn('status', [
                BroadcastCampaignRecipient::STATUS_PENDING,
                BroadcastCampaignRecipient::STATUS_SENDING,
            ])
            ->count();

        $status = $this->status;
        if ($pending === 0) {
            $status = match (true) {
                $failed === 0 && $sent > 0 => self::STATUS_SENT,
                $sent === 0 => self::STATUS_FAILED,
                default => self::STATUS_PARTIAL,
            };
        }

        $this->forceFill([
            'sent_count' => $sent,
            'delivered_count' => $this->isSms() ? $delivered : $sent,
            'failed_count' => $failed,
            'status' => $status,
        ])->save();
    }
}
