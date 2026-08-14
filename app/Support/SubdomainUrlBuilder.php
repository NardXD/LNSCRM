<?php

namespace App\Support;

class SubdomainUrlBuilder
{
    /**
     * Build an app URL on the main domain. Subdomains are not used.
     */
    public static function build(string $subdomain, string $path = '/'): string
    {
        return url($path);
    }
}
