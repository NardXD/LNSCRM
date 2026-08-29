<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BroadcastMessagingLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_bootstrap_exposes_max_recipients(): void
    {
        config(['broadcast.max_recipients' => 10000]);

        [$user] = $this->userWithPermissions([
            'view_broadcast_messaging',
            'send_broadcast_sms',
        ]);

        $this->actingAs($user)
            ->getJson('/api/broadcast/bootstrap')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.max_recipients', 10000);
    }

    public function test_store_rejects_more_than_max_recipients(): void
    {
        config(['broadcast.max_recipients' => 5]);

        [$user] = $this->userWithPermissions([
            'view_broadcast_messaging',
            'send_broadcast_sms',
        ]);

        $recipients = [];
        for ($i = 0; $i < 6; $i++) {
            $recipients[] = [
                'source' => 'manual',
                'address' => '+1555000'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            ];
        }

        $this->actingAs($user)
            ->postJson('/api/broadcast/campaigns', [
                'name' => 'Large send',
                'type' => 'sms',
                'from_number' => '+15551234567',
                'body' => 'Hello',
                'recipients' => $recipients,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['recipients']);
    }

    /**
     * @param  list<string>  $slugs
     * @return array{0: User, 1: Company}
     */
    private function userWithPermissions(array $slugs): array
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-broadcast-limit',
            'status' => 'active',
            'email' => 'admin-broadcast-limit@lns.test',
        ]);

        $role = Role::query()->create([
            'name' => 'Manager',
            'slug' => 'manager-broadcast-limit',
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        foreach ($slugs as $slug) {
            $permission = Permission::query()->create([
                'name' => $slug,
                'slug' => $slug,
                'display_name' => $slug,
                'company_id' => $company->id,
            ]);
            $role->permissions()->attach($permission->id);
        }

        $user = User::query()->create([
            'name' => 'Manager',
            'email' => 'manager-broadcast-limit@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        return [$user, $company];
    }
}
