<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadIdentity;
use App\Models\Permission;
use App\Models\PhoneCallLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LeadConnectedThreadAgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_leads_list_exposes_connected_phone_call_thread_and_its_age(): void
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-thread-age',
            'status' => 'active',
            'email' => 'admin-thread-age@lns.test',
        ]);
        $role = Role::query()->create([
            'name' => 'Manager',
            'slug' => 'manager-thread-age',
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
            'email' => 'manager-thread-age@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $lead = Lead::query()->create([
            'company_id' => $company->id,
            'name' => 'John Caller',
            'status' => 'new',
        ]);
        $lead->addIdentity(LeadIdentity::TYPE_PHONE, '+15551234567');

        $startedAt = now()->subHours(5);
        $call = PhoneCallLog::query()->create([
            'company_id' => $company->id,
            'call_sid' => 'CA-thread-age-test',
            'direction' => 'inbound',
            'from_number' => '+15551234567',
            'to_number' => '+15559876543',
            'status' => 'completed',
            'duration' => 42,
            'started_at' => $startedAt,
        ]);

        $payload = $this->actingAs($user)
            ->getJson('/api/leads')
            ->assertOk()
            ->json();

        $row = collect($payload['data'])->firstWhere('id', $lead->id);

        $this->assertNotNull($row);
        $this->assertTrue($row['has_connected_thread']);
        $this->assertSame('call', $row['connected_thread_channel']);
        $this->assertSame($startedAt->toIso8601String(), $row['connected_thread_at']);
        $this->assertNotNull($call->id);
    }
}
