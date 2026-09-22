<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\InboxSignature;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SharedInbox;
use App\Models\SharedInboxMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InboxSignatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrap_returns_only_the_logged_in_users_signatures(): void
    {
        [$user, $other] = $this->inboxUsers();

        $mine = InboxSignature::query()->create([
            'user_id' => $user->id,
            'company_id' => $user->company_id,
            'name' => 'Mine',
            'body_html' => '<p>Alice</p>',
            'body_text' => 'Alice',
            'is_default' => true,
        ]);
        InboxSignature::query()->create([
            'user_id' => $other->id,
            'company_id' => $other->company_id,
            'name' => 'Theirs',
            'body_html' => '<p>Bob</p>',
            'body_text' => 'Bob',
            'is_default' => true,
        ]);

        $payload = $this->actingAs($user)
            ->getJson('/api/inbox/bootstrap')
            ->assertOk()
            ->assertJsonPath('default_signature_id', $mine->id)
            ->json();

        $ids = collect($payload['signatures'] ?? [])->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertSame([$mine->id], $ids);
    }

    public function test_user_can_create_and_update_their_own_signature(): void
    {
        [$user] = $this->inboxUsers();

        $created = $this->actingAs($user)
            ->postJson('/api/inbox/signatures', [
                'name' => 'Work',
                'body_html' => '<p>Best,<br>Alice</p>',
                'body_text' => "Best,\nAlice",
            ])
            ->assertCreated()
            ->assertJsonPath('signature.name', 'Work')
            ->assertJsonPath('signature.is_default', true)
            ->json();

        $id = $created['signature']['id'];

        $this->actingAs($user)
            ->putJson('/api/inbox/signatures/'.$id, [
                'name' => 'Work updated',
                'body_html' => '<p>Thanks,<br>Alice</p>',
            ])
            ->assertOk()
            ->assertJsonPath('signature.name', 'Work updated')
            ->assertJsonPath('signature.is_default', true);

        $this->assertDatabaseHas('inbox_signatures', [
            'id' => $id,
            'user_id' => $user->id,
            'name' => 'Work updated',
            'is_default' => true,
        ]);
    }

    public function test_users_cannot_see_or_change_another_users_signature(): void
    {
        [$user, $other] = $this->inboxUsers();

        $theirs = InboxSignature::query()->create([
            'user_id' => $other->id,
            'company_id' => $other->company_id,
            'name' => 'Bob',
            'body_html' => '<p>Bob</p>',
            'body_text' => 'Bob',
            'is_default' => true,
        ]);

        $this->actingAs($user)
            ->putJson('/api/inbox/signatures/'.$theirs->id, [
                'name' => 'Hijacked',
                'body_html' => '<p>Nope</p>',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->postJson('/api/inbox/signatures/'.$theirs->id.'/default')
            ->assertForbidden();

        $this->actingAs($user)
            ->deleteJson('/api/inbox/signatures/'.$theirs->id)
            ->assertForbidden();

        $this->assertDatabaseHas('inbox_signatures', [
            'id' => $theirs->id,
            'user_id' => $other->id,
            'name' => 'Bob',
        ]);
    }

    public function test_setting_default_is_scoped_to_the_logged_in_user(): void
    {
        [$user, $other] = $this->inboxUsers();

        $first = InboxSignature::query()->create([
            'user_id' => $user->id,
            'company_id' => $user->company_id,
            'name' => 'First',
            'body_html' => '<p>One</p>',
            'body_text' => 'One',
            'is_default' => true,
        ]);
        $second = InboxSignature::query()->create([
            'user_id' => $user->id,
            'company_id' => $user->company_id,
            'name' => 'Second',
            'body_html' => '<p>Two</p>',
            'body_text' => 'Two',
            'is_default' => false,
        ]);
        $otherDefault = InboxSignature::query()->create([
            'user_id' => $other->id,
            'company_id' => $other->company_id,
            'name' => 'Other default',
            'body_html' => '<p>Other</p>',
            'body_text' => 'Other',
            'is_default' => true,
        ]);

        $this->actingAs($user)
            ->postJson('/api/inbox/signatures/'.$second->id.'/default')
            ->assertOk()
            ->assertJsonPath('default_signature_id', $second->id)
            ->assertJsonPath('signature.is_default', true);

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
        $this->assertTrue($otherDefault->fresh()->is_default);
    }

    public function test_deleting_the_default_promotes_another_signature(): void
    {
        [$user] = $this->inboxUsers();

        $default = InboxSignature::query()->create([
            'user_id' => $user->id,
            'company_id' => $user->company_id,
            'name' => 'Alpha',
            'body_html' => '<p>A</p>',
            'body_text' => 'A',
            'is_default' => true,
        ]);
        $kept = InboxSignature::query()->create([
            'user_id' => $user->id,
            'company_id' => $user->company_id,
            'name' => 'Beta',
            'body_html' => '<p>B</p>',
            'body_text' => 'B',
            'is_default' => false,
        ]);

        $this->actingAs($user)
            ->deleteJson('/api/inbox/signatures/'.$default->id)
            ->assertOk()
            ->assertJsonPath('deleted', true)
            ->assertJsonPath('default_signature_id', $kept->id);

        $this->assertDatabaseMissing('inbox_signatures', ['id' => $default->id]);
        $this->assertTrue($kept->fresh()->is_default);
    }

    public function test_import_copies_browser_signatures_onto_the_current_user_only(): void
    {
        [$user, $other] = $this->inboxUsers();

        $this->actingAs($user)
            ->postJson('/api/inbox/signatures/import', [
                'default_signature_id' => 'sig_local_2',
                'signatures' => [
                    [
                        'id' => 'sig_local_1',
                        'name' => 'Imported A',
                        'body_html' => '<p>A</p>',
                        'body_text' => 'A',
                    ],
                    [
                        'id' => 'sig_local_2',
                        'name' => 'Imported B',
                        'body_html' => '<p>B</p>',
                        'body_text' => 'B',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('imported', 2);

        $this->assertSame(2, InboxSignature::query()->where('user_id', $user->id)->count());
        $this->assertSame(0, InboxSignature::query()->where('user_id', $other->id)->count());
        $this->assertTrue(
            InboxSignature::query()->where('user_id', $user->id)->where('name', 'Imported B')->value('is_default')
        );
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function inboxUsers(): array
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-inbox-signatures',
            'status' => 'active',
            'email' => 'admin-inbox-signatures@lns.test',
        ]);

        $role = Role::query()->create([
            'name' => 'Staff',
            'slug' => 'staff-inbox-signatures',
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
            'name' => 'Alice Agent',
            'email' => 'alice-inbox-signatures@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);
        $other = User::query()->create([
            'name' => 'Bob Agent',
            'email' => 'bob-inbox-signatures@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $inbox = SharedInbox::query()->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'name' => 'Support',
            'email' => 'support-signatures@example.com',
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

        return [$user, $other];
    }
}
