<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\OutlookMailAccount;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SharedInbox;
use App\Models\SharedInboxMember;
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

    public function test_bootstrap_includes_shared_and_direct_m365_senders(): void
    {
        [$user, $company] = $this->userWithPermissions([
            'view_broadcast_messaging',
            'send_broadcast_email',
        ]);

        $account = OutlookMailAccount::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'email' => 'sender@example.com',
            'access_token' => 'token',
            'is_active' => true,
        ]);

        $shared = SharedInbox::query()->create([
            'company_id' => $company->id,
            'outlook_mail_account_id' => $account->id,
            'created_by' => $user->id,
            'name' => 'Support',
            'email' => 'support@example.com',
            'type' => SharedInbox::TYPE_SHARED,
            'is_active' => true,
        ]);
        SharedInboxMember::query()->create([
            'shared_inbox_id' => $shared->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);

        $direct = SharedInbox::query()->create([
            'company_id' => $company->id,
            'outlook_mail_account_id' => $account->id,
            'created_by' => $user->id,
            'name' => 'Marketing',
            'email' => 'marketing@example.com',
            'type' => SharedInbox::TYPE_BROADCAST,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->getJson('/api/broadcast/bootstrap')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['id' => $shared->id, 'type' => 'shared', 'email' => 'support@example.com'])
            ->assertJsonFragment(['id' => $direct->id, 'type' => 'broadcast', 'email' => 'marketing@example.com'])
            ->assertJsonStructure(['data' => ['outlook_connect_url']]);
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
