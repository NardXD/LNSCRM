<?php

namespace Tests\Feature;

use App\Models\CallAgentPresence;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\InboundCallQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AgentPresenceHeartbeatTest extends TestCase
{
    use RefreshDatabase;

    public function test_heartbeat_skips_redundant_writes_within_min_interval(): void
    {
        [$user] = $this->agentUser();
        $service = app(InboundCallQueueService::class);

        $first = $service->setAvailable($user);
        $originalHeartbeat = $first->last_heartbeat_at?->copy();
        $this->assertNotNull($originalHeartbeat);

        $updatedAt = CallAgentPresence::query()->whereKey($first->id)->value('updated_at');

        $second = $service->heartbeat($user);
        $this->assertTrue($second->last_heartbeat_at->equalTo($originalHeartbeat));
        $this->assertSame(
            (string) $updatedAt,
            (string) CallAgentPresence::query()->whereKey($first->id)->value('updated_at')
        );
    }

    public function test_heartbeat_endpoint_is_cheap_for_offline_agents(): void
    {
        [$user] = $this->agentUser();

        $this->actingAs($user)
            ->postJson('/twilio/agent-presence/heartbeat')
            ->assertOk()
            ->assertJsonPath('data.status', 'offline');

        $this->assertDatabaseMissing('call_agent_presences', [
            'user_id' => $user->id,
            'status' => CallAgentPresence::STATUS_AVAILABLE,
        ]);
    }

    public function test_heartbeat_endpoint_updates_available_agents(): void
    {
        [$user] = $this->agentUser();
        app(InboundCallQueueService::class)->setAvailable($user);

        CallAgentPresence::query()->where('user_id', $user->id)->update([
            'last_heartbeat_at' => now()->subMinutes(2),
            'updated_at' => now()->subMinutes(2),
        ]);

        $this->actingAs($user)
            ->postJson('/twilio/agent-presence/heartbeat')
            ->assertOk()
            ->assertJsonPath('data.status', 'available');

        $presence = CallAgentPresence::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($presence?->last_heartbeat_at);
        $this->assertTrue($presence->last_heartbeat_at->greaterThan(now()->subMinute()));
    }

    /**
     * @return array{0: User}
     */
    private function agentUser(): array
    {
        $company = Company::query()->create([
            'name' => 'Presence Co',
            'subdomain' => 'presence-co-'.uniqid(),
            'status' => 'active',
            'email' => 'admin-presence@lns.test',
        ]);
        $permission = Permission::query()->create([
            'name' => 'view_phone_system',
            'slug' => 'view_phone_system',
            'display_name' => 'View Phone System',
            'company_id' => $company->id,
        ]);
        $role = Role::query()->create([
            'company_id' => $company->id,
            'name' => 'Agent',
            'slug' => 'agent-presence-'.uniqid(),
            'is_active' => true,
        ]);
        $role->permissions()->attach($permission->id);

        $user = User::query()->create([
            'name' => 'Presence Agent',
            'email' => 'presence-agent-'.uniqid().'@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        return [$user];
    }
}
