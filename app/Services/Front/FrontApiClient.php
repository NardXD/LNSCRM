<?php

namespace App\Services\Front;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FrontApiClient
{
    private readonly string $token;

    public static function normalizeToken(string $token): string
    {
        $token = trim($token);
        if (preg_match('/^Bearer\s+/i', $token)) {
            $token = preg_replace('/^Bearer\s+/i', '', $token);
        }

        return trim($token);
    }

    public function __construct(
        string $token,
        private readonly string $baseUrl = 'https://api2.frontapp.com',
    ) {
        $token = self::normalizeToken($token);
        $this->token = $token;

        if ($this->token === '') {
            throw new RuntimeException('Front API token is required.');
        }
    }

    public static function fromConfig(?string $tokenOverride = null): self
    {
        $token = self::normalizeToken((string) ($tokenOverride ?: config('services.front.api_token', '')));
        if ($token === '') {
            throw new RuntimeException('Front API token is required. Set FRONT_API_TOKEN or pass --token.');
        }

        return new self($token, rtrim((string) config('services.front.base_url', 'https://api2.frontapp.com'), '/'));
    }

    public function verifyConnection(): void
    {
        $attempts = [
            ['/tags', ['limit' => 1]],
            ['/inboxes', ['limit' => 1]],
        ];

        $lastError = null;
        foreach ($attempts as [$path, $query]) {
            try {
                $response = Http::timeout(30)
                    ->withToken($this->token)
                    ->acceptJson()
                    ->get($this->absoluteUrl($path), $query);

                $this->assertSuccessful($response);

                return;
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
            }
        }

        throw new RuntimeException($lastError ?? 'Front API token could not be verified.');
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
        yield from $this->paginateConversations(
            '/inboxes/'.rawurlencode($inboxId).'/conversations',
            $statuses
        );
    }

    /**
     * @param  list<string>  $statuses
     * @return \Generator<int, array<string, mixed>>
     */
    public function listTaggedConversations(string $tagId, array $statuses = ['archived', 'assigned', 'unassigned']): \Generator
    {
        yield from $this->paginateConversations(
            '/tags/'.rawurlencode($tagId).'/conversations',
            $statuses
        );
    }

    /**
     * @param  list<string>  $statuses
     * @return array{results: list<array<string, mixed>>, next_page_url: string|null}
     */
    public function fetchConversationPage(string $path, ?string $pageUrl = null, array $statuses = ['archived', 'assigned', 'unassigned'], int $limit = 20): array
    {
        if ($pageUrl) {
            $response = Http::timeout(45)
                ->withToken($this->token)
                ->acceptJson()
                ->get($pageUrl);
        } else {
            $response = $this->requestConversationPage($path, $this->conversationQuery($statuses, $limit));
            if (! $response->successful()) {
                $response = $this->requestConversationPage($path, ['limit' => $limit]);
            }
        }

        $this->assertSuccessful($response);

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('Front API returned an unexpected response.');
        }

        $results = [];
        foreach ($payload['_results'] ?? [] as $item) {
            if (is_array($item)) {
                $results[] = $item;
            }
        }

        $next = $payload['_pagination']['next'] ?? null;

        return [
            'results' => $results,
            'next_page_url' => is_string($next) && $next !== '' ? $next : null,
        ];
    }

    public function fetchInboxConversationPage(string $inboxId, ?string $pageUrl = null, array $statuses = ['archived', 'assigned', 'unassigned'], int $limit = 20): array
    {
        return $this->fetchConversationPage(
            '/inboxes/'.rawurlencode($inboxId).'/conversations',
            $pageUrl,
            $statuses,
            $limit
        );
    }

    public function fetchTaggedConversationPage(string $tagId, ?string $pageUrl = null, array $statuses = ['archived', 'assigned', 'unassigned'], int $limit = 20): array
    {
        return $this->fetchConversationPage(
            '/tags/'.rawurlencode($tagId).'/conversations',
            $pageUrl,
            $statuses,
            $limit
        );
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function requestConversationPage(string $path, array $query): Response
    {
        return Http::timeout(45)
            ->withToken($this->token)
            ->acceptJson()
            ->get($this->absoluteUrl($path), $query);
    }

    /**
     * @param  list<string>  $statuses
     * @return \Generator<int, array<string, mixed>>
     */
    private function paginateConversations(string $path, array $statuses): \Generator
    {
        $pageUrl = null;
        do {
            $page = $this->fetchConversationPage($path, $pageUrl, $statuses, 100);
            foreach ($page['results'] as $item) {
                yield $item;
            }
            $pageUrl = $page['next_page_url'];
        } while ($pageUrl);
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
                ? Http::timeout(120)->withToken($this->token)->acceptJson()->get($url, $query)
                : Http::timeout(120)->withToken($this->token)->acceptJson()->get($url);

            $firstRequest = false;
            $this->assertSuccessful($response);

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

    /**
     * @param  list<string>  $statuses
     * @return array<string, mixed>
     */
    private function conversationQuery(array $statuses, int $limit = 100): array
    {
        return [
            'limit' => max(1, min($limit, 100)),
            'q' => json_encode(['statuses' => array_values($statuses)], JSON_THROW_ON_ERROR),
        ];
    }

    private function assertSuccessful(Response $response): void
    {
        try {
            $response->throw();
        } catch (RequestException $e) {
            throw new RuntimeException($this->parseErrorMessage($response), $response->status(), $e);
        }
    }

    private function parseErrorMessage(Response $response): string
    {
        $payload = $response->json();
        if (is_array($payload)) {
            $error = $payload['_error'] ?? null;
            if (is_array($error)) {
                $title = trim((string) ($error['title'] ?? 'Front API error'));
                $message = trim((string) ($error['message'] ?? ''));
                $details = trim((string) ($error['details'] ?? ''));

                return trim($title.($message !== '' ? ': '.$message : '').($details !== '' ? ' ('.$details.')' : ''));
            }

            $message = trim((string) ($payload['message'] ?? ''));
            if ($message !== '') {
                return 'Front API request failed: '.$message;
            }
        }

        $body = trim($response->body());
        if ($body !== '') {
            return 'Front API request failed ('.$response->status().'): '.$body;
        }

        return 'Front API request failed with HTTP '.$response->status().'. Check that your token is valid and has tags:read, inboxes:read, and conversations:read scopes.';
    }

    private function absoluteUrl(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return $this->baseUrl.'/'.ltrim($path, '/');
    }
}
