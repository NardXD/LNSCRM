<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class McpApiKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'key_hash',
        'key_prefix',
        'can_write',
        'allowed_tools',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'can_write' => 'boolean',
            'allowed_tools' => 'array',
        ];
    }

    /**
     * Determine whether this key may call the given tool.
     * A null/empty allow-list means every tool is permitted.
     */
    public function allowsTool(string $tool): bool
    {
        $allowed = $this->allowed_tools;

        if (empty($allowed)) {
            return true;
        }

        return in_array($tool, $allowed, true);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Generate a new API key. Returns the plaintext key (only shown once).
     */
    public static function generateKey(): string
    {
        return 'mcp_'.Str::random(48);
    }

    /**
     * Hash the plaintext key for storage.
     */
    public static function hashKey(string $plainKey): string
    {
        return hash('sha256', $plainKey);
    }

    /**
     * Get the prefix (first 12 chars) for fast lookup.
     */
    public static function getKeyPrefix(string $plainKey): string
    {
        return substr($plainKey, 0, 12);
    }

    /**
     * Find a valid API key by the provided plaintext key.
     */
    public static function findByKey(string $plainKey): ?self
    {
        $prefix = self::getKeyPrefix($plainKey);
        $hash = self::hashKey($plainKey);

        return self::where('key_prefix', $prefix)
            ->get()
            ->first(fn ($key) => hash_equals($key->key_hash, $hash));
    }
}
