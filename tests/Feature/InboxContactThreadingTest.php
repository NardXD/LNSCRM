<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\InboxConversation;
use App\Models\InboxMessage;
use App\Models\OutlookMailAccount;
use App\Models\SharedInbox;
use App\Models\User;
use App\Services\InboxReplyService;
use App\Services\InboxThreadMergeService;
use App\Services\OutlookMailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InboxContactThreadingTest extends TestCase
{
    use RefreshDatabase;

    private SharedInbox $inbox;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $company = Company::query()->create([
            'name' => 'Acme',
            'subdomain' => 'acme-contact-'.uniqid(),
            'status' => 'active',
            'email' => 'admin-contact-'.uniqid().'@lns.test',
        ]);
        $this->user = User::factory()->create(['company_id' => $company->id]);
        $account = OutlookMailAccount::query()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'email' => 'shared@acme-co.test',
            'access_token' => 'token',
            'refresh_token' => 'refresh',
            'token_expires_at' => now()->addHour(),
            'is_active' => true,
        ]);
        $this->inbox = SharedInbox::query()->create([
            'company_id' => $company->id,
            'outlook_mail_account_id' => $account->id,
            'name' => 'Support',
            'email' => 'shared@acme-co.test',
            'type' => SharedInbox::TYPE_SHARED,
            'is_active' => true,
        ]);
    }

    public function test_new_email_from_same_address_joins_existing_thread(): void
    {
        $this->receive('m1', 'conv-1', 'john@customer.test', 'Storage unit question', now()->subHour());
        $this->receive('m2', 'conv-2', 'JOHN@customer.test', 'Payment for May', now());

        $visible = InboxConversation::query()->notMerged()->where('folder', 'inbox')->get();
        $this->assertCount(1, $visible);

        $home = $visible->first();
        $this->assertSame(2, InboxMessage::query()->where('inbox_conversation_id', $home->id)->count());
        $this->assertSame('Payment for May', $home->subject);
        $this->assertSame('john@customer.test', $home->contact_email);

        // A later reply on the second Outlook conversation still lands in the same thread.
        $this->receive('m3', 'conv-2', 'john@customer.test', 'RE: Payment for May', now()->addMinute());
        $this->assertSame(3, InboxMessage::query()->where('inbox_conversation_id', $home->id)->count());
        $this->assertSame(1, InboxConversation::query()->notMerged()->where('folder', 'inbox')->count());
    }

    public function test_different_addresses_stay_separate(): void
    {
        $this->receive('m1', 'conv-1', 'john@customer.test', 'Hello', now()->subHour());
        $this->receive('m2', 'conv-2', 'mary@customer.test', 'Hello', now());

        $this->assertSame(2, InboxConversation::query()->notMerged()->where('folder', 'inbox')->count());
    }

    public function test_new_email_reopens_archived_thread_and_keeps_assignee(): void
    {
        $this->receive('m1', 'conv-1', 'john@customer.test', 'First', now()->subDay());
        $home = InboxConversation::query()->firstOrFail();
        $home->update(['status' => 'archived', 'assigned_to' => $this->user->id]);

        $this->receive('m2', 'conv-2', 'john@customer.test', 'Second', now());

        $home->refresh();
        $this->assertSame('open', $home->status);
        $this->assertSame('archived', $home->reopened_from);
        $this->assertSame($this->user->id, (int) $home->assigned_to);
        $this->assertSame(2, InboxMessage::query()->where('inbox_conversation_id', $home->id)->count());
    }

    public function test_old_backfilled_email_does_not_reopen_archived_thread(): void
    {
        $this->receive('m1', 'conv-1', 'john@customer.test', 'First', now()->subDays(20));
        $home = InboxConversation::query()->firstOrFail();
        $home->update(['status' => 'archived']);

        $this->receive('m2', 'conv-2', 'john@customer.test', 'Second', now()->subDays(10));

        $this->assertSame('archived', $home->fresh()->status);
    }

    public function test_automated_and_internal_senders_are_not_grouped(): void
    {
        $this->receive('m1', 'conv-1', 'noreply@vendor.test', 'Receipt 1', now()->subHour());
        $this->receive('m2', 'conv-2', 'noreply@vendor.test', 'Receipt 2', now());
        $this->receive('m3', 'conv-3', 'colleague@acme-co.test', 'Fwd: lead A', now()->subHour());
        $this->receive('m4', 'conv-4', 'colleague@acme-co.test', 'Fwd: lead B', now());

        $this->assertSame(4, InboxConversation::query()->notMerged()->where('folder', 'inbox')->count());
    }

    public function test_excluded_address_from_config_is_not_grouped(): void
    {
        config(['inbox.group_by_contact_exclude' => ['@forms.test']]);

        $this->receive('m1', 'conv-1', 'web@forms.test', 'Inquiry A', now()->subHour());
        $this->receive('m2', 'conv-2', 'web@forms.test', 'Inquiry B', now());

        $this->assertSame(2, InboxConversation::query()->notMerged()->where('folder', 'inbox')->count());
    }

    public function test_unmerged_thread_is_not_regrouped(): void
    {
        $this->receive('m1', 'conv-1', 'john@customer.test', 'First', now()->subHour());
        $this->receive('m2', 'conv-2', 'john@customer.test', 'Second', now());

        $home = InboxConversation::query()->notMerged()->firstOrFail();
        $child = InboxConversation::query()->where('merged_into_id', $home->id)->firstOrFail();
        app(InboxThreadMergeService::class)->unmerge($home, $child);

        $this->artisan('inbox:group-by-contact')->assertSuccessful();
        $this->receive('m3', 'conv-2', 'john@customer.test', 'RE: Second', now()->addMinute());

        $child->refresh();
        $this->assertNull($child->merged_into_id);
        $this->assertTrue($child->auto_group_disabled);
        $this->assertSame(2, InboxMessage::query()->where('inbox_conversation_id', $child->id)->count());
    }

    public function test_compose_to_existing_contact_joins_thread(): void
    {
        $this->receive('m1', 'conv-1', 'john@customer.test', 'Question', now()->subHour());
        $home = InboxConversation::query()->firstOrFail();
        $home->update(['status' => 'archived']);

        $this->partialMock(OutlookMailService::class, function ($mock) {
            $mock->shouldReceive('sendMail')->andReturn(['id' => 'graph-sent-1', 'conversationId' => 'conv-compose']);
        });

        $result = app(InboxReplyService::class)->sendCompose($this->inbox, $this->user, [
            'to' => 'John@customer.test',
            'subject' => 'Your quote',
            'body' => '<p>Here is the quote</p>',
        ]);

        $home->refresh();
        $this->assertSame($home->id, $result['conversation']->id);
        $this->assertSame('open', $home->status);
        $this->assertSame($this->user->id, (int) $home->assigned_to);
        $this->assertSame('Your quote', $home->subject);
        $this->assertSame('john@customer.test', $home->from_email);
        $this->assertSame(2, InboxMessage::query()->where('inbox_conversation_id', $home->id)->count());

        // The Sent Items copy syncs back onto the same thread without a duplicate.
        $this->sync('graph-sent-1', 'conv-compose', 'shared@acme-co.test', 'Your quote', now(), 'sent', 'outbound', 'john@customer.test');
        $this->assertSame(2, InboxMessage::query()->where('inbox_conversation_id', $home->id)->count());
        $this->assertSame(1, InboxConversation::query()->notMerged()->count());
    }

    public function test_compose_to_new_contact_stays_a_sent_thread(): void
    {
        $this->partialMock(OutlookMailService::class, function ($mock) {
            $mock->shouldReceive('sendMail')->andReturn(['id' => 'graph-sent-1', 'conversationId' => 'conv-compose']);
        });

        $result = app(InboxReplyService::class)->sendCompose($this->inbox, $this->user, [
            'to' => 'new@customer.test',
            'subject' => 'Hello',
            'body' => 'Hi',
        ]);

        $this->assertSame('sent', $result['conversation']->folder);
        $this->assertSame('new@customer.test', $result['conversation']->contact_email);
    }

    public function test_reply_from_contact_absorbs_sent_only_thread(): void
    {
        $this->sync('s1', 'conv-a', 'shared@acme-co.test', 'Offer', now()->subHour(), 'sent', 'outbound', 'john@customer.test');
        $this->receive('m1', 'conv-b', 'john@customer.test', 'New question', now());

        $visible = InboxConversation::query()->notMerged()->get();
        $this->assertCount(1, $visible);
        $this->assertSame('inbox', $visible->first()->folder);
        $this->assertSame(2, InboxMessage::query()->where('inbox_conversation_id', $visible->first()->id)->count());
    }

    public function test_backfill_groups_existing_history(): void
    {
        config(['inbox.group_by_contact' => false]);
        $this->receive('m1', 'conv-1', 'john@customer.test', 'Old', now()->subDays(5));
        $this->receive('m2', 'conv-2', 'john@customer.test', 'Newer', now()->subDays(2));
        $this->receive('m3', 'conv-3', 'mary@customer.test', 'Mary', now()->subDay());
        InboxConversation::query()->where('external_conversation_id', 'conv-2')->update(['status' => 'archived']);
        config(['inbox.group_by_contact' => true]);

        $this->artisan('inbox:group-by-contact', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(3, InboxConversation::query()->notMerged()->count());

        $this->artisan('inbox:group-by-contact')->assertSuccessful();

        $visible = InboxConversation::query()->notMerged()->get();
        $this->assertCount(2, $visible);
        $john = $visible->firstWhere('contact_email', 'john@customer.test');
        // The open thread stays the home so it doesn't vanish from Open.
        $this->assertSame('conv-1', $john->external_conversation_id);
        $this->assertSame('open', $john->status);
        $this->assertSame('Newer', $john->subject);
        $this->assertSame(2, InboxMessage::query()->where('inbox_conversation_id', $john->id)->count());
    }

    private function receive(string $id, string $conversationId, string $from, string $subject, $at): void
    {
        $this->sync($id, $conversationId, $from, $subject, $at, 'inbox', 'inbound', 'shared@acme-co.test');
    }

    private function sync(
        string $id,
        string $conversationId,
        string $from,
        string $subject,
        $at,
        string $folder,
        string $direction,
        string $to
    ): void {
        app(OutlookMailService::class)->upsertMessage(
            $this->inbox->fresh('account'),
            [
                'id' => $id,
                'conversationId' => $conversationId,
                'subject' => $subject,
                'bodyPreview' => $subject.' body',
                'body' => ['contentType' => 'text', 'content' => $subject.' body'],
                'from' => ['emailAddress' => ['address' => $from, 'name' => $from]],
                'toRecipients' => [['emailAddress' => ['address' => $to, 'name' => $to]]],
                'ccRecipients' => [],
                'receivedDateTime' => $at->toIso8601String(),
                'isRead' => false,
                'isDraft' => false,
            ],
            $folder,
            $folder === 'sent' ? 'sent' : 'open',
            $direction
        );
    }
}
