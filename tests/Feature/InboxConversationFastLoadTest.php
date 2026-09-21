<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\InboxConversation;
use App\Models\InboxMessage;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SharedInbox;
use App\Models\SharedInboxMember;
use App\Models\User;
use App\Services\OutlookMailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InboxConversationFastLoadTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_a_thread_returns_local_messages_without_graph_until_hydrate(): void
    {
        [$user, $conversation] = $this->agentWithThread();

        $this->partialMock(OutlookMailService::class, function ($mock) {
            $mock->shouldNotReceive('hydrateConversationBodies');
        });

        $this->actingAs($user)
            ->getJson('/api/inbox/conversations/'.$conversation->id)
            ->assertOk()
            ->assertJsonPath('conversation.id', $conversation->id)
            ->assertJsonPath('conversation.messages.0.body_text', 'Need a quote this week');
    }

    public function test_hydrate_fetches_outlook_bodies_for_the_open_thread(): void
    {
        [$user, $conversation] = $this->agentWithThread();

        $this->partialMock(OutlookMailService::class, function ($mock) {
            $mock->shouldReceive('hydrateConversationBodies')->once();
        });

        $this->actingAs($user)
            ->getJson('/api/inbox/conversations/'.$conversation->id.'?hydrate=1')
            ->assertOk()
            ->assertJsonPath('conversation.messages.0.body_text', 'Need a quote this week');
    }

    public function test_conversation_list_still_returns_open_threads(): void
    {
        [$user, $conversation] = $this->agentWithThread();

        $this->actingAs($user)
            ->getJson('/api/inbox/conversations?view=open')
            ->assertOk()
            ->assertJsonPath('conversations.0.id', $conversation->id)
            ->assertJsonPath('meta.has_more', false);
    }

    /**
     * @return array{0: User, 1: InboxConversation}
     */
    private function agentWithThread(): array
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-inbox-fast-'.uniqid(),
            'status' => 'active',
            'email' => 'admin-inbox-fast-'.uniqid().'@lns.test',
        ]);

        $role = Role::query()->create([
            'name' => 'Staff',
            'slug' => 'staff-inbox-fast-'.uniqid(),
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

        $user = User::query()->create([
            'name' => 'Alice',
            'email' => 'alice-inbox-fast-'.uniqid().'@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $inbox = SharedInbox::query()->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'name' => 'Support',
            'email' => 'support-fast@example.com',
            'type' => SharedInbox::TYPE_SHARED,
            'is_active' => true,
        ]);
        SharedInboxMember::query()->create([
            'shared_inbox_id' => $inbox->id,
            'user_id' => $user->id,
            'role' => 'member',
        ]);

        $conversation = InboxConversation::query()->create([
            'company_id' => $company->id,
            'shared_inbox_id' => $inbox->id,
            'folder' => 'inbox',
            'external_conversation_id' => 'conv-fast-'.uniqid(),
            'status' => 'open',
            'subject' => 'Storage quote',
            'from_name' => 'Customer',
            'from_email' => 'customer@example.com',
            'is_read' => false,
            'message_count' => 1,
            'last_message_at' => now(),
        ]);

        InboxMessage::query()->create([
            'inbox_conversation_id' => $conversation->id,
            'external_message_id' => 'msg-fast-'.uniqid(),
            'direction' => 'inbound',
            'from_name' => 'Customer',
            'from_email' => 'customer@example.com',
            'subject' => 'Storage quote',
            'body_text' => 'Need a quote this week',
            'sent_at' => now(),
        ]);

        return [$user, $conversation];
    }
}
