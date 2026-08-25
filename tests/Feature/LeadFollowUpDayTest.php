<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadIdentity;
use App\Models\LeadLabel;
use App\Models\LeadRule;
use App\Models\LeadStatus;
use App\Models\MessageTemplate;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsAppConversation;
use App\Services\LeadRuleEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LeadFollowUpDayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        Carbon::setTestNow(Carbon::parse('2026-08-26 12:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_list_filters_and_counts_follow_up_days(): void
    {
        [$user, $company] = $this->userWithPermissions(['view_leads']);

        $createdToday = $this->makeLead($company, 'Created Today', 'new', now());
        $day4 = $this->makeLead($company, 'Day Four', 'new', now()->subDays(4));
        $day10 = $this->makeLead($company, 'Day Ten', 'new', now()->subDays(10));
        $old = $this->makeLead($company, 'Old Lead', 'new', now()->subDays(100));
        $converted = $this->makeLead($company, 'Converted Four', 'converted', now()->subDays(4));
        $snoozed = $this->makeLead($company, 'Snoozed Four', Lead::STATUS_SNOOZED, now()->subDays(4));
        $moveIn = $this->makeLead($company, 'Move In Four', 'new', now()->subDays(4));
        $inquiry = LeadLabel::query()->firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Inquiry'],
            ['color' => '#9333ea']
        );
        $moveInLabel = LeadLabel::query()->firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Move in'],
            ['color' => '#16a34a']
        );
        foreach ([$day4, $day10, $old, $converted, $snoozed, $moveIn] as $lead) {
            $lead->labels()->syncWithoutDetaching([$inquiry->id]);
        }
        $moveIn->labels()->syncWithoutDetaching([$moveInLabel->id]);

        Artisan::call('leads:process-follow-up-days');

        $this->actingAs($user)
            ->getJson('/api/leads?follow_up_day=4')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['id' => $day4->id, 'follow_up_day' => 4])
            ->assertJsonFragment(['id' => $snoozed->id])
            ->assertJsonMissing(['name' => 'Created Today'])
            ->assertJsonMissing(['name' => 'Day Ten'])
            ->assertJsonMissing(['name' => 'Converted Four'])
            ->assertJsonMissing(['name' => 'Move In Four']);

        $counts = $this->actingAs($user)
            ->getJson('/api/leads/follow-up-counts')
            ->assertOk()
            ->json('data');

        $this->assertSame([4, 10, 30, 90], $counts['days']);
        $this->assertSame('4th Day FU', collect($counts['labels'])->firstWhere('day', 4)['name']);
        $this->assertSame(2, $counts['counts']['4']);
        $this->assertSame(1, $counts['counts']['10']);
        $this->assertSame(0, $counts['counts']['30']);
        $this->assertSame(1, $counts['counts']['90']);
    }

    public function test_lead_labels_can_be_deleted_and_are_not_recreated(): void
    {
        [$user, $company] = $this->userWithPermissions(['view_leads']);

        $this->actingAs($user)
            ->putJson('/api/leads/follow-up-days', ['days' => [4, 10, 30, 90]])
            ->assertOk();

        $custom = LeadLabel::query()->create([
            'company_id' => $company->id,
            'name' => 'Custom tag',
            'color' => '#111111',
        ]);
        $fourth = LeadLabel::query()
            ->where('company_id', $company->id)
            ->where('name', '4th Day FU')
            ->firstOrFail();
        $inquiry = LeadLabel::query()
            ->where('company_id', $company->id)
            ->where('name', 'Inquiry')
            ->firstOrFail();

        $this->actingAs($user)
            ->deleteJson('/api/leads/labels/'.$custom->id)
            ->assertOk();
        $this->actingAs($user)
            ->deleteJson('/api/leads/labels/'.$fourth->id)
            ->assertOk();
        $this->actingAs($user)
            ->deleteJson('/api/leads/labels/'.$inquiry->id)
            ->assertOk();

        $this->actingAs($user)
            ->getJson('/api/leads/follow-up-counts')
            ->assertOk()
            ->assertJsonPath('data.days', [10, 30, 90]);

        $names = $this->actingAs($user)
            ->getJson('/api/leads/labels')
            ->assertOk()
            ->json('data');
        $names = collect($names)->pluck('name')->all();

        $this->assertNotContains('Custom tag', $names);
        $this->assertNotContains('4th Day FU', $names);
        $this->assertNotContains('Inquiry', $names);
        $this->assertContains('10th Day FU', $names);
    }

    public function test_follow_up_days_can_be_configured(): void
    {
        [$user, $company] = $this->userWithPermissions(['view_leads']);
        $dayTwo = $this->makeLead($company, 'Day Two', 'new', now()->subDays(2));
        $daySeven = $this->makeLead($company, 'Day Seven', 'new', now()->subDays(7));
        $inquiry = LeadLabel::query()->firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Inquiry'],
            ['color' => '#9333ea']
        );
        $dayTwo->labels()->syncWithoutDetaching([$inquiry->id]);
        $daySeven->labels()->syncWithoutDetaching([$inquiry->id]);

        $this->actingAs($user)
            ->putJson('/api/leads/follow-up-days', ['days' => [2, 7]])
            ->assertOk()
            ->assertJsonPath('data.days', [2, 7])
            ->assertJsonPath('data.labels.0.name', '2nd Day FU')
            ->assertJsonPath('data.labels.1.name', '7th Day FU');

        Artisan::call('leads:process-follow-up-days');

        $counts = $this->actingAs($user)
            ->getJson('/api/leads/follow-up-counts')
            ->assertOk()
            ->json('data');

        $this->assertSame([2, 7], $counts['days']);
        $this->assertSame(1, $counts['counts']['2']);
        $this->assertSame(1, $counts['counts']['7']);
        $this->assertArrayNotHasKey('4', $counts['counts']);
    }

    public function test_follow_up_day_rule_fires_once_per_day_and_can_unsnooze(): void
    {
        [$user, $company] = $this->userWithPermissions(['view_leads']);
        $label = LeadLabel::query()->create([
            'company_id' => $company->id,
            'name' => 'Day 2 follow-up',
            'color' => '#4338ca',
        ]);
        LeadStatus::ensureForCompany((int) $company->id);

        LeadRule::query()->create([
            'company_id' => $company->id,
            'name' => 'Tag day 2',
            'priority' => 10,
            'is_active' => true,
            'triggers' => [LeadRuleEngine::TRIGGER_FOLLOW_UP_DAY_REACHED],
            'conditions' => [
                ['field' => 'follow_up_day', 'operator' => 'equals', 'value' => '2'],
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

        Artisan::call('leads:process-follow-up-days');
        Artisan::call('leads:process-follow-up-days');

        $lead->refresh();
        $this->assertSame(2, (int) $lead->follow_up_notified_day);
        $this->assertTrue($lead->labels()->where('lead_labels.id', $label->id)->exists());
        $this->assertSame('contacted', $lead->status);
        $this->assertSame(1, LeadActivity::query()->where('lead_id', $lead->id)->where('action', LeadActivity::FOLLOW_UP_DAY)->count());

        $day3->refresh();
        $this->assertSame(3, (int) $day3->follow_up_notified_day);
        $this->assertFalse($day3->labels()->where('lead_labels.id', $label->id)->exists());
    }

    public function test_message_channels_skip_whatsapp_without_thread(): void
    {
        [$user, $company] = $this->userWithPermissions(['view_leads', 'view_whatsapp', 'view_sms', 'send_sms']);
        $lead = $this->makeLead($company, 'Email Only', 'new', now()->subDays(1));
        $lead->addIdentity(LeadIdentity::TYPE_EMAIL, 'only@example.com');

        $channels = $this->actingAs($user)
            ->getJson('/api/leads/'.$lead->id.'/message-channels')
            ->assertOk()
            ->json('data.channels');

        $whatsapp = collect($channels)->firstWhere('id', 'whatsapp');
        $this->assertFalse($whatsapp['available']);
        $this->assertStringContainsString('WhatsApp', (string) $whatsapp['reason']);

        $this->actingAs($user)
            ->postJson('/api/leads/'.$lead->id.'/messages', [
                'channel' => 'whatsapp',
                'body' => 'Hello',
            ])
            ->assertStatus(422);

        WhatsAppConversation::query()->create([
            'company_id' => $company->id,
            'wa_id' => '639171111111',
            'phone' => '+639171111111',
            'name' => 'Email Only',
        ]);
        $lead->addIdentity(LeadIdentity::TYPE_PHONE, '+639171111111');

        $withPhone = $this->actingAs($user)
            ->getJson('/api/leads/'.$lead->fresh()->id.'/message-channels')
            ->assertOk()
            ->json('data.channels');

        $whatsappReady = collect($withPhone)->firstWhere('id', 'whatsapp');
        $this->assertTrue($whatsappReady['available']);

        $this->actingAs($user)
            ->postJson('/api/leads/'.$lead->id.'/messages', [
                'channel' => 'whatsapp',
                'body' => 'Following up on day {{follow_up_day}}',
            ])
            ->assertStatus(422);

        MessageTemplate::query()->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'channel' => MessageTemplate::CHANNEL_WHATSAPP,
            'name' => 'Day 2 ping',
            'body_text' => 'Hi {{first_name}}',
        ]);

        $this->assertSame(0, LeadActivity::query()->where('lead_id', $lead->id)->where('action', LeadActivity::TEMPLATE_SENT)->count());
    }

    /**
     * @param  list<string>  $slugs
     * @return array{0: User, 1: Company}
     */
    private function userWithPermissions(array $slugs): array
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-followup',
            'status' => 'active',
            'email' => 'admin-followup@lns.test',
            'timezone' => 'UTC',
        ]);

        $role = Role::query()->create([
            'name' => 'Manager',
            'slug' => 'manager-followup',
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
            'email' => 'manager-followup@lns.test',
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
