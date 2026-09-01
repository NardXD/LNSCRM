<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyCompanyBySubdomain
{
    /**
     * Bind the single company for this install. Subdomains are not used.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('admin/*')) {
            return $next($request);
        }

        $company = Company::current();

        if ($company) {
            app()->instance('company', $company);

            // Do not overwrite a posted "company" field (e.g. tenant name on storage quotes).
            if (! $request->has('company')) {
                $request->merge(['company' => $company]);
            }
        }

        return $next($request);
    }
}
