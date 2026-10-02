<?php

namespace Tests\Feature;

use App\Jobs\ProcessScheduledInboxReplyJob;
use App\Models\Company;
use App\Models\InboxConversation;
use App\Models\InboxConversationActivity;
use App\Models\InboxMessage;
use App\Models\OutlookMailAccount;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ScheduledInboxReply;
use App\Models\SharedInbox;
use App\Models\SharedInboxMember;
use App\Models\User;
use App\Services\OutlookMailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InboxReplyQueuedTest extends TestCase
{
    use RefreshDatabase;

    public function test_sending_a_reply_queues_the_outlook_send_instead_of_blocking(): void
    {
        Queue::fake();

        [$user, $inbox] = $this->connectedInboxFixture();
        $conversation = $this->makeConversation($user, $inbox);

        $response = $this->actingAs($user)
            ->postJson('/api/inbox/conversations/'.$conversation->id.'/reply', [
                'to' => 'customer@example.com',
                'body' => '<p>Hello</p>',
            ]);

        $response->assertOk();
        $response->assertJsonPath('queued', true);

        // Nothing was actually sent yet — no outbound message, no activity logged.
        $this->assertSame(0, InboxMessage::query()->where('inbox_conversation_id', $conversation->id)->where('direction', 'outbound')->count());
        $this->assertSame(0, InboxConversationActivity::query()->where('inbox_conversation_id', $conversation->id)->where('action', 'replied')->count());

        $scheduled = ScheduledInboxReply::query()->where('inbox_conversation_id', $conversation->id)->firstOrFail();
        $this->assertTrue((bool) $scheduled->is_immediate);
        $this->assertSame(ScheduledInboxReply::STATUS_PENDING, $scheduled->status);

        Queue::assertPushed(ProcessScheduledInboxReplyJob::class, fn (ProcessScheduledInboxReplyJob $job) => $job->scheduledReplyId === $scheduled->id);
    }

    public function test_an_immediate_queued_reply_does_not_appear_as_a_scheduled_reply_card(): void
    {
        [$user, $inbox] = $this->connectedInboxFixture();
        $conversation = $this->makeConversation($user, $inbox);

        $this->mock(OutlookMailService::class, function ($mock) {
            $mock->shouldReceive('sendMail')->once()->andReturn([
                'sent' => true,
                'id' => 'graph-msg-immediate-1',
            ]);
        });

        // QUEUE_CONNECTION=sync in tests, so the job (and thus the real send) runs
        // inline here — but even in the split second before that, an immediate
        // send must never render as a "scheduled" card.
        $response = $this->actingAs($user)
            ->postJson('/api/inbox/conversations/'.$conversation->id.'/reply', [
                'to' => 'customer@example.com',
                'body' => '<p>Hello</p>',
            ])
            ->assertOk();

        $this->assertArrayNotHasKey('scheduled', $response->json());

        $shown = $this->actingAs($user)
            ->getJson('/api/inbox/conversations/'.$conversation->id)
            ->assertOk()
            ->json('conversation');

        $this->assertEmpty($shown['scheduled_replies'] ?? []);
    }

    public function test_a_genuinely_scheduled_reply_still_shows_its_scheduled_card(): void
    {
        [$user, $inbox] = $this->connectedInboxFixture();
        $conversation = $this->makeConversation($user, $inbox);

        $sendAt = now()->addHour()->toIso8601String();

        $this->actingAs($user)
            ->postJson('/api/inbox/conversations/'.$conversation->id.'/reply', [
                'to' => 'customer@example.com',
                'body' => '<p>Hello later</p>',
                'send_at' => $sendAt,
            ])
            ->assertOk()
            ->assertJsonPath('scheduled', true);

        $scheduled = ScheduledInboxReply::query()->where('inbox_conversation_id', $conversation->id)->firstOrFail();
        $this->assertFalse((bool) $scheduled->is_immediate);

        $shown = $this->actingAs($user)
            ->getJson('/api/inbox/conversations/'.$conversation->id)
            ->assertOk()
            ->json('conversation');

        $this->assertCount(1, $shown['scheduled_replies'] ?? []);
    }

    public function test_reply_uses_edited_subject_and_updates_thread_subject(): void
    {
        [$user, $inbox] = $this->connectedInboxFixture();
        $conversation = $this->makeConversation($user, $inbox);

        $sentSubjects = [];
        $this->mock(OutlookMailService::class, function ($mock) use (&$sentSubjects) {
            $mock->shouldReceive('sendMail')->andReturnUsing(function ($inbox, $payload) use (&$sentSubjects) {
                $sentSubjects[] = $payload['subject'];

                return ['sent' => true, 'id' => 'graph-msg-'.count($sentSubjects)];
            });
        });

        $this->actingAs($user)
            ->postJson('/api/inbox/conversations/'.$conversation->id.'/reply', [
                'to' => 'customer@example.com',
                'subject' => 'Re: Updated quote for unit 12',
                'body' => '<p>Hello</p>',
            ])
            ->assertOk();

        $this->assertSame(['Re: Updated quote for unit 12'], $sentSubjects);
        $this->assertSame('Updated quote for unit 12', $conversation->fresh()->subject);
        $this->assertSame(
            'Re: Updated quote for unit 12',
            InboxMessage::query()->where('inbox_conversation_id', $conversation->id)->where('direction', 'outbound')->value('subject')
        );

        // Without an edited subject, the next reply defaults to the new thread subject.
        $this->actingAs($user)
            ->postJson('/api/inbox/conversations/'.$conversation->id.'/reply', [
                'to' => 'customer@example.com',
                'body' => '<p>Follow-up</p>',
            ])
            ->assertOk();

        $this->assertSame('Re: Updated quote for unit 12', $sentSubjects[1]);
    }

    public function test_scheduled_reply_keeps_edited_subject(): void
    {
        [$user, $inbox] = $this->connectedInboxFixture();
        $conversation = $this->makeConversation($user, $inbox);

        $this->actingAs($user)
            ->postJson('/api/inbox/conversations/'.$conversation->id.'/reply', [
                'to' => 'customer@example.com',
                'subject' => 'Payment reminder',
                'body' => '<p>Hello later</p>',
                'send_at' => now()->addHour()->toIso8601String(),
            ])
            ->assertOk()
            ->assertJsonPath('scheduled', true);

        $this->assertSame(
            'Payment reminder',
            ScheduledInboxReply::query()->where('inbox_conversation_id', $conversation->id)->value('subject')
        );
    }

    /**
     * @return array{0: User, 1: SharedInbox}
     */
    private function connectedInboxFixture(): array
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-inbox-reply-queue',
            'status' => 'active',
            'email' => 'admin-inbox-reply-queue@lns.test',
        ]);

        $role = Role::query()->create([
            'name' => 'Staff',
            'slug' => 'staff-inbox-reply-queue',
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
            'email' => 'login-inbox-reply-queue@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $account = OutlookMailAccount::query()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'email' => 'mailbox@example.com',
            'access_token' => 'token',
            'refresh_token' => 'refresh',
            'token_expires_at' => now()->addHour(),
        ]);

        $inbox = SharedInbox::query()->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'outlook_mail_account_id' => $account->id,
            'name' => 'Support',
            'email' => 'mailbox@example.com',
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

    private function makeConversation(User $user, SharedInbox $inbox): InboxConversation
    {
        return InboxConversation::query()->create([
            'company_id' => $user->company_id,
            'shared_inbox_id' => $inbox->id,
            'folder' => 'inbox',
            'external_conversation_id' => 'conv-'.uniqid(),
            'status' => 'open',
            'subject' => 'Need docs',
            'from_name' => 'Customer',
            'from_email' => 'customer@example.com',
            'assigned_to' => $user->id,
            'is_read' => true,
            'message_count' => 1,
            'last_message_at' => now(),
        ]);
    }
}
