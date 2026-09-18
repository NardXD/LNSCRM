<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Conversation;
use App\Models\FrontIntegration;
use App\Models\Message;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FrontDiscussionImportUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_front_discussion_import_creates_messaging_group_chat(): void
    {
        [$user, $company] = $this->userWithIntegrationsPermission();
        $teammate = User::query()->create([
            'name' => 'Alex Agent',
            'email' => 'alex@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        FrontIntegration::query()->create([
            'company_id' => $company->id,
            'api_token' => Crypt::encryptString('front-secret-token'),
            'is_active' => true,
        ]);

        Http::fake(function ($request) use ($teammate, $user) {
            $url = $request->url();

            if (str_contains($url, '/comments')) {
                return Http::response([
                    '_results' => [
                        [
                            'id' => 'com_ui',
                            'body' => 'Need a callback.',
                            'posted_at' => 1710000300,
                            'author' => [
                                'email' => $teammate->email,
                                'first_name' => 'Alex',
                                'last_name' => 'Agent',
                            ],
                        ],
                    ],
                ]);
            }

            if (str_contains($url, '/followers')) {
                return Http::response([
                    '_results' => [
                        ['email' => $teammate->email],
                        ['email' => $user->email],
                    ],
                ]);
            }

            return Http::response([
                '_results' => [
                    [
                        'id' => 'cnv_ui',
                        'type' => 'discussion',
                        'subject' => 'Callback thread',
                    ],
                ],
            ]);
        });

        $this->actingAs($user)
            ->postJson('/api/integrations/front/import-discussions', [
                'dry_run' => false,
            ])
            ->assertOk()
            ->assertJsonPath('stats.discussions_imported', 1)
            ->assertJsonPath('stats.messages_imported', 1)
            ->assertJsonPath('has_more', false);

        $conversation = Conversation::query()->where('front_conversation_id', 'cnv_ui')->first();
        $this->assertNotNull($conversation);
        $this->assertSame('Callback thread', $conversation->name);
        $this->assertEqualsCanonicalizing(
            [$user->id, $teammate->id],
            $conversation->participants()->pluck('users.id')->all()
        );
        $this->assertDatabaseHas('messages', [
            'front_comment_id' => 'com_ui',
            'user_id' => $teammate->id,
            'body' => 'Need a callback.',
        ]);

        $integration = FrontIntegration::query()->where('company_id', $company->id)->first();
        $this->assertNotNull($integration?->last_discussion_import_at);
        $this->assertSame(1, $integration->last_discussion_import_stats['messages_imported'] ?? null);
    }

    public function test_front_discussion_import_requires_connection(): void
    {
        [$user] = $this->userWithIntegrationsPermission();

        $this->actingAs($user)
            ->postJson('/api/integrations/front/import-discussions', ['dry_run' => true])
            ->assertStatus(400);
    }

    public function test_dry_run_preview_does_not_persist_chats(): void
    {
        [$user, $company] = $this->userWithIntegrationsPermission();

        FrontIntegration::query()->create([
            'company_id' => $company->id,
            'api_token' => Crypt::encryptString('front-secret-token'),
            'is_active' => true,
        ]);

        Http::fake(function ($request) use ($user) {
            $url = $request->url();

            if (str_contains($url, '/comments')) {
                return Http::response([
                    '_results' => [
                        [
                            'id' => 'com_preview',
                            'body' => 'Preview me',
                            'author' => ['email' => $user->email],
                        ],
                    ],
                ]);
            }

            if (str_contains($url, '/followers')) {
                return Http::response(['_results' => []]);
            }

            return Http::response([
                '_results' => [
                    [
                        'id' => 'cnv_preview',
                        'type' => 'discussion',
                        'subject' => 'Preview thread',
                    ],
                ],
            ]);
        });

        $this->actingAs($user)
            ->postJson('/api/integrations/front/import-discussions', [
                'dry_run' => true,
                'persist_results' => false,
            ])
            ->assertOk()
            ->assertJsonPath('stats.messages_imported', 1)
            ->assertJsonPath('has_more', false);

        $this->assertSame(0, Conversation::query()->count());
        $this->assertSame(0, Message::query()->count());
    }

    public function test_paged_discussion_import_follows_front_pagination(): void
    {
        [$user, $company] = $this->userWithIntegrationsPermission();

        FrontIntegration::query()->create([
            'company_id' => $company->id,
            'api_token' => Crypt::encryptString('front-secret-token'),
            'is_active' => true,
        ]);

        $listPages = [
            [
                '_results' => [
                    [
                        'id' => 'cnv_one',
                        'type' => 'discussion',
                        'subject' => 'Page one',
                    ],
                ],
                '_pagination' => ['next' => 'https://api2.frontapp.com/conversations?page=2'],
            ],
            [
                '_results' => [
                    [
                        'id' => 'cnv_two',
                        'type' => 'discussion',
                        'subject' => 'Page two',
                    ],
                ],
            ],
        ];
        $listPage = 0;

        Http::fake(function ($request) use (&$listPage, $listPages, $user) {
            $url = $request->url();

            if (str_contains($url, '/comments')) {
                $commentId = str_contains($url, 'cnv_two') ? 'com_two' : 'com_one';
                $body = $commentId === 'com_two' ? 'Second comment' : 'First comment';

                return Http::response([
                    '_results' => [
                        [
                            'id' => $commentId,
                            'body' => $body,
                            'author' => ['email' => $user->email],
                        ],
                    ],
                ]);
            }

            if (str_contains($url, '/followers')) {
                return Http::response(['_results' => []]);
            }

            $page = $listPages[$listPage] ?? ['_results' => []];
            $listPage++;

            return Http::response($page);
        });

        $first = $this->actingAs($user)
            ->postJson('/api/integrations/front/import-discussions', [
                'dry_run' => false,
                'persist_results' => false,
            ])
            ->assertOk()
            ->assertJsonPath('has_more', true)
            ->assertJsonPath('stats.messages_imported', 1);

        $nextUrl = $first->json('next_page_url');
        $this->assertNotEmpty($nextUrl);

        $this->actingAs($user)
            ->postJson('/api/integrations/front/import-discussions', [
                'dry_run' => false,
                'page_url' => $nextUrl,
                'persist_results' => false,
            ])
            ->assertOk()
            ->assertJsonPath('has_more', false)
            ->assertJsonPath('stats.messages_imported', 1);

        $this->assertSame(2, Conversation::query()->count());
        $this->assertSame(2, Message::query()->count());
    }

    /**
     * @return array{0: User, 1: Company}
     */
    private function userWithIntegrationsPermission(): array
    {
        $company = Company::query()->create([
            'name' => 'Loc & Stor',
            'subdomain' => 'front-discussions-ui',
            'quotation_prefix' => 'LNS',
            'status' => 'active',
            'email' => 'staff@lns.test',
        ]);

        $role = Role::query()->create([
            'name' => 'Admin',
            'slug' => 'admin-front-discussions-ui',
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

        return [$user, $company];
    }
}
