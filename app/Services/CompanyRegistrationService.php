<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Support\CompanyPermissionFactory;
use Illuminate\Support\Str;

class CompanyRegistrationService
{
    public function __construct(protected AiSettingsService $aiSettings) {}

    /**
     * Create a new company with admin user, permissions, and subscription.
     */
    public function createCompany(array $data): Company
    {
        $subdomain = $this->generateUniqueSubdomain($data['company']);
        $plan = $this->findOrCreatePlan($data['plan'] ?? 'free');

        $company = Company::create([
            'name' => $data['company'],
            'subdomain' => $subdomain,
            'email' => $data['email'],
            'status' => $data['status'] ?? 'trial',
            'trial_ends_at' => ($data['status'] ?? 'trial') === 'trial' ? now()->addDays(14) : null,
        ]);

        $permissions = CompanyPermissionFactory::createForCompany($company);

        $adminRole = Role::create([
            'name' => 'Administrator',
            'slug' => 'admin',
            'description' => 'Full system access',
            'is_active' => true,
            'company_id' => $company->id,
        ]);

        $adminRole->permissions()->sync($permissions->pluck('id')->toArray());

        User::create([
            'name' => trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')),
            'email' => $data['email'],
            'password' => $data['password'],
            'company_id' => $company->id,
            'status' => 'active',
            'role_id' => $adminRole->id,
        ]);

        if ($plan->price > 0) {
            Subscription::create([
                'company_id' => $company->id,
                'plan_id' => $plan->id,
                'status' => 'pending',
                'starts_at' => now(),
                'amount' => $plan->price,
                'billing_cycle' => 'monthly',
                'next_billing_date' => now()->addMonth(),
            ]);
        } else {
            Subscription::create([
                'company_id' => $company->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'starts_at' => now(),
                'amount' => 0,
                'billing_cycle' => 'monthly',
            ]);
        }

        $this->aiSettings->autoConnectIfEnabled($company->id);

        return $company;
    }

    public function generateUniqueSubdomain(string $companyName): string
    {
        $subdomain = Str::slug(Str::lower($companyName), '-');
        $subdomain = preg_replace('/[^a-z0-9-]/', '', $subdomain);

        if (empty($subdomain)) {
            $subdomain = 'company-'.Str::random(6);
        }

        $originalSubdomain = $subdomain;
        $counter = 1;
        while (Company::where('subdomain', $subdomain)->exists()) {
            $subdomain = $originalSubdomain.'-'.$counter;
            $counter++;
        }

        return $subdomain;
    }

    private function findOrCreatePlan(string $planSlug): Plan
    {
        $planMapping = [
            'free' => ['name' => 'Free', 'price' => 0.00, 'description' => 'Free plan with basic features'],
            'gold' => ['name' => 'Gold', 'price' => 149.00, 'description' => 'Perfect for growing businesses'],
            'platinum' => ['name' => 'Platinum', 'price' => 399.00, 'description' => 'Ultimate solution for professionals'],
        ];

        $planData = $planMapping[$planSlug] ?? $planMapping['free'];

        return Plan::firstOrCreate(
            ['name' => $planData['name']],
            [
                'name' => $planData['name'],
                'description' => $planData['description'],
                'price' => $planData['price'],
                'billing_cycle' => 'monthly',
                'is_featured' => $planSlug === 'gold',
                'is_active' => true,
                'max_users' => $planSlug === 'free' ? 1 : ($planSlug === 'gold' ? 10 : 40),
            ]
        );
    }

    private function createCompanyPermissions(Company $company): \Illuminate\Support\Collection
    {
        $sidebarPermissions = require app_path('Helpers/SidebarPermissionsMap.php');

        $createdPermissions = collect();

        foreach ($sidebarPermissions as $permissionData) {
            $permission = Permission::where('slug', $permissionData['slug'])
                ->where('company_id', $company->id)
                ->first();

            if (! $permission) {
                $permission = Permission::create([
                    'name' => $permissionData['name'],
                    'slug' => $permissionData['slug'],
                    'display_name' => $permissionData['display_name'] ?? $permissionData['name'],
                    'description' => $permissionData['description'] ?? '',
                    'category' => $permissionData['category'] ?? 'main',
                    'company_id' => $company->id,
                ]);
            }

            $createdPermissions->push($permission);
        }

        return $createdPermissions;
    }
}
