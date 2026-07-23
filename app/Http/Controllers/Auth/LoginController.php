<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * Show the login form.
     */
    public function showLoginForm(Request $request)
    {
        // Redirect if already authenticated
        if (Auth::check()) {
            $user = Auth::user();

            // If user has a company and we're on main domain, redirect to their subdomain
            if ($user->company_id) {
                $company = $user->company;

                if ($company && $company->subdomain) {
                    $currentCompany = $this->getCompanyFromRequest($request);

                    // Only redirect if we're on main domain (not already on subdomain)
                    if (! $currentCompany) {
                        $subdomainUrl = $this->buildSubdomainUrl($company->subdomain, '/time-tracking');

                        return redirect($subdomainUrl);
                    }
                }
            }

            return redirect()->route('time-tracking');
        }

        return view('auth.login');
    }

    /**
     * Handle a login request.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = $request->boolean('remember');

        // Get the company from subdomain if available
        $company = $this->getCompanyFromRequest($request);

        // Attempt to authenticate the user
        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();

            $user = Auth::user();

            // Block login if company is suspended
            if ($user->company_id && $user->company?->status === 'suspended') {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                throw ValidationException::withMessages([
                    'email' => ['Your company account has been suspended. Please contact your administrator.'],
                ]);
            }

            // If we're on a subdomain, verify user belongs to that company
            if ($company && $user->company_id !== $company->id) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                throw ValidationException::withMessages([
                    'email' => ['This account does not belong to this company. Please log in through the correct subdomain.'],
                ]);
            }

            // If logging in from main domain (localhost:8000), redirect to user's subdomain
            if (! $company && $user->company_id) {
                $userCompany = $user->company;

                if ($userCompany && $userCompany->subdomain) {
                    // Redirect to user's subdomain time tracking
                    $subdomainUrl = $this->buildSubdomainUrl($userCompany->subdomain, '/time-tracking');

                    return redirect($subdomainUrl);
                }
            }

            // Redirect to time tracking on the same host (avoid stale intended URLs to /).
            $request->session()->forget('url.intended');

            return redirect()->route('time-tracking');
        }

        throw ValidationException::withMessages([
            'email' => ['The provided credentials do not match our records.'],
        ]);
    }

    /**
     * Handle a logout request.
     */
    public function logout(Request $request)
    {
        $redirectUrl = $this->resolveLoginPortalUrl($request);

        // Only logout if user is authenticated
        if (Auth::check()) {
            // Logout the authenticated user
            Auth::logout();

            // Clear all session data
            $request->session()->flush();

            // Invalidate the session
            $request->session()->invalidate();

            // Regenerate CSRF token
            $request->session()->regenerateToken();

            // Clear remember me cookie if exists
            $cookieName = 'remember_web_'.sha1(config('app.key'));
            $cookie = cookie()->forget($cookieName);

            return redirect($redirectUrl)->withCookie($cookie);
        }

        return redirect($redirectUrl);
    }

    /**
     * Resolve the login portal URL for the current company context.
     */
    private function resolveLoginPortalUrl(Request $request): string
    {
        $company = $this->getCompanyFromRequest($request);

        if ($company?->subdomain) {
            return $this->buildSubdomainUrl($company->subdomain, '/login');
        }

        if (Auth::check()) {
            $userCompany = Auth::user()->company;

            if ($userCompany?->subdomain) {
                return $this->buildSubdomainUrl($userCompany->subdomain, '/login');
            }
        }

        return config('app.url');
    }

    /**
     * Build subdomain URL.
     */
    private function buildSubdomainUrl(string $subdomain, string $path = '/'): string
    {
        $baseUrl = config('app.url');
        $parsed = parse_url($baseUrl);
        $scheme = $parsed['scheme'] ?? (request()->secure() ? 'https' : 'http');
        $port = request()->getPort();

        // Handle different base URL formats
        if (str_contains($baseUrl, 'localhost')) {
            // For localhost, use subdomain.localhost format
            $host = $subdomain.'.localhost';
        } else {
            // For production domains, extract the domain and prepend subdomain
            $host = $subdomain.'.'.($parsed['host'] ?? 'localhost');
        }

        // Build URL with port if not default (skip for standard HTTP/HTTPS ports)
        $url = ($port && $port != 80 && $port != 443)
            ? "{$scheme}://{$host}:{$port}{$path}"
            : "{$scheme}://{$host}{$path}";

        return $url;
    }

    /**
     * Safely get company from request or container.
     */
    private function getCompanyFromRequest(Request $request): ?Company
    {
        // First try to get from request (set by middleware)
        $company = $request->get('company');

        if ($company instanceof Company) {
            return $company;
        }

        // Fallback: try container if bound
        if (app()->bound('company')) {
            try {
                return app('company');
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }
}
