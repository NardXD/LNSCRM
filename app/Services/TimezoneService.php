<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class TimezoneService
{
    /**
     * Get the company timezone for the authenticated user.
     */
    public static function getCompanyTimezone(): string
    {
        $user = Auth::user();
        if ($user && $user->company_id && $user->company) {
            return $user->company->timezone ?? 'America/New_York';
        }
        return 'America/New_York'; // Default fallback
    }

    /**
     * Set the application timezone based on the company.
     */
    public static function setApplicationTimezone(): void
    {
        $timezone = self::getCompanyTimezone();
        date_default_timezone_set($timezone);
        Carbon::setLocale('en');
    }

    /**
     * Convert a date/time to company timezone.
     */
    public static function toCompanyTimezone($dateTime, $fromTimezone = null): Carbon
    {
        $companyTimezone = self::getCompanyTimezone();
        
        if ($dateTime instanceof Carbon) {
            return $dateTime->setTimezone($companyTimezone);
        }
        
        $carbon = Carbon::parse($dateTime, $fromTimezone);
        return $carbon->setTimezone($companyTimezone);
    }

    /**
     * Get current date/time in company timezone.
     */
    public static function now(): Carbon
    {
        return Carbon::now(self::getCompanyTimezone());
    }

    /**
     * Get today's date in company timezone.
     */
    public static function today(): Carbon
    {
        return Carbon::today(self::getCompanyTimezone());
    }

    /**
     * Format a date/time using company timezone.
     */
    public static function format($dateTime, string $format = 'Y-m-d H:i:s'): string
    {
        return self::toCompanyTimezone($dateTime)->format($format);
    }

    /**
     * Convert a UTC / offset-aware external timestamp into the app timezone
     * before persisting. Eloquent stores naive wall-clock times, so leaving a
     * Carbon instance in UTC writes the UTC clock and then reads it back as
     * local time (8 hours off in Asia/Manila).
     */
    public static function fromExternal(mixed $value): Carbon
    {
        if ($value === null || $value === '') {
            return now();
        }

        $carbon = $value instanceof Carbon
            ? $value->copy()
            : ($value instanceof \DateTimeInterface
                ? Carbon::instance($value)
                : Carbon::parse($value));

        return $carbon->timezone(config('app.timezone'));
    }
}
