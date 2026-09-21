<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginDashboardRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_successful_login_lands_on_the_dashboard(): void
    {
        $user = $this->userWithDashboardAccess();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_authenticated_users_are_sent_from_login_to_the_dashboard(): void
    {
        $user = $this->userWithDashboardAccess();

        $this->actingAs($user)
            ->get('/login')
            ->assertRedirect(route('dashboard'));
    }

    public function test_authenticated_users_are_sent_from_the_home_page_to_the_dashboard(): void
    {
        $user = $this->userWithDashboardAccess();

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect(route('dashboard'));
    }

    private function userWithDashboardAccess(): User
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-login-dashboard',
            'status' => 'active',
            'email' => 'admin-login-dashboard@lns.test',
        ]);

        $role = Role::query()->create([
            'name' => 'Manager',
            'slug' => 'manager-login-dashboard',
            'company_id' => $company->id,
            'is_active' => true,
        ]);
        $permission = Permission::query()->create([
            'name' => 'view_dashboard',
            'slug' => 'view_dashboard',
            'display_name' => 'Dashboard',
            'company_id' => $company->id,
        ]);
        $role->permissions()->attach($permission->id);

        return User::query()->create([
            'name' => 'Login User',
            'email' => 'login-dashboard@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);
    }
}
