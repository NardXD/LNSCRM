<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\InboxConversation;
use App\Models\Lead;
use App\Models\LeadIdentity;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SharedInbox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LeadContactHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_history_includes_shared_inbox_mail_not_personal(): void
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-history',
            'status' => 'active',
            'email' => 'admin-history@lns.test',
        ]);
        $role = Role::query()->create([
            'name' => 'Manager',
            'slug' => 'manager-history',
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
            'email' => 'manager-history@lns.test',
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
        $lead->addIdentity(LeadIdentity::TYPE_EMAIL, 'jane@example.com');

        $personal = SharedInbox::query()->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'name' => 'My Outlook',
            'email' => 'personal@example.com',
            'type' => SharedInbox::TYPE_PERSONAL,
            'is_active' => true,
        ]);
        $shared = SharedInbox::query()->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'name' => 'Support',
            'email' => 'support@example.com',
            'type' => SharedInbox::TYPE_SHARED,
            'is_active' => true,
        ]);

        $personalThread = InboxConversation::query()->create([
            'company_id' => $company->id,
            'shared_inbox_id' => $personal->id,
            'lead_id' => $lead->id,
            'external_conversation_id' => 'personal-thread',
            'subject' => 'Personal mailbox email',
            'snippet' => 'From my Outlook',
            'from_email' => 'jane@example.com',
            'status' => 'open',
            'last_message_at' => now(),
        ]);
        $sharedThread = InboxConversation::query()->create([
            'company_id' => $company->id,
            'shared_inbox_id' => $shared->id,
            'lead_id' => $lead->id,
            'external_conversation_id' => 'shared-thread',
            'subject' => 'Shared mailbox email',
            'snippet' => 'From support inbox',
            'from_email' => 'jane@example.com',
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        $payload = $this->actingAs($user)
            ->getJson('/api/leads/'.$lead->id.'/history')
            ->assertOk()
            ->json();

        $threadIds = collect($payload['threads'])->pluck('conversation_id')->all();
        $eventIds = collect($payload['events'])->pluck('conversation_id')->all();

        $this->assertContains($sharedThread->id, $threadIds);
        $this->assertNotContains($personalThread->id, $threadIds);
        $this->assertContains($sharedThread->id, $eventIds);
        $this->assertNotContains($personalThread->id, $eventIds);
    }
}
