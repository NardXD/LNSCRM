<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class InboxTag extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'color',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(InboxConversation::class, 'inbox_conversation_tag')
            ->withTimestamps();
    }
}
