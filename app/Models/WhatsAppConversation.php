<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'extracted_email',
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

    /**
     * Lead labels applied directly to this conversation, independent of any
     * matched Lead — mirrors FacebookConversation::leadLabels().
     *
     * Foreign pivot key is explicit because Laravel's snake_case guesser splits
     * "WhatsApp" into "whats_app" (capital A mid-word), which doesn't match the
     * actual "whatsapp_conversation_id" column on the pivot table.
     */
    public function leadLabels(): BelongsToMany
    {
        return $this->belongsToMany(LeadLabel::class, 'whatsapp_conversation_lead_label', 'whatsapp_conversation_id', 'lead_label_id')
            ->withTimestamps();
    }

    public function isWithinMessagingWindow(): bool
    {
        return $this->window_expires_at && $this->window_expires_at->isFuture();
    }
}
