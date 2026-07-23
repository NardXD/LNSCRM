<?php

namespace App\Support;

class SubdomainUrlBuilder
{
    public static function build(string $subdomain, string $path = '/'): string
    {
        $baseUrl = config('app.url');
        $parsed = parse_url($baseUrl);
        $scheme = $parsed['scheme'] ?? (request()->secure() ? 'https' : 'http');
        $port = request()->getPort();

        if (str_contains($baseUrl, 'localhost')) {
            $host = $subdomain.'.localhost';
        } else {
            $host = $subdomain.'.'.($parsed['host'] ?? 'localhost');
        }

        return ($port && $port != 80 && $port != 443)
            ? "{$scheme}://{$host}:{$port}{$path}"
            : "{$scheme}://{$host}{$path}";
    }
}
