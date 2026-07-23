<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'contact_person',
        'email',
        'phone',
        'industry',
        'status',
        'website',
        'address',
        'revenue',
        'stripe_customer_id',
    ];

    protected function casts(): array
    {
        return [
            'revenue' => 'decimal:2',
        ];
    }

    /**
     * Get the company that owns the client.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the contacts for the client.
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
    }

    /**
     * Get the employees (users) assigned to the client.
     */
    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'client_user');
    }

    /**
     * Get the projects for the client.
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Get the notes for the client.
     */
    public function notes(): HasMany
    {
        return $this->hasMany(ClientNote::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get the quotations for the client.
     */
    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    /**
     * Get the contracts for the client.
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * Get the invoices for the client.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get the client users (portal users) for the client.
     */
    public function clientUsers(): HasMany
    {
        return $this->hasMany(ClientUser::class);
    }

    /**
     * Get initials from client name.
     */
    public function getInitialsAttribute(): string
    {
        $words = explode(' ', $this->name);
        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1).substr($words[count($words) - 1], 0, 1));
        }

        return strtoupper(substr($this->name, 0, 2));
    }
}
