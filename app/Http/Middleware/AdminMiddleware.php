<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (! auth()->check()) {
            return redirect()->route('admin.login');
        }

        // Only users with is_admin = 1 in the database can access admin routes
        if (! auth()->user()->is_admin) {
            abort(403, 'Access denied. Only users with admin privileges (is_admin = 1) can access this area.');
        }

        return $next($request);
    }
}
