<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadIdentity;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsAppConversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LeadContactHistoryWhatsAppMatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_whatsapp_thread_matches_lead_phone_despite_different_formatting(): void
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-wa-history',
            'status' => 'active',
            'email' => 'admin-wa-history@lns.test',
        ]);
        $role = Role::query()->create([
            'name' => 'Manager',
            'slug' => 'manager-wa-history',
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
            'email' => 'manager-wa-history@lns.test',
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
        // Deliberately formatted differently from how it's stored on the WhatsApp side.
        $lead->addIdentity(LeadIdentity::TYPE_PHONE, '555-123-4567');

        $matching = WhatsAppConversation::query()->create([
            'company_id' => $company->id,
            'wa_id' => '15551234567',
            'name' => 'Jane',
            'unread_count' => 0,
        ]);

        $unrelated = WhatsAppConversation::query()->create([
            'company_id' => $company->id,
            'wa_id' => '19998887777',
            'name' => 'Someone Else',
            'unread_count' => 0,
        ]);

        $payload = $this->actingAs($user)
            ->getJson('/api/leads/'.$lead->id.'/history')
            ->assertOk()
            ->json();

        $threadIds = collect($payload['threads'])->where('channel', 'whatsapp')->pluck('conversation_id')->all();

        $this->assertContains($matching->id, $threadIds);
        $this->assertNotContains($unrelated->id, $threadIds);
    }
}
