<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'client_id',
        'client_name',
        'ticket_number',
        'subject',
        'description',
        'assigned_to',
        'priority',
        'status',
        'category',
        'sla',
        'image_path',
        'first_response_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'first_response_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * Get the company that owns the ticket.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the client associated with the ticket.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the user assigned to the ticket.
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the comments for the ticket.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class)->orderBy('created_at', 'asc');
    }
}
