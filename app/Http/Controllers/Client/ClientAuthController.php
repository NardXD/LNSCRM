<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClientAuthController extends Controller
{
    /**
     * Show the client login form.
     */
    public function showLoginForm(Request $request): View|RedirectResponse
    {
        // Redirect if already authenticated as client
        if (Auth::guard('client')->check()) {
            return redirect()->route('client.portal.dashboard');
        }

        return view('auth.client-login');
    }

    /**
     * Handle a client login request.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = $request->boolean('remember');

        // Attempt to authenticate the client user
        if (Auth::guard('client')->attempt($credentials, $remember)) {
            $request->session()->regenerate();

            $clientUser = Auth::guard('client')->user();

            // Check if client user is active
            if (! $clientUser->isActive()) {
                Auth::guard('client')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                throw ValidationException::withMessages([
                    'email' => ['Your account has been deactivated. Please contact your administrator.'],
                ]);
            }

            // Check if the client is active
            if ($clientUser->client && $clientUser->client->status !== 'active') {
                Auth::guard('client')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                throw ValidationException::withMessages([
                    'email' => ['Your client account is not active. Please contact support.'],
                ]);
            }

            // Block login if company is suspended
            if ($clientUser->client?->company?->status === 'suspended') {
                Auth::guard('client')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                throw ValidationException::withMessages([
                    'email' => ['Your company account has been suspended. Please contact your administrator.'],
                ]);
            }

            // Get company from subdomain if available
            $company = $this->getCompanyFromRequest($request);

            // Verify client belongs to the correct company (if on subdomain)
            if ($company && $clientUser->client && $clientUser->client->company_id !== $company->id) {
                Auth::guard('client')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                throw ValidationException::withMessages([
                    'email' => ['This account does not belong to this company.'],
                ]);
            }

            return redirect()->intended(route('client.portal.dashboard'));
        }

        throw ValidationException::withMessages([
            'email' => ['The provided credentials do not match our records.'],
        ]);
    }

    /**
     * Handle a client logout request.
     */
    public function logout(Request $request): RedirectResponse
    {
        if (Auth::guard('client')->check()) {
            Auth::guard('client')->logout();

            $request->session()->flush();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $cookieName = 'remember_client_'.sha1(config('app.key'));
            $cookie = cookie()->forget($cookieName);

            return redirect()->route('client.login')->withCookie($cookie);
        }

        return redirect()->route('client.login');
    }

    /**
     * Safely get company from request or container.
     */
    private function getCompanyFromRequest(Request $request): ?Company
    {
        $company = $request->get('company');

        if ($company instanceof Company) {
            return $company;
        }

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
