<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\FacebookConversation;
use App\Models\FacebookMessage;
use App\Models\Lead;
use App\Models\LeadLabel;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\MessageContactExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FacebookConversationListLeadMatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversation_list_shows_the_assigned_lead_matched_by_phone_not_just_by_name(): void
    {
        $company = Company::create([
            'name' => 'LNS',
            'subdomain' => 'lns-fblist-'.uniqid(),
            'status' => 'active',
            'email' => 'admin-fblist-'.uniqid().'@lns.test',
            'timezone' => 'UTC',
        ]);

        $role = Role::create([
            'name' => 'Staff',
            'slug' => 'staff-fblist-'.uniqid(),
            'company_id' => $company->id,
            'is_active' => true,
        ]);
        $role->permissions()->attach(Permission::create([
            'name' => 'view_facebook',
            'slug' => 'view_facebook',
            'display_name' => 'Facebook & Instagram',
            'company_id' => $company->id,
        ])->id);

        $agent = User::create([
            'name' => 'Alice',
            'email' => 'alice-fblist-'.uniqid().'@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $assignee = User::create([
            'name' => 'Clarence Agustin',
            'email' => 'clarence-fblist-'.uniqid().'@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $label = LeadLabel::create([
            'company_id' => $company->id,
            'name' => 'Inquiry',
            'color' => '#4338ca',
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
        $lead->labels()->attach($label->id);

        // The Facebook display name differs from the lead's own name (a very common
        // real-world case), so a name-only match would miss this — only the phone
        // extracted from the inbound message can link them.
        $conversation = FacebookConversation::create([
            'company_id' => $company->id,
            'channel' => 'messenger',
            'peer_id' => 'peer-'.uniqid(),
            'name' => 'JB M.',
            'last_message_at' => now(),
        ]);

        FacebookMessage::create([
            'company_id' => $company->id,
            'facebook_conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'type' => 'text',
            'text' => "Full name: Jeson Broniola\nPhone number: 0951 332 0904",
            'sent_at' => now(),
        ]);

        // Simulates what already happens on every inbound webhook / opened thread —
        // the extractor caches the extracted phone/email onto the conversation row.
        app(MessageContactExtractor::class)->applyToConversation($conversation);

        $this->actingAs($agent)
            ->getJson('/api/facebook/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.lead.assigned_user.name', 'Clarence Agustin')
            ->assertJsonPath('data.0.lead.labels.0.name', 'Inquiry');
    }
}
