<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\CompanyPermissionFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $company = Company::where('subdomain', 'lns')->first();

        if (! $company) {
            $this->command?->error('LNS company not found. Run CompanySeeder first.');

            return;
        }

        CompanyPermissionFactory::createForCompany($company);

        $adminRole = Role::firstOrCreate(
            [
                'slug' => 'admin',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Administrator',
                'description' => 'Full system access',
                'is_active' => true,
            ]
        );

        $allPermissionIds = Permission::where('company_id', $company->id)->pluck('id');
        $adminRole->permissions()->sync($allPermissionIds);

        User::updateOrCreate(
            ['email' => 'nard@gmail.com'],
            [
                'name' => 'Nard',
                'password' => Hash::make('password'),
                'company_id' => $company->id,
                'role_id' => $adminRole->id,
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
    }
}
