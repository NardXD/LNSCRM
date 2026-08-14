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

            if ($company && $user->company_id && $user->company_id !== $company->id && ! $user->is_admin) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                throw ValidationException::withMessages([
                    'email' => ['This account does not belong to this company.'],
                ]);
            }

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
        return url('/login');
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
