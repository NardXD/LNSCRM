<?php

namespace App\Services\Front;

use App\Models\Company;
use App\Models\FrontCommentImportProgress;
use App\Models\FrontSyncedCommentConversation;
use App\Models\InboxConversation;
use App\Models\InboxConversationComment;
use App\Models\SharedInbox;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class FrontCommentImportService
{
    public const DRY_RUN_LIMIT = 100;

    private const MAX_COMMENT_ATTACHMENTS = 20;

    private const MAX_ATTACHMENT_BYTES = 15 * 1024 * 1024;

    /** @var array<int, array{by_email: array<string, User>, by_name: array<string, User>, fallback: User|null}> */
    private array $userLookupCache = [];

    public function __construct(
        private readonly FrontTagImportService $tagImport,
    ) {}

    /**
     * @param  array{
     *     dry_run?: bool,
     *     inbox_map?: array<string, int|string>,
     *     front_inbox_id?: string|null,
     *     shared_inbox_id?: int|null,
     *     statuses?: list<string>,
     *     fallback_user_id?: int|null,
     * }  $options
     * @return array<string, mixed>
     */
    public function importFromApi(Company $company, FrontApiClient $client, array $options = []): array
    {
        if ($options['dry_run'] ?? false) {
            $options['max_conversations'] = self::DRY_RUN_LIMIT;
        }

        $sharedInboxes = $this->tagImport->sharedInboxesForCompany($company, $options['shared_inbox_id'] ?? null);
        if ($sharedInboxes->isEmpty()) {
            throw new RuntimeException('No active shared inboxes found in LNSCRM. Connect Outlook mailboxes under Inbox first.');
        }

        $manualMap = $options['inbox_map'] ?? [];
        $frontInboxFilter = $options['front_inbox_id'] ?? null;
        $inboxListingError = null;
        $frontInboxes = [];

        try {
            $frontInboxes = $client->listInboxes();
        } catch (\Throwable $e) {
            $inboxListingError = $e->getMessage();
        }

        $inboxMap = $this->tagImport->resolveInboxMap($frontInboxes, $sharedInboxes, $manualMap, $frontInboxFilter);
        if ($inboxMap === []) {
            throw new RuntimeException(
                $inboxListingError
                    ? 'Could not list Front inboxes ('.$inboxListingError.'). Map at least one Front inbox to a LNSCRM shared inbox.'
                    : 'Map at least one Front inbox to a LNSCRM shared inbox before importing comments.'
            );
        }

        return $this->importFromApiViaInboxes($company, $client, $sharedInboxes, $inboxMap, $options);
    }

    /**
     * @param  Collection<int, SharedInbox>  $sharedInboxes
     * @param  array<string, int>  $inboxMap
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function importFromApiViaInboxes(
        Company $company,
        FrontApiClient $client,
        Collection $sharedInboxes,
        array $inboxMap,
        array $options
    ): array {
        $stats = $this->emptyStats();
        $stats['mapped_inboxes'] = count($inboxMap);
        $stats['import_mode'] = 'inboxes';

        foreach ($inboxMap as $frontInboxId => $sharedInboxId) {
            $sharedInbox = $sharedInboxes->firstWhere('id', $sharedInboxId);
            if (! $sharedInbox) {
                continue;
            }

            $this->tagImport->prepareConversationLookup($sharedInbox);

            $statuses = $options['statuses'] ?? ['archived', 'assigned', 'unassigned'];
            $maxConversations = $options['max_conversations'] ?? null;
            $scanned = 0;

            try {
                foreach ($client->listInboxConversations($frontInboxId, $statuses) as $frontConversation) {
                    $scanned++;
                    $stats['conversations_scanned'] = ((int) ($stats['conversations_scanned'] ?? 0)) + 1;

                    if ($maxConversations !== null && $scanned > $maxConversations) {
                        $stats['preview_limit'] = $maxConversations;
                        $stats['preview_limited'] = true;
                        break 2;
                    }

                    $this->importConversationComments(
                        $company,
                        $client,
                        $sharedInbox,
                        $frontConversation,
                        $options,
                        $stats
                    );
                }
            } catch (\Throwable $e) {
                $stats['inbox_errors'] = $stats['inbox_errors'] ?? [];
                $stats['inbox_errors'][] = $frontInboxId.': '.$e->getMessage();
            }
        }

        if (! empty($stats['inbox_errors']) && (int) ($stats['comments_imported'] ?? 0) === 0 && (int) ($stats['comments_existing'] ?? 0) === 0) {
            throw new RuntimeException(implode(' ', $stats['inbox_errors']));
        }

        return $stats;
    }

    /**
     * @param  array{
     *     dry_run?: bool,
     *     statuses?: list<string>,
     *     fallback_user_id?: int|null,
     * }  $options
     * @return array<string, mixed>
     */
    public function importInboxPageBatch(
        Company $company,
        FrontApiClient $client,
        string $frontInboxId,
        int $sharedInboxId,
        array $options = [],
        ?string $pageUrl = null,
    ): array {
        $sharedInboxes = $this->tagImport->sharedInboxesForCompany($company, $sharedInboxId);
        $sharedInbox = $sharedInboxes->first();
        if (! $sharedInbox) {
            throw new RuntimeException("Shared inbox {$sharedInboxId} not found.");
        }

        $this->tagImport->prepareConversationLookup($sharedInbox);

        $dryRun = (bool) ($options['dry_run'] ?? false);
        $isDryRunPreview = $dryRun && $pageUrl === null;

        $progress = null;
        $resumedFrom = 0;
        if (! $dryRun && $pageUrl === null) {
            $progress = FrontCommentImportProgress::query()
                ->where('company_id', $company->id)
                ->where('front_inbox_id', $frontInboxId)
                ->first();

            if ($progress?->next_page_url) {
                $pageUrl = $progress->next_page_url;
                $resumedFrom = (int) $progress->conversations_done;
            }
        }

        $statuses = $options['statuses'] ?? ['archived', 'assigned', 'unassigned'];
        $pageLimit = $isDryRunPreview ? self::DRY_RUN_LIMIT : 20;
        $page = $client->fetchInboxConversationPage($frontInboxId, $pageUrl, $statuses, $pageLimit);

        $stats = $this->emptyStats();
        $stats['mapped_inboxes'] = 1;
        $stats['import_mode'] = 'inboxes';
        $stats['page_conversations'] = count($page['results']);
        if ($resumedFrom > 0) {
            $stats['resumed_from'] = $resumedFrom;
        }

        foreach ($page['results'] as $frontConversation) {
            $stats['conversations_scanned'] = ((int) ($stats['conversations_scanned'] ?? 0)) + 1;
            $this->importConversationComments(
                $company,
                $client,
                $sharedInbox,
                $frontConversation,
                $options,
                $stats
            );
        }

        if ($isDryRunPreview) {
            $stats['preview_limit'] = self::DRY_RUN_LIMIT;
            $stats['preview_limited'] = ($page['next_page_url'] ?? null) !== null
                || count($page['results']) >= self::DRY_RUN_LIMIT;
        }

        $hasMore = $isDryRunPreview ? false : $page['next_page_url'] !== null;

        if (! $dryRun) {
            if ($hasMore) {
                FrontCommentImportProgress::query()->updateOrCreate(
                    ['company_id' => $company->id, 'front_inbox_id' => $frontInboxId],
                    [
                        'shared_inbox_id' => $sharedInboxId,
                        'next_page_url' => $page['next_page_url'],
                        'conversations_done' => $resumedFrom + count($page['results']),
                    ]
                );
            } else {
                FrontCommentImportProgress::query()
                    ->where('company_id', $company->id)
                    ->where('front_inbox_id', $frontInboxId)
                    ->delete();
            }
        }

        return array_merge($stats, [
            'has_more' => $hasMore,
            'next_page_url' => $isDryRunPreview ? null : $page['next_page_url'],
            'front_inbox_id' => $frontInboxId,
        ]);
    }

    public function resetProgress(Company $company): void
    {
        FrontCommentImportProgress::query()->where('company_id', $company->id)->delete();
        FrontSyncedCommentConversation::query()->where('company_id', $company->id)->delete();
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function importFromFile(Company $company, string $path, array $options = []): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Import file not found: {$path}");
        }

        $payload = json_decode((string) file_get_contents($path), true);
        if (! is_array($payload)) {
            throw new RuntimeException('Import file must contain valid JSON.');
        }

        $sharedInboxes = $this->tagImport->sharedInboxesForCompany($company, $options['shared_inbox_id'] ?? null);
        $frontInboxes = collect($payload['inboxes'] ?? [])
            ->filter(fn ($row) => is_array($row))
            ->values()
            ->all();

        $inboxMap = $this->tagImport->resolveInboxMap(
            $frontInboxes,
            $sharedInboxes,
            $options['inbox_map'] ?? [],
            $options['front_inbox_id'] ?? null
        );

        if ($inboxMap === []) {
            throw new RuntimeException('No Front inbox in the export could be mapped to a local shared inbox.');
        }

        $stats = $this->emptyStats();
        $stats['mapped_inboxes'] = count($inboxMap);
        $stats['import_mode'] = 'inboxes';

        foreach ($frontInboxes as $frontInbox) {
            $frontInboxId = (string) ($frontInbox['id'] ?? '');
            $sharedInboxId = $inboxMap[$frontInboxId] ?? null;
            if (! $sharedInboxId) {
                continue;
            }

            $sharedInbox = $sharedInboxes->firstWhere('id', $sharedInboxId);
            if (! $sharedInbox) {
                continue;
            }

            $this->tagImport->prepareConversationLookup($sharedInbox);

            foreach ($frontInbox['conversations'] ?? [] as $frontConversation) {
                if (! is_array($frontConversation)) {
                    continue;
                }

                $stats['conversations_scanned'] = ((int) ($stats['conversations_scanned'] ?? 0)) + 1;
                $this->importConversationComments(
                    $company,
                    null,
                    $sharedInbox,
                    $frontConversation,
                    $options,
                    $stats
                );
            }
        }

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $frontConversation
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $stats
     */
    private function importConversationComments(
        Company $company,
        ?FrontApiClient $client,
        SharedInbox $sharedInbox,
        array $frontConversation,
        array $options,
        array &$stats
    ): void {
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $frontConversationId = (string) ($frontConversation['id'] ?? '');
        $frontUpdatedAt = $this->frontTimestamp($frontConversation['updated_at'] ?? null);

        if (! $dryRun && $this->alreadySynced($company, $frontConversationId, $frontUpdatedAt)) {
            $stats['conversations_already_synced'] = ((int) ($stats['conversations_already_synced'] ?? 0)) + 1;

            return;
        }

        $localConversation = $this->tagImport->matchConversation($sharedInbox, $frontConversation);
        if (! $localConversation) {
            $stats['conversations_unmatched'] = ((int) ($stats['conversations_unmatched'] ?? 0)) + 1;
            $stats['unmatched_samples'] = $this->appendSample(
                $stats['unmatched_samples'] ?? [],
                $this->conversationLabel($frontConversation)
            );

            return;
        }

        $stats['conversations_matched'] = ((int) ($stats['conversations_matched'] ?? 0)) + 1;

        try {
            $comments = $this->commentsForConversation($client, $frontConversation, $frontConversationId);
        } catch (\Throwable $e) {
            $stats['comment_errors'] = $stats['comment_errors'] ?? [];
            $stats['comment_errors'][] = ($frontConversationId !== '' ? $frontConversationId : 'unknown').': '.$e->getMessage();

            return;
        }

        if ($comments === []) {
            if (! $dryRun) {
                $this->markSynced($company, $frontConversationId, $frontUpdatedAt);
            }

            return;
        }

        $stats['conversations_with_comments'] = ((int) ($stats['conversations_with_comments'] ?? 0)) + 1;

        foreach ($comments as $frontComment) {
            if (! is_array($frontComment)) {
                continue;
            }

            $this->importFrontComment($company, $client, $localConversation, $frontComment, $options, $stats);
        }

        if (! $dryRun) {
            $this->markSynced($company, $frontConversationId, $frontUpdatedAt);
        }
    }

    /**
     * @param  array<string, mixed>  $frontConversation
     * @return list<array<string, mixed>>
     */
    private function commentsForConversation(?FrontApiClient $client, array $frontConversation, string $frontConversationId): array
    {
        $embedded = $frontConversation['comments'] ?? null;
        if (is_array($embedded)) {
            return array_values(array_filter($embedded, 'is_array'));
        }

        if (! $client || $frontConversationId === '') {
            return [];
        }

        return $client->listConversationComments($frontConversationId);
    }

    /**
     * @param  array<string, mixed>  $frontComment
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $stats
     */
    private function importFrontComment(
        Company $company,
        ?FrontApiClient $client,
        InboxConversation $localConversation,
        array $frontComment,
        array $options,
        array &$stats
    ): void {
        $frontAttachments = $this->frontAttachments($frontComment);
        $body = trim((string) ($frontComment['body'] ?? ''));
        if ($body === '' && $frontAttachments === []) {
            return;
        }

        $frontCommentId = trim((string) ($frontComment['id'] ?? ''));
        $existing = $frontCommentId !== ''
            ? InboxConversationComment::query()->where('front_comment_id', $frontCommentId)->first()
            : null;

        if ($existing) {
            $stats['comments_existing'] = ((int) ($stats['comments_existing'] ?? 0)) + 1;
            if ($options['dry_run'] ?? false) {
                $this->countAttachmentPreview($frontAttachments, $existing, $stats);
            } else {
                $this->storeFrontCommentAttachments($client, $existing, $frontAttachments, $stats);
            }

            return;
        }

        [$html, $plain] = $this->commentBodies($body);
        if ($plain === '') {
            $plain = $this->attachmentFallbackText($frontAttachments);
            $html = $plain !== '' ? nl2br(e($plain), false) : '';
        }

        if ($plain === '') {
            return;
        }

        $author = is_array($frontComment['author'] ?? null) ? $frontComment['author'] : [];
        $importedName = $this->authorName($author);
        $importedEmail = strtolower(trim((string) ($author['email'] ?? ''))) ?: null;
        $user = $this->resolveAuthor($company, $author, $options['fallback_user_id'] ?? null);

        if (! $user) {
            $stats['comments_skipped_no_user'] = ((int) ($stats['comments_skipped_no_user'] ?? 0)) + 1;

            return;
        }

        $matchedByEmail = $importedEmail !== null && strtolower((string) $user->email) === $importedEmail;
        if (! $matchedByEmail) {
            $stats['comments_unmatched_author'] = ((int) ($stats['comments_unmatched_author'] ?? 0)) + 1;
        }

        $postedAt = $this->frontTimestamp($frontComment['posted_at'] ?? null);

        if ($options['dry_run'] ?? false) {
            $stats['comments_imported'] = ((int) ($stats['comments_imported'] ?? 0)) + 1;
            $this->countAttachmentPreview($frontAttachments, null, $stats);

            return;
        }

        $comment = new InboxConversationComment();
        $comment->fill([
            'inbox_conversation_id' => $localConversation->id,
            'user_id' => $user->id,
            'body_html' => $html,
            'body_text' => $plain,
            'mentioned_user_ids' => [],
            'attachments' => [],
            'front_comment_id' => $frontCommentId !== '' ? $frontCommentId : null,
            'imported_author_name' => $importedName !== '' ? $importedName : null,
            'imported_author_email' => $importedEmail,
        ]);
        $comment->created_at = $postedAt ?? now();
        $comment->updated_at = $postedAt ?? now();
        $comment->save();

        $this->storeFrontCommentAttachments($client, $comment, $frontAttachments, $stats);

        $stats['comments_imported'] = ((int) ($stats['comments_imported'] ?? 0)) + 1;
    }

    /**
     * @param  array<string, mixed>  $frontComment
     * @return list<array<string, mixed>>
     */
    private function frontAttachments(array $frontComment): array
    {
        $items = [];
        foreach ($frontComment['attachments'] ?? [] as $attachment) {
            if (is_array($attachment)) {
                $items[] = $attachment;
            }
        }

        return array_slice($items, 0, self::MAX_COMMENT_ATTACHMENTS);
    }

    /**
     * @param  list<array<string, mixed>>  $frontAttachments
     */
    private function attachmentFallbackText(array $frontAttachments): string
    {
        $names = [];
        foreach ($frontAttachments as $attachment) {
            $name = trim((string) ($attachment['filename'] ?? $attachment['name'] ?? ''));
            if ($name !== '') {
                $names[] = $name;
            }
        }

        if ($names === []) {
            return $frontAttachments !== [] ? 'Attachment' : '';
        }

        return implode(', ', $names);
    }

    /**
     * @param  list<array<string, mixed>>  $frontAttachments
     * @param  array<string, mixed>  $stats
     */
    private function countAttachmentPreview(array $frontAttachments, ?InboxConversationComment $existing, array &$stats): void
    {
        if ($existing && $this->commentHasStoredAttachments($existing)) {
            return;
        }

        foreach ($frontAttachments as $attachment) {
            if ($this->attachmentHasImportableSource($attachment)) {
                $stats['attachments_imported'] = ((int) ($stats['attachments_imported'] ?? 0)) + 1;
            } else {
                $stats['attachments_failed'] = ((int) ($stats['attachments_failed'] ?? 0)) + 1;
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $frontAttachments
     * @param  array<string, mixed>  $stats
     */
    private function storeFrontCommentAttachments(
        ?FrontApiClient $client,
        InboxConversationComment $comment,
        array $frontAttachments,
        array &$stats
    ): void {
        if ($frontAttachments === [] || $this->commentHasStoredAttachments($comment)) {
            return;
        }

        $stored = [];
        foreach ($frontAttachments as $index => $attachment) {
            $file = $this->resolveAttachmentBinary($client, $attachment);
            if ($file === null) {
                $stats['attachments_failed'] = ((int) ($stats['attachments_failed'] ?? 0)) + 1;

                continue;
            }

            $safeName = Str::slug(pathinfo($file['name'], PATHINFO_FILENAME)) ?: 'file';
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = $safeName.($ext !== '' ? '.'.$ext : '');
            $path = "inbox-comments/{$comment->id}/{$index}_{$filename}";
            Storage::disk('local')->put($path, $file['binary']);
            $stored[] = [
                'name' => $file['name'],
                'content_type' => $file['content_type'],
                'size' => strlen($file['binary']),
                'path' => $path,
                'index' => $index,
            ];
            $stats['attachments_imported'] = ((int) ($stats['attachments_imported'] ?? 0)) + 1;
        }

        if ($stored !== []) {
            $comment->update(['attachments' => $stored]);
        }
    }

    /**
     * @param  array<string, mixed>  $attachment
     * @return array{name: string, content_type: string, binary: string}|null
     */
    private function resolveAttachmentBinary(?FrontApiClient $client, array $attachment): ?array
    {
        $name = trim((string) ($attachment['filename'] ?? $attachment['name'] ?? ''));
        if ($name === '') {
            $name = 'attachment';
        }

        $contentType = trim((string) ($attachment['content_type'] ?? $attachment['contentType'] ?? ''));
        $reportedSize = (int) ($attachment['size'] ?? 0);
        if ($reportedSize > self::MAX_ATTACHMENT_BYTES) {
            return null;
        }

        $embedded = $attachment['content'] ?? $attachment['content_bytes'] ?? $attachment['contentBytes'] ?? $attachment['data'] ?? null;
        if (is_string($embedded) && $embedded !== '') {
            $binary = base64_decode($embedded, true);
            if ($binary === false || $binary === '' || strlen($binary) > self::MAX_ATTACHMENT_BYTES) {
                return null;
            }

            return [
                'name' => $name,
                'content_type' => $contentType !== '' ? $contentType : 'application/octet-stream',
                'binary' => $binary,
            ];
        }

        $url = $this->attachmentDownloadUrl($attachment);
        if (! $client || $url === '') {
            return null;
        }

        try {
            $downloaded = $client->download($url);
        } catch (\Throwable) {
            return null;
        }

        $binary = $downloaded['body'];
        if ($binary === '' || strlen($binary) > self::MAX_ATTACHMENT_BYTES) {
            return null;
        }

        return [
            'name' => $name,
            'content_type' => $contentType !== '' ? $contentType : $downloaded['content_type'],
            'binary' => $binary,
        ];
    }

    /**
     * @param  array<string, mixed>  $attachment
     */
    private function attachmentHasImportableSource(array $attachment): bool
    {
        $embedded = $attachment['content'] ?? $attachment['content_bytes'] ?? $attachment['contentBytes'] ?? $attachment['data'] ?? null;
        if (is_string($embedded) && $embedded !== '') {
            return true;
        }

        return $this->attachmentDownloadUrl($attachment) !== '';
    }

    /**
     * @param  array<string, mixed>  $attachment
     */
    private function attachmentDownloadUrl(array $attachment): string
    {
        $url = trim((string) ($attachment['url'] ?? ''));
        if ($url !== '') {
            return $url;
        }

        $id = trim((string) ($attachment['id'] ?? ''));
        if ($id === '') {
            return '';
        }

        return '/download/'.rawurlencode($id);
    }

    private function commentHasStoredAttachments(InboxConversationComment $comment): bool
    {
        $attachments = $comment->attachments ?? [];

        return is_array($attachments) && $attachments !== [];
    }

    /**
     * @param  array<string, mixed>  $author
     */
    private function resolveAuthor(Company $company, array $author, ?int $fallbackUserId): ?User
    {
        $lookup = $this->userLookup($company, $fallbackUserId);
        $email = strtolower(trim((string) ($author['email'] ?? '')));
        if ($email !== '' && isset($lookup['by_email'][$email])) {
            return $lookup['by_email'][$email];
        }

        $name = $this->normalizeName($this->authorName($author));
        if ($name !== '' && isset($lookup['by_name'][$name])) {
            return $lookup['by_name'][$name];
        }

        return $lookup['fallback'];
    }

    /**
     * @return array{by_email: array<string, User>, by_name: array<string, User>, fallback: User|null}
     */
    private function userLookup(Company $company, ?int $fallbackUserId): array
    {
        if (isset($this->userLookupCache[$company->id])) {
            return $this->userLookupCache[$company->id];
        }

        $users = User::query()
            ->where('company_id', $company->id)
            ->orderBy('id')
            ->get(['id', 'name', 'email', 'company_id']);

        $byEmail = [];
        $byName = [];
        foreach ($users as $user) {
            $email = strtolower(trim((string) $user->email));
            if ($email !== '' && ! isset($byEmail[$email])) {
                $byEmail[$email] = $user;
            }

            $name = $this->normalizeName((string) $user->name);
            if ($name !== '' && ! isset($byName[$name])) {
                $byName[$name] = $user;
            }
        }

        $fallback = $fallbackUserId
            ? $users->firstWhere('id', $fallbackUserId)
            : $users->first();

        return $this->userLookupCache[$company->id] = [
            'by_email' => $byEmail,
            'by_name' => $byName,
            'fallback' => $fallback,
        ];
    }

    /**
     * @param  array<string, mixed>  $author
     */
    private function authorName(array $author): string
    {
        $full = trim(trim((string) ($author['first_name'] ?? '')).' '.trim((string) ($author['last_name'] ?? '')));
        if ($full !== '') {
            return $full;
        }

        $username = trim((string) ($author['username'] ?? ''));
        if ($username !== '') {
            return $username;
        }

        return trim((string) ($author['email'] ?? ''));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function commentBodies(string $body): array
    {
        $stripped = trim(html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($body !== strip_tags($body)) {
            $html = strip_tags($body, '<p><br><br/><a><strong><em><b><i><ul><ol><li><code><pre><blockquote><span>');

            return [$html, $stripped];
        }

        return [nl2br(e($body), false), $stripped !== '' ? $stripped : trim($body)];
    }

    private function alreadySynced(Company $company, string $frontConversationId, ?Carbon $frontUpdatedAt): bool
    {
        if ($frontConversationId === '') {
            return false;
        }

        $record = FrontSyncedCommentConversation::query()
            ->where('company_id', $company->id)
            ->where('front_conversation_id', $frontConversationId)
            ->first();

        if (! $record) {
            return false;
        }

        if (! $frontUpdatedAt || ! $record->front_updated_at) {
            return true;
        }

        return $record->front_updated_at->gte($frontUpdatedAt);
    }

    private function markSynced(Company $company, string $frontConversationId, ?Carbon $frontUpdatedAt): void
    {
        if ($frontConversationId === '') {
            return;
        }

        FrontSyncedCommentConversation::query()->updateOrCreate(
            ['company_id' => $company->id, 'front_conversation_id' => $frontConversationId],
            ['front_updated_at' => $frontUpdatedAt]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyStats(): array
    {
        return [
            'mapped_inboxes' => 0,
            'conversations_scanned' => 0,
            'conversations_already_synced' => 0,
            'conversations_matched' => 0,
            'conversations_unmatched' => 0,
            'conversations_with_comments' => 0,
            'comments_imported' => 0,
            'comments_existing' => 0,
            'comments_unmatched_author' => 0,
            'comments_skipped_no_user' => 0,
            'attachments_imported' => 0,
            'attachments_failed' => 0,
            'unmatched_samples' => [],
        ];
    }

    /**
     * @param  list<string>  $samples
     * @return list<string>
     */
    private function appendSample(array $samples, string $label): array
    {
        if (in_array($label, $samples, true) || count($samples) >= 10) {
            return $samples;
        }

        $samples[] = $label;

        return $samples;
    }

    /**
     * @param  array<string, mixed>  $frontConversation
     */
    private function conversationLabel(array $frontConversation): string
    {
        $subject = trim((string) ($frontConversation['subject'] ?? '(no subject)'));
        $recipient = trim((string) ($frontConversation['recipient']['handle'] ?? 'unknown'));

        return "{$recipient} — {$subject}";
    }

    private function normalizeName(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    private function frontTimestamp(mixed $value): ?Carbon
    {
        if (! is_numeric($value)) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $value);
    }
}
