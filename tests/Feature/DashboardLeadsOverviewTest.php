<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\FacebookConversation;
use App\Models\FacebookMessage;
use App\Models\InboxConversation;
use App\Models\InboxMessage;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadStatus;
use App\Models\Permission;
use App\Models\PhoneCallLog;
use App\Models\Role;
use App\Models\SharedInbox;
use App\Models\SmsConversation;
use App\Models\SmsMessage;
use App\Models\User;
use App\Models\ViberConversation;
use App\Models\ViberMessage;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Services\DashboardOverviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardLeadsOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_focuses_on_leads_and_channel_activity(): void
    {
        [$user] = $this->userWithDashboardAccess();
        $companyId = (int) $user->company_id;

        Lead::query()->create([
            'company_id' => $companyId,
            'name' => 'Ava Converted',
            'status' => 'converted',
            'source' => 'Facebook',
        ]);
        Lead::query()->create([
            'company_id' => $companyId,
            'name' => 'Ben New',
            'status' => 'new',
            'source' => 'SMS/Text',
        ]);
        Lead::query()->create([
            'company_id' => $companyId,
            'name' => 'Cara Lost',
            'status' => 'lost',
            'source' => 'Website',
        ]);
        Lead::query()->create([
            'company_id' => $companyId,
            'name' => 'Archived Should Hide',
            'status' => Lead::STATUS_ARCHIVED,
            'source' => 'Website',
        ]);
        $lastMonthLead = Lead::query()->create([
            'company_id' => $companyId,
            'name' => 'Last Month Lead',
            'status' => 'new',
            'source' => 'Website',
        ]);
        Lead::query()->whereKey($lastMonthLead->id)->update([
            'created_at' => now()->subMonth(),
            'updated_at' => now()->subMonth(),
        ]);

        $otherCompany = Company::query()->create([
            'name' => 'Other Co',
            'subdomain' => 'other-dashboard',
            'status' => 'active',
            'email' => 'other-dashboard@lns.test',
        ]);
        Lead::query()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Other Company Lead',
            'status' => 'new',
        ]);

        $this->seedChannels($companyId, $otherCompany->id);

        $page = $this->actingAs($user)->get('/dashboard');
        $page->assertOk();
        $page->assertSee('Lead pipeline and channel activity for', false);
        $page->assertSee('Leads this month', false);
        $page->assertSee('Phone System', false);
        $page->assertSee('Inbox', false);
        $page->assertSee('Viber', false);
        $page->assertSee('Facebook', false);
        $page->assertSee('SMS', false);
        $page->assertSee('WhatsApp', false);
        $page->assertSee('conversion rate', false);
        $page->assertSee('page-skel-stat', false);
        $page->assertDontSee('Ava Converted', false);
        $page->assertDontSee('Archived Should Hide', false);
        $page->assertDontSee('Other Company Lead', false);
        $page->assertDontSee('Last Month Lead', false);
        $page->assertDontSee('Total Revenue', false);
        $page->assertDontSee('Active Projects', false);

        $overview = $this->actingAs($user)->getJson('/api/dashboard/overview');
        $overview->assertOk();
        $overview->assertJsonPath('leads.total', 3);
        $overview->assertJsonPath('leads.converted', 1);
        $overview->assertJsonFragment(['name' => 'Ava Converted']);
        $overview->assertJsonFragment(['name' => 'Ben New']);
        $overview->assertJsonMissing(['name' => 'Archived Should Hide']);
        $overview->assertJsonMissing(['name' => 'Other Company Lead']);
        $overview->assertJsonMissing(['name' => 'Last Month Lead']);
        $this->assertStringContainsString('calls this month', $overview->getContent());
        $this->assertStringContainsString('open threads', $overview->getContent());
        $this->assertStringContainsString('Unread inbox lead', $overview->getContent());
        $this->assertStringContainsString('Viber Unread', $overview->getContent());
        $this->assertStringContainsString('Facebook Unread', $overview->getContent());
        $this->assertStringContainsString('SMS Unread', $overview->getContent());
        $this->assertStringContainsString('WhatsApp Unread', $overview->getContent());
    }

    public function test_dashboard_overview_uses_a_small_number_of_queries(): void
    {
        [$user] = $this->userWithDashboardAccess();
        $other = Company::query()->create([
            'name' => 'Other Co Queries',
            'subdomain' => 'other-dashboard-queries',
            'status' => 'active',
            'email' => 'other-dashboard-queries@lns.test',
        ]);
        $this->seedChannels((int) $user->company_id, (int) $other->id);
        LeadStatus::ensureForCompany((int) $user->company_id);

        DB::flushQueryLog();
        DB::enableQueryLog();
        app(DashboardOverviewService::class)->forCompany((int) $user->company_id);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(
            25,
            $queryCount,
            'Dashboard overview should not issue more than 25 queries, got '.$queryCount
        );
    }

    public function test_guests_cannot_view_the_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect();
    }

    /**
     * @return array{0: User}
     */
    private function userWithDashboardAccess(): array
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-dashboard',
            'status' => 'active',
            'email' => 'admin-dashboard@lns.test',
        ]);

        $role = Role::query()->create([
            'name' => 'Manager',
            'slug' => 'manager-dashboard',
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        $slugs = [
            'view_dashboard',
            'view_leads',
            'view_lead_reports',
            'view_phone_system',
            'view_inbox',
            'view_viber',
            'view_facebook',
            'view_sms',
            'view_whatsapp',
        ];
        foreach ($slugs as $slug) {
            $permission = Permission::query()->create([
                'name' => $slug,
                'slug' => $slug,
                'display_name' => ucwords(str_replace('_', ' ', $slug)),
                'company_id' => $company->id,
            ]);
            $role->permissions()->attach($permission->id);
        }

        $user = User::query()->create([
            'name' => 'Dashboard Manager',
            'email' => 'dashboard-manager@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        return [$user];
    }

    private function seedChannels(int $companyId, int $otherCompanyId): void
    {
        PhoneCallLog::query()->create([
            'company_id' => $companyId,
            'call_sid' => 'CA-inbound-1',
            'direction' => 'inbound',
            'from_number' => '+15551110001',
            'to_number' => '+15552220000',
            'status' => 'completed',
            'duration' => 42,
        ]);
        PhoneCallLog::query()->create([
            'company_id' => $companyId,
            'call_sid' => 'CA-missed-1',
            'direction' => 'inbound',
            'from_number' => '+15551110002',
            'to_number' => '+15552220000',
            'status' => 'no-answer',
            'duration' => 0,
        ]);
        PhoneCallLog::query()->create([
            'company_id' => $otherCompanyId,
            'call_sid' => 'CA-other-1',
            'direction' => 'inbound',
            'status' => 'completed',
        ]);

        $inbox = SharedInbox::query()->create([
            'company_id' => $companyId,
            'name' => 'Support',
            'email' => 'support@example.com',
            'type' => SharedInbox::TYPE_SHARED,
            'is_active' => true,
        ]);
        $conversation = InboxConversation::query()->create([
            'company_id' => $companyId,
            'shared_inbox_id' => $inbox->id,
            'folder' => 'inbox',
            'external_conversation_id' => 'conv-unread',
            'subject' => 'Unread inbox lead',
            'snippet' => 'Need a quote',
            'from_name' => 'Maya',
            'from_email' => 'maya@example.com',
            'status' => 'open',
            'is_read' => false,
            'message_count' => 1,
            'last_message_at' => now(),
        ]);
        InboxMessage::query()->create([
            'inbox_conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'from_name' => 'Maya',
            'from_email' => 'maya@example.com',
            'subject' => 'Unread inbox lead',
            'body_text' => 'Need a quote',
            'sent_at' => now(),
        ]);

        $viber = ViberConversation::query()->create([
            'company_id' => $companyId,
            'viber_user_id' => 'vb-1',
            'name' => 'Viber Unread',
            'unread_count' => 2,
            'last_message_preview' => 'Hello from Viber',
            'last_message_at' => now(),
        ]);
        ViberMessage::query()->create([
            'company_id' => $companyId,
            'viber_conversation_id' => $viber->id,
            'direction' => 'inbound',
            'type' => 'text',
            'text' => 'Hello from Viber',
            'sent_at' => now(),
        ]);

        $facebook = FacebookConversation::query()->create([
            'company_id' => $companyId,
            'channel' => 'messenger',
            'peer_id' => 'fb-1',
            'name' => 'Facebook Unread',
            'unread_count' => 3,
            'last_message_preview' => 'Hello from Facebook',
            'last_message_at' => now(),
        ]);
        FacebookMessage::query()->create([
            'company_id' => $companyId,
            'facebook_conversation_id' => $facebook->id,
            'direction' => 'inbound',
            'type' => 'text',
            'text' => 'Hello from Facebook',
            'sent_at' => now(),
        ]);

        $sms = SmsConversation::query()->create([
            'company_id' => $companyId,
            'peer_phone' => '+15553330001',
            'our_number' => '+15552220000',
            'name' => 'SMS Unread',
            'unread_count' => 1,
            'last_message_preview' => 'Hello from SMS',
            'last_message_at' => now(),
        ]);
        SmsMessage::query()->create([
            'company_id' => $companyId,
            'sms_conversation_id' => $sms->id,
            'message_sid' => 'SM-dashboard-1',
            'direction' => 'inbound',
            'from_number' => '+15553330001',
            'to_number' => '+15552220000',
            'body' => 'Hello from SMS',
            'sent_at' => now(),
        ]);

        $whatsapp = WhatsAppConversation::query()->create([
            'company_id' => $companyId,
            'wa_id' => '15554440001',
            'name' => 'WhatsApp Unread',
            'profile_name' => 'WhatsApp Unread',
            'phone' => '+15554440001',
            'unread_count' => 4,
            'last_message_preview' => 'Hello from WhatsApp',
            'last_message_at' => now(),
        ]);
        WhatsAppMessage::query()->create([
            'company_id' => $companyId,
            'whatsapp_conversation_id' => $whatsapp->id,
            'direction' => 'inbound',
            'type' => 'text',
            'text' => 'Hello from WhatsApp',
            'sent_at' => now(),
        ]);

        $lead = Lead::query()->where('company_id', $companyId)->where('name', 'Ben New')->first();
        if ($lead) {
            LeadActivity::query()->create([
                'lead_id' => $lead->id,
                'action' => LeadActivity::CREATED,
                'summary' => 'Lead created from SMS',
            ]);
        }
    }
}
