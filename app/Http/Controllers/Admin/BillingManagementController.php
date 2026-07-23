<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Plan;
use Illuminate\Http\Request;

class BillingManagementController extends Controller
{
    /**
     * Display the billing management page.
     */
    public function index()
    {
        $plans = Plan::all();
        $companies = Company::with(['activeSubscription.plan'])->get()->map(function ($company) {
            $subscription = $company->activeSubscription;

            return [
                'id' => $company->id,
                'name' => $company->name,
                'status' => $company->status,
                'plan' => $subscription ? $subscription->plan->name : null,
                'billing_cycle' => $subscription ? ucfirst($subscription->billing_cycle) : null,
                'amount' => $subscription ? (float) $subscription->amount : 0,
                'next_billing' => $subscription && $subscription->next_billing_date ? $subscription->next_billing_date->format('Y-m-d') : null,
            ];
        });
        $recentPayments = Payment::with('company')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'company' => $payment->company->name,
                    'amount' => (float) $payment->amount,
                    'date' => $payment->paid_at ? $payment->paid_at->format('Y-m-d') : $payment->created_at->format('Y-m-d'),
                    'status' => $payment->status,
                    'method' => ucfirst(str_replace('_', ' ', $payment->payment_method)),
                ];
            });

        return view('admin.billing-management', compact('plans', 'companies', 'recentPayments'));
    }

    /**
     * Store a new plan.
     */
    public function storePlan(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'billing_cycle' => 'required|in:monthly,yearly',
            'features' => 'nullable|array',
            'is_featured' => 'boolean',
            'max_users' => 'nullable|integer|min:1',
            'storage_limit' => 'nullable|string|max:255',
        ]);

        $plan = Plan::create($validated);

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Plan created successfully.',
                'data' => $plan,
            ]);
        }

        return redirect()->route('admin.billing-management')->with('success', 'Plan created successfully.');
    }

    /**
     * Update a plan.
     */
    public function updatePlan(Request $request, Plan $plan)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'billing_cycle' => 'required|in:monthly,yearly',
            'features' => 'nullable|array',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'max_users' => 'nullable|integer|min:1',
            'storage_limit' => 'nullable|string|max:255',
        ]);

        $plan->update($validated);

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Plan updated successfully.',
                'data' => $plan,
            ]);
        }

        return redirect()->route('admin.billing-management')->with('success', 'Plan updated successfully.');
    }

    /**
     * Delete a plan.
     */
    public function destroyPlan(Plan $plan)
    {
        if ($plan->subscriptions()->count() > 0) {
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete plan with active subscriptions.',
                ], 422);
            }

            return redirect()->route('admin.billing-management')
                ->with('error', 'Cannot delete plan with active subscriptions.');
        }

        $plan->delete();

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Plan deleted successfully.',
            ]);
        }

        return redirect()->route('admin.billing-management')->with('success', 'Plan deleted successfully.');
    }

    /**
     * API: Get all plans
     */
    public function apiPlans()
    {
        $plans = Plan::all();

        return response()->json($plans);
    }

    /**
     * API: Get single plan
     */
    public function apiGetPlan(Plan $plan)
    {
        return response()->json($plan);
    }

    /**
     * API: Store plan
     */
    public function apiStorePlan(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'billing_cycle' => 'required|in:monthly,yearly',
            'features' => 'nullable|array',
            'is_featured' => 'boolean',
            'max_users' => 'nullable|integer|min:1',
            'storage_limit' => 'nullable|string|max:255',
        ]);

        $plan = Plan::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Plan created successfully.',
            'data' => $plan,
        ]);
    }

    /**
     * API: Update plan
     */
    public function apiUpdatePlan(Request $request, Plan $plan)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'billing_cycle' => 'required|in:monthly,yearly',
            'features' => 'nullable|array',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'max_users' => 'nullable|integer|min:1',
            'storage_limit' => 'nullable|string|max:255',
        ]);

        $plan->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Plan updated successfully.',
            'data' => $plan,
        ]);
    }

    /**
     * API: Destroy plan
     */
    public function apiDestroyPlan(Plan $plan)
    {
        if ($plan->subscriptions()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete plan with active subscriptions.',
            ], 422);
        }

        $plan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Plan deleted successfully.',
        ]);
    }

    /**
     * API: Get companies with billing info
     */
    public function apiCompanies()
    {
        $companies = Company::with(['activeSubscription.plan', 'modules'])->get()->map(function ($company) {
            $subscription = $company->activeSubscription;

            return [
                'id' => $company->id,
                'name' => $company->name,
                'status' => $company->status,
                'plan' => $subscription ? $subscription->plan->name : null,
                'billing_cycle' => $subscription ? ucfirst($subscription->billing_cycle) : null,
                'amount' => $subscription ? (float) $subscription->amount : 0,
                'next_billing' => $subscription && $subscription->next_billing_date ? $subscription->next_billing_date->format('Y-m-d') : null,
            ];
        });

        return response()->json($companies);
    }

    /**
     * API: Get recent payments
     */
    public function apiPayments()
    {
        $payments = Payment::with('company')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'company' => $payment->company->name,
                    'amount' => (float) $payment->amount,
                    'date' => $payment->paid_at ? $payment->paid_at->format('Y-m-d') : $payment->created_at->format('Y-m-d'),
                    'status' => $payment->status,
                    'method' => ucfirst(str_replace('_', ' ', $payment->payment_method)),
                ];
            });

        return response()->json($payments);
    }
}
