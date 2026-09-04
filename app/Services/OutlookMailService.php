<?php

namespace App\Services;

use App\Models\InboxConversation;
use App\Models\InboxConversationUserRead;
use App\Models\InboxMailFolder;
use App\Models\InboxMessage;
use App\Models\OutlookMailAccount;
use App\Models\SharedInbox;
use App\Notifications\InboxMessageNotification;
use App\Support\EmailQuotedHistory;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OutlookMailService
{
    private const GRAPH_BASE = 'https://graph.microsoft.com/v1.0';

    /**
     * Defaults for the handful of well-known folders that carry special CRM meaning.
     * Every other folder Graph reports (custom folders, nested subfolders, and any
     * other well-known folder) is still discovered and synced — see discoverFolders() —
     * just without one of these special status/direction defaults.
     *
     * @var array<string, array{graph: string, status: string, direction: string}>
     */
    public const FOLDERS = [
        'inbox' => ['graph' => 'inbox', 'status' => 'open', 'direction' => 'inbound'],
        'drafts' => ['graph' => 'drafts', 'status' => 'drafts', 'direction' => 'outbound'],
        'sent' => ['graph' => 'sentitems', 'status' => 'sent', 'direction' => 'outbound'],
        'trash' => ['graph' => 'deleteditems', 'status' => 'trashed', 'direction' => 'inbound'],
        'spam' => ['graph' => 'junkemail', 'status' => 'spam', 'direction' => 'inbound'],
        'archive' => ['graph' => 'archive', 'status' => 'open', 'direction' => 'inbound'],
    ];

    /**
     * Graph wellKnownName values that are diagnostic/transient, not real correspondence —
     * never synced even though discoverFolders() would otherwise pick them up.
     */
    private const SKIP_WELL_KNOWN = ['outbox', 'syncissues', 'deletedsearchresults', 'conversationhistory'];

    /** Max folders / discovery-loop iterations, guarding against pathological mailboxes. */
    private const MAX_DISCOVERED_FOLDERS = 500;

    public function __construct(
        protected CalendarOauthSettingsService $oauthSettings,
        protected ChannelUnreadNotifier $unreadNotifier,
        protected LeadAutoCreateService $leadAutoCreate
    ) {}

    /**
     * @return array{client_id: string|null, client_secret: string|null, redirect: string}
     */
    public function getMailCredentials(?int $companyId): array
    {
        $creds = $this->oauthSettings->getCredentials('outlook', $companyId);
        $creds['redirect'] = rtrim(config('app.url', 'http://localhost:8000'), '/').'/inbox/connect/outlook/callback';

        return $creds;
    }

    public function refreshTokenIfNeeded(OutlookMailAccount $account): OutlookMailAccount
    {
        if (! $account->needsRefresh() || ! $account->refresh_token) {
            return $account;
        }

        $creds = $this->getMailCredentials($account->company_id);
        $tenant = $this->oauthSettings->getMicrosoftTenant($account->company_id);

        $response = Http::asForm()->post(
            "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token",
            [
                'client_id' => $creds['client_id'],
                'client_secret' => $creds['client_secret'],
                'refresh_token' => $account->refresh_token,
                'grant_type' => 'refresh_token',
                'scope' => 'openid profile email User.Read Mail.ReadWrite Mail.Send Mail.ReadWrite.Shared offline_access',
            ]
        );

        if (! $response->successful()) {
            Log::warning('Outlook mail token refresh failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return $account;
        }

        $data = $response->json();
        $account->access_token = $data['access_token'];
        $account->token_expires_at = now()->addSeconds($data['expires_in'] ?? 3600);
        if (! empty($data['refresh_token'])) {
            $account->refresh_token = $data['refresh_token'];
        }
        $account->save();

        return $account;
    }

    public function syncInbox(SharedInbox $inbox, ?string $onlyFolder = null): int
    {
        $account = $inbox->account;
        if (! $account || ! $account->is_active) {
            return 0;
        }

        // Never import another mailbox's mail into this inbox.
        if (! $this->assertAccountMatchesInbox($inbox, $account)) {
            $this->clearInboxConversations($inbox);
            $inbox->outlook_mail_account_id = null;
            $inbox->save();

            return 0;
        }

        $account = $this->refreshTokenIfNeeded($account);
        $imported = 0;

        $folders = $this->discoverFolders($inbox);
        if ($folders->isEmpty()) {
            // Discovery couldn't reach Graph at all — e.g. a transient error on a
            // brand-new inbox that has never been discovered before. Fall back to the
            // well-known folders directly rather than skipping the sync entirely.
            $folders = collect(self::FOLDERS)->map(fn ($meta) => (object) [
                'graph_folder_id' => $meta['graph'],
                'status_default' => $meta['status'],
                'direction_default' => $meta['direction'],
            ]);
        }
        if ($onlyFolder !== null) {
            $folders = $folders->has($onlyFolder) ? $folders->only([$onlyFolder]) : collect();
        }

        foreach ($folders as $localKey => $folderRow) {
            $meta = [
                'graph' => $folderRow->graph_folder_id,
                'status' => $folderRow->status_default,
                'direction' => $folderRow->direction_default,
            ];
            $nextLink = null;
            $fetched = 0;
            do {
                $page = $this->syncFolderPage($inbox, $account, $localKey, $meta, $nextLink, $fetched);
                $imported += $page['imported'];
                $fetched += $page['fetched'];
                $nextLink = $page['next_link'];
                $account = $inbox->account()->first() ?: $account;
            } while ($nextLink);
        }

        $inbox->last_synced_at = now();
        $inbox->save();

        return $imported;
    }

    /**
     * Discover every mail folder in this mailbox — well-known folders, custom
     * top-level folders, and nested subfolders — and cache the result in
     * inbox_mail_folders. Skips a small denylist of diagnostic/transient system
     * folders (see SKIP_WELL_KNOWN). Returns the cached rows keyed by local_key.
     *
     * @return Collection<string, InboxMailFolder>
     */
    public function discoverFolders(SharedInbox $inbox, bool $forceRefresh = false): Collection
    {
        $cached = InboxMailFolder::where('shared_inbox_id', $inbox->id)->get()->keyBy('local_key');

        // Re-discover at most once an hour on the automatic path (the --full cron runs
        // every 15 minutes) so newly created custom folders surface on their own —
        // without this, a mailbox discovered once would never be re-crawled by syncInbox().
        if (! $forceRefresh && $cached->isNotEmpty()) {
            $oldest = $cached->pluck('last_synced_at')->filter()->min();
            if ($oldest && $oldest->gt(now()->subHour())) {
                return $cached;
            }
        }

        $account = $inbox->account;
        if (! $account || ! $account->is_active) {
            return $cached;
        }

        $account = $this->refreshTokenIfNeeded($account);
        $mailboxPath = $this->mailboxPath($inbox);

        // Graph's wellKnownName for each folder we already give special CRM meaning to.
        $wellKnownLocalKeys = [];
        foreach (self::FOLDERS as $localKey => $meta) {
            $wellKnownLocalKeys[$meta['graph']] = $localKey;
        }

        $byGraphId = [];
        $queue = [null]; // null = mailbox root
        $visited = [];
        $hadErrors = false;
        $guard = 0;

        while ($queue !== [] && count($byGraphId) < self::MAX_DISCOVERED_FOLDERS && $guard < self::MAX_DISCOVERED_FOLDERS * 2) {
            $guard++;
            $parentId = array_shift($queue);
            if ($parentId !== null) {
                if (isset($visited[$parentId])) {
                    continue;
                }
                $visited[$parentId] = true;
            }

            $url = $parentId === null
                ? self::GRAPH_BASE."/{$mailboxPath}/mailFolders"
                : self::GRAPH_BASE."/{$mailboxPath}/mailFolders/{$parentId}/childFolders";

            $nextUrl = $url.'?'.http_build_query([
                '$top' => 250,
                '$select' => 'id,displayName,parentFolderId,childFolderCount,totalItemCount,wellKnownName',
            ]);

            while ($nextUrl && count($byGraphId) < self::MAX_DISCOVERED_FOLDERS) {
                $response = Http::withToken($account->access_token)->timeout(30)->get($nextUrl);
                if (! $response->successful()) {
                    Log::warning('Outlook mail folder discovery failed', [
                        'inbox_id' => $inbox->id,
                        'parent_id' => $parentId,
                        'status' => $response->status(),
                    ]);
                    $hadErrors = true;

                    break;
                }

                $payload = $response->json() ?: [];
                $batch = $payload['value'] ?? [];
                foreach ($batch as $f) {
                    $graphId = (string) ($f['id'] ?? '');
                    if ($graphId === '' || isset($byGraphId[$graphId])) {
                        continue;
                    }

                    $wellKnown = strtolower((string) ($f['wellKnownName'] ?? ''));
                    if (in_array($wellKnown, self::SKIP_WELL_KNOWN, true)) {
                        continue;
                    }

                    $byGraphId[$graphId] = $f;
                    if ((int) ($f['childFolderCount'] ?? 0) > 0) {
                        $queue[] = $graphId;
                    }
                }

                $next = $payload['@odata.nextLink'] ?? null;
                $nextUrl = is_string($next) && $next !== '' ? $next : null;
            }
        }

        if ($byGraphId === []) {
            // Discovery failed outright (token/permission issue) — keep whatever was cached.
            return $cached;
        }

        $localKeyByGraphId = [];
        foreach ($byGraphId as $graphId => $f) {
            $wellKnown = strtolower((string) ($f['wellKnownName'] ?? ''));
            $localKeyByGraphId[$graphId] = $wellKnownLocalKeys[$wellKnown]
                ?? $cached->firstWhere('graph_folder_id', $graphId)?->local_key
                ?? ('cf_'.substr(md5($graphId), 0, 12));
        }

        $seenLocalKeys = [];
        foreach ($byGraphId as $graphId => $f) {
            $localKey = $localKeyByGraphId[$graphId];
            $meta = self::FOLDERS[$localKey] ?? ['status' => 'open', 'direction' => 'inbound'];
            $parentGraphId = (string) ($f['parentFolderId'] ?? '');

            // Guard the (shared_inbox_id, graph_folder_id) unique index against the rare
            // case where this folder's computed local_key changed since the last crawl.
            $staleRow = $cached->firstWhere('graph_folder_id', $graphId);
            if ($staleRow && $staleRow->local_key !== $localKey) {
                $staleRow->delete();
            }

            InboxMailFolder::updateOrCreate(
                ['shared_inbox_id' => $inbox->id, 'local_key' => $localKey],
                [
                    'graph_folder_id' => $graphId,
                    'display_name' => (string) ($f['displayName'] ?? $localKey),
                    'parent_local_key' => $localKeyByGraphId[$parentGraphId] ?? null,
                    'well_known_name' => ($f['wellKnownName'] ?? null) ?: null,
                    'status_default' => $meta['status'],
                    'direction_default' => $meta['direction'],
                    'graph_total_count' => (int) ($f['totalItemCount'] ?? 0),
                    'last_synced_at' => now(),
                ]
            );
            $seenLocalKeys[] = $localKey;
        }

        // Only prune folders Graph no longer reports when the crawl completed cleanly —
        // a partial/errored crawl must not look like folders were deleted.
        if (! $hadErrors) {
            InboxMailFolder::where('shared_inbox_id', $inbox->id)
                ->whereNotIn('local_key', $seenLocalKeys)
                ->delete();
        }

        return InboxMailFolder::where('shared_inbox_id', $inbox->id)->get()->keyBy('local_key');
    }

    /**
     * Lightweight newest-first probe for background / scheduled sync.
     * Mirrors the inbox UI auto-sync (Inbox + Sent only, few pages, early stop).
     */
    public function syncRecent(SharedInbox $inbox): int
    {
        $account = $inbox->account;
        if (! $account || ! $account->is_active) {
            return 0;
        }

        if (! $this->assertAccountMatchesInbox($inbox, $account)) {
            $this->clearInboxConversations($inbox);
            $inbox->outlook_mail_account_id = null;
            $inbox->save();

            return 0;
        }

        $account = $this->refreshTokenIfNeeded($account);
        $imported = 0;

        // Cheap path: resolve Inbox/Sent from the folder cache without a full discovery
        // crawl, falling back to the well-known folder name for a brand-new inbox that
        // has never been discovered yet (Graph accepts well-known names as folder ids).
        $cachedFolders = InboxMailFolder::where('shared_inbox_id', $inbox->id)
            ->whereIn('local_key', ['inbox', 'sent'])
            ->get()
            ->keyBy('local_key');

        // Same probe targets as the /inbox auto-sync (quiet + recentOnly).
        $probes = [
            'inbox' => 2,
            'sent' => 1,
        ];

        foreach ($probes as $folder => $maxPages) {
            $folderRow = $cachedFolders->get($folder);
            $meta = $folderRow
                ? ['graph' => $folderRow->graph_folder_id, 'status' => $folderRow->status_default, 'direction' => $folderRow->direction_default]
                : (self::FOLDERS[$folder] ?? null);
            if (! $meta) {
                continue;
            }

            $nextLink = null;
            $fetched = 0;

            for ($page = 0; $page < $maxPages; $page++) {
                $result = $this->syncFolderPage($inbox, $account, $folder, $meta, $nextLink, $fetched);
                $imported += $result['imported'];
                $fetched += $result['fetched'];
                $account = $inbox->account()->first() ?: $account;

                // First page all already synced → recent mail is current.
                if ($result['imported'] === 0 && $result['fetched'] > 0 && $page === 0) {
                    break;
                }

                if ($result['done'] || ($result['caught_up'] ?? false) || ! $result['next_link']) {
                    break;
                }

                $nextLink = $result['next_link'];
            }
        }

        $inbox->last_synced_at = now();
        $inbox->save();

        return $imported;
    }

    /**
     * Graph vs local message counts for this mailbox.
     * `remaining` = emails not yet in CRM (used for sync progress).
     *
     * @return array{
     *   total: int,
     *   graph_total: int,
     *   already_synced: int,
     *   remaining: int,
     *   folders: array<string, int>,
     *   folders_synced: array<string, int>,
     *   folders_remaining: array<string, int>,
     *   folder_labels: array<string, string>
     * }
     */
    public function getMailboxMessageTotals(SharedInbox $inbox): array
    {
        $account = $inbox->account;

        if (! $account || ! $account->is_active || ! $this->assertAccountMatchesInbox($inbox, $account)) {
            return [
                'total' => 0,
                'graph_total' => 0,
                'already_synced' => 0,
                'remaining' => 0,
                'folders' => [],
                'folders_synced' => [],
                'folders_remaining' => [],
                'folder_labels' => [],
            ];
        }

        $account = $this->refreshTokenIfNeeded($account);

        // Discovery itself fetches totalItemCount for every folder in the crawl, so
        // folder-level Graph counts come for free — no extra per-folder API calls needed.
        $discovered = $this->discoverFolders($inbox, forceRefresh: true);

        $folders = [];
        $foldersSynced = [];
        $foldersRemaining = [];
        $folderLabels = [];
        $graphTotal = 0;
        $alreadySynced = 0;
        $remaining = 0;

        foreach ($discovered as $localKey => $folderRow) {
            $localCount = (int) InboxMessage::query()
                ->whereHas('conversation', function ($q) use ($inbox, $localKey) {
                    $q->where('shared_inbox_id', $inbox->id)
                        ->where('folder', $localKey);
                })
                ->count();

            $foldersSynced[$localKey] = $localCount;
            $alreadySynced += $localCount;
            $folderLabels[$localKey] = $folderRow->display_name;

            $graphCount = (int) $folderRow->graph_total_count;
            $folders[$localKey] = $graphCount;
            $graphTotal += $graphCount;

            $folderRemaining = max(0, $graphCount - $localCount);
            $foldersRemaining[$localKey] = $folderRemaining;
            $remaining += $folderRemaining;
        }

        return [
            'total' => $remaining,
            'graph_total' => $graphTotal,
            'already_synced' => $alreadySynced,
            'remaining' => $remaining,
            'folders' => $folders,
            'folders_synced' => $foldersSynced,
            'folders_remaining' => $foldersRemaining,
            'folder_labels' => $folderLabels,
        ];
    }

    /**
     * Sync a single Graph page for a folder (used by progress UI).
     *
     * @param  array{graph: string, status: string, direction: string}  $meta
     * @return array{imported: int, fetched: int, next_link: ?string, done: bool}
     */
    public function syncFolderPage(
        SharedInbox $inbox,
        OutlookMailAccount $account,
        string $folder,
        array $meta,
        ?string $nextLink = null,
        int $fetchedSoFar = 0
    ): array {
        $mailboxPath = $this->mailboxPath($inbox);
        // Newest first so incremental sync picks up new mail immediately.
        // (Oldest-first + early-stop on count delta left new messages stuck behind
        // tens of thousands of already-synced pages.)
        $orderField = $folder === 'drafts' ? 'lastModifiedDateTime' : 'receivedDateTime';
        $select = 'id,conversationId,subject,bodyPreview,from,toRecipients,ccRecipients,replyTo,receivedDateTime,sentDateTime,lastModifiedDateTime,isRead,isDraft';

        $account = $this->refreshTokenIfNeeded($account);

        $request = Http::withToken($account->access_token)
            ->timeout(90)
            ->withHeaders(['Prefer' => 'odata.maxpagesize=100']);

        if ($nextLink) {
            if (! str_starts_with($nextLink, self::GRAPH_BASE.'/')) {
                return ['imported' => 0, 'fetched' => 0, 'skipped' => 0, 'next_link' => null, 'done' => true];
            }
            $response = $request->get($nextLink);
        } else {
            $response = $request->get(self::GRAPH_BASE."/{$mailboxPath}/mailFolders/{$meta['graph']}/messages", [
                '$top' => 100,
                '$orderby' => $orderField.' desc',
                '$select' => $select,
            ]);
        }

        if (! $response->successful()) {
            Log::warning('Outlook mail folder page sync failed', [
                'inbox_id' => $inbox->id,
                'folder' => $folder,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
                'fetched_so_far' => $fetchedSoFar,
            ]);

            return ['imported' => 0, 'fetched' => 0, 'skipped' => 0, 'next_link' => null, 'done' => true];
        }

        $payload = $response->json() ?: [];
        $batch = $payload['value'] ?? [];
        if (! is_array($batch) || $batch === []) {
            return ['imported' => 0, 'fetched' => 0, 'skipped' => 0, 'next_link' => null, 'done' => true];
        }

        $imported = 0;
        foreach ($batch as $msg) {
            if ($this->upsertMessage($inbox, $msg, $folder, $meta['status'], $meta['direction'])) {
                $imported++;
            }
        }

        // Do not use $response->json('@odata.nextLink') — data_get breaks on the dot.
        $next = $payload['@odata.nextLink'] ?? null;
        $next = is_string($next) && $next !== '' ? $next : null;

        if (! $next && count($batch) >= 100) {
            $next = self::GRAPH_BASE."/{$mailboxPath}/mailFolders/{$meta['graph']}/messages?".http_build_query([
                '$top' => 100,
                '$skip' => $fetchedSoFar + count($batch),
                '$orderby' => $orderField.' desc',
                '$select' => $select,
            ]);
        }

        $skipped = count($batch) - $imported;
        // Newest-first: after we've already walked past newer mail in this run,
        // a full page of already-synced messages means we hit the previously
        // imported window — stop instead of scanning the whole mailbox.
        // (Do not short-circuit on the first page so a cancelled initial sync can
        // still continue into older unsynced pages.)
        $caughtUp = $imported === 0 && count($batch) > 0 && $fetchedSoFar > 0;
        if ($caughtUp) {
            $next = null;
        }

        Log::info('Outlook mail folder sync page', [
            'inbox_id' => $inbox->id,
            'folder' => $folder,
            'batch' => count($batch),
            'imported' => $imported,
            'fetched_so_far' => $fetchedSoFar + count($batch),
            'has_next' => (bool) $next,
            'caught_up' => $caughtUp,
        ]);

        return [
            'imported' => $imported,
            'fetched' => count($batch),
            'skipped' => $skipped,
            'next_link' => $next,
            'done' => $next === null,
            'caught_up' => $caughtUp,
        ];
    }

    /**
     * @param  array<string, mixed>  $msg
     */
    public function upsertMessage(
        SharedInbox $inbox,
        array $msg,
        string $folder = 'inbox',
        string $status = 'open',
        string $direction = 'inbound'
    ): bool {
        $from = $msg['from']['emailAddress'] ?? [];
        $fromEmail = $this->truncate($from['address'] ?? null, 320);
        $fromName = $this->truncate($from['name'] ?? $fromEmail, 500);
        $subject = $this->truncate($msg['subject'] ?? null, 998) ?: '(No subject)';
        $conversationId = $this->truncate($msg['conversationId'] ?? $msg['id'] ?? null, 512);
        if (! $conversationId) {
            return false;
        }

        $receivedAt = $this->parseGraphDateTime(
            $msg['receivedDateTime'] ?? $msg['sentDateTime'] ?? $msg['lastModifiedDateTime'] ?? null
        );

        $isNew = false;
        $conversation = InboxConversation::firstOrNew([
            'shared_inbox_id' => $inbox->id,
            'folder' => $folder,
            'external_conversation_id' => $conversationId,
        ]);

        if (! $conversation->exists) {
            $isNew = true;
            $conversation->company_id = $inbox->company_id;
            $conversation->assigned_to = null;
            $conversation->is_read = (bool) ($msg['isRead'] ?? ($folder !== 'inbox'));
        }

        $writingToMergeTarget = false;
        $sourceConversationId = null;
        if (! $isNew && $conversation->merged_into_id) {
            $mergeRoot = $conversation->mergeRoot();
            if ((int) $mergeRoot->id !== (int) $conversation->id) {
                $sourceConversationId = (int) $conversation->id;
                $conversation = $mergeRoot;
                $writingToMergeTarget = true;
            }
        }

        if (! $writingToMergeTarget) {
            // Don't overwrite local archive/workflow moves back from sync for inbox threads
            if ($isNew || ! in_array($conversation->status, ['archived'], true) || $folder !== 'inbox') {
                $conversation->status = $status;
            }

            $conversation->folder = $folder;
            $conversation->subject = $subject;
            $conversation->snippet = EmailQuotedHistory::snippet(null, $msg['bodyPreview'] ?? '');
            $conversation->from_name = $fromName;
            $conversation->from_email = $fromEmail;
        } else {
            $conversation->snippet = EmailQuotedHistory::snippet(null, $msg['bodyPreview'] ?? '') ?: $conversation->snippet;
        }

        if (! $conversation->last_message_at || $receivedAt->gt($conversation->last_message_at)) {
            $conversation->last_message_at = $receivedAt;
            if ($folder === 'inbox') {
                $conversation->is_read = (bool) ($msg['isRead'] ?? false);
            }
        }

        $conversation->save();

        $externalMessageId = $this->truncate($msg['id'] ?? null, 512);
        if ($externalMessageId) {
            $messageHome = $this->messageHomeConversation($inbox, $conversation, $folder, $conversationId);
            $existing = InboxMessage::where('inbox_conversation_id', $messageHome->id)
                ->where('external_message_id', $externalMessageId)
                ->first();

            if (! $existing && (int) $messageHome->id !== (int) $conversation->id) {
                $existing = InboxMessage::where('inbox_conversation_id', $conversation->id)
                    ->where('external_message_id', $externalMessageId)
                    ->first();
            }

            if ($existing) {
                // Correct timezone drift from earlier syncs (Graph UTC stored as local wall-clock).
                if (! $existing->sent_at || ! $existing->sent_at->equalTo($receivedAt)) {
                    $existing->sent_at = $receivedAt;
                }
                if ($existing->reply_to_emails === null && array_key_exists('replyTo', $msg)) {
                    $existing->reply_to_emails = $this->graphRecipientAddresses($msg['replyTo'] ?? []) ?: '';
                }
                if (array_key_exists('isDraft', $msg)) {
                    $existing->is_draft = (bool) $msg['isDraft'];
                }
                if ($existing->isDirty()) {
                    $existing->save();
                }

                return false;
            }

            $toEmails = $this->graphRecipientAddresses($msg['toRecipients'] ?? []);
            $ccEmails = $this->graphRecipientAddresses($msg['ccRecipients'] ?? []);
            $replyToEmails = array_key_exists('replyTo', $msg)
                ? ($this->graphRecipientAddresses($msg['replyTo'] ?? []) ?: '')
                : null;

            $body = $msg['body'] ?? [];
            $contentType = strtolower($body['contentType'] ?? 'text');
            // List sync uses bodyPreview only; full body is hydrated when opening a thread.
            $content = $body['content'] ?? ($msg['bodyPreview'] ?? '');
            $safeHtml = ($contentType === 'html' && ! empty($body['content']))
                ? preg_replace('#<script\b[^>]*>(.*?)</script>#is', '', $content)
                : null;
            $bodyText = strip_tags($content);

            // For sent/drafts without from, use mailbox email
            if (! $fromEmail && in_array($folder, ['sent', 'drafts'], true)) {
                $fromEmail = $this->truncate($inbox->email ?? $inbox->account?->email, 320);
                $fromName = $fromName ?: $fromEmail;
            }

            $localOutbound = $this->matchingLocalOutbound($messageHome->id, $receivedAt);
            if ($localOutbound) {
                $localOutbound->external_message_id = $externalMessageId;
                if ($toEmails) {
                    $localOutbound->to_emails = $toEmails;
                }
                if ($ccEmails) {
                    $localOutbound->cc_emails = $ccEmails;
                }
                if ($replyToEmails !== null) {
                    $localOutbound->reply_to_emails = $replyToEmails;
                }
                if (blank($localOutbound->body_html) && $safeHtml) {
                    $localOutbound->body_html = $safeHtml;
                    $localOutbound->body_text = $bodyText;
                }
                if (! $localOutbound->sent_at || ! $localOutbound->sent_at->equalTo($receivedAt)) {
                    $localOutbound->sent_at = $receivedAt;
                }
                $localOutbound->save();

                return false;
            }

            InboxMessage::create([
                'inbox_conversation_id' => $messageHome->id,
                'source_conversation_id' => $sourceConversationId,
                'external_message_id' => $externalMessageId,
                'direction' => ($msg['isDraft'] ?? false) ? 'outbound' : $direction,
                'is_draft' => (bool) ($msg['isDraft'] ?? false),
                'from_name' => $fromName,
                'from_email' => $fromEmail,
                'to_emails' => $toEmails ?: null,
                'cc_emails' => $ccEmails ?: null,
                'reply_to_emails' => $replyToEmails,
                'subject' => $subject === '(No subject)' ? null : $subject,
                'body_html' => $safeHtml,
                'body_text' => $bodyText,
                'is_read' => (bool) ($msg['isRead'] ?? true),
                'sent_at' => $receivedAt,
            ]);

            $conversation->message_count = $conversation->messages()->count();
            $conversation->save();
            if ((int) $messageHome->id !== (int) $conversation->id) {
                if (! $messageHome->last_message_at || $receivedAt->gt($messageHome->last_message_at)) {
                    $messageHome->last_message_at = $receivedAt;
                    $messageHome->snippet = EmailQuotedHistory::snippet($safeHtml, $bodyText ?: ($msg['bodyPreview'] ?? ''));
                }
                $messageHome->message_count = $messageHome->messages()->count();
                $messageHome->save();
            }

            $messageDirection = ($msg['isDraft'] ?? false) ? 'outbound' : $direction;
            if ($folder === 'inbox' && $messageDirection === 'inbound' && $inbox->type === SharedInbox::TYPE_SHARED) {
                // New inbound mail makes the thread unread again for every shared member.
                InboxConversationUserRead::query()
                    ->where('inbox_conversation_id', $conversation->id)
                    ->where('is_read', true)
                    ->update(['is_read' => false]);
            }
            if ($folder === 'inbox' && $messageDirection === 'inbound') {
                $fresh = $conversation->fresh(['inbox']);
                if ($fresh) {
                    $this->leadAutoCreate->applyRules(
                        $this->leadAutoCreate->fromInboxConversation($fresh),
                        'inbox',
                        LeadRuleEngine::inboundTriggers($isNew),
                        [
                            'company_id' => (int) $fresh->company_id,
                            'contact_name' => $fresh->from_name,
                            'email' => $fresh->from_email,
                            'subject' => $fresh->subject,
                            'message' => $bodyText ?: $fresh->snippet,
                            'inbox_id' => $fresh->shared_inbox_id,
                            'shared_inbox_id' => $fresh->shared_inbox_id,
                            'inbox_conversation_id' => $fresh->id,
                        ]
                    );
                }

                if (! $conversation->is_read) {
                    $fresh = $conversation->fresh(['inbox']);
                    if ($fresh) {
                        $this->unreadNotifier->notifyUsers(
                            $this->unreadNotifier->inboxRecipients($fresh),
                            InboxMessageNotification::class,
                            (int) $fresh->id,
                            new InboxMessageNotification($fresh)
                        );
                    }
                }
            }

            return true;
        }

        return $isNew;
    }

    private function messageHomeConversation(
        SharedInbox $inbox,
        InboxConversation $folderConversation,
        string $folder,
        string $graphConversationId
    ): InboxConversation {
        if (! in_array($folder, ['sent', 'drafts'], true) || $graphConversationId === '') {
            return $folderConversation;
        }

        $inboxThread = InboxConversation::query()
            ->where('shared_inbox_id', $inbox->id)
            ->where('folder', 'inbox')
            ->where('external_conversation_id', $graphConversationId)
            ->whereNull('merged_into_id')
            ->first();

        return $inboxThread ?: $folderConversation;
    }

    private function matchingLocalOutbound(int $conversationId, Carbon $receivedAt): ?InboxMessage
    {
        return InboxMessage::query()
            ->where('inbox_conversation_id', $conversationId)
            ->where('direction', 'outbound')
            ->where('external_message_id', 'like', 'local-%')
            ->whereBetween('sent_at', [
                $receivedAt->copy()->subMinutes(15),
                $receivedAt->copy()->addMinutes(15),
            ])
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Graph returns UTC timestamps (…Z). Persist in the app timezone so UI dates match local time.
     */
    private function parseGraphDateTime(mixed $value): Carbon
    {
        if ($value === null || $value === '') {
            return now();
        }

        return Carbon::parse($value)->timezone(config('app.timezone'));
    }

    /**
     * Move a message to another Outlook well-known folder (best-effort).
     */
    public function moveConversationToFolder(SharedInbox $inbox, InboxConversation $conversation, string $folder): bool
    {
        if (! isset(self::FOLDERS[$folder])) {
            return false;
        }

        $account = $inbox->account;
        if (! $account) {
            return false;
        }

        $account = $this->refreshTokenIfNeeded($account);
        $mailboxPath = $this->mailboxPath($inbox);
        $folderRow = InboxMailFolder::where('shared_inbox_id', $inbox->id)->where('local_key', $folder)->first();
        $destination = $folderRow?->graph_folder_id ?? self::FOLDERS[$folder]['graph'];

        $messageIds = $conversation->messages()
            ->whereNotNull('external_message_id')
            ->where('external_message_id', 'not like', 'local-%')
            ->pluck('external_message_id');

        $ok = true;
        foreach ($messageIds as $messageId) {
            $response = Http::withToken($account->access_token)
                ->post(self::GRAPH_BASE."/{$mailboxPath}/messages/{$messageId}/move", [
                    'destinationId' => $destination,
                ]);

            if (! $response->successful()) {
                Log::warning('Outlook move message failed', [
                    'message_id' => $messageId,
                    'folder' => $folder,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                $ok = false;
            }
        }

        return $ok;
    }

    /**
     * Fetch full HTML bodies from Graph for messages that only have a preview.
     */
    public function hydrateConversationBodies(SharedInbox $inbox, InboxConversation $conversation): void
    {
        $account = $inbox->account;
        if (! $account) {
            return;
        }

        $account = $this->refreshTokenIfNeeded($account);
        $mailboxPath = $this->mailboxPath($inbox->loadMissing('account'));

        $messages = $conversation->messages()
            ->whereNotNull('external_message_id')
            ->where('external_message_id', 'not like', 'local-%')
            ->where(function ($q) {
                $q->whereNull('body_html')
                    ->orWhere('body_html', '')
                    ->orWhere(function ($inner) {
                        $inner->whereNull('reply_to_emails')
                            ->where('direction', 'inbound');
                    });
            })
            ->get();

        foreach ($messages as $message) {
            $needsBody = blank($message->body_html);
            $needsReplyTo = $message->reply_to_emails === null;
            $messageId = rawurlencode((string) $message->external_message_id);
            $response = Http::withToken($account->access_token)
                ->timeout(60)
                ->withHeaders([
                    'Prefer' => 'outlook.body-content-type="html"',
                ])
                ->get(self::GRAPH_BASE."/{$mailboxPath}/messages/{$messageId}", [
                    '$select' => $needsBody ? 'id,body,bodyPreview,replyTo' : 'id,replyTo',
                ]);

            if (! $response->successful()) {
                Log::warning('Outlook message body hydrate failed', [
                    'message_id' => $message->external_message_id,
                    'status' => $response->status(),
                ]);

                continue;
            }

            $payload = $response->json() ?: [];
            if ($needsReplyTo) {
                $message->reply_to_emails = $this->graphRecipientAddresses($payload['replyTo'] ?? []) ?: '';
            }

            if ($needsBody) {
                $body = $payload['body'] ?? [];
                $contentType = strtolower($body['contentType'] ?? 'text');
                $content = $body['content'] ?? ($payload['bodyPreview'] ?? '');
                if ($content !== '') {
                    if ($contentType === 'html') {
                        $message->body_html = preg_replace('#<script\b[^>]*>(.*?)</script>#is', '', $content);
                        $message->body_text = strip_tags($content);
                    } else {
                        $message->body_html = null;
                        $message->body_text = $content;
                    }
                }
            }

            if ($message->isDirty()) {
                $message->save();
            }
        }

        $latest = $conversation->messages()->orderByDesc('sent_at')->orderByDesc('id')->first();
        if ($latest) {
            $snippet = EmailQuotedHistory::snippet($latest->body_html, $latest->body_text ?: $conversation->snippet);
            if ($snippet !== (string) $conversation->snippet) {
                $conversation->snippet = $snippet;
                $conversation->save();
            }
        }

        $this->hydrateConversationAttachments($inbox, $conversation, $account, $mailboxPath);
    }

    /**
     * Fetch file-attachment metadata from Graph for messages not yet hydrated.
     * null attachments = not fetched; [] = none; list = downloadable files.
     * Also embeds cid: inline images into body_html as data URIs so they render in the UI.
     *
     * @param  OutlookMailAccount|null  $account  Pre-refreshed account when called from hydrateConversationBodies
     */
    public function hydrateConversationAttachments(
        SharedInbox $inbox,
        InboxConversation $conversation,
        ?OutlookMailAccount $account = null,
        ?string $mailboxPath = null
    ): void {
        $account = $account ?: $inbox->account;
        if (! $account) {
            return;
        }

        $account = $this->refreshTokenIfNeeded($account);
        $mailboxPath = $mailboxPath ?: $this->mailboxPath($inbox->loadMissing('account'));

        $messages = $conversation->messages()
            ->whereNotNull('external_message_id')
            ->where('external_message_id', 'not like', 'local-%')
            ->get()
            ->filter(fn (InboxMessage $message) => $this->needsAttachmentHydration($message));

        foreach ($messages as $message) {
            $messageId = rawurlencode((string) $message->external_message_id);
            $response = Http::withToken($account->access_token)
                ->timeout(120)
                // Do not $select contentId/contentBytes — those exist only on fileAttachment
                // and selecting them on the base attachment type returns HTTP 400.
                // Unselected responses include them for file attachments.
                ->get(self::GRAPH_BASE."/{$mailboxPath}/messages/{$messageId}/attachments", [
                    '$top' => 50,
                ]);

            if (! $response->successful()) {
                Log::warning('Outlook message attachments hydrate failed', [
                    'message_id' => $message->external_message_id,
                    'status' => $response->status(),
                ]);
                // Retry later on transient errors; mark empty on permanent failures.
                if ($response->status() >= 500 || $response->status() === 429) {
                    continue;
                }
                $message->attachments = [];
                $message->save();

                continue;
            }

            $items = $response->json('value') ?? [];
            $files = [];
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $type = (string) ($item['@odata.type'] ?? '');
                if ($type !== '' && $type !== '#microsoft.graph.fileAttachment') {
                    continue;
                }
                $externalId = (string) ($item['id'] ?? '');
                $name = trim((string) ($item['name'] ?? ''));
                if ($externalId === '' || $name === '') {
                    continue;
                }
                $contentId = trim((string) ($item['contentId'] ?? ''), "<> \t\r\n");
                $files[] = [
                    'id' => $externalId,
                    'name' => $name,
                    'content_type' => (string) ($item['contentType'] ?? 'application/octet-stream'),
                    'size' => isset($item['size']) ? (int) $item['size'] : null,
                    'is_inline' => ! empty($item['isInline']),
                    'content_id' => $contentId !== '' ? $contentId : null,
                ];
            }

            $message->attachments = $files;

            $html = (string) ($message->body_html ?? '');
            if ($html !== '' && preg_match('/cid:/i', $html)) {
                $message->body_html = $this->embedCidImages($html, is_array($items) ? $items : []);
            }

            $message->save();
        }
    }

    /**
     * Whether attachment metadata (and cid image embedding) still needs to be fetched.
     */
    private function needsAttachmentHydration(InboxMessage $message): bool
    {
        if ($message->attachments === null) {
            return true;
        }

        $html = (string) ($message->body_html ?? '');
        if ($html === '' || ! preg_match('/cid:/i', $html)) {
            return false;
        }

        // Body still has unresolved cid: refs, or we never stored content_id maps.
        if (preg_match('/src=["\']cid:/i', $html)) {
            return true;
        }

        return ! collect($message->attachments)->contains(
            fn ($a) => is_array($a) && ! empty($a['content_id'])
        );
    }

    /**
     * Replace cid:… image refs with data URIs from Graph fileAttachment contentBytes.
     *
     * @param  array<int, mixed>  $graphAttachments
     */
    private function embedCidImages(string $html, array $graphAttachments): string
    {
        foreach ($graphAttachments as $item) {
            if (! is_array($item)) {
                continue;
            }
            $cid = trim((string) ($item['contentId'] ?? ''), "<> \t\r\n");
            $bytes = $item['contentBytes'] ?? null;
            if ($cid === '' || ! is_string($bytes) || $bytes === '') {
                continue;
            }

            $contentType = (string) ($item['contentType'] ?? 'application/octet-stream');
            if ($contentType === '') {
                $contentType = 'application/octet-stream';
            }
            $dataUri = 'data:'.$contentType.';base64,'.$bytes;
            $quoted = preg_quote($cid, '/');

            // src="cid:foo" / src='cid:foo' / src="cid:foo@bar"
            $html = preg_replace(
                '/(src\s*=\s*["\'])cid:'.$quoted.'(?:@[^"\']*)?(["\'])/i',
                '$1'.$dataUri.'$2',
                $html
            ) ?? $html;

            // url(cid:foo) in CSS
            $html = preg_replace(
                '/(url\(\s*[\'"]?)cid:'.$quoted.'(?:@[^\'"\)]*)?([\'"]?\s*\))/i',
                '$1'.$dataUri.'$2',
                $html
            ) ?? $html;
        }

        return $html;
    }

    /**
     * Download a file attachment binary from Graph.
     *
     * @return array{name: string, content_type: string, content: string}|null
     */
    public function downloadMessageAttachment(
        SharedInbox $inbox,
        InboxMessage $message,
        string $attachmentId
    ): ?array {
        $account = $inbox->account;
        if (! $account || ! $message->external_message_id) {
            return null;
        }
        if (str_starts_with((string) $message->external_message_id, 'local-')) {
            return null;
        }

        $meta = collect($message->attachments ?? [])->firstWhere('id', $attachmentId);
        if (! $meta) {
            return null;
        }

        $account = $this->refreshTokenIfNeeded($account);
        $mailboxPath = $this->mailboxPath($inbox->loadMissing('account'));
        $messageId = rawurlencode((string) $message->external_message_id);
        $attachId = rawurlencode($attachmentId);

        $response = Http::withToken($account->access_token)
            ->timeout(120)
            ->withHeaders(['Accept' => '*/*'])
            ->get(self::GRAPH_BASE."/{$mailboxPath}/messages/{$messageId}/attachments/{$attachId}/\$value");

        if (! $response->successful()) {
            Log::warning('Outlook attachment download failed', [
                'message_id' => $message->external_message_id,
                'attachment_id' => $attachmentId,
                'status' => $response->status(),
            ]);

            return null;
        }

        return [
            'name' => (string) ($meta['name'] ?? 'attachment'),
            'content_type' => (string) ($meta['content_type'] ?? 'application/octet-stream'),
            'content' => $response->body(),
        ];
    }

    /**
     * @param  array{to: string, subject: string, body: string, cc?: string|null, reply_to_message_id?: string|null, honor_recipients?: bool}  $payload
     */
    public function sendMail(SharedInbox $inbox, array $payload): ?array
    {
        $account = $inbox->account;
        if (! $account) {
            return null;
        }

        $account = $this->refreshTokenIfNeeded($account);
        $mailboxPath = $this->mailboxPath($inbox);

        $toList = array_values(array_filter(array_map('trim', explode(',', $payload['to']))));
        $ccList = array_values(array_filter(array_map('trim', explode(',', (string) ($payload['cc'] ?? '')))));

        $message = [
            'subject' => $payload['subject'],
            'body' => [
                'contentType' => 'HTML',
                'content' => $payload['body'],
            ],
            'toRecipients' => array_map(fn ($email) => [
                'emailAddress' => ['address' => $email],
            ], $toList),
        ];

        if ($ccList !== []) {
            $message['ccRecipients'] = array_map(fn ($email) => [
                'emailAddress' => ['address' => $email],
            ], $ccList);
        }

        $attachments = $payload['attachments'] ?? [];
        if (is_array($attachments) && $attachments !== []) {
            $message['attachments'] = [];
            foreach ($attachments as $attachment) {
                $name = trim((string) ($attachment['name'] ?? ''));
                $bytes = (string) ($attachment['contentBytes'] ?? '');
                if ($name === '' || $bytes === '') {
                    continue;
                }
                $item = [
                    '@odata.type' => '#microsoft.graph.fileAttachment',
                    'name' => $name,
                    'contentType' => (string) ($attachment['contentType'] ?? 'application/octet-stream'),
                    'contentBytes' => $bytes,
                ];
                if (! empty($attachment['isInline']) && ! empty($attachment['contentId'])) {
                    $item['isInline'] = true;
                    $item['contentId'] = (string) $attachment['contentId'];
                }
                $message['attachments'][] = $item;
            }
        }

        $hasAttachments = ! empty($message['attachments']);
        $honorRecipients = ! empty($payload['honor_recipients']) || $ccList !== [];

        // Graph /reply ignores To/CC — use sendMail when the user set recipients or attached files.
        if (! empty($payload['reply_to_message_id'])
            && ! str_starts_with((string) $payload['reply_to_message_id'], 'local-')
            && ! $hasAttachments
            && ! $honorRecipients
        ) {
            $response = Http::withToken($account->access_token)
                ->post(self::GRAPH_BASE."/{$mailboxPath}/messages/{$payload['reply_to_message_id']}/reply", [
                    'comment' => $payload['body'],
                ]);
        } else {
            $response = Http::withToken($account->access_token)
                ->timeout(120)
                ->post(self::GRAPH_BASE."/{$mailboxPath}/sendMail", [
                    'message' => $message,
                    'saveToSentItems' => true,
                ]);
        }

        if (! $response->successful()) {
            Log::warning('Outlook send mail failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        return ['sent' => true];
    }

    /**
     * Save (create or update) a draft reply in the Outlook Drafts folder without sending it.
     *
     * @param  array{
     *     body: string,
     *     to: string,
     *     cc?: ?string,
     *     attachments?: array<int, array{name: string, contentType: string, contentBytes: string, isInline?: bool, contentId?: string}>,
     *     reply_to_message_id: string,
     *     draft_message_id?: ?string,
     * }  $payload
     * @return array{id: string}|null
     */
    public function saveDraftReply(SharedInbox $inbox, array $payload): ?array
    {
        $account = $inbox->account;
        if (! $account) {
            return null;
        }

        $replyToId = (string) ($payload['reply_to_message_id'] ?? '');
        if ($replyToId === '' || str_starts_with($replyToId, 'local-')) {
            return null;
        }

        try {
            $result = $this->performSaveDraftReply($inbox, $account, $replyToId, $payload);
            Log::info('OutlookMailService::saveDraftReply finished', [
                'inbox_id' => $inbox->id,
                'result' => $result,
            ]);

            return $result;
        } catch (\Throwable $e) {
            Log::warning('Outlook save draft reply failed', [
                'inbox_id' => $inbox->id,
                'exception_class' => get_class($e),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{id: string}|null
     */
    private function performSaveDraftReply(SharedInbox $inbox, OutlookMailAccount $account, string $replyToId, array $payload): ?array
    {
        $account = $this->refreshTokenIfNeeded($account);
        $mailboxPath = $this->mailboxPath($inbox);

        Log::info('OutlookMailService::performSaveDraftReply starting', [
            'inbox_id' => $inbox->id,
            'mailbox_path' => $mailboxPath,
            'reply_to_message_id' => $replyToId,
            'requested_draft_message_id' => $payload['draft_message_id'] ?? null,
        ]);

        $draftId = (string) ($payload['draft_message_id'] ?? '');
        $existingBody = '';
        $isNewDraft = false;

        if ($draftId !== '') {
            $existingResp = Http::withToken($account->access_token)
                ->timeout(15)
                ->get(self::GRAPH_BASE."/{$mailboxPath}/messages/{$draftId}", ['$select' => 'id,body,isDraft']);
            if ($existingResp->successful() && ($existingResp->json('isDraft') ?? true)) {
                $existingBody = (string) ($existingResp->json('body.content') ?? '');
            } else {
                $draftId = '';
            }
        }

        if ($draftId === '') {
            $createResp = Http::withToken($account->access_token)
                ->timeout(20)
                ->post(self::GRAPH_BASE."/{$mailboxPath}/messages/{$replyToId}/createReply", []);

            Log::info('OutlookMailService createReply response', [
                'status' => $createResp->status(),
                'successful' => $createResp->successful(),
            ]);

            if (! $createResp->successful()) {
                Log::warning('Outlook create draft reply failed', [
                    'status' => $createResp->status(),
                    'body' => mb_substr($createResp->body(), 0, 500),
                ]);

                return null;
            }

            $draftId = (string) $createResp->json('id');
            $existingBody = (string) ($createResp->json('body.content') ?? '');
            $isNewDraft = true;
            if ($draftId === '') {
                Log::warning('Outlook create draft reply returned no id', [
                    'body' => mb_substr($createResp->body(), 0, 500),
                ]);

                return null;
            }
        }

        $toList = array_values(array_filter(array_map('trim', explode(',', (string) $payload['to']))));
        $ccList = array_values(array_filter(array_map('trim', explode(',', (string) ($payload['cc'] ?? '')))));

        $update = [
            'body' => [
                'contentType' => 'HTML',
                'content' => $this->mergeDraftBody($existingBody, (string) $payload['body']),
            ],
            'toRecipients' => array_map(fn ($email) => ['emailAddress' => ['address' => $email]], $toList),
            'ccRecipients' => array_map(fn ($email) => ['emailAddress' => ['address' => $email]], $ccList),
        ];

        $patchResp = Http::withToken($account->access_token)
            ->timeout(20)
            ->patch(self::GRAPH_BASE."/{$mailboxPath}/messages/{$draftId}", $update);

        Log::info('OutlookMailService patch draft response', [
            'status' => $patchResp->status(),
            'successful' => $patchResp->successful(),
        ]);

        if (! $patchResp->successful()) {
            Log::warning('Outlook update draft reply failed', [
                'status' => $patchResp->status(),
                'body' => mb_substr($patchResp->body(), 0, 500),
            ]);

            return null;
        }

        // A freshly created draft has no attachments yet — only reconcile
        // (list + delete) when reusing a draft from an earlier save, so the
        // common case skips two round trips.
        if (! $isNewDraft) {
            $existingAttachments = Http::withToken($account->access_token)
                ->timeout(15)
                ->get(self::GRAPH_BASE."/{$mailboxPath}/messages/{$draftId}/attachments", ['$select' => 'id']);
            if ($existingAttachments->successful()) {
                foreach ($existingAttachments->json('value') ?? [] as $existingAttachment) {
                    $attachmentId = $existingAttachment['id'] ?? null;
                    if ($attachmentId) {
                        Http::withToken($account->access_token)
                            ->timeout(15)
                            ->delete(self::GRAPH_BASE."/{$mailboxPath}/messages/{$draftId}/attachments/{$attachmentId}");
                    }
                }
            }
        }

        foreach ($payload['attachments'] ?? [] as $attachment) {
            $name = trim((string) ($attachment['name'] ?? ''));
            $bytes = (string) ($attachment['contentBytes'] ?? '');
            if ($name === '' || $bytes === '') {
                continue;
            }
            $item = [
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => $name,
                'contentType' => (string) ($attachment['contentType'] ?? 'application/octet-stream'),
                'contentBytes' => $bytes,
            ];
            if (! empty($attachment['isInline']) && ! empty($attachment['contentId'])) {
                $item['isInline'] = true;
                $item['contentId'] = (string) $attachment['contentId'];
            }
            Http::withToken($account->access_token)
                ->timeout(30)
                ->post(self::GRAPH_BASE."/{$mailboxPath}/messages/{$draftId}/attachments", $item);
        }

        // PATCH already returns the fully updated message — reuse it instead
        // of an extra GET round trip.
        $this->upsertMessage($inbox, $patchResp->json() ?: [], 'drafts', 'drafts', 'outbound');

        return ['id' => $draftId];
    }

    /**
     * Best-effort cleanup of an Outlook draft once its content has actually
     * been sent through a different action (e.g. the reply/send flow).
     */
    public function deleteDraftMessage(SharedInbox $inbox, string $draftId): void
    {
        $account = $inbox->account;
        if (! $account || $draftId === '' || str_starts_with($draftId, 'local-')) {
            return;
        }

        try {
            $account = $this->refreshTokenIfNeeded($account);
            $mailboxPath = $this->mailboxPath($inbox);

            $response = Http::withToken($account->access_token)
                ->timeout(15)
                ->delete(self::GRAPH_BASE."/{$mailboxPath}/messages/{$draftId}");

            if (! $response->successful() && $response->status() !== 404) {
                Log::warning('Outlook draft cleanup failed', [
                    'inbox_id' => $inbox->id,
                    'status' => $response->status(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Outlook draft cleanup threw', [
                'inbox_id' => $inbox->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Insert freshly-typed reply content ahead of the quoted history Graph
     * pre-fills a createReply draft with, so drafts read like a normal reply.
     */
    private function mergeDraftBody(string $existingHtml, string $newContent): string
    {
        if ($existingHtml === '') {
            return $newContent;
        }

        if (preg_match('/<body[^>]*>/i', $existingHtml, $m, PREG_OFFSET_CAPTURE)) {
            $insertAt = $m[0][1] + strlen($m[0][0]);

            return substr($existingHtml, 0, $insertAt).$newContent.substr($existingHtml, $insertAt);
        }

        return $newContent.$existingHtml;
    }

    /**
     * @param  array<int, mixed>  $recipients
     */
    private function graphRecipientAddresses(array $recipients): string
    {
        return collect($recipients)
            ->map(fn ($r) => $r['emailAddress']['address'] ?? null)
            ->filter()
            ->implode(', ');
    }

    private function truncate(?string $value, int $max): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return mb_strlen($value) > $max ? mb_substr($value, 0, $max) : $value;
    }

    /**
     * If a linked MS365 account does not match this inbox's address, unlink it and
     * delete wrongly imported conversations. Returns false when a repair ran.
     */
    public function repairInboxBinding(SharedInbox $inbox): bool
    {
        $account = $inbox->account;
        if (! $account) {
            return true;
        }

        if ($this->assertAccountMatchesInbox($inbox, $account)) {
            return true;
        }

        $this->clearInboxConversations($inbox);
        $inbox->outlook_mail_account_id = null;
        $inbox->save();

        return false;
    }

    /**
     * Delete conversations in chunks so large mailboxes do not blow the request timeout.
     */
    public function clearInboxConversations(SharedInbox $inbox): void
    {
        $inbox->conversations()
            ->orderBy('id')
            ->chunkById(100, function ($conversations) {
                InboxConversation::whereIn('id', $conversations->pluck('id'))->delete();
            });
    }

    /**
     * Graph mailbox path for this inbox's configured address only.
     * Never falls back to /me when the authenticated account email differs.
     */
    private function mailboxPath(SharedInbox $inbox): string
    {
        $target = $this->targetMailboxEmail($inbox);
        $accountEmail = $inbox->account?->email;

        if (! $target || ! $accountEmail) {
            return 'me';
        }

        if (strcasecmp($target, $accountEmail) === 0) {
            return 'me';
        }

        return 'users/'.rawurlencode($target);
    }

    /** Email address this inbox is bound to (shared mailbox or login mailbox). */
    private function targetMailboxEmail(SharedInbox $inbox): ?string
    {
        $email = $inbox->external_mailbox ?: $inbox->email;

        return $email ? strtolower(trim($email)) : null;
    }

    /**
     * mailbox_login inboxes must use a matching MS365 account.
     * shared_mailbox inboxes may use a delegate account + /users/{address}.
     */
    private function assertAccountMatchesInbox(SharedInbox $inbox, OutlookMailAccount $account): bool
    {
        $target = $this->targetMailboxEmail($inbox);
        if (! $target) {
            return true;
        }

        $accountEmail = strtolower(trim((string) $account->email));

        // Delegate access to a different shared mailbox address is allowed.
        if (! empty($inbox->external_mailbox) && strcasecmp($target, $accountEmail) !== 0) {
            return true;
        }

        if (strcasecmp($target, $accountEmail) !== 0) {
            Log::warning('Outlook inbox refused sync: account email does not match inbox address', [
                'inbox_id' => $inbox->id,
                'inbox_email' => $target,
                'account_email' => $account->email,
            ]);

            return false;
        }

        return true;
    }
}
