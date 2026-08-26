<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class Conversation extends Model
{
    protected static function booted(): void
    {
        static::deleting(function (Conversation $conversation) {
            // Delete message attachments (Message model's deleting event handles each)
            $conversation->messages()->each(fn ($m) => $m->delete());

            // Delete group/conversation avatar photo
            if ($conversation->photo && Storage::disk('public')->exists($conversation->photo)) {
                Storage::disk('public')->delete($conversation->photo);
            }
        });
    }

    protected $fillable = [
        'company_id',
        'type',
        'name',
        'photo',
        'created_by',
    ];

    /**
     * Get the company that owns the conversation.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user who created the conversation.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the participants in the conversation.
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->using(ConversationParticipant::class)
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    /**
     * Get the messages in the conversation.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    /**
     * Get the latest message in the conversation (for preview).
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany('created_at');
    }

    /**
     * Scope to filter conversations by company.
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}
