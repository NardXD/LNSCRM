<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Lead;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsAppConversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WhatsAppConversationListLeadNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversation_list_uses_the_saved_lead_name_instead_of_the_whatsapp_profile(): void
    {
        [$agent, $company] = $this->agentWithWhatsApp($companySuffix = uniqid());

        $lead = Lead::create([
            'company_id' => $company->id,
            'name' => 'Maria Santos',
            'status' => 'new',
        ]);
        $lead->syncIdentities([
            ['type' => 'phone', 'value' => '+639171234567', 'is_primary' => true],
        ]);

        WhatsAppConversation::create([
            'company_id' => $company->id,
            'wa_id' => '639171234567',
            'phone' => '639171234567',
            'name' => 'WhatsApp User',
            'profile_name' => 'MS',
            'last_message_at' => now(),
        ]);

        $this->actingAs($agent)
            ->getJson('/api/whatsapp/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Maria Santos');
    }

    public function test_assigned_lead_still_appears_and_its_name_is_used(): void
    {
        [$agent, $company] = $this->agentWithWhatsApp(uniqid());

        $assignee = User::create([
            'name' => 'Clarence Agustin',
            'email' => 'clarence-walist-'.uniqid().'@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $agent->role_id,
            'status' => 'active',
        ]);

        $lead = Lead::create([
            'company_id' => $company->id,
            'name' => 'Jeson Broniola',
            'status' => 'new',
            'assigned_to' => $assignee->id,
        ]);
        $lead->syncIdentities([
            ['type' => 'phone', 'value' => '0951 332 0904', 'is_primary' => true],
        ]);

        WhatsAppConversation::create([
            'company_id' => $company->id,
            'wa_id' => '639513320904',
            'phone' => '639513320904',
            'name' => 'JB',
            'profile_name' => 'JB',
            'last_message_at' => now(),
        ]);

        $this->actingAs($agent)
            ->getJson('/api/whatsapp/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Jeson Broniola')
            ->assertJsonPath('data.0.lead.assigned_user.name', 'Clarence Agustin');
    }

    /**
     * @return array{0: User, 1: Company}
     */
    private function agentWithWhatsApp(string $suffix): array
    {
        $company = Company::create([
            'name' => 'LNS',
            'subdomain' => 'lns-walist-'.$suffix,
            'status' => 'active',
            'email' => 'admin-walist-'.$suffix.'@lns.test',
            'timezone' => 'UTC',
        ]);

        $role = Role::create([
            'name' => 'Staff',
            'slug' => 'staff-walist-'.$suffix,
            'company_id' => $company->id,
            'is_active' => true,
        ]);
        $role->permissions()->attach(Permission::create([
            'name' => 'view_whatsapp',
            'slug' => 'view_whatsapp',
            'display_name' => 'WhatsApp',
            'company_id' => $company->id,
        ])->id);

        $agent = User::create([
            'name' => 'Alice',
            'email' => 'alice-walist-'.$suffix.'@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        return [$agent, $company];
    }
}
