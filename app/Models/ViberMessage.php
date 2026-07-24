<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ViberMessage extends Model
{
    protected $fillable = [
        'company_id',
        'viber_conversation_id',
        'user_id',
        'direction',
        'message_token',
        'type',
        'text',
        'media_url',
        'thumbnail_url',
        'file_name',
        'file_size',
        'duration',
        'latitude',
        'longitude',
        'contact_name',
        'contact_phone',
        'sticker_id',
        'status',
        'raw_payload',
        'sent_at',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'sent_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ViberConversation::class, 'viber_conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
