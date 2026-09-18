<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InboxForwardResendTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbox_page_includes_forward_and_resend_actions(): void
    {
        $user = $this->inboxUser();

        $this->actingAs($user)
            ->get('/inbox')
            ->assertOk()
            ->assertSee('id="btnModeForward"', false)
            ->assertSee('id="btnModeResend"', false)
            ->assertSee('data-forward-msg=', false)
            ->assertSee('data-resend-msg=', false)
            ->assertSee('>Forward</button>', false)
            ->assertSee('>Resend</button>', false);
    }

    private function inboxUser(): User
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-inbox-forward-resend',
            'status' => 'active',
            'email' => 'admin-inbox-forward-resend@lns.test',
        ]);

        $role = Role::query()->create([
            'name' => 'Staff',
            'slug' => 'staff-inbox-forward-resend',
            'company_id' => $company->id,
            'is_active' => true,
        ]);
        $permission = Permission::query()->create([
            'name' => 'view_inbox',
            'slug' => 'view_inbox',
            'display_name' => 'View Inbox',
            'company_id' => $company->id,
        ]);
        $role->permissions()->attach($permission->id);

        return User::query()->create([
            'name' => 'Login User',
            'email' => 'login-inbox-forward-resend@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);
    }
}
