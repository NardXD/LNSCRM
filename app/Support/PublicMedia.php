<?php

namespace App\Support;

class PublicMedia
{
    /**
     * Public URL for a file on the public disk, served by Laravel (not the storage symlink).
     */
    public static function url(?string $path): ?string
    {
        $path = self::normalize($path);

        if ($path === null) {
            return null;
        }

        return url('/media/'.$path);
    }

    public static function normalize(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');

        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        return $path;
    }
}
