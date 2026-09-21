<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\FacebookConversation;
use App\Models\FacebookIntegration;
use App\Models\FacebookMessage;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\FacebookGraphHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FacebookConversationFastLoadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_conversation_list_and_poll_return_local_rows_without_graph(): void
    {
        [$user, $conversation] = $this->agentWithThread();

        $this->mock(FacebookGraphHistoryService::class, function ($mock) {
            $mock->shouldNotReceive('thread');
        });

        $this->actingAs($user)
            ->getJson('/api/facebook/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.id', $conversation->id)
            ->assertJsonPath('has_more', false);

        $this->actingAs($user)
            ->getJson('/api/facebook/conversations?poll=1')
            ->assertOk()
            ->assertJsonPath('data.0.id', $conversation->id);
    }

    public function test_opening_messages_does_not_call_graph_until_hydrate(): void
    {
        [$user, $conversation] = $this->agentWithThread();

        $this->mock(FacebookGraphHistoryService::class, function ($mock) {
            $mock->shouldNotReceive('thread');
        });

        $this->actingAs($user)
            ->getJson('/api/facebook/conversations/'.$conversation->id.'/messages')
            ->assertOk()
            ->assertJsonPath('data.0.text', 'Hi there')
            ->assertJsonPath('conversation.is_read', true);

        $this->actingAs($user)
            ->getJson('/api/facebook/conversations/'.$conversation->id.'/messages?poll=1')
            ->assertOk()
            ->assertJsonPath('data.0.text', 'Hi there');
    }

    public function test_hydrate_imports_the_graph_thread(): void
    {
        Cache::flush();
        [$user, $conversation] = $this->agentWithThread();

        $graph = \Mockery::mock(FacebookGraphHistoryService::class);
        $graph->shouldReceive('thread')->once()->andReturn([]);
        $graph->shouldReceive('lastError')->andReturn(null);
        $this->app->instance(FacebookGraphHistoryService::class, $graph);

        $this->actingAs($user)
            ->getJson('/api/facebook/conversations/'.$conversation->id.'/messages?hydrate=1')
            ->assertOk()
            ->assertJsonPath('data.0.text', 'Hi there');
    }

    /**
     * @return array{0: User, 1: FacebookConversation}
     */
    private function agentWithThread(): array
    {
        $company = Company::create([
            'name' => 'LNS',
            'subdomain' => 'lns-fbfast-'.uniqid(),
            'status' => 'active',
            'email' => 'admin-fbfast-'.uniqid().'@lns.test',
            'timezone' => 'UTC',
        ]);

        $role = Role::create([
            'name' => 'Staff',
            'slug' => 'staff-fbfast-'.uniqid(),
            'company_id' => $company->id,
            'is_active' => true,
        ]);
        $role->permissions()->attach(Permission::create([
            'name' => 'view_facebook',
            'slug' => 'view_facebook',
            'display_name' => 'Facebook & Instagram',
            'company_id' => $company->id,
        ])->id);

        $user = User::create([
            'name' => 'Alice',
            'email' => 'alice-fbfast-'.uniqid().'@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        FacebookIntegration::query()->create([
            'company_id' => $company->id,
            'page_id' => '222764457920914',
            'page_access_token' => 'page-token',
            'page_name' => 'LNS Page',
            'webhook_key' => 'fb-fast-'.uniqid(),
            'webhook_verify_token' => 'verify-token',
            'is_active' => true,
        ]);

        $conversation = FacebookConversation::create([
            'company_id' => $company->id,
            'channel' => 'messenger',
            'peer_id' => 'peer-'.uniqid(),
            'name' => 'Customer',
            'last_message_at' => now(),
        ]);

        FacebookMessage::create([
            'company_id' => $company->id,
            'facebook_conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'type' => 'text',
            'text' => 'Hi there',
            'sent_at' => now(),
        ]);

        return [$user, $conversation];
    }
}
