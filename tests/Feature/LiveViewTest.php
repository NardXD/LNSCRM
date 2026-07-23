<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyHistory;
use App\Models\LiveViewSession;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ScreenRecording;
use App\Models\TimeTracking;
use App\Models\User;
use App\Models\WebrtcSignal;
use App\Services\LiveViewIceConfigService;
use App\Services\LiveViewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LiveViewTest extends TestCase
{
    use RefreshDatabase;

    private function createCompanyUser(string $email, Company $company, array $permissionSlugs = []): User
    {
        $user = User::query()->create([
            'name' => strtoupper(explode('@', $email)[0]),
            'email' => $email,
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        if ($permissionSlugs !== []) {
            $role = Role::query()->create([
                'company_id' => $company->id,
                'name' => 'Live View Role '.$user->id,
                'slug' => 'live_view_role_'.$user->id,
            ]);

            foreach ($permissionSlugs as $slug) {
                $permission = Permission::query()->firstOrCreate(
                    ['slug' => $slug, 'company_id' => $company->id],
                    [
                        'name' => $slug,
                        'display_name' => $slug,
                        'description' => $slug,
                        'category' => 'employee_monitoring',
                    ]
                );
                $role->permissions()->attach($permission->id);
            }

            $user->roles()->attach($role->id);
        }

        return $user;
    }

    private function makeWorkerLive(User $worker): void
    {
        TimeTracking::query()->create([
            'user_id' => $worker->id,
            'company_id' => $worker->company_id,
            'date' => now()->toDateString(),
            'time_in' => now()->subHour(),
            'status' => 'active',
        ]);

        ScreenRecording::query()->create([
            'user_id' => $worker->id,
            'company_id' => $worker->company_id,
            'date' => now()->toDateString(),
            'status' => 'recording',
            'metadata' => ['recording_session_active' => true],
        ]);

        app(LiveViewService::class)->heartbeat($worker, '127.0.0.1', true);
    }

    public function test_ice_config_endpoint_returns_servers_for_authenticated_user(): void
    {
        $company = Company::query()->create([
            'name' => 'Acme',
            'subdomain' => 'acme',
            'status' => 'active',
            'email' => 'admin@acme.test',
        ]);

        $admin = $this->createCompanyUser('admin@acme.test', $company, ['view_live_screen']);

        $response = $this->actingAs($admin)->getJson('/api/live-view/ice-config');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'ice_servers',
                'turn_configured',
                'ice_gathering_timeout_ms',
                'signal_poll_active_interval_ms',
                'signal_poll_idle_interval_ms',
                'signal_poll_connect_interval_ms',
                'heartbeat_interval_ms',
            ]);
    }

    public function test_admin_can_start_live_view_session_when_worker_is_live(): void
    {
        $company = Company::query()->create([
            'name' => 'Acme',
            'subdomain' => 'acme',
            'status' => 'active',
            'email' => 'admin@acme.test',
        ]);

        $admin = $this->createCompanyUser('admin@acme.test', $company, ['view_live_screen', 'view_employee_monitoring']);
        $worker = $this->createCompanyUser('worker@acme.test', $company);
        $this->makeWorkerLive($worker);

        $response = $this->actingAs($admin)->postJson('/api/live-view/sessions', [
            'worker_id' => $worker->id,
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('live_view_sessions', [
            'admin_id' => $admin->id,
            'worker_id' => $worker->id,
            'status' => LiveViewSession::STATUS_PENDING,
        ]);

        $this->assertDatabaseHas('webrtc_signals', [
            'to_user_id' => $worker->id,
            'signal_type' => 'live-view-request',
        ]);

        $this->assertDatabaseHas('company_histories', [
            'company_id' => $company->id,
            'action' => CompanyHistory::ACTION_LIVE_VIEW_STARTED,
        ]);
    }

    public function test_heartbeat_clears_when_stream_inactive(): void
    {
        $company = Company::query()->create([
            'name' => 'Acme',
            'subdomain' => 'acme',
            'status' => 'active',
            'email' => 'admin@acme.test',
        ]);

        $worker = $this->createCompanyUser('worker@acme.test', $company);
        $this->makeWorkerLive($worker);

        $this->actingAs($worker)->postJson('/api/live-view/heartbeat', [
            'stream_active' => false,
        ])->assertOk();

        $service = app(LiveViewService::class);
        $this->assertFalse($service->isWorkerLiveAvailable($worker));
    }

    public function test_cleanup_command_marks_stale_sessions_failed(): void
    {
        $company = Company::query()->create([
            'name' => 'Acme',
            'subdomain' => 'acme',
            'status' => 'active',
            'email' => 'admin@acme.test',
        ]);

        $admin = $this->createCompanyUser('admin@acme.test', $company);
        $worker = $this->createCompanyUser('worker@acme.test', $company);

        $session = LiveViewSession::query()->create([
            'company_id' => $company->id,
            'admin_id' => $admin->id,
            'worker_id' => $worker->id,
            'status' => LiveViewSession::STATUS_PENDING,
        ]);
        $session->forceFill([
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ])->saveQuietly();

        $this->artisan('live-view:cleanup')->assertSuccessful();

        $this->assertDatabaseHas('live_view_sessions', [
            'status' => LiveViewSession::STATUS_FAILED,
        ]);
    }

    public function test_ice_config_service_includes_turn_when_configured(): void
    {
        config([
            'live-view.turn_urls' => ['turn:turn.example.com:3478'],
            'live-view.turn_username' => 'user',
            'live-view.turn_credential' => 'pass',
        ]);

        $service = app(LiveViewIceConfigService::class);
        $servers = $service->iceServers();

        $this->assertTrue($service->turnConfigured());
        $this->assertNotEmpty($servers);
        $this->assertArrayHasKey('credential', $servers[1]);
    }

    public function test_live_view_sessions_list_supports_pagination(): void
    {
        $company = Company::query()->create([
            'name' => 'Acme',
            'subdomain' => 'acme',
            'status' => 'active',
            'email' => 'admin@acme.test',
        ]);

        $admin = $this->createCompanyUser('admin@acme.test', $company, ['view_live_screen']);
        $worker = $this->createCompanyUser('worker@acme.test', $company);

        for ($i = 0; $i < 12; $i++) {
            LiveViewSession::query()->create([
                'company_id' => $company->id,
                'admin_id' => $admin->id,
                'worker_id' => $worker->id,
                'status' => LiveViewSession::STATUS_ENDED,
            ]);
        }

        $response = $this->actingAs($admin)->getJson('/api/live-view/sessions?page=2&per_page=10');

        $response->assertOk()
            ->assertJsonPath('pagination.current_page', 2)
            ->assertJsonPath('pagination.per_page', 10)
            ->assertJsonPath('pagination.total', 12)
            ->assertJsonCount(2, 'sessions');
    }

    public function test_participants_can_exchange_chat_messages_during_active_session(): void
    {
        $company = Company::query()->create([
            'name' => 'Acme',
            'subdomain' => 'acme',
            'status' => 'active',
            'email' => 'admin@acme.test',
        ]);

        $admin = $this->createCompanyUser('admin@acme.test', $company, ['view_live_screen']);
        $worker = $this->createCompanyUser('worker@acme.test', $company);

        $session = LiveViewSession::query()->create([
            'company_id' => $company->id,
            'admin_id' => $admin->id,
            'worker_id' => $worker->id,
            'status' => LiveViewSession::STATUS_ACTIVE,
        ]);

        $this->actingAs($admin)
            ->postJson("/api/live-view/sessions/{$session->id}/messages", [
                'body' => 'Please check the spreadsheet.',
            ])
            ->assertOk()
            ->assertJsonPath('message.body', 'Please check the spreadsheet.');

        $this->assertDatabaseHas('live_view_chat_messages', [
            'live_view_session_id' => $session->id,
            'sender_id' => $admin->id,
            'body' => 'Please check the spreadsheet.',
        ]);

        $this->assertDatabaseHas('webrtc_signals', [
            'to_user_id' => $worker->id,
            'signal_type' => 'chat-message',
        ]);

        $this->actingAs($worker)
            ->getJson("/api/live-view/sessions/{$session->id}/messages")
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.body', 'Please check the spreadsheet.');

        $second = $this->actingAs($admin)
            ->postJson("/api/live-view/sessions/{$session->id}/messages", [
                'body' => 'Second message.',
            ])
            ->assertOk()
            ->json('message');

        $this->actingAs($worker)
            ->getJson("/api/live-view/sessions/{$session->id}/messages?since_id={$second['id']}")
            ->assertOk()
            ->assertJsonCount(0, 'messages');

        $this->actingAs($worker)
            ->getJson("/api/live-view/sessions/{$session->id}/messages?since_id=".($second['id'] - 1))
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.body', 'Second message.');
    }
}
