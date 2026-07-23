<?php

namespace App\Http\Middleware;

use App\Models\RecorderApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecorderTokenAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json([
                'success' => false,
                'message' => 'Missing bearer token.',
            ], 401);
        }

        $apiToken = RecorderApiToken::findByPlainToken($token);

        if (! $apiToken || ! $apiToken->user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid recorder token.',
            ], 401);
        }

        if ($apiToken->expires_at && $apiToken->expires_at->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Recorder token has expired.',
            ], 401);
        }

        $apiToken->forceFill(['last_used_at' => now()])->save();

        $request->attributes->set('recorder_token', $apiToken);
        $request->attributes->set('recorder_user', $apiToken->user);
        $request->attributes->set('recorder_company_id', $apiToken->company_id);

        return $next($request);
    }
}
