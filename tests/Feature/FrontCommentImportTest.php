<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\InboxConversation;
use App\Models\InboxConversationComment;
use App\Models\SharedInbox;
use App\Models\User;
use App\Services\Front\FrontApiClient;
use App\Services\Front\FrontCommentImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FrontCommentImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_comments_from_json_export_and_matches_authors(): void
    {
        [$company, $sharedInbox, $conversation] = $this->seedInboxConversation(
            subject: 'Storage inquiry',
            fromEmail: 'jane@example.com'
        );

        $author = User::query()->create([
            'name' => 'Alex Agent',
            'email' => 'alex@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        $payload = [
            'inboxes' => [
                [
                    'id' => 'inb_sales',
                    'name' => $sharedInbox->name,
                    'conversations' => [
                        [
                            'id' => 'cnv_1',
                            'subject' => 'Re: Storage inquiry',
                            'recipient' => ['handle' => 'jane@example.com'],
                            'comments' => [
                                [
                                    'id' => 'com_1',
                                    'body' => "Customer wants a quote.\nCall back tomorrow.",
                                    'posted_at' => 1710000000,
                                    'author' => [
                                        'email' => 'alex@lns.test',
                                        'first_name' => 'Alex',
                                        'last_name' => 'Agent',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $path = $this->writeExport($payload);

        $stats = app(FrontCommentImportService::class)->importFromFile($company, $path, [
            'inbox_map' => ['inb_sales' => $sharedInbox->id],
        ]);

        $this->assertSame(1, $stats['mapped_inboxes']);
        $this->assertSame(1, $stats['conversations_matched']);
        $this->assertSame(1, $stats['conversations_with_comments']);
        $this->assertSame(1, $stats['comments_imported']);
        $this->assertSame(0, $stats['comments_unmatched_author']);

        $comment = InboxConversationComment::query()->where('front_comment_id', 'com_1')->first();
        $this->assertNotNull($comment);
        $this->assertSame($conversation->id, $comment->inbox_conversation_id);
        $this->assertSame($author->id, $comment->user_id);
        $this->assertSame("Customer wants a quote.\nCall back tomorrow.", $comment->body_text);
        $this->assertStringContainsString('Call back tomorrow.', $comment->body_html);
        $this->assertSame(1710000000, $comment->created_at?->getTimestamp());
    }

    public function test_keeps_front_author_name_when_teammate_is_not_a_crm_user(): void
    {
        [$company, $sharedInbox, $conversation] = $this->seedInboxConversation(
            subject: 'Storage inquiry',
            fromEmail: 'jane@example.com'
        );

        $fallback = User::query()->create([
            'name' => 'Fallback Admin',
            'email' => 'admin@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        $path = $this->writeExport([
            'inboxes' => [
                [
                    'id' => 'inb_sales',
                    'name' => $sharedInbox->name,
                    'conversations' => [
                        [
                            'id' => 'cnv_1',
                            'subject' => 'Storage inquiry',
                            'recipient' => ['handle' => 'jane@example.com'],
                            'comments' => [
                                [
                                    'id' => 'com_orphan',
                                    'body' => 'Left this note in Front.',
                                    'posted_at' => 1710000100,
                                    'author' => [
                                        'email' => 'former@front.test',
                                        'first_name' => 'Former',
                                        'last_name' => 'Teammate',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $stats = app(FrontCommentImportService::class)->importFromFile($company, $path, [
            'inbox_map' => ['inb_sales' => $sharedInbox->id],
            'fallback_user_id' => $fallback->id,
        ]);

        $this->assertSame(1, $stats['comments_imported']);
        $this->assertSame(1, $stats['comments_unmatched_author']);

        $comment = InboxConversationComment::query()->where('front_comment_id', 'com_orphan')->first();
        $this->assertNotNull($comment);
        $this->assertSame($conversation->id, $comment->inbox_conversation_id);
        $this->assertSame($fallback->id, $comment->user_id);
        $this->assertSame('Former Teammate', $comment->imported_author_name);
        $this->assertSame('former@front.test', $comment->imported_author_email);
    }

    public function test_dry_run_does_not_write_comments(): void
    {
        [$company, $sharedInbox] = $this->seedInboxConversation(
            subject: 'Storage inquiry',
            fromEmail: 'jane@example.com'
        );

        User::query()->create([
            'name' => 'Alex Agent',
            'email' => 'alex@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        $path = $this->writeExport([
            'inboxes' => [
                [
                    'id' => 'inb_sales',
                    'name' => $sharedInbox->name,
                    'conversations' => [
                        [
                            'id' => 'cnv_1',
                            'subject' => 'Storage inquiry',
                            'recipient' => ['handle' => 'jane@example.com'],
                            'comments' => [
                                [
                                    'id' => 'com_1',
                                    'body' => 'Preview only',
                                    'author' => ['email' => 'alex@lns.test'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $stats = app(FrontCommentImportService::class)->importFromFile($company, $path, [
            'inbox_map' => ['inb_sales' => $sharedInbox->id],
            'dry_run' => true,
        ]);

        $this->assertSame(1, $stats['comments_imported']);
        $this->assertSame(0, InboxConversationComment::query()->count());
    }

    public function test_skips_comments_that_were_already_imported(): void
    {
        [$company, $sharedInbox] = $this->seedInboxConversation(
            subject: 'Storage inquiry',
            fromEmail: 'jane@example.com'
        );

        User::query()->create([
            'name' => 'Alex Agent',
            'email' => 'alex@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        $path = $this->writeExport([
            'inboxes' => [
                [
                    'id' => 'inb_sales',
                    'name' => $sharedInbox->name,
                    'conversations' => [
                        [
                            'id' => 'cnv_1',
                            'subject' => 'Storage inquiry',
                            'recipient' => ['handle' => 'jane@example.com'],
                            'updated_at' => time() + 60,
                            'comments' => [
                                [
                                    'id' => 'com_1',
                                    'body' => 'Same note',
                                    'author' => ['email' => 'alex@lns.test'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $service = app(FrontCommentImportService::class);
        $service->resetProgress($company);

        $first = $service->importFromFile($company, $path, [
            'inbox_map' => ['inb_sales' => $sharedInbox->id],
        ]);
        $this->assertSame(1, $first['comments_imported']);

        $service->resetProgress($company);

        $second = $service->importFromFile($company, $path, [
            'inbox_map' => ['inb_sales' => $sharedInbox->id],
        ]);

        $this->assertSame(0, $second['comments_imported']);
        $this->assertSame(1, $second['comments_existing']);
        $this->assertSame(1, InboxConversationComment::query()->count());
    }

    public function test_imports_comments_from_front_api(): void
    {
        [$company, $sharedInbox] = $this->seedInboxConversation(
            subject: 'Pricing request',
            fromEmail: 'pat@example.com'
        );

        User::query()->create([
            'name' => 'Pat Staff',
            'email' => 'staff@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        Http::fake([
            'https://api2.frontapp.com/inboxes' => Http::response([
                '_results' => [
                    ['id' => 'inb_1', 'name' => $sharedInbox->name],
                ],
            ]),
            'https://api2.frontapp.com/inboxes/inb_1/conversations*' => Http::response([
                '_results' => [
                    [
                        'id' => 'cnv_9',
                        'subject' => 'Pricing request',
                        'recipient' => ['handle' => 'pat@example.com'],
                    ],
                ],
            ]),
            'https://api2.frontapp.com/conversations/cnv_9/comments*' => Http::response([
                '_results' => [
                    [
                        'id' => 'com_api',
                        'body' => 'Follow up next week.',
                        'posted_at' => 1710000200,
                        'author' => [
                            'email' => 'staff@lns.test',
                            'first_name' => 'Pat',
                            'last_name' => 'Staff',
                        ],
                    ],
                ],
            ]),
        ]);

        $stats = app(FrontCommentImportService::class)->importFromApi(
            $company,
            new FrontApiClient('front-test-token'),
            ['inbox_map' => ['inb_1' => $sharedInbox->id]]
        );

        $this->assertSame(1, $stats['conversations_matched']);
        $this->assertSame(1, $stats['comments_imported']);
        $this->assertDatabaseHas('inbox_conversation_comments', [
            'front_comment_id' => 'com_api',
            'body_text' => 'Follow up next week.',
        ]);
    }

    /**
     * @return array{0: Company, 1: SharedInbox, 2: InboxConversation}
     */
    private function seedInboxConversation(string $subject, string $fromEmail): array
    {
        $company = Company::query()->create([
            'name' => 'Loc & Stor',
            'subdomain' => 'front-comments',
            'quotation_prefix' => 'LNS',
            'status' => 'active',
            'email' => 'staff@lns.test',
        ]);

        $sharedInbox = SharedInbox::query()->create([
            'company_id' => $company->id,
            'name' => 'Sales Inbox',
            'email' => 'sales@lns.test',
            'type' => SharedInbox::TYPE_SHARED,
            'color' => '#5f61e6',
            'is_active' => true,
        ]);

        $conversation = InboxConversation::query()->create([
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

        return [$company, $sharedInbox, $conversation];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeExport(array $payload): string
    {
        $path = storage_path('framework/testing/front-comments.json');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR));

        return $path;
    }
}
