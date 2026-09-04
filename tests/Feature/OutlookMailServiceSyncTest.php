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

        // A full (100-item) page so the service synthesizes a next link and keeps
        // paging — a short page would look like "reached the end" on its own,
        // never even attempting the second (failing) request this test is about.
        $page1 = ['value' => array_map(fn ($i) => $this->messageStub('m'.$i), range(1, 100))];

        Http::fake([
            'graph.microsoft.com/v1.0/me/mailFolders/inbox/messages*' => Http::sequence()
                ->push($page1, 200)
                ->push('server error', 500),
            'graph.microsoft.com/*' => Http::response(['value' => []], 200),
        ]);

        /** @var OutlookMailService $service */
        $service = app(OutlookMailService::class);

        $imported = $service->syncInbox($inbox, 'inbox');

        $this->assertSame(100, $imported);
        $this->assertSame(100, InboxMessage::count());

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

        Http::fake([
            'graph.microsoft.com/v1.0/me/mailFolders/inbox/messages*' => Http::sequence()
                ->push($page1, 200)
                ->push($emptyPage, 200),
            'graph.microsoft.com/*' => Http::response(['value' => []], 200),
        ]);

        /** @var OutlookMailService $service */
        $service = app(OutlookMailService::class);

        $imported = $service->syncInbox($inbox, 'inbox');
        $this->assertSame(1, $imported);

        $inbox->refresh();
        $state = $inbox->folder_sync_state['inbox'] ?? null;
        $this->assertNotNull($state);
        $this->assertTrue($state['backfill_done'], 'Reaching Graph\'s real end must mark the folder backfill-complete.');
        $this->assertNull($state['next_link']);

        // Second run: folder is already fully backfilled, so it must start a fresh
        // newest-first pass from scratch (not resume a stale cursor) and re-fetching
        // the same already-imported message must not count as new.
        Http::fake([
            'graph.microsoft.com/v1.0/me/mailFolders/inbox/messages*' => Http::response($page1, 200),
            'graph.microsoft.com/*' => Http::response(['value' => []], 200),
        ]);

        $imported2 = $service->syncInbox($inbox, 'inbox');
        $this->assertSame(0, $imported2, 'Re-fetching an already-imported message must not count as new.');
        $this->assertSame(1, InboxMessage::count(), 'No duplicate message should have been created.');

        $inbox->refresh();
        $stateAfter = $inbox->folder_sync_state['inbox'] ?? null;
        $this->assertTrue($stateAfter['backfill_done'], 'A completed folder must stay marked backfill-complete.');
        $this->assertNull($stateAfter['next_link']);
    }
}
