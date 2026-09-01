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
        $modules = Module::all();
        $plan = Plan::where('name', 'Enterprise')->first();

        $company = Company::firstOrCreate(
            ['subdomain' => 'lns'],
            [
                'name' => 'LNS',
                'email' => 'nard@gmail.com',
                'quotation_prefix' => 'LNS',
                'status' => 'active',
            ]
        );

        $company->update([
            'name' => 'LNS',
            'email' => 'nard@gmail.com',
            'quotation_prefix' => 'LNS',
            'status' => 'active',
        ]);

        if ($plan && ! $company->subscriptions()->where('plan_id', $plan->id)->exists()) {
            $subscription = Subscription::create([
                'company_id' => $company->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'starts_at' => now()->subMonths(2),
                'ends_at' => now()->addYear(),
                'amount' => $plan->price,
                'billing_cycle' => $plan->billing_cycle,
                'next_billing_date' => now()->addMonth(),
            ]);

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

        $moduleIds = $modules->pluck('id');
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
