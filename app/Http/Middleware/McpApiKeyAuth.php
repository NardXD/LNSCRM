<?php

namespace App\Http\Middleware;

use App\Models\McpApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class McpApiKeyAuth
{
    /**
     * Handle an incoming request. Validate X-API-Key and attach company to request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('X-API-Key') ?? $request->header('x-api-key');

        if (empty($key)) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Missing X-API-Key header',
            ], 401);
        }

        $apiKey = McpApiKey::findByKey($key);

        if (! $apiKey) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid API key',
            ], 401);
        }

        $request->attributes->set('mcp_company_id', $apiKey->company_id);
        $request->attributes->set('mcp_api_key', $apiKey);

        return $next($request);
    }
}
