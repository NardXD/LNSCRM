<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\InboxConversation;
use App\Models\InboxUserSetting;
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

class InboxSidebarLabelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrap_includes_label_counts_and_null_sidebar_until_customized(): void
    {
        [$user, $other, $inbox] = $this->inboxWithTwoAgents();
        $inquiry = LeadLabel::query()->create([
            'company_id' => $user->company_id,
            'name' => 'Inquiry',
            'color' => '#4338ca',
        ]);
        $followUp = LeadLabel::query()->create([
            'company_id' => $user->company_id,
            'name' => '10th Day FU',
            'color' => '#dc2626',
        ]);

        $tagged = $this->makeConversation($inbox, ['subject' => 'Need a quote']);
        $tagged->leadLabels()->attach($inquiry->id);
        $this->makeConversation($inbox, ['subject' => 'Untagged']);

        $payload = $this->actingAs($user)
            ->getJson('/api/inbox/bootstrap')
            ->assertOk()
            ->assertJsonPath('sidebar_label_ids', null)
            ->json();

        $labels = collect($payload['lead_labels'] ?? []);
        $this->assertSame(1, $labels->firstWhere('id', $inquiry->id)['count'] ?? null);
        $this->assertSame(0, $labels->firstWhere('id', $followUp->id)['count'] ?? null);
        $this->assertTrue($labels->firstWhere('id', $inquiry->id)['shared'] ?? false);
    }

    public function test_label_filter_matches_conversation_and_lead_labels(): void
    {
        [$user, $other, $inbox] = $this->inboxWithTwoAgents();
        $inquiry = LeadLabel::query()->create([
            'company_id' => $user->company_id,
            'name' => 'Inquiry',
            'color' => '#4338ca',
        ]);

        $onThread = $this->makeConversation($inbox, ['subject' => 'Tagged on thread']);
        $onThread->leadLabels()->attach($inquiry->id);

        $lead = Lead::query()->create([
            'company_id' => $user->company_id,
            'name' => 'Jane Doe',
            'status' => 'new',
        ]);
        $lead->labels()->attach($inquiry->id);
        $onLead = $this->makeConversation($inbox, [
            'subject' => 'Tagged via lead',
            'lead_id' => $lead->id,
        ]);

        $otherLabel = LeadLabel::query()->create([
            'company_id' => $user->company_id,
            'name' => 'Banned',
            'color' => '#ef4444',
        ]);
        $otherThread = $this->makeConversation($inbox, ['subject' => 'Different label']);
        $otherThread->leadLabels()->attach($otherLabel->id);

        $ids = collect($this->actingAs($user)
            ->getJson('/api/inbox/conversations?view=open&label_id='.$inquiry->id)
            ->assertOk()
            ->json('conversations'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertEqualsCanonicalizing([$onThread->id, $onLead->id], $ids);
        $this->assertNotContains($otherThread->id, $ids);
    }

    public function test_label_folders_split_open_archived_and_snoozed(): void
    {
        [$user, $other, $inbox] = $this->inboxWithTwoAgents();
        $inquiry = LeadLabel::query()->create([
            'company_id' => $user->company_id,
            'name' => 'Inquiry',
            'color' => '#4338ca',
        ]);

        $open = $this->makeConversation($inbox, ['subject' => 'Open labeled']);
        $open->leadLabels()->attach($inquiry->id);
        $archived = $this->makeConversation($inbox, [
            'subject' => 'Archived labeled',
            'status' => 'archived',
        ]);
        $archived->leadLabels()->attach($inquiry->id);
        $snoozed = $this->makeConversation($inbox, [
            'subject' => 'Snoozed labeled',
            'status' => 'archived',
            'reopen_at' => now()->addDay(),
        ]);
        $snoozed->leadLabels()->attach($inquiry->id);

        $openIds = collect($this->actingAs($user)
            ->getJson('/api/inbox/conversations?view=open&label_id='.$inquiry->id)
            ->assertOk()
            ->assertJsonPath('meta.label_folders.open', 1)
            ->assertJsonPath('meta.label_folders.archived', 1)
            ->assertJsonPath('meta.label_folders.snoozed', 1)
            ->json('conversations'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $this->assertEqualsCanonicalizing([$open->id], $openIds);

        $archivedIds = collect($this->actingAs($user)
            ->getJson('/api/inbox/conversations?view=archived&label_id='.$inquiry->id)
            ->assertOk()
            ->json('conversations'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $this->assertEqualsCanonicalizing([$archived->id], $archivedIds);

        $snoozedIds = collect($this->actingAs($user)
            ->getJson('/api/inbox/conversations?view=snoozed&label_id='.$inquiry->id)
            ->assertOk()
            ->json('conversations'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $this->assertEqualsCanonicalizing([$snoozed->id], $snoozedIds);
    }

    public function test_sidebar_label_order_is_saved_per_user(): void
    {
        [$user, $other, $inbox] = $this->inboxWithTwoAgents();
        $inquiry = LeadLabel::query()->create([
            'company_id' => $user->company_id,
            'name' => 'Inquiry',
            'color' => '#4338ca',
        ]);
        $followUp = LeadLabel::query()->create([
            'company_id' => $user->company_id,
            'name' => '10th Day FU',
            'color' => '#dc2626',
        ]);

        $this->actingAs($user)
            ->putJson('/api/inbox/sidebar-labels', [
                'label_ids' => [$followUp->id, $inquiry->id],
            ])
            ->assertOk()
            ->assertJsonPath('sidebar_label_ids.0', $followUp->id)
            ->assertJsonPath('sidebar_label_ids.1', $inquiry->id);

        $this->actingAs($other)
            ->getJson('/api/inbox/bootstrap')
            ->assertOk()
            ->assertJsonPath('sidebar_label_ids', null);

        $this->actingAs($user)
            ->getJson('/api/inbox/bootstrap')
            ->assertOk()
            ->assertJsonPath('sidebar_label_ids.0', $followUp->id)
            ->assertJsonPath('sidebar_label_ids.1', $inquiry->id);

        $this->assertDatabaseHas('inbox_user_settings', [
            'user_id' => $user->id,
        ]);
        $this->assertSame(
            [$followUp->id, $inquiry->id],
            InboxUserSetting::query()->where('user_id', $user->id)->value('sidebar_label_ids')
        );
    }

    public function test_unknown_label_filter_is_not_found(): void
    {
        [$user] = $this->inboxWithTwoAgents();

        $this->actingAs($user)
            ->getJson('/api/inbox/conversations?view=open&label_id=99999')
            ->assertNotFound();
    }

    /**
     * @return array{0: User, 1: User, 2: SharedInbox}
     */
    private function inboxWithTwoAgents(): array
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-inbox-sidebar-labels',
            'status' => 'active',
            'email' => 'admin-inbox-sidebar-labels@lns.test',
        ]);

        $role = Role::query()->create([
            'name' => 'Staff',
            'slug' => 'staff-inbox-sidebar-labels',
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
            'email' => 'login-inbox-sidebar-labels@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);
        $other = User::query()->create([
            'name' => 'Other Agent',
            'email' => 'other-inbox-sidebar-labels@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $inbox = SharedInbox::query()->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'name' => 'Support',
            'email' => 'support-sidebar-labels@example.com',
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
            'external_conversation_id' => 'conv-sidebar-'.uniqid(),
            'status' => 'open',
            'subject' => 'Thread',
            'from_name' => 'Customer',
            'from_email' => 'customer@example.com',
            'is_read' => true,
            'message_count' => 1,
            'last_message_at' => now(),
        ], $overrides));
    }
}
