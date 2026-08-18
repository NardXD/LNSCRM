<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TwilioPhoneNumber extends Model
{
    protected $fillable = [
        'company_id',
        'phone_number',
        'twilio_sid',
        'friendly_name',
        'capabilities',
        'assigned_user_id',
        'sms_assigned_user_id',
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

    public function smsAssignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sms_assigned_user_id');
    }
}
