<?php

namespace Tests\Feature;

use App\Jobs\ApplyInboxLeadRulesJob;
use App\Models\Company;
use App\Models\InboxConversation;
use App\Models\InboxMessage;
use App\Models\Lead;
use App\Models\LeadIdentity;
use App\Models\LeadLabel;
use App\Models\LeadRule;
use App\Models\LeadStatus;
use App\Models\OutlookMailAccount;
use App\Models\SharedInbox;
use App\Models\User;
use App\Services\LeadRuleEngine;
use App\Services\OutlookMailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OutboundEmailSyncedLeadRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_sent_folder_sync_dispatches_outbound_email_synced_rules(): void
    {
        [$inbox, $lead, $conversation, $label] = $this->setupSharedInboxThread();

        LeadRule::query()->create([
            'company_id' => $inbox->company_id,
            'name' => 'Payment confirmation synced',
            'priority' => 10,
            'is_active' => true,
            'triggers' => [LeadRuleEngine::TRIGGER_OUTBOUND_EMAIL_SYNCED],
            'conditions' => [
                ['field' => 'channel', 'operator' => 'in', 'value' => ['inbox']],
                ['field' => 'shared_inbox', 'operator' => 'in', 'value' => [$inbox->id]],
                ['field' => 'subject', 'operator' => 'contains', 'value' => 'Payment confirmation'],
            ],
            'actions' => [
                ['type' => 'add_label', 'value' => $label->id],
                ['type' => 'reopen_email_thread', 'value' => null],
            ],
        ]);

        /** @var OutlookMailService $service */
        $service = app(OutlookMailService::class);
        $created = $service->upsertMessage(
            $inbox,
            [
                'id' => 'graph-sent-1',
                'conversationId' => 'conv-shared-1',
                'subject' => 'Payment confirmation #42',
                'bodyPreview' => 'Thanks for your payment.',
                'body' => ['contentType' => 'text', 'content' => 'Thanks for your payment.'],
                'from' => ['emailAddress' => ['address' => 'shared@example.com', 'name' => 'Shared']],
                'toRecipients' => [
                    ['emailAddress' => ['address' => 'customer@example.com', 'name' => 'Customer']],
                ],
                'ccRecipients' => [],
                'receivedDateTime' => now()->toIso8601String(),
                'isRead' => true,
                'isDraft' => false,
            ],
            'sent',
            'open',
            'outbound'
        );

        $this->assertTrue($created);
        $this->assertSame(1, InboxMessage::query()->where('external_message_id', 'graph-sent-1')->count());

        $lead->refresh();
        $conversation->refresh();

        $this->assertTrue($lead->labels()->where('lead_labels.id', $label->id)->exists());
        $this->assertSame('open', $conversation->status);
        $this->assertFalse((bool) $conversation->is_read);
    }

    public function test_local_crm_outbound_merge_does_not_dispatch_synced_rules(): void
    {
        [$inbox, , $conversation] = $this->setupSharedInboxThread();

        InboxMessage::query()->create([
            'inbox_conversation_id' => $conversation->id,
            'external_message_id' => 'local-abc123',
            'direction' => 'outbound',
            'from_email' => 'shared@example.com',
            'to_emails' => 'customer@example.com',
            'subject' => 'Payment confirmation #42',
            'body_text' => 'Thanks for your payment.',
            'is_read' => true,
            'sent_at' => now(),
        ]);

        Queue::fake();

        /** @var OutlookMailService $service */
        $service = app(OutlookMailService::class);
        $created = $service->upsertMessage(
            $inbox,
            [
                'id' => 'graph-sent-merge',
                'conversationId' => 'conv-shared-1',
                'subject' => 'Payment confirmation #42',
                'bodyPreview' => 'Thanks for your payment.',
                'body' => ['contentType' => 'text', 'content' => 'Thanks for your payment.'],
                'from' => ['emailAddress' => ['address' => 'shared@example.com', 'name' => 'Shared']],
                'toRecipients' => [
                    ['emailAddress' => ['address' => 'customer@example.com', 'name' => 'Customer']],
                ],
                'ccRecipients' => [],
                'receivedDateTime' => now()->toIso8601String(),
                'isRead' => true,
                'isDraft' => false,
            ],
            'sent',
            'open',
            'outbound'
        );

        $this->assertFalse($created);
        Queue::assertNothingPushed();
    }

    public function test_sent_mail_older_than_three_days_does_not_dispatch_synced_rules(): void
    {
        [$inbox, $lead, $conversation, $label] = $this->setupSharedInboxThread();

        LeadRule::query()->create([
            'company_id' => $inbox->company_id,
            'name' => 'Payment confirmation synced',
            'priority' => 10,
            'is_active' => true,
            'triggers' => [LeadRuleEngine::TRIGGER_OUTBOUND_EMAIL_SYNCED],
            'conditions' => [
                ['field' => 'channel', 'operator' => 'in', 'value' => ['inbox']],
                ['field' => 'subject', 'operator' => 'contains', 'value' => 'Payment confirmation'],
            ],
            'actions' => [
                ['type' => 'add_label', 'value' => $label->id],
                ['type' => 'reopen_email_thread', 'value' => null],
            ],
        ]);

        Queue::fake();

        /** @var OutlookMailService $service */
        $service = app(OutlookMailService::class);
        $created = $service->upsertMessage(
            $inbox,
            [
                'id' => 'graph-sent-old',
                'conversationId' => 'conv-shared-1',
                'subject' => 'Payment confirmation #99',
                'bodyPreview' => 'Thanks for your payment.',
                'body' => ['contentType' => 'text', 'content' => 'Thanks for your payment.'],
                'from' => ['emailAddress' => ['address' => 'shared@example.com', 'name' => 'Shared']],
                'toRecipients' => [
                    ['emailAddress' => ['address' => 'customer@example.com', 'name' => 'Customer']],
                ],
                'ccRecipients' => [],
                'receivedDateTime' => now()->subDays(4)->toIso8601String(),
                'isRead' => true,
                'isDraft' => false,
            ],
            'sent',
            'open',
            'outbound'
        );

        $this->assertTrue($created);
        $this->assertSame(1, InboxMessage::query()->where('external_message_id', 'graph-sent-old')->count());
        Queue::assertNothingPushed();

        $lead->refresh();
        $conversation->refresh();
        $this->assertFalse($lead->labels()->where('lead_labels.id', $label->id)->exists());
        $this->assertSame('archived', $conversation->status);
    }

    /**
     * @return array{0: SharedInbox, 1: Lead, 2: InboxConversation, 3: LeadLabel}
     */
    private function setupSharedInboxThread(): array
    {
        $company = Company::query()->create([
            'name' => 'Acme',
            'subdomain' => 'acme-synced-'.uniqid(),
            'status' => 'active',
            'email' => 'admin-synced-'.uniqid().'@lns.test',
        ]);
        LeadStatus::ensureForCompany((int) $company->id);

        $user = User::factory()->create(['company_id' => $company->id]);
        $account = OutlookMailAccount::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'email' => 'shared@example.com',
            'access_token' => 'token',
            'refresh_token' => 'refresh',
            'token_expires_at' => now()->addHour(),
            'is_active' => true,
        ]);
        $inbox = SharedInbox::query()->create([
            'company_id' => $company->id,
            'outlook_mail_account_id' => $account->id,
            'name' => 'Talk2Us',
            'email' => 'shared@example.com',
            'type' => SharedInbox::TYPE_SHARED,
            'is_active' => true,
        ]);

        $lead = Lead::query()->create([
            'company_id' => $company->id,
            'name' => 'Customer',
            'status' => LeadStatus::fallbackSlug((int) $company->id),
            'source' => 'inbox',
        ]);
        $lead->addIdentity(LeadIdentity::TYPE_EMAIL, 'customer@example.com');

        $label = LeadLabel::query()->create([
            'company_id' => $company->id,
            'name' => 'Payment confirmed',
            'color' => '#166534',
        ]);

        $conversation = InboxConversation::query()->create([
            'company_id' => $company->id,
            'shared_inbox_id' => $inbox->id,
            'lead_id' => $lead->id,
            'folder' => 'inbox',
            'status' => 'archived',
            'subject' => 'Earlier thread',
            'from_name' => 'Customer',
            'from_email' => 'customer@example.com',
            'external_conversation_id' => 'conv-shared-1',
            'last_message_at' => now()->subDay(),
            'message_count' => 1,
            'is_read' => true,
        ]);

        return [$inbox, $lead, $conversation, $label];
    }
}
