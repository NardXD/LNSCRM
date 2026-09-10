<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\InboxConversation;
use App\Models\InboxMessage;
use App\Models\InboxTemplate;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadIdentity;
use App\Models\MessageTemplate;
use App\Models\OutlookMailAccount;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SharedInbox;
use App\Models\SharedInboxMember;
use App\Models\User;
use App\Models\WhatsAppConversation;
use App\Services\InboxReplyService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LeadMailFollowUpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        Carbon::setTestNow(Carbon::parse('2026-08-26 12:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_message_channels_skip_whatsapp_without_thread(): void
    {
        [$user, $company] = $this->userWithPermissions(['view_leads', 'view_whatsapp', 'view_sms', 'send_sms']);
        $lead = $this->makeLead($company, 'Email Only', 'new', now()->subDays(1));
        $lead->addIdentity(LeadIdentity::TYPE_EMAIL, 'only@example.com');

        $channels = $this->actingAs($user)
            ->getJson('/api/leads/'.$lead->id.'/message-channels')
            ->assertOk()
            ->json('data.channels');

        $whatsapp = collect($channels)->firstWhere('id', 'whatsapp');
        $this->assertFalse($whatsapp['available']);
        $this->assertStringContainsString('WhatsApp', (string) $whatsapp['reason']);

        $this->actingAs($user)
            ->postJson('/api/leads/'.$lead->id.'/messages', [
                'channel' => 'whatsapp',
                'body' => 'Hello',
            ])
            ->assertStatus(422);

        WhatsAppConversation::query()->create([
            'company_id' => $company->id,
            'wa_id' => '639171111111',
            'phone' => '+639171111111',
            'name' => 'Email Only',
        ]);
        $lead->addIdentity(LeadIdentity::TYPE_PHONE, '+639171111111');

        $withPhone = $this->actingAs($user)
            ->getJson('/api/leads/'.$lead->fresh()->id.'/message-channels')
            ->assertOk()
            ->json('data.channels');

        $whatsappReady = collect($withPhone)->firstWhere('id', 'whatsapp');
        $this->assertTrue($whatsappReady['available']);

        $this->actingAs($user)
            ->postJson('/api/leads/'.$lead->id.'/messages', [
                'channel' => 'whatsapp',
                'body' => 'Following up',
            ])
            ->assertStatus(422);

        MessageTemplate::query()->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'channel' => MessageTemplate::CHANNEL_WHATSAPP,
            'name' => 'Day 2 ping',
            'body_text' => 'Hi {{first_name}}',
        ]);

        $this->assertSame(0, LeadActivity::query()->where('lead_id', $lead->id)->where('action', LeadActivity::TEMPLATE_SENT)->count());
    }

    public function test_mail_follow_up_sends_html_and_merges_tokens(): void
    {
        [$user, $company] = $this->userWithPermissions(['view_leads', 'view_inbox']);
        $lead = $this->makeLead($company, 'Anna Cruz', 'new', now()->subDays(2));
        $lead->addIdentity(LeadIdentity::TYPE_EMAIL, 'anna@example.com');

        $account = OutlookMailAccount::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'email' => 'inbox@example.com',
            'access_token' => 'token',
            'is_active' => true,
        ]);
        $inbox = SharedInbox::query()->create([
            'company_id' => $company->id,
            'outlook_mail_account_id' => $account->id,
            'created_by' => $user->id,
            'name' => 'Personal',
            'email' => 'inbox@example.com',
            'type' => SharedInbox::TYPE_PERSONAL,
            'is_active' => true,
        ]);
        InboxConversation::query()->create([
            'company_id' => $company->id,
            'shared_inbox_id' => $inbox->id,
            'lead_id' => $lead->id,
            'external_conversation_id' => 'conv-html-1',
            'subject' => 'Quote',
            'from_name' => 'Anna Cruz',
            'from_email' => 'anna@example.com',
            'status' => 'open',
        ]);
        InboxTemplate::query()->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'name' => 'HTML follow-up',
            'subject' => 'Checking in',
            'body_html' => '<p>Hi <strong>{{first_name}}</strong></p>',
            'body_text' => 'Hi {{first_name}}',
        ]);

        $channels = $this->actingAs($user)
            ->getJson('/api/leads/'.$lead->id.'/message-channels')
            ->assertOk()
            ->json('data.channels');
        $mail = collect($channels)->firstWhere('id', 'inbox');
        $this->assertTrue($mail['available']);
        $this->assertStringContainsString('<strong>{{first_name}}</strong>', $mail['templates'][0]['body']);

        $captured = null;
        $this->mock(InboxReplyService::class, function ($mock) use (&$captured) {
            $mock->shouldReceive('send')
                ->once()
                ->andReturnUsing(function ($conversation, $sharedInbox, $actor, $payload) use (&$captured) {
                    $captured = $payload['body'];

                    return [
                        'message' => new InboxMessage(['body_html' => $payload['body']]),
                        'conversation' => $conversation,
                    ];
                });
        });

        $this->actingAs($user)
            ->postJson('/api/leads/'.$lead->id.'/messages', [
                'channel' => 'inbox',
                'body' => '<p>Hi <strong>{{first_name}}</strong>.</p><p><a href="https://example.com">Open</a></p>',
                'subject' => 'Checking in',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(
            '<p>Hi <strong>Anna</strong>.</p><p><a href="https://example.com">Open</a></p>',
            $captured
        );
        $this->assertSame(1, LeadActivity::query()->where('lead_id', $lead->id)->where('action', LeadActivity::TEMPLATE_SENT)->count());
    }

    public function test_mail_follow_up_is_available_from_lead_email_without_thread(): void
    {
        [$user, $company] = $this->userWithPermissions(['view_leads', 'view_inbox']);
        $lead = $this->makeLead($company, 'Anna Cruz', 'new', now()->subDays(2));
        $lead->addIdentity(LeadIdentity::TYPE_EMAIL, 'anna@example.com');

        $account = OutlookMailAccount::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'email' => 'inbox@example.com',
            'access_token' => 'token',
            'is_active' => true,
        ]);
        SharedInbox::query()->create([
            'company_id' => $company->id,
            'outlook_mail_account_id' => $account->id,
            'created_by' => $user->id,
            'name' => 'Personal',
            'email' => 'inbox@example.com',
            'type' => SharedInbox::TYPE_PERSONAL,
            'is_active' => true,
        ]);

        $channels = $this->actingAs($user)
            ->getJson('/api/leads/'.$lead->id.'/message-channels')
            ->assertOk()
            ->json('data.channels');
        $mail = collect($channels)->firstWhere('id', 'inbox');
        $this->assertTrue($mail['available']);
        $this->assertNull($mail['conversation_id']);
        $this->assertNotEmpty($mail['mailboxes']);
        $this->assertSame('Personal', $mail['mailboxes'][0]['name']);
        $this->assertSame(['anna@example.com'], $mail['emails']);

        $captured = null;
        $this->mock(InboxReplyService::class, function ($mock) use (&$captured, $lead) {
            $mock->shouldReceive('send')->never();
            $mock->shouldReceive('sendCompose')
                ->once()
                ->andReturnUsing(function ($inbox, $actor, $payload) use (&$captured, $lead) {
                    $captured = $payload;
                    $conversation = InboxConversation::query()->create([
                        'company_id' => $lead->company_id,
                        'shared_inbox_id' => $inbox->id,
                        'external_conversation_id' => 'local-compose-test',
                        'subject' => $payload['subject'],
                        'from_name' => $actor->name,
                        'from_email' => $inbox->email,
                        'status' => 'sent',
                    ]);

                    return [
                        'message' => new InboxMessage(['body_html' => $payload['body']]),
                        'conversation' => $conversation,
                    ];
                });
        });

        $this->actingAs($user)
            ->postJson('/api/leads/'.$lead->id.'/messages', [
                'channel' => 'inbox',
                'body' => '<p>Hi <strong>{{first_name}}</strong></p>',
                'subject' => 'Checking in',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('anna@example.com', $captured['to']);
        $this->assertSame('Checking in', $captured['subject']);
        $this->assertSame('<p>Hi <strong>Anna</strong></p>', $captured['body']);
        $this->assertSame($lead->id, InboxConversation::query()->where('external_conversation_id', 'local-compose-test')->value('lead_id'));
    }

    public function test_mail_follow_up_defaults_to_all_emails_and_can_send_a_subset(): void
    {
        [$user, $company] = $this->userWithPermissions(['view_leads', 'view_inbox']);
        $lead = $this->makeLead($company, 'Anna Cruz', 'new', now()->subDays(2));
        $lead->addIdentity(LeadIdentity::TYPE_EMAIL, 'anna@example.com');
        $lead->addIdentity(LeadIdentity::TYPE_EMAIL, 'anna.work@example.com');

        $account = OutlookMailAccount::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'email' => 'inbox@example.com',
            'access_token' => 'token',
            'is_active' => true,
        ]);
        SharedInbox::query()->create([
            'company_id' => $company->id,
            'outlook_mail_account_id' => $account->id,
            'created_by' => $user->id,
            'name' => 'Personal',
            'email' => 'inbox@example.com',
            'type' => SharedInbox::TYPE_PERSONAL,
            'is_active' => true,
        ]);

        $channels = $this->actingAs($user)
            ->getJson('/api/leads/'.$lead->id.'/message-channels')
            ->assertOk()
            ->json('data.channels');
        $mail = collect($channels)->firstWhere('id', 'inbox');
        $this->assertEqualsCanonicalizing(
            ['anna@example.com', 'anna.work@example.com'],
            $mail['emails']
        );

        $captured = [];
        $this->mock(InboxReplyService::class, function ($mock) use (&$captured, $lead) {
            $mock->shouldReceive('sendCompose')
                ->once()
                ->andReturnUsing(function ($inbox, $actor, $payload) use (&$captured, $lead) {
                    $captured[] = $payload['to'];
                    $conversation = InboxConversation::query()->create([
                        'company_id' => $lead->company_id,
                        'shared_inbox_id' => $inbox->id,
                        'external_conversation_id' => 'local-to-all',
                        'subject' => $payload['subject'],
                        'from_name' => $actor->name,
                        'from_email' => $inbox->email,
                        'status' => 'sent',
                    ]);

                    return [
                        'message' => new InboxMessage(['body_html' => $payload['body']]),
                        'conversation' => $conversation,
                    ];
                });
            $mock->shouldReceive('send')
                ->once()
                ->andReturnUsing(function ($conversation, $inbox, $actor, $payload) use (&$captured) {
                    $captured[] = $payload['to'];

                    return [
                        'message' => new InboxMessage(['body_html' => $payload['body']]),
                        'conversation' => $conversation,
                    ];
                });
        });

        $this->actingAs($user)
            ->postJson('/api/leads/'.$lead->id.'/messages', [
                'channel' => 'inbox',
                'body' => '<p>Hello</p>',
                'subject' => 'All inboxes',
            ])
            ->assertOk();

        $this->actingAs($user)
            ->postJson('/api/leads/'.$lead->id.'/messages', [
                'channel' => 'inbox',
                'body' => '<p>Hello</p>',
                'subject' => 'Work only',
                'to' => ['anna.work@example.com'],
            ])
            ->assertOk();

        $this->assertEqualsCanonicalizing(
            ['anna@example.com', 'anna.work@example.com'],
            array_map('trim', explode(',', (string) $captured[0]))
        );
        $this->assertSame('anna.work@example.com', $captured[1]);
    }

    public function test_mail_follow_up_can_send_from_selected_shared_mailbox(): void
    {
        [$user, $company] = $this->userWithPermissions(['view_leads', 'view_inbox']);
        $lead = $this->makeLead($company, 'Anna Cruz', 'new', now()->subDays(2));
        $lead->addIdentity(LeadIdentity::TYPE_EMAIL, 'anna@example.com');

        $account = OutlookMailAccount::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'email' => 'personal@example.com',
            'access_token' => 'token',
            'is_active' => true,
        ]);
        SharedInbox::query()->create([
            'company_id' => $company->id,
            'outlook_mail_account_id' => $account->id,
            'created_by' => $user->id,
            'name' => 'Personal',
            'email' => 'personal@example.com',
            'type' => SharedInbox::TYPE_PERSONAL,
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
            'role' => 'member',
        ]);

        $channels = $this->actingAs($user)
            ->getJson('/api/leads/'.$lead->id.'/message-channels')
            ->assertOk()
            ->json('data.channels');
        $mail = collect($channels)->firstWhere('id', 'inbox');
        $this->assertTrue(collect($mail['mailboxes'])->contains(fn ($box) => $box['id'] === $shared->id && $box['type'] === 'shared'));

        $sentFrom = null;
        $this->mock(InboxReplyService::class, function ($mock) use (&$sentFrom, $lead) {
            $mock->shouldReceive('send')->never();
            $mock->shouldReceive('sendCompose')
                ->once()
                ->andReturnUsing(function ($inbox, $actor, $payload) use (&$sentFrom, $lead) {
                    $sentFrom = (int) $inbox->id;
                    $conversation = InboxConversation::query()->create([
                        'company_id' => $lead->company_id,
                        'shared_inbox_id' => $inbox->id,
                        'external_conversation_id' => 'local-shared-compose',
                        'subject' => $payload['subject'],
                        'from_name' => $actor->name,
                        'from_email' => $inbox->email,
                        'status' => 'sent',
                    ]);

                    return [
                        'message' => new InboxMessage(['body_html' => $payload['body']]),
                        'conversation' => $conversation,
                    ];
                });
        });

        $this->actingAs($user)
            ->postJson('/api/leads/'.$lead->id.'/messages', [
                'channel' => 'inbox',
                'inbox_id' => $shared->id,
                'body' => '<p>Hello</p>',
                'subject' => 'Shared follow-up',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame($shared->id, $sentFrom);
    }

    /**
     * @param  list<string>  $slugs
     * @return array{0: User, 1: Company}
     */
    private function userWithPermissions(array $slugs): array
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-followup',
            'status' => 'active',
            'email' => 'admin-followup@lns.test',
            'timezone' => 'UTC',
        ]);

        $role = Role::query()->create([
            'name' => 'Manager',
            'slug' => 'manager-followup',
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
            'email' => 'manager-followup@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        return [$user, $company];
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function makeLead(Company $company, string $name, string $status, Carbon $createdAt, array $extra = []): Lead
    {
        $lead = Lead::query()->create(array_merge([
            'company_id' => $company->id,
            'name' => $name,
            'first_name' => explode(' ', $name)[0],
            'status' => $status,
        ], $extra));
        $lead->created_at = $createdAt;
        $lead->updated_at = $createdAt;
        $lead->save();

        return $lead->fresh();
    }
}
