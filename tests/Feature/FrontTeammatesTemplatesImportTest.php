<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\InboxTemplate;
use App\Models\Role;
use App\Models\User;
use App\Services\Front\FrontApiClient;
use App\Services\Front\FrontTeammatesAndTemplatesImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FrontTeammatesTemplatesImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_teammates_and_templates_from_json_export(): void
    {
        [$company, $role] = $this->seedCompany();

        User::query()->create([
            'name' => 'Existing Agent',
            'email' => 'alex@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $path = $this->writeExport([
            'teammates' => [
                [
                    'id' => 'tea_1',
                    'email' => 'alex@lns.test',
                    'first_name' => 'Alex',
                    'last_name' => 'Agent',
                ],
                [
                    'id' => 'tea_2',
                    'email' => 'new@lns.test',
                    'first_name' => 'New',
                    'last_name' => 'Hire',
                ],
                [
                    'id' => 'tea_3',
                    'email' => 'blocked@lns.test',
                    'is_blocked' => true,
                ],
            ],
            'message_templates' => [
                [
                    'id' => 'rsp_1',
                    'name' => 'Welcome',
                    'subject' => 'Thanks for writing',
                    'body' => "Hi there,\nWe will reply shortly.",
                ],
            ],
        ]);

        $stats = app(FrontTeammatesAndTemplatesImportService::class)->importFromFile($company, $path, [
            'role' => $role->slug,
        ]);

        $this->assertSame(3, $stats['teammates_scanned']);
        $this->assertSame(1, $stats['teammates_existing']);
        $this->assertSame(1, $stats['teammates_created']);
        $this->assertSame(1, $stats['teammates_skipped_blocked']);
        $this->assertSame(1, $stats['templates_created']);

        $created = User::query()->where('email', 'new@lns.test')->first();
        $this->assertNotNull($created);
        $this->assertSame($company->id, $created->company_id);
        $this->assertSame($role->id, $created->role_id);
        $this->assertSame('New Hire', $created->name);

        $this->assertDatabaseHas('inbox_templates', [
            'company_id' => $company->id,
            'name' => 'Welcome',
            'subject' => 'Thanks for writing',
            'front_template_id' => 'rsp_1',
        ]);
    }

    public function test_dry_run_does_not_write_users_or_templates(): void
    {
        [$company] = $this->seedCompany();

        $path = $this->writeExport([
            'teammates' => [
                ['email' => 'new@lns.test', 'first_name' => 'New', 'last_name' => 'Hire'],
            ],
            'message_templates' => [
                ['id' => 'rsp_1', 'name' => 'Welcome', 'body' => 'Hello'],
            ],
        ]);

        $stats = app(FrontTeammatesAndTemplatesImportService::class)->importFromFile($company, $path, [
            'dry_run' => true,
        ]);

        $this->assertSame(1, $stats['teammates_created']);
        $this->assertSame(1, $stats['templates_created']);
        $this->assertNull(User::query()->where('email', 'new@lns.test')->first());
        $this->assertSame(0, InboxTemplate::query()->count());
    }

    public function test_skips_templates_already_imported(): void
    {
        [$company] = $this->seedCompany();

        $path = $this->writeExport([
            'message_templates' => [
                ['id' => 'rsp_1', 'name' => 'Welcome', 'body' => 'Hello'],
            ],
        ]);

        $service = app(FrontTeammatesAndTemplatesImportService::class);
        $service->importFromFile($company, $path, ['import_teammates' => false]);
        $second = $service->importFromFile($company, $path, ['import_teammates' => false]);

        $this->assertSame(1, $second['templates_existing']);
        $this->assertSame(0, $second['templates_created']);
        $this->assertSame(1, InboxTemplate::query()->count());
    }

    public function test_imports_from_front_api(): void
    {
        [$company, $role] = $this->seedCompany();

        Http::fake([
            'https://api2.frontapp.com/teammates/*/message_templates*' => Http::response([
                '_results' => [],
            ]),
            'https://api2.frontapp.com/teammates*' => Http::response([
                '_results' => [
                    [
                        'id' => 'tea_api',
                        'email' => 'api@lns.test',
                        'first_name' => 'Api',
                        'last_name' => 'User',
                    ],
                ],
            ]),
            'https://api2.frontapp.com/message_templates*' => Http::response([
                '_results' => [
                    [
                        'id' => 'rsp_api',
                        'name' => 'Follow up',
                        'body' => 'Just checking in.',
                    ],
                ],
            ]),
        ]);

        $stats = app(FrontTeammatesAndTemplatesImportService::class)->importFromApi(
            $company,
            new FrontApiClient('front-test-token'),
            ['role' => $role->id]
        );

        $this->assertSame(1, $stats['teammates_created']);
        $this->assertSame(1, $stats['templates_created']);
        $this->assertDatabaseHas('users', [
            'email' => 'api@lns.test',
            'company_id' => $company->id,
        ]);
        $this->assertDatabaseHas('inbox_templates', [
            'front_template_id' => 'rsp_api',
            'name' => 'Follow up',
        ]);
    }

    /**
     * @return array{0: Company, 1: Role}
     */
    private function seedCompany(): array
    {
        $company = Company::query()->create([
            'name' => 'Loc & Stor',
            'subdomain' => 'front-teammates',
            'quotation_prefix' => 'LNS',
            'status' => 'active',
            'email' => 'staff@lns.test',
        ]);

        $role = Role::query()->create([
            'name' => 'Agent',
            'slug' => 'agent-front',
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        return [$company, $role];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeExport(array $payload): string
    {
        $path = storage_path('framework/testing/front-teammates-templates.json');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR));

        return $path;
    }
}
