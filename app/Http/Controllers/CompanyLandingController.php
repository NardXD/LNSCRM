<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CompanyLandingController extends Controller
{
    /**
     * Show the root page: company landing when on subdomain with valid company,
     * otherwise the promotional home page with login form.
     * Redirects authenticated users to time tracking.
     */
    public function index(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('time-tracking');
        }

        $company = $request->get('company') ?? (app()->bound('company') ? app('company') : null);

        if ($company) {
            return view('landing.company-landing', ['company' => $company]);
        }

        return view('landing.index');
    }

    /**
     * Show the Hiring Assistant chat interface. Requires company (subdomain).
     * Redirects to landing if no company.
     */
    public function hiringAssistant(Request $request): View|RedirectResponse
    {
        $company = $request->get('company') ?? (app()->bound('company') ? app('company') : null);

        if (! $company) {
            return redirect('/');
        }

        $disabled = $company->status === 'suspended';
        $disabledReason = $disabled
            ? 'This company account has been suspended. The hiring assistant is currently unavailable. Please contact your administrator.'
            : null;

        return view('landing.hiring-assistant', [
            'company' => $company,
            'disabled' => $disabled,
            'disabledReason' => $disabledReason,
        ]);
    }
}
