<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\InboxConversation;
use App\Models\InboxConversationFollower;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SharedInbox;
use App\Models\SharedInboxMember;
use App\Models\User;
use App\Notifications\InboxThreadUpdateNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InboxParticipantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversation_participants_default_to_empty_not_all_inbox_members(): void
    {
        [$user, $other, $third, $inbox, $conversation] = $this->sharedThread();

        $this->actingAs($user)
            ->getJson('/api/inbox/conversations/'.$conversation->id)
            ->assertOk()
            ->assertJsonPath('conversation.participants', [])
            ->assertJsonPath('conversation.member_reads', []);
    }

    public function test_invite_adds_participants_without_listing_uninvited_members(): void
    {
        Notification::fake();
        [$user, $other, $third, $inbox, $conversation] = $this->sharedThread();

        $response = $this->actingAs($user)
            ->postJson('/api/inbox/conversations/'.$conversation->id.'/participants', [
                'user_ids' => [$other->id],
            ])
            ->assertOk();

        $participants = collect($response->json('conversation.participants'));
        $this->assertEqualsCanonicalizing(
            [$user->id, $other->id],
            $participants->pluck('id')->all()
        );
        $this->assertFalse($participants->contains('id', $third->id));

        $this->assertDatabaseHas('inbox_conversation_followers', [
            'inbox_conversation_id' => $conversation->id,
            'user_id' => $other->id,
            'is_subscribed' => true,
        ]);
        $this->assertDatabaseHas('inbox_conversation_followers', [
            'inbox_conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'is_subscribed' => true,
        ]);

        Notification::assertSentTo($other, InboxThreadUpdateNotification::class);
        Notification::assertNotSentTo($third, InboxThreadUpdateNotification::class);
    }

    public function test_commenting_and_mentions_auto_subscribe_participants(): void
    {
        [$user, $other, $third, $inbox, $conversation] = $this->sharedThread();

        $this->actingAs($user)
            ->postJson('/api/inbox/conversations/'.$conversation->id.'/comments', [
                'body' => 'Can you take a look @Other?',
                'mentioned_user_ids' => [$other->id],
            ])
            ->assertCreated();

        $this->assertTrue(
            InboxConversationFollower::query()
                ->where('inbox_conversation_id', $conversation->id)
                ->where('user_id', $user->id)
                ->where('is_subscribed', true)
                ->exists()
        );
        $this->assertTrue(
            InboxConversationFollower::query()
                ->where('inbox_conversation_id', $conversation->id)
                ->where('user_id', $other->id)
                ->where('is_subscribed', true)
                ->exists()
        );
        $this->assertFalse(
            InboxConversationFollower::query()
                ->where('inbox_conversation_id', $conversation->id)
                ->where('user_id', $third->id)
                ->exists()
        );
    }

    public function test_assign_auto_subscribes_assignee(): void
    {
        [$user, $other, $third, $inbox, $conversation] = $this->sharedThread();

        $this->actingAs($user)
            ->postJson('/api/inbox/conversations/'.$conversation->id.'/assign', [
                'assigned_to' => $other->id,
            ])
            ->assertOk();

        $this->assertTrue(
            InboxConversationFollower::query()
                ->where('inbox_conversation_id', $conversation->id)
                ->where('user_id', $other->id)
                ->where('is_subscribed', true)
                ->exists()
        );
    }

    public function test_unsubscribe_marks_shared_inbox_member_not_subscribed(): void
    {
        [$user, $other, $third, $inbox, $conversation] = $this->sharedThread();

        InboxConversationFollower::query()->create([
            'inbox_conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'is_subscribed' => true,
        ]);

        $this->actingAs($user)
            ->postJson('/api/inbox/conversations/'.$conversation->id.'/unsubscribe')
            ->assertOk()
            ->assertJsonPath('conversation.participants.0.is_subscribed', false);

        $this->assertDatabaseHas('inbox_conversation_followers', [
            'inbox_conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'is_subscribed' => false,
        ]);
    }

    public function test_cannot_remove_shared_inbox_member_participant(): void
    {
        [$user, $other, $third, $inbox, $conversation] = $this->sharedThread();

        InboxConversationFollower::query()->create([
            'inbox_conversation_id' => $conversation->id,
            'user_id' => $other->id,
            'is_subscribed' => true,
        ]);

        $this->actingAs($user)
            ->deleteJson('/api/inbox/conversations/'.$conversation->id.'/participants/'.$other->id)
            ->assertStatus(422);
    }

    public function test_invited_non_member_can_open_personal_conversation_and_be_removed(): void
    {
        Notification::fake();
        [$owner, $guest, $personal, $conversation] = $this->personalThreadWithGuest();

        $this->actingAs($owner)
            ->postJson('/api/inbox/conversations/'.$conversation->id.'/participants', [
                'user_ids' => [$guest->id],
            ])
            ->assertOk();

        $this->actingAs($guest)
            ->getJson('/api/inbox/conversations/'.$conversation->id)
            ->assertOk()
            ->assertJsonPath('conversation.id', $conversation->id);

        $this->actingAs($owner)
            ->deleteJson('/api/inbox/conversations/'.$conversation->id.'/participants/'.$guest->id)
            ->assertOk();

        $this->assertDatabaseMissing('inbox_conversation_followers', [
            'inbox_conversation_id' => $conversation->id,
            'user_id' => $guest->id,
        ]);

        $this->actingAs($guest)
            ->getJson('/api/inbox/conversations/'.$conversation->id)
            ->assertForbidden();
    }

    public function test_subscribed_view_lists_followed_conversations_across_mailboxes(): void
    {
        [$user, $other, $third, $inbox, $conversation] = $this->sharedThread();
        $unfollowed = InboxConversation::query()->create([
            'company_id' => $inbox->company_id,
            'shared_inbox_id' => $inbox->id,
            'folder' => 'inbox',
            'external_conversation_id' => 'conv-unfollowed-'.uniqid(),
            'status' => 'open',
            'subject' => 'Not following',
            'from_name' => 'Customer',
            'from_email' => 'other@example.com',
            'is_read' => true,
            'message_count' => 1,
            'last_message_at' => now(),
        ]);

        InboxConversationFollower::query()->create([
            'inbox_conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'is_subscribed' => true,
        ]);

        $ids = collect($this->actingAs($user)
            ->getJson('/api/inbox/conversations?view=subscribed')
            ->assertOk()
            ->json('conversations'))
            ->pluck('id')
            ->all();

        $this->assertContains($conversation->id, $ids);
        $this->assertNotContains($unfollowed->id, $ids);

        $this->actingAs($user)
            ->getJson('/api/inbox/nav-counts')
            ->assertOk()
            ->assertJsonPath('subscribed_count', 1);
    }

    /**
     * @return array{0: User, 1: User, 2: User, 3: SharedInbox, 4: InboxConversation}
     */
    private function sharedThread(): array
    {
        [$company, $role] = $this->companyWithInboxRole();

        $user = $this->makeUser($company, $role, 'Owner Agent', 'owner-participants@lns.test');
        $other = $this->makeUser($company, $role, 'Other Agent', 'other-participants@lns.test');
        $third = $this->makeUser($company, $role, 'Third Agent', 'third-participants@lns.test');

        $inbox = SharedInbox::query()->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'name' => 'Support',
            'email' => 'support-participants@example.com',
            'type' => SharedInbox::TYPE_SHARED,
            'is_active' => true,
        ]);

        foreach ([$user, $other, $third] as $member) {
            SharedInboxMember::query()->create([
                'shared_inbox_id' => $inbox->id,
                'user_id' => $member->id,
                'role' => 'member',
            ]);
        }

        $conversation = InboxConversation::query()->create([
            'company_id' => $company->id,
            'shared_inbox_id' => $inbox->id,
            'folder' => 'inbox',
            'external_conversation_id' => 'conv-participants-'.uniqid(),
            'status' => 'open',
            'subject' => 'Invite me',
            'from_name' => 'Customer',
            'from_email' => 'customer@example.com',
            'is_read' => true,
            'message_count' => 1,
            'last_message_at' => now(),
        ]);

        return [$user, $other, $third, $inbox, $conversation];
    }

    /**
     * @return array{0: User, 1: User, 2: SharedInbox, 3: InboxConversation}
     */
    private function personalThreadWithGuest(): array
    {
        [$company, $role] = $this->companyWithInboxRole();
        $owner = $this->makeUser($company, $role, 'Personal Owner', 'personal-owner@lns.test');
        $guest = $this->makeUser($company, $role, 'Guest Teammate', 'personal-guest@lns.test');

        $personal = SharedInbox::query()->create([
            'company_id' => $company->id,
            'created_by' => $owner->id,
            'name' => $owner->email,
            'email' => $owner->email,
            'type' => SharedInbox::TYPE_PERSONAL,
            'is_active' => true,
        ]);

        $conversation = InboxConversation::query()->create([
            'company_id' => $company->id,
            'shared_inbox_id' => $personal->id,
            'folder' => 'inbox',
            'external_conversation_id' => 'conv-personal-'.uniqid(),
            'status' => 'open',
            'subject' => 'Private thread',
            'from_name' => 'Customer',
            'from_email' => 'private@example.com',
            'is_read' => true,
            'message_count' => 1,
            'last_message_at' => now(),
        ]);

        return [$owner, $guest, $personal, $conversation];
    }

    /**
     * @return array{0: Company, 1: Role}
     */
    private function companyWithInboxRole(): array
    {
        $company = Company::query()->create([
            'name' => 'Participants Co',
            'subdomain' => 'participants-co-'.uniqid(),
            'status' => 'active',
            'email' => 'admin-participants@lns.test',
        ]);
        $permission = Permission::query()->create([
            'name' => 'view_inbox',
            'slug' => 'view_inbox',
            'display_name' => 'View Inbox',
            'company_id' => $company->id,
        ]);
        $role = Role::query()->create([
            'company_id' => $company->id,
            'name' => 'Agent',
            'slug' => 'agent-participants-'.uniqid(),
            'is_active' => true,
        ]);
        $role->permissions()->attach($permission->id);

        return [$company, $role];
    }

    private function makeUser(Company $company, Role $role, string $name, string $email): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);
    }
}
