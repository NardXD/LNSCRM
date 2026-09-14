<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\FrontIntegration;
use App\Models\InboxConversation;
use App\Models\InboxConversationComment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SharedInbox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FrontCommentImportUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_front_comment_import_matches_conversations_and_authors(): void
    {
        [$user, $company, $sharedInbox] = $this->userWithIntegrationsPermission();
        $this->seedConversation($company, $sharedInbox, 'Pricing request', 'pat@example.com');

        FrontIntegration::query()->create([
            'company_id' => $company->id,
            'api_token' => Crypt::encryptString('front-secret-token'),
            'is_active' => true,
        ]);

        Http::fake([
            'https://api2.frontapp.com/inboxes/inb_1/conversations*' => Http::response([
                '_results' => [
                    [
                        'id' => 'cnv_1',
                        'subject' => 'Pricing request',
                        'recipient' => ['handle' => 'pat@example.com'],
                    ],
                ],
            ]),
            'https://api2.frontapp.com/conversations/cnv_1/comments*' => Http::response([
                '_results' => [
                    [
                        'id' => 'com_ui',
                        'body' => 'Need a callback.',
                        'posted_at' => 1710000300,
                        'author' => [
                            'email' => $user->email,
                            'first_name' => 'Integrations',
                            'last_name' => 'Admin',
                        ],
                    ],
                ],
            ]),
        ]);

        $this->actingAs($user)
            ->postJson('/api/integrations/front/import-comments', [
                'dry_run' => false,
                'inbox_map' => ['inb_1' => $sharedInbox->id],
                'front_inbox_id' => 'inb_1',
                'shared_inbox_id' => $sharedInbox->id,
            ])
            ->assertOk()
            ->assertJsonPath('stats.conversations_matched', 1)
            ->assertJsonPath('stats.comments_imported', 1);

        $this->assertDatabaseHas('inbox_conversation_comments', [
            'front_comment_id' => 'com_ui',
            'user_id' => $user->id,
            'body_text' => 'Need a callback.',
        ]);

        $integration = FrontIntegration::query()->where('company_id', $company->id)->first();
        $this->assertNotNull($integration?->last_comment_import_at);
        $this->assertSame(1, $integration->last_comment_import_stats['comments_imported'] ?? null);
    }

    public function test_front_comment_import_requires_connection(): void
    {
        [$user] = $this->userWithIntegrationsPermission();

        $this->actingAs($user)
            ->postJson('/api/integrations/front/import-comments', ['dry_run' => true])
            ->assertStatus(400);
    }

    public function test_dry_run_preview_does_not_persist_comments(): void
    {
        [$user, $company, $sharedInbox] = $this->userWithIntegrationsPermission();
        $this->seedConversation($company, $sharedInbox, 'Pricing request', 'pat@example.com');

        FrontIntegration::query()->create([
            'company_id' => $company->id,
            'api_token' => Crypt::encryptString('front-secret-token'),
            'is_active' => true,
        ]);

        Http::fake([
            'https://api2.frontapp.com/inboxes/inb_1/conversations*' => Http::response([
                '_results' => [
                    [
                        'id' => 'cnv_1',
                        'subject' => 'Pricing request',
                        'recipient' => ['handle' => 'pat@example.com'],
                    ],
                ],
            ]),
            'https://api2.frontapp.com/conversations/cnv_1/comments*' => Http::response([
                '_results' => [
                    [
                        'id' => 'com_preview',
                        'body' => 'Preview me',
                        'author' => ['email' => $user->email],
                    ],
                ],
            ]),
        ]);

        $this->actingAs($user)
            ->postJson('/api/integrations/front/import-comments', [
                'dry_run' => true,
                'front_inbox_id' => 'inb_1',
                'shared_inbox_id' => $sharedInbox->id,
                'inbox_map' => ['inb_1' => $sharedInbox->id],
                'persist_results' => false,
            ])
            ->assertOk()
            ->assertJsonPath('stats.comments_imported', 1)
            ->assertJsonPath('has_more', false);

        $this->assertSame(0, InboxConversationComment::query()->count());
    }

    public function test_paged_comment_import_follows_front_pagination(): void
    {
        [$user, $company, $sharedInbox] = $this->userWithIntegrationsPermission();
        $this->seedConversation($company, $sharedInbox, 'Page one', 'one@example.com');
        $this->seedConversation($company, $sharedInbox, 'Page two', 'two@example.com');

        FrontIntegration::query()->create([
            'company_id' => $company->id,
            'api_token' => Crypt::encryptString('front-secret-token'),
            'is_active' => true,
        ]);

        Http::fake([
            'https://api2.frontapp.com/inboxes/inb_1/conversations*' => Http::sequence()
                ->push([
                    '_results' => [
                        [
                            'id' => 'cnv_one',
                            'subject' => 'Page one',
                            'recipient' => ['handle' => 'one@example.com'],
                        ],
                    ],
                    '_pagination' => ['next' => 'https://api2.frontapp.com/inboxes/inb_1/conversations?page=2'],
                ])
                ->push([
                    '_results' => [
                        [
                            'id' => 'cnv_two',
                            'subject' => 'Page two',
                            'recipient' => ['handle' => 'two@example.com'],
                        ],
                    ],
                ]),
            'https://api2.frontapp.com/conversations/cnv_one/comments*' => Http::response([
                '_results' => [
                    [
                        'id' => 'com_one',
                        'body' => 'First comment',
                        'author' => ['email' => $user->email],
                    ],
                ],
            ]),
            'https://api2.frontapp.com/conversations/cnv_two/comments*' => Http::response([
                '_results' => [
                    [
                        'id' => 'com_two',
                        'body' => 'Second comment',
                        'author' => ['email' => $user->email],
                    ],
                ],
            ]),
        ]);

        $first = $this->actingAs($user)
            ->postJson('/api/integrations/front/import-comments', [
                'dry_run' => false,
                'front_inbox_id' => 'inb_1',
                'shared_inbox_id' => $sharedInbox->id,
                'inbox_map' => ['inb_1' => $sharedInbox->id],
                'persist_results' => false,
            ])
            ->assertOk()
            ->assertJsonPath('has_more', true)
            ->assertJsonPath('stats.comments_imported', 1);

        $nextUrl = $first->json('next_page_url');
        $this->assertNotEmpty($nextUrl);

        $this->actingAs($user)
            ->postJson('/api/integrations/front/import-comments', [
                'dry_run' => false,
                'front_inbox_id' => 'inb_1',
                'shared_inbox_id' => $sharedInbox->id,
                'inbox_map' => ['inb_1' => $sharedInbox->id],
                'page_url' => $nextUrl,
                'persist_results' => false,
            ])
            ->assertOk()
            ->assertJsonPath('has_more', false)
            ->assertJsonPath('stats.comments_imported', 1);

        $this->assertSame(2, InboxConversationComment::query()->count());
    }

    /**
     * @return array{0: User, 1: Company, 2: SharedInbox}
     */
    private function userWithIntegrationsPermission(): array
    {
        $company = Company::query()->create([
            'name' => 'Loc & Stor',
            'subdomain' => 'front-comments-ui',
            'quotation_prefix' => 'LNS',
            'status' => 'active',
            'email' => 'staff@lns.test',
        ]);

        $role = Role::query()->create([
            'name' => 'Admin',
            'slug' => 'admin-front-comments-ui',
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        $permission = Permission::query()->create([
            'name' => 'view_integrations',
            'slug' => 'view_integrations',
            'display_name' => 'View Integrations',
            'description' => 'View integrations page',
            'category' => 'main',
        ]);
        $role->permissions()->attach($permission->id);

        $user = User::query()->create([
            'name' => 'Integrations Admin',
            'email' => 'integrations-admin@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $sharedInbox = SharedInbox::query()->create([
            'company_id' => $company->id,
            'name' => 'Sales Inbox',
            'email' => 'sales@lns.test',
            'type' => SharedInbox::TYPE_SHARED,
            'color' => '#5f61e6',
            'is_active' => true,
        ]);

        return [$user, $company, $sharedInbox];
    }

    private function seedConversation(Company $company, SharedInbox $sharedInbox, string $subject, string $fromEmail): void
    {
        InboxConversation::query()->create([
            'company_id' => $company->id,
            'shared_inbox_id' => $sharedInbox->id,
            'folder' => 'inbox',
            'subject' => $subject,
            'from_name' => 'Customer',
            'from_email' => $fromEmail,
            'status' => 'open',
            'is_read' => true,
            'message_count' => 1,
            'last_message_at' => now(),
        ]);
    }
}
