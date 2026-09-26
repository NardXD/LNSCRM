<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadLabel;
use App\Models\LeadRule;
use App\Models\LeadStatus;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\LeadRuleEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LeadAgeRuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-08-26 12:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_lead_age_rule_fires_once_per_day_and_can_unsnooze(): void
    {
        [$user, $company] = $this->userWithPermissions(['view_leads']);
        $label = LeadLabel::query()->create([
            'company_id' => $company->id,
            'name' => 'Day 2 tag',
            'color' => '#4338ca',
        ]);
        LeadStatus::ensureForCompany((int) $company->id);

        LeadRule::query()->create([
            'company_id' => $company->id,
            'name' => 'Tag day 2',
            'priority' => 10,
            'is_active' => true,
            'triggers' => [LeadRuleEngine::TRIGGER_LEAD_AGE_REACHED],
            'conditions' => [
                ['field' => 'lead_age', 'operator' => 'equals', 'value' => '2'],
            ],
            'actions' => [
                ['type' => 'add_label', 'value' => $label->id],
                ['type' => 'unsnooze', 'value' => null],
            ],
        ]);

        $lead = $this->makeLead($company, 'Snoozed Two', Lead::STATUS_SNOOZED, now()->subDays(2), [
            'reopen_status' => 'contacted',
        ]);
        $day3 = $this->makeLead($company, 'Day Three', 'new', now()->subDays(3));
        $converted = $this->makeLead($company, 'Converted Two', 'converted', now()->subDays(2));

        Artisan::call('leads:process-lead-age');
        Artisan::call('leads:process-lead-age');

        $lead->refresh();
        $this->assertSame(2, (int) $lead->follow_up_notified_day);
        $this->assertTrue($lead->labels()->where('lead_labels.id', $label->id)->exists());
        $this->assertSame('contacted', $lead->status);

        $day3->refresh();
        $this->assertSame(3, (int) $day3->follow_up_notified_day);
        $this->assertFalse($day3->labels()->where('lead_labels.id', $label->id)->exists());

        $converted->refresh();
        $this->assertNull($converted->follow_up_notified_day);
        $this->assertFalse($converted->labels()->where('lead_labels.id', $label->id)->exists());
    }

    public function test_lead_age_rule_matches_channel_and_shared_inbox_from_linked_thread(): void
    {
        [$user, $company] = $this->userWithPermissions(['view_leads']);
        LeadStatus::ensureForCompany((int) $company->id);

        $account = \App\Models\OutlookMailAccount::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'email' => 'talk2us@example.com',
            'access_token' => 'token',
            'refresh_token' => 'refresh',
            'token_expires_at' => now()->addHour(),
            'is_active' => true,
        ]);
        $talk2us = \App\Models\SharedInbox::query()->create([
            'company_id' => $company->id,
            'outlook_mail_account_id' => $account->id,
            'name' => 'Talk2Us',
            'email' => 'talk2us@example.com',
            'type' => \App\Models\SharedInbox::TYPE_SHARED,
            'is_active' => true,
        ]);
        $otherInbox = \App\Models\SharedInbox::query()->create([
            'company_id' => $company->id,
            'outlook_mail_account_id' => $account->id,
            'name' => 'Payment',
            'email' => 'payment@example.com',
            'type' => \App\Models\SharedInbox::TYPE_SHARED,
            'is_active' => true,
        ]);

        $fuLabel = LeadLabel::query()->create([
            'company_id' => $company->id,
            'name' => '2nd Day FU',
            'color' => '#4338ca',
        ]);
        $seedLabel = LeadLabel::query()->create([
            'company_id' => $company->id,
            'name' => 'Inquiry',
            'color' => '#166534',
        ]);

        LeadRule::query()->create([
            'company_id' => $company->id,
            'name' => '2nd day FU',
            'priority' => 10,
            'is_active' => true,
            'triggers' => [LeadRuleEngine::TRIGGER_LEAD_AGE_REACHED],
            'conditions' => [
                ['field' => 'channel', 'operator' => 'in', 'value' => ['inbox']],
                ['field' => 'shared_inbox', 'operator' => 'in', 'value' => [$talk2us->id]],
                ['field' => 'lead_age', 'operator' => 'greater_than', 'value' => '1'],
                ['field' => 'lead_label', 'operator' => 'has', 'value' => []],
                ['field' => 'lead_label', 'operator' => 'does_not_have', 'value' => [$fuLabel->id]],
            ],
            'actions' => [
                ['type' => 'notify_assignee', 'value' => null],
                ['type' => 'add_label', 'value' => [$fuLabel->id]],
            ],
        ]);

        $matched = $this->makeLead($company, 'Talk2Us Lead', 'new', now()->subDays(2), [
            'source' => 'web',
            'assigned_to' => $user->id,
        ]);
        $matched->labels()->attach($seedLabel->id);
        \App\Models\InboxConversation::query()->create([
            'company_id' => $company->id,
            'shared_inbox_id' => $talk2us->id,
            'lead_id' => $matched->id,
            'folder' => 'inbox',
            'status' => 'archived',
            'subject' => 'Follow up',
            'from_name' => 'Customer',
            'from_email' => 'customer@example.com',
            'external_conversation_id' => 'conv-age-1',
            'last_message_at' => now()->subDay(),
            'message_count' => 1,
            'is_read' => true,
        ]);

        $wrongInbox = $this->makeLead($company, 'Payment Lead', 'new', now()->subDays(2), [
            'source' => 'inbox',
            'assigned_to' => $user->id,
        ]);
        \App\Models\InboxConversation::query()->create([
            'company_id' => $company->id,
            'shared_inbox_id' => $otherInbox->id,
            'lead_id' => $wrongInbox->id,
            'folder' => 'inbox',
            'status' => 'archived',
            'subject' => 'Payment',
            'from_name' => 'Other',
            'from_email' => 'other@example.com',
            'external_conversation_id' => 'conv-age-2',
            'last_message_at' => now()->subDay(),
            'message_count' => 1,
            'is_read' => true,
        ]);

        Artisan::call('leads:process-lead-age');

        $matched->refresh();
        $wrongInbox->refresh();
        $this->assertTrue($matched->labels()->where('lead_labels.id', $fuLabel->id)->exists());
        $this->assertFalse($wrongInbox->labels()->where('lead_labels.id', $fuLabel->id)->exists());
    }

    /**
     * @param  list<string>  $slugs
     * @return array{0: User, 1: Company}
     */
    private function userWithPermissions(array $slugs): array
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-lead-age-'.uniqid(),
            'status' => 'active',
            'email' => 'admin-lead-age-'.uniqid().'@lns.test',
            'timezone' => 'UTC',
        ]);

        $role = Role::query()->create([
            'name' => 'Manager',
            'slug' => 'manager-lead-age-'.uniqid(),
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        foreach ($slugs as $slug) {
            $permission = Permission::query()->create([
                'name' => $slug,
                'slug' => $slug,
                'display_name' => $slug,
                'company_id' => $company->id,
            ]);
            $role->permissions()->attach($permission->id);
        }

        $user = User::query()->create([
            'name' => 'Manager',
            'email' => 'manager-lead-age-'.uniqid().'@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        return [$user, $company];
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function makeLead(Company $company, string $name, string $status, Carbon $createdAt, array $extra = []): Lead
    {
        $lead = Lead::query()->create(array_merge([
            'company_id' => $company->id,
            'name' => $name,
            'first_name' => explode(' ', $name)[0],
            'status' => $status,
        ], $extra));
        $lead->created_at = $createdAt;
        $lead->updated_at = $createdAt;
        $lead->save();

        return $lead->fresh();
    }
}
