<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RecorderApiToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'company_id',
        'name',
        'token_hash',
        'token_prefix',
        'device_id',
        'platform',
        'expires_at',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public static function generateToken(): string
    {
        return 'rec_'.Str::random(60);
    }

    public static function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public static function tokenPrefix(string $plainToken): string
    {
        return substr($plainToken, 0, 16);
    }

    public static function findByPlainToken(string $plainToken): ?self
    {
        $prefix = self::tokenPrefix($plainToken);
        $hash = self::hashToken($plainToken);

        return self::where('token_prefix', $prefix)
            ->get()
            ->first(function (self $token) use ($hash): bool {
                return hash_equals($token->token_hash, $hash);
            });
    }
}
