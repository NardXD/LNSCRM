<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TwilioFlexIntegration extends Model
{
    protected $fillable = [
        'company_id',
        'account_sid',
        'auth_token',
        'app_sid',
        'api_key',
        'api_secret',
        'workspace_sid',
        'workflow_sid',
        'webhook_key',
        'api_key_prefix',
        'api_key_hash',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function hasPluginApiKey(): bool
    {
        return filled($this->api_key_hash) && filled($this->api_key_prefix);
    }

    /** @deprecated Use hasPluginApiKey() */
    public function hasApiKey(): bool
    {
        return $this->hasPluginApiKey();
    }

    public static function generateApiKey(): string
    {
        return 'flex_'.Str::random(48);
    }

    public static function hashApiKey(string $plainKey): string
    {
        return hash('sha256', $plainKey);
    }

    public static function apiKeyPrefix(string $plainKey): string
    {
        return substr($plainKey, 0, 12);
    }

    public static function findByApiKey(string $plainKey): ?self
    {
        $prefix = self::apiKeyPrefix($plainKey);
        $hash = self::hashApiKey($plainKey);

        return self::query()
            ->where('api_key_prefix', $prefix)
            ->where('is_active', true)
            ->get()
            ->first(fn (self $row) => hash_equals((string) $row->api_key_hash, $hash));
    }

    public function setPluginApiKey(string $plainKey): void
    {
        $this->api_key_prefix = self::apiKeyPrefix($plainKey);
        $this->api_key_hash = self::hashApiKey($plainKey);
    }

    /** @deprecated Use setPluginApiKey() */
    public function setApiKey(string $plainKey): void
    {
        $this->setPluginApiKey($plainKey);
    }
}
