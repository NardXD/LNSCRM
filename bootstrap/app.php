<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

require_once __DIR__.'/pdo_mysql_polyfill.php';

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'company.active' => \App\Http\Middleware\EnsureCompanyNotSuspended::class,
            'client.company.active' => \App\Http\Middleware\EnsureClientCompanyNotSuspended::class,
            'mcp.api_key' => \App\Http\Middleware\McpApiKeyAuth::class,
            'recorder.token' => \App\Http\Middleware\RecorderTokenAuth::class,
        ]);
        $middleware->web(prepend: [
            \App\Http\Middleware\IdentifyCompanyBySubdomain::class,
        ], append: [
            \App\Http\Middleware\SetCompanyTimezone::class,
        ]);

        // Exclude webhook routes from CSRF protection
        $middleware->validateCsrfTokens(except: [
            'twilio/voice',
            'twilio/status-callback',
            'twilio/sms-webhook',
            'twilio/sms-status',
            'webhooks/stripe/*',
            'webhooks/wise/*',
            'webhooks/viber/*',
            'mcp',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
