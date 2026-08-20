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

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * @return array<string, mixed>
     */
    public function lastStats(): array
    {
        return $this->lastStats;
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
        $this->lastStats = ['threads' => 0, 'messages' => 0, 'skipped_no_peer' => 0];
        $pageId = trim($pageId);
        $ownIds = $this->normalizeOwnIds($pageId, $ownIds);
        $this->assertPageToken($accessToken);
        $deadline = microtime(true) + max(5, $deadlineSeconds);
        $rows = [];

        foreach ($platforms as $platform) {
            $platform = strtolower((string) $platform);
            if (! in_array($platform, ['messenger', 'instagram'], true)) {
                continue;
            }

            foreach ($this->conversations($pageId, $accessToken, $platform) as $thread) {
                if (count($rows) >= $maxMessages || microtime(true) >= $deadline) {
                    break 2;
                }

                $this->lastStats['threads']++;
                $updated = isset($thread['updated_time']) ? Carbon::parse($thread['updated_time']) : null;
                if ($after && $updated && $updated->lt($after)) {
                    continue;
                }

                $thread = $this->enrichThread($thread, $accessToken);
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
        $pageId = trim($pageId);
        $peerId = trim($peerId);
        $platform = $channel === 'instagram' ? 'instagram' : 'messenger';
        $ownIds = $this->normalizeOwnIds($pageId, $ownIds);

        if ($pageId === '' || $peerId === '') {
            return [];
        }

        $this->assertPageToken($accessToken);

        $response = $this->graphGet($this->baseUrl.'/'.$pageId.'/conversations', [
            'platform' => $platform,
            'user_id' => $peerId,
            'fields' => $this->conversationListFields(),
            'limit' => 5,
            'access_token' => $accessToken,
        ]);

        if (! $response['ok']) {
            $this->lastError = $response['error'];
            Log::warning('Facebook Graph thread lookup failed', [
                'platform' => $platform,
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
            foreach ($this->conversations($pageId, $accessToken, $platform) as $thread) {
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
            $this->lastError = $this->lastError ?: $e->getMessage();
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
    protected function conversations(string $pageId, string $accessToken, string $platform): \Generator
    {
        $pages = 0;
        $next = $this->baseUrl.'/'.$pageId.'/conversations';
        $params = [
            'platform' => $platform,
            'fields' => $this->conversationListFields(),
            'limit' => 25,
            'access_token' => $accessToken,
        ];
        $firstError = null;
        $yielded = false;

        while ($next && $pages < 40) {
            $pages++;
            $response = $this->graphGet($next, $params);
            if (! $response['ok']) {
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
    protected function enrichThread(array $thread, string $accessToken): array
    {
        $conversationId = (string) ($thread['id'] ?? '');
        if ($conversationId === '') {
            return $thread;
        }

        $node = $this->graphGetNode($this->baseUrl.'/'.$conversationId, [
            'fields' => 'participants,messages.limit(20)',
            'access_token' => $accessToken,
        ]);
        if (! $node['ok']) {
            $this->lastError = $this->lastError ?: $node['error'];

            return $thread;
        }

        if (empty($thread['participants']) && ! empty($node['node']['participants'])) {
            $thread['participants'] = $node['node']['participants'];
        }

        $stubs = $node['node']['messages']['data'] ?? [];
        if (! is_array($stubs) || $stubs === []) {
            return $thread;
        }

        $ids = [];
        foreach ($stubs as $stub) {
            if (is_array($stub) && ! empty($stub['id'])) {
                $ids[] = (string) $stub['id'];
            }
        }

        $details = $this->messageDetails($ids, $accessToken);
        $thread['messages'] = ['data' => $details !== [] ? $details : $stubs];

        return $thread;
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

    protected function assertPageToken(string $accessToken): void
    {
        $graph = app(FacebookGraphMessagingService::class);
        $info = $graph->inspectToken($accessToken);
        if (($info['type'] ?? null) === 'USER' || $graph->isMailboxPermissionError($info['error'])) {
            throw new \RuntimeException($graph->mailboxPermissionMessage());
        }
        if (! $info['valid'] || $graph->isExpiredTokenError($info['error'])) {
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
        $response = $query === []
            ? Http::timeout(20)->get($url)
            : Http::timeout(20)->get($url, $query);

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
        $response = Http::timeout(20)->get($url, $query);
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
