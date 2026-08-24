<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadIdentity;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LeadIdentityRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_primary_and_alternate_contacts_can_be_removed_without_updating_the_lead(): void
    {
        [$user, $lead] = $this->userWithLead();

        $primaryPhone = $lead->identities()->create([
            'type' => LeadIdentity::TYPE_PHONE,
            'value' => '+15551230001',
            'normalized_value' => '+15551230001',
            'label' => 'Primary',
            'is_primary' => true,
        ]);
        $altEmail = $lead->identities()->create([
            'type' => LeadIdentity::TYPE_EMAIL,
            'value' => 'alt@example.com',
            'normalized_value' => 'alt@example.com',
            'label' => 'Alternate',
            'is_primary' => false,
        ]);
        $keepEmail = $lead->identities()->create([
            'type' => LeadIdentity::TYPE_EMAIL,
            'value' => 'keep@example.com',
            'normalized_value' => 'keep@example.com',
            'label' => 'Primary',
            'is_primary' => true,
        ]);

        $this->actingAs($user)
            ->deleteJson('/api/leads/'.$lead->id.'/identities/'.$primaryPhone->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.phone', null);

        $this->actingAs($user)
            ->deleteJson('/api/leads/'.$lead->id.'/identities/'.$altEmail->id)
            ->assertOk()
            ->assertJsonPath('data.alt_email', null)
            ->assertJsonPath('data.email', 'keep@example.com');

        $this->assertDatabaseMissing('lead_identities', ['id' => $primaryPhone->id]);
        $this->assertDatabaseMissing('lead_identities', ['id' => $altEmail->id]);
        $this->assertDatabaseHas('lead_identities', ['id' => $keepEmail->id]);

        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'action' => LeadActivity::IDENTITY_REMOVED,
            'user_id' => $user->id,
        ]);
        $this->assertTrue(
            $lead->activities()->where('action', LeadActivity::IDENTITY_REMOVED)
                ->get()
                ->contains(fn (LeadActivity $activity) => str_contains((string) $activity->summary, 'primary phone'))
        );
        $this->assertTrue(
            $lead->activities()->where('action', LeadActivity::IDENTITY_REMOVED)
                ->get()
                ->contains(fn (LeadActivity $activity) => str_contains((string) $activity->summary, 'alternate email'))
        );
    }

    public function test_identity_from_another_lead_cannot_be_deleted(): void
    {
        [$user, $lead] = $this->userWithLead();
        $other = Lead::query()->create([
            'company_id' => $lead->company_id,
            'name' => 'Other Lead',
            'status' => 'new',
        ]);
        $otherPhone = $other->identities()->create([
            'type' => LeadIdentity::TYPE_PHONE,
            'value' => '+15550009999',
            'normalized_value' => '+15550009999',
            'label' => 'Primary',
            'is_primary' => true,
        ]);

        $this->actingAs($user)
            ->deleteJson('/api/leads/'.$lead->id.'/identities/'.$otherPhone->id)
            ->assertNotFound();

        $this->assertDatabaseHas('lead_identities', ['id' => $otherPhone->id]);
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
            'email' => 'manager@lns.test',
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
