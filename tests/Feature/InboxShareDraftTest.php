<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\InboxConversation;
use App\Models\InboxMessage;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SharedInbox;
use App\Models\SharedInboxMember;
use App\Models\User;
use App\Notifications\InboxThreadUpdateNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InboxShareDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_compose_share_draft_assigns_and_notifies_selected_teammates(): void
    {
        Notification::fake();
        Storage::fake('local');

        [$user, $other, $outsider, $inbox, $conversation] = $this->sharedInboxFixture();

        $this->actingAs($user)
            ->postJson('/api/inbox/compose/share-draft', [
                'inbox_id' => $inbox->id,
                'to' => 'customer@example.com',
                'cc' => 'cc@example.com',
                'subject' => 'Quote follow-up',
                'body' => '<p>Please review this draft</p>',
                'share_with_user_ids' => [$other->id, $outsider->id],
                'attachments' => [[
                    'name' => 'notes.txt',
                    'contentType' => 'text/plain',
                    'contentBytes' => base64_encode('hello'),
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('shared', true)
            ->assertJsonPath('conversation.folder', 'drafts')
            ->assertJsonPath('conversation.assigned_to', $other->id)
            ->assertJsonPath('message.is_draft', true)
            ->assertJsonPath('message.subject', 'Quote follow-up');

        $draftConversation = InboxConversation::query()->where('folder', 'drafts')->first();
        $this->assertNotNull($draftConversation);
        $this->assertSame($other->id, $draftConversation->assigned_to);
        $this->assertStringStartsWith('local-draft-', (string) $draftConversation->external_conversation_id);

        $draft = InboxMessage::query()
            ->where('inbox_conversation_id', $draftConversation->id)
            ->where('is_draft', true)
            ->first();
        $this->assertNotNull($draft);
        $this->assertSame('customer@example.com', $draft->to_emails);
        $this->assertNotEmpty($draft->attachments);
        $this->assertTrue(Storage::disk('local')->exists($draft->attachments[0]['path']));

        $this->assertDatabaseHas('inbox_conversation_activities', [
            'inbox_conversation_id' => $draftConversation->id,
            'action' => 'draft_shared',
        ]);

        Notification::assertSentTo($other, InboxThreadUpdateNotification::class);
        Notification::assertNotSentTo($user, InboxThreadUpdateNotification::class);
        Notification::assertNotSentTo($outsider, InboxThreadUpdateNotification::class);
    }

    public function test_compose_share_draft_updates_existing_local_draft(): void
    {
        [$user, $other, $outsider, $inbox] = $this->sharedInboxFixture();

        $existing = InboxConversation::query()->create([
            'company_id' => $inbox->company_id,
            'shared_inbox_id' => $inbox->id,
            'folder' => 'drafts',
            'external_conversation_id' => 'local-draft-existing',
            'status' => 'drafts',
            'subject' => 'Old subject',
            'from_name' => $user->name,
            'from_email' => $inbox->email,
            'assigned_to' => $user->id,
            'is_read' => true,
            'message_count' => 1,
            'last_message_at' => now(),
        ]);
        InboxMessage::query()->create([
            'inbox_conversation_id' => $existing->id,
            'external_message_id' => 'local-draft-existing-msg',
            'direction' => 'outbound',
            'is_draft' => true,
            'from_name' => $user->name,
            'from_email' => $inbox->email,
            'to_emails' => 'old@example.com',
            'subject' => 'Old subject',
            'body_html' => '<p>Old</p>',
            'body_text' => 'Old',
            'is_read' => true,
            'sent_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson('/api/inbox/compose/share-draft', [
                'inbox_id' => $inbox->id,
                'draft_conversation_id' => $existing->id,
                'to' => 'new@example.com',
                'subject' => 'Updated subject',
                'body' => '<p>Updated draft</p>',
                'share_with_user_ids' => [$other->id],
            ])
            ->assertCreated()
            ->assertJsonPath('conversation.id', $existing->id)
            ->assertJsonPath('conversation.assigned_to', $other->id)
            ->assertJsonPath('message.body_text', 'Updated draft');

        $this->assertSame(1, InboxConversation::query()->where('folder', 'drafts')->count());
        $this->assertSame(1, InboxMessage::query()->where('is_draft', true)->count());
        $this->assertDatabaseHas('inbox_conversations', [
            'id' => $existing->id,
            'subject' => 'Updated subject',
            'assigned_to' => $other->id,
        ]);
    }

    public function test_reply_share_draft_stays_on_the_live_thread(): void
    {
        Notification::fake();

        [$user, $other, $outsider, $inbox, $conversation] = $this->sharedInboxFixture();
        InboxMessage::query()->create([
            'inbox_conversation_id' => $conversation->id,
            'external_message_id' => 'inbound-1',
            'direction' => 'inbound',
            'from_name' => 'Customer',
            'from_email' => 'customer@example.com',
            'to_emails' => $inbox->email,
            'subject' => $conversation->subject,
            'body_html' => '<p>Need a quote</p>',
            'body_text' => 'Need a quote',
            'is_read' => true,
            'sent_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson('/api/inbox/conversations/'.$conversation->id.'/share-draft', [
                'inbox_id' => $inbox->id,
                'to' => 'customer@example.com',
                'body' => '<p>Draft reply for review</p>',
                'share_with_user_ids' => [$other->id],
            ])
            ->assertOk()
            ->assertJsonPath('shared', true)
            ->assertJsonPath('conversation.id', $conversation->id)
            ->assertJsonPath('conversation.folder', 'inbox')
            ->assertJsonPath('conversation.assigned_to', $other->id)
            ->assertJsonPath('message.is_draft', true);

        $this->assertDatabaseHas('inbox_messages', [
            'inbox_conversation_id' => $conversation->id,
            'is_draft' => true,
            'to_emails' => 'customer@example.com',
        ]);
        $this->assertDatabaseHas('inbox_conversation_activities', [
            'inbox_conversation_id' => $conversation->id,
            'action' => 'draft_shared',
        ]);

        Notification::assertSentTo($other, InboxThreadUpdateNotification::class);
        Notification::assertNotSentTo($user, InboxThreadUpdateNotification::class);
        Notification::assertNotSentTo($outsider, InboxThreadUpdateNotification::class);
    }

    public function test_share_draft_requires_a_teammate_who_can_access_the_inbox(): void
    {
        [$user, $other, $outsider, $inbox] = $this->sharedInboxFixture();

        $this->actingAs($user)
            ->postJson('/api/inbox/compose/share-draft', [
                'inbox_id' => $inbox->id,
                'to' => 'customer@example.com',
                'subject' => 'Quote',
                'body' => '<p>Hello</p>',
                'share_with_user_ids' => [$outsider->id],
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Select at least one teammate to share this draft with.');

        $this->actingAs($user)
            ->postJson('/api/inbox/compose/share-draft', [
                'inbox_id' => $inbox->id,
                'to' => 'customer@example.com',
                'subject' => 'Quote',
                'body' => '<p>Hello</p>',
                'share_with_user_ids' => [$user->id],
            ])
            ->assertStatus(422);
    }

    /**
     * @return array{0: User, 1: User, 2: User, 3: SharedInbox, 4: InboxConversation}
     */
    private function sharedInboxFixture(): array
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-inbox-share-draft',
            'status' => 'active',
            'email' => 'admin-inbox-share-draft@lns.test',
        ]);

        $role = Role::query()->create([
            'name' => 'Staff',
            'slug' => 'staff-inbox-share-draft',
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
            'email' => 'login-inbox-share-draft@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);
        $other = User::query()->create([
            'name' => 'Other Agent',
            'email' => 'other-inbox-share-draft@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);
        $outsider = User::query()->create([
            'name' => 'Outside Agent',
            'email' => 'outside-inbox-share-draft@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $inbox = SharedInbox::query()->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'name' => 'Support',
            'email' => 'support-share-draft@example.com',
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

        $conversation = InboxConversation::query()->create([
            'company_id' => $inbox->company_id,
            'shared_inbox_id' => $inbox->id,
            'folder' => 'inbox',
            'external_conversation_id' => 'conv-share-draft-'.uniqid(),
            'status' => 'open',
            'subject' => 'Need a quote',
            'from_name' => 'Customer',
            'from_email' => 'customer@example.com',
            'is_read' => true,
            'message_count' => 1,
            'last_message_at' => now(),
        ]);

        return [$user, $other, $outsider, $inbox, $conversation];
    }
}
