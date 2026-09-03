<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\FacebookConversation;
use App\Models\FacebookConversationUserRead;
use App\Models\FacebookMessage;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FacebookConversationReadStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_one_agent_opening_a_conversation_does_not_mark_it_read_for_another(): void
    {
        [$alice, $bob, , $conversation] = $this->twoAgentsWithConversation();

        $this->actingAs($alice)
            ->getJson('/api/facebook/conversations/'.$conversation->id.'/messages')
            ->assertOk()
            ->assertJsonPath('conversation.is_read', true);

        $this->actingAs($bob)
            ->getJson('/api/facebook/conversations')
            ->assertOk()
            ->assertJsonFragment(['id' => $conversation->id, 'is_read' => false]);
    }

    public function test_a_new_user_with_no_history_defaults_to_unread(): void
    {
        [, , $company, $conversation] = $this->twoAgentsWithConversation();

        $role = Role::where('company_id', $company->id)->first();
        $carol = User::create([
            'name' => 'Carol',
            'email' => 'carol-fbread@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $this->actingAs($carol)
            ->getJson('/api/facebook/conversations')
            ->assertOk()
            ->assertJsonFragment(['id' => $conversation->id, 'is_read' => false]);
    }

    public function test_polling_the_open_conversation_does_not_reset_a_manual_mark_as_unread(): void
    {
        [$alice, , , $conversation] = $this->twoAgentsWithConversation();

        $this->actingAs($alice)
            ->getJson('/api/facebook/conversations/'.$conversation->id.'/messages')
            ->assertOk()
            ->assertJsonPath('conversation.is_read', true);

        $this->actingAs($alice)
            ->patchJson('/api/facebook/conversations/'.$conversation->id.'/read', ['is_read' => false])
            ->assertOk()
            ->assertJsonPath('conversation.is_read', false);

        // The page polls this same endpoint every few seconds while a conversation
        // stays open — that must not silently re-mark it read.
        $this->actingAs($alice)
            ->getJson('/api/facebook/conversations/'.$conversation->id.'/messages?poll=1')
            ->assertOk()
            ->assertJsonPath('conversation.is_read', false);
    }

    public function test_a_new_inbound_message_makes_an_already_read_conversation_unread_again(): void
    {
        [$alice, $bob, $company, $conversation] = $this->twoAgentsWithConversation();

        $this->actingAs($alice)->getJson('/api/facebook/conversations/'.$conversation->id.'/messages')->assertOk();
        $this->actingAs($bob)->getJson('/api/facebook/conversations/'.$conversation->id.'/messages')->assertOk();

        $this->assertTrue(
            FacebookConversationUserRead::where('facebook_conversation_id', $conversation->id)
                ->where('user_id', $alice->id)->value('is_read')
        );

        FacebookMessage::create([
            'company_id' => $company->id,
            'facebook_conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'type' => 'text',
            'text' => 'Are you still there?',
            'sent_at' => now(),
        ]);
        $conversation->update(['unread_count' => (int) $conversation->unread_count + 1]);
        FacebookConversationUserRead::where('facebook_conversation_id', $conversation->id)
            ->where('is_read', true)
            ->update(['is_read' => false]);

        $this->actingAs($alice)
            ->getJson('/api/facebook/conversations')
            ->assertOk()
            ->assertJsonFragment(['id' => $conversation->id, 'is_read' => false]);
    }

    public function test_unread_and_read_tabs_filter_the_conversation_list(): void
    {
        [$alice, , $company, $conversation] = $this->twoAgentsWithConversation();

        $otherConversation = FacebookConversation::create([
            'company_id' => $company->id,
            'channel' => 'messenger',
            'peer_id' => 'peer-other',
            'name' => 'Other Customer',
            'last_message_at' => now(),
        ]);

        $this->actingAs($alice)
            ->getJson('/api/facebook/conversations?read=unread')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($alice)->getJson('/api/facebook/conversations/'.$conversation->id.'/messages')->assertOk();

        $this->actingAs($alice)
            ->getJson('/api/facebook/conversations?read=read')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['id' => $conversation->id]);

        $this->actingAs($alice)
            ->getJson('/api/facebook/conversations?read=unread')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['id' => $otherConversation->id]);
    }

    public function test_pagination_still_works_after_the_per_user_read_join(): void
    {
        [$alice, , $company, $conversation] = $this->twoAgentsWithConversation();

        FacebookConversation::create([
            'company_id' => $company->id,
            'channel' => 'messenger',
            'peer_id' => 'peer-older',
            'name' => 'Older Customer',
            'last_message_at' => now()->subMinute(),
        ]);

        $this->actingAs($alice)
            ->getJson('/api/facebook/conversations?limit=1&before_id='.$conversation->id)
            ->assertOk();
    }

    public function test_sidebar_unread_count_is_per_agent(): void
    {
        [$alice, $bob, , $conversation] = $this->twoAgentsWithConversation();

        $this->actingAs($alice)->getJson('/api/facebook/conversations/'.$conversation->id.'/messages')->assertOk();

        $this->actingAs($alice)
            ->getJson('/api/notifications/channel-unread-counts')
            ->assertOk()
            ->assertJsonPath('data.facebook', 0);

        $this->actingAs($bob)
            ->getJson('/api/notifications/channel-unread-counts')
            ->assertOk()
            ->assertJsonPath('data.facebook', 1);
    }

    /**
     * @return array{0: User, 1: User, 2: Company, 3: FacebookConversation}
     */
    private function twoAgentsWithConversation(): array
    {
        $company = Company::create([
            'name' => 'LNS',
            'subdomain' => 'lns-fbread-'.uniqid(),
            'status' => 'active',
            'email' => 'admin-fbread-'.uniqid().'@lns.test',
            'timezone' => 'UTC',
        ]);

        $role = Role::create([
            'name' => 'Staff',
            'slug' => 'staff-fbread-'.uniqid(),
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        $permission = Permission::create([
            'name' => 'view_facebook',
            'slug' => 'view_facebook',
            'display_name' => 'Facebook & Instagram',
            'company_id' => $company->id,
        ]);
        $role->permissions()->attach($permission->id);

        $alice = User::create([
            'name' => 'Alice',
            'email' => 'alice-fbread-'.uniqid().'@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $bob = User::create([
            'name' => 'Bob',
            'email' => 'bob-fbread-'.uniqid().'@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
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

        return [$alice, $bob, $company, $conversation];
    }
}
