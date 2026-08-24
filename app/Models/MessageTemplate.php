<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageTemplate extends Model
{
    public const CHANNEL_SMS = 'sms';

    public const CHANNEL_FACEBOOK = 'facebook';

    public const CHANNEL_VIBER = 'viber';

    public const CHANNEL_WHATSAPP = 'whatsapp';

    protected $fillable = [
        'company_id',
        'created_by',
        'channel',
        'name',
        'body_text',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return array<int, array<string, mixed>> */
    public static function listForCompany(int $companyId, string $channel): array
    {
        return static::query()
            ->where('company_id', $companyId)
            ->where('channel', $channel)
            ->orderBy('name')
            ->get()
            ->map(fn (self $template) => [
                'id' => $template->id,
                'name' => $template->name,
                'body' => $template->body_text,
                'body_text' => $template->body_text,
            ])
            ->values()
            ->all();
    }
}
