<?php

namespace Tests\Feature;

use App\Jobs\ApplyInboxLeadRulesJob;
use App\Jobs\ProcessInboxConversationReopenJob;
use App\Jobs\ProcessScheduledInboxReplyJob;
use App\Jobs\SyncSharedInboxMailJob;
use App\Models\Company;
use App\Models\InboxConversation;
use App\Models\OutlookMailAccount;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ScheduledInboxReply;
use App\Models\SharedInbox;
use App\Models\User;
use App\Notifications\InboxThreadUpdateNotification;
use App\Support\InboxQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InboxQueueJobsTest extends TestCase
{
    use RefreshDatabase;

    public function test_thread_update_notification_is_queued_on_inbox_notify(): void
    {
        $this->assertTrue(is_subclass_of(InboxThreadUpdateNotification::class, ShouldQueue::class));

        [$user, $inbox] = $this->agentWithInbox();
        $conversation = InboxConversation::query()->create([
            'company_id' => $user->company_id,
            'shared_inbox_id' => $inbox->id,
            'folder' => 'inbox',
            'status' => 'open',
            'subject' => 'Hello',
            'from_email' => 'a@example.com',
            'external_conversation_id' => 'ext-1',
            'last_message_at' => now(),
            'message_count' => 1,
        ]);

        $notification = new InboxThreadUpdateNotification(
            conversation: $conversation,
            action: 'assigned',
            summary: 'You were assigned',
            involves: 'assignee',
        );

        $this->assertSame(InboxQueue::NOTIFY, $notification->queue);
    }

    public function test_sync_mail_command_queues_per_inbox_jobs(): void
    {
        [, $inbox] = $this->agentWithInbox(withAccount: true);

        Queue::fake();

        $this->artisan('inbox:sync-mail')
            ->assertSuccessful();

        Queue::assertPushedOn(InboxQueue::MAIL, SyncSharedInboxMailJob::class);
        Queue::assertPushed(SyncSharedInboxMailJob::class, function (SyncSharedInboxMailJob $job) use ($inbox) {
            return $job->inboxId === (int) $inbox->id && $job->full === false;
        });
    }

    public function test_sync_mail_command_skips_fresh_shared_inbox_on_recent_run(): void
    {
        [, $inbox] = $this->agentWithInbox(withAccount: true);
        $inbox->forceFill(['last_synced_at' => now()->subSeconds(20)])->save();

        Queue::fake();

        $this->artisan('inbox:sync-mail')
            ->assertSuccessful();

        Queue::assertNotPushed(SyncSharedInboxMailJob::class);
    }

    public function test_sync_mail_command_queues_stale_personal_inbox_after_one_minute(): void
    {
        [, $inbox] = $this->agentWithInbox(withAccount: true, type: SharedInbox::TYPE_PERSONAL);
        $inbox->forceFill(['last_synced_at' => now()->subSeconds(61)])->save();

        Queue::fake();

        $this->artisan('inbox:sync-mail')
            ->assertSuccessful();

        Queue::assertPushedOn(InboxQueue::MAIL, SyncSharedInboxMailJob::class);
    }

    public function test_sync_mail_command_queues_full_when_backfill_incomplete(): void
    {
        [, $inbox] = $this->agentWithInbox(withAccount: true);
        $inbox->forceFill([
            'last_synced_at' => now(),
            'folder_sync_state' => [
                'inbox' => ['backfill_done' => false, 'next_link' => 'https://example.test/next', 'fetched' => 25],
            ],
        ])->save();

        Queue::fake();

        $this->artisan('inbox:sync-mail', ['--full' => true])
            ->assertSuccessful();

        Queue::assertPushed(SyncSharedInboxMailJob::class, function (SyncSharedInboxMailJob $job) use ($inbox) {
            return $job->inboxId === (int) $inbox->id && $job->full === true;
        });
    }

    public function test_process_scheduled_replies_command_queues_jobs(): void
    {
        [$user, $inbox] = $this->agentWithInbox();
        $conversation = InboxConversation::query()->create([
            'company_id' => $user->company_id,
            'shared_inbox_id' => $inbox->id,
            'folder' => 'inbox',
            'status' => 'open',
            'subject' => 'Later',
            'from_email' => 'a@example.com',
            'external_conversation_id' => 'ext-sched',
            'last_message_at' => now(),
            'message_count' => 1,
        ]);

        $scheduled = ScheduledInboxReply::query()->create([
            'shared_inbox_id' => $inbox->id,
            'inbox_conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'type' => ScheduledInboxReply::TYPE_REPLY,
            'status' => ScheduledInboxReply::STATUS_PENDING,
            'send_at' => now()->subMinute(),
            'to_emails' => 'a@example.com',
            'body_html' => '<p>Hi</p>',
        ]);

        Queue::fake();

        $this->artisan('inbox:process-scheduled-replies')
            ->assertSuccessful();

        Queue::assertPushedOn(InboxQueue::MAIL, ProcessScheduledInboxReplyJob::class);
        Queue::assertPushed(ProcessScheduledInboxReplyJob::class, function (ProcessScheduledInboxReplyJob $job) use ($scheduled) {
            return $job->scheduledReplyId === (int) $scheduled->id;
        });
    }

    public function test_process_reopens_command_queues_jobs(): void
    {
        [$user, $inbox] = $this->agentWithInbox();
        $conversation = InboxConversation::query()->create([
            'company_id' => $user->company_id,
            'shared_inbox_id' => $inbox->id,
            'folder' => 'inbox',
            'status' => 'archived',
            'subject' => 'Snoozed',
            'from_email' => 'a@example.com',
            'external_conversation_id' => 'ext-reopen',
            'last_message_at' => now(),
            'message_count' => 1,
            'reopen_at' => now()->subMinute(),
            'assigned_to' => $user->id,
        ]);

        Queue::fake();

        $this->artisan('inbox:process-reopens')
            ->assertSuccessful();

        Queue::assertPushedOn(InboxQueue::MAIL, ProcessInboxConversationReopenJob::class);
        Queue::assertPushed(ProcessInboxConversationReopenJob::class, function (ProcessInboxConversationReopenJob $job) use ($conversation) {
            return $job->conversationId === (int) $conversation->id;
        });
    }

    public function test_apply_inbox_lead_rules_job_uses_default_queue(): void
    {
        Queue::fake();

        ApplyInboxLeadRulesJob::dispatch(
            conversationId: 1,
            isNew: true,
            bodyText: 'hello',
            dedupeKey: 'test-dedupe-1',
        );

        Queue::assertPushedOn(InboxQueue::DEFAULT, ApplyInboxLeadRulesJob::class);
    }

    /**
     * @return array{0: User, 1: SharedInbox}
     */
    private function agentWithInbox(bool $withAccount = false, string $type = SharedInbox::TYPE_SHARED): array
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-inbox-queue-'.uniqid(),
            'status' => 'active',
            'email' => 'admin-inbox-queue-'.uniqid().'@lns.test',
        ]);

        $role = Role::query()->create([
            'name' => 'Staff',
            'slug' => 'staff-inbox-queue-'.uniqid(),
            'company_id' => $company->id,
            'is_active' => true,
        ]);
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'view_inbox', 'company_id' => $company->id],
            ['name' => 'view_inbox', 'display_name' => 'View Inbox']
        );
        $role->permissions()->attach($permission->id);

        $user = User::query()->create([
            'name' => 'Queue Agent',
            'email' => 'queue-agent-'.uniqid().'@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $accountId = null;
        $accountEmail = 'mail-'.uniqid().'@lns.test';
        if ($withAccount) {
            $account = OutlookMailAccount::query()->create([
                'user_id' => $user->id,
                'company_id' => $company->id,
                'email' => $accountEmail,
                'access_token' => 'token',
                'refresh_token' => 'refresh',
                'token_expires_at' => now()->addHour(),
                'is_active' => true,
            ]);
            $accountId = $account->id;
        }

        $inbox = SharedInbox::query()->create([
            'company_id' => $company->id,
            'name' => 'Support',
            'type' => $type,
            'email' => $type === SharedInbox::TYPE_PERSONAL ? $accountEmail : 'support-'.uniqid().'@lns.test',
            'is_active' => true,
            'outlook_mail_account_id' => $accountId,
            'created_by' => $user->id,
        ]);

        return [$user, $inbox];
    }
}
