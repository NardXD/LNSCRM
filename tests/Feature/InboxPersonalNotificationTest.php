<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\InboxConversation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SharedInbox;
use App\Models\SharedInboxMember;
use App\Models\User;
use App\Notifications\InboxThreadUpdateNotification;
use App\Services\InboxReopenService;
use App\Services\OutlookMailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use ReflectionMethod;
use Tests\TestCase;

class InboxPersonalNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_comment_without_a_mention_does_not_notify_teammates(): void
    {
        [$user, $other, $inbox] = $this->inboxWithTwoAgents();
        $conversation = $this->makeConversation($inbox, ['subject' => 'Quiet thread']);

        $this->actingAs($user)
            ->postJson('/api/inbox/conversations/'.$conversation->id.'/comments', [
                'body' => 'Internal note with no mention',
            ])
            ->assertCreated();

        $this->assertSame(0, $user->notifications()->count());
        $this->assertSame(0, $other->notifications()->count());
    }

    public function test_assigning_a_thread_notifies_only_the_new_assignee(): void
    {
        [$user, $other, $inbox] = $this->inboxWithTwoAgents();
        $conversation = $this->makeConversation($inbox, ['subject' => 'Needs an owner']);

        $this->actingAs($user)
            ->postJson('/api/inbox/conversations/'.$conversation->id.'/assign', [
                'assigned_to' => $other->id,
            ])
            ->assertOk();

        $this->assertSame(0, $user->notifications()->count());
        $this->assertSame(1, $other->notifications()->count());
        $this->assertSame('assignee', $other->notifications()->first()->data['involves'] ?? null);
        $this->assertStringContainsString('assigned "Needs an owner" to you', $other->notifications()->first()->data['summary'] ?? '');
    }

    public function test_self_assignment_does_not_notify(): void
    {
        [$user, $other, $inbox] = $this->inboxWithTwoAgents();
        $conversation = $this->makeConversation($inbox, ['subject' => 'Mine']);

        $this->actingAs($user)
            ->postJson('/api/inbox/conversations/'.$conversation->id.'/assign', [
                'assigned_to' => $user->id,
            ])
            ->assertOk();

        $this->assertSame(0, $user->notifications()->count());
        $this->assertSame(0, $other->notifications()->count());
    }

    public function test_archiving_does_not_notify_inbox_members(): void
    {
        [$user, $other, $inbox] = $this->inboxWithTwoAgents();
        $conversation = $this->makeConversation($inbox, [
            'subject' => 'Done',
            'assigned_to' => $other->id,
        ]);

        $this->actingAs($user)
            ->patchJson('/api/inbox/conversations/'.$conversation->id.'/status', [
                'status' => 'archived',
            ])
            ->assertOk();

        $this->assertSame(0, $user->notifications()->count());
        $this->assertSame(0, $other->notifications()->count());
    }

    public function test_a_finished_snooze_notifies_only_the_assignee(): void
    {
        [$user, $other, $inbox] = $this->inboxWithTwoAgents();
        $conversation = $this->makeConversation($inbox, [
            'subject' => 'Wake me',
            'status' => 'archived',
            'folder' => 'archive',
            'assigned_to' => $other->id,
            'reopen_at' => now()->subMinute(),
        ]);

        $this->assertSame(1, app(InboxReopenService::class)->processDue());

        $this->assertSame(0, $user->notifications()->count());
        $this->assertSame(1, $other->notifications()->count());
        $payload = $other->notifications()->first()->data;
        $this->assertSame('reopen', $payload['involves'] ?? null);
        $this->assertSame('reopened', $payload['action'] ?? null);
        $this->assertSame($conversation->id, $payload['conversation_id'] ?? null);
    }

    public function test_a_customer_reply_notifies_the_assignee_without_email(): void
    {
        [$user, $other, $inbox] = $this->inboxWithTwoAgents();
        $conversation = $this->makeConversation($inbox, [
            'subject' => 'Order status',
            'assigned_to' => $other->id,
            'from_name' => 'Pat Customer',
        ]);
        Mail::fake();

        $this->notifyCustomerReply($conversation, 'Pat Customer', 'Any update?', now());

        Mail::assertNothingSent();
        $this->assertSame(0, $user->notifications()->count());
        $this->assertSame(1, $other->notifications()->count());
        $payload = $other->notifications()->first()->data;
        $this->assertSame('reply', $payload['involves'] ?? null);
        $this->assertStringContainsString('replied on "Order status"', $payload['summary'] ?? '');
    }

    public function test_an_old_synced_customer_reply_does_not_notify(): void
    {
        [$user, $other, $inbox] = $this->inboxWithTwoAgents();
        $conversation = $this->makeConversation($inbox, [
            'subject' => 'Old thread',
            'assigned_to' => $other->id,
        ]);

        $this->notifyCustomerReply($conversation, 'Pat', 'From last year', now()->subDays(3));

        $this->assertSame(0, $user->notifications()->count());
        $this->assertSame(0, $other->notifications()->count());
    }

    public function test_the_bell_hides_broadcast_thread_updates_and_shows_personal_ones(): void
    {
        [$user, $other, $inbox] = $this->inboxWithTwoAgents();
        $conversation = $this->makeConversation($inbox, ['subject' => 'Visible']);

        $other->notify(new InboxThreadUpdateNotification(
            conversation: $conversation,
            action: 'archived',
            summary: 'Someone archived this',
        ));
        $other->notify(new InboxThreadUpdateNotification(
            conversation: $conversation,
            action: 'customer_reply',
            summary: 'Pat replied on "Visible"',
            involves: 'reply',
            sendMail: false,
        ));

        $response = $this->actingAs($other)->getJson('/api/notifications');

        $response->assertOk()
            ->assertJsonPath('data.unread_count', 1)
            ->assertJsonCount(1, 'data.notifications');
        $this->assertSame('reply', $response->json('data.notifications.0.data.involves'));

        $this->actingAs($user)->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0)
            ->assertJsonCount(0, 'data.notifications');
    }

    private function notifyCustomerReply(
        InboxConversation $conversation,
        string $fromName,
        string $body,
        \Carbon\Carbon $receivedAt
    ): void {
        $method = new ReflectionMethod(OutlookMailService::class, 'notifyAssigneeOfCustomerReply');
        $method->setAccessible(true);
        $method->invoke(app(OutlookMailService::class), $conversation, $fromName, $body, $receivedAt);
    }

    /**
     * @return array{0: User, 1: User, 2: SharedInbox}
     */
    private function inboxWithTwoAgents(): array
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-inbox-personal-notes',
            'status' => 'active',
            'email' => 'admin-inbox-personal@lns.test',
        ]);

        $role = Role::query()->create([
            'name' => 'Staff',
            'slug' => 'staff-inbox-personal',
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
            'email' => 'login-inbox-personal@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);
        $other = User::query()->create([
            'name' => 'Other Agent',
            'email' => 'other-inbox-personal@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $inbox = SharedInbox::query()->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'name' => 'Support',
            'email' => 'support@example.com',
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
