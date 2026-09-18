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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InboxMessageIdSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_quick_search_finds_a_sent_message_by_local_id_from_open_view(): void
    {
        [$user, $inbox] = $this->inboxUser();
        $this->makeOpenConversation($inbox, 'Visible in open');
        $sent = $this->makeConversation($inbox, [
            'subject' => 'Sent quote',
            'folder' => 'sent',
            'status' => 'sent',
            'from_email' => 'support@example.com',
        ]);
        $message = $this->makeMessage($sent, [
            'external_message_id' => 'AAMkAGI-graph-id==',
            'direction' => 'outbound',
            'from_email' => 'support@example.com',
            'to_emails' => 'customer@example.com',
            'subject' => 'Sent quote',
            'body_text' => 'Here is the quote',
        ]);

        $payload = $this->actingAs($user)
            ->getJson('/api/inbox/conversations?view=open&search='.$message->id)
            ->assertOk()
            ->assertJsonPath('meta.matched_message_id', $message->id)
            ->json();

        $ids = collect($payload['conversations'] ?? [])->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertContains($sent->id, $ids);
    }

    public function test_quick_search_finds_a_message_by_outlook_id(): void
    {
        [$user, $inbox] = $this->inboxUser();
        $conversation = $this->makeOpenConversation($inbox, 'Need a quote');
        $message = $this->makeMessage($conversation, [
            'external_message_id' => 'AAMkAGI2threadooutlookid==',
            'from_email' => 'customer@example.com',
            'subject' => 'Need a quote',
            'body_text' => 'Can I get pricing?',
        ]);

        $this->actingAs($user)
            ->getJson('/api/inbox/conversations?view=open&search='.urlencode($message->external_message_id))
            ->assertOk()
            ->assertJsonPath('conversations.0.id', $conversation->id)
            ->assertJsonPath('meta.matched_message_id', $message->id);
    }

    public function test_quick_search_accepts_a_pasted_message_link(): void
    {
        [$user, $inbox] = $this->inboxUser();
        $conversation = $this->makeOpenConversation($inbox, 'Follow up');
        $message = $this->makeMessage($conversation, [
            'from_email' => 'customer@example.com',
            'subject' => 'Follow up',
            'body_text' => 'Checking in',
        ]);

        $link = 'http://localhost/inbox?conversation='.$conversation->id.'&message='.$message->id;

        $this->actingAs($user)
            ->getJson('/api/inbox/conversations?view=open&search='.urlencode($link))
            ->assertOk()
            ->assertJsonPath('conversations.0.id', $conversation->id)
            ->assertJsonPath('meta.matched_message_id', $message->id);
    }

    public function test_message_id_search_does_not_return_inaccessible_inboxes(): void
    {
        [$user, $inbox] = $this->inboxUser();
        $otherCompany = Company::query()->create([
            'name' => 'Other Co',
            'subdomain' => 'other-inbox-message-id',
            'status' => 'active',
            'email' => 'other-inbox-message-id@lns.test',
        ]);
        $foreignInbox = SharedInbox::query()->create([
            'company_id' => $otherCompany->id,
            'created_by' => $user->id,
            'name' => 'Foreign',
            'email' => 'foreign@example.com',
            'type' => SharedInbox::TYPE_SHARED,
            'is_active' => true,
        ]);
        $foreign = $this->makeOpenConversation($foreignInbox, 'Secret');
        $message = $this->makeMessage($foreign, [
            'from_email' => 'hidden@example.com',
            'subject' => 'Secret',
            'body_text' => 'Nope',
        ]);

        $this->actingAs($user)
            ->getJson('/api/inbox/conversations?view=open&search='.$message->id)
            ->assertOk()
            ->assertJsonCount(0, 'conversations');
    }

    public function test_inbox_page_includes_copy_id_and_link_actions(): void
    {
        [$user] = $this->inboxUser();

        $this->actingAs($user)
            ->get('/inbox')
            ->assertOk()
            ->assertSee('data-copy-msg-id=', false)
            ->assertSee('data-copy-msg-link=', false)
            ->assertSee('Copy message ID', false)
            ->assertSee('Copy message link', false)
            ->assertSee('Quick search or message ID', false);
    }

    /**
     * @return array{0: User, 1: SharedInbox}
     */
    private function inboxUser(): array
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-inbox-message-id',
            'status' => 'active',
            'email' => 'admin-inbox-message-id@lns.test',
        ]);

        $role = Role::query()->create([
            'name' => 'Staff',
            'slug' => 'staff-inbox-message-id',
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
            'email' => 'login-inbox-message-id@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $inbox = SharedInbox::query()->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'name' => 'Support',
            'email' => 'support-message-id@example.com',
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

    private function makeOpenConversation(SharedInbox $inbox, string $subject): InboxConversation
    {
        return $this->makeConversation($inbox, [
            'subject' => $subject,
            'from_email' => 'customer@example.com',
        ]);
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeMessage(InboxConversation $conversation, array $overrides): InboxMessage
    {
        return InboxMessage::query()->create(array_merge([
            'inbox_conversation_id' => $conversation->id,
            'external_message_id' => 'local-msg-'.uniqid(),
            'direction' => 'inbound',
            'is_draft' => false,
            'from_name' => 'Customer',
            'from_email' => 'customer@example.com',
            'to_emails' => 'support@example.com',
            'subject' => $conversation->subject,
            'body_html' => '<p>Hello</p>',
            'body_text' => 'Hello',
            'is_read' => true,
            'sent_at' => now(),
        ], $overrides));
    }
}
