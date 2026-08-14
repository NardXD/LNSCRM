<?php

use App\Support\PublicMedia;

if (! function_exists('public_media_url')) {
    function public_media_url(?string $path): ?string
    {
        return PublicMedia::url($path);
    }
}
