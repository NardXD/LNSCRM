<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InfobipPhoneNumber extends Model
{
    protected $fillable = [
        'company_id',
        'phone_number',
        'infobip_number_id',
        'friendly_name',
        'capabilities',
        'assigned_user_id',
    ];

    protected function casts(): array
    {
        return [
            'capabilities' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
