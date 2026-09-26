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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InboxConversationSortTest extends TestCase
{
    use RefreshDatabase;

    public function test_sort_newest_and_oldest_by_last_message_at(): void
    {
        [$user, $inbox] = $this->agentWithInbox();

        $older = $this->makeConversation($inbox, [
            'subject' => 'Older thread',
            'last_message_at' => now()->subDays(2),
        ]);
        $newer = $this->makeConversation($inbox, [
            'subject' => 'Newer thread',
            'last_message_at' => now()->subHour(),
        ]);

        $newestIds = collect($this->actingAs($user)
            ->getJson('/api/inbox/conversations?view=open&sort=newest')
            ->assertOk()
            ->json('conversations'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $oldestIds = collect($this->actingAs($user)
            ->getJson('/api/inbox/conversations?view=open&sort=oldest')
            ->assertOk()
            ->json('conversations'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertSame([$newer->id, $older->id], $newestIds);
        $this->assertSame([$older->id, $newer->id], $oldestIds);
    }

    public function test_newest_unreplied_puts_waiting_threads_first(): void
    {
        [$user, $inbox] = $this->agentWithInbox();

        $replied = $this->makeConversation($inbox, [
            'subject' => 'Already replied',
            'last_message_at' => now()->subMinutes(5),
        ]);
        InboxMessage::query()->create([
            'inbox_conversation_id' => $replied->id,
            'direction' => 'inbound',
            'from_email' => 'customer@example.com',
            'subject' => 'Hi',
            'body_text' => 'Question',
            'sent_at' => now()->subMinutes(10),
            'is_draft' => false,
        ]);
        InboxMessage::query()->create([
            'inbox_conversation_id' => $replied->id,
            'direction' => 'outbound',
            'from_email' => 'support@example.com',
            'subject' => 'Re: Hi',
            'body_text' => 'Answer',
            'sent_at' => now()->subMinutes(5),
            'is_draft' => false,
        ]);

        $unreplied = $this->makeConversation($inbox, [
            'subject' => 'Needs reply',
            'last_message_at' => now()->subHour(),
        ]);
        InboxMessage::query()->create([
            'inbox_conversation_id' => $unreplied->id,
            'direction' => 'inbound',
            'from_email' => 'waiting@example.com',
            'subject' => 'Help',
            'body_text' => 'Please reply',
            'sent_at' => now()->subHour(),
            'is_draft' => false,
        ]);

        $ids = collect($this->actingAs($user)
            ->getJson('/api/inbox/conversations?view=open&sort=newest_unreplied')
            ->assertOk()
            ->json('conversations'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertSame([$unreplied->id, $replied->id], $ids);
    }

    /**
     * @return array{0: User, 1: SharedInbox}
     */
    private function agentWithInbox(): array
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-inbox-sort',
            'status' => 'active',
            'email' => 'admin-inbox-sort@lns.test',
        ]);

        $role = Role::query()->create([
            'name' => 'Staff',
            'slug' => 'staff-inbox-sort',
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
            'name' => 'Sort User',
            'email' => 'sort-inbox@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $inbox = SharedInbox::query()->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'name' => 'Support',
            'email' => 'support-sort@example.com',
            'type' => SharedInbox::TYPE_SHARED,
            'is_active' => true,
        ]);
        SharedInboxMember::query()->create([
            'shared_inbox_id' => $inbox->id,
            'user_id' => $user->id,
            'role' => 'member',
        ]);

        return [$user, $inbox];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeConversation(SharedInbox $inbox, array $overrides): InboxConversation
    {
        return InboxConversation::query()->create(array_merge([
            'company_id' => $inbox->company_id,
            'shared_inbox_id' => $inbox->id,
            'folder' => 'inbox',
            'external_conversation_id' => 'conv-sort-'.uniqid(),
            'status' => 'open',
            'subject' => 'Thread',
            'from_name' => 'Customer',
            'from_email' => 'customer@example.com',
            'is_read' => true,
            'message_count' => 1,
            'last_message_at' => now(),
        ], $overrides));
    }
}
