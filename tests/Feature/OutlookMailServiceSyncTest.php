<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\InboxMessage;
use App\Models\OutlookMailAccount;
use App\Models\SharedInbox;
use App\Models\User;
use App\Services\OutlookMailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OutlookMailServiceSyncTest extends TestCase
{
    use RefreshDatabase;

    private function makeInbox(): SharedInbox
    {
        $company = Company::create(['name' => 'Acme']);
        $user = User::factory()->create(['company_id' => $company->id]);

        $account = OutlookMailAccount::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'email' => 'shared@example.com',
            'access_token' => 'token',
            'refresh_token' => 'refresh',
            'token_expires_at' => now()->addHour(),
            'is_active' => true,
        ]);

        return SharedInbox::create([
            'company_id' => $company->id,
            'outlook_mail_account_id' => $account->id,
            'name' => 'Shared',
            'email' => 'shared@example.com',
            'type' => SharedInbox::TYPE_SHARED,
            'is_active' => true,
        ]);
    }

    private function messageStub(string $id): array
    {
        return [
            'id' => $id,
            'conversationId' => 'conv-'.$id,
            'subject' => 'Subject '.$id,
            'bodyPreview' => 'Preview '.$id,
            'from' => ['emailAddress' => ['address' => 'a@example.com', 'name' => 'A']],
            'toRecipients' => [],
            'ccRecipients' => [],
            'receivedDateTime' => '2026-01-01T00:00:00Z',
            'isRead' => true,
            'isDraft' => false,
        ];
    }

    public function test_full_sync_resumes_from_persisted_cursor_after_a_failed_page(): void
    {
        $inbox = $this->makeInbox();

        // A full (PAGE_SIZE-item) page so the service synthesizes a next link and
        // keeps paging — a short page would look like "reached the end" on its
        // own, never even attempting the second (failing) request this test is about.
        $page1 = ['value' => array_map(fn ($i) => $this->messageStub('m'.$i), range(1, 25))];

        Http::fake([
            'graph.microsoft.com/v1.0/me/mailFolders/inbox/messages*' => Http::sequence()
                ->push($page1, 200)
                ->push('server error', 500),
            'graph.microsoft.com/*' => Http::response(['value' => []], 200),
        ]);

        /** @var OutlookMailService $service */
        $service = app(OutlookMailService::class);

        $imported = $service->syncInbox($inbox, 'inbox');

        $this->assertSame(25, $imported);
        $this->assertSame(25, InboxMessage::count());

        $inbox->refresh();
        $state = $inbox->folder_sync_state['inbox'] ?? null;
        $this->assertNotNull($state, 'Cursor must be persisted after an interrupted backfill.');
        $this->assertFalse($state['backfill_done'], 'A folder must not be marked backfill-complete after a failed page.');
        $this->assertNotNull($state['next_link'], 'The resume point must be preserved on failure.');
    }

    public function test_full_sync_completes_backfill_and_switches_to_cheap_incremental_mode(): void
    {
        $inbox = $this->makeInbox();

        $page1 = ['value' => [$this->messageStub('m1')]];
        $emptyPage = ['value' => []];
        $deltaLink = 'https://graph.microsoft.com/v1.0/me/mailFolders/inbox/messages/delta?$deltatoken=abc';

        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($page1, $emptyPage, $deltaLink) {
            $url = $request->url();

            if (str_contains($url, '/messages/delta')) {
                return Http::response([
                    'value' => [$this->messageStub('m1')],
                    '@odata.deltaLink' => $deltaLink,
                ], 200);
            }

            if (str_contains($url, '/mailFolders/inbox/messages')) {
                static $listCalls = 0;
                $listCalls++;

                return Http::response($listCalls === 1 ? $page1 : $emptyPage, 200);
            }

            return Http::response(['value' => []], 200);
        });

        /** @var OutlookMailService $service */
        $service = app(OutlookMailService::class);

        $imported = $service->syncInbox($inbox, 'inbox');
        $this->assertSame(1, $imported);

        $inbox->refresh();
        $state = $inbox->folder_sync_state['inbox'] ?? null;
        $this->assertNotNull($state);
        $this->assertTrue($state['backfill_done'], 'Reaching Graph\'s real end must mark the folder backfill-complete.');
        $this->assertNull($state['next_link']);

        // Second run: folder is already fully backfilled, so it must use Graph delta
        // (not resume a stale list cursor) and re-applying an already-imported message
        // must not count as new.
        $imported2 = $service->syncInbox($inbox->fresh(['account']), 'inbox');
        $this->assertSame(0, $imported2, 'Re-fetching an already-imported message must not count as new.');
        $this->assertSame(1, InboxMessage::count(), 'No duplicate message should have been created.');

        $inbox->refresh();
        $stateAfter = $inbox->folder_sync_state['inbox'] ?? null;
        $this->assertTrue($stateAfter['backfill_done'], 'A completed folder must stay marked backfill-complete.');
        $this->assertSame($deltaLink, $stateAfter['delta_link'] ?? null);
    }

    public function test_sync_recent_applies_delta_and_persists_delta_link(): void
    {
        $inbox = $this->makeInbox();
        $inbox->folder_sync_state = [
            'inbox' => [
                'backfill_done' => true,
                'delta_link' => 'https://graph.microsoft.com/v1.0/me/mailFolders/inbox/messages/delta?$deltatoken=inbox-0',
            ],
            'sent' => [
                'backfill_done' => true,
                'delta_link' => 'https://graph.microsoft.com/v1.0/me/mailFolders/sentitems/messages/delta?$deltatoken=sent-0',
            ],
        ];
        $inbox->save();

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $url = $request->url();

            if (str_contains($url, 'deltatoken=inbox-0')) {
                return Http::response([
                    'value' => [$this->messageStub('new-1')],
                    '@odata.deltaLink' => 'https://graph.microsoft.com/v1.0/me/mailFolders/inbox/messages/delta?$deltatoken=inbox-1',
                ], 200);
            }

            if (str_contains($url, 'deltatoken=sent-0')) {
                return Http::response([
                    'value' => [],
                    '@odata.deltaLink' => 'https://graph.microsoft.com/v1.0/me/mailFolders/sentitems/messages/delta?$deltatoken=sent-1',
                ], 200);
            }

            return Http::response(['value' => []], 200);
        });

        /** @var OutlookMailService $service */
        $service = app(OutlookMailService::class);

        $imported = $service->syncRecent($inbox->fresh(['account']));

        $this->assertSame(1, $imported);
        $this->assertSame(1, InboxMessage::count());

        $inbox->refresh();
        $this->assertSame(
            'https://graph.microsoft.com/v1.0/me/mailFolders/inbox/messages/delta?$deltatoken=inbox-1',
            $inbox->folder_sync_state['inbox']['delta_link'] ?? null
        );
        $this->assertSame(
            'https://graph.microsoft.com/v1.0/me/mailFolders/sentitems/messages/delta?$deltatoken=sent-1',
            $inbox->folder_sync_state['sent']['delta_link'] ?? null
        );
    }

    public function test_sync_recent_uses_newest_first_probe_when_backfill_done_but_no_delta_link(): void
    {
        $inbox = $this->makeInbox();
        $inbox->folder_sync_state = [
            'inbox' => ['backfill_done' => true],
            'sent' => ['backfill_done' => true],
        ];
        $inbox->save();

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $url = $request->url();

            if (str_contains($url, '/messages/delta')) {
                return Http::response('delta should not be called from syncRecent without delta_link', 500);
            }

            if (str_contains($url, '/mailFolders/inbox/messages')) {
                return Http::response(['value' => [$this->messageStub('probe-1')]], 200);
            }

            if (str_contains($url, '/mailFolders/sentitems/messages')) {
                return Http::response(['value' => []], 200);
            }

            return Http::response(['value' => []], 200);
        });

        /** @var OutlookMailService $service */
        $service = app(OutlookMailService::class);

        $imported = $service->syncRecent($inbox->fresh(['account']));

        $this->assertSame(1, $imported);
        $this->assertTrue(InboxMessage::query()->where('external_message_id', 'probe-1')->exists());
        $this->assertNull($inbox->fresh()->folder_sync_state['inbox']['delta_link'] ?? null);
    }

    public function test_sync_recent_follows_stored_delta_link_for_incremental_mail(): void
    {
        $inbox = $this->makeInbox();
        $inbox->folder_sync_state = [
            'inbox' => [
                'backfill_done' => true,
                'delta_link' => 'https://graph.microsoft.com/v1.0/me/mailFolders/inbox/messages/delta?$deltatoken=prev',
            ],
            'sent' => [
                'backfill_done' => true,
                'delta_link' => 'https://graph.microsoft.com/v1.0/me/mailFolders/sentitems/messages/delta?$deltatoken=sent-prev',
            ],
        ];
        $inbox->save();

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $url = $request->url();

            if (str_contains($url, 'deltatoken=prev')) {
                return Http::response([
                    'value' => [$this->messageStub('delta-new')],
                    '@odata.deltaLink' => 'https://graph.microsoft.com/v1.0/me/mailFolders/inbox/messages/delta?$deltatoken=next',
                ], 200);
            }

            if (str_contains($url, 'deltatoken=sent-prev')) {
                return Http::response([
                    'value' => [],
                    '@odata.deltaLink' => 'https://graph.microsoft.com/v1.0/me/mailFolders/sentitems/messages/delta?$deltatoken=sent-next',
                ], 200);
            }

            return Http::response(['value' => []], 200);
        });

        /** @var OutlookMailService $service */
        $service = app(OutlookMailService::class);

        $imported = $service->syncRecent($inbox->fresh(['account']));

        $this->assertSame(1, $imported);
        $this->assertTrue(InboxMessage::query()->where('external_message_id', 'delta-new')->exists());

        $inbox->refresh();
        $this->assertSame(
            'https://graph.microsoft.com/v1.0/me/mailFolders/inbox/messages/delta?$deltatoken=next',
            $inbox->folder_sync_state['inbox']['delta_link'] ?? null
        );
    }

    public function test_sync_recent_clears_delta_link_on_410_gone(): void
    {
        $inbox = $this->makeInbox();
        $inbox->folder_sync_state = [
            'inbox' => [
                'backfill_done' => true,
                'delta_link' => 'https://graph.microsoft.com/v1.0/me/mailFolders/inbox/messages/delta?$deltatoken=stale',
            ],
            'sent' => [
                'backfill_done' => true,
                'delta_link' => 'https://graph.microsoft.com/v1.0/me/mailFolders/sentitems/messages/delta?$deltatoken=sent-ok',
            ],
        ];
        $inbox->save();

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $url = $request->url();

            if (str_contains($url, 'deltatoken=stale')) {
                return Http::response('gone', 410);
            }

            if (str_contains($url, 'deltatoken=sent-ok')) {
                return Http::response([
                    'value' => [],
                    '@odata.deltaLink' => 'https://graph.microsoft.com/v1.0/me/mailFolders/sentitems/messages/delta?$deltatoken=sent-ok-2',
                ], 200);
            }

            return Http::response(['value' => []], 200);
        });

        /** @var OutlookMailService $service */
        $service = app(OutlookMailService::class);

        $service->syncRecent($inbox->fresh(['account']));

        $inbox->refresh();
        $this->assertArrayHasKey('inbox', $inbox->folder_sync_state ?? []);
        $this->assertNull($inbox->folder_sync_state['inbox']['delta_link']);
        $this->assertNull($inbox->folder_sync_state['inbox']['delta_next_link']);
        $this->assertSame(
            'https://graph.microsoft.com/v1.0/me/mailFolders/sentitems/messages/delta?$deltatoken=sent-ok-2',
            $inbox->folder_sync_state['sent']['delta_link'] ?? null
        );
    }

    public function test_mailbox_totals_fetches_folder_counts_concurrently(): void
    {
        $inbox = $this->makeInbox();

        Http::fake([
            'graph.microsoft.com/v1.0/me/mailFolders/inbox*' => Http::response(['totalItemCount' => 10], 200),
            'graph.microsoft.com/v1.0/me/mailFolders/drafts*' => Http::response(['totalItemCount' => 2], 200),
            'graph.microsoft.com/v1.0/me/mailFolders/sentitems*' => Http::response(['totalItemCount' => 5], 200),
            'graph.microsoft.com/v1.0/me/mailFolders/deleteditems*' => Http::response(['totalItemCount' => 1], 200),
            'graph.microsoft.com/v1.0/me/mailFolders/junkemail*' => Http::response(['totalItemCount' => 0], 200),
        ]);

        /** @var OutlookMailService $service */
        $service = app(OutlookMailService::class);

        $totals = $service->getMailboxMessageTotals($inbox);

        $this->assertSame(18, $totals['graph_total']);
        $this->assertSame(18, $totals['remaining']);
        $this->assertSame([], $totals['folders_failed']);
        $this->assertSame(10, $totals['folders']['inbox']);

        // Every folder's count is fetched in its own pooled request rather than one
        // request per folder waited on in turn.
        Http::assertSentCount(5);
    }

    public function test_mailbox_totals_reports_a_persistently_failing_folder_without_blocking_the_others(): void
    {
        $inbox = $this->makeInbox();

        Http::fake([
            // Drafts fails every attempt (both the initial round and the retry round).
            'graph.microsoft.com/v1.0/me/mailFolders/drafts*' => Http::response('server error', 500),
            'graph.microsoft.com/v1.0/me/mailFolders/*' => Http::response(['totalItemCount' => 7], 200),
        ]);

        /** @var OutlookMailService $service */
        $service = app(OutlookMailService::class);

        $totals = $service->getMailboxMessageTotals($inbox);

        $this->assertSame(['drafts'], $totals['folders_failed']);
        // The failing folder must not be silently counted as "0 remaining" — the
        // other four folders' real counts still come through untouched.
        $this->assertSame(0, $totals['folders']['drafts']);
        $this->assertSame(7, $totals['folders']['inbox']);
        $this->assertSame(28, $totals['graph_total']);

        // One initial round (5 requests) + one retry round for just the failing
        // folder (1 request) = 6 total, not 5 x (1 + retries) = 15.
        Http::assertSentCount(6);
    }

    public function test_stale_next_link_for_another_mailbox_is_dropped(): void
    {
        $inbox = $this->makeInbox();
        $account = $inbox->account;

        Http::fake();

        /** @var OutlookMailService $service */
        $service = app(OutlookMailService::class);

        $result = $service->syncFolderPage(
            $inbox,
            $account,
            'inbox',
            OutlookMailService::FOLDERS['inbox'],
            'https://graph.microsoft.com/v1.0/users/team%40example.com/mailFolders/inbox/messages?$skip=25',
            25,
            false
        );

        $this->assertTrue($result['done']);
        $this->assertFalse($result['failed']);
        $this->assertNull($result['next_link']);
        $this->assertSame(0, $result['imported']);
        Http::assertNothingSent();
    }

    public function test_personal_inbox_with_external_mailbox_is_unlinked_on_repair(): void
    {
        $company = Company::create(['name' => 'Acme']);
        $user = User::factory()->create(['company_id' => $company->id]);

        $account = OutlookMailAccount::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'email' => 'alice@example.com',
            'access_token' => 'token',
            'refresh_token' => 'refresh',
            'token_expires_at' => now()->addHour(),
            'is_active' => true,
        ]);

        $inbox = SharedInbox::create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'outlook_mail_account_id' => $account->id,
            'name' => 'Personal',
            'email' => 'alice@example.com',
            'external_mailbox' => 'team@example.com',
            'type' => SharedInbox::TYPE_PERSONAL,
            'is_active' => true,
            'folder_sync_state' => [
                'inbox' => [
                    'next_link' => 'https://graph.microsoft.com/v1.0/users/team@example.com/mailFolders/inbox/messages',
                    'fetched' => 50,
                    'backfill_done' => false,
                ],
            ],
        ]);

        /** @var OutlookMailService $service */
        $service = app(OutlookMailService::class);

        $this->assertFalse($service->repairInboxBinding($inbox->fresh(['account'])));

        $inbox->refresh();
        $this->assertNull($inbox->outlook_mail_account_id);
        $this->assertNull($inbox->folder_sync_state);
    }
}
