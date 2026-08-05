<?php

namespace App\Http\Middleware;

use App\Models\TwilioFlexIntegration;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FlexApiKeyAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('X-API-Key')
            ?? $request->header('x-api-key')
            ?? $request->query('api_key');

        if (empty($key) || ! is_string($key)) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Missing X-API-Key header',
            ], 401);
        }

        $integration = TwilioFlexIntegration::findByApiKey($key);

        if (! $integration) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid Flex API key',
            ], 401);
        }

        $request->attributes->set('flex_company_id', $integration->company_id);
        $request->attributes->set('flex_integration', $integration);

        return $next($request);
    }
}
