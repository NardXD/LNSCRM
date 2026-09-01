<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class Facilities
{
    public const CACHE_KEY = 'storeganise.sites.directory';

    public static function configured(): array
    {
        return config('storeganise.sites', []);
    }

    public static function all(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        return is_array($cached) && $cached !== [] ? $cached : self::configured();
    }

    public static function codes(): array
    {
        return array_keys(self::all());
    }

    public static function name(?string $code): string
    {
        if (! $code) {
            return '';
        }

        return (string) data_get(self::all(), "{$code}.name", $code);
    }

    public static function siteCode(?string $localCode): string
    {
        if (! $localCode) {
            return '';
        }

        return (string) data_get(self::all(), "{$localCode}.code", $localCode);
    }

    public static function label(?string $code): string
    {
        $name = self::name($code);

        if ($name === '' || $code === null) {
            return '';
        }

        return "{$name} ({$code})";
    }

    public static function discountOptions(string $code): array
    {
        $discounts = config('storeganise.discounts');

        return $discounts[$code] ?? $discounts['default'] ?? [];
    }

    public static function prefersPushRate(string $code): bool
    {
        return in_array($code, ['L001', 'L006'], true);
    }

    public static function banking(string $code): array
    {
        return config("storeganise.banking.{$code}", [
            'facility' => self::name($code),
            'city' => '',
            'address' => '',
            'bank_name' => '',
            'branch' => '',
            'account_type' => '',
            'account_number' => '',
            'account_name' => '',
            'viber' => '',
        ]);
    }

    /**
     * Resolve a Storeganise site id or code to the local facility code (e.g. L001).
     */
    public static function localCodeForSite(?string $siteIdOrCode): string
    {
        $siteIdOrCode = trim((string) $siteIdOrCode);
        if ($siteIdOrCode === '') {
            return '';
        }

        foreach (self::configured() as $localCode => $site) {
            if ($localCode === $siteIdOrCode || (string) ($site['code'] ?? '') === $siteIdOrCode) {
                return $localCode;
            }
        }

        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            foreach ($cached as $localCode => $site) {
                if (! is_array($site)) {
                    continue;
                }
                if (($site['id'] ?? '') === $siteIdOrCode || ($site['code'] ?? '') === $siteIdOrCode || $localCode === $siteIdOrCode) {
                    return (string) ($site['code'] ?? $localCode);
                }
            }
        }

        return $siteIdOrCode;
    }

    /**
     * Human-readable facility label for a Storeganise site id or code.
     *
     * @param  array<string, array{id?: string, code?: string, name?: string}>  $directory
     */
    public static function displayLabelForSite(?string $siteIdOrCode, array $directory = []): string
    {
        $siteIdOrCode = trim((string) $siteIdOrCode);
        if ($siteIdOrCode === '') {
            return '';
        }

        $site = self::findSiteInDirectory($siteIdOrCode, $directory);
        if ($site !== null) {
            return self::formatSiteLabel($site);
        }

        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached) && $cached !== []) {
            $site = self::findSiteInDirectory($siteIdOrCode, $cached);
            if ($site !== null) {
                return self::formatSiteLabel($site);
            }
        }

        foreach (self::configured() as $localCode => $configured) {
            if ($localCode === $siteIdOrCode || (string) ($configured['code'] ?? '') === $siteIdOrCode) {
                return self::label($localCode);
            }
        }

        return $siteIdOrCode;
    }

    /**
     * @param  array<string, array{id?: string, code?: string, name?: string}>  $directory
     * @return array{id?: string, code?: string, name?: string}|null
     */
    protected static function findSiteInDirectory(string $siteIdOrCode, array $directory): ?array
    {
        foreach ($directory as $key => $site) {
            if (! is_array($site)) {
                continue;
            }

            $id = (string) ($site['id'] ?? '');
            $code = (string) ($site['code'] ?? $key);

            if ($id === $siteIdOrCode || $code === $siteIdOrCode || (string) $key === $siteIdOrCode) {
                return $site;
            }
        }

        return null;
    }

    /**
     * @param  array{id?: string, code?: string, name?: string}  $site
     */
    protected static function formatSiteLabel(array $site): string
    {
        $name = trim((string) ($site['name'] ?? ''));
        $code = trim((string) ($site['code'] ?? ''));

        if ($name !== '' && $code !== '' && strcasecmp($name, $code) !== 0) {
            return "{$name} ({$code})";
        }

        if ($name !== '') {
            return $name;
        }

        if ($code !== '') {
            return $code;
        }

        return (string) ($site['id'] ?? 'Facility');
    }
}
