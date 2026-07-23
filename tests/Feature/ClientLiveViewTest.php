<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Company;
use App\Models\LiveViewSession;
use App\Models\ScreenRecording;
use App\Models\TimeTracking;
use App\Models\User;
use App\Services\LiveViewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClientLiveViewTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        return Company::query()->create([
            'name' => 'Acme',
            'subdomain' => 'acme',
            'status' => 'active',
            'email' => 'admin@acme.test',
        ]);
    }

    private function makeWorker(Company $company, int $id): User
    {
        return User::query()->create([
            'id' => $id,
            'name' => 'Worker '.$id,
            'email' => "worker{$id}@acme.test",
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'status' => 'active',
        ]);
    }

    private function makeClientUser(Client $client, int $id): ClientUser
    {
        return ClientUser::query()->create([
            'id' => $id,
            'client_id' => $client->id,
            'name' => 'Client User '.$id,
            'email' => "client{$id}@acme.test",
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
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

    public function test_client_can_start_live_view_session_for_assigned_employee(): void
    {
        $company = $this->makeCompany();
        $client = Client::query()->create(['company_id' => $company->id, 'name' => 'Client Co', 'contact_person' => 'Jane Doe', 'email' => 'client@clientco.test', 'status' => 'active']);
        $clientUser = $this->makeClientUser($client, 1);
        $worker = $this->makeWorker($company, 1);
        $client->employees()->attach($worker->id);
        $this->makeWorkerLive($worker);

        $response = $this->actingAs($clientUser, 'client')->postJson('/client/api/live-view/sessions', [
            'worker_id' => $worker->id,
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('live_view_sessions', [
            'admin_id' => $clientUser->id,
            'admin_type' => 'client',
            'worker_id' => $worker->id,
            'status' => LiveViewSession::STATUS_PENDING,
        ]);

        $this->assertDatabaseHas('webrtc_signals', [
            'from_type' => 'client',
            'to_user_id' => $worker->id,
            'to_type' => 'user',
            'signal_type' => 'live-view-request',
        ]);
    }

    public function test_client_cannot_start_session_for_unassigned_employee(): void
    {
        $company = $this->makeCompany();
        $client = Client::query()->create(['company_id' => $company->id, 'name' => 'Client Co', 'contact_person' => 'Jane Doe', 'email' => 'client@clientco.test', 'status' => 'active']);
        $clientUser = $this->makeClientUser($client, 1);
        $worker = $this->makeWorker($company, 1);
        $this->makeWorkerLive($worker);

        $response = $this->actingAs($clientUser, 'client')->postJson('/client/api/live-view/sessions', [
            'worker_id' => $worker->id,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('live_view_sessions', ['admin_id' => $clientUser->id]);
    }

    public function test_worker_can_signal_back_to_client_viewer_and_client_can_pull_it(): void
    {
        $company = $this->makeCompany();
        $client = Client::query()->create(['company_id' => $company->id, 'name' => 'Client Co', 'contact_person' => 'Jane Doe', 'email' => 'client@clientco.test', 'status' => 'active']);
        $clientUser = $this->makeClientUser($client, 1);
        $worker = $this->makeWorker($company, 1);
        $client->employees()->attach($worker->id);
        $this->makeWorkerLive($worker);

        $start = $this->actingAs($clientUser, 'client')->postJson('/client/api/live-view/sessions', [
            'worker_id' => $worker->id,
        ])->assertOk();

        $sessionId = $start->json('session.id');

        // Worker answers back to the client viewer via the shared worker-facing endpoint.
        $this->actingAs($worker)->postJson('/api/live-view/signals', [
            'to_user_id' => $clientUser->id,
            'session_id' => $sessionId,
            'signal_type' => 'answer',
            'payload' => ['answer' => ['type' => 'answer', 'sdp' => 'v=0']],
        ])->assertOk();

        $this->assertDatabaseHas('webrtc_signals', [
            'from_type' => 'user',
            'from_user_id' => $worker->id,
            'to_type' => 'client',
            'to_user_id' => $clientUser->id,
            'signal_type' => 'answer',
        ]);

        $pull = $this->actingAs($clientUser, 'client')->getJson("/client/api/live-view/signals?session_id={$sessionId}");
        $pull->assertOk();
        $this->assertCount(1, $pull->json('signals'));
        $this->assertSame('answer', $pull->json('signals.0.signal_type'));
    }

    public function test_client_and_user_with_colliding_ids_do_not_cross_access_sessions(): void
    {
        $company = $this->makeCompany();
        $client = Client::query()->create(['company_id' => $company->id, 'name' => 'Client Co', 'contact_person' => 'Jane Doe', 'email' => 'client@clientco.test', 'status' => 'active']);

        // Deliberately give the ClientUser the same numeric id as a real admin User.
        $admin = User::query()->create([
            'id' => 42,
            'name' => 'Admin Forty Two',
            'email' => 'admin42@acme.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'status' => 'active',
        ]);
        $clientUser = $this->makeClientUser($client, 42);
        $worker = $this->makeWorker($company, 2);
        $client->employees()->attach($worker->id);
        $this->makeWorkerLive($worker);

        $start = $this->actingAs($clientUser, 'client')->postJson('/client/api/live-view/sessions', [
            'worker_id' => $worker->id,
        ])->assertOk();

        $sessionId = $start->json('session.id');

        // The admin with the colliding id must NOT be able to access the client's session.
        $this->actingAs($admin)->getJson("/api/live-view/sessions/{$sessionId}")
            ->assertStatus(403);
    }

    public function test_client_can_end_session(): void
    {
        $company = $this->makeCompany();
        $client = Client::query()->create(['company_id' => $company->id, 'name' => 'Client Co', 'contact_person' => 'Jane Doe', 'email' => 'client@clientco.test', 'status' => 'active']);
        $clientUser = $this->makeClientUser($client, 1);
        $worker = $this->makeWorker($company, 1);
        $client->employees()->attach($worker->id);
        $this->makeWorkerLive($worker);

        $start = $this->actingAs($clientUser, 'client')->postJson('/client/api/live-view/sessions', [
            'worker_id' => $worker->id,
        ])->assertOk();

        $sessionId = $start->json('session.id');

        $this->actingAs($clientUser, 'client')->postJson("/client/api/live-view/sessions/{$sessionId}/end", [
            'reason' => 'client_closed',
        ])->assertOk()->assertJsonPath('session.status', LiveViewSession::STATUS_ENDED);

        $this->assertDatabaseHas('live_view_sessions', [
            'id' => $sessionId,
            'status' => LiveViewSession::STATUS_ENDED,
        ]);
    }
}
