<?php

namespace App\Http\Controllers;

use App\Services\DashboardOverviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardOverviewService $overview
    ) {}

    public function index(Request $request): View
    {
        $user = Auth::user();
        $payload = $this->overview->forCompany($user?->company_id ? (int) $user->company_id : null);

        return view('dashboard.index', [
            'leads' => $payload['leads'],
            'pipeline' => $payload['pipeline'],
            'sources' => $payload['sources'],
            'recentLeads' => $payload['recentLeads'],
            'recentActivity' => $payload['recentActivity'],
            'channels' => $payload['channels'],
            'attention' => $payload['attention'],
        ]);
    }
}
