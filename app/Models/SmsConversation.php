<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmsConversation extends Model
{
    protected $fillable = [
        'company_id',
        'peer_phone',
        'our_number',
        'name',
        'unread_count',
        'last_message_preview',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SmsMessage::class, 'sms_conversation_id');
    }
}
