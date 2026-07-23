<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'user_id',
        'note',
    ];

    /**
     * Get the client that owns the note.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the user who created the note.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
