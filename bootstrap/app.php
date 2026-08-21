<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureClientCompanyNotSuspended;
use App\Http\Middleware\EnsureCompanyNotSuspended;
use App\Http\Middleware\FlexApiKeyAuth;
use App\Http\Middleware\IdentifyCompanyBySubdomain;
use App\Http\Middleware\McpApiKeyAuth;
use App\Http\Middleware\RecorderTokenAuth;
use App\Http\Middleware\SetCompanyTimezone;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

require_once __DIR__.'/pdo_mysql_polyfill.php';
require_once __DIR__.'/../app/helpers.php';

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust reverse proxies (ngrok, load balancers) so request URLs match what Twilio signed.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'permission' => CheckPermission::class,
            'company.active' => EnsureCompanyNotSuspended::class,
            'client.company.active' => EnsureClientCompanyNotSuspended::class,
            'mcp.api_key' => McpApiKeyAuth::class,
            'recorder.token' => RecorderTokenAuth::class,
            'flex.api_key' => FlexApiKeyAuth::class,
        ]);
        $middleware->web(prepend: [
            IdentifyCompanyBySubdomain::class,
        ], append: [
            SetCompanyTimezone::class,
        ]);

        // Exclude webhook routes from CSRF protection
        $middleware->validateCsrfTokens(except: [
            'twilio/voice',
            'twilio/dial-action',
            'twilio/status-callback',
            'twilio/client-status',
            'twilio/recording-callback',
            'twilio/sms-webhook',
            'twilio/sms-status',
            'twilio/broadcast-sms-status',
            'webhooks/stripe/*',
            'webhooks/wise/*',
            'webhooks/viber/*',
            'webhooks/whatsapp/*',
            'webhooks/facebook/*',
            'webhooks/flex/*',
            'api/flex/*',
            'flex/screen-pop/*',
            'mcp',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
