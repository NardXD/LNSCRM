<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Subscription;

class AdminController extends Controller
{
    /**
     * Display the admin control panel.
     */
    public function index()
    {
        $stats = [
            'total_companies' => Company::count(),
            'active_subscriptions' => Subscription::where('status', 'active')->count(),
            'monthly_revenue' => Payment::where('status', 'completed')
                ->whereMonth('created_at', now()->month)
                ->sum('amount'),
            'pending_approvals' => Company::where('status', 'trial')->count(),
        ];

        return view('admin.index', compact('stats'));
    }

    /**
     * API: Get admin stats
     */
    public function apiStats()
    {
        $totalCompanies = Company::count();
        $activeSubscriptions = Subscription::where('status', 'active')->count();
        $monthlyRevenue = Payment::where('status', 'completed')
            ->whereMonth('created_at', now()->month)
            ->sum('amount');
        $pendingApprovals = Company::where('status', 'trial')->count();

        $lastMonthRevenue = Payment::where('status', 'completed')
            ->whereMonth('created_at', now()->subMonth()->month)
            ->sum('amount');

        $revenueChange = $lastMonthRevenue > 0
            ? (($monthlyRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100
            : 0;

        $thisMonthCompanies = Company::whereMonth('created_at', now()->month)->count();

        $activeRate = $totalCompanies > 0
            ? ($activeSubscriptions / $totalCompanies) * 100
            : 0;

        return response()->json([
            'total_companies' => $totalCompanies,
            'active_subscriptions' => $activeSubscriptions,
            'monthly_revenue' => (float) $monthlyRevenue,
            'pending_approvals' => $pendingApprovals,
            'companies_this_month' => $thisMonthCompanies,
            'revenue_change_percent' => round($revenueChange, 1),
            'active_rate' => round($activeRate, 1),
        ]);
    }
}
