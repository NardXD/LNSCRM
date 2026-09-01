<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\FrontIntegration;
use App\Models\InboxConversation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SharedInbox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FrontTagImportUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_front_integration_can_be_saved_and_used_to_run_import(): void
    {
        [$user, $company, $sharedInbox] = $this->userWithIntegrationsPermission();
        $this->seedConversation($company, $sharedInbox, 'Pricing request', 'pat@example.com');

        Http::fake([
            'https://api2.frontapp.com/inboxes' => Http::response([
                '_results' => [
                    ['id' => 'inb_1', 'name' => $sharedInbox->name],
                ],
            ]),
            'https://api2.frontapp.com/inboxes/inb_1/conversations*' => Http::response([
                '_results' => [
                    [
                        'subject' => 'Pricing request',
                        'recipient' => ['handle' => 'pat@example.com'],
                        'tags' => [
                            ['name' => 'Hot', 'highlight' => 'red', 'is_private' => false],
                        ],
                    ],
                ],
            ]),
        ]);

        $this->actingAs($user)
            ->postJson('/api/integrations/front', ['api_token' => 'front-secret-token'])
            ->assertOk()
            ->assertJsonPath('status', 'connected');

        $this->actingAs($user)
            ->getJson('/api/integrations/front/mapping')
            ->assertOk()
            ->assertJsonPath('rows.0.front_id', 'inb_1')
            ->assertJsonPath('rows.0.shared_inbox_id', $sharedInbox->id);

        $response = $this->actingAs($user)
            ->postJson('/api/integrations/front/import-tags', [
                'dry_run' => false,
                'inbox_map' => ['inb_1' => $sharedInbox->id],
            ])
            ->assertOk()
            ->assertJsonPath('stats.conversations_matched', 1)
            ->assertJsonPath('stats.tags_created', 1);

        $this->assertDatabaseHas('inbox_tags', [
            'company_id' => $company->id,
            'name' => 'Hot',
        ]);

        $integration = FrontIntegration::query()->where('company_id', $company->id)->first();
        $this->assertNotNull($integration?->last_import_at);
        $this->assertSame(1, $integration->last_import_stats['conversations_matched'] ?? null);
    }

    public function test_front_import_requires_connection(): void
    {
        [$user] = $this->userWithIntegrationsPermission();

        $this->actingAs($user)
            ->postJson('/api/integrations/front/import-tags', ['dry_run' => true])
            ->assertStatus(400);
    }

    /**
     * @return array{0: User, 1: Company, 2: SharedInbox}
     */
    private function userWithIntegrationsPermission(): array
    {
        $company = Company::query()->create([
            'name' => 'Loc & Stor',
            'subdomain' => 'front-ui',
            'quotation_prefix' => 'LNS',
            'status' => 'active',
            'email' => 'staff@lns.test',
        ]);

        $role = Role::query()->create([
            'name' => 'Admin',
            'slug' => 'admin-front-ui',
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
