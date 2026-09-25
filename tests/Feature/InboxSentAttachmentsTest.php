<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\InboxConversation;
use App\Models\InboxMessage;
use App\Models\OutlookMailAccount;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SharedInbox;
use App\Models\SharedInboxMember;
use App\Models\User;
use App\Services\OutlookMailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InboxSentAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_compose_uses_outlook_attachment_metadata_on_the_sent_message(): void
    {
        [$user, $inbox] = $this->connectedInboxFixture();

        $this->mock(OutlookMailService::class, function ($mock) {
            $mock->shouldReceive('sendMail')->once()->andReturn([
                'sent' => true,
                'id' => 'graph-msg-compose-1',
                'conversationId' => 'graph-conv-compose-1',
                'attachments' => [[
                    'id' => 'graph-attach-1',
                    'name' => 'quote.pdf',
                    'content_type' => 'application/pdf',
                    'size' => 12,
                    'is_inline' => false,
                    'content_id' => null,
                ]],
            ]);
        });

        $response = $this->actingAs($user)
            ->postJson('/api/inbox/compose', [
                'inbox_id' => $inbox->id,
                'to' => 'customer@example.com',
                'subject' => 'Quote with file',
                'body' => '<p>See attached</p>',
                'attachments' => [[
                    'name' => 'quote.pdf',
                    'contentType' => 'application/pdf',
                    'contentBytes' => base64_encode('%PDF-fake'),
                ]],
            ])
            ->assertCreated();

        $conversationId = (int) $response->json('conversation.id');
        $messageId = (int) $response->json('message.id');

        $message = InboxMessage::query()->findOrFail($messageId);
        $this->assertSame('graph-msg-compose-1', $message->external_message_id);
        $this->assertNotEmpty($message->attachments);
        $this->assertSame('graph-attach-1', $message->attachments[0]['id']);
        $this->assertSame('quote.pdf', $message->attachments[0]['name']);
        $this->assertArrayNotHasKey('path', $message->attachments[0]);

        $conversation = InboxConversation::query()->findOrFail($conversationId);
        $this->assertSame('graph-conv-compose-1', $conversation->external_conversation_id);

        $shown = $this->actingAs($user)
            ->getJson('/api/inbox/conversations/'.$conversationId)
            ->assertOk()
            ->json('conversation.messages.0.attachments');

        $this->assertCount(1, $shown);
        $this->assertSame('quote.pdf', $shown[0]['name']);
        $this->assertNotEmpty($shown[0]['download_url']);
        $this->assertFalse($shown[0]['local']);
    }

    public function test_reply_uses_outlook_attachment_metadata_on_the_outbound_message(): void
    {
        [$user, $inbox] = $this->connectedInboxFixture();

        $conversation = InboxConversation::query()->create([
            'company_id' => $user->company_id,
            'shared_inbox_id' => $inbox->id,
            'folder' => 'inbox',
            'external_conversation_id' => 'conv-'.uniqid(),
            'status' => 'open',
            'subject' => 'Need docs',
            'from_name' => 'Customer',
            'from_email' => 'customer@example.com',
            'assigned_to' => $user->id,
            'is_read' => true,
            'message_count' => 1,
            'last_message_at' => now(),
        ]);

        $this->mock(OutlookMailService::class, function ($mock) {
            $mock->shouldReceive('sendMail')->once()->andReturn([
                'sent' => true,
                'id' => 'graph-msg-reply-1',
                'attachments' => [[
                    'id' => 'graph-attach-2',
                    'name' => 'spec.docx',
                    'content_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'size' => 20,
                    'is_inline' => false,
                    'content_id' => null,
                ]],
            ]);
        });

        $this->actingAs($user)
            ->postJson('/api/inbox/conversations/'.$conversation->id.'/reply', [
                'to' => 'customer@example.com',
                'body' => '<p>Attached</p>',
                'attachments' => [[
                    'name' => 'spec.docx',
                    'contentType' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'contentBytes' => base64_encode('docx-bytes'),
                ]],
            ])
            ->assertOk();

        $outbound = InboxMessage::query()
            ->where('inbox_conversation_id', $conversation->id)
            ->where('direction', 'outbound')
            ->latest('id')
            ->first();

        $this->assertNotNull($outbound);
        $this->assertSame('graph-msg-reply-1', $outbound->external_message_id);
        $this->assertSame('graph-attach-2', $outbound->attachments[0]['id']);
        $this->assertArrayNotHasKey('path', $outbound->attachments[0]);

        $shown = $this->actingAs($user)
            ->getJson('/api/inbox/conversations/'.$conversation->id)
            ->assertOk()
            ->json('conversation.messages');

        $outboundPayload = collect($shown)->firstWhere('id', $outbound->id);
        $this->assertNotNull($outboundPayload);
        $this->assertCount(1, $outboundPayload['attachments']);
        $this->assertSame('spec.docx', $outboundPayload['attachments'][0]['name']);
        $this->assertFalse($outboundPayload['attachments'][0]['local']);
    }

    /**
     * @return array{0: User, 1: SharedInbox}
     */
    private function connectedInboxFixture(): array
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-inbox-sent-attach',
            'status' => 'active',
            'email' => 'admin-inbox-sent-attach@lns.test',
        ]);

        $role = Role::query()->create([
            'name' => 'Staff',
            'slug' => 'staff-inbox-sent-attach',
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
            'email' => 'login-inbox-sent-attach@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $account = OutlookMailAccount::query()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'email' => 'mailbox@example.com',
            'access_token' => 'token',
            'refresh_token' => 'refresh',
            'token_expires_at' => now()->addHour(),
        ]);

        $inbox = SharedInbox::query()->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'outlook_mail_account_id' => $account->id,
            'name' => 'Support',
            'email' => 'mailbox@example.com',
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
}
