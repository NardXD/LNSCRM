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

class InboxSentViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_sent_view_lists_mail_the_user_sent_from_personal_and_shared_inboxes(): void
    {
        [$user, $other, $shared, $personal] = $this->fixture();

        $mineShared = $this->conversation($shared, 'Mine via shared');
        $this->message($mineShared, 'outbound', $user);

        $theirsShared = $this->conversation($shared, 'Teammate via shared', 'sent');
        $this->message($theirsShared, 'outbound', $other);

        // Synced from the user's own Outlook Sent Items — no CRM sender recorded.
        $minePersonal = $this->conversation($personal, 'Mine via personal', 'sent');
        $this->message($minePersonal, 'outbound', null);

        $inboundOnly = $this->conversation($shared, 'Inbound only');
        $this->message($inboundOnly, 'inbound', null);

        $trashed = $this->conversation($shared, 'Trashed', 'trash');
        $this->message($trashed, 'outbound', $user);

        $draftOnly = $this->conversation($shared, 'Draft only');
        $this->message($draftOnly, 'outbound', $user, true);

        $ids = collect($this->actingAs($user)
            ->getJson('/api/inbox/conversations?view=sent')
            ->assertOk()
            ->json('conversations'))
            ->pluck('id')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(collect([$mineShared->id, $minePersonal->id])->sort()->values()->all(), $ids);
    }

    public function test_mailbox_sent_folder_still_lists_the_whole_folder(): void
    {
        [$user, $other, $shared] = $this->fixture();

        $theirsShared = $this->conversation($shared, 'Teammate via shared', 'sent');
        $this->message($theirsShared, 'outbound', $other);

        $ids = collect($this->actingAs($user)
            ->getJson('/api/inbox/conversations?view=sent&inbox_id='.$shared->id)
            ->assertOk()
            ->json('conversations'))
            ->pluck('id')
            ->all();

        $this->assertSame([$theirsShared->id], $ids);
    }

    /**
     * @return array{0: User, 1: User, 2: SharedInbox, 3: SharedInbox}
     */
    private function fixture(): array
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-inbox-sent-view',
            'status' => 'active',
            'email' => 'admin-inbox-sent-view@lns.test',
        ]);
        $role = Role::query()->create([
            'name' => 'Staff',
            'slug' => 'staff-inbox-sent-view',
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

        $makeUser = fn (string $key) => User::query()->create([
            'name' => ucfirst($key).' Agent',
            'email' => $key.'-inbox-sent-view@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);
        $user = $makeUser('login');
        $other = $makeUser('other');

        $shared = SharedInbox::query()->create([
            'company_id' => $company->id,
            'created_by' => $other->id,
            'name' => 'Support',
            'email' => 'support-sent-view@example.com',
            'type' => SharedInbox::TYPE_SHARED,
            'is_active' => true,
        ]);
        foreach ([$user, $other] as $member) {
            SharedInboxMember::query()->create([
                'shared_inbox_id' => $shared->id,
                'user_id' => $member->id,
                'role' => 'member',
            ]);
        }

        $personal = SharedInbox::query()->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'name' => 'Personal',
            'email' => 'login-sent-view@example.com',
            'type' => SharedInbox::TYPE_PERSONAL,
            'is_active' => true,
        ]);

        return [$user, $other, $shared, $personal];
    }

    private function conversation(SharedInbox $inbox, string $subject, string $folder = 'inbox'): InboxConversation
    {
        return InboxConversation::query()->create([
            'company_id' => $inbox->company_id,
            'shared_inbox_id' => $inbox->id,
            'folder' => $folder,
            'external_conversation_id' => 'conv-sent-view-'.uniqid(),
            'status' => $folder === 'inbox' ? 'open' : ($folder === 'trash' ? 'trashed' : $folder),
            'subject' => $subject,
            'from_name' => 'Customer',
            'from_email' => 'customer@example.com',
            'is_read' => true,
            'message_count' => 1,
            'last_message_at' => now(),
        ]);
    }

    private function message(InboxConversation $conversation, string $direction, ?User $sender, bool $draft = false): void
    {
        InboxMessage::query()->create([
            'inbox_conversation_id' => $conversation->id,
            'external_message_id' => 'msg-sent-view-'.uniqid(),
            'direction' => $direction,
            'sent_by_user_id' => $sender?->id,
            'is_draft' => $draft,
            'from_name' => $sender?->name ?? 'Customer',
            'from_email' => 'someone@example.com',
            'to_emails' => 'customer@example.com',
            'subject' => $conversation->subject,
            'body_html' => '<p>Hi</p>',
            'body_text' => 'Hi',
            'is_read' => true,
            'sent_at' => now(),
        ]);
    }
}
