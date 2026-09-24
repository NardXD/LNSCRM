<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadIdentity;
use App\Models\LeadStatus;
use App\Models\Permission;
use App\Models\PhoneCallLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LeadListPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_list_skips_connected_threads_and_includes_status_counts(): void
    {
        [$user, $lead] = $this->userWithLead();
        LeadStatus::ensureForCompany((int) $lead->company_id);

        Lead::query()->create([
            'company_id' => $lead->company_id,
            'name' => 'Contacted Lead',
            'status' => 'contacted',
        ]);

        $lead->addIdentity(LeadIdentity::TYPE_PHONE, '+15551234567');
        PhoneCallLog::query()->create([
            'company_id' => $lead->company_id,
            'call_sid' => 'CA-list-perf',
            'direction' => 'inbound',
            'from_number' => '+15551234567',
            'to_number' => '+15559876543',
            'status' => 'completed',
            'duration' => 10,
            'started_at' => now()->subHour(),
        ]);

        $payload = $this->actingAs($user)
            ->getJson('/api/leads')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status_counts.all', 2)
            ->assertJsonPath('status_counts.new', 1)
            ->assertJsonPath('status_counts.contacted', 1)
            ->json();

        $row = collect($payload['data'])->firstWhere('id', $lead->id);
        $this->assertNotNull($row);
        $this->assertFalse($row['has_connected_thread']);
    }

    public function test_connected_threads_endpoint_hydrates_page_ids(): void
    {
        [$user, $lead] = $this->userWithLead();
        $lead->addIdentity(LeadIdentity::TYPE_PHONE, '+15551234567');
        $startedAt = now()->subHours(2);
        PhoneCallLog::query()->create([
            'company_id' => $lead->company_id,
            'call_sid' => 'CA-hydrate',
            'direction' => 'inbound',
            'from_number' => '+15551234567',
            'to_number' => '+15559876543',
            'status' => 'completed',
            'duration' => 10,
            'started_at' => $startedAt,
        ]);

        $payload = $this->actingAs($user)
            ->getJson('/api/leads/connected-threads?ids[]='.$lead->id)
            ->assertOk()
            ->assertJsonPath('data.'.$lead->id.'.has_connected_thread', true)
            ->assertJsonPath('data.'.$lead->id.'.connected_thread_channel', 'call')
            ->json();

        $this->assertNotEmpty($payload['data'][(string) $lead->id]['connected_thread_at'] ?? null);
        $this->assertNotNull($startedAt);
    }

    /**
     * @return array{0: User, 1: Lead}
     */
    private function userWithLead(): array
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-list-perf-'.uniqid(),
            'status' => 'active',
            'email' => 'admin-list-perf-'.uniqid().'@lns.test',
        ]);
        $role = Role::query()->create([
            'name' => 'Manager',
            'slug' => 'manager-list-perf-'.uniqid(),
            'company_id' => $company->id,
            'is_active' => true,
        ]);
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'view_leads', 'company_id' => $company->id],
            ['name' => 'view_leads', 'display_name' => 'View Leads']
        );
        $role->permissions()->attach($permission->id);
        $user = User::query()->create([
            'name' => 'Manager',
            'email' => 'manager-list-perf-'.uniqid().'@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);
        $lead = Lead::query()->create([
            'company_id' => $company->id,
            'name' => 'New Lead',
            'status' => 'new',
        ]);

        return [$user, $lead];
    }
}
