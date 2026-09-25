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

    public function test_shared_inbox_archive_and_snooze_are_visible_to_every_member(): void
    {
        [$user, $other, $inbox] = $this->inboxWithTwoAgents();

        $myOpen = $this->makeConversation($inbox, [
            'subject' => 'Mine open',
            'from_email' => 'mine-open@example.com',
            'assigned_to' => $user->id,
        ]);
        $myArchived = $this->makeConversation($inbox, [
            'subject' => 'Archived but still mine',
            'from_email' => 'archived@example.com',
            'assigned_to' => $user->id,
            'status' => 'archived',
        ]);
        $theirArchived = $this->makeConversation($inbox, [
            'subject' => 'Archived for teammate',
            'from_email' => 'their-archived@example.com',
            'assigned_to' => $other->id,
            'status' => 'archived',
        ]);
        $unassignedArchived = $this->makeConversation($inbox, [
            'subject' => 'Archived with no assignee',
            'from_email' => 'unassigned-archived@example.com',
            'status' => 'archived',
        ]);
        $mySnoozed = $this->makeConversation($inbox, [
            'subject' => 'Snoozed mine',
            'from_email' => 'snoozed@example.com',
            'assigned_to' => $user->id,
            'status' => 'archived',
            'reopen_at' => now()->addDay(),
        ]);
        $theirSnoozed = $this->makeConversation($inbox, [
            'subject' => 'Snoozed teammate',
            'from_email' => 'their-snoozed@example.com',
            'assigned_to' => $other->id,
            'status' => 'archived',
            'reopen_at' => now()->addDay(),
        ]);
        $trashed = $this->makeConversation($inbox, [
            'subject' => 'Trashed assignment',
            'from_email' => 'trash@example.com',
            'assigned_to' => $user->id,
            'folder' => 'trash',
            'status' => 'trashed',
        ]);

        $assignedIds = $this->conversationIds($user, 'assigned_to_me');
        $archivedIds = $this->conversationIds($user, 'archived');
        $otherArchivedIds = $this->conversationIds($other, 'archived');
        $snoozedIds = $this->conversationIds($user, 'snoozed');
        $otherSnoozedIds = $this->conversationIds($other, 'snoozed');

        $this->assertContains($myOpen->id, $assignedIds);
        $this->assertNotContains($myArchived->id, $assignedIds);
        $this->assertNotContains($mySnoozed->id, $assignedIds);

        $sharedArchived = [$myArchived->id, $theirArchived->id, $unassignedArchived->id];
        $this->assertEqualsCanonicalizing($sharedArchived, $archivedIds);
        $this->assertEqualsCanonicalizing($sharedArchived, $otherArchivedIds);
        $this->assertNotContains($mySnoozed->id, $archivedIds);
        $this->assertNotContains($trashed->id, $archivedIds);

        $sharedSnoozed = [$mySnoozed->id, $theirSnoozed->id];
        $this->assertEqualsCanonicalizing($sharedSnoozed, $snoozedIds);
        $this->assertEqualsCanonicalizing($sharedSnoozed, $otherSnoozedIds);
        $this->assertNotContains($myArchived->id, $snoozedIds);
    }

    public function test_personal_inbox_archive_stays_limited_to_the_logged_in_users_mail(): void
    {
        [$user, $other] = $this->inboxWithTwoAgents();

        $inbox = SharedInbox::query()->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'name' => 'Personal',
            'email' => 'login-inbox-assigned@lns.test',
            'type' => SharedInbox::TYPE_PERSONAL,
            'is_active' => true,
        ]);

        $mine = $this->makeConversation($inbox, [
            'subject' => 'My personal archive',
            'from_email' => 'personal-mine@example.com',
            'assigned_to' => $user->id,
            'status' => 'archived',
        ]);
        $theirs = $this->makeConversation($inbox, [
            'subject' => 'Teammate personal archive',
            'from_email' => 'personal-theirs@example.com',
            'assigned_to' => $other->id,
            'status' => 'archived',
        ]);

        $archivedIds = $this->conversationIds($user, 'archived');

        $this->assertContains($mine->id, $archivedIds);
        $this->assertNotContains($theirs->id, $archivedIds);
    }

    public function test_assigned_to_me_folders_split_open_snoozed_and_archived(): void
    {
        [$user, $other, $inbox] = $this->inboxWithTwoAgents();

        $open = $this->makeConversation($inbox, [
            'subject' => 'Open mine',
            'from_email' => 'open-mine@example.com',
            'assigned_to' => $user->id,
        ]);
        $archived = $this->makeConversation($inbox, [
            'subject' => 'Archived mine',
            'from_email' => 'archived-mine@example.com',
            'assigned_to' => $user->id,
            'status' => 'archived',
        ]);
        $snoozed = $this->makeConversation($inbox, [
            'subject' => 'Snoozed mine',
            'from_email' => 'snoozed-mine@example.com',
            'assigned_to' => $user->id,
            'status' => 'archived',
            'reopen_at' => now()->addDay(),
        ]);
        $theirArchived = $this->makeConversation($inbox, [
            'subject' => 'Archived teammate',
            'from_email' => 'archived-other@example.com',
            'assigned_to' => $other->id,
            'status' => 'archived',
        ]);

        $this->assertEqualsCanonicalizing([$open->id], $this->conversationIds($user, 'assigned_to_me'));
        $this->assertEqualsCanonicalizing(
            [$archived->id],
            $this->conversationIds($user, 'assigned_to_me', 'archived')
        );
        $this->assertEqualsCanonicalizing(
            [$snoozed->id],
            $this->conversationIds($user, 'assigned_to_me', 'snoozed')
        );
        $this->assertNotContains(
            $theirArchived->id,
            $this->conversationIds($user, 'assigned_to_me', 'archived')
        );

        $this->actingAs($user)
            ->getJson('/api/inbox/bootstrap')
            ->assertOk()
            ->assertJsonPath('assigned_to_me_count', 1)
            ->assertJsonPath('assigned_archived_count', 1)
            ->assertJsonPath('assigned_snoozed_count', 1);
    }

    public function test_reopened_threads_appear_only_in_the_matching_open_folder(): void
    {
        [$user, $other, $inbox] = $this->inboxWithTwoAgents();

        $plainOpen = $this->makeConversation($inbox, [
            'subject' => 'Never held',
            'from_email' => 'plain@example.com',
        ]);
        $stillSnoozed = $this->makeConversation($inbox, [
            'subject' => 'Still snoozed',
            'from_email' => 'still-snoozed@example.com',
            'status' => 'archived',
            'reopen_at' => now()->addDay(),
        ]);
        $stillArchived = $this->makeConversation($inbox, [
            'subject' => 'Still archived',
            'from_email' => 'still-archived@example.com',
            'status' => 'archived',
        ]);
        $dueSnooze = $this->makeConversation($inbox, [
            'subject' => 'Snooze finished',
            'from_email' => 'due-snooze@example.com',
            'status' => 'archived',
            'reopen_at' => now()->subMinute(),
        ]);
        $archived = $this->makeConversation($inbox, [
            'subject' => 'Was archived',
            'from_email' => 'was-archived@example.com',
            'status' => 'archived',
        ]);

        app(\App\Services\InboxReopenService::class)->processDue();
        $dueSnooze->refresh();
        $this->assertSame('open', $dueSnooze->status);
        $this->assertSame('snoozed', $dueSnooze->reopened_from);
        $this->assertNull($dueSnooze->reopen_at);

        $this->actingAs($user)
            ->patchJson('/api/inbox/conversations/'.$archived->id.'/status', ['status' => 'open'])
            ->assertOk();
        $archived->refresh();
        $this->assertSame('archived', $archived->reopened_from);

        $this->assertEqualsCanonicalizing(
            [$dueSnooze->id],
            $this->conversationIds($user, 'snoozed', 'open')
        );
        $this->assertEqualsCanonicalizing(
            [$dueSnooze->id],
            $this->conversationIds($other, 'snoozed', 'open')
        );
        $this->assertEqualsCanonicalizing(
            [$archived->id],
            $this->conversationIds($user, 'archived', 'open')
        );
        $this->assertNotContains($plainOpen->id, $this->conversationIds($user, 'snoozed', 'open'));
        $this->assertNotContains($plainOpen->id, $this->conversationIds($user, 'archived', 'open'));
        $this->assertNotContains($stillSnoozed->id, $this->conversationIds($user, 'snoozed', 'open'));
        $this->assertNotContains($stillArchived->id, $this->conversationIds($user, 'archived', 'open'));
        $this->assertEqualsCanonicalizing(
            [$stillSnoozed->id],
            $this->conversationIds($user, 'snoozed')
        );
        $this->assertEqualsCanonicalizing(
            [$stillArchived->id],
            $this->conversationIds($user, 'archived')
        );

        $this->actingAs($user)
            ->getJson('/api/inbox/bootstrap')
            ->assertOk()
            ->assertJsonPath('reopened_snoozed_count', 1)
            ->assertJsonPath('reopened_archived_count', 1);
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
            ->assertJsonPath('assigned_to_me_count', 1)
            ->assertJsonPath('archived_count', 1)
            ->assertJsonPath('snoozed_count', 0)
            ->assertJsonPath('inboxes.0.assigned_to_me_count', 1)
            ->assertJsonPath('inboxes.0.archived_count', 1);

        $this->actingAs($other)
            ->getJson('/api/inbox/bootstrap')
            ->assertOk()
            ->assertJsonPath('assigned_to_me_count', 1)
            ->assertJsonPath('archived_count', 1)
            ->assertJsonPath('inboxes.0.assigned_to_me_count', 1)
            ->assertJsonPath('inboxes.0.archived_count', 1);
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

    public function test_global_open_view_excludes_personal_inbox_mail(): void
    {
        [$user, $other, $shared] = $this->inboxWithTwoAgents();

        $personal = SharedInbox::query()->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'name' => 'Personal',
            'email' => $user->email,
            'type' => SharedInbox::TYPE_PERSONAL,
            'is_active' => true,
        ]);

        $sharedThread = $this->makeConversation($shared, [
            'subject' => 'Shared open',
            'from_email' => 'shared-customer@example.com',
        ]);
        $personalThread = $this->makeConversation($personal, [
            'subject' => 'Personal open',
            'from_email' => 'personal-customer@example.com',
        ]);

        $globalIds = collect($this->actingAs($user)
            ->getJson('/api/inbox/conversations?view=open')
            ->assertOk()
            ->json('conversations'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertContains($sharedThread->id, $globalIds);
        $this->assertNotContains($personalThread->id, $globalIds);

        $personalIds = collect($this->actingAs($user)
            ->getJson('/api/inbox/conversations?view=open&inbox_id='.$personal->id)
            ->assertOk()
            ->json('conversations'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertContains($personalThread->id, $personalIds);
        $this->assertNotContains($sharedThread->id, $personalIds);
    }

    public function test_conversation_list_includes_attached_lead_and_skips_unattached_fuzzy_match(): void
    {
        [$user, $other, $inbox] = $this->inboxWithTwoAgents();

        $lead = Lead::query()->create([
            'company_id' => $user->company_id,
            'name' => 'Jane Doe',
            'status' => 'new',
            'assigned_to' => $user->id,
        ]);
        $attached = $this->makeConversation($inbox, [
            'subject' => 'Attached lead',
            'from_email' => 'attached@example.com',
            'lead_id' => $lead->id,
        ]);
        $unattached = $this->makeConversation($inbox, [
            'subject' => 'Same sender as a lead, but not linked',
            'from_email' => 'jane@example.com',
            'from_name' => 'Jane Doe',
        ]);

        $rows = collect($this->actingAs($user)
            ->getJson('/api/inbox/conversations?view=open')
            ->assertOk()
            ->assertJsonPath('meta.has_more', false)
            ->json('conversations'));

        $attachedRow = $rows->firstWhere('id', $attached->id);
        $unattachedRow = $rows->firstWhere('id', $unattached->id);

        $this->assertNotNull($attachedRow);
        $this->assertSame($lead->id, $attachedRow['lead']['id'] ?? null);
        $this->assertSame('Jane Doe', $attachedRow['lead']['name'] ?? null);
        $this->assertNull($unattachedRow['lead'] ?? null);
    }

    public function test_conversation_list_uses_has_more_instead_of_counting_every_thread(): void
    {
        [$user, $other, $inbox] = $this->inboxWithTwoAgents();

        for ($i = 0; $i < 41; $i++) {
            $this->makeConversation($inbox, [
                'subject' => 'Thread '.$i,
                'from_email' => 'customer'.$i.'@example.com',
                'last_message_at' => now()->subMinutes($i),
            ]);
        }

        $this->actingAs($user)
            ->getJson('/api/inbox/conversations?view=open&page=1')
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.has_more', true)
            ->assertJsonCount(40, 'conversations');

        $this->actingAs($user)
            ->getJson('/api/inbox/conversations?view=open&page=2')
            ->assertOk()
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.has_more', false)
            ->assertJsonCount(1, 'conversations');
    }

    /**
     * @return list<int>
     */
    private function assignedToMeIds(User $user): array
    {
        return $this->conversationIds($user, 'assigned_to_me');
    }

    /**
     * @return list<int>
     */
    private function conversationIds(User $user, string $view, ?string $bucket = null): array
    {
        $url = '/api/inbox/conversations?view='.$view;
        if ($bucket) {
            $url .= '&bucket='.$bucket;
        }

        $payload = $this->actingAs($user)
            ->getJson($url)
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
