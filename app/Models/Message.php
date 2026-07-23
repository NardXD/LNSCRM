<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Message extends Model
{
    protected static function booted(): void
    {
        static::deleting(function (Message $message) {
            if ($message->attachment_path && Storage::disk('public')->exists($message->attachment_path)) {
                Storage::disk('public')->delete($message->attachment_path);
            }
        });
    }
    protected $fillable = [
        'conversation_id',
        'user_id',
        'body',
        'attachment_path',
        'attachment_name',
        'attachment_type',
    ];

    /**
     * Get the conversation the message belongs to.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the user who sent the message.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
