<?php

namespace App\Services\Front;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FrontApiClient
{
    public function __construct(
        private readonly string $token,
        private readonly string $baseUrl = 'https://api2.frontapp.com',
    ) {}

    public static function fromConfig(?string $tokenOverride = null): self
    {
        $token = trim((string) ($tokenOverride ?: config('services.front.api_token', '')));
        if ($token === '') {
            throw new RuntimeException('Front API token is required. Set FRONT_API_TOKEN or pass --token.');
        }

        return new self($token, rtrim((string) config('services.front.base_url', 'https://api2.frontapp.com'), '/'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listTags(): array
    {
        return iterator_to_array($this->paginate('/tags'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listInboxes(): array
    {
        return iterator_to_array($this->paginate('/inboxes'));
    }

    /**
     * @param  list<string>  $statuses
     * @return \Generator<int, array<string, mixed>>
     */
    public function listInboxConversations(string $inboxId, array $statuses = ['archived', 'assigned', 'unassigned']): \Generator
    {
        $query = [
            'limit' => 100,
            'q' => json_encode(['statuses' => array_values($statuses)], JSON_THROW_ON_ERROR),
        ];

        yield from $this->paginate('/inboxes/'.rawurlencode($inboxId).'/conversations', $query);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return \Generator<int, array<string, mixed>>
     */
    public function paginate(string $path, array $query = []): \Generator
    {
        $url = $this->absoluteUrl($path);
        $firstRequest = true;

        while ($url !== null) {
            $response = $firstRequest
                ? Http::timeout(60)->withToken($this->token)->acceptJson()->get($url, $query)
                : Http::timeout(60)->withToken($this->token)->acceptJson()->get($url);

            $firstRequest = false;

            try {
                $response->throw();
            } catch (RequestException $e) {
                $message = $response->json('message') ?? $response->body();
                throw new RuntimeException('Front API request failed: '.$message, $response->status(), $e);
            }

            $payload = $response->json();
            if (! is_array($payload)) {
                throw new RuntimeException('Front API returned an unexpected response.');
            }

            foreach ($payload['_results'] ?? [] as $item) {
                if (is_array($item)) {
                    yield $item;
                }
            }

            $next = $payload['_pagination']['next'] ?? null;
            $url = is_string($next) && $next !== '' ? $next : null;
            $query = [];
        }
    }

    private function absoluteUrl(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return $this->baseUrl.'/'.ltrim($path, '/');
    }
}
