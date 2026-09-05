<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\FacebookConversation;
use App\Models\Lead;
use App\Models\LeadLabel;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LeadFacebookConversionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_saving_a_labeled_conversation_as_a_lead_carries_the_label_over(): void
    {
        [$user, $conversation] = $this->userWithConversation();

        $label = LeadLabel::create([
            'company_id' => $conversation->company_id,
            'name' => 'Hot lead',
            'color' => '#4338ca',
        ]);
        $conversation->leadLabels()->attach($label->id);

        $response = $this->actingAs($user)->postJson('/api/leads', [
            'name' => 'Jane Customer',
            'phones' => [['value' => '5551234567', 'label' => null]],
            'facebook_conversation_id' => $conversation->id,
            'source' => 'facebook',
        ]);

        $response->assertCreated();

        $lead = Lead::findOrFail($response->json('data.id'));
        $this->assertTrue($lead->labels()->where('lead_labels.id', $label->id)->exists());

        // The conversation's own (pre-lead) label pivot is cleared once it has been
        // carried over, so it isn't shown twice (once as a conversation chip, once
        // as a lead label chip) the next time the thread is opened.
        $this->assertSame(0, $conversation->leadLabels()->count());
    }

    /**
     * @return array{0: User, 1: FacebookConversation}
     */
    private function userWithConversation(): array
    {
        $company = Company::create([
            'name' => 'LNS',
            'subdomain' => 'lns-fbconv-'.uniqid(),
            'status' => 'active',
            'email' => 'admin-fbconv-'.uniqid().'@lns.test',
            'timezone' => 'UTC',
        ]);

        $role = Role::create([
            'name' => 'Staff',
            'slug' => 'staff-fbconv-'.uniqid(),
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        $permission = Permission::create([
            'name' => 'view_leads',
            'slug' => 'view_leads',
            'display_name' => 'Leads',
            'company_id' => $company->id,
        ]);
        $role->permissions()->attach($permission->id);

        $user = User::create([
            'name' => 'Alice',
            'email' => 'alice-fbconv-'.uniqid().'@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $conversation = FacebookConversation::create([
            'company_id' => $company->id,
            'channel' => 'messenger',
            'peer_id' => 'peer-'.uniqid(),
            'name' => 'Jane Customer',
            'last_message_at' => now(),
        ]);

        return [$user, $conversation];
    }
}
