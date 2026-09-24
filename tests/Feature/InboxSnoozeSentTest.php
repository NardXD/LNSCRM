<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\InboxConversation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SharedInbox;
use App\Models\SharedInboxMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InboxSnoozeSentTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_sent_email_can_be_snoozed_and_appears_for_all_members(): void
    {
        [$user, $other, $inbox] = $this->inboxWithTwoAgents();

        $sent = $this->makeConversation($inbox, [
            'subject' => 'Shared sent follow-up',
            'from_email' => 'agent@example.com',
            'folder' => 'sent',
            'status' => 'sent',
            'assigned_to' => $user->id,
        ]);

        $until = now()->addDay()->seconds(0)->milliseconds(0);

        $this->actingAs($user)
            ->postJson('/api/inbox/conversations/'.$sent->id.'/snooze', [
                'until' => $until->toIso8601String(),
            ])
            ->assertOk()
            ->assertJsonPath('conversation.folder', 'inbox')
            ->assertJsonPath('conversation.status', 'archived');

        $sent->refresh();
        $this->assertSame('inbox', $sent->folder);
        $this->assertSame('archived', $sent->status);
        $this->assertNotNull($sent->reopen_at);
        $this->assertTrue($sent->reopen_at->greaterThan(now()));

        $this->assertContains($sent->id, $this->conversationIds($user, 'snoozed'));
        $this->assertContains($sent->id, $this->conversationIds($other, 'snoozed'));
        $this->assertNotContains($sent->id, $this->conversationIds($user, 'sent'));
    }

    public function test_personal_sent_email_can_be_snoozed_even_without_assignee(): void
    {
        [$user, $other] = $this->twoAgents();

        $inbox = SharedInbox::query()->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'name' => 'Personal',
            'email' => 'login-inbox-snooze-sent@lns.test',
            'type' => SharedInbox::TYPE_PERSONAL,
            'is_active' => true,
        ]);

        $sent = $this->makeConversation($inbox, [
            'subject' => 'Personal sent follow-up',
            'from_email' => 'me@example.com',
            'folder' => 'sent',
            'status' => 'sent',
            'assigned_to' => null,
        ]);

        $until = now()->addHours(6)->seconds(0)->milliseconds(0);

        $this->actingAs($user)
            ->postJson('/api/inbox/conversations/'.$sent->id.'/snooze', [
                'until' => $until->toIso8601String(),
            ])
            ->assertOk();

        $sent->refresh();
        $this->assertSame('inbox', $sent->folder);
        $this->assertSame('archived', $sent->status);
        $this->assertSame($user->id, (int) $sent->assigned_to);
        $this->assertNotNull($sent->reopen_at);

        $this->assertContains($sent->id, $this->conversationIds($user, 'snoozed'));
        $this->assertNotContains($sent->id, $this->conversationIds($other, 'snoozed'));
    }

    public function test_snoozing_sent_merges_into_existing_inbox_twin(): void
    {
        [$user, , $inbox] = $this->inboxWithTwoAgents();
        $externalId = 'outlook-conv-'.uniqid();

        $inboxTwin = $this->makeConversation($inbox, [
            'subject' => 'Original inbound',
            'from_email' => 'customer@example.com',
            'folder' => 'inbox',
            'status' => 'open',
            'external_conversation_id' => $externalId,
            'assigned_to' => $user->id,
        ]);
        $sent = $this->makeConversation($inbox, [
            'subject' => 'Re: Original inbound',
            'from_email' => 'agent@example.com',
            'folder' => 'sent',
            'status' => 'sent',
            'external_conversation_id' => $externalId,
            'assigned_to' => $user->id,
        ]);

        $until = now()->addDays(2)->seconds(0)->milliseconds(0);

        $this->actingAs($user)
            ->postJson('/api/inbox/conversations/'.$sent->id.'/snooze', [
                'until' => $until->toIso8601String(),
            ])
            ->assertOk()
            ->assertJsonPath('conversation.id', $inboxTwin->id);

        $this->assertDatabaseMissing('inbox_conversations', ['id' => $sent->id]);

        $inboxTwin->refresh();
        $this->assertSame('archived', $inboxTwin->status);
        $this->assertSame('inbox', $inboxTwin->folder);
        $this->assertNotNull($inboxTwin->reopen_at);
        $this->assertContains($inboxTwin->id, $this->conversationIds($user, 'snoozed'));
    }

    /**
     * @return list<int>
     */
    private function conversationIds(User $user, string $view): array
    {
        return collect($this->actingAs($user)
            ->getJson('/api/inbox/conversations?view='.$view)
            ->assertOk()
            ->json('conversations'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function twoAgents(): array
    {
        [$user, $other] = $this->inboxWithTwoAgents();

        return [$user, $other];
    }

    /**
     * @return array{0: User, 1: User, 2: SharedInbox}
     */
    private function inboxWithTwoAgents(): array
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-inbox-snooze-sent',
            'status' => 'active',
            'email' => 'admin-inbox-snooze-sent@lns.test',
        ]);

        $role = Role::query()->create([
            'name' => 'Staff',
            'slug' => 'staff-inbox-snooze-sent',
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
            'name' => 'Login User',
            'email' => 'login-inbox-snooze-sent@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);
        $other = User::query()->create([
            'name' => 'Other Agent',
            'email' => 'other-inbox-snooze-sent@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $inbox = SharedInbox::query()->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'name' => 'Support',
            'email' => 'support-snooze-sent@example.com',
            'type' => SharedInbox::TYPE_SHARED,
            'is_active' => true,
        ]);
        SharedInboxMember::query()->create([
            'shared_inbox_id' => $inbox->id,
            'user_id' => $user->id,
            'role' => 'member',
        ]);
        SharedInboxMember::query()->create([
            'shared_inbox_id' => $inbox->id,
            'user_id' => $other->id,
            'role' => 'member',
        ]);

        return [$user, $other, $inbox];
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
            'external_conversation_id' => 'conv-'.uniqid(),
            'status' => 'open',
            'from_name' => 'Customer',
            'is_read' => true,
            'message_count' => 1,
            'last_message_at' => now(),
        ], $overrides));
    }
}
