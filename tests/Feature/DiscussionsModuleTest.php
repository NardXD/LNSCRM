<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Conversation;
use App\Models\DiscussionRule;
use App\Models\InboxTag;
use App\Models\Message;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SharedInbox;
use App\Models\SharedInboxMember;
use App\Models\User;
use App\Services\DiscussionRuleEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DiscussionsModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_page_requires_view_discussions_permission(): void
    {
        [$user] = $this->usersWithDiscussions();
        $user->role->permissions()->detach();

        $this->actingAs($user)->getJson('/api/discussions/bootstrap')->assertForbidden();
    }

    public function test_page_loads_with_permission(): void
    {
        [$user] = $this->usersWithDiscussions();

        $this->actingAs($user)->get('/discussions')->assertOk();
    }

    public function test_create_with_teammates_xor_shared_inbox(): void
    {
        [$user, $other, $inbox] = $this->usersWithDiscussions();

        $this->actingAs($user)
            ->postJson('/api/discussions/conversations', [
                'subject' => 'Both',
                'comment' => 'Nope',
                'teammate_ids' => [$other->id],
                'shared_inbox_id' => $inbox->id,
            ])
            ->assertStatus(422);

        $this->actingAs($user)
            ->postJson('/api/discussions/conversations', [
                'subject' => 'Missing to',
                'comment' => 'Nope',
            ])
            ->assertStatus(422);

        $created = $this->actingAs($user)
            ->postJson('/api/discussions/conversations', [
                'subject' => 'Planning',
                'comment' => 'Lets sync tomorrow',
                'teammate_ids' => [$other->id],
            ])
            ->assertCreated()
            ->json('data');

        $this->assertSame('Planning', $created['subject']);
        $this->assertSame(Conversation::KIND_DISCUSSION, Conversation::find($created['id'])->kind);
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $created['id'],
            'body' => 'Lets sync tomorrow',
            'user_id' => $user->id,
        ]);
    }

    public function test_create_with_shared_inbox_adds_members(): void
    {
        [$user, $other, $inbox] = $this->usersWithDiscussions();

        $created = $this->actingAs($user)
            ->postJson('/api/discussions/conversations', [
                'subject' => 'IT issue',
                'comment' => 'Printers are down',
                'shared_inbox_id' => $inbox->id,
            ])
            ->assertCreated()
            ->json('data');

        $conversation = Conversation::find($created['id']);
        $this->assertSame($inbox->id, $conversation->shared_inbox_id);
        $this->assertNotNull($conversation->moved_to_shared_at);
        $this->assertEqualsCanonicalizing(
            [$user->id, $other->id],
            $conversation->participants()->pluck('users.id')->all()
        );
    }

    public function test_list_views_and_access(): void
    {
        [$user, $other] = $this->usersWithDiscussions();

        $mine = $this->makeDiscussion($user, [$user->id, $other->id], ['name' => 'Mine']);
        $assigned = $this->makeDiscussion($other, [$other->id], [
            'name' => 'Assigned to me',
            'assigned_to' => $user->id,
        ]);
        $snoozed = $this->makeDiscussion($user, [$user->id], [
            'name' => 'Later',
            'reopen_at' => now()->addDay(),
        ]);
        $archived = $this->makeDiscussion($user, [$user->id], [
            'name' => 'Done',
            'status' => Conversation::STATUS_ARCHIVED,
        ]);

        $subscribed = collect($this->actingAs($user)->getJson('/api/discussions/conversations?view=subscribed')->json('data'))
            ->pluck('id')->all();
        $this->assertContains($mine->id, $subscribed);
        $this->assertNotContains($assigned->id, $subscribed);

        $assignedIds = collect($this->actingAs($user)->getJson('/api/discussions/conversations?view=assigned')->json('data'))
            ->pluck('id')->all();
        $this->assertContains($assigned->id, $assignedIds);

        $snoozedIds = collect($this->actingAs($user)->getJson('/api/discussions/conversations?view=snoozed')->json('data'))
            ->pluck('id')->all();
        $this->assertContains($snoozed->id, $snoozedIds);

        $archivedIds = collect($this->actingAs($user)->getJson('/api/discussions/conversations?view=archived')->json('data'))
            ->pluck('id')->all();
        $this->assertContains($archived->id, $archivedIds);
    }

    public function test_assign_snooze_archive_unsubscribe_and_subject(): void
    {
        [$user, $other] = $this->usersWithDiscussions();
        $discussion = $this->makeDiscussion($user, [$user->id, $other->id], ['name' => 'Ops']);

        $this->actingAs($user)
            ->patchJson('/api/discussions/conversations/'.$discussion->id, ['subject' => 'Ops renamed'])
            ->assertOk()
            ->assertJsonPath('data.subject', 'Ops renamed');

        $this->actingAs($user)
            ->postJson('/api/discussions/conversations/'.$discussion->id.'/assign', ['assigned_to' => $other->id])
            ->assertOk()
            ->assertJsonPath('data.assigned_to', $other->id);

        $this->actingAs($user)
            ->postJson('/api/discussions/conversations/'.$discussion->id.'/snooze', [
                'reopen_at' => now()->addDays(2)->toIso8601String(),
            ])
            ->assertOk();
        $this->assertNotNull($discussion->fresh()->reopen_at);

        $this->actingAs($user)
            ->postJson('/api/discussions/conversations/'.$discussion->id.'/archive', ['archived' => true])
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');

        $this->actingAs($other)
            ->postJson('/api/discussions/conversations/'.$discussion->id.'/unsubscribe')
            ->assertOk();
        $this->assertFalse($discussion->participants()->where('users.id', $other->id)->exists());
    }

    public function test_tags_and_move_constraints(): void
    {
        [$user, $other, $inbox] = $this->usersWithDiscussions();
        $otherInbox = SharedInbox::query()->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'name' => 'Sales',
            'email' => 'sales@example.com',
            'type' => SharedInbox::TYPE_SHARED,
            'is_active' => true,
        ]);
        SharedInboxMember::query()->create([
            'shared_inbox_id' => $otherInbox->id,
            'user_id' => $user->id,
            'role' => 'member',
        ]);

        $tag = InboxTag::query()->create([
            'company_id' => $user->company_id,
            'name' => 'Urgent',
            'color' => '#ef4444',
        ]);

        $discussion = $this->makeDiscussion($user, [$user->id], ['name' => 'Tagged']);

        $this->actingAs($user)
            ->postJson('/api/discussions/conversations/'.$discussion->id.'/tags', ['tag_ids' => [$tag->id]])
            ->assertOk();
        $this->assertTrue($discussion->tags()->where('inbox_tags.id', $tag->id)->exists());

        $this->actingAs($user)
            ->postJson('/api/discussions/conversations/'.$discussion->id.'/move', ['shared_inbox_id' => $inbox->id])
            ->assertOk();
        $discussion->refresh();
        $this->assertSame($inbox->id, $discussion->shared_inbox_id);
        $this->assertNotNull($discussion->moved_to_shared_at);

        $this->actingAs($user)
            ->postJson('/api/discussions/conversations/'.$discussion->id.'/move', ['shared_inbox_id' => null])
            ->assertStatus(422);

        $this->actingAs($user)
            ->postJson('/api/discussions/conversations/'.$discussion->id.'/move', ['shared_inbox_id' => $otherInbox->id])
            ->assertOk()
            ->assertJsonPath('data.shared_inbox.id', $otherInbox->id);
    }

    public function test_mention_adds_participant(): void
    {
        [$user, $other] = $this->usersWithDiscussions();
        $discussion = $this->makeDiscussion($user, [$user->id], ['name' => 'Loop in']);

        $this->actingAs($user)
            ->postJson('/api/discussions/conversations/'.$discussion->id.'/messages', [
                'body' => 'Hey @'.$other->name.' can you look?',
                'mentioned_user_ids' => [$other->id],
            ])
            ->assertCreated();

        $this->assertTrue($discussion->participants()->where('users.id', $other->id)->exists());
    }

    public function test_rule_assigns_on_create(): void
    {
        [$user, $other] = $this->usersWithDiscussions();

        DiscussionRule::query()->create([
            'company_id' => $user->company_id,
            'name' => 'Auto assign',
            'priority' => 1,
            'is_active' => true,
            'stop_processing' => true,
            'triggers' => [DiscussionRuleEngine::TRIGGER_DISCUSSION_CREATED],
            'conditions' => [],
            'actions' => [['type' => 'assign', 'value' => $other->id]],
            'created_by' => $user->id,
        ]);

        $created = $this->actingAs($user)
            ->postJson('/api/discussions/conversations', [
                'subject' => 'Auto owned',
                'comment' => 'Please take this',
                'teammate_ids' => [$other->id],
            ])
            ->assertCreated()
            ->json('data');

        $this->assertSame($other->id, $created['assigned_to']);
    }

    public function test_messaging_excludes_discussions(): void
    {
        [$user, $other] = $this->usersWithDiscussions();

        $msgPerm = Permission::query()->create([
            'name' => 'view_messaging',
            'slug' => 'view_messaging',
            'display_name' => 'Messaging',
            'company_id' => $user->company_id,
        ]);
        $user->role->permissions()->attach($msgPerm->id);
        $user = $user->fresh(['role']);

        $discussion = $this->makeDiscussion($user, [$user->id, $other->id], ['name' => 'Hidden from messaging']);
        $chat = Conversation::query()->create([
            'company_id' => $user->company_id,
            'type' => 'group',
            'kind' => Conversation::KIND_CHAT,
            'status' => Conversation::STATUS_OPEN,
            'name' => 'Normal chat',
            'created_by' => $user->id,
        ]);
        $chat->participants()->attach([$user->id, $other->id]);
        Message::query()->create([
            'conversation_id' => $chat->id,
            'user_id' => $user->id,
            'body' => 'hi',
        ]);

        $response = $this->actingAs($user)->getJson('/api/messaging/conversations');
        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($chat->id, $ids);
        $this->assertNotContains($discussion->id, $ids);
    }

    public function test_imported_discussion_kind_is_discussion(): void
    {
        [$user] = $this->usersWithDiscussions();

        $conversation = Conversation::query()->create([
            'company_id' => $user->company_id,
            'type' => 'group',
            'kind' => Conversation::KIND_DISCUSSION,
            'status' => Conversation::STATUS_OPEN,
            'name' => 'Imported',
            'created_by' => $user->id,
            'front_conversation_id' => 'cnv_imported',
        ]);
        $conversation->participants()->attach($user->id);

        $ids = collect($this->actingAs($user)->getJson('/api/discussions/conversations?view=discussions')->json('data'))
            ->pluck('id')->all();
        $this->assertContains($conversation->id, $ids);
    }

    /**
     * @return array{0: User, 1: User, 2: SharedInbox}
     */
    private function usersWithDiscussions(): array
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-discussions-'.uniqid(),
            'status' => 'active',
            'email' => 'admin-discussions-'.uniqid().'@lns.test',
        ]);

        $role = Role::query()->create([
            'name' => 'Staff',
            'slug' => 'staff-discussions-'.uniqid(),
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        foreach ([
            ['view_discussions', 'Discussions'],
            ['create_discussion_tags', 'Discussion Tags'],
            ['create_discussion_rules', 'Discussion Rules'],
        ] as [$slug, $label]) {
            $permission = Permission::query()->create([
                'name' => $slug,
                'slug' => $slug,
                'display_name' => $label,
                'company_id' => $company->id,
            ]);
            $role->permissions()->attach($permission->id);
        }

        $user = User::query()->create([
            'name' => 'Alex Agent',
            'email' => 'alex-'.uniqid().'@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);
        $other = User::query()->create([
            'name' => 'Pat Staff',
            'email' => 'pat-'.uniqid().'@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $inbox = SharedInbox::query()->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'name' => 'Support',
            'email' => 'support-'.uniqid().'@example.com',
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
     * @param  list<int>  $participantIds
     * @param  array<string, mixed>  $overrides
     */
    private function makeDiscussion(User $creator, array $participantIds, array $overrides = []): Conversation
    {
        $conversation = Conversation::query()->create(array_merge([
            'company_id' => $creator->company_id,
            'type' => 'group',
            'kind' => Conversation::KIND_DISCUSSION,
            'status' => Conversation::STATUS_OPEN,
            'name' => 'Discussion',
            'created_by' => $creator->id,
        ], $overrides));

        foreach (array_unique($participantIds) as $pid) {
            $conversation->participants()->attach($pid, ['last_read_at' => now()]);
        }

        Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $creator->id,
            'body' => 'Starter',
        ]);

        return $conversation;
    }
}
