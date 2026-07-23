<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyHistory;
use App\Models\Module;
use Illuminate\Http\Request;

class CompanyAccessControlController extends Controller
{
    /**
     * Display the company access control page.
     */
    public function index()
    {
        $companies = Company::with('modules')->get();
        $modules = Module::where('is_active', true)->orderBy('sort_order')->get();

        return view('admin.company-access-control', compact('companies', 'modules'));
    }

    /**
     * Update company module access.
     */
    public function updateModuleAccess(Request $request, Company $company)
    {
        $validated = $request->validate([
            'modules' => 'required|array',
            'modules.*' => 'exists:modules,id',
        ]);

        $company->modules()->sync($validated['modules'], ['is_enabled' => true, 'granted_at' => now()]);

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Module access updated successfully.',
                'data' => $company->modules,
            ]);
        }

        return redirect()->route('admin.company-access-control')
            ->with('success', 'Module access updated successfully.');
    }

    /**
     * Toggle module access for a company.
     */
    public function toggleModuleAccess(Request $request, Company $company, Module $module)
    {
        $enabled = $request->boolean('enabled');

        $company->modules()->updateExistingPivot($module->id, [
            'is_enabled' => $enabled,
            'granted_at' => $enabled ? now() : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Module access updated successfully.',
        ]);
    }

    /**
     * API: Get all modules
     */
    public function apiModules()
    {
        $modules = Module::where('is_active', true)->orderBy('sort_order')->get();

        return response()->json($modules);
    }

    /**
     * API: Get company modules
     */
    public function apiCompanyModules(Company $company)
    {
        $modules = $company->modules()->get()->pluck('slug')->toArray();

        return response()->json(['modules' => $modules]);
    }

    /**
     * API: Update company modules
     */
    public function apiUpdateCompanyModules(Request $request, Company $company)
    {
        $validated = $request->validate([
            'modules' => 'required|array',
            'modules.*' => 'string', // Module slugs
        ]);

        $oldModules = $company->modules()->pluck('slug')->toArray();
        $newModules = $validated['modules'];

        $syncData = Module::whereIn('slug', $validated['modules'])
            ->get()
            ->mapWithKeys(fn ($m) => [$m->id => ['is_enabled' => true, 'granted_at' => now()]])
            ->all();
        $company->modules()->sync($syncData);

        CompanyHistory::log(
            $company,
            CompanyHistory::ACTION_MODULES_UPDATED,
            ['modules' => $oldModules],
            ['modules' => $newModules]
        );

        return response()->json([
            'success' => true,
            'message' => 'Module access updated successfully.',
            'data' => $company->modules,
        ]);
    }
}
