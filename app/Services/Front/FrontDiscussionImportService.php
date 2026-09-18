<?php

namespace App\Services\Front;

use App\Models\Company;
use App\Models\Conversation;
use App\Models\FrontDiscussionImportProgress;
use App\Models\FrontSyncedDiscussion;
use App\Models\Message;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FrontDiscussionImportService
{
    public const DRY_RUN_LIMIT = 100;

    /** @var array<int, array{by_email: array<string, User>, by_name: array<string, User>, fallback: User|null}> */
    private array $userLookupCache = [];

    /**
     * @param  array{
     *     dry_run?: bool,
     *     statuses?: list<string>,
     *     fallback_user_id?: int|null,
     * }  $options
     * @return array<string, mixed>
     */
    public function importFromApi(Company $company, FrontApiClient $client, array $options = []): array
    {
        $stats = $this->emptyStats();
        $pageUrl = null;

        do {
            $pageStats = $this->importPageBatch($company, $client, $options, $pageUrl);
            $this->mergeStats($stats, $pageStats);
            $pageUrl = ($pageStats['has_more'] ?? false) ? ($pageStats['next_page_url'] ?? null) : null;
        } while ($pageUrl);

        return $stats;
    }

    /**
     * Import one page of company conversations, keeping only Front discussions.
     *
     * @param  array{
     *     dry_run?: bool,
     *     statuses?: list<string>,
     *     fallback_user_id?: int|null,
     * }  $options
     * @return array<string, mixed>
     */
    public function importPageBatch(
        Company $company,
        FrontApiClient $client,
        array $options = [],
        ?string $pageUrl = null,
    ): array {
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $isDryRunPreview = $dryRun && $pageUrl === null;

        $resumedFrom = 0;
        if (! $dryRun && $pageUrl === null) {
            $progress = FrontDiscussionImportProgress::query()
                ->where('company_id', $company->id)
                ->first();

            if ($progress?->next_page_url) {
                $pageUrl = $progress->next_page_url;
                $resumedFrom = (int) $progress->conversations_done;
            }
        }

        $statuses = $options['statuses'] ?? ['archived', 'assigned', 'unassigned'];
        $pageLimit = $isDryRunPreview ? self::DRY_RUN_LIMIT : 20;
        $page = $client->fetchCompanyConversationPage($pageUrl, $statuses, $pageLimit);

        $stats = $this->emptyStats();
        $stats['page_conversations'] = count($page['results']);
        if ($resumedFrom > 0) {
            $stats['resumed_from'] = $resumedFrom;
        }

        foreach ($page['results'] as $frontConversation) {
            if (! is_array($frontConversation)) {
                continue;
            }

            $stats['conversations_scanned'] = ((int) ($stats['conversations_scanned'] ?? 0)) + 1;
            $this->importDiscussion($company, $client, $frontConversation, $options, $stats);
        }

        if ($isDryRunPreview) {
            $stats['preview_limit'] = self::DRY_RUN_LIMIT;
            $stats['preview_limited'] = ($page['next_page_url'] ?? null) !== null
                || count($page['results']) >= self::DRY_RUN_LIMIT;
        }

        $hasMore = $isDryRunPreview ? false : $page['next_page_url'] !== null;

        if (! $dryRun) {
            if ($hasMore) {
                FrontDiscussionImportProgress::query()->updateOrCreate(
                    ['company_id' => $company->id],
                    [
                        'next_page_url' => $page['next_page_url'],
                        'conversations_done' => $resumedFrom + count($page['results']),
                    ]
                );
            } else {
                FrontDiscussionImportProgress::query()
                    ->where('company_id', $company->id)
                    ->delete();
            }
        }

        return array_merge($stats, [
            'has_more' => $hasMore,
            'next_page_url' => $isDryRunPreview ? null : $page['next_page_url'],
        ]);
    }

    public function resetProgress(Company $company): void
    {
        FrontDiscussionImportProgress::query()->where('company_id', $company->id)->delete();
        FrontSyncedDiscussion::query()->where('company_id', $company->id)->delete();
    }

    /**
     * @param  array{
     *     dry_run?: bool,
     *     fallback_user_id?: int|null,
     * }  $options
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

        $stats = $this->emptyStats();

        foreach ($payload['discussions'] ?? [] as $frontConversation) {
            if (! is_array($frontConversation)) {
                continue;
            }

            $stats['conversations_scanned'] = ((int) ($stats['conversations_scanned'] ?? 0)) + 1;
            $this->importDiscussion($company, null, $frontConversation, $options, $stats, true);
        }

        foreach ($payload['conversations'] ?? [] as $frontConversation) {
            if (! is_array($frontConversation)) {
                continue;
            }

            $stats['conversations_scanned'] = ((int) ($stats['conversations_scanned'] ?? 0)) + 1;
            $this->importDiscussion($company, null, $frontConversation, $options, $stats);
        }

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $frontConversation
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $stats
     */
    private function importDiscussion(
        Company $company,
        ?FrontApiClient $client,
        array $frontConversation,
        array $options,
        array &$stats,
        bool $forceDiscussion = false
    ): void {
        if (! $forceDiscussion && ! $this->isDiscussion($frontConversation)) {
            $stats['conversations_skipped'] = ((int) ($stats['conversations_skipped'] ?? 0)) + 1;

            return;
        }

        $dryRun = (bool) ($options['dry_run'] ?? false);
        $frontConversationId = (string) ($frontConversation['id'] ?? '');
        $frontUpdatedAt = $this->frontTimestamp($frontConversation['updated_at'] ?? null);

        $stats['discussions_found'] = ((int) ($stats['discussions_found'] ?? 0)) + 1;

        if (! $dryRun && $this->alreadySynced($company, $frontConversationId, $frontUpdatedAt)) {
            $stats['discussions_already_synced'] = ((int) ($stats['discussions_already_synced'] ?? 0)) + 1;

            return;
        }

        try {
            $comments = $this->commentsForConversation($client, $frontConversation, $frontConversationId);
        } catch (\Throwable $e) {
            $stats['comment_errors'] = $stats['comment_errors'] ?? [];
            $stats['comment_errors'][] = ($frontConversationId !== '' ? $frontConversationId : 'unknown').': '.$e->getMessage();

            return;
        }

        $followers = $this->followersForConversation($client, $frontConversation, $frontConversationId);
        $teammates = $this->collectTeammates($frontConversation, $followers, $comments);
        $participants = $this->resolveParticipants($company, $teammates, $options['fallback_user_id'] ?? null);

        if ($participants === []) {
            $stats['discussions_skipped_no_users'] = ((int) ($stats['discussions_skipped_no_users'] ?? 0)) + 1;
            $stats['unmatched_samples'] = $this->appendSample(
                $stats['unmatched_samples'] ?? [],
                $this->discussionLabel($frontConversation).' (no matching CRM users)'
            );

            return;
        }

        if ($comments === []) {
            if ($dryRun) {
                $stats['discussions_imported'] = ((int) ($stats['discussions_imported'] ?? 0)) + 1;

                return;
            }

            $conversation = $this->upsertConversation($company, $frontConversation, $participants);
            $this->markSynced($company, $frontConversationId, $conversation->id, $frontUpdatedAt);
            $stats['discussions_imported'] = ((int) ($stats['discussions_imported'] ?? 0)) + 1;

            return;
        }

        $stats['discussions_with_comments'] = ((int) ($stats['discussions_with_comments'] ?? 0)) + 1;

        if ($dryRun) {
            $stats['discussions_imported'] = ((int) ($stats['discussions_imported'] ?? 0)) + 1;
            foreach ($comments as $frontComment) {
                if (! is_array($frontComment)) {
                    continue;
                }
                $this->countCommentPreview($company, $frontComment, $options, $stats);
            }

            return;
        }

        $conversation = DB::transaction(function () use ($company, $frontConversation, $participants, $comments, $options, &$stats) {
            $conversation = $this->upsertConversation($company, $frontConversation, $participants);

            foreach ($this->sortedComments($comments) as $frontComment) {
                if (! is_array($frontComment)) {
                    continue;
                }

                $this->importFrontComment($company, $conversation, $frontComment, $options, $stats);
            }

            return $conversation;
        });

        $this->markSynced($company, $frontConversationId, $conversation->id, $frontUpdatedAt);
        $stats['discussions_imported'] = ((int) ($stats['discussions_imported'] ?? 0)) + 1;
    }

    /**
     * @param  array<string, mixed>  $frontConversation
     */
    private function isDiscussion(array $frontConversation): bool
    {
        return strtolower(trim((string) ($frontConversation['type'] ?? ''))) === 'discussion';
    }

    /**
     * @param  list<User>  $participants
     */
    private function upsertConversation(Company $company, array $frontConversation, array $participants): Conversation
    {
        $frontConversationId = trim((string) ($frontConversation['id'] ?? ''));
        $subject = $this->discussionName($frontConversation);
        $creatorId = $participants[0]->id;

        $conversation = null;
        if ($frontConversationId !== '') {
            $conversation = Conversation::query()
                ->where('company_id', $company->id)
                ->where('front_conversation_id', $frontConversationId)
                ->first();
        }

        if (! $conversation) {
            $conversation = Conversation::query()->create([
                'company_id' => $company->id,
                'type' => 'group',
                'name' => $subject,
                'created_by' => $creatorId,
                'front_conversation_id' => $frontConversationId !== '' ? $frontConversationId : null,
            ]);
        } else {
            $conversation->forceFill([
                'name' => $subject,
            ])->save();
        }

        $existingIds = $conversation->participants()->pluck('users.id')->all();
        foreach ($participants as $user) {
            if (in_array($user->id, $existingIds, true)) {
                continue;
            }

            $conversation->participants()->attach($user->id, [
                'last_read_at' => now(),
            ]);
            $existingIds[] = $user->id;
        }

        return $conversation;
    }

    /**
     * @param  array<string, mixed>  $frontComment
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $stats
     */
    private function importFrontComment(
        Company $company,
        Conversation $conversation,
        array $frontComment,
        array $options,
        array &$stats
    ): void {
        $frontCommentId = trim((string) ($frontComment['id'] ?? ''));
        if ($frontCommentId !== '' && Message::query()->where('front_comment_id', $frontCommentId)->exists()) {
            $stats['messages_existing'] = ((int) ($stats['messages_existing'] ?? 0)) + 1;

            return;
        }

        $body = $this->commentBody($frontComment);
        if ($body === '') {
            return;
        }

        $author = is_array($frontComment['author'] ?? null) ? $frontComment['author'] : [];
        $importedName = $this->authorName($author);
        $importedEmail = strtolower(trim((string) ($author['email'] ?? ''))) ?: null;
        $user = $this->resolveAuthor($company, $author, $options['fallback_user_id'] ?? null);

        if (! $user) {
            $stats['messages_skipped_no_user'] = ((int) ($stats['messages_skipped_no_user'] ?? 0)) + 1;

            return;
        }

        $matchedByEmail = $importedEmail !== null && strtolower((string) $user->email) === $importedEmail;
        if (! $matchedByEmail) {
            $stats['messages_unmatched_author'] = ((int) ($stats['messages_unmatched_author'] ?? 0)) + 1;
            if ($importedName !== '') {
                $body = $importedName.': '.$body;
            }
        }

        $postedAt = $this->frontTimestamp($frontComment['posted_at'] ?? null) ?? now();

        $message = new Message();
        $message->fill([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'body' => $body,
            'front_comment_id' => $frontCommentId !== '' ? $frontCommentId : null,
        ]);
        $message->created_at = $postedAt;
        $message->updated_at = $postedAt;
        $message->save();

        if (! $conversation->participants()->where('users.id', $user->id)->exists()) {
            $conversation->participants()->attach($user->id, ['last_read_at' => now()]);
        }

        $stats['messages_imported'] = ((int) ($stats['messages_imported'] ?? 0)) + 1;
    }

    /**
     * @param  array<string, mixed>  $frontComment
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $stats
     */
    private function countCommentPreview(Company $company, array $frontComment, array $options, array &$stats): void
    {
        $frontCommentId = trim((string) ($frontComment['id'] ?? ''));
        if ($frontCommentId !== '' && Message::query()->where('front_comment_id', $frontCommentId)->exists()) {
            $stats['messages_existing'] = ((int) ($stats['messages_existing'] ?? 0)) + 1;

            return;
        }

        if ($this->commentBody($frontComment) === '') {
            return;
        }

        $author = is_array($frontComment['author'] ?? null) ? $frontComment['author'] : [];
        $importedEmail = strtolower(trim((string) ($author['email'] ?? ''))) ?: null;
        $user = $this->resolveAuthor($company, $author, $options['fallback_user_id'] ?? null);

        if (! $user) {
            $stats['messages_skipped_no_user'] = ((int) ($stats['messages_skipped_no_user'] ?? 0)) + 1;

            return;
        }

        $matchedByEmail = $importedEmail !== null && strtolower((string) $user->email) === $importedEmail;
        if (! $matchedByEmail) {
            $stats['messages_unmatched_author'] = ((int) ($stats['messages_unmatched_author'] ?? 0)) + 1;
        }

        $stats['messages_imported'] = ((int) ($stats['messages_imported'] ?? 0)) + 1;
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
     * @param  array<string, mixed>  $frontConversation
     * @return list<array<string, mixed>>
     */
    private function followersForConversation(?FrontApiClient $client, array $frontConversation, string $frontConversationId): array
    {
        $embedded = $frontConversation['followers'] ?? $frontConversation['teammates'] ?? null;
        if (is_array($embedded)) {
            return array_values(array_filter($embedded, 'is_array'));
        }

        if (! $client || $frontConversationId === '') {
            return [];
        }

        try {
            return $client->listConversationFollowers($frontConversationId);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $frontConversation
     * @param  list<array<string, mixed>>  $followers
     * @param  list<array<string, mixed>>  $comments
     * @return list<array<string, mixed>>
     */
    private function collectTeammates(array $frontConversation, array $followers, array $comments): array
    {
        $teammates = [];

        if (is_array($frontConversation['assignee'] ?? null)) {
            $teammates[] = $frontConversation['assignee'];
        }

        foreach ($followers as $follower) {
            $teammates[] = $follower;
        }

        foreach ($comments as $comment) {
            if (is_array($comment['author'] ?? null)) {
                $teammates[] = $comment['author'];
            }
        }

        $unique = [];
        foreach ($teammates as $teammate) {
            $key = strtolower(trim((string) ($teammate['email'] ?? $teammate['id'] ?? '')));
            if ($key === '') {
                $key = $this->normalizeName($this->authorName($teammate));
            }
            if ($key === '' || isset($unique[$key])) {
                continue;
            }
            $unique[$key] = $teammate;
        }

        return array_values($unique);
    }

    /**
     * @param  list<array<string, mixed>>  $teammates
     * @return list<User>
     */
    private function resolveParticipants(Company $company, array $teammates, ?int $fallbackUserId): array
    {
        $users = [];
        $seen = [];

        foreach ($teammates as $teammate) {
            $user = $this->matchUser($company, $teammate);
            if (! $user || isset($seen[$user->id])) {
                continue;
            }
            $seen[$user->id] = true;
            $users[] = $user;
        }

        if ($users === []) {
            $fallback = $this->userLookup($company, $fallbackUserId)['fallback'];
            if ($fallback) {
                $users[] = $fallback;
            }
        }

        return $users;
    }

    /**
     * @param  array<string, mixed>  $author
     */
    private function matchUser(Company $company, array $author): ?User
    {
        $lookup = $this->userLookup($company, null);
        $email = strtolower(trim((string) ($author['email'] ?? '')));
        if ($email !== '' && isset($lookup['by_email'][$email])) {
            return $lookup['by_email'][$email];
        }

        $name = $this->normalizeName($this->authorName($author));
        if ($name !== '' && isset($lookup['by_name'][$name])) {
            return $lookup['by_name'][$name];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $author
     */
    private function resolveAuthor(Company $company, array $author, ?int $fallbackUserId): ?User
    {
        return $this->matchUser($company, $author)
            ?? $this->userLookup($company, $fallbackUserId)['fallback'];
    }

    /**
     * @return array{by_email: array<string, User>, by_name: array<string, User>, fallback: User|null}
     */
    private function userLookup(Company $company, ?int $fallbackUserId): array
    {
        if (isset($this->userLookupCache[$company->id])) {
            $cached = $this->userLookupCache[$company->id];
            if ($fallbackUserId) {
                $cached['fallback'] = User::query()
                    ->where('company_id', $company->id)
                    ->where('id', $fallbackUserId)
                    ->first() ?: $cached['fallback'];
            }

            return $cached;
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
            ? ($users->firstWhere('id', $fallbackUserId) ?: $users->first())
            : $users->first();

        return $this->userLookupCache[$company->id] = [
            'by_email' => $byEmail,
            'by_name' => $byName,
            'fallback' => $fallback,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $comments
     * @return list<array<string, mixed>>
     */
    private function sortedComments(array $comments): array
    {
        usort($comments, function (array $a, array $b): int {
            return ((int) ($a['posted_at'] ?? 0)) <=> ((int) ($b['posted_at'] ?? 0));
        });

        return $comments;
    }

    /**
     * @param  array<string, mixed>  $frontComment
     */
    private function commentBody(array $frontComment): string
    {
        $body = trim((string) ($frontComment['body'] ?? ''));
        $plain = trim(html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        $attachmentNames = [];
        foreach ($frontComment['attachments'] ?? [] as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }
            $name = trim((string) ($attachment['filename'] ?? $attachment['name'] ?? ''));
            if ($name !== '') {
                $attachmentNames[] = $name;
            }
        }

        if ($attachmentNames !== []) {
            $plain = trim($plain."\n\n[Attachment: ".implode(', ', $attachmentNames).']');
        }

        return $plain;
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
     * @param  array<string, mixed>  $frontConversation
     */
    private function discussionName(array $frontConversation): string
    {
        $subject = trim((string) ($frontConversation['subject'] ?? ''));

        return $subject !== '' ? $subject : 'Front discussion';
    }

    /**
     * @param  array<string, mixed>  $frontConversation
     */
    private function discussionLabel(array $frontConversation): string
    {
        $id = trim((string) ($frontConversation['id'] ?? ''));

        return $this->discussionName($frontConversation).($id !== '' ? " ({$id})" : '');
    }

    private function alreadySynced(Company $company, string $frontConversationId, ?Carbon $frontUpdatedAt): bool
    {
        if ($frontConversationId === '') {
            return false;
        }

        $record = FrontSyncedDiscussion::query()
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

    private function markSynced(Company $company, string $frontConversationId, int $conversationId, ?Carbon $frontUpdatedAt): void
    {
        if ($frontConversationId === '') {
            return;
        }

        FrontSyncedDiscussion::query()->updateOrCreate(
            ['company_id' => $company->id, 'front_conversation_id' => $frontConversationId],
            [
                'conversation_id' => $conversationId,
                'front_updated_at' => $frontUpdatedAt,
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyStats(): array
    {
        return [
            'conversations_scanned' => 0,
            'conversations_skipped' => 0,
            'discussions_found' => 0,
            'discussions_already_synced' => 0,
            'discussions_imported' => 0,
            'discussions_with_comments' => 0,
            'discussions_skipped_no_users' => 0,
            'messages_imported' => 0,
            'messages_existing' => 0,
            'messages_unmatched_author' => 0,
            'messages_skipped_no_user' => 0,
            'unmatched_samples' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $target
     * @param  array<string, mixed>  $source
     */
    private function mergeStats(array &$target, array $source): void
    {
        foreach ([
            'conversations_scanned',
            'conversations_skipped',
            'discussions_found',
            'discussions_already_synced',
            'discussions_imported',
            'discussions_with_comments',
            'discussions_skipped_no_users',
            'messages_imported',
            'messages_existing',
            'messages_unmatched_author',
            'messages_skipped_no_user',
        ] as $key) {
            $target[$key] = ((int) ($target[$key] ?? 0)) + ((int) ($source[$key] ?? 0));
        }

        if (! empty($source['preview_limit'])) {
            $target['preview_limit'] = $source['preview_limit'];
        }
        if (! empty($source['preview_limited'])) {
            $target['preview_limited'] = true;
        }
        if (! empty($source['comment_errors']) && is_array($source['comment_errors'])) {
            $target['comment_errors'] = array_merge($target['comment_errors'] ?? [], $source['comment_errors']);
        }
        foreach ($source['unmatched_samples'] ?? [] as $sample) {
            $target['unmatched_samples'] = $this->appendSample($target['unmatched_samples'] ?? [], (string) $sample);
        }
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

    private function normalizeName(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    private function frontTimestamp(mixed $value): ?Carbon
    {
        if (! is_numeric($value)) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $value, 'UTC')->timezone((string) config('app.timezone'));
    }
}
