<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\FacebookConversation;
use App\Models\FacebookIntegration;
use App\Models\FacebookMessage;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FacebookMessengerSendTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_messenger_replies_use_the_page_graph_api_instead_of_twilio(): void
    {
        Http::fake([
            'https://graph.facebook.com/v21.0/222764457920914/messages' => Http::response([
                'message_id' => 'mid.GRAPH1',
            ], 200),
        ]);

        [$user, $conversation] = $this->agentWithMessengerThread();

        $this->actingAs($user)
            ->postJson('/api/facebook/conversations/'.$conversation->id.'/messages', [
                'type' => 'text',
                'text' => 'We can help with that',
            ])
            ->assertCreated()
            ->assertJsonPath('data.text', 'We can help with that')
            ->assertJsonPath('data.direction', 'outbound')
            ->assertJsonPath('data.status', 'sent');

        $this->assertSame('mid.GRAPH1', FacebookMessage::query()->where('direction', 'outbound')->value('mid'));
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/222764457920914/messages')
                && $request['recipient']['id'] === 'psid-customer'
                && $request['message']['text'] === 'We can help with that';
        });
        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), 'api.twilio.com');
        });
    }

    /**
     * @return array{0: User, 1: FacebookConversation}
     */
    private function agentWithMessengerThread(): array
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-fb-send-'.uniqid(),
            'status' => 'active',
            'email' => 'admin-fb-send-'.uniqid().'@lns.test',
            'timezone' => 'UTC',
        ]);

        $role = Role::query()->create([
            'name' => 'Staff',
            'slug' => 'staff-fb-send-'.uniqid(),
            'company_id' => $company->id,
            'is_active' => true,
        ]);
        $permission = Permission::query()->create([
            'name' => 'view_facebook',
            'slug' => 'view_facebook',
            'display_name' => 'Facebook & Instagram',
            'company_id' => $company->id,
        ]);
        $role->permissions()->attach($permission->id);

        $user = User::query()->create([
            'name' => 'Staff',
            'email' => 'staff-fb-send-'.uniqid().'@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        FacebookIntegration::query()->create([
            'company_id' => $company->id,
            'page_id' => '222764457920914',
            'page_access_token' => 'page-token',
            'page_name' => 'Loc&Stor 24/7 Self Storage Philippines',
            'webhook_key' => 'fb-send-'.uniqid(),
            'webhook_verify_token' => 'verify-token',
            'is_active' => true,
        ]);

        $conversation = FacebookConversation::query()->create([
            'company_id' => $company->id,
            'channel' => 'messenger',
            'peer_id' => 'psid-customer',
            'name' => 'Customer',
            'last_message_at' => now(),
        ]);

        return [$user, $conversation];
    }
}
