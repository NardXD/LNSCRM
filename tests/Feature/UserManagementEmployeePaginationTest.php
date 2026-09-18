<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementEmployeePaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_list_returns_integer_page_metadata_and_the_second_page(): void
    {
        [$agent, $company] = $this->managerWithEmployees(12);

        $page1 = $this->actingAs($agent)
            ->getJson('/api/user-management/employees?page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('per_page', 10)
            ->assertJsonPath('last_page', 2)
            ->assertJsonPath('total', 13)
            ->json();

        $this->assertSame(1, $page1['current_page']);
        $this->assertIsInt($page1['current_page']);
        $this->assertIsInt($page1['per_page']);
        $this->assertIsInt($page1['last_page']);
        $this->assertCount(10, $page1['data']);

        $page2 = $this->actingAs($agent)
            ->getJson('/api/user-management/employees?page=2&per_page=10')
            ->assertOk()
            ->assertJsonPath('current_page', 2)
            ->json();

        $this->assertCount(3, $page2['data']);
        $this->assertNotEquals(
            collect($page1['data'])->pluck('id')->all(),
            collect($page2['data'])->pluck('id')->all()
        );
    }

    public function test_out_of_range_page_clamps_to_the_last_page_instead_of_returning_empty(): void
    {
        [$agent] = $this->managerWithEmployees(5);

        $this->actingAs($agent)
            ->getJson('/api/user-management/employees?page=11&per_page=10')
            ->assertOk()
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('last_page', 1)
            ->assertJsonCount(6, 'data');
    }

    /**
     * @return array{0: User, 1: Company}
     */
    private function managerWithEmployees(int $extraCount): array
    {
        $suffix = uniqid();
        $company = Company::create([
            'name' => 'LNS',
            'subdomain' => 'lns-emp-'.$suffix,
            'status' => 'active',
            'email' => 'admin-emp-'.$suffix.'@lns.test',
            'timezone' => 'UTC',
        ]);

        $role = Role::create([
            'name' => 'Manager',
            'slug' => 'manager-emp-'.$suffix,
            'company_id' => $company->id,
            'is_active' => true,
        ]);
        $role->permissions()->attach(Permission::create([
            'name' => 'view_user_management',
            'slug' => 'view_user_management',
            'display_name' => 'User Management',
            'company_id' => $company->id,
        ])->id);

        $agent = User::create([
            'name' => 'Manager',
            'email' => 'manager-emp-'.$suffix.'@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        for ($i = 1; $i <= $extraCount; $i++) {
            User::create([
                'name' => 'Employee '.$i,
                'email' => 'emp-'.$i.'-'.$suffix.'@lns.test',
                'password' => Hash::make('password'),
                'company_id' => $company->id,
                'role_id' => $role->id,
                'status' => 'active',
            ]);
        }

        return [$agent, $company];
    }
}
