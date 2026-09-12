<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MessagingReactionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_participant_can_react_and_toggle_the_same_reaction_off(): void
    {
        [$alice, $bob, $conversation] = $this->directChat();

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $alice->id,
            'body' => 'Hello',
        ]);

        $this->actingAs($bob)
            ->postJson('/api/messaging/conversations/'.$conversation->id.'/messages/'.$message->id.'/react', [
                'type' => 'love',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.my_reaction', 'love')
            ->assertJsonPath('data.reactions.0.type', 'love')
            ->assertJsonPath('data.reactions.0.emoji', '❤️')
            ->assertJsonPath('data.reactions.0.count', 1)
            ->assertJsonPath('data.reactions.0.reacted', true);

        $this->assertDatabaseHas('message_reactions', [
            'message_id' => $message->id,
            'user_id' => $bob->id,
            'type' => 'love',
        ]);

        $this->actingAs($bob)
            ->postJson('/api/messaging/conversations/'.$conversation->id.'/messages/'.$message->id.'/react', [
                'type' => 'love',
            ])
            ->assertOk()
            ->assertJsonPath('data.my_reaction', null)
            ->assertJsonPath('data.reactions', []);

        $this->assertDatabaseMissing('message_reactions', [
            'message_id' => $message->id,
            'user_id' => $bob->id,
        ]);
    }

    public function test_changing_reaction_replaces_the_previous_one(): void
    {
        [$alice, $bob, $conversation] = $this->directChat();

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $alice->id,
            'body' => 'Wow',
        ]);

        $this->actingAs($bob)
            ->postJson('/api/messaging/conversations/'.$conversation->id.'/messages/'.$message->id.'/react', [
                'type' => 'like',
            ])
            ->assertOk();

        $this->actingAs($bob)
            ->postJson('/api/messaging/conversations/'.$conversation->id.'/messages/'.$message->id.'/react', [
                'type' => 'haha',
            ])
            ->assertOk()
            ->assertJsonPath('data.my_reaction', 'haha')
            ->assertJsonPath('data.reactions.0.type', 'haha')
            ->assertJsonPath('data.reactions.0.count', 1);

        $this->assertSame(1, MessageReaction::query()->where('message_id', $message->id)->count());
        $this->assertSame('haha', MessageReaction::query()->where('message_id', $message->id)->value('type'));
    }

    public function test_messages_include_grouped_reactions_from_other_participants(): void
    {
        [$alice, $bob, $conversation] = $this->directChat();

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $alice->id,
            'body' => 'Team update',
        ]);

        MessageReaction::query()->create([
            'message_id' => $message->id,
            'user_id' => $bob->id,
            'type' => 'wow',
        ]);

        $payload = $this->actingAs($alice)
            ->getJson('/api/messaging/conversations/'.$conversation->id.'/messages')
            ->assertOk()
            ->json('data.messages.0');

        $this->assertNull($payload['my_reaction']);
        $this->assertSame('wow', $payload['reactions'][0]['type']);
        $this->assertFalse($payload['reactions'][0]['reacted']);
        $this->assertSame($bob->id, $payload['reactions'][0]['users'][0]['id']);
        $this->assertSame('Bob', $payload['reactions'][0]['users'][0]['name']);
    }

    public function test_non_participant_cannot_react(): void
    {
        [$alice, $bob, $conversation] = $this->directChat();
        $carol = $this->companyUser($bob->company_id, $bob->role_id, 'Carol', 'carol-msg-react-direct@lns.test');

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $alice->id,
            'body' => 'Private',
        ]);

        $this->actingAs($carol)
            ->postJson('/api/messaging/conversations/'.$conversation->id.'/messages/'.$message->id.'/react', [
                'type' => 'like',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('message_reactions', [
            'message_id' => $message->id,
        ]);
    }

    public function test_cannot_react_to_a_message_from_another_conversation(): void
    {
        [$alice, , $group] = $this->groupChat();
        [$dave, , $other] = $this->directChat('other');

        $outsiderMessage = Message::query()->create([
            'conversation_id' => $other->id,
            'user_id' => $dave->id,
            'body' => 'Private',
        ]);

        $this->actingAs($alice)
            ->postJson('/api/messaging/conversations/'.$group->id.'/messages/'.$outsiderMessage->id.'/react', [
                'type' => 'like',
            ])
            ->assertNotFound();
    }

    public function test_invalid_reaction_type_is_rejected(): void
    {
        [$alice, $bob, $conversation] = $this->directChat();

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $alice->id,
            'body' => 'Nope',
        ]);

        $this->actingAs($bob)
            ->postJson('/api/messaging/conversations/'.$conversation->id.'/messages/'.$message->id.'/react', [
                'type' => 'fire',
            ])
            ->assertStatus(422);
    }

    /**
     * @return array{0: User, 1: User, 2: Conversation}
     */
    private function directChat(string $suffix = 'direct'): array
    {
        [$alice, $bob, $company] = $this->twoUsers($suffix);

        $conversation = Conversation::query()->create([
            'company_id' => $company->id,
            'type' => 'direct',
            'created_by' => $alice->id,
        ]);
        $conversation->participants()->attach([$alice->id, $bob->id]);

        return [$alice, $bob, $conversation];
    }

    /**
     * @return array{0: User, 1: User, 2: Conversation}
     */
    private function groupChat(): array
    {
        [$alice, $bob, $company] = $this->twoUsers('group');

        $conversation = Conversation::query()->create([
            'company_id' => $company->id,
            'type' => 'group',
            'name' => 'Sales',
            'created_by' => $alice->id,
        ]);
        $conversation->participants()->attach([$alice->id, $bob->id]);

        return [$alice, $bob, $conversation];
    }

    /**
     * @return array{0: User, 1: User, 2: Company}
     */
    private function twoUsers(string $suffix): array
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-msg-react-'.$suffix,
            'status' => 'active',
            'email' => 'admin-msg-react-'.$suffix.'@lns.test',
            'timezone' => 'UTC',
        ]);

        $role = Role::query()->create([
            'name' => 'Staff',
            'slug' => 'staff-msg-react-'.$suffix,
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

        $alice = $this->companyUser($company->id, $role->id, 'Alice', 'alice-msg-react-'.$suffix.'@lns.test');
        $bob = $this->companyUser($company->id, $role->id, 'Bob', 'bob-msg-react-'.$suffix.'@lns.test');

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
