<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AdminAuthController extends Controller
{
    /**
     * Show the admin login form.
     */
    public function showLoginForm()
    {
        // Redirect if already authenticated as admin
        if (Auth::check() && Auth::user()->is_admin) {
            return redirect()->route('admin-control');
        }

        return view('auth.admin-login');
    }

    /**
     * Handle admin login request.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = $request->boolean('remember');

        // Attempt to authenticate the user
        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();

            $user = Auth::user();

            // Check if user is an admin
            if (! $user->is_admin) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                throw ValidationException::withMessages([
                    'email' => ['These credentials do not have admin access.'],
                ]);
            }

            return redirect()->route('admin-control');
        }

        throw ValidationException::withMessages([
            'email' => ['The provided credentials do not match our records.'],
        ]);
    }

    /**
     * Handle admin logout request.
     */
    public function logout(Request $request)
    {
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

        // Redirect to APP_URL
        return redirect(config('app.url'))->withCookie($cookie);
    }
}
