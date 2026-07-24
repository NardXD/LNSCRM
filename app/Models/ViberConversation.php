<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ViberConversation extends Model
{
    protected $fillable = [
        'company_id',
        'viber_user_id',
        'name',
        'avatar',
        'phone',
        'language',
        'country',
        'is_subscribed',
        'unread_count',
        'last_message_preview',
        'last_message_at',
    ];

    protected $casts = [
        'is_subscribed' => 'boolean',
        'last_message_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ViberMessage::class);
    }
}
