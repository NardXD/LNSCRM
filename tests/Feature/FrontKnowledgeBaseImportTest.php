<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\KnowledgeBaseArticle;
use App\Models\KnowledgeBaseCategory;
use App\Models\User;
use App\Services\Front\FrontApiClient;
use App\Services\Front\FrontKnowledgeBaseImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FrontKnowledgeBaseImportTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://api2.frontapp.com';

    public function test_imports_categories_and_articles_from_front(): void
    {
        Storage::fake('public');
        [$company, $firstUser, $editor] = $this->seedCompany();
        $this->fakeFront();

        $stats = app(FrontKnowledgeBaseImportService::class)->importFromApi($company, new FrontApiClient('front-test-token'));

        $this->assertSame(1, $stats['knowledge_bases_scanned']);
        $this->assertSame(2, $stats['categories_scanned']);
        $this->assertSame(2, $stats['articles_created']);
        $this->assertSame(0, $stats['articles_failed']);
        $this->assertSame(2, $stats['files_downloaded']);

        $this->assertSame(2, $stats['categories_created']);
        $billing = KnowledgeBaseCategory::query()->where('front_category_id', 'kbc_parent')->firstOrFail();
        $refunds = KnowledgeBaseCategory::query()->where('front_category_id', 'kbc_child')->firstOrFail();
        $this->assertSame('Billing', $billing->name);
        $this->assertNull($billing->parent_id);
        $this->assertSame('Refunds', $refunds->name);
        $this->assertSame($billing->id, $refunds->parent_id);
        $this->assertSame('article', $refunds->type);

        $published = KnowledgeBaseArticle::query()->where('front_article_id', 'kba_1')->firstOrFail();
        $this->assertSame('How to refund', $published->title);
        $this->assertSame('published', $published->visibility);
        $this->assertSame($refunds->id, $published->category_id);
        $this->assertSame('Billing › Refunds', $published->category);
        $this->assertSame(1654309308, $published->updated_at->getTimestamp());
        $this->assertSame($editor->id, $published->user_id);
        $this->assertSame('Refunds Go to Billing &amp; click refund.', $published->excerpt);
        $this->assertStringNotContainsString('frontapp.com', $published->content);
        $this->assertStringContainsString('src="/media/knowledge-base/front/'.$company->id.'/kba_1/', $published->content);
        $this->assertStringContainsString('<h3>Attachments</h3>', $published->content);
        $this->assertStringContainsString('guide.pdf', $published->content);
        $this->assertSame(1622672452, $published->created_at->getTimestamp());

        $draft = KnowledgeBaseArticle::query()->where('front_article_id', 'kba_2')->firstOrFail();
        $this->assertSame('draft', $draft->visibility);
        $this->assertNull($draft->category_id);
        $this->assertNull($draft->category);
        $this->assertSame($firstUser->id, $draft->user_id);

        $this->assertCount(2, Storage::disk('public')->allFiles("knowledge-base/front/{$company->id}/kba_1"));
    }

    public function test_second_run_skips_already_imported_articles(): void
    {
        Storage::fake('public');
        [$company] = $this->seedCompany();
        $this->fakeFront();

        $service = app(FrontKnowledgeBaseImportService::class);
        $service->importFromApi($company, new FrontApiClient('front-test-token'));
        KnowledgeBaseArticle::query()->where('front_article_id', 'kba_1')->update(['title' => 'Edited in CRM']);

        $second = $service->importFromApi($company, new FrontApiClient('front-test-token'));

        $this->assertSame(2, $second['articles_existing']);
        $this->assertSame(0, $second['articles_created']);
        $this->assertSame(2, KnowledgeBaseArticle::query()->count());
        $this->assertSame(0, $second['categories_created']);
        $this->assertSame(2, KnowledgeBaseCategory::query()->where('type', 'article')->count());
        $this->assertSame('Edited in CRM', KnowledgeBaseArticle::query()->where('front_article_id', 'kba_1')->value('title'));

        $refreshed = $service->importFromApi($company, new FrontApiClient('front-test-token'), ['refresh' => true]);

        $this->assertSame(2, $refreshed['articles_updated']);
        $this->assertSame(2, KnowledgeBaseArticle::query()->count());
        $this->assertSame('How to refund', KnowledgeBaseArticle::query()->where('front_article_id', 'kba_1')->value('title'));
    }

    public function test_dry_run_writes_nothing(): void
    {
        Storage::fake('public');
        [$company] = $this->seedCompany();
        $this->fakeFront();

        $stats = app(FrontKnowledgeBaseImportService::class)->importFromApi(
            $company,
            new FrontApiClient('front-test-token'),
            ['dry_run' => true]
        );

        $this->assertSame(2, $stats['articles_created']);
        $this->assertSame(0, KnowledgeBaseArticle::query()->count());
        $this->assertSame(0, KnowledgeBaseCategory::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_command_runs_import(): void
    {
        Storage::fake('public');
        [$company] = $this->seedCompany();
        $this->fakeFront();

        $this->artisan('knowledge-base:import-front', [
            '--company' => $company->id,
            '--token' => 'front-test-token',
        ])->assertSuccessful();

        $this->assertSame(2, KnowledgeBaseArticle::query()->where('company_id', $company->id)->count());
    }

    private function fakeFront(): void
    {
        Http::fake([
            self::API.'/knowledge_bases/knb_1/categories*' => Http::response([
                '_pagination' => ['next' => null],
                '_results' => [
                    ['id' => 'kbc_parent', 'slug' => '/categories/1', 'is_hidden' => false, 'locales' => ['en']],
                    ['id' => 'kbc_child', 'slug' => '/categories/2', 'is_hidden' => false, 'locales' => ['en']],
                ],
            ]),
            self::API.'/knowledge_bases/knb_1/articles*' => Http::response([
                '_pagination' => ['next' => null],
                '_results' => [
                    ['id' => 'kba_1', 'slug' => '/articles/1', 'locales' => ['en']],
                    ['id' => 'kba_2', 'slug' => '/articles/2', 'locales' => ['en']],
                ],
            ]),
            self::API.'/knowledge_bases*' => Http::response([
                '_results' => [
                    ['id' => 'knb_1', 'type' => 'internal', 'locales' => ['en']],
                ],
            ]),
            self::API.'/knowledge_base_categories/kbc_parent/content' => Http::response([
                'id' => 'kbc_parent',
                'name' => 'Billing',
                '_links' => ['related' => ['parent_category' => null]],
            ]),
            self::API.'/knowledge_base_categories/kbc_child/content' => Http::response([
                'id' => 'kbc_child',
                'name' => 'Refunds',
                '_links' => ['related' => ['parent_category' => 'https://acme.api.frontapp.com/knowledge_base_categories/kbc_parent']],
            ]),
            self::API.'/knowledge_base_articles/kba_1/content' => Http::response([
                'id' => 'kba_1',
                'name' => 'How to refund',
                'status' => 'published',
                'content' => '<h1>Refunds</h1><p>Go to Billing &amp; click refund.</p><img src="https://acme.api.frontapp.com/download/fil_img">',
                'created_at' => 1622672452.363,
                'updated_at' => 1654309308.278,
                'attachments' => [
                    ['id' => 'fil_img', 'filename' => 'screen.png', 'url' => 'https://acme.api.frontapp.com/download/fil_img', 'content_type' => 'image/png', 'size' => 3, 'metadata' => ['is_inline' => true]],
                    ['id' => 'fil_pdf', 'filename' => 'guide.pdf', 'url' => 'https://acme.api.frontapp.com/download/fil_pdf', 'content_type' => 'application/pdf', 'size' => 3, 'metadata' => ['is_inline' => false]],
                ],
                '_links' => ['related' => [
                    'category' => 'https://acme.api.frontapp.com/knowledge_base_categories/kbc_child',
                    'last_editor' => 'https://acme.api.frontapp.com/teammates/tea_1',
                ]],
            ]),
            self::API.'/knowledge_base_articles/kba_2/content' => Http::response([
                'id' => 'kba_2',
                'name' => 'Work in progress',
                'status' => 'draft',
                'content' => '<p>Not ready</p>',
                'attachments' => [],
                '_links' => ['related' => []],
            ]),
            'https://acme.api.frontapp.com/teammates/tea_1' => Http::response([
                'id' => 'tea_1',
                'email' => 'Editor@LNS.test',
            ]),
            'https://acme.api.frontapp.com/download/*' => Http::response('bin', 200, ['Content-Type' => 'application/octet-stream']),
        ]);
    }

    /**
     * @return array{0: Company, 1: User, 2: User}
     */
    private function seedCompany(): array
    {
        $company = Company::query()->create([
            'name' => 'Loc & Stor',
            'subdomain' => 'front-kb',
            'quotation_prefix' => 'LNS',
            'status' => 'active',
            'email' => 'staff@lns.test',
        ]);

        $first = User::query()->create([
            'name' => 'First User',
            'email' => 'first@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        $editor = User::query()->create([
            'name' => 'Editor',
            'email' => 'editor@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        return [$company, $first, $editor];
    }
}
