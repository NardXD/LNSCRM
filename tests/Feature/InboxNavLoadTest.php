<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\InboxConversation;
use App\Models\InboxSignature;
use App\Models\InboxTemplate;
use App\Models\LeadLabel;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SharedInbox;
use App\Models\SharedInboxMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InboxNavLoadTest extends TestCase
{
    use RefreshDatabase;

    public function test_lite_bootstrap_skips_counts_and_composer_bodies(): void
    {
        [$user, $inbox] = $this->agentWithInbox();

        LeadLabel::query()->create([
            'company_id' => $user->company_id,
            'name' => 'Inquiry',
            'color' => '#4338ca',
        ]);

        $this->makeConversation($inbox, [
            'subject' => 'Open thread',
            'from_email' => 'customer@example.com',
        ]);

        InboxTemplate::query()->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'name' => 'Quote',
            'body_html' => '<p>Hello</p>',
            'body_text' => 'Hello',
        ]);
        InboxSignature::query()->create([
            'user_id' => $user->id,
            'company_id' => $user->company_id,
            'name' => 'Mine',
            'body_html' => '<p>Alice</p>',
            'body_text' => 'Alice',
            'is_default' => true,
        ]);

        $this->actingAs($user)
            ->getJson('/api/inbox/bootstrap?lite=1')
            ->assertOk()
            ->assertJsonPath('assigned_to_me_count', 0)
            ->assertJsonPath('inboxes.0.open_count', null)
            ->assertJsonPath('templates', [])
            ->assertJsonPath('signatures', [])
            ->assertJsonPath('lead_labels.0.count', 0);
    }

    public function test_nav_counts_returns_folder_and_label_badges(): void
    {
        [$user, $inbox] = $this->agentWithInbox();
        $inquiry = LeadLabel::query()->create([
            'company_id' => $user->company_id,
            'name' => 'Inquiry',
            'color' => '#4338ca',
        ]);

        $open = $this->makeConversation($inbox, [
            'subject' => 'Need a quote',
            'from_email' => 'customer@example.com',
            'assigned_to' => $user->id,
        ]);
        $open->leadLabels()->attach($inquiry->id);
        $this->makeConversation($inbox, [
            'subject' => 'Archived',
            'from_email' => 'done@example.com',
            'status' => 'archived',
            'assigned_to' => $user->id,
        ]);

        $payload = $this->actingAs($user)
            ->getJson('/api/inbox/nav-counts')
            ->assertOk()
            ->assertJsonPath('assigned_to_me_count', 1)
            ->assertJsonPath('assigned_archived_count', 1)
            ->assertJsonPath('by_inbox.'.$inbox->id.'.open_count', 1)
            ->assertJsonPath('lead_label_counts.'.$inquiry->id, 1)
            ->json();

        $this->assertSame(1, (int) ($payload['by_inbox'][$inbox->id]['open_count'] ?? 0));
    }

    public function test_composer_tools_returns_templates_and_signatures(): void
    {
        [$user] = $this->agentWithInbox();

        $template = InboxTemplate::query()->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'name' => 'Quote',
            'body_html' => '<p>Hello</p>',
            'body_text' => 'Hello',
        ]);
        $signature = InboxSignature::query()->create([
            'user_id' => $user->id,
            'company_id' => $user->company_id,
            'name' => 'Mine',
            'body_html' => '<p>Alice</p>',
            'body_text' => 'Alice',
            'is_default' => true,
        ]);

        $this->actingAs($user)
            ->getJson('/api/inbox/composer-tools')
            ->assertOk()
            ->assertJsonPath('templates.0.id', $template->id)
            ->assertJsonPath('templates.0.body_html', '<p>Hello</p>')
            ->assertJsonPath('signatures.0.id', $signature->id)
            ->assertJsonPath('default_signature_id', $signature->id);
    }

    /**
     * @return array{0: User, 1: SharedInbox}
     */
    private function agentWithInbox(): array
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-inbox-nav-load',
            'status' => 'active',
            'email' => 'admin-inbox-nav-load@lns.test',
        ]);

        $role = Role::query()->create([
            'name' => 'Staff',
            'slug' => 'staff-inbox-nav-load',
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
            'name' => 'Nav User',
            'email' => 'nav-inbox-load@lns.test',
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

        return [$user, $inbox];
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
