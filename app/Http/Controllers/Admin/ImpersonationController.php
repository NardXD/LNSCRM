<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyHistory;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ImpersonationController extends Controller
{
    private const CACHE_PREFIX = 'impersonate:';

    private const TOKEN_TTL_SECONDS = 120;

    /**
     * Start impersonation by logging in as the company admin on this host.
     */
    public function loginAsCompanyAdmin(Request $request, Company $company): RedirectResponse
    {
        $adminUser = $company->adminUser();

        if (! $adminUser) {
            return redirect()->route('admin.company-management')
                ->with('error', 'No active admin user found for "'.$company->name.'".');
        }

        $token = Str::random(64);

        Cache::put(self::CACHE_PREFIX.$token, [
            'user_id' => $adminUser->id,
            'impersonator_id' => auth()->id(),
            'company_id' => $company->id,
        ], self::TOKEN_TTL_SECONDS);

        return redirect()->to(url('/auth/impersonate?token='.urlencode($token)));
    }

    /**
     * Complete impersonation (session is created here).
     */
    public function accept(Request $request): RedirectResponse
    {
        $token = $request->query('token');

        if (! is_string($token) || $token === '') {
            return redirect()->route('login')
                ->with('error', 'Invalid impersonation link.');
        }

        $payload = Cache::pull(self::CACHE_PREFIX.$token);

        if (! is_array($payload)) {
            return redirect()->route('login')
                ->with('error', 'Impersonation link has expired. Please try again from the admin panel.');
        }

        $user = User::query()->find($payload['user_id'] ?? null);
        $company = Company::query()->find($payload['company_id'] ?? null);
        $requestCompany = $this->resolveRequestCompany($request);

        if (! $user || ! $company || ! $requestCompany || $requestCompany->id !== $company->id) {
            return redirect()->route('login')
                ->with('error', 'Invalid impersonation request.');
        }

        if ($user->company_id !== $company->id || $user->status !== 'active') {
            abort(403, 'Unable to impersonate this user.');
        }

        $request->session()->put('impersonator_id', $payload['impersonator_id']);
        Auth::login($user);
        $request->session()->regenerate();

        CompanyHistory::log(
            $company,
            CompanyHistory::ACTION_ADMIN_LOGIN_AS,
            null,
            [
                'user_id' => $user->id,
                'user_email' => $user->email,
            ],
            'Platform admin logged in as company admin',
            $payload['impersonator_id']
        );

        return redirect()->route('dashboard');
    }

    /**
     * End impersonation and return to the platform admin panel.
     */
    public function leave(Request $request): RedirectResponse
    {
        $impersonatorId = $request->session()->get('impersonator_id');

        if (! $impersonatorId) {
            return redirect()->route('dashboard');
        }

        $impersonator = User::query()->find($impersonatorId);

        if (! $impersonator || ! $impersonator->is_admin) {
            $request->session()->forget('impersonator_id');

            return redirect()->route('login');
        }

        $token = Str::random(64);

        Cache::put(self::CACHE_PREFIX.'restore:'.$token, [
            'impersonator_id' => $impersonator->id,
        ], self::TOKEN_TTL_SECONDS);

        $request->session()->forget('impersonator_id');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $baseUrl = rtrim((string) config('app.url'), '/');
        $url = $baseUrl.'/admin/auth/restore?token='.urlencode($token);

        return redirect()->away($url);
    }

    /**
     * Restore platform admin session on the main domain after leaving impersonation.
     */
    public function restore(Request $request): RedirectResponse
    {
        $token = $request->query('token');

        if (! is_string($token) || $token === '') {
            return redirect()->route('admin.login')
                ->with('error', 'Invalid restore link.');
        }

        $payload = Cache::pull(self::CACHE_PREFIX.'restore:'.$token);

        if (! is_array($payload)) {
            return redirect()->route('admin.login')
                ->with('error', 'Restore link has expired. Please log in again.');
        }

        $impersonator = User::query()->find($payload['impersonator_id'] ?? null);

        if (! $impersonator || ! $impersonator->is_admin) {
            return redirect()->route('admin.login')
                ->with('error', 'Unable to restore admin session.');
        }

        Auth::login($impersonator);
        $request->session()->regenerate();

        return redirect()->route('admin.company-management');
    }

    private function resolveRequestCompany(Request $request): ?Company
    {
        $company = $request->get('company');

        if ($company instanceof Company) {
            return $company;
        }

        if (app()->bound('company')) {
            $boundCompany = app('company');

            return $boundCompany instanceof Company ? $boundCompany : null;
        }

        return null;
    }
}
