<?php

namespace App\Services;

use App\Models\InboxConversation;
use App\Models\InboxMessage;
use App\Models\OutlookMailAccount;
use App\Models\SharedInbox;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OutlookMailService
{
    private const GRAPH_BASE = 'https://graph.microsoft.com/v1.0';

    /** @var array<string, array{graph: string, status: string, direction: string}> */
    public const FOLDERS = [
        'inbox' => ['graph' => 'inbox', 'status' => 'open', 'direction' => 'inbound'],
        'drafts' => ['graph' => 'drafts', 'status' => 'drafts', 'direction' => 'outbound'],
        'sent' => ['graph' => 'sentitems', 'status' => 'sent', 'direction' => 'outbound'],
        'trash' => ['graph' => 'deleteditems', 'status' => 'trashed', 'direction' => 'inbound'],
        'spam' => ['graph' => 'junkemail', 'status' => 'spam', 'direction' => 'inbound'],
    ];

    public function __construct(
        protected CalendarOauthSettingsService $oauthSettings,
        protected InboxRuleEngine $ruleEngine
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

        $folders = self::FOLDERS;
        if ($onlyFolder !== null) {
            if (! isset($folders[$onlyFolder])) {
                return 0;
            }
            $folders = [$onlyFolder => $folders[$onlyFolder]];
        }

        foreach ($folders as $folder => $meta) {
            $nextLink = null;
            $fetched = 0;
            do {
                $page = $this->syncFolderPage($inbox, $account, $folder, $meta, $nextLink, $fetched);
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
     *   folders_remaining: array<string, int>
     * }
     */
    public function getMailboxMessageTotals(SharedInbox $inbox): array
    {
        $account = $inbox->account;
        $folders = array_fill_keys(array_keys(self::FOLDERS), 0);
        $foldersSynced = array_fill_keys(array_keys(self::FOLDERS), 0);
        $foldersRemaining = array_fill_keys(array_keys(self::FOLDERS), 0);
        $graphTotal = 0;
        $alreadySynced = 0;
        $remaining = 0;

        if (! $account || ! $account->is_active || ! $this->assertAccountMatchesInbox($inbox, $account)) {
            return [
                'total' => 0,
                'graph_total' => 0,
                'already_synced' => 0,
                'remaining' => 0,
                'folders' => $folders,
                'folders_synced' => $foldersSynced,
                'folders_remaining' => $foldersRemaining,
            ];
        }

        $account = $this->refreshTokenIfNeeded($account);
        $mailboxPath = $this->mailboxPath($inbox->loadMissing('account'));

        foreach (self::FOLDERS as $folder => $meta) {
            $localCount = (int) InboxMessage::query()
                ->whereHas('conversation', function ($q) use ($inbox, $folder) {
                    $q->where('shared_inbox_id', $inbox->id)
                        ->where('folder', $folder);
                })
                ->count();

            $foldersSynced[$folder] = $localCount;
            $alreadySynced += $localCount;

            $response = Http::withToken($account->access_token)
                ->timeout(30)
                ->get(self::GRAPH_BASE."/{$mailboxPath}/mailFolders/{$meta['graph']}", [
                    '$select' => 'totalItemCount,unreadItemCount,displayName',
                ]);

            if (! $response->successful()) {
                Log::warning('Outlook folder count failed', [
                    'inbox_id' => $inbox->id,
                    'folder' => $folder,
                    'status' => $response->status(),
                ]);
                // Fall back to treating unsynced as unknown; don't block other folders.
                $foldersRemaining[$folder] = 0;
                continue;
            }

            $graphCount = (int) ($response->json('totalItemCount') ?? 0);
            $folders[$folder] = $graphCount;
            $graphTotal += $graphCount;

            $folderRemaining = max(0, $graphCount - $localCount);
            $foldersRemaining[$folder] = $folderRemaining;
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
        $select = 'id,conversationId,subject,bodyPreview,from,toRecipients,ccRecipients,receivedDateTime,sentDateTime,lastModifiedDateTime,isRead,isDraft';

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

        // Don't overwrite local archive/workflow moves back from sync for inbox threads
        if ($isNew || ! in_array($conversation->status, ['archived'], true) || $folder !== 'inbox') {
            $conversation->status = $status;
        }

        $conversation->folder = $folder;
        $conversation->subject = $subject;
        $conversation->snippet = mb_substr($msg['bodyPreview'] ?? '', 0, 500);
        $conversation->from_name = $fromName;
        $conversation->from_email = $fromEmail;

        if (! $conversation->last_message_at || $receivedAt->gt($conversation->last_message_at)) {
            $conversation->last_message_at = $receivedAt;
            if ($folder === 'inbox') {
                $conversation->is_read = (bool) ($msg['isRead'] ?? false);
            }
        }

        $conversation->save();

        $externalMessageId = $this->truncate($msg['id'] ?? null, 512);
        if ($externalMessageId) {
            $existing = InboxMessage::where('inbox_conversation_id', $conversation->id)
                ->where('external_message_id', $externalMessageId)
                ->first();

            if ($existing) {
                // Correct timezone drift from earlier syncs (Graph UTC stored as local wall-clock).
                if (! $existing->sent_at || ! $existing->sent_at->equalTo($receivedAt)) {
                    $existing->sent_at = $receivedAt;
                    $existing->save();
                }

                return false;
            }

            $toEmails = collect($msg['toRecipients'] ?? [])
                ->map(fn ($r) => $r['emailAddress']['address'] ?? null)
                ->filter()
                ->implode(', ');
            $ccEmails = collect($msg['ccRecipients'] ?? [])
                ->map(fn ($r) => $r['emailAddress']['address'] ?? null)
                ->filter()
                ->implode(', ');

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

            InboxMessage::create([
                'inbox_conversation_id' => $conversation->id,
                'external_message_id' => $externalMessageId,
                'direction' => ($msg['isDraft'] ?? false) ? 'outbound' : $direction,
                'from_name' => $fromName,
                'from_email' => $fromEmail,
                'to_emails' => $toEmails ?: null,
                'cc_emails' => $ccEmails ?: null,
                'subject' => $subject === '(No subject)' ? null : $subject,
                'body_html' => $safeHtml,
                'body_text' => $bodyText,
                'is_read' => (bool) ($msg['isRead'] ?? true),
                'sent_at' => $receivedAt,
            ]);

            $conversation->message_count = $conversation->messages()->count();
            $conversation->save();

            $messageDirection = ($msg['isDraft'] ?? false) ? 'outbound' : $direction;
            if ($folder === 'inbox' && $messageDirection === 'inbound') {
                $triggers = $isNew
                    ? [
                        InboxRuleEngine::TRIGGER_INBOUND_MESSAGE,
                        InboxRuleEngine::TRIGGER_INBOUND_MESSAGE_NEW,
                    ]
                    : [InboxRuleEngine::TRIGGER_INBOUND_MESSAGE];
                $this->ruleEngine->apply($conversation->fresh(['tags', 'inbox']), $triggers);
            }

            return true;
        }

        return $isNew;
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
        $destination = self::FOLDERS[$folder]['graph'];

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
                    ->orWhere('body_html', '');
            })
            ->get();

        foreach ($messages as $message) {
            $messageId = rawurlencode((string) $message->external_message_id);
            $response = Http::withToken($account->access_token)
                ->timeout(60)
                ->withHeaders([
                    'Prefer' => 'outlook.body-content-type="html"',
                ])
                ->get(self::GRAPH_BASE."/{$mailboxPath}/messages/{$messageId}", [
                    '$select' => 'id,body,bodyPreview',
                ]);

            if (! $response->successful()) {
                Log::warning('Outlook message body hydrate failed', [
                    'message_id' => $message->external_message_id,
                    'status' => $response->status(),
                ]);
                continue;
            }

            $payload = $response->json() ?: [];
            $body = $payload['body'] ?? [];
            $contentType = strtolower($body['contentType'] ?? 'text');
            $content = $body['content'] ?? ($payload['bodyPreview'] ?? '');
            if ($content === '') {
                continue;
            }

            if ($contentType === 'html') {
                $message->body_html = preg_replace('#<script\b[^>]*>(.*?)</script>#is', '', $content);
                $message->body_text = strip_tags($content);
            } else {
                $message->body_html = null;
                $message->body_text = $content;
            }
            $message->save();
        }
    }

    /**
     * @param  array{to: string, subject: string, body: string, cc?: string|null, reply_to_message_id?: string|null}  $payload
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
                $message['attachments'][] = [
                    '@odata.type' => '#microsoft.graph.fileAttachment',
                    'name' => $name,
                    'contentType' => (string) ($attachment['contentType'] ?? 'application/octet-stream'),
                    'contentBytes' => $bytes,
                ];
            }
        }

        $hasAttachments = ! empty($message['attachments']);

        // Graph /reply only accepts a comment — use sendMail when attaching files.
        if (! empty($payload['reply_to_message_id'])
            && ! str_starts_with((string) $payload['reply_to_message_id'], 'local-')
            && ! $hasAttachments
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
