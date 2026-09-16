<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppIntegration;
use App\Models\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WhatsAppCloudApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_meta_webhook_verification_returns_the_challenge(): void
    {
        $company = $this->makeCompany();
        $integration = $this->makeIntegration($company, [
            'webhook_key' => 'wa-verify-key',
            'webhook_verify_token' => 'crm-verify-token',
        ]);

        $this->get('/webhooks/whatsapp/'.$integration->webhook_key.'?hub.mode=subscribe&hub.verify_token=crm-verify-token&hub.challenge=1158201444')
            ->assertOk()
            ->assertSee('1158201444', false);
    }

    public function test_meta_webhook_verification_rejects_a_bad_token(): void
    {
        $company = $this->makeCompany();
        $integration = $this->makeIntegration($company, [
            'webhook_key' => 'wa-verify-bad',
            'webhook_verify_token' => 'crm-verify-token',
        ]);

        $this->get('/webhooks/whatsapp/'.$integration->webhook_key.'?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=1')
            ->assertForbidden();
    }

    public function test_cloud_api_webhook_stores_an_inbound_text_message(): void
    {
        Notification::fake();
        $company = $this->makeCompany();
        $integration = $this->makeIntegration($company, [
            'webhook_key' => 'wa-inbound-key',
            'phone_number_id' => '106540352242922',
        ]);

        $payload = $this->inboundTextPayload('wamid.IN1', '15559876543', 'Hello from Cloud API', '106540352242922');

        $this->postJson('/webhooks/whatsapp/'.$integration->webhook_key, $payload)
            ->assertOk()
            ->assertSee('EVENT_RECEIVED', false);

        $conversation = WhatsAppConversation::query()->where('company_id', $company->id)->first();
        $this->assertNotNull($conversation);
        $this->assertSame('+15559876543', $conversation->wa_id);
        $this->assertSame('Alice', $conversation->name);

        $message = WhatsAppMessage::query()->where('wamid', 'wamid.IN1')->first();
        $this->assertNotNull($message);
        $this->assertSame('inbound', $message->direction);
        $this->assertSame('Hello from Cloud API', $message->text);
        $this->assertSame('received', $message->status);
        $this->assertTrue($conversation->fresh()->window_expires_at->isFuture());
    }

    public function test_cloud_api_webhook_updates_outbound_status(): void
    {
        Notification::fake();
        $company = $this->makeCompany();
        $integration = $this->makeIntegration($company, ['webhook_key' => 'wa-status-key']);
        $conversation = WhatsAppConversation::query()->create([
            'company_id' => $company->id,
            'wa_id' => '+15559876543',
            'phone' => '15559876543',
            'name' => 'Alice',
        ]);
        WhatsAppMessage::query()->create([
            'company_id' => $company->id,
            'whatsapp_conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'wamid' => 'wamid.OUT1',
            'type' => 'text',
            'text' => 'Hi',
            'status' => 'accepted',
            'sent_at' => now(),
        ]);

        $this->postJson('/webhooks/whatsapp/'.$integration->webhook_key, [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => '102290129340398',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => [
                            'display_phone_number' => '15551234567',
                            'phone_number_id' => '106540352242922',
                        ],
                        'statuses' => [[
                            'id' => 'wamid.OUT1',
                            'status' => 'delivered',
                            'timestamp' => '1749416383',
                            'recipient_id' => '15559876543',
                        ]],
                    ],
                ]],
            ]],
        ])->assertOk();

        $this->assertSame('delivered', WhatsAppMessage::query()->where('wamid', 'wamid.OUT1')->value('status'));
    }

    public function test_cloud_api_webhook_still_stores_inbound_when_app_secret_signature_is_wrong(): void
    {
        Notification::fake();
        $company = $this->makeCompany();
        $integration = $this->makeIntegration($company, [
            'webhook_key' => 'wa-sig-key',
            'app_secret' => 'meta-app-secret',
        ]);

        $this->postJson('/webhooks/whatsapp/'.$integration->webhook_key, $this->inboundTextPayload('wamid.SIG', '15559876543', 'Hello'), [
            'X-Hub-Signature-256' => 'sha256=deadbeef',
        ])->assertOk()
            ->assertSee('EVENT_RECEIVED', false);

        $this->assertSame('Hello', WhatsAppMessage::query()->where('wamid', 'wamid.SIG')->value('text'));
    }

    public function test_cloud_api_webhook_stores_inbound_when_phone_number_id_differs(): void
    {
        Notification::fake();
        $company = $this->makeCompany();
        $integration = $this->makeIntegration($company, [
            'webhook_key' => 'wa-mismatch-key',
            'phone_number_id' => '106540352242922',
        ]);

        $payload = $this->inboundTextPayload('wamid.MISMATCH', '15559876543', 'Still import me', '999999999999');

        $this->postJson('/webhooks/whatsapp/'.$integration->webhook_key, $payload)
            ->assertOk();

        $this->assertSame('Still import me', WhatsAppMessage::query()->where('wamid', 'wamid.MISMATCH')->value('text'));
    }

    public function test_saving_whatsapp_integration_verifies_the_meta_token(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'subscribed_apps')) {
                return Http::response(['success' => true], 200);
            }

            return Http::response([
                'id' => '106540352242922',
                'display_phone_number' => '+15551234567',
                'verified_name' => 'Acme Support',
            ], 200);
        });

        [$user] = $this->userWithPermissions(['view_integrations']);

        $this->actingAs($user)
            ->postJson('/api/integrations/whatsapp', [
                'phone_number_id' => '106540352242922',
                'waba_id' => '102290129340398',
                'access_token' => 'EAAB-permanent-token',
                'app_secret' => 'app-secret',
                'welcome_message' => 'Thanks for messaging us.',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'connected')
            ->assertJsonPath('integration.phone_number_id', '106540352242922')
            ->assertJsonPath('integration.business_name', 'Acme Support')
            ->assertJsonPath('integration.has_access_token', true)
            ->assertJsonPath('integration.has_app_secret', true);

        $integration = WhatsAppIntegration::query()->first();
        $this->assertNotNull($integration);
        $this->assertTrue($integration->isCloudConnected());
        $this->assertNotEmpty($integration->webhook_verify_token);
        $this->assertSame('+15551234567', $integration->from_number);
    }

    public function test_saving_whatsapp_integration_rejects_an_invalid_token(): void
    {
        Http::fake([
            '*' => Http::response(['error' => ['message' => 'Invalid OAuth access token.', 'code' => 190]], 400),
        ]);

        [$user] = $this->userWithPermissions(['view_integrations']);

        $this->actingAs($user)
            ->postJson('/api/integrations/whatsapp', [
                'phone_number_id' => '106540352242922',
                'access_token' => 'expired-token',
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['error' => 'Your WhatsApp Cloud API access token expired. Open Integrations → WhatsApp Business, paste a permanent System User token from Meta for Developers → WhatsApp → API Setup (or Business Settings → System Users), then Save.']);
    }

    public function test_bootstrap_is_connected_only_with_cloud_api_credentials(): void
    {
        [$user, $company] = $this->userWithPermissions(['view_whatsapp']);
        $this->makeIntegration($company);

        $this->actingAs($user)
            ->getJson('/api/whatsapp/bootstrap')
            ->assertOk()
            ->assertJsonPath('connected', true)
            ->assertJsonPath('account.has_access_token', true);
    }

    public function test_sending_a_whatsapp_reply_uses_the_cloud_api(): void
    {
        Http::fake([
            'https://graph.facebook.com/v21.0/106540352242922/messages' => Http::response([
                'messaging_product' => 'whatsapp',
                'messages' => [['id' => 'wamid.OUT2', 'message_status' => 'accepted']],
            ], 200),
        ]);

        [$user, $company] = $this->userWithPermissions(['view_whatsapp']);
        $this->makeIntegration($company);
        $conversation = WhatsAppConversation::query()->create([
            'company_id' => $company->id,
            'wa_id' => '+15559876543',
            'phone' => '15559876543',
            'name' => 'Alice',
        ]);

        $this->actingAs($user)
            ->postJson('/api/whatsapp/conversations/'.$conversation->id.'/messages', [
                'type' => 'text',
                'text' => 'On our way',
            ])
            ->assertCreated()
            ->assertJsonPath('data.text', 'On our way')
            ->assertJsonPath('data.direction', 'outbound');

        $this->assertSame('wamid.OUT2', WhatsAppMessage::query()->value('wamid'));
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/106540352242922/messages')
                && $request['to'] === '15559876543'
                && $request['type'] === 'text'
                && $request['text']['body'] === 'On our way';
        });
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeIntegration(Company $company, array $overrides = []): WhatsAppIntegration
    {
        return WhatsAppIntegration::query()->create(array_merge([
            'company_id' => $company->id,
            'phone_number_id' => '106540352242922',
            'waba_id' => '102290129340398',
            'access_token' => 'test-token',
            'from_number' => '+15551234567',
            'display_phone_number' => '+15551234567',
            'webhook_key' => 'wa-key-'.uniqid(),
            'webhook_verify_token' => 'verify-token',
            'is_active' => true,
        ], $overrides));
    }

    private function makeCompany(): Company
    {
        return Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-wa-'.uniqid(),
            'status' => 'active',
            'email' => 'admin-wa-'.uniqid().'@lns.test',
            'timezone' => 'UTC',
        ]);
    }

    /**
     * @param  list<string>  $slugs
     * @return array{0: User, 1: Company}
     */
    private function userWithPermissions(array $slugs): array
    {
        $company = $this->makeCompany();
        $role = Role::query()->create([
            'name' => 'Staff',
            'slug' => 'staff-wa-'.uniqid(),
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
            'name' => 'Staff',
            'email' => 'staff-wa-'.uniqid().'@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        return [$user, $company];
    }

    /**
     * @return array<string, mixed>
     */
    private function inboundTextPayload(string $wamid, string $from, string $body, string $phoneNumberId = '106540352242922'): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => '102290129340398',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => [
                            'display_phone_number' => '15551234567',
                            'phone_number_id' => $phoneNumberId,
                        ],
                        'contacts' => [[
                            'profile' => ['name' => 'Alice'],
                            'wa_id' => $from,
                        ]],
                        'messages' => [[
                            'from' => $from,
                            'id' => $wamid,
                            'timestamp' => '1749416383',
                            'type' => 'text',
                            'text' => ['body' => $body],
                        ]],
                    ],
                ]],
            ]],
        ];
    }
}
