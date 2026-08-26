<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MessagingEditAndSeenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_author_can_edit_own_message_and_other_participant_cannot(): void
    {
        [$alice, $bob, $conversation] = $this->directChat();

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $alice->id,
            'body' => 'Hello',
        ]);

        $this->actingAs($alice)
            ->postJson('/api/messaging/conversations/'.$conversation->id.'/messages/'.$message->id.'/update', [
                'body' => 'Hello there',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.body', 'Hello there')
            ->assertJsonPath('data.is_mine', true);

        $this->assertNotNull($message->fresh()->edited_at);
        $this->assertSame('Hello there', $message->fresh()->body);

        $this->actingAs($bob)
            ->postJson('/api/messaging/conversations/'.$conversation->id.'/messages/'.$message->id.'/update', [
                'body' => 'Hacked',
            ])
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->assertSame('Hello there', $message->fresh()->body);
    }

    public function test_edit_rejects_empty_body_without_attachment(): void
    {
        [$alice, $bob, $conversation] = $this->directChat();

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $alice->id,
            'body' => 'Keep me',
        ]);

        $this->actingAs($alice)
            ->postJson('/api/messaging/conversations/'.$conversation->id.'/messages/'.$message->id.'/update', [
                'body' => '   ',
            ])
            ->assertStatus(422);

        $this->assertSame('Keep me', $message->fresh()->body);
        $this->assertNull($message->fresh()->edited_at);
    }

    public function test_opening_chat_records_seen_receipt_for_the_other_person(): void
    {
        [$alice, $bob, $conversation] = $this->directChat();

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $alice->id,
            'body' => 'Did you see this?',
        ]);

        $this->actingAs($alice)
            ->getJson('/api/messaging/conversations/'.$conversation->id.'/messages')
            ->assertOk()
            ->assertJsonPath('data.receipts.0.last_read_at', null)
            ->assertJsonPath('data.messages.0.seen_by', []);

        $this->actingAs($bob)
            ->getJson('/api/messaging/conversations/'.$conversation->id.'/messages')
            ->assertOk();

        $payload = $this->actingAs($alice)
            ->getJson('/api/messaging/conversations/'.$conversation->id.'/messages')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $payload['receipts']);
        $this->assertSame($bob->id, $payload['receipts'][0]['id']);
        $this->assertNotNull($payload['receipts'][0]['last_read_at']);
        $this->assertNotEmpty($payload['messages'][0]['seen_by']);
        $this->assertSame($bob->id, $payload['messages'][0]['seen_by'][0]['id']);
    }

    public function test_seen_is_not_reported_when_other_user_has_stale_last_read(): void
    {
        [$alice, $bob, $conversation] = $this->directChat();

        $conversation->participants()->updateExistingPivot($bob->id, [
            'last_read_at' => now()->subHour()->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
        ]);

        Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $alice->id,
            'body' => 'New after they left',
        ]);

        $message = $this->actingAs($alice)
            ->getJson('/api/messaging/conversations/'.$conversation->id.'/messages')
            ->assertOk()
            ->json('data.messages.0');

        $this->assertSame('New after they left', $message['body']);
        $this->assertSame([], $message['seen_by']);
    }

    public function test_group_member_can_reply_to_another_members_message(): void
    {
        [$alice, $bob, $conversation] = $this->groupChat();

        $original = Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $bob->id,
            'body' => 'Can anyone take this client?',
        ]);

        $this->actingAs($alice)
            ->postJson('/api/messaging/conversations/'.$conversation->id.'/messages', [
                'body' => 'I can take it.',
                'reply_to_id' => $original->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.body', 'I can take it.')
            ->assertJsonPath('data.reply_to.id', $original->id)
            ->assertJsonPath('data.reply_to.author', 'Bob')
            ->assertJsonPath('data.reply_to.body', 'Can anyone take this client?');
    }

    public function test_cannot_reply_to_a_message_from_another_conversation(): void
    {
        [$alice, , $group] = $this->groupChat();
        [$dave, , $other] = $this->directChat('other');

        $outsider = Message::query()->create([
            'conversation_id' => $other->id,
            'user_id' => $dave->id,
            'body' => 'Private',
        ]);

        $this->actingAs($alice)
            ->postJson('/api/messaging/conversations/'.$group->id.'/messages', [
                'body' => 'Nope',
                'reply_to_id' => $outsider->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
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

        $alice = User::query()->create([
            'name' => 'Alice',
            'email' => 'alice-messaging-'.$suffix.'@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $bob = User::query()->create([
            'name' => 'Bob',
            'email' => 'bob-messaging-'.$suffix.'@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        return [$alice, $bob, $company];
    }
}
