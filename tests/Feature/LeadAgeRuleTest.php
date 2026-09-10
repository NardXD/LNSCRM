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

    /**
     * @param  list<string>  $slugs
     * @return array{0: User, 1: Company}
     */
    private function userWithPermissions(array $slugs): array
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-lead-age',
            'status' => 'active',
            'email' => 'admin-lead-age@lns.test',
            'timezone' => 'UTC',
        ]);

        $role = Role::query()->create([
            'name' => 'Manager',
            'slug' => 'manager-lead-age',
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
            'email' => 'manager-lead-age@lns.test',
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
