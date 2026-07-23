<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
        'group',
    ];

    /**
     * Get a setting value by key. Optionally scope by group.
     */
    public static function getValue(string $key, $default = null, ?string $group = null)
    {
        $query = self::where('key', $key);
        if ($group !== null) {
            $query->where('group', $group);
        }
        $setting = $query->first();

        if (! $setting) {
            return $default;
        }

        return match ($setting->type) {
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $setting->value,
            'json' => json_decode($setting->value, true),
            default => $setting->value,
        };
    }

    /**
     * Set a setting value by key and group.
     */
    public static function setValue(string $key, $value, string $type = 'string', ?string $group = 'general', ?string $description = null): void
    {
        // Use both key and group to find or create the setting
        // This allows the same key to exist for different groups (companies)
        $setting = self::firstOrNew([
            'key' => $key,
            'group' => $group,
        ]);

        $setting->value = is_array($value) ? json_encode($value) : $value;
        $setting->type = $type;
        $setting->group = $group;
        $setting->description = $description ?? $setting->description;

        $setting->save();
    }
}
