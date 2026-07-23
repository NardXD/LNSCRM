<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $company = Company::firstOrCreate(
            ['name' => 'Admin Company'],
            [
                'email' => 'admin@example.com',
                'status' => 'active',
            ]
        );

        $user = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => bcrypt('password'),
                'is_admin' => true,
                'status' => 'active',
                'company_id' => $company->id,
                'email_verified_at' => now(),
            ]
        );

        $user->update(['company_id' => $company->id]);
    }
}
