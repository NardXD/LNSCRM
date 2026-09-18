<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\InboxConversation;
use App\Models\InboxConversationActivity;
use App\Models\InboxConversationComment;
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

class InboxCommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_can_edit_their_internal_comment(): void
    {
        [$user, $other, $inbox, $conversation, $comment] = $this->threadWithComment();

        $this->actingAs($user)
            ->patchJson('/api/inbox/conversations/'.$conversation->id.'/comments/'.$comment->id, [
                'body' => 'Updated internal note',
                'mentioned_user_ids' => [$other->id],
            ])
            ->assertOk()
            ->assertJsonPath('comment.body_text', 'Updated internal note')
            ->assertJsonPath('comment.can_edit', true)
            ->assertJsonPath('comment.mentioned_user_ids.0', $other->id);

        $this->assertDatabaseHas('inbox_conversation_comments', [
            'id' => $comment->id,
            'body_text' => 'Updated internal note',
        ]);
        $this->assertDatabaseHas('inbox_conversation_activities', [
            'inbox_conversation_id' => $conversation->id,
            'action' => 'comment_updated',
        ]);
    }

    public function test_teammate_cannot_edit_someone_elses_comment(): void
    {
        [$user, $other, $inbox, $conversation, $comment] = $this->threadWithComment();

        $this->actingAs($other)
            ->patchJson('/api/inbox/conversations/'.$conversation->id.'/comments/'.$comment->id, [
                'body' => 'Nope',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('inbox_conversation_comments', [
            'id' => $comment->id,
            'body_text' => 'Original note',
        ]);
    }

    public function test_author_can_delete_their_internal_comment_and_attachments(): void
    {
        [$user, $other, $inbox, $conversation, $comment] = $this->threadWithComment();

        Storage::fake('local');
        $path = 'inbox-comments/'.$comment->id.'/0_photo.png';
        Storage::disk('local')->put($path, 'png');
        $comment->update([
            'attachments' => [[
                'name' => 'photo.png',
                'content_type' => 'image/png',
                'size' => 3,
                'path' => $path,
                'index' => 0,
            ]],
        ]);

        $this->actingAs($user)
            ->deleteJson('/api/inbox/conversations/'.$conversation->id.'/comments/'.$comment->id)
            ->assertOk()
            ->assertJsonPath('deleted', true);

        $this->assertDatabaseMissing('inbox_conversation_comments', ['id' => $comment->id]);
        $this->assertFalse(Storage::disk('local')->exists($path));
        $this->assertDatabaseHas('inbox_conversation_activities', [
            'inbox_conversation_id' => $conversation->id,
            'action' => 'comment_deleted',
        ]);
    }

    public function test_teammate_cannot_delete_someone_elses_comment(): void
    {
        [$user, $other, $inbox, $conversation, $comment] = $this->threadWithComment();

        $this->actingAs($other)
            ->deleteJson('/api/inbox/conversations/'.$conversation->id.'/comments/'.$comment->id)
            ->assertForbidden();

        $this->assertDatabaseHas('inbox_conversation_comments', ['id' => $comment->id]);
    }

    public function test_empty_comment_update_is_rejected(): void
    {
        [$user, $other, $inbox, $conversation, $comment] = $this->threadWithComment();

        $this->actingAs($user)
            ->patchJson('/api/inbox/conversations/'.$conversation->id.'/comments/'.$comment->id, [
                'body' => '<p>   </p>',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Comment cannot be empty.');
    }

    public function test_imported_comment_cannot_be_edited_by_fallback_user(): void
    {
        [$user, $other, $inbox, $conversation, $comment] = $this->threadWithComment([
            'imported_author_name' => 'Front Teammate',
            'imported_author_email' => 'front-author@example.com',
        ]);

        $this->actingAs($user)
            ->getJson('/api/inbox/conversations/'.$conversation->id)
            ->assertOk()
            ->assertJsonPath('conversation.comments.0.can_edit', false);

        $this->actingAs($user)
            ->patchJson('/api/inbox/conversations/'.$conversation->id.'/comments/'.$comment->id, [
                'body' => 'Should not work',
            ])
            ->assertForbidden();
    }

    public function test_editing_a_comment_notifies_newly_mentioned_teammates_only(): void
    {
        Notification::fake();

        [$user, $other, $inbox, $conversation, $comment] = $this->threadWithComment();

        $this->actingAs($user)
            ->patchJson('/api/inbox/conversations/'.$conversation->id.'/comments/'.$comment->id, [
                'body' => 'Hey <span data-mention-user-id="'.$other->id.'">@Other</span>',
                'mentioned_user_ids' => [$other->id],
            ])
            ->assertOk();

        Notification::assertSentTo($other, InboxThreadUpdateNotification::class);
        Notification::assertNotSentTo($user, InboxThreadUpdateNotification::class);
        $this->assertSame(1, InboxConversationActivity::query()->where('action', 'comment_updated')->count());
    }

    /**
     * @param  array<string, mixed>  $commentOverrides
     * @return array{0: User, 1: User, 2: SharedInbox, 3: InboxConversation, 4: InboxConversationComment}
     */
    private function threadWithComment(array $commentOverrides = []): array
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-inbox-comments',
            'status' => 'active',
            'email' => 'admin-inbox-comments@lns.test',
        ]);

        $role = Role::query()->create([
            'name' => 'Staff',
            'slug' => 'staff-inbox-comments',
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
            'email' => 'login-inbox-comments@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);
        $other = User::query()->create([
            'name' => 'Other Agent',
            'email' => 'other-inbox-comments@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $inbox = SharedInbox::query()->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'name' => 'Support',
            'email' => 'support-comments@example.com',
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
            'external_conversation_id' => 'conv-comments-'.uniqid(),
            'status' => 'open',
            'subject' => 'Need a quote',
            'from_name' => 'Customer',
            'from_email' => 'customer@example.com',
            'is_read' => true,
            'message_count' => 1,
            'last_message_at' => now(),
        ]);

        $comment = InboxConversationComment::query()->create(array_merge([
            'inbox_conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'body_html' => '<p>Original note</p>',
            'body_text' => 'Original note',
            'mentioned_user_ids' => [],
            'attachments' => [],
        ], $commentOverrides));

        return [$user, $other, $inbox, $conversation, $comment];
    }
}
