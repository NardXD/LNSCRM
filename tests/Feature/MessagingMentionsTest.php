<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Notifications\MessagingMentionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MessagingMentionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_group_member_can_mention_another_member_and_they_are_notified(): void
    {
        [$alice, $bob, $cara, $conversation] = $this->groupChatWithThree();
        Notification::fake();

        $this->actingAs($alice)
            ->postJson('/api/messaging/conversations/'.$conversation->id.'/messages', [
                'body' => 'Can you take this @Bob?',
                'mentioned_user_ids' => [$bob->id],
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.body', 'Can you take this @Bob?')
            ->assertJsonPath('data.mentioned_user_ids.0', $bob->id)
            ->assertJsonPath('data.mentions.0.id', $bob->id)
            ->assertJsonPath('data.mentions.0.name', 'Bob')
            ->assertJsonPath('data.mentions_me', false);

        $this->assertSame([$bob->id], Message::query()->latest('id')->first()?->mentioned_user_ids);

        Notification::assertSentTo($bob, MessagingMentionNotification::class, function (MessagingMentionNotification $notification) use ($conversation, $alice) {
            $payload = $notification->toArray($notification->actor);

            return $notification->conversation->is($conversation)
                && $notification->actor->is($alice)
                && $payload['type'] === 'messaging_mention'
                && $payload['is_mention'] === true
                && str_contains($payload['url'], '/messaging?conversation='.$conversation->id);
        });
        Notification::assertNotSentTo($alice, MessagingMentionNotification::class);
        Notification::assertNotSentTo($cara, MessagingMentionNotification::class);
    }

    public function test_mention_is_detected_from_message_body_without_explicit_ids(): void
    {
        [$alice, $bob, , $conversation] = $this->groupChatWithThree();
        Notification::fake();

        $this->actingAs($alice)
            ->postJson('/api/messaging/conversations/'.$conversation->id.'/messages', [
                'body' => 'Thanks @Bob for covering.',
            ])
            ->assertOk()
            ->assertJsonPath('data.mentioned_user_ids.0', $bob->id);

        Notification::assertSentTo($bob, MessagingMentionNotification::class);
    }

    public function test_cannot_mention_a_user_who_is_not_in_the_group(): void
    {
        [$alice, $bob, $conversation, $outsider] = $this->groupChatWithOutsider();
        Notification::fake();

        $this->actingAs($alice)
            ->postJson('/api/messaging/conversations/'.$conversation->id.'/messages', [
                'body' => 'Hello @Outsider',
                'mentioned_user_ids' => [$outsider->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.mentioned_user_ids', []);

        Notification::assertNothingSent();
        $this->assertSame($bob->id, $conversation->participants()->where('users.id', $bob->id)->first()?->id);
    }

    public function test_direct_chats_ignore_mentions(): void
    {
        [$alice, $bob, $conversation] = $this->directChat();
        Notification::fake();

        $this->actingAs($alice)
            ->postJson('/api/messaging/conversations/'.$conversation->id.'/messages', [
                'body' => 'Hey @Bob',
                'mentioned_user_ids' => [$bob->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.mentioned_user_ids', [])
            ->assertJsonPath('data.mentions', []);

        Notification::assertNothingSent();
    }

    public function test_group_messages_include_members_and_mention_flags(): void
    {
        [$alice, $bob, $cara, $conversation] = $this->groupChatWithThree();

        Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $alice->id,
            'body' => 'Ping @Cara',
            'mentioned_user_ids' => [$cara->id],
        ]);

        $payload = $this->actingAs($bob)
            ->getJson('/api/messaging/conversations/'.$conversation->id.'/messages')
            ->assertOk()
            ->json('data');

        $this->assertSame('group', $payload['conversation']['type']);
        $this->assertCount(3, $payload['conversation']['members']);
        $this->assertSame('Ping @Cara', $payload['messages'][0]['body']);
        $this->assertSame([$cara->id], $payload['messages'][0]['mentioned_user_ids']);
        $this->assertFalse($payload['messages'][0]['mentions_me']);

        $caraView = $this->actingAs($cara)
            ->getJson('/api/messaging/conversations/'.$conversation->id.'/messages')
            ->assertOk()
            ->json('data.messages.0');

        $this->assertTrue($caraView['mentions_me']);
        $this->assertSame('Cara', $caraView['mentions'][0]['name']);
    }

    public function test_editing_a_message_notifies_newly_mentioned_members_only(): void
    {
        [$alice, $bob, $cara, $conversation] = $this->groupChatWithThree();

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $alice->id,
            'body' => 'Can you help @Bob',
            'mentioned_user_ids' => [$bob->id],
        ]);

        Notification::fake();

        $this->actingAs($alice)
            ->postJson('/api/messaging/conversations/'.$conversation->id.'/messages/'.$message->id.'/update', [
                'body' => 'Can you help @Bob and @Cara',
                'mentioned_user_ids' => [$bob->id, $cara->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.body', 'Can you help @Bob and @Cara');

        $this->assertEqualsCanonicalizing([$bob->id, $cara->id], $message->fresh()->mentioned_user_ids);

        Notification::assertSentTo($cara, MessagingMentionNotification::class);
        Notification::assertNotSentTo($bob, MessagingMentionNotification::class);
        Notification::assertNotSentTo($alice, MessagingMentionNotification::class);
    }

    /**
     * @return array{0: User, 1: User, 2: Conversation}
     */
    private function directChat(): array
    {
        [$alice, $bob, $company] = $this->twoUsers('mention-dm');

        $conversation = Conversation::query()->create([
            'company_id' => $company->id,
            'type' => 'direct',
            'created_by' => $alice->id,
        ]);
        $conversation->participants()->attach([$alice->id, $bob->id]);

        return [$alice, $bob, $conversation];
    }

    /**
     * @return array{0: User, 1: User, 2: User, 3: Conversation}
     */
    private function groupChatWithThree(): array
    {
        [$alice, $bob, $company] = $this->twoUsers('mention-group');
        $cara = $this->companyUser($company->id, (int) $alice->role_id, 'Cara', 'cara-messaging-mention@lns.test');

        $conversation = Conversation::query()->create([
            'company_id' => $company->id,
            'type' => 'group',
            'name' => 'Ops',
            'created_by' => $alice->id,
        ]);
        $conversation->participants()->attach([$alice->id, $bob->id, $cara->id]);

        return [$alice, $bob, $cara, $conversation];
    }

    /**
     * @return array{0: User, 1: User, 2: Conversation, 3: User}
     */
    private function groupChatWithOutsider(): array
    {
        [$alice, $bob, , $conversation] = $this->groupChatWithThree();
        $outsider = $this->companyUser($alice->company_id, (int) $alice->role_id, 'Outsider', 'outsider-messaging-mention@lns.test');

        return [$alice, $bob, $conversation, $outsider];
    }

    /**
     * @return array{0: User, 1: User, 2: Company}
     */
    private function twoUsers(string $suffix): array
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-messaging-'.$suffix,
            'status' => 'active',
            'email' => 'admin-messaging-'.$suffix.'@lns.test',
            'timezone' => 'UTC',
        ]);

        $role = Role::query()->create([
            'name' => 'Staff',
            'slug' => 'staff-messaging-'.$suffix,
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        $permission = Permission::query()->create([
            'name' => 'view_messaging',
            'slug' => 'view_messaging',
            'display_name' => 'Messaging',
            'company_id' => $company->id,
        ]);
        $role->permissions()->attach($permission->id);

        $alice = $this->companyUser($company->id, $role->id, 'Alice', 'alice-messaging-'.$suffix.'@lns.test');
        $bob = $this->companyUser($company->id, $role->id, 'Bob', 'bob-messaging-'.$suffix.'@lns.test');

        return [$alice, $bob, $company];
    }

    private function companyUser(int $companyId, int $roleId, string $name, string $email): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
            'company_id' => $companyId,
            'role_id' => $roleId,
            'status' => 'active',
        ]);
    }
}
