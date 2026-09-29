<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\InboxUserSetting;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InboxConvTimeFormatTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrap_defaults_to_relative_format(): void
    {
        $user = $this->agent();

        $this->actingAs($user)
            ->getJson('/api/inbox/bootstrap')
            ->assertOk()
            ->assertJsonPath('conv_time_format', 'relative');
    }

    public function test_saving_absolute_format_persists_and_is_returned_on_next_bootstrap(): void
    {
        $user = $this->agent();

        $this->actingAs($user)
            ->putJson('/api/inbox/conv-time-format', ['format' => 'absolute'])
            ->assertOk()
            ->assertJsonPath('conv_time_format', 'absolute');

        $this->assertSame('absolute', InboxUserSetting::query()->where('user_id', $user->id)->first()?->conv_time_format);

        $this->actingAs($user)
            ->getJson('/api/inbox/bootstrap')
            ->assertOk()
            ->assertJsonPath('conv_time_format', 'absolute');
    }

    public function test_format_is_scoped_per_user(): void
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-conv-time-format',
            'status' => 'active',
            'email' => 'admin-conv-time-format@lns.test',
        ]);
        $role = Role::query()->create([
            'name' => 'Staff',
            'slug' => 'staff-conv-time-format',
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

        $userA = User::query()->create([
            'name' => 'User A',
            'email' => 'user-a-conv-time-format@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);
        $userB = User::query()->create([
            'name' => 'User B',
            'email' => 'user-b-conv-time-format@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $this->actingAs($userA)
            ->putJson('/api/inbox/conv-time-format', ['format' => 'absolute'])
            ->assertOk();

        $this->actingAs($userB)
            ->getJson('/api/inbox/bootstrap')
            ->assertOk()
            ->assertJsonPath('conv_time_format', 'relative');
    }

    public function test_invalid_format_is_rejected(): void
    {
        $user = $this->agent();

        $this->actingAs($user)
            ->putJson('/api/inbox/conv-time-format', ['format' => 'bogus'])
            ->assertStatus(422);
    }

    private function agent(): User
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-conv-time-format-'.uniqid(),
            'status' => 'active',
            'email' => 'admin-conv-time-format-'.uniqid().'@lns.test',
        ]);

        $role = Role::query()->create([
            'name' => 'Staff',
            'slug' => 'staff-conv-time-format-'.uniqid(),
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
            'name' => 'Conv Time User',
            'email' => 'conv-time-format-'.uniqid().'@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);
    }
}
