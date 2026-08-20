<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookGraphHistoryService
{
    protected string $baseUrl = 'https://graph.facebook.com/v21.0';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history(
        string $pageId,
        string $accessToken,
        ?Carbon $after = null,
        int $maxMessages = 1500,
        int $deadlineSeconds = 90
    ): array {
        $rows = [];
        $pageId = trim($pageId);
        $deadline = microtime(true) + max(5, $deadlineSeconds);
        $stopWhenStale = $after !== null && $deadlineSeconds <= 15;

        foreach ($this->paginate($this->baseUrl.'/'.$pageId.'/conversations', [
            'platform' => 'messenger',
            'fields' => 'id,updated_time,participants',
            'limit' => 50,
            'access_token' => $accessToken,
        ]) as $thread) {
            if (count($rows) >= $maxMessages || microtime(true) >= $deadline) {
                break;
            }

            $updated = isset($thread['updated_time']) ? Carbon::parse($thread['updated_time']) : null;
            if ($after && $updated && $updated->lt($after)) {
                if ($stopWhenStale) {
                    break;
                }
                continue;
            }

            $peer = $this->peerFromParticipants($thread['participants']['data'] ?? [], $pageId);
            if (! $peer) {
                continue;
            }

            $conversationId = (string) ($thread['id'] ?? '');
            if ($conversationId === '') {
                continue;
            }

            foreach ($this->paginate($this->baseUrl.'/'.$conversationId.'/messages', [
                'fields' => 'id,message,from,created_time,sticker,attachments{mime_type,name,size,image_data,file_url,video_data}',
                'limit' => 100,
                'access_token' => $accessToken,
            ]) as $message) {
                if (count($rows) >= $maxMessages || microtime(true) >= $deadline) {
                    break 2;
                }

                $sentAt = isset($message['created_time'])
                    ? TimezoneService::fromExternal($message['created_time'])
                    : now();
                if ($after && $sentAt->lt($after)) {
                    continue;
                }

                $fromId = (string) ($message['from']['id'] ?? '');
                $attachment = $this->firstAttachment($message['attachments']['data'] ?? []);
                $type = $attachment['type'] ?? 'text';
                $text = isset($message['message']) && is_string($message['message']) ? $message['message'] : null;
                if (! $text && ! empty($message['sticker'])) {
                    $type = 'image';
                }

                $rows[] = [
                    'mid' => (string) ($message['id'] ?? ''),
                    'channel' => 'messenger',
                    'peer_id' => $peer['id'],
                    'name' => $peer['name'],
                    'direction' => $fromId !== '' && $fromId === $pageId ? 'outbound' : 'inbound',
                    'text' => $text,
                    'type' => $type,
                    'media_url' => $attachment['url'] ?? null,
                    'mime_type' => $attachment['mime'] ?? null,
                    'status' => $fromId === $pageId ? 'sent' : 'received',
                    'sent_at' => $sentAt,
                    'raw' => [
                        'id' => $message['id'] ?? null,
                        'conversation_id' => $conversationId,
                        'from' => $message['from'] ?? null,
                        'synced' => true,
                        'source' => 'graph',
                    ],
                ];
            }
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
     * @param  array<int, array<string, mixed>>  $participants
     * @return array{id: string, name: ?string}|null
     */
    protected function peerFromParticipants(array $participants, string $pageId): ?array
    {
        foreach ($participants as $participant) {
            $id = (string) ($participant['id'] ?? '');
            if ($id === '' || $id === $pageId) {
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
     * @return \Generator<int, array<string, mixed>>
     */
    protected function paginate(string $url, array $query): \Generator
    {
        $next = $url;
        $params = $query;
        $pages = 0;

        while ($next && $pages < 200) {
            $pages++;
            $response = $params === []
                ? Http::timeout(15)->get($next)
                : Http::timeout(15)->get($next, $params);

            if (! $response->successful()) {
                Log::warning('Facebook Graph history request failed', [
                    'url' => $next,
                    'error' => $this->errorMessage($response->json(), 'HTTP '.$response->status()),
                ]);
                break;
            }

            $payload = $response->json() ?: [];
            foreach ($payload['data'] ?? [] as $item) {
                if (is_array($item)) {
                    yield $item;
                }
            }

            $next = $payload['paging']['next'] ?? null;
            $params = [];
        }
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
