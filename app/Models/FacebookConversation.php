<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacebookConversation extends Model
{
    public const PLACEHOLDER_NAMES = [
        'messenger user',
        'instagram user',
        'facebook user',
    ];

    protected $fillable = [
        'company_id',
        'channel',
        'peer_id',
        'name',
        'username',
        'profile_pic',
        'unread_count',
        'last_message_preview',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public static function isPlaceholderName(?string $name): bool
    {
        return in_array(strtolower(trim((string) $name)), self::PLACEHOLDER_NAMES, true);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(FacebookMessage::class);
    }

    public function userReads(): HasMany
    {
        return $this->hasMany(FacebookConversationUserRead::class, 'facebook_conversation_id');
    }

    /**
     * Lead labels applied directly to this conversation, independent of any
     * matched Lead — Facebook conversations have no persisted lead_id, so this
     * is the only place a label can live before/without a lead.
     */
    public function leadLabels(): BelongsToMany
    {
        return $this->belongsToMany(LeadLabel::class, 'facebook_conversation_lead_label')
            ->withTimestamps();
    }
}
