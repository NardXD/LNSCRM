<?php

namespace App\Services\Front;

use App\Models\Company;
use App\Models\InboxConversation;
use App\Models\InboxTag;
use App\Models\Lead;
use App\Models\LeadLabel;
use App\Models\SharedInbox;
use App\Services\FlexCrmLookupService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FrontTagImportService
{
    public const DRY_RUN_LIMIT = 100;

    public function __construct(
        private readonly FlexCrmLookupService $crmLookup,
    ) {}

    /** @var array<string, string> */
    public const HIGHLIGHT_COLORS = [
        'grey' => '#64748b',
        'pink' => '#ec4899',
        'red' => '#ef4444',
        'orange' => '#f97316',
        'yellow' => '#eab308',
        'green' => '#22c55e',
        'light-blue' => '#38bdf8',
        'blue' => '#3b82f6',
        'purple' => '#a855f7',
    ];

    /**
     * @var array<int, array{
     *     by_external: array<string, InboxConversation>,
     *     by_email_subject: array<string, list<InboxConversation>>
     * }>
     */
    private array $conversationLookupCache = [];

    /**
     * @param  array{
     *     dry_run?: bool,
     *     include_private?: bool,
     *     inbox_map?: array<string, int|string>,
     *     front_inbox_id?: string|null,
     *     shared_inbox_id?: int|null,
     *     statuses?: list<string>,
     * }  $options
     * @return array<string, int|list<string>>
     */
    public function importFromApi(Company $company, FrontApiClient $client, array $options = []): array
    {
        if ($options['dry_run'] ?? false) {
            $options['max_conversations'] = self::DRY_RUN_LIMIT;
        }

        $sharedInboxes = $this->loadSharedInboxes($company, $options['shared_inbox_id'] ?? null);
        if ($sharedInboxes->isEmpty()) {
            throw new RuntimeException('No active shared inboxes found in LNSCRM. Connect Outlook mailboxes under Inbox first.');
        }

        $manualMap = $options['inbox_map'] ?? [];
        $frontInboxFilter = $options['front_inbox_id'] ?? null;

        if ($manualMap !== [] || $frontInboxFilter) {
            $inboxMap = $this->resolveInboxMap([], $sharedInboxes, $manualMap, $frontInboxFilter);
            if ($inboxMap === []) {
                throw new RuntimeException('None of the selected Front inbox mappings matched a local shared inbox.');
            }

            return $this->importFromApiViaInboxes($company, $client, $sharedInboxes, $inboxMap, $options);
        }

        $inboxListingError = null;

        try {
            $frontInboxes = $client->listInboxes();
            $inboxMap = $this->resolveInboxMap($frontInboxes, $sharedInboxes, [], null);

            if ($inboxMap !== []) {
                return $this->importFromApiViaInboxes($company, $client, $sharedInboxes, $inboxMap, $options);
            }
        } catch (\Throwable $e) {
            $inboxListingError = $e->getMessage();
        }

        $stats = $this->importFromApiViaTags($company, $client, $sharedInboxes, $options);
        $stats['import_mode'] = 'tags';
        if ($inboxListingError) {
            $stats['front_inbox_warning'] = $inboxListingError;
        }

        return $stats;
    }

    /**
     * @param  Collection<int, SharedInbox>  $sharedInboxes
     * @param  array<string, int>  $inboxMap
     * @param  array<string, mixed>  $options
     * @return array<string, int|list<string>>
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

            $statuses = $options['statuses'] ?? ['archived', 'assigned', 'unassigned'];
            $maxConversations = $options['max_conversations'] ?? null;
            $scanned = 0;

            try {
                foreach ($client->listInboxConversations($frontInboxId, $statuses) as $frontConversation) {
                    $scanned++;
                    $stats['conversations_scanned'] = $scanned;

                    if ($maxConversations !== null && $scanned > $maxConversations) {
                        $stats['preview_limit'] = $maxConversations;
                        $stats['preview_limited'] = true;
                        break 2;
                    }

                    $this->importConversationTags(
                        $company,
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

        if (! empty($stats['inbox_errors']) && (int) ($stats['conversations_matched'] ?? 0) === 0) {
            throw new RuntimeException(implode(' ', $stats['inbox_errors']));
        }

        return $stats;
    }

    /**
     * Import one page of conversations from a mapped Front inbox (for UI batching).
     *
     * @param  array{
     *     dry_run?: bool,
     *     include_private?: bool,
     *     statuses?: list<string>,
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
        $sharedInboxes = $this->loadSharedInboxes($company, $sharedInboxId);
        $sharedInbox = $sharedInboxes->first();
        if (! $sharedInbox) {
            throw new RuntimeException("Shared inbox {$sharedInboxId} not found.");
        }

        if (! isset($this->conversationLookupCache[$sharedInbox->id])) {
            $this->warmConversationLookup($sharedInbox);
        }

        $statuses = $options['statuses'] ?? ['archived', 'assigned', 'unassigned'];
        $isDryRunPreview = ($options['dry_run'] ?? false) && $pageUrl === null;
        $pageLimit = $isDryRunPreview ? self::DRY_RUN_LIMIT : 20;
        $page = $client->fetchInboxConversationPage($frontInboxId, $pageUrl, $statuses, $pageLimit);

        $stats = $this->emptyStats();
        $stats['mapped_inboxes'] = 1;
        $stats['import_mode'] = 'inboxes';
        $stats['page_conversations'] = count($page['results']);

        foreach ($page['results'] as $frontConversation) {
            $stats['conversations_scanned'] = ((int) ($stats['conversations_scanned'] ?? 0)) + 1;
            $this->importConversationTags(
                $company,
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

        return array_merge($stats, [
            'has_more' => $isDryRunPreview ? false : $page['next_page_url'] !== null,
            'next_page_url' => $isDryRunPreview ? null : $page['next_page_url'],
            'front_inbox_id' => $frontInboxId,
        ]);
    }

    /**
     * @param  Collection<int, SharedInbox>  $sharedInboxes
     * @param  array<string, mixed>  $options
     * @return array<string, int|list<string>>
     */
    private function importFromApiViaTags(
        Company $company,
        FrontApiClient $client,
        Collection $sharedInboxes,
        array $options
    ): array {
        $stats = $this->emptyStats();
        $stats['mapped_inboxes'] = $sharedInboxes->count();
        $seenConversationIds = [];
        $statuses = $options['statuses'] ?? ['archived', 'assigned', 'unassigned'];
        $maxConversations = $options['max_conversations'] ?? null;
        $scanned = 0;

        foreach ($client->listTags() as $frontTag) {
            if (! ($options['include_private'] ?? false) && (bool) ($frontTag['is_private'] ?? false)) {
                continue;
            }

            $tagId = (string) ($frontTag['id'] ?? '');
            if ($tagId === '') {
                continue;
            }

            foreach ($client->listTaggedConversations($tagId, $statuses) as $frontConversation) {
                $scanned++;
                $stats['conversations_scanned'] = $scanned;

                if ($maxConversations !== null && $scanned > $maxConversations) {
                    $stats['preview_limit'] = $maxConversations;
                    $stats['preview_limited'] = true;
                    break 2;
                }

                $frontConversationId = (string) ($frontConversation['id'] ?? '');
                if ($frontConversationId !== '' && isset($seenConversationIds[$frontConversationId])) {
                    continue;
                }

                if ($frontConversationId !== '') {
                    $seenConversationIds[$frontConversationId] = true;
                }

                if (collect($frontConversation['tags'] ?? [])->isEmpty()) {
                    continue;
                }

                $matched = $this->matchConversationAcrossInboxes($sharedInboxes, $frontConversation);
                if (! $matched) {
                    $stats['conversations_unmatched'] = ((int) ($stats['conversations_unmatched'] ?? 0)) + 1;
                    $stats['unmatched_samples'] = $this->appendSample(
                        $stats['unmatched_samples'] ?? [],
                        $this->conversationLabel($frontConversation)
                    );

                    continue;
                }

                $this->importConversationTags(
                    $company,
                    $matched['inbox'],
                    $frontConversation,
                    $options,
                    $stats
                );
            }
        }

        return $stats;
    }

    /**
     * @param  Collection<int, SharedInbox>  $sharedInboxes
     * @param  array<string, mixed>  $frontConversation
     * @return array{inbox: SharedInbox, conversation: InboxConversation}|null
     */
    private function matchConversationAcrossInboxes(Collection $sharedInboxes, array $frontConversation): ?array
    {
        foreach ($sharedInboxes as $sharedInbox) {
            $conversation = $this->matchConversation($sharedInbox, $frontConversation);
            if ($conversation) {
                return [
                    'inbox' => $sharedInbox,
                    'conversation' => $conversation,
                ];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, int|list<string>>
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

        $sharedInboxes = $this->loadSharedInboxes($company, $options['shared_inbox_id'] ?? null);
        $frontInboxes = collect($payload['inboxes'] ?? [])
            ->filter(fn ($row) => is_array($row))
            ->values()
            ->all();

        $inboxMap = $this->resolveInboxMap(
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

            foreach ($frontInbox['conversations'] ?? [] as $frontConversation) {
                if (! is_array($frontConversation)) {
                    continue;
                }

                $this->importConversationTags(
                    $company,
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
     * @return array{
     *     rows: list<array{front_id: string, front_name: string, shared_inbox_id: int|null, shared_inbox_name: string|null}>,
     *     shared_inboxes: list<array{id: int, name: string, email: string|null}>,
     *     suggested_map: array<string, int>
     * }
     */
    public function mappingPreview(Company $company, ?FrontApiClient $client = null): array
    {
        $sharedInboxes = $this->loadSharedInboxes($company);
        $rows = [];
        $suggestedMap = [];
        $frontError = null;
        $importMode = 'inboxes';

        if ($client) {
            try {
                $frontInboxes = $client->listInboxes();
                foreach ($frontInboxes as $frontInbox) {
                    $frontId = (string) ($frontInbox['id'] ?? '');
                    if ($frontId === '') {
                        continue;
                    }

                    $matched = $this->matchSharedInbox($frontInbox, $sharedInboxes);
                    $rows[] = [
                        'front_id' => $frontId,
                        'front_name' => (string) ($frontInbox['name'] ?? $frontId),
                        'shared_inbox_id' => $matched ? (int) $matched->id : null,
                        'shared_inbox_name' => $matched?->name,
                    ];

                    if ($matched) {
                        $suggestedMap[$frontId] = (int) $matched->id;
                    }
                }
            } catch (\Throwable $e) {
                $frontError = $e->getMessage();
                $importMode = 'tags';
            }
        }

        return [
            'rows' => $rows,
            'shared_inboxes' => $sharedInboxes
                ->map(fn (SharedInbox $inbox) => [
                    'id' => (int) $inbox->id,
                    'name' => (string) $inbox->name,
                    'email' => $inbox->email ?: $inbox->external_mailbox,
                ])
                ->values()
                ->all(),
            'suggested_map' => $suggestedMap,
            'front_error' => $frontError,
            'import_mode' => $importMode,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $frontInboxes
     * @param  Collection<int, SharedInbox>  $sharedInboxes
     * @param  array<string, int|string>  $manualMap
     * @return array<string, int>
     */
    public function resolveInboxMap(
        array $frontInboxes,
        Collection $sharedInboxes,
        array $manualMap = [],
        ?string $onlyFrontInboxId = null
    ): array {
        $map = [];

        foreach ($manualMap as $frontId => $sharedId) {
            $frontId = trim((string) $frontId);
            $sharedId = (int) $sharedId;
            if ($frontId === '' || $sharedId <= 0) {
                continue;
            }
            if ($onlyFrontInboxId !== null && $frontId !== $onlyFrontInboxId) {
                continue;
            }
            if ($sharedInboxes->contains('id', $sharedId)) {
                $map[$frontId] = $sharedId;
            }
        }

        foreach ($frontInboxes as $frontInbox) {
            $frontId = (string) ($frontInbox['id'] ?? '');
            if ($frontId === '' || isset($map[$frontId])) {
                continue;
            }
            if ($onlyFrontInboxId !== null && $frontId !== $onlyFrontInboxId) {
                continue;
            }

            $sharedInbox = $this->matchSharedInbox($frontInbox, $sharedInboxes);
            if ($sharedInbox) {
                $map[$frontId] = (int) $sharedInbox->id;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $frontInbox
     * @param  Collection<int, SharedInbox>  $sharedInboxes
     */
    public function matchSharedInbox(array $frontInbox, Collection $sharedInboxes): ?SharedInbox
    {
        $frontName = $this->normalizeName((string) ($frontInbox['name'] ?? ''));
        if ($frontName !== '') {
            $byName = $sharedInboxes->first(fn (SharedInbox $inbox) => $this->normalizeName($inbox->name) === $frontName);
            if ($byName) {
                return $byName;
            }
        }

        $frontEmail = strtolower(trim((string) ($frontInbox['email'] ?? $frontInbox['address'] ?? '')));
        if ($frontEmail !== '') {
            $byEmail = $sharedInboxes->first(function (SharedInbox $inbox) use ($frontEmail) {
                $candidates = array_filter([
                    strtolower((string) $inbox->email),
                    strtolower((string) $inbox->external_mailbox),
                    strtolower((string) ($inbox->account?->email)),
                ]);

                return in_array($frontEmail, $candidates, true);
            });

            if ($byEmail) {
                return $byEmail;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $frontConversation
     */
    public function matchConversation(SharedInbox $sharedInbox, array $frontConversation): ?InboxConversation
    {
        if (isset($this->conversationLookupCache[$sharedInbox->id])) {
            return $this->matchConversationFromCache($sharedInbox->id, $frontConversation);
        }

        $externalIds = collect($frontConversation['metadata']['external_conversation_ids'] ?? [])
            ->filter(fn ($id) => is_string($id) && trim($id) !== '')
            ->values()
            ->all();

        if ($externalIds !== []) {
            $byExternal = InboxConversation::query()
                ->where('company_id', $sharedInbox->company_id)
                ->where('shared_inbox_id', $sharedInbox->id)
                ->whereNull('merged_into_id')
                ->whereIn('external_conversation_id', $externalIds)
                ->orderByDesc('last_message_at')
                ->first();

            if ($byExternal) {
                return $byExternal;
            }
        }

        $recipient = strtolower(trim((string) ($frontConversation['recipient']['handle'] ?? '')));
        $subject = $this->normalizeSubject((string) ($frontConversation['subject'] ?? ''));

        $query = InboxConversation::query()
            ->where('company_id', $sharedInbox->company_id)
            ->where('shared_inbox_id', $sharedInbox->id)
            ->whereNull('merged_into_id');

        if ($recipient !== '') {
            $query->whereRaw('LOWER(TRIM(from_email)) = ?', [$recipient]);
        }

        if ($subject !== '') {
            $query->where(function ($builder) use ($subject) {
                $builder
                    ->whereRaw('LOWER(TRIM(subject)) = ?', [$subject])
                    ->orWhereRaw('LOWER(TRIM(subject)) = ?', ['re: '.$subject])
                    ->orWhereRaw('LOWER(TRIM(subject)) = ?', ['fwd: '.$subject])
                    ->orWhereRaw('LOWER(TRIM(subject)) = ?', ['fw: '.$subject]);
            });
        }

        $candidates = $query->orderByDesc('last_message_at')->get();
        if ($candidates->isEmpty()) {
            return null;
        }

        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        $updatedAt = $this->frontTimestamp($frontConversation['updated_at'] ?? null);
        if ($updatedAt) {
            $closest = $candidates
                ->sortBy(fn (InboxConversation $conversation) => abs(
                    ($conversation->last_message_at?->getTimestamp() ?? 0) - $updatedAt->getTimestamp()
                ))
                ->first();

            if ($closest) {
                return $closest;
            }
        }

        return null;
    }

    private function warmConversationLookup(SharedInbox $sharedInbox): void
    {
        $conversations = InboxConversation::query()
            ->where('company_id', $sharedInbox->company_id)
            ->where('shared_inbox_id', $sharedInbox->id)
            ->whereNull('merged_into_id')
            ->orderByDesc('last_message_at')
            ->get(['id', 'external_conversation_id', 'from_email', 'subject', 'last_message_at']);

        $byExternal = [];
        $byEmailSubject = [];

        foreach ($conversations as $conversation) {
            $externalId = trim((string) $conversation->external_conversation_id);
            if ($externalId !== '' && ! isset($byExternal[$externalId])) {
                $byExternal[$externalId] = $conversation;
            }

            $email = strtolower(trim((string) $conversation->from_email));
            $subject = $this->normalizeSubject((string) $conversation->subject);
            if ($email === '' && $subject === '') {
                continue;
            }

            $key = $email.'|'.$subject;
            if (! isset($byEmailSubject[$key])) {
                $byEmailSubject[$key] = [];
            }
            $byEmailSubject[$key][] = $conversation;
        }

        $this->conversationLookupCache[$sharedInbox->id] = [
            'by_external' => $byExternal,
            'by_email_subject' => $byEmailSubject,
        ];
    }

    /**
     * @param  array<string, mixed>  $frontConversation
     */
    private function matchConversationFromCache(int $sharedInboxId, array $frontConversation): ?InboxConversation
    {
        $cache = $this->conversationLookupCache[$sharedInboxId];

        foreach ($frontConversation['metadata']['external_conversation_ids'] ?? [] as $externalId) {
            if (! is_string($externalId)) {
                continue;
            }
            $externalId = trim($externalId);
            if ($externalId !== '' && isset($cache['by_external'][$externalId])) {
                return $cache['by_external'][$externalId];
            }
        }

        $recipient = strtolower(trim((string) ($frontConversation['recipient']['handle'] ?? '')));
        $subject = $this->normalizeSubject((string) ($frontConversation['subject'] ?? ''));

        $candidates = $this->lookupCachedCandidates($cache['by_email_subject'], $recipient, $subject);
        if ($candidates === []) {
            return null;
        }

        if (count($candidates) === 1) {
            return $candidates[0];
        }

        $updatedAt = $this->frontTimestamp($frontConversation['updated_at'] ?? null);
        if ($updatedAt) {
            usort($candidates, fn (InboxConversation $a, InboxConversation $b) => abs(
                ($a->last_message_at?->getTimestamp() ?? 0) - $updatedAt->getTimestamp()
            ) <=> abs(
                ($b->last_message_at?->getTimestamp() ?? 0) - $updatedAt->getTimestamp()
            ));

            return $candidates[0];
        }

        return null;
    }

    /**
     * @param  array<string, list<InboxConversation>>  $byEmailSubject
     * @return list<InboxConversation>
     */
    private function lookupCachedCandidates(array $byEmailSubject, string $recipient, string $subject): array
    {
        $keys = [];
        if ($recipient !== '' && $subject !== '') {
            $keys[] = $recipient.'|'.$subject;
        }
        if ($recipient !== '' && $subject === '') {
            foreach (array_keys($byEmailSubject) as $key) {
                if (str_starts_with($key, $recipient.'|')) {
                    $keys[] = $key;
                }
            }
        }

        $candidates = [];
        foreach ($keys as $key) {
            foreach ($byEmailSubject[$key] ?? [] as $conversation) {
                $candidates[] = $conversation;
            }
        }

        return $candidates;
    }

    public function mapHighlightColor(?string $highlight): string
    {
        $key = strtolower(trim((string) $highlight));

        return self::HIGHLIGHT_COLORS[$key] ?? '#64748b';
    }

    public function normalizeSubject(string $subject): string
    {
        $subject = trim($subject);
        while ($subject !== '' && preg_match('/^(re|fwd|fw):\s*(.*)$/iu', $subject, $matches)) {
            $subject = trim($matches[2]);
        }

        return mb_strtolower($subject);
    }

    /**
     * @param  array<string, mixed>  $frontConversation
     * @param  array<string, mixed>  $options
     * @param  array<string, int|list<string>>  $stats
     */
    private function importConversationTags(
        Company $company,
        SharedInbox $sharedInbox,
        array $frontConversation,
        array $options,
        array &$stats
    ): void {
        $frontTags = collect($frontConversation['tags'] ?? [])
            ->filter(fn ($tag) => is_array($tag))
            ->values();

        if ($frontTags->isEmpty()) {
            return;
        }

        if (! ($options['include_private'] ?? false)) {
            $frontTags = $frontTags->reject(fn (array $tag) => (bool) ($tag['is_private'] ?? false));
        }

        if ($frontTags->isEmpty()) {
            return;
        }

        $stats['front_conversations_with_tags'] = ((int) ($stats['front_conversations_with_tags'] ?? 0)) + 1;

        $localConversation = $this->matchConversation($sharedInbox, $frontConversation);
        if (! $localConversation) {
            $stats['conversations_unmatched'] = ((int) ($stats['conversations_unmatched'] ?? 0)) + 1;
            $stats['unmatched_samples'] = $this->appendSample(
                $stats['unmatched_samples'] ?? [],
                $this->conversationLabel($frontConversation)
            );

            return;
        }

        $stats['conversations_matched'] = ((int) ($stats['conversations_matched'] ?? 0)) + 1;
        $lead = $this->resolveLeadForConversation($localConversation);
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $leadLabelIds = [];
        $inboxTagIds = [];

        foreach ($frontTags as $frontTag) {
            $name = trim((string) ($frontTag['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $color = $this->mapHighlightColor($frontTag['highlight'] ?? null);

            if ($lead) {
                $label = $this->ensureLeadLabel($company, $name, $color, $dryRun, $stats);
                if ($label) {
                    $leadLabelIds[] = (int) $label->id;
                }
            } else {
                $tag = $this->ensureInboxTag($company, $name, $color, $dryRun, $stats);
                if ($tag) {
                    $inboxTagIds[] = (int) $tag->id;
                }
            }
        }

        if ($leadLabelIds === [] && $inboxTagIds === []) {
            return;
        }

        if ($dryRun) {
            $stats['tags_applied'] = ((int) ($stats['tags_applied'] ?? 0)) + count($leadLabelIds) + count($inboxTagIds);
            if ($leadLabelIds !== []) {
                $stats['lead_labels_applied'] = ((int) ($stats['lead_labels_applied'] ?? 0)) + count($leadLabelIds);
            }

            return;
        }

        if ($lead && $leadLabelIds !== []) {
            $existing = $lead->labels()->pluck('lead_labels.id')->map(fn ($id) => (int) $id)->all();
            $merged = array_values(array_unique(array_merge($existing, $leadLabelIds)));
            $added = count(array_diff($merged, $existing));

            if ($added > 0) {
                DB::transaction(function () use ($lead, $merged) {
                    $lead->labels()->syncWithoutDetaching($merged);
                });
                $stats['tags_applied'] = ((int) ($stats['tags_applied'] ?? 0)) + $added;
                $stats['lead_labels_applied'] = ((int) ($stats['lead_labels_applied'] ?? 0)) + $added;
            }

            $this->crmLookup->forgetLeadIndexes((int) $company->id);
        }

        if ($inboxTagIds !== []) {
            $existing = $localConversation->tags()->pluck('inbox_tags.id')->map(fn ($id) => (int) $id)->all();
            $merged = array_values(array_unique(array_merge($existing, $inboxTagIds)));
            $added = count(array_diff($merged, $existing));

            if ($added > 0) {
                DB::transaction(function () use ($localConversation, $merged) {
                    $localConversation->tags()->syncWithoutDetaching($merged);
                });
                $stats['tags_applied'] = ((int) ($stats['tags_applied'] ?? 0)) + $added;
            }
        }
    }

    private function resolveLeadForConversation(InboxConversation $conversation): ?Lead
    {
        if ($conversation->lead_id) {
            $lead = Lead::query()
                ->where('company_id', $conversation->company_id)
                ->find($conversation->lead_id);
            if ($lead) {
                return $lead;
            }
        }

        $email = strtolower(trim((string) $conversation->from_email));
        if ($email !== '' && str_contains($email, '@')) {
            $lead = $this->crmLookup->findLeadByEmail((int) $conversation->company_id, $email);
            if ($lead) {
                return $lead;
            }
        }

        $name = trim((string) $conversation->from_name);
        if ($name !== '') {
            return $this->crmLookup->findLeadByName((int) $conversation->company_id, $name);
        }

        return null;
    }

    /**
     * @param  array<string, int|list<string>>  $stats
     */
    /**
     * @param  array<string, int|list<string>>  $stats
     */
    private function ensureLeadLabel(Company $company, string $name, string $color, bool $dryRun, array &$stats): ?LeadLabel
    {
        $existing = LeadLabel::query()
            ->where('company_id', $company->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing) {
            $stats['tags_existing'] = ((int) ($stats['tags_existing'] ?? 0)) + 1;

            return $existing;
        }

        if ($dryRun) {
            $stats['tags_created'] = ((int) ($stats['tags_created'] ?? 0)) + 1;

            return new LeadLabel([
                'company_id' => $company->id,
                'name' => $name,
                'color' => $color,
            ]);
        }

        $label = LeadLabel::query()->create([
            'company_id' => $company->id,
            'name' => $name,
            'color' => $color,
        ]);

        $stats['tags_created'] = ((int) ($stats['tags_created'] ?? 0)) + 1;

        return $label;
    }

    /**
     * @param  array<string, int|list<string>>  $stats
     */
    private function ensureInboxTag(Company $company, string $name, string $color, bool $dryRun, array &$stats): ?InboxTag
    {
        $existing = InboxTag::query()
            ->where('company_id', $company->id)
            ->where('name', $name)
            ->first();

        if ($existing) {
            $stats['tags_existing'] = ((int) ($stats['tags_existing'] ?? 0)) + 1;

            return $existing;
        }

        if ($dryRun) {
            $stats['tags_created'] = ((int) ($stats['tags_created'] ?? 0)) + 1;

            return new InboxTag([
                'company_id' => $company->id,
                'name' => $name,
                'color' => $color,
            ]);
        }

        $tag = InboxTag::query()->create([
            'company_id' => $company->id,
            'name' => $name,
            'color' => $color,
        ]);

        $stats['tags_created'] = ((int) ($stats['tags_created'] ?? 0)) + 1;

        return $tag;
    }

    /**
     * @return Collection<int, SharedInbox>
     */
    private function loadSharedInboxes(Company $company, ?int $sharedInboxId = null): Collection
    {
        $query = SharedInbox::query()
            ->with('account')
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->whereNotIn('type', [SharedInbox::TYPE_BROADCAST, SharedInbox::TYPE_QUOTATION])
            ->orderBy('id');

        if ($sharedInboxId) {
            $query->whereKey($sharedInboxId);
        }

        return $query->get();
    }

    /**
     * @return array<string, int|list<string>>
     */
    private function emptyStats(): array
    {
        return [
            'mapped_inboxes' => 0,
            'conversations_scanned' => 0,
            'front_conversations_with_tags' => 0,
            'conversations_matched' => 0,
            'conversations_unmatched' => 0,
            'tags_created' => 0,
            'tags_existing' => 0,
            'tags_applied' => 0,
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
