<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\InboxConversation;
use App\Models\Lead;
use App\Models\LeadLabel;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SharedInbox;
use App\Models\SharedInboxMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InboxConversationLabelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_add_an_existing_label_before_saving_as_a_lead(): void
    {
        [$user, $conversation] = $this->userWithConversation();
        $label = LeadLabel::query()->create([
            'company_id' => $conversation->company_id,
            'name' => 'Inquiry',
            'color' => '#4338ca',
        ]);

        $this->actingAs($user)
            ->postJson('/api/inbox/conversations/'.$conversation->id.'/lead-labels', [
                'label_id' => $label->id,
            ])
            ->assertOk()
            ->assertJsonPath('conversation.lead_labels.0.id', $label->id)
            ->assertJsonPath('conversation.lead_labels.0.name', 'Inquiry');

        $this->assertTrue($conversation->fresh()->leadLabels->contains('id', $label->id));
        $this->assertDatabaseHas('inbox_conversation_activities', [
            'inbox_conversation_id' => $conversation->id,
            'action' => 'label_added',
        ]);
    }

    public function test_can_create_a_new_label_on_a_thread_that_is_not_a_lead(): void
    {
        [$user, $conversation] = $this->userWithConversation();

        $this->actingAs($user)
            ->postJson('/api/inbox/conversations/'.$conversation->id.'/lead-labels', [
                'name' => 'Hot lead',
            ])
            ->assertOk()
            ->assertJsonPath('conversation.lead_labels.0.name', 'Hot lead');

        $label = LeadLabel::query()
            ->where('company_id', $conversation->company_id)
            ->where('name', 'Hot lead')
            ->first();
        $this->assertNotNull($label);
        $this->assertTrue($conversation->fresh()->leadLabels->contains('id', $label->id));
    }

    public function test_can_remove_a_conversation_label_before_it_is_a_lead(): void
    {
        [$user, $conversation] = $this->userWithConversation();
        $label = LeadLabel::query()->create([
            'company_id' => $conversation->company_id,
            'name' => 'Inquiry',
            'color' => '#4338ca',
        ]);
        $conversation->leadLabels()->attach($label->id);

        $this->actingAs($user)
            ->deleteJson('/api/inbox/conversations/'.$conversation->id.'/lead-labels/'.$label->id)
            ->assertOk();

        $this->assertSame(0, $conversation->fresh()->leadLabels()->count());
    }

    public function test_saving_a_labeled_inbox_thread_as_a_lead_carries_the_label_over(): void
    {
        [$user, $conversation] = $this->userWithConversation();
        $label = LeadLabel::query()->create([
            'company_id' => $conversation->company_id,
            'name' => 'Inquiry',
            'color' => '#4338ca',
        ]);
        $conversation->leadLabels()->attach($label->id);

        $response = $this->actingAs($user)->postJson('/api/leads', [
            'name' => 'Jane Customer',
            'emails' => [['value' => 'jane@example.com', 'label' => null]],
            'source' => 'inbox',
            'inbox_conversation_ids' => [$conversation->id],
        ]);

        $response->assertCreated();

        $lead = Lead::findOrFail($response->json('data.id'));
        $this->assertTrue($lead->labels()->where('lead_labels.id', $label->id)->exists());
        $this->assertSame($lead->id, (int) $conversation->fresh()->lead_id);
        $this->assertSame(0, $conversation->fresh()->leadLabels()->count());
    }

    public function test_saving_a_labeled_inbox_thread_that_matches_an_existing_lead_still_carries_the_label_over(): void
    {
        [$user, $conversation] = $this->userWithConversation();
        $label = LeadLabel::query()->create([
            'company_id' => $conversation->company_id,
            'name' => 'Inquiry',
            'color' => '#4338ca',
        ]);
        $conversation->leadLabels()->attach($label->id);

        $existingLead = Lead::query()->create([
            'company_id' => $conversation->company_id,
            'name' => 'Jane Customer',
            'status' => 'new',
        ]);
        $existingLead->syncIdentities([
            ['type' => 'email', 'value' => 'jane@example.com', 'is_primary' => true],
        ]);

        $response = $this->actingAs($user)->postJson('/api/leads', [
            'name' => 'Jane Customer',
            'emails' => [['value' => 'jane@example.com', 'label' => null]],
            'source' => 'inbox',
            'inbox_conversation_ids' => [$conversation->id],
        ]);

        $response->assertStatus(422)->assertJsonPath('existing_lead_id', $existingLead->id);

        $this->assertTrue($existingLead->labels()->where('lead_labels.id', $label->id)->exists());
        $this->assertSame(0, $conversation->fresh()->leadLabels()->count());
    }

    public function test_attaching_a_labeled_thread_to_an_existing_lead_graduates_the_labels(): void
    {
        [$user, $conversation] = $this->userWithConversation();
        $label = LeadLabel::query()->create([
            'company_id' => $conversation->company_id,
            'name' => 'Inquiry',
            'color' => '#4338ca',
        ]);
        $conversation->leadLabels()->attach($label->id);

        $lead = Lead::query()->create([
            'company_id' => $conversation->company_id,
            'name' => 'Existing Lead',
            'status' => 'new',
        ]);

        $this->actingAs($user)
            ->postJson('/api/inbox/conversations/'.$conversation->id.'/lead', [
                'lead_id' => $lead->id,
            ])
            ->assertOk()
            ->assertJsonPath('conversation.lead_id', $lead->id);

        $this->assertTrue($lead->fresh()->labels()->where('lead_labels.id', $label->id)->exists());
        $this->assertSame(0, $conversation->fresh()->leadLabels()->count());
    }

    /**
     * @return array{0: User, 1: InboxConversation}
     */
    private function userWithConversation(): array
    {
        $suffix = uniqid();
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-inbox-conv-labels-'.$suffix,
            'status' => 'active',
            'email' => 'admin-inbox-conv-labels-'.$suffix.'@lns.test',
        ]);

        $role = Role::query()->create([
            'name' => 'Staff',
            'slug' => 'staff-inbox-conv-labels-'.$suffix,
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        foreach (['view_inbox', 'view_leads'] as $slug) {
            $permission = Permission::query()->create([
                'name' => $slug,
                'slug' => $slug,
                'display_name' => $slug,
                'company_id' => $company->id,
            ]);
            $role->permissions()->attach($permission->id);
        }

        $user = User::query()->create([
            'name' => 'Alice',
            'email' => 'alice-inbox-conv-labels-'.$suffix.'@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $inbox = SharedInbox::query()->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'name' => 'Sales',
            'email' => 'sales-inbox-conv-labels-'.$suffix.'@example.com',
            'type' => SharedInbox::TYPE_SHARED,
            'is_active' => true,
        ]);
        SharedInboxMember::query()->create([
            'shared_inbox_id' => $inbox->id,
            'user_id' => $user->id,
            'role' => 'member',
        ]);

        $conversation = InboxConversation::query()->create([
            'company_id' => $inbox->company_id,
            'shared_inbox_id' => $inbox->id,
            'folder' => 'inbox',
            'external_conversation_id' => 'conv-labels-'.$suffix,
            'status' => 'open',
            'subject' => 'Storage inquiry',
            'from_name' => 'Jane Customer',
            'from_email' => 'jane@example.com',
            'is_read' => true,
            'message_count' => 1,
            'last_message_at' => now(),
        ]);

        return [$user, $conversation];
    }
}
