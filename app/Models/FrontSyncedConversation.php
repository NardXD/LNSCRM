<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FrontSyncedConversation extends Model
{
    protected $fillable = [
        'company_id',
        'front_conversation_id',
        'front_updated_at',
    ];

    protected $casts = [
        'front_updated_at' => 'datetime',
    ];
}
