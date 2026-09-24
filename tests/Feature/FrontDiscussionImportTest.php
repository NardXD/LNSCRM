<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\Front\FrontApiClient;
use App\Services\Front\FrontDiscussionImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FrontDiscussionImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_discussion_threads_into_messaging_from_json_export(): void
    {
        $company = $this->seedCompany('front-discussions');
        $alex = $this->seedUser($company, 'Alex Agent', 'alex@lns.test');
        $pat = $this->seedUser($company, 'Pat Staff', 'pat@lns.test');

        $path = $this->writeExport([
            'discussions' => [
                [
                    'id' => 'cnv_standup',
                    'type' => 'discussion',
                    'subject' => 'Weekly standup',
                    'updated_at' => 1710000400,
                    'followers' => [
                        ['email' => 'alex@lns.test', 'first_name' => 'Alex', 'last_name' => 'Agent'],
                        ['email' => 'pat@lns.test', 'first_name' => 'Pat', 'last_name' => 'Staff'],
                    ],
                    'comments' => [
                        [
                            'id' => 'com_2',
                            'body' => 'I can take the follow-up.',
                            'posted_at' => 1710000300,
                            'author' => [
                                'email' => 'pat@lns.test',
                                'first_name' => 'Pat',
                                'last_name' => 'Staff',
                            ],
                        ],
                        [
                            'id' => 'com_1',
                            'body' => 'Anyone free after lunch?',
                            'posted_at' => 1710000200,
                            'author' => [
                                'email' => 'alex@lns.test',
                                'first_name' => 'Alex',
                                'last_name' => 'Agent',
                            ],
                        ],
                    ],
                ],
            ],
            'conversations' => [
                [
                    'id' => 'cnv_email',
                    'type' => 'conversation',
                    'subject' => 'Customer email',
                    'comments' => [
                        [
                            'id' => 'com_email',
                            'body' => 'Should not import',
                            'author' => ['email' => 'alex@lns.test'],
                        ],
                    ],
                ],
            ],
        ]);

        $stats = app(FrontDiscussionImportService::class)->importFromFile($company, $path);

        $this->assertSame(2, $stats['conversations_scanned']);
        $this->assertSame(1, $stats['conversations_skipped']);
        $this->assertSame(1, $stats['discussions_found']);
        $this->assertSame(1, $stats['discussions_imported']);
        $this->assertSame(2, $stats['messages_imported']);
        $this->assertSame(0, $stats['messages_unmatched_author']);

        $conversation = Conversation::query()->where('front_conversation_id', 'cnv_standup')->first();
        $this->assertNotNull($conversation);
        $this->assertSame('group', $conversation->type);
        $this->assertSame(Conversation::KIND_DISCUSSION, $conversation->kind);
        $this->assertSame('Weekly standup', $conversation->name);
        $this->assertEqualsCanonicalizing([$alex->id, $pat->id], $conversation->participants()->pluck('users.id')->all());

        $messages = Message::query()->where('conversation_id', $conversation->id)->orderBy('created_at')->get();
        $this->assertCount(2, $messages);
        $this->assertSame('Anyone free after lunch?', $messages[0]->body);
        $this->assertSame($alex->id, $messages[0]->user_id);
        $this->assertSame(1710000200, $messages[0]->created_at?->getTimestamp());
        $this->assertSame('I can take the follow-up.', $messages[1]->body);
        $this->assertSame($pat->id, $messages[1]->user_id);
        $this->assertSame(0, Conversation::query()->where('front_conversation_id', 'cnv_email')->count());
    }

    public function test_prefixes_unmatched_front_author_on_imported_messages(): void
    {
        $company = $this->seedCompany('front-discussions-orphan');
        $fallback = $this->seedUser($company, 'Fallback Admin', 'admin@lns.test');

        $path = $this->writeExport([
            'discussions' => [
                [
                    'id' => 'cnv_orphan',
                    'subject' => 'Old thread',
                    'comments' => [
                        [
                            'id' => 'com_orphan',
                            'body' => 'Left this in Front.',
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
        ]);

        $stats = app(FrontDiscussionImportService::class)->importFromFile($company, $path, [
            'fallback_user_id' => $fallback->id,
        ]);

        $this->assertSame(1, $stats['messages_imported']);
        $this->assertSame(1, $stats['messages_unmatched_author']);

        $message = Message::query()->where('front_comment_id', 'com_orphan')->first();
        $this->assertNotNull($message);
        $this->assertSame($fallback->id, $message->user_id);
        $this->assertSame('Former Teammate: Left this in Front.', $message->body);
        $this->assertTrue(
            $message->conversation->participants()->where('users.id', $fallback->id)->exists()
        );
    }

    public function test_dry_run_does_not_write_messaging_chats(): void
    {
        $company = $this->seedCompany('front-discussions-dry');
        $this->seedUser($company, 'Alex Agent', 'alex@lns.test');

        $path = $this->writeExport([
            'discussions' => [
                [
                    'id' => 'cnv_dry',
                    'subject' => 'Preview only',
                    'comments' => [
                        [
                            'id' => 'com_dry',
                            'body' => 'Preview me',
                            'author' => ['email' => 'alex@lns.test'],
                        ],
                    ],
                ],
            ],
        ]);

        $stats = app(FrontDiscussionImportService::class)->importFromFile($company, $path, [
            'dry_run' => true,
        ]);

        $this->assertSame(1, $stats['discussions_imported']);
        $this->assertSame(1, $stats['messages_imported']);
        $this->assertSame(0, Conversation::query()->count());
        $this->assertSame(0, Message::query()->count());
    }

    public function test_skips_comments_that_were_already_imported(): void
    {
        $company = $this->seedCompany('front-discussions-dup');
        $this->seedUser($company, 'Alex Agent', 'alex@lns.test');

        $path = $this->writeExport([
            'discussions' => [
                [
                    'id' => 'cnv_dup',
                    'subject' => 'Same thread',
                    'updated_at' => time() + 60,
                    'comments' => [
                        [
                            'id' => 'com_dup',
                            'body' => 'Same note',
                            'author' => ['email' => 'alex@lns.test'],
                        ],
                    ],
                ],
            ],
        ]);

        $service = app(FrontDiscussionImportService::class);
        $service->resetProgress($company);

        $first = $service->importFromFile($company, $path);
        $this->assertSame(1, $first['messages_imported']);

        $service->resetProgress($company);

        $second = $service->importFromFile($company, $path);
        $this->assertSame(0, $second['messages_imported']);
        $this->assertSame(1, $second['messages_existing']);
        $this->assertSame(1, Message::query()->count());
        $this->assertSame(1, Conversation::query()->count());
    }

    public function test_imports_discussions_from_front_api_and_skips_email_threads(): void
    {
        $company = $this->seedCompany('front-discussions-api');
        $this->seedUser($company, 'Pat Staff', 'staff@lns.test');

        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/conversations/cnv_disc/comments')) {
                return Http::response([
                    '_results' => [
                        [
                            'id' => 'com_api',
                            'body' => 'Ship it Friday.',
                            'posted_at' => 1710000200,
                            'author' => [
                                'email' => 'staff@lns.test',
                                'first_name' => 'Pat',
                                'last_name' => 'Staff',
                            ],
                        ],
                    ],
                ]);
            }

            if (str_contains($url, '/followers')) {
                return Http::response([
                    '_results' => [
                        ['email' => 'staff@lns.test', 'first_name' => 'Pat', 'last_name' => 'Staff'],
                    ],
                ]);
            }

            return Http::response([
                '_results' => [
                    [
                        'id' => 'cnv_email',
                        'type' => 'conversation',
                        'subject' => 'Customer email',
                    ],
                    [
                        'id' => 'cnv_disc',
                        'type' => 'discussion',
                        'subject' => 'Launch checklist',
                    ],
                ],
            ]);
        });

        $stats = app(FrontDiscussionImportService::class)->importFromApi(
            $company,
            new FrontApiClient('front-test-token')
        );

        $this->assertSame(2, $stats['conversations_scanned']);
        $this->assertSame(1, $stats['conversations_skipped']);
        $this->assertSame(1, $stats['discussions_imported']);
        $this->assertSame(1, $stats['messages_imported']);
        $this->assertDatabaseHas('conversations', [
            'front_conversation_id' => 'cnv_disc',
            'name' => 'Launch checklist',
            'type' => 'group',
        ]);
        $this->assertDatabaseHas('messages', [
            'front_comment_id' => 'com_api',
            'body' => 'Ship it Friday.',
        ]);
        $this->assertDatabaseMissing('conversations', [
            'front_conversation_id' => 'cnv_email',
        ]);
    }

    private function seedCompany(string $subdomain): Company
    {
        return Company::query()->create([
            'name' => 'Loc & Stor',
            'subdomain' => $subdomain,
            'quotation_prefix' => 'LNS',
            'status' => 'active',
            'email' => 'staff@lns.test',
        ]);
    }

    private function seedUser(Company $company, string $name, string $email): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeExport(array $payload): string
    {
        $path = storage_path('framework/testing/front-discussions.json');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR));

        return $path;
    }
}
