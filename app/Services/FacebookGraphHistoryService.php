<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookGraphHistoryService
{
    protected string $baseUrl = 'https://graph.facebook.com/v21.0';

    protected ?string $lastError = null;

    /** @var array<string, mixed> */
    protected array $lastStats = [];

    /** @var array<string, string> */
    protected array $platformErrors = [];

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * A cURL/HTTP client exception message includes the full request URL, which
     * includes the live access_token query param — strip it before this can ever
     * reach a log file or be shown in a UI hint.
     */
    public static function sanitizeGraphError(?string $message): ?string
    {
        if ($message === null || $message === '') {
            return $message;
        }

        return preg_replace('/access_token=[^&\s"]+/i', 'access_token=REDACTED', $message) ?? $message;
    }

    /**
     * @return array<string, mixed>
     */
    public function lastStats(): array
    {
        return $this->lastStats;
    }

    /**
     * Per-platform Graph API errors from the last history()/thread() call, e.g.
     * ['instagram' => '(#3) Application does not have the capability to make this API call.'].
     * Lets a Messenger success no longer hide an Instagram-only failure.
     *
     * @return array<string, string>
     */
    public function platformErrors(): array
    {
        return $this->platformErrors;
    }

    /**
     * @param  array<int, string>  $ownIds
     * @return array<int, array<string, mixed>>
     */
    public function history(
        string $pageId,
        string $accessToken,
        ?Carbon $after = null,
        int $maxMessages = 1500,
        int $deadlineSeconds = 90,
        array $ownIds = [],
        array $platforms = ['messenger']
    ): array {
        $this->lastError = null;
        $this->platformErrors = [];
        $this->lastStats = ['threads' => 0, 'messages' => 0, 'skipped_no_peer' => 0];
        $pageId = trim($pageId);
        $ownIds = $this->normalizeOwnIds($pageId, $ownIds);
        $this->assertPageToken($accessToken, $pageId);
        $rows = [];

        // Each platform gets its own time slice off a fresh clock, instead of one deadline
        // shared across platforms — otherwise a Page with thousands of Messenger threads
        // consumes the whole budget and Instagram (which has no Twilio fallback) never
        // even gets attempted.
        $platformCount = max(1, count(array_filter(
            $platforms,
            fn ($p) => in_array(strtolower((string) $p), ['messenger', 'instagram'], true)
        )));
        $perPlatformSeconds = max(5, $deadlineSeconds) / $platformCount;

        foreach ($platforms as $platform) {
            $platform = strtolower((string) $platform);
            if (! in_array($platform, ['messenger', 'instagram'], true)) {
                continue;
            }

            // Meta only exposes the /conversations edge on the Page node — the Instagram
            // Business Account node rejects it outright with "(#100) nonexisting field".
            $node = $pageId;
            $deadline = microtime(true) + $perPlatformSeconds;

            try {
                foreach ($this->conversations($node, $accessToken, $platform, $deadline) as $thread) {
                    if (count($rows) >= $maxMessages) {
                        break 2;
                    }
                    if (microtime(true) >= $deadline) {
                        // Only this platform's time slice ran out — let the next platform
                        // (e.g. Instagram after Messenger) still get its own full turn.
                        break;
                    }

                    $this->lastStats['threads']++;
                    $updated = isset($thread['updated_time']) ? Carbon::parse($thread['updated_time']) : null;
                    if ($after && $updated && $updated->lt($after)) {
                        continue;
                    }

                    $thread = $this->enrichThread($thread, $accessToken, $deadline);
                    $mapped = $this->mapThread($thread, $platform, $ownIds, $after);
                    $this->lastStats['skipped_no_peer'] += $mapped['skipped_no_peer'];
                    foreach ($mapped['rows'] as $row) {
                        $rows[] = $row;
                        $this->lastStats['messages']++;
                        if (count($rows) >= $maxMessages) {
                            break 3;
                        }
                    }
                }
            } catch (\Throwable $e) {
                $safeMessage = self::sanitizeGraphError($e->getMessage());
                $this->platformErrors[$platform] = $safeMessage;
                $this->lastError = $safeMessage;
                Log::error('Facebook Graph history sync failed for one platform', [
                    'platform' => $platform,
                    'node_id' => $node,
                    'error' => $safeMessage,
                ]);
            }
        }

        return array_values(array_filter($rows, fn ($row) => ($row['mid'] ?? '') !== ''));
    }

    /**
     * Last ~20 Page Inbox messages for one customer, including replies sent from Messenger.
     *
     * @param  array<int, string>  $ownIds
     * @return array<int, array<string, mixed>>
     */
    public function thread(
        string $pageId,
        string $accessToken,
        string $peerId,
        string $channel = 'messenger',
        array $ownIds = []
    ): array {
        $this->lastError = null;
        $this->platformErrors = [];
        $pageId = trim($pageId);
        $peerId = trim($peerId);
        $platform = $channel === 'instagram' ? 'instagram' : 'messenger';
        // Meta only exposes the /conversations edge on the Page node — the Instagram
        // Business Account node rejects it outright with "(#100) nonexisting field".
        $node = $pageId;
        $ownIds = $this->normalizeOwnIds($pageId, $ownIds);

        if ($node === '' || $peerId === '') {
            return [];
        }

        $this->assertPageToken($accessToken, $pageId);

        $response = $this->graphGet($this->baseUrl.'/'.$node.'/conversations', [
            'platform' => $platform,
            'user_id' => $peerId,
            'fields' => $this->conversationListFields(),
            'limit' => $platform === 'instagram' ? 1 : 5,
            'access_token' => $accessToken,
        ]);

        if (! $response['ok']) {
            $this->lastError = $response['error'];
            $this->platformErrors[$platform] = $response['error'];
            Log::error('Facebook Graph thread lookup failed', [
                'platform' => $platform,
                'node_id' => $node,
                'peer_id' => $peerId,
                'error' => $response['error'],
            ]);

            return [];
        }

        $rows = [];
        foreach ($response['data'] as $thread) {
            $mapped = $this->mapThread($this->enrichThread($thread, $accessToken), $platform, $ownIds, null, $peerId);
            foreach ($mapped['rows'] as $row) {
                $rows[] = $row;
            }
        }

        if ($rows !== []) {
            return array_values(array_filter($rows, fn ($row) => ($row['mid'] ?? '') !== ''));
        }

        try {
            $seen = 0;
            $deadline = microtime(true) + 8;
            foreach ($this->conversations($node, $accessToken, $platform) as $thread) {
                if ($seen++ >= 25 || microtime(true) >= $deadline) {
                    break;
                }
                $peer = $this->peerFromThread($thread, $ownIds)
                    ?: $this->peerFromMessages($thread['messages']['data'] ?? [], $ownIds);
                if (! $peer || $peer['id'] !== $peerId) {
                    continue;
                }
                $mapped = $this->mapThread($this->enrichThread($thread, $accessToken), $platform, $ownIds, null, $peerId);
                foreach ($mapped['rows'] as $row) {
                    $rows[] = $row;
                }
                break;
            }
        } catch (\Throwable $e) {
            $this->lastError = $this->lastError ?: self::sanitizeGraphError($e->getMessage());
        }

        return array_values(array_filter($rows, fn ($row) => ($row['mid'] ?? '') !== ''));
    }

    public function pageInfo(string $pageId, string $accessToken): array
    {
        $response = Http::timeout(30)->get($this->baseUrl.'/'.$pageId, [
            'fields' => 'id,name',
            'access_token' => $accessToken,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException($this->errorMessage($response->json(), 'Could not verify the Facebook Page access token.'));
        }

        return $response->json() ?: [];
    }

    /**
     * @param  array<int, string>  $ownIds
     * @return array<int, string>
     */
    protected function normalizeOwnIds(string $pageId, array $ownIds): array
    {
        $ids = array_values(array_filter(array_map('strval', array_merge([$pageId], $ownIds))));

        return array_values(array_unique($ids));
    }

    /**
     * @return \Generator<int, array<string, mixed>>
     */
    protected function conversations(
        string $pageId,
        string $accessToken,
        string $platform,
        ?float $deadline = null
    ): \Generator {
        $pages = 0;
        // On some Pages Meta's Instagram conversations edge errors with
        // "(#1) Please reduce the amount of data you're asking for" for any
        // limit above 1, even though the same edge handles limit=25 fine for
        // Messenger — request one thread per page for Instagram.
        $limit = $platform === 'instagram' ? 1 : 25;
        $next = $this->baseUrl.'/'.$pageId.'/conversations';
        $params = [
            'platform' => $platform,
            'fields' => $this->conversationListFields(),
            'limit' => $limit,
            'access_token' => $accessToken,
        ];
        $firstError = null;
        $yielded = false;
        $retriedWithSmallerLimit = false;

        while ($next && $pages < 40) {
            if ($deadline !== null && microtime(true) >= $deadline) {
                break;
            }
            $pages++;
            try {
                $response = $this->graphGet($next, $params);
            } catch (\Throwable $e) {
                // A single slow/failed page (this Graph edge can hang or time out on later
                // pages even after page 1 succeeds) shouldn't discard threads already
                // yielded from earlier pages — stop paginating and keep what we have.
                // The raw exception message includes the full request URL (with the live
                // access_token query param) — never let that reach a log file as-is.
                $firstError = self::sanitizeGraphError($e->getMessage());
                break;
            }
            if (! $response['ok']) {
                if (! $retriedWithSmallerLimit && $limit > 1 && $this->isReduceDataError($response['error'])) {
                    // Meta explicitly asks for a retry with less data — honor it once
                    // instead of surfacing a transient-looking error as a hard failure.
                    $retriedWithSmallerLimit = true;
                    $pages--;
                    $next = $this->baseUrl.'/'.$pageId.'/conversations';
                    $params = [
                        'platform' => $platform,
                        'fields' => $this->conversationListFields(),
                        'limit' => 1,
                        'access_token' => $accessToken,
                    ];

                    continue;
                }

                $firstError = $response['error'];
                break;
            }

            foreach ($response['data'] as $item) {
                $yielded = true;
                yield $item;
            }

            $next = $response['next'];
            $params = [];
        }

        if (! $yielded && $firstError) {
            $this->lastError = $firstError;
            throw new \RuntimeException($firstError);
        }
    }

    protected function isReduceDataError(?string $message): bool
    {
        $haystack = strtolower((string) $message);

        return $haystack !== '' && str_contains($haystack, 'reduce the amount of data');
    }

    protected function conversationListFields(): string
    {
        return 'id,updated_time,snippet,message_count,participants';
    }

    /**
     * Messenger Platform only returns message ids on the conversation. Fetch
     * bodies from /{message-id} so Graph does not treat this as a user mailbox.
     *
     * @param  array<string, mixed>  $thread
     * @return array<string, mixed>
     */
    protected function enrichThread(array $thread, string $accessToken, ?float $deadline = null): array
    {
        $conversationId = (string) ($thread['id'] ?? '');
        if ($conversationId === '') {
            return $thread;
        }

        if ($deadline !== null && microtime(true) >= $deadline) {
            return $thread;
        }

        $messageFields = 'id,created_time,from,to,message,sticker,attachments{mime_type,name,image_data,file_url,video_data}';
        $node = $this->graphGetNode($this->baseUrl.'/'.$conversationId, [
            'fields' => 'participants,messages.limit(25){'.$messageFields.'}',
            'access_token' => $accessToken,
        ]);
        if (! $node['ok']) {
            $node = $this->graphGetNode($this->baseUrl.'/'.$conversationId, [
                'fields' => 'participants,messages.limit(25)',
                'access_token' => $accessToken,
            ]);
        }
        if (! $node['ok']) {
            $this->lastError = $this->lastError ?: $node['error'];

            return $thread;
        }

        if (empty($thread['participants']) && ! empty($node['node']['participants'])) {
            $thread['participants'] = $node['node']['participants'];
        }

        $stubs = $node['node']['messages']['data'] ?? [];
        if (! is_array($stubs) || $stubs === []) {
            $edge = $this->graphGet($this->baseUrl.'/'.$conversationId.'/messages', [
                'fields' => $messageFields,
                'limit' => 25,
                'access_token' => $accessToken,
            ]);
            if ($edge['ok'] && $edge['data'] !== []) {
                $thread['messages'] = ['data' => $edge['data']];
            }

            return $thread;
        }

        if ($this->messagesHaveBodies($stubs)) {
            $thread['messages'] = ['data' => $stubs];

            return $thread;
        }

        $ids = [];
        foreach ($stubs as $stub) {
            if (is_array($stub) && ! empty($stub['id'])) {
                $ids[] = (string) $stub['id'];
            }
        }

        if ($deadline !== null && microtime(true) >= $deadline) {
            $thread['messages'] = ['data' => $stubs];

            return $thread;
        }

        $details = $this->messageDetails($ids, $accessToken);
        if ($details === []) {
            $edge = $this->graphGet($this->baseUrl.'/'.$conversationId.'/messages', [
                'fields' => $messageFields,
                'limit' => 25,
                'access_token' => $accessToken,
            ]);
            if ($edge['ok'] && $edge['data'] !== []) {
                $details = $edge['data'];
            }
        }
        $thread['messages'] = ['data' => $details !== [] ? $details : $stubs];

        return $thread;
    }

    /**
     * @param  array<int, mixed>  $messages
     */
    protected function messagesHaveBodies(array $messages): bool
    {
        foreach ($messages as $message) {
            if (! is_array($message)) {
                continue;
            }
            if (! empty($message['from']) || (isset($message['message']) && $message['message'] !== '') || ! empty($message['created_time'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $ids
     * @return array<int, array<string, mixed>>
     */
    protected function messageDetails(array $ids, string $accessToken): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === []) {
            return [];
        }

        $payload = $this->graphGetNode($this->baseUrl.'/', [
            'ids' => implode(',', array_slice($ids, 0, 20)),
            'fields' => 'id,created_time,from,to,message,sticker,attachments{mime_type,name,image_data,file_url,video_data}',
            'access_token' => $accessToken,
        ]);
        if (! $payload['ok']) {
            $this->lastError = $this->lastError ?: $payload['error'];

            return [];
        }

        $rows = [];
        foreach ($payload['node'] as $item) {
            if (is_array($item) && ! empty($item['id']) && empty($item['error'])) {
                $rows[] = $item;
            }
        }

        return $rows;
    }

    protected function assertPageToken(string $accessToken, string $pageId = ''): void
    {
        $graph = app(FacebookGraphMessagingService::class);
        $actor = $graph->tokenActor($accessToken);
        if ($actor['ok'] && $pageId !== '' && $actor['id'] === $pageId) {
            return;
        }
        if ($actor['ok'] && $pageId !== '' && $actor['id'] !== '' && $actor['id'] !== $pageId) {
            throw new \RuntimeException($graph->mailboxPermissionMessage());
        }
        if ($graph->isExpiredTokenError($actor['error'] ?? null)) {
            throw new \RuntimeException($graph->expiredTokenMessage());
        }
    }

    protected function conversationFields(): string
    {
        return $this->conversationListFields();
    }

    /**
     * @param  array<string, mixed>  $thread
     * @param  array<int, string>  $ownIds
     * @return array{rows: array<int, array<string, mixed>>, skipped_no_peer: int}
     */
    protected function mapThread(
        array $thread,
        string $platform,
        array $ownIds,
        ?Carbon $after = null,
        ?string $forcedPeerId = null
    ): array {
        $conversationId = (string) ($thread['id'] ?? '');
        $messages = $thread['messages']['data'] ?? [];
        if (! is_array($messages)) {
            $messages = [];
        }

        $peer = $forcedPeerId
            ? ['id' => $forcedPeerId, 'name' => null]
            : $this->peerFromThread($thread, $ownIds);

        if ((! $peer || $peer['id'] === '') && $messages !== []) {
            $peer = $this->peerFromMessages($messages, $ownIds);
        }

        if (! $peer || $peer['id'] === '') {
            return ['rows' => [], 'skipped_no_peer' => $conversationId !== '' ? 1 : 0];
        }

        $channel = $platform === 'instagram' ? 'instagram' : 'messenger';
        $rows = [];

        foreach ($messages as $message) {
            if (! is_array($message)) {
                continue;
            }

            $sentAt = isset($message['created_time'])
                ? TimezoneService::fromExternal($message['created_time'])
                : now();
            if ($after && $sentAt->lt($after)) {
                continue;
            }

            $fromId = $this->actorId($message['from'] ?? null);
            $toIds = $this->actorIds($message['to'] ?? null);
            $direction = $this->direction($fromId, $toIds, $peer['id'], $ownIds);
            $attachment = $this->firstAttachment($message['attachments']['data'] ?? []);
            $type = $attachment['type'] ?? 'text';
            $text = isset($message['message']) && is_string($message['message']) ? $message['message'] : null;
            if (! $text && ! empty($message['sticker'])) {
                $type = 'image';
            }

            $rows[] = [
                'mid' => (string) ($message['id'] ?? ''),
                'channel' => $channel,
                'peer_id' => $peer['id'],
                'name' => $peer['name'],
                'direction' => $direction,
                'text' => $text,
                'type' => $type,
                'media_url' => $attachment['url'] ?? null,
                'mime_type' => $attachment['mime'] ?? null,
                'status' => $direction === 'outbound' ? 'sent' : 'received',
                'sent_at' => $sentAt,
                'raw' => [
                    'id' => $message['id'] ?? null,
                    'conversation_id' => $conversationId,
                    'from' => $message['from'] ?? null,
                    'to' => $message['to'] ?? null,
                    'synced' => true,
                    'source' => 'graph',
                ],
            ];
        }

        return ['rows' => $rows, 'skipped_no_peer' => 0];
    }

    /**
     * @param  array<string, mixed>  $thread
     * @param  array<int, string>  $ownIds
     * @return array{id: string, name: ?string}|null
     */
    protected function peerFromThread(array $thread, array $ownIds): ?array
    {
        $participants = $thread['participants']['data'] ?? [];
        if (! is_array($participants)) {
            return null;
        }

        foreach ($participants as $participant) {
            $id = $this->actorId($participant);
            if ($id === '' || in_array($id, $ownIds, true)) {
                continue;
            }

            return [
                'id' => $id,
                'name' => isset($participant['name']) && is_string($participant['name']) ? $participant['name'] : null,
            ];
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, string>  $ownIds
     * @return array{id: string, name: ?string}|null
     */
    protected function peerFromMessages(array $messages, array $ownIds): ?array
    {
        foreach ($messages as $message) {
            $fromId = $this->actorId($message['from'] ?? null);
            $fromName = is_array($message['from'] ?? null) && isset($message['from']['name'])
                ? (string) $message['from']['name']
                : null;
            if ($fromId !== '' && ! in_array($fromId, $ownIds, true)) {
                return ['id' => $fromId, 'name' => $fromName];
            }

            foreach ($this->actorRecords($message['to'] ?? null) as $actor) {
                $id = $this->actorId($actor);
                if ($id !== '' && ! in_array($id, $ownIds, true)) {
                    $name = isset($actor['name']) && is_string($actor['name']) ? $actor['name'] : null;

                    return ['id' => $id, 'name' => $name];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $toIds
     * @param  array<int, string>  $ownIds
     */
    protected function direction(string $fromId, array $toIds, string $peerId, array $ownIds): string
    {
        if ($fromId !== '' && $fromId === $peerId) {
            return 'inbound';
        }
        if ($fromId !== '' && in_array($fromId, $ownIds, true)) {
            return 'outbound';
        }
        if ($toIds !== [] && in_array($peerId, $toIds, true)) {
            return 'outbound';
        }
        if ($fromId !== '' && ! in_array($fromId, $ownIds, true) && $fromId !== $peerId) {
            // Page admin replies often use the person's Facebook id, not the Page id.
            return 'outbound';
        }

        return $fromId === '' ? 'outbound' : 'inbound';
    }

    /**
     * @param  mixed  $actor
     */
    protected function actorId(mixed $actor): string
    {
        if (is_string($actor) && $actor !== '') {
            return $actor;
        }
        if (! is_array($actor)) {
            return '';
        }

        foreach (['id', 'user_id'] as $key) {
            if (isset($actor[$key]) && is_scalar($actor[$key]) && (string) $actor[$key] !== '') {
                return (string) $actor[$key];
            }
        }

        return '';
    }

    /**
     * @param  mixed  $to
     * @return array<int, string>
     */
    protected function actorIds(mixed $to): array
    {
        $ids = [];
        foreach ($this->actorRecords($to) as $actor) {
            $id = $this->actorId($actor);
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @param  mixed  $to
     * @return array<int, array<string, mixed>>
     */
    protected function actorRecords(mixed $to): array
    {
        if (is_array($to) && isset($to['data']) && is_array($to['data'])) {
            return array_values(array_filter($to['data'], 'is_array'));
        }
        if (is_array($to) && isset($to['id'])) {
            return [$to];
        }

        return [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $attachments
     * @return array{type: string, url: ?string, mime: ?string}|null
     */
    protected function firstAttachment(array $attachments): ?array
    {
        if ($attachments === []) {
            return null;
        }

        $attachment = $attachments[0];
        $mime = strtolower((string) ($attachment['mime_type'] ?? ''));
        $url = $attachment['file_url']
            ?? ($attachment['image_data']['url'] ?? null)
            ?? ($attachment['video_data']['url'] ?? null);

        $type = match (true) {
            str_starts_with($mime, 'image/') || isset($attachment['image_data']) => 'image',
            str_starts_with($mime, 'video/') || isset($attachment['video_data']) => 'video',
            str_starts_with($mime, 'audio/') => 'audio',
            default => 'file',
        };

        return [
            'type' => $url ? $type : 'file',
            'url' => is_string($url) ? $url : null,
            'mime' => $mime !== '' ? $mime : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{ok: bool, data: array<int, array<string, mixed>>, next: ?string, error: ?string}
     */
    protected function graphGet(string $url, array $query = []): array
    {
        // The Instagram conversations edge can take 15-20s to respond even when it
        // succeeds (observed directly against this Page) — a short timeout here was
        // killing genuinely-in-progress requests before Meta could answer.
        $response = $query === []
            ? Http::timeout(20)->connectTimeout(5)->get($url)
            : Http::timeout(20)->connectTimeout(5)->get($url, $query);

        $payload = $response->json() ?: [];
        if (! $response->successful()) {
            $error = $this->errorMessage($payload, 'HTTP '.$response->status());

            return ['ok' => false, 'data' => [], 'next' => null, 'error' => $error];
        }

        $data = [];
        foreach ($payload['data'] ?? [] as $item) {
            if (is_array($item)) {
                $data[] = $item;
            }
        }

        return [
            'ok' => true,
            'data' => $data,
            'next' => isset($payload['paging']['next']) && is_string($payload['paging']['next'])
                ? $payload['paging']['next']
                : null,
            'error' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{ok: bool, node: array<string, mixed>, error: ?string}
     */
    protected function graphGetNode(string $url, array $query = []): array
    {
        $response = Http::timeout(10)->connectTimeout(5)->get($url, $query);
        $payload = $response->json() ?: [];
        if (! $response->successful()) {
            return [
                'ok' => false,
                'node' => [],
                'error' => $this->errorMessage($payload, 'HTTP '.$response->status()),
            ];
        }

        return [
            'ok' => true,
            'node' => is_array($payload) ? $payload : [],
            'error' => null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    protected function errorMessage(?array $payload, string $fallback): string
    {
        $message = $payload['error']['message'] ?? null;

        return is_string($message) && $message !== '' ? $message : $fallback;
    }
}
