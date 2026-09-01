<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\InboxConversation;
use App\Models\LeadLabel;
use App\Models\SharedInbox;
use App\Services\Front\FrontApiClient;
use App\Services\Front\FrontTagImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FrontTagImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_tags_from_json_export_and_matches_conversations(): void
    {
        [$company, $sharedInbox, $conversation] = $this->seedInboxConversation(
            subject: 'Storage inquiry',
            fromEmail: 'jane@example.com'
        );

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
                            'tags' => [
                                ['name' => 'Inquiry', 'highlight' => 'blue', 'is_private' => false],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $path = storage_path('framework/testing/front-tags.json');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR));

        $stats = app(FrontTagImportService::class)->importFromFile($company, $path, [
            'inbox_map' => ['inb_sales' => $sharedInbox->id],
        ]);

        $this->assertSame(1, $stats['mapped_inboxes']);
        $this->assertSame(1, $stats['conversations_matched']);
        $this->assertSame(0, $stats['conversations_unmatched']);
        $this->assertSame(1, $stats['tags_created']);
        $this->assertSame(1, $stats['tags_applied']);
        $this->assertSame(1, $stats['lead_labels_applied'] ?? 0);

        $label = LeadLabel::query()->where('company_id', $company->id)->where('name', 'Inquiry')->first();
        $this->assertNotNull($label);
        $this->assertSame('#3b82f6', $label->color);
        $this->assertTrue($conversation->fresh()->leadLabels->contains('id', $label->id));
    }

    public function test_imports_front_tags_as_lead_labels_when_lead_matches(): void
    {
        [$company, $sharedInbox, $conversation] = $this->seedInboxConversation(
            subject: 'Storage inquiry',
            fromEmail: 'jane@example.com'
        );

        $lead = \App\Models\Lead::query()->create([
            'company_id' => $company->id,
            'name' => 'Jane Customer',
            'email' => 'jane@example.com',
            'status' => 'new',
        ]);

        $payload = [
            'inboxes' => [
                [
                    'id' => 'inb_sales',
                    'name' => $sharedInbox->name,
                    'conversations' => [
                        [
                            'subject' => 'Re: Storage inquiry',
                            'recipient' => ['handle' => 'jane@example.com'],
                            'tags' => [
                                ['name' => 'VIP', 'highlight' => 'purple', 'is_private' => false],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $path = storage_path('framework/testing/front-tags-lead.json');
        file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR));

        $stats = app(FrontTagImportService::class)->importFromFile($company, $path, [
            'inbox_map' => ['inb_sales' => $sharedInbox->id],
        ]);

        $this->assertSame(1, $stats['conversations_matched']);
        $this->assertSame(1, $stats['lead_labels_applied'] ?? 0);

        $label = LeadLabel::query()->where('company_id', $company->id)->where('name', 'VIP')->first();
        $this->assertNotNull($label);
        $this->assertTrue($lead->fresh()->labels->contains('id', $label->id));
        $this->assertSame(0, $conversation->fresh()->tags()->count());
        $this->assertSame(0, $conversation->fresh()->leadLabels()->count());
    }

    public function test_dry_run_does_not_persist_tags(): void
    {
        [$company, $sharedInbox, $conversation] = $this->seedInboxConversation(
            subject: 'Move in question',
            fromEmail: 'bob@example.com'
        );

        $payload = [
            'inboxes' => [
                [
                    'id' => 'inb_ops',
                    'name' => $sharedInbox->name,
                    'conversations' => [
                        [
                            'subject' => 'Move in question',
                            'recipient' => ['handle' => 'bob@example.com'],
                            'tags' => [
                                ['name' => 'Move in', 'highlight' => 'green'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $path = storage_path('framework/testing/front-tags-dry-run.json');
        file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR));

        $stats = app(FrontTagImportService::class)->importFromFile($company, $path, [
            'inbox_map' => ['inb_ops' => $sharedInbox->id],
            'dry_run' => true,
        ]);

        $this->assertSame(1, $stats['conversations_matched']);
        $this->assertSame(1, $stats['tags_created']);
        $this->assertSame(0, LeadLabel::query()->count());
        $this->assertSame(0, $conversation->fresh()->leadLabels()->count());
    }

    public function test_conversation_label_graduates_to_lead_once_a_lead_matches(): void
    {
        [$company, $sharedInbox, $conversation] = $this->seedInboxConversation(
            subject: 'Storage inquiry',
            fromEmail: 'jane@example.com'
        );

        $payload = [
            'inboxes' => [
                [
                    'id' => 'inb_sales',
                    'name' => $sharedInbox->name,
                    'conversations' => [
                        [
                            'subject' => 'Re: Storage inquiry',
                            'recipient' => ['handle' => 'jane@example.com'],
                            'tags' => [
                                ['name' => 'VIP', 'highlight' => 'purple', 'is_private' => false],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $path = storage_path('framework/testing/front-tags-graduate.json');
        file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR));

        app(FrontTagImportService::class)->importFromFile($company, $path, [
            'inbox_map' => ['inb_sales' => $sharedInbox->id],
        ]);

        $label = LeadLabel::query()->where('company_id', $company->id)->where('name', 'VIP')->first();
        $this->assertNotNull($label);
        $this->assertTrue($conversation->fresh()->leadLabels->contains('id', $label->id));

        $lead = \App\Models\Lead::query()->create([
            'company_id' => $company->id,
            'name' => 'Jane Customer',
            'email' => 'jane@example.com',
            'status' => 'new',
        ]);

        app(FrontTagImportService::class)->importFromFile($company, $path, [
            'inbox_map' => ['inb_sales' => $sharedInbox->id],
        ]);

        $this->assertTrue($lead->fresh()->labels->contains('id', $label->id));
        $this->assertSame(0, $conversation->fresh()->leadLabels()->count());
    }

    public function test_imports_from_front_api_with_pagination(): void
    {
        [$company, $sharedInbox] = $this->seedInboxConversation(
            subject: 'Pricing request',
            fromEmail: 'pat@example.com'
        );

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
                        'tags' => [
                            ['name' => 'Hot', 'highlight' => 'red', 'is_private' => false],
                        ],
                    ],
                ],
            ]),
        ]);

        config(['services.front.api_token' => 'front-test-token']);

        $stats = app(FrontTagImportService::class)->importFromApi(
            $company,
            FrontApiClient::fromConfig('front-test-token'),
            ['inbox_map' => ['inb_1' => $sharedInbox->id]]
        );

        $this->assertSame(1, $stats['conversations_matched']);
        $this->assertDatabaseHas('lead_labels', [
            'company_id' => $company->id,
            'name' => 'Hot',
            'color' => '#ef4444',
        ]);
    }

    public function test_matches_by_external_conversation_id_when_available(): void
    {
        [$company, $sharedInbox, $conversation] = $this->seedInboxConversation(
            subject: 'Different subject line',
            fromEmail: 'other@example.com',
            externalConversationId: 'AAQkADExample123'
        );

        $service = app(FrontTagImportService::class);
        $matched = $service->matchConversation($sharedInbox, [
            'subject' => 'Totally different',
            'recipient' => ['handle' => 'someone-else@example.com'],
            'metadata' => [
                'external_conversation_ids' => ['AAQkADExample123'],
            ],
        ]);

        $this->assertNotNull($matched);
        $this->assertSame($conversation->id, $matched->id);
    }

    /**
     * @return array{0: Company, 1: SharedInbox, 2: InboxConversation}
     */
    private function seedInboxConversation(
        string $subject,
        string $fromEmail,
        ?string $externalConversationId = null
    ): array {
        $company = Company::query()->create([
            'name' => 'Loc & Stor',
            'subdomain' => 'front-import',
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
            'external_conversation_id' => $externalConversationId,
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
}
