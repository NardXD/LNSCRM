<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Module;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = Plan::all();
        $modules = Module::all();

        $companies = [
            [
                'name' => 'Acme Corporation',
                'email' => 'contact@acme.com',
                'status' => 'active',
                'plan_id' => 2, // Professional
                'modules' => ['dashboard', 'time-tracking', 'user-management', 'employee-monitoring', 'project-management', 'billing', 'client-management'],
            ],
            [
                'name' => 'TechStart Inc',
                'email' => 'info@techstart.com',
                'status' => 'active',
                'plan_id' => 3, // Enterprise
                'modules' => $modules->pluck('slug')->toArray(), // All modules
            ],
            [
                'name' => 'BrandCo',
                'email' => 'hello@brandco.com',
                'status' => 'trial',
                'plan_id' => 1, // Basic
                'modules' => ['dashboard', 'time-tracking', 'user-management', 'employee-monitoring'],
            ],
            [
                'name' => 'ShopNow',
                'email' => 'support@shopnow.com',
                'status' => 'active',
                'plan_id' => 2, // Professional
                'modules' => ['dashboard', 'time-tracking', 'user-management', 'project-management', 'billing', 'client-management', 'messaging'],
            ],
            [
                'name' => 'CloudTech',
                'email' => 'contact@cloudtech.com',
                'status' => 'expired',
                'plan_id' => 1, // Basic
                'modules' => ['dashboard', 'billing'],
            ],
        ];

        foreach ($companies as $companyData) {
            $plan = $plans->find($companyData['plan_id']);
            $moduleSlugs = $companyData['modules'];
            $status = $companyData['status'];
            $companyName = $companyData['name'];
            unset($companyData['plan_id'], $companyData['modules']);

            $company = Company::firstOrCreate(
                ['name' => $companyName],
                $companyData
            );

            // Create subscription if it doesn't exist
            if ($plan && ! $company->subscriptions()->where('plan_id', $plan->id)->exists()) {
                $subscription = Subscription::create([
                    'company_id' => $company->id,
                    'plan_id' => $plan->id,
                    'status' => $status === 'trial' ? 'trial' : 'active',
                    'starts_at' => now()->subMonths(2),
                    'ends_at' => $status === 'expired' ? now()->subDays(5) : now()->addMonth(),
                    'trial_ends_at' => $status === 'trial' ? now()->addDays(15) : null,
                    'amount' => $plan->price,
                    'billing_cycle' => $plan->billing_cycle,
                    'next_billing_date' => $status !== 'expired' ? now()->addMonth() : null,
                ]);

                // Create a payment for active subscriptions
                if ($status === 'active') {
                    Payment::firstOrCreate(
                        [
                            'company_id' => $company->id,
                            'subscription_id' => $subscription->id,
                            'paid_at' => now()->subMonth(),
                        ],
                        [
                            'amount' => $plan->price,
                            'status' => 'completed',
                            'payment_method' => 'credit_card',
                        ]
                    );
                }
            }

            // Sync modules (this will add new ones and keep existing)
            $moduleIds = Module::whereIn('slug', $moduleSlugs)->pluck('id');
            $existingModuleIds = $company->modules()->pluck('modules.id')->toArray();
            $newModuleIds = $moduleIds->diff($existingModuleIds);

            if ($newModuleIds->isNotEmpty()) {
                $company->modules()->attach($newModuleIds, [
                    'is_enabled' => true,
                    'granted_at' => now(),
                ]);
            }
        }
    }
}
