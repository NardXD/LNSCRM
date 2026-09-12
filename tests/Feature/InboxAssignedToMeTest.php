<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\InboxConversation;
use App\Models\Lead;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SharedInbox;
use App\Models\SharedInboxMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InboxAssignedToMeTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_to_me_includes_emails_assigned_to_the_logged_in_user(): void
    {
        [$user, $other, $inbox] = $this->inboxWithTwoAgents();

        $mine = $this->makeConversation($inbox, [
            'subject' => 'Assigned on thread',
            'from_email' => 'thread@example.com',
            'assigned_to' => $user->id,
        ]);
        $theirs = $this->makeConversation($inbox, [
            'subject' => 'Assigned to teammate',
            'from_email' => 'teammate@example.com',
            'assigned_to' => $other->id,
        ]);
        $unassigned = $this->makeConversation($inbox, [
            'subject' => 'Nobody owns this',
            'from_email' => 'open@example.com',
        ]);

        $ids = $this->assignedToMeIds($user);

        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($theirs->id, $ids);
        $this->assertNotContains($unassigned->id, $ids);
    }

    public function test_assigned_to_me_includes_unassigned_mail_whose_lead_belongs_to_the_logged_in_user(): void
    {
        [$user, $other, $inbox] = $this->inboxWithTwoAgents();

        $myLead = Lead::query()->create([
            'company_id' => $user->company_id,
            'name' => 'Jane Doe',
            'status' => 'new',
            'assigned_to' => $user->id,
        ]);
        $otherLead = Lead::query()->create([
            'company_id' => $user->company_id,
            'name' => 'Other Lead',
            'status' => 'new',
            'assigned_to' => $other->id,
        ]);

        $linked = $this->makeConversation($inbox, [
            'subject' => 'Lead is mine, thread unassigned',
            'from_email' => 'jane@example.com',
            'lead_id' => $myLead->id,
        ]);
        $otherLeadLinked = $this->makeConversation($inbox, [
            'subject' => 'Lead belongs to teammate',
            'from_email' => 'other-lead@example.com',
            'lead_id' => $otherLead->id,
        ]);
        $assignedToOther = $this->makeConversation($inbox, [
            'subject' => 'Assigned to teammate even if lead is mine',
            'from_email' => 'jane2@example.com',
            'lead_id' => $myLead->id,
            'assigned_to' => $other->id,
        ]);
        $assignedToMe = $this->makeConversation($inbox, [
            'subject' => 'Assigned to logged in user',
            'from_email' => 'mine@example.com',
            'assigned_to' => $user->id,
        ]);

        $ids = $this->assignedToMeIds($user);

        $this->assertContains($assignedToMe->id, $ids);
        $this->assertContains($linked->id, $ids);
        $this->assertNotContains($otherLeadLinked->id, $ids);
        $this->assertNotContains($assignedToOther->id, $ids);
        $this->assertEqualsCanonicalizing(
            [$assignedToOther->id, $otherLeadLinked->id],
            $this->assignedToMeIds($other)
        );
    }

    public function test_assigned_to_me_includes_archived_inbox_mail_assigned_to_the_logged_in_user(): void
    {
        [$user, , $inbox] = $this->inboxWithTwoAgents();

        $archived = $this->makeConversation($inbox, [
            'subject' => 'Archived but still mine',
            'from_email' => 'archived@example.com',
            'assigned_to' => $user->id,
            'status' => 'archived',
        ]);
        $trashed = $this->makeConversation($inbox, [
            'subject' => 'Trashed assignment',
            'from_email' => 'trash@example.com',
            'assigned_to' => $user->id,
            'folder' => 'trash',
            'status' => 'trashed',
        ]);

        $ids = $this->assignedToMeIds($user);

        $this->assertContains($archived->id, $ids);
        $this->assertNotContains($trashed->id, $ids);
    }

    public function test_bootstrap_assigned_to_me_count_is_only_for_the_logged_in_user(): void
    {
        [$user, $other, $inbox] = $this->inboxWithTwoAgents();

        $this->makeConversation($inbox, [
            'subject' => 'Mine open',
            'from_email' => 'mine-open@example.com',
            'assigned_to' => $user->id,
        ]);
        $this->makeConversation($inbox, [
            'subject' => 'Mine archived',
            'from_email' => 'mine-archived@example.com',
            'assigned_to' => $user->id,
            'status' => 'archived',
        ]);
        $this->makeConversation($inbox, [
            'subject' => 'Theirs',
            'from_email' => 'theirs@example.com',
            'assigned_to' => $other->id,
        ]);
        $this->makeConversation($inbox, [
            'subject' => 'Mine trash',
            'from_email' => 'mine-trash@example.com',
            'assigned_to' => $user->id,
            'folder' => 'trash',
            'status' => 'trashed',
        ]);

        $this->actingAs($user)
            ->getJson('/api/inbox/bootstrap')
            ->assertOk()
            ->assertJsonPath('assigned_to_me_count', 2)
            ->assertJsonPath('inboxes.0.assigned_to_me_count', 2);

        $this->actingAs($other)
            ->getJson('/api/inbox/bootstrap')
            ->assertOk()
            ->assertJsonPath('assigned_to_me_count', 1)
            ->assertJsonPath('inboxes.0.assigned_to_me_count', 1);
    }

    public function test_bootstrap_assigned_to_me_count_includes_lead_owned_unassigned_mail(): void
    {
        [$user, $other, $inbox] = $this->inboxWithTwoAgents();

        $myLead = Lead::query()->create([
            'company_id' => $user->company_id,
            'name' => 'Jane Doe',
            'status' => 'new',
            'assigned_to' => $user->id,
        ]);
        $this->makeConversation($inbox, [
            'subject' => 'Thread assigned to me',
            'from_email' => 'mine@example.com',
            'assigned_to' => $user->id,
        ]);
        $this->makeConversation($inbox, [
            'subject' => 'Lead assigned to me',
            'from_email' => 'lead@example.com',
            'lead_id' => $myLead->id,
        ]);
        $this->makeConversation($inbox, [
            'subject' => 'Assigned to teammate',
            'from_email' => 'other@example.com',
            'assigned_to' => $other->id,
        ]);

        $this->actingAs($user)
            ->getJson('/api/inbox/bootstrap')
            ->assertOk()
            ->assertJsonPath('assigned_to_me_count', 2);

        $this->actingAs($other)
            ->getJson('/api/inbox/bootstrap')
            ->assertOk()
            ->assertJsonPath('assigned_to_me_count', 1);
    }

    /**
     * @return list<int>
     */
    private function assignedToMeIds(User $user): array
    {
        $payload = $this->actingAs($user)
            ->getJson('/api/inbox/conversations?view=assigned_to_me')
            ->assertOk()
            ->json();

        return collect($payload['conversations'] ?? [])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return array{0: User, 1: User, 2: SharedInbox}
     */
    private function inboxWithTwoAgents(): array
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-inbox-assigned',
            'status' => 'active',
            'email' => 'admin-inbox-assigned@lns.test',
        ]);

        $role = Role::query()->create([
            'name' => 'Staff',
            'slug' => 'staff-inbox-assigned',
            'company_id' => $company->id,
            'is_active' => true,
        ]);
        $permission = Permission::query()->create([
            'name' => 'view_inbox',
            'slug' => 'view_inbox',
            'display_name' => 'View Inbox',
            'company_id' => $company->id,
        ]);
        $role->permissions()->attach($permission->id);

        $user = User::query()->create([
            'name' => 'Login User',
            'email' => 'login-inbox-assigned@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);
        $other = User::query()->create([
            'name' => 'Other Agent',
            'email' => 'other-inbox-assigned@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $inbox = SharedInbox::query()->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'name' => 'Support',
            'email' => 'support@example.com',
            'type' => SharedInbox::TYPE_SHARED,
            'is_active' => true,
        ]);
        SharedInboxMember::query()->create([
            'shared_inbox_id' => $inbox->id,
            'user_id' => $user->id,
            'role' => 'member',
        ]);
        SharedInboxMember::query()->create([
            'shared_inbox_id' => $inbox->id,
            'user_id' => $other->id,
            'role' => 'member',
        ]);

        return [$user, $other, $inbox];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeConversation(SharedInbox $inbox, array $overrides): InboxConversation
    {
        return InboxConversation::query()->create(array_merge([
            'company_id' => $inbox->company_id,
            'shared_inbox_id' => $inbox->id,
            'folder' => 'inbox',
            'external_conversation_id' => 'conv-'.uniqid(),
            'status' => 'open',
            'from_name' => 'Customer',
            'is_read' => true,
            'message_count' => 1,
            'last_message_at' => now(),
        ], $overrides));
    }
}
