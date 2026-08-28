<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadLabel;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LeadLabelManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_labels_can_be_created_renamed_and_recolored(): void
    {
        [$user] = $this->userWithLead();

        $created = $this->actingAs($user)
            ->postJson('/api/leads/labels', ['name' => 'VIP', 'color' => '#4338ca'])
            ->assertCreated()
            ->json('data');

        $this->assertSame('VIP', $created['name']);
        $this->assertSame('#4338ca', $created['color']);

        $this->actingAs($user)
            ->patchJson('/api/leads/labels/'.$created['id'], [
                'name' => 'Priority',
                'color' => '#16a34a',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Priority')
            ->assertJsonPath('data.color', '#16a34a');

        $this->assertDatabaseHas('lead_labels', [
            'id' => $created['id'],
            'name' => 'Priority',
            'color' => '#16a34a',
        ]);
    }

    public function test_label_rename_rejects_duplicate_names(): void
    {
        [$user, $lead] = $this->userWithLead();

        $first = LeadLabel::query()->create([
            'company_id' => $lead->company_id,
            'name' => 'Hot',
            'color' => '#4338ca',
        ]);
        LeadLabel::query()->create([
            'company_id' => $lead->company_id,
            'name' => 'Warm',
            'color' => '#ca8a04',
        ]);

        $this->actingAs($user)
            ->patchJson('/api/leads/labels/'.$first->id, ['name' => 'Warm'])
            ->assertStatus(422);
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
            'email' => 'manager-labels@lns.test',
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
