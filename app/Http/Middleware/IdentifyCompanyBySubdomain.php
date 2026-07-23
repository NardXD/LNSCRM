<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyCompanyBySubdomain
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $subdomain = $this->extractSubdomain($request->getHost());

        if (! $subdomain || $request->is('admin/*')) {
            return $next($request);
        }

        $company = Company::where('subdomain', $subdomain)->first();

        if (! $company) {
            abort(404, 'Company not found');
        }

        $request->merge(['company' => $company]);
        app()->instance('company', $company);

        return $next($request);
    }

    /**
     * Extract subdomain from host.
     */
    private function extractSubdomain(string $host): ?string
    {
        // Handle localhost subdomain format: subdomain.localhost
        if (str_contains($host, '.localhost')) {
            return str_replace('.localhost', '', $host);
        }

        // Handle .test, .local, or other local TLDs
        if (preg_match('/^([a-z0-9-]+)\.(test|local|localhost)$/i', $host, $matches)) {
            return strtolower($matches[1]);
        }

        // Handle main domain without subdomain (e.g., localhost, 127.0.0.1)
        $parts = explode('.', $host);
        if (count($parts) <= 2) {
            return null;
        }

        // Extract subdomain (first part before the domain)
        return strtolower($parts[0]);
    }
}
