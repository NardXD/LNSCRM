<?php

namespace Tests\Feature;

use App\Jobs\ApplyInboxLeadRulesJob;
use App\Models\Company;
use App\Models\InboxConversation;
use App\Models\Lead;
use App\Models\LeadIdentity;
use App\Models\LeadLabel;
use App\Models\LeadRule;
use App\Models\LeadStatus;
use App\Models\OutlookMailAccount;
use App\Models\SharedInbox;
use App\Models\User;
use App\Services\LeadRuleEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadRuleAddLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_label_applies_multiple_labels_for_inbox_subject_match(): void
    {
        [$inbox, $otherInbox, $lead, $conversation, $labelA, $labelB] = $this->setupThread();

        LeadRule::query()->create([
            'company_id' => $inbox->company_id,
            'name' => 'Newport Payment / Talk2Us',
            'priority' => 10,
            'is_active' => true,
            'triggers' => [
                LeadRuleEngine::TRIGGER_INBOUND_MESSAGE,
                LeadRuleEngine::TRIGGER_INBOUND_MESSAGE_NEW,
                LeadRuleEngine::TRIGGER_OUTBOUND_MESSAGE_NEW,
                LeadRuleEngine::TRIGGER_OUTBOUND_EMAIL_SYNCED,
            ],
            'conditions' => [
                ['field' => 'channel', 'operator' => 'in', 'value' => ['inbox']],
                ['field' => 'shared_inbox', 'operator' => 'in', 'value' => [$inbox->id, $otherInbox->id]],
                ['field' => 'subject', 'operator' => 'contains', 'value' => 'Newport'],
            ],
            // Same shape the /leads rule UI saves for multi-select.
            'actions' => [
                ['type' => 'add_label', 'value' => [$labelA->id, $labelB->id]],
                ['type' => 'reopen_email_thread', 'value' => null],
            ],
        ]);

        (new ApplyInboxLeadRulesJob(
            conversationId: (int) $conversation->id,
            isNew: false,
            bodyText: 'Please see Newport booking.',
            subject: 'Re: Newport villa inquiry',
        ))->handle(app(\App\Services\LeadAutoCreateService::class));

        $lead->refresh();
        $conversation->refresh();

        $this->assertTrue($lead->labels()->where('lead_labels.id', $labelA->id)->exists());
        $this->assertTrue($lead->labels()->where('lead_labels.id', $labelB->id)->exists());
        $this->assertSame('open', $conversation->status);

        $rule = LeadRule::query()->where('name', 'Newport Payment / Talk2Us')->first();
        $this->assertNotNull($rule?->last_applied_at);
        $this->assertSame((int) $lead->id, (int) $rule->last_applied_lead_id);
        $this->assertSame((int) $conversation->id, (int) $rule->last_applied_inbox_conversation_id);
    }

    public function test_add_label_skips_when_subject_does_not_match(): void
    {
        [$inbox, $otherInbox, $lead, $conversation, $labelA] = $this->setupThread();

        LeadRule::query()->create([
            'company_id' => $inbox->company_id,
            'name' => 'Newport only',
            'priority' => 10,
            'is_active' => true,
            'triggers' => [LeadRuleEngine::TRIGGER_INBOUND_MESSAGE],
            'conditions' => [
                ['field' => 'channel', 'operator' => 'in', 'value' => ['inbox']],
                ['field' => 'shared_inbox', 'operator' => 'in', 'value' => [$inbox->id, $otherInbox->id]],
                ['field' => 'subject', 'operator' => 'contains', 'value' => 'Newport'],
            ],
            'actions' => [
                ['type' => 'add_label', 'value' => [$labelA->id]],
            ],
        ]);

        (new ApplyInboxLeadRulesJob(
            conversationId: (int) $conversation->id,
            isNew: false,
            bodyText: 'Unrelated',
            subject: 'General question',
        ))->handle(app(\App\Services\LeadAutoCreateService::class));

        $lead->refresh();
        $this->assertFalse($lead->labels()->where('lead_labels.id', $labelA->id)->exists());
    }

    public function test_add_label_is_skipped_when_no_lead_exists(): void
    {
        [$inbox, $otherInbox, , $conversation, $labelA] = $this->setupThread(withLead: false);

        LeadRule::query()->create([
            'company_id' => $inbox->company_id,
            'name' => 'Newport no lead',
            'priority' => 10,
            'is_active' => true,
            'triggers' => [LeadRuleEngine::TRIGGER_INBOUND_MESSAGE],
            'conditions' => [
                ['field' => 'channel', 'operator' => 'in', 'value' => ['inbox']],
                ['field' => 'shared_inbox', 'operator' => 'in', 'value' => [$inbox->id, $otherInbox->id]],
                ['field' => 'subject', 'operator' => 'contains', 'value' => 'Newport'],
            ],
            'actions' => [
                ['type' => 'add_label', 'value' => [$labelA->id]],
            ],
        ]);

        (new ApplyInboxLeadRulesJob(
            conversationId: (int) $conversation->id,
            isNew: true,
            bodyText: 'Newport inquiry',
            subject: 'Newport villa',
        ))->handle(app(\App\Services\LeadAutoCreateService::class));

        $this->assertSame(0, $labelA->leads()->count());
        $this->assertNull($conversation->fresh()->lead_id);

        $rule = LeadRule::query()->where('name', 'Newport no lead')->first();
        $this->assertNotNull($rule?->last_applied_at);
        $this->assertNull($rule->last_applied_lead_id);
        $this->assertSame((int) $conversation->id, (int) $rule->last_applied_inbox_conversation_id);
    }

    /**
     * @return array{0: SharedInbox, 1: SharedInbox, 2: ?Lead, 3: InboxConversation, 4: LeadLabel, 5?: LeadLabel}
     */
    private function setupThread(bool $withLead = true): array
    {
        $company = Company::query()->create([
            'name' => 'Acme',
            'subdomain' => 'acme-add-label-'.uniqid(),
            'status' => 'active',
            'email' => 'admin-add-label-'.uniqid().'@lns.test',
        ]);
        LeadStatus::ensureForCompany((int) $company->id);

        $user = User::factory()->create(['company_id' => $company->id]);
        $account = OutlookMailAccount::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'email' => 'payment@example.com',
            'access_token' => 'token',
            'refresh_token' => 'refresh',
            'token_expires_at' => now()->addHour(),
            'is_active' => true,
        ]);
        $payment = SharedInbox::query()->create([
            'company_id' => $company->id,
            'outlook_mail_account_id' => $account->id,
            'name' => 'Payment',
            'email' => 'payment@example.com',
            'type' => SharedInbox::TYPE_SHARED,
            'is_active' => true,
        ]);
        $talk2us = SharedInbox::query()->create([
            'company_id' => $company->id,
            'outlook_mail_account_id' => $account->id,
            'name' => 'Talk2Us',
            'email' => 'talk2us@example.com',
            'type' => SharedInbox::TYPE_SHARED,
            'is_active' => true,
        ]);

        $lead = null;
        if ($withLead) {
            $lead = Lead::query()->create([
                'company_id' => $company->id,
                'name' => 'Customer',
                'status' => LeadStatus::fallbackSlug((int) $company->id),
                'source' => 'inbox',
            ]);
            $lead->addIdentity(LeadIdentity::TYPE_EMAIL, 'customer@example.com');
        }

        $labelA = LeadLabel::query()->create([
            'company_id' => $company->id,
            'name' => 'FB Inquiries',
            'color' => '#166534',
        ]);
        $labelB = LeadLabel::query()->create([
            'company_id' => $company->id,
            'name' => 'EWT',
            'color' => '#1d4ed8',
        ]);

        $conversation = InboxConversation::query()->create([
            'company_id' => $company->id,
            'shared_inbox_id' => $payment->id,
            'lead_id' => $lead?->id,
            'folder' => 'inbox',
            'status' => 'archived',
            'subject' => 'Earlier thread',
            'from_name' => 'Customer',
            'from_email' => 'customer@example.com',
            'external_conversation_id' => 'conv-add-label-1',
            'last_message_at' => now()->subDay(),
            'message_count' => 1,
            'is_read' => true,
        ]);

        return [$payment, $talk2us, $lead, $conversation, $labelA, $labelB];
    }
}
