<?php

namespace App\Http\Controllers;

use App\Services\DashboardOverviewService;
use Illuminate\Http\JsonResponse;
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
        return view('dashboard.index');
    }

    public function overview(Request $request): JsonResponse
    {
        $user = Auth::user();
        $companyModuleSlugs = null;

        if ($user && $user->company && ! $user->is_admin) {
            $slugs = $user->company->modules()
                ->wherePivot('is_enabled', true)
                ->pluck('slug')
                ->toArray();
            $companyModuleSlugs = empty($slugs) ? null : $slugs;
        }

        return response()->json(
            $this->overview->forFrontend(
                $user?->company_id ? (int) $user->company_id : null,
                $user?->getPermissionSlugs() ?? [],
                $companyModuleSlugs
            )
        );
    }
}
