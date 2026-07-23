<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureClientCompanyNotSuspended
{
    /**
     * Handle an incoming request.
     * Block access for authenticated client users whose company is suspended.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('client')->check()) {
            return $next($request);
        }

        $clientUser = Auth::guard('client')->user();
        if ($clientUser->client?->company?->status === 'suspended') {
            Auth::guard('client')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('client.login')
                ->withErrors(['email' => 'Your company account has been suspended. Please contact your administrator.']);
        }

        return $next($request);
    }
}
