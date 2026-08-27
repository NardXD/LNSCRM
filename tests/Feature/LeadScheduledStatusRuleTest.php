<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadRule;
use App\Models\LeadStatus;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\LeadActivityService;
use App\Services\LeadRuleEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LeadScheduledStatusRuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        Carbon::setTestNow(Carbon::parse('2026-08-27 12:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_status_change_schedules_status_from_the_trigger_date(): void
    {
        [$user, $company] = $this->userWithPermissions(['view_leads', 'create_lead_rules']);
        LeadStatus::ensureForCompany((int) $company->id);

        LeadRule::query()->create([
            'company_id' => $company->id,
            'name' => 'Qualified then contacted',
            'priority' => 10,
            'is_active' => true,
            'triggers' => [LeadRuleEngine::TRIGGER_LEAD_STATUS_CHANGED],
            'conditions' => [
                ['field' => 'status_changed', 'operator' => 'equals', 'value' => 'qualified'],
            ],
            'actions' => [
                ['type' => 'set_status_after_days', 'value' => ['days' => 3, 'status' => 'contacted']],
            ],
        ]);

        $lead = Lead::query()->create([
            'company_id' => $company->id,
            'name' => 'Pat Qualified',
            'first_name' => 'Pat',
            'status' => 'new',
        ]);

        $before = app(LeadActivityService::class)->snapshot($lead);
        $lead->status = 'qualified';
        $lead->save();
        app(LeadActivityService::class)->recordDiff($lead, $before);

        $lead->refresh();
        $this->assertSame('qualified', $lead->status);
        $this->assertSame('qualified', $lead->scheduled_status_from);
        $this->assertSame('contacted', $lead->scheduled_status);
        $this->assertTrue($lead->scheduled_status_at?->equalTo(now()->addDays(3)));
        $this->assertSame(1, LeadActivity::query()->where('lead_id', $lead->id)->where('action', LeadActivity::STATUS_SCHEDULED)->count());

        Carbon::setTestNow(now()->addDays(2));
        Artisan::call('leads:process-scheduled-statuses');
        $lead->refresh();
        $this->assertSame('qualified', $lead->status);
        $this->assertNotNull($lead->scheduled_status_at);

        Carbon::setTestNow(now()->addDay());
        Artisan::call('leads:process-scheduled-statuses');
        $lead->refresh();
        $this->assertSame('contacted', $lead->status);
        $this->assertNull($lead->scheduled_status_at);
        $this->assertNull($lead->scheduled_status);
        $this->assertNull($lead->scheduled_status_from);
    }

    public function test_leaving_the_trigger_status_cancels_the_scheduled_change(): void
    {
        [$user, $company] = $this->userWithPermissions(['view_leads']);
        LeadStatus::ensureForCompany((int) $company->id);

        LeadRule::query()->create([
            'company_id' => $company->id,
            'name' => 'Qualified delay',
            'priority' => 10,
            'is_active' => true,
            'triggers' => [LeadRuleEngine::TRIGGER_LEAD_STATUS_CHANGED],
            'conditions' => [
                ['field' => 'status_changed', 'operator' => 'equals', 'value' => 'qualified'],
            ],
            'actions' => [
                ['type' => 'set_status_after_days', 'value' => ['days' => 3, 'status' => 'contacted']],
            ],
        ]);

        $lead = Lead::query()->create([
            'company_id' => $company->id,
            'name' => 'Left Qualified',
            'first_name' => 'Left',
            'status' => 'new',
        ]);

        $activity = app(LeadActivityService::class);
        $before = $activity->snapshot($lead);
        $lead->status = 'qualified';
        $lead->save();
        $activity->recordDiff($lead, $before);

        $lead->refresh();
        $beforeLost = $activity->snapshot($lead);
        $lead->status = 'lost';
        $lead->save();
        $activity->recordDiff($lead, $beforeLost);

        $lead->refresh();
        $this->assertSame('lost', $lead->status);
        $this->assertNull($lead->scheduled_status_at);

        Carbon::setTestNow(now()->addDays(3));
        Artisan::call('leads:process-scheduled-statuses');
        $lead->refresh();
        $this->assertSame('lost', $lead->status);
    }

    public function test_rule_api_accepts_set_status_after_days(): void
    {
        [$user, $company] = $this->userWithPermissions(['view_leads', 'create_lead_rules']);
        LeadStatus::ensureForCompany((int) $company->id);

        $this->actingAs($user)
            ->postJson('/api/leads/rules', [
                'name' => 'After qualified',
                'triggers' => ['lead_status_changed'],
                'conditions' => [
                    ['field' => 'channel', 'operator' => 'in', 'value' => []],
                    ['field' => 'shared_inbox', 'operator' => 'in', 'value' => []],
                    ['field' => 'status_changed', 'operator' => 'equals', 'value' => 'qualified'],
                ],
                'actions' => [
                    ['type' => 'set_status_after_days', 'value' => ['days' => 7, 'status' => 'contacted']],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.actions.0.type', 'set_status_after_days')
            ->assertJsonPath('data.actions.0.value.days', 7)
            ->assertJsonPath('data.actions.0.value.status', 'contacted');
    }

    /**
     * @param  list<string>  $slugs
     * @return array{0: User, 1: Company}
     */
    private function userWithPermissions(array $slugs): array
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-scheduled-status',
            'status' => 'active',
            'email' => 'admin-scheduled-status@lns.test',
            'timezone' => 'UTC',
        ]);

        $role = Role::query()->create([
            'name' => 'Manager',
            'slug' => 'manager-scheduled-status',
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
            'email' => 'manager-scheduled-status@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        return [$user, $company];
    }
}
