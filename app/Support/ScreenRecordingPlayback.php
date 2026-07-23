<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ScreenRecordingPlayback
{
    public static function mimeTypeForPath(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'ogg' => 'video/ogg',
            'mov' => 'video/quicktime',
            default => 'video/webm',
        };
    }

    public static function fileResponse(string $absolutePath, string $storagePath): BinaryFileResponse
    {
        return response()->file($absolutePath, [
            'Content-Type' => self::mimeTypeForPath($storagePath),
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
