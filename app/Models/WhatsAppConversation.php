<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppConversation extends Model
{
    protected $table = 'whatsapp_conversations';

    protected $fillable = [
        'company_id',
        'wa_id',
        'name',
        'profile_name',
        'phone',
        'is_subscribed',
        'unread_count',
        'last_message_preview',
        'last_message_at',
        'window_expires_at',
    ];

    protected $casts = [
        'is_subscribed' => 'boolean',
        'last_message_at' => 'datetime',
        'window_expires_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'whatsapp_conversation_id');
    }

    public function isWithinMessagingWindow(): bool
    {
        return $this->window_expires_at && $this->window_expires_at->isFuture();
    }
}
