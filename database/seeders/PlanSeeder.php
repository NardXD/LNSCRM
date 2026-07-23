<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Basic',
                'description' => 'Perfect for small teams getting started',
                'price' => 29.00,
                'billing_cycle' => 'monthly',
                'features' => ['5 Users', '10GB Storage', 'Email Support'],
                'is_featured' => false,
                'is_active' => true,
                'max_users' => 5,
                'storage_limit' => '10GB',
            ],
            [
                'name' => 'Professional',
                'description' => 'Best for growing businesses',
                'price' => 79.00,
                'billing_cycle' => 'monthly',
                'features' => ['20 Users', '100GB Storage', 'Priority Support', 'API Access'],
                'is_featured' => true,
                'is_active' => true,
                'max_users' => 20,
                'storage_limit' => '100GB',
            ],
            [
                'name' => 'Enterprise',
                'description' => 'For large organizations with advanced needs',
                'price' => 199.00,
                'billing_cycle' => 'monthly',
                'features' => ['Unlimited Users', '1TB Storage', '24/7 Support', 'API Access', 'Custom Integrations'],
                'is_featured' => false,
                'is_active' => true,
                'max_users' => null,
                'storage_limit' => '1TB',
            ],
        ];

        foreach ($plans as $plan) {
            Plan::firstOrCreate(
                ['name' => $plan['name']],
                $plan
            );
        }
    }
}
