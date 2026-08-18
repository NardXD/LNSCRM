<?php

namespace App\Models;

use App\Services\TwilioCompanyService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadIdentity extends Model
{
    public const TYPE_PHONE = 'phone';

    public const TYPE_EMAIL = 'email';

    public const TYPE_FACEBOOK = 'facebook';

    public const TYPE_INSTAGRAM = 'instagram';

    public const TYPES = [
        self::TYPE_PHONE,
        self::TYPE_EMAIL,
        self::TYPE_FACEBOOK,
        self::TYPE_INSTAGRAM,
    ];

    protected $fillable = [
        'lead_id',
        'type',
        'value',
        'normalized_value',
        'label',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public static function normalize(string $type, string $value): string
    {
        $value = trim($value);

        return match ($type) {
            self::TYPE_PHONE => app(TwilioCompanyService::class)->normalizePhone($value),
            self::TYPE_EMAIL => strtolower($value),
            default => strtolower($value),
        };
    }
}
