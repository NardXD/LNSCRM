<?php
/**
 * @deprecated Legacy entry point — forwards to Laravel /infobip/voice.
 * Update Infobip portal to use: https://your-domain/infobip/voice
 */

declare(strict_types=1);

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$uri = '/infobip/voice';
if (! empty($_SERVER['QUERY_STRING'])) {
    $uri .= '?'.$_SERVER['QUERY_STRING'];
}

$request = Request::create(
    $uri,
    $_SERVER['REQUEST_METHOD'] ?? 'POST',
    array_merge($_GET ?? [], $_POST ?? []),
    $_COOKIE ?? [],
    $_FILES ?? [],
    $_SERVER
);

$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
