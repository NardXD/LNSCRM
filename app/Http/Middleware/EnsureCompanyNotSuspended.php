<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyNotSuspended
{
    /**
     * Handle an incoming request.
     * Block access for authenticated users whose company is suspended.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        // Skip for admin routes - admins use /admin prefix
        if ($request->is('admin') || $request->is('admin/*')) {
            return $next($request);
        }

        if ($request->session()->has('impersonator_id')) {
            return $next($request);
        }

        $user = Auth::user();
        if ($user->company_id && $user->company?->status === 'suspended') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Your company account has been suspended. Please contact your administrator.']);
        }

        return $next($request);
    }
}
