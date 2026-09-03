<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacebookConversationUserRead extends Model
{
    protected $table = 'facebook_conversation_user_reads';

    protected $fillable = [
        'facebook_conversation_id',
        'user_id',
        'last_read_at',
        'is_read',
    ];

    protected $casts = [
        'last_read_at' => 'datetime',
        'is_read' => 'boolean',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(FacebookConversation::class, 'facebook_conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
