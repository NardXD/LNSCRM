<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCompanyRequest;
use App\Models\Company;
use App\Models\CompanyHistory;
use App\Models\Module;
use App\Services\CompanyRegistrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompanyManagementController extends Controller
{
    public function __construct(
        protected CompanyRegistrationService $registrationService
    ) {}

    /**
     * Display the company management page.
     */
    public function index()
    {
        $companies = Company::withCount('users')->with('activeSubscription.plan')->get();
        $modules = Module::where('is_active', true)->orderBy('sort_order')->get();

        return view('admin.company-management', compact('companies', 'modules'));
    }

    /**
     * Store a new company (create company with admin user).
     */
    public function store(StoreCompanyRequest $request)
    {
        try {
            DB::beginTransaction();

            $company = $this->registrationService->createCompany([
                'company' => $request->company,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'password' => $request->password,
                'plan' => $request->plan ?? 'free',
                'status' => $request->status ?? 'trial',
            ]);

            // Always include leave-management and team-management for new companies
            $moduleSlugs = array_unique(array_merge(
                $request->modules ?? [],
                ['leave-management', 'team-management']
            ));

            if (! empty($moduleSlugs)) {
                $syncData = Module::whereIn('slug', $moduleSlugs)
                    ->get()
                    ->mapWithKeys(fn ($m) => [$m->id => ['is_enabled' => true, 'granted_at' => now()]])
                    ->all();
                $company->modules()->sync($syncData);
            }

            CompanyHistory::log($company, CompanyHistory::ACTION_CREATED, null, [
                'status' => $company->status,
                'plan' => $request->plan ?? 'free',
                'modules' => $moduleSlugs ?? [],
            ], 'Company created with '.count($moduleSlugs).' modules (including Leave Management and Team Management)');

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Company created successfully.',
                    'data' => $company->load('users', 'activeSubscription.plan'),
                ]);
            }

            return redirect()->route('admin.company-management')
                ->with('success', 'Company "'.$company->name.'" created successfully. Admin can log in at /login.');
        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Update a company.
     */
    public function update(Request $request, Company $company)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'status' => 'required|in:trial,active,suspended',
            'subdomain' => 'nullable|string|max:255|unique:companies,subdomain,'.$company->id,
        ]);

        $company->update($validated);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Company updated successfully.',
                'data' => $company,
            ]);
        }

        return redirect()->route('admin.company-management')
            ->with('success', 'Company updated successfully.');
    }

    /**
     * Update company status only.
     */
    public function updateStatus(Request $request, Company $company)
    {
        $validated = $request->validate([
            'status' => 'required|in:trial,active,suspended',
        ]);

        $oldStatus = $company->status;
        $company->update($validated);

        CompanyHistory::log(
            $company,
            CompanyHistory::ACTION_STATUS_CHANGED,
            ['status' => $oldStatus],
            ['status' => $company->status]
        );

        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully.',
            'data' => ['status' => $company->status],
        ]);
    }

    /**
     * API: Get all companies.
     */
    public function apiCompanies()
    {
        $companies = Company::withCount('users')
            ->with(['activeSubscription.plan'])
            ->orderBy('name')
            ->get()
            ->map(function ($company) {
                return [
                    'id' => $company->id,
                    'name' => $company->name,
                    'subdomain' => $company->subdomain,
                    'email' => $company->email,
                    'status' => $company->status,
                    'users_count' => $company->users_count,
                    'plan' => $company->activeSubscription?->plan?->name ?? 'N/A',
                    'trial_ends_at' => $company->trial_ends_at?->format('Y-m-d'),
                    'created_at' => $company->created_at->format('Y-m-d H:i'),
                ];
            });

        return response()->json($companies);
    }

    /**
     * API: Get company history.
     */
    public function apiCompanyHistory(Company $company)
    {
        $histories = $company->histories()
            ->with('changedBy:id,name,email')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->sortByDesc('created_at')
            ->values()
            ->map(fn ($h) => [
                'id' => $h->id,
                'action' => $h->action,
                'summary' => $h->summary,
                'old_value' => $h->old_value,
                'new_value' => $h->new_value,
                'description' => $h->description,
                'changed_by' => $h->changedBy ? $h->changedBy->name : 'System',
                'created_at' => $h->created_at->format('M j, Y g:i A'),
            ]);

        return response()->json(['histories' => $histories]);
    }

    /**
     * API: Get single company.
     */
    public function apiGetCompany(Company $company)
    {
        $company->load(['users', 'activeSubscription.plan']);

        return response()->json($company);
    }
}
