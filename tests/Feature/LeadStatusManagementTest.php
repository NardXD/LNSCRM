<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LeadStatusManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_statuses_can_be_created_renamed_and_deleted(): void
    {
        [$user, $lead] = $this->userWithLead();

        $this->actingAs($user)
            ->getJson('/api/leads/statuses')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertTrue(
            LeadStatus::query()->where('company_id', $lead->company_id)->where('slug', 'new')->exists()
        );

        $created = $this->actingAs($user)
            ->postJson('/api/leads/statuses', ['name' => 'Hot'])
            ->assertCreated()
            ->json('data');

        $this->assertSame('Hot', $created['name']);
        $this->assertSame('hot', $created['slug']);

        $this->actingAs($user)
            ->patchJson('/api/leads/statuses/'.$created['id'], ['name' => 'Very Hot'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Very Hot')
            ->assertJsonPath('data.slug', 'hot');

        $lead->update(['status' => 'hot']);

        $this->actingAs($user)
            ->deleteJson('/api/leads/statuses/'.$created['id'])
            ->assertOk();

        $this->assertDatabaseMissing('lead_statuses', ['id' => $created['id']]);
        $this->assertSame('new', $lead->fresh()->status);
    }

    public function test_snoozed_status_cannot_be_deleted(): void
    {
        [$user, $lead] = $this->userWithLead();
        LeadStatus::ensureForCompany((int) $lead->company_id);
        $snoozed = LeadStatus::query()
            ->where('company_id', $lead->company_id)
            ->where('slug', Lead::STATUS_SNOOZED)
            ->firstOrFail();

        $this->actingAs($user)
            ->deleteJson('/api/leads/statuses/'.$snoozed->id)
            ->assertStatus(422);
    }

    public function test_lead_list_includes_counts_per_status_tab(): void
    {
        [$user, $lead] = $this->userWithLead();
        LeadStatus::ensureForCompany((int) $lead->company_id);

        Lead::query()->create([
            'company_id' => $lead->company_id,
            'name' => 'Contacted Lead',
            'status' => 'contacted',
        ]);
        Lead::query()->create([
            'company_id' => $lead->company_id,
            'name' => 'Archived Lead',
            'status' => Lead::STATUS_ARCHIVED,
        ]);

        $this->actingAs($user)
            ->getJson('/api/leads')
            ->assertOk()
            ->assertJsonPath('status_counts.all', 2)
            ->assertJsonPath('status_counts.new', 1)
            ->assertJsonPath('status_counts.contacted', 1)
            ->assertJsonPath('status_counts.archived', 1);
    }

    /**
     * @return array{0: User, 1: Lead}
     */
    private function userWithLead(): array
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns',
            'status' => 'active',
            'email' => 'admin@lns.test',
        ]);

        $role = Role::query()->create([
            'name' => 'Manager',
            'slug' => 'manager',
            'company_id' => $company->id,
            'is_active' => true,
        ]);
        $permission = Permission::query()->create([
            'name' => 'view_leads',
            'slug' => 'view_leads',
            'display_name' => 'View Leads',
            'company_id' => $company->id,
        ]);
        $role->permissions()->attach($permission->id);

        $user = User::query()->create([
            'name' => 'Manager',
            'email' => 'manager-status@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $lead = Lead::query()->create([
            'company_id' => $company->id,
            'name' => 'Jane Doe',
            'status' => 'new',
        ]);

        return [$user, $lead];
    }
}
