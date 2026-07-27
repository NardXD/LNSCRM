<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacebookMessage extends Model
{
    protected $fillable = [
        'company_id',
        'facebook_conversation_id',
        'user_id',
        'direction',
        'mid',
        'type',
        'text',
        'media_url',
        'mime_type',
        'file_name',
        'file_size',
        'status',
        'raw_payload',
        'sent_at',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'sent_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(FacebookConversation::class, 'facebook_conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
