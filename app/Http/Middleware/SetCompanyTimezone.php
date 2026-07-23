<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\TimezoneService;

class SetCompanyTimezone
{
    /**
     * Handle an incoming request.
     * Sets the application timezone based on the authenticated user's company.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Set timezone if user is authenticated
        if (auth()->check() && auth()->user()->company) {
            TimezoneService::setApplicationTimezone();
        }

        return $next($request);
    }
}
