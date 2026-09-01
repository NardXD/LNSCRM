<?php

namespace App\Services;

use App\Models\StoreganiseIntegration;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StoreganiseService
{
    protected ?StoreganiseIntegration $integration = null;

    protected ?string $rawApiKey = null;

    protected ?string $businessCode = null;

    public function __construct(?int $companyId = null, ?string $businessCode = null, ?string $rawApiKey = null)
    {
        if ($businessCode && $rawApiKey) {
            $this->businessCode = strtolower(trim($businessCode));
            $this->rawApiKey = $rawApiKey;

            return;
        }

        if ($companyId) {
            $this->integration = StoreganiseIntegration::where('company_id', $companyId)
                ->where('is_active', true)
                ->first();
        }
    }

    public function isConfigured(): bool
    {
        return $this->integration !== null || ($this->businessCode && $this->rawApiKey);
    }

    public function getBaseUrl(): string
    {
        $code = $this->businessCode ?? $this->integration?->business_code;

        return self::baseUrlFromBusinessCode((string) $code);
    }

    public static function baseUrlFromBusinessCode(string $value): string
    {
        $value = trim($value);

        if (preg_match('#^https?://#i', $value)) {
            $parsed = parse_url($value);
            $host = strtolower((string) ($parsed['host'] ?? ''));

            if ($host !== '') {
                return 'https://'.$host;
            }
        }

        $code = strtolower($value);
        $code = preg_replace('#\.storeganise\.com/?$#', '', $code) ?? $code;
        $code = trim($code, '/');

        return 'https://'.$code.'.storeganise.com';
    }

    public function adminUrl(string $path): string
    {
        $base = rtrim($this->getBaseUrl(), '/');
        $base = (string) preg_replace('#(?:/api(?:/v1(?:/admin)?)?)+$#i', '', $base);

        return $base.'/api/v1/admin/'.ltrim($path, '/');
    }

    protected function getApiKey(): ?string
    {
        if ($this->rawApiKey) {
            return $this->rawApiKey;
        }

        if (! $this->integration?->api_key) {
            return null;
        }

        try {
            return Crypt::decryptString($this->integration->api_key);
        } catch (\Exception $e) {
            Log::error('Storeganise: Failed to decrypt API key', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return array{valid: bool, error?: string}
     */
    public function validateCredentials(): array
    {
        $apiKey = $this->getApiKey();
        $baseUrl = $this->getBaseUrl();

        if (! $apiKey) {
            return ['valid' => false, 'error' => 'API key is required.'];
        }

        if (! preg_match('/^[a-z0-9-]+$/', strtolower((string) ($this->businessCode ?? $this->integration?->business_code ?? '')))) {
            return ['valid' => false, 'error' => 'Business code must contain only lowercase letters, numbers, and hyphens.'];
        }

        $response = $this->request('GET', 'settings');
        if ($response->successful()) {
            return ['valid' => true];
        }

        if ($response->status() === 404) {
            return [
                'valid' => false,
                'error' => 'Storeganise returned 404. Check the business code matches your admin URL subdomain (e.g. locnstor for https://locnstor.storeganise.com).',
            ];
        }

        return [
            'valid' => false,
            'error' => $this->extractErrorMessage($response) ?: 'Storeganise credentials could not be verified (HTTP '.$response->status().').',
        ];
    }

    /**
     * @return list<array{id: string, name: string, code: ?string}>
     */
    public function listSites(): array
    {
        $sites = [];
        $limit = 100;

        for ($offset = 0, $page = 0; $page < 10; $offset += $limit, $page++) {
            $data = $this->get('sites', ['limit' => $limit, 'offset' => $offset]);
            if ($data === null) {
                break;
            }

            $items = $this->normalizeList($data);
            if ($items === [] && $this->looksLikeSite($data)) {
                $items = [$data];
            }

            foreach ($items as $site) {
                if (! is_array($site)) {
                    continue;
                }

                $normalized = $this->normalizeSite($site);
                if ($normalized !== null) {
                    $sites[$normalized['id']] = $normalized;
                }
            }

            if (count($items) < $limit) {
                break;
            }
        }

        return collect($sites)
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSite(string $siteIdOrCode): ?array
    {
        $response = $this->request('GET', 'sites/'.rawurlencode($siteIdOrCode));
        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getUser(string $idOrEmail): ?array
    {
        $response = $this->request('GET', 'users/'.rawurlencode($idOrEmail));
        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, error?: string, user?: array<string, mixed>}
     */
    public function createUser(array $payload): array
    {
        $response = $this->request('POST', 'users', $payload);
        if ($response->successful()) {
            $data = $response->json();

            return [
                'success' => true,
                'user' => is_array($data) ? $data : [],
            ];
        }

        if (in_array($response->status(), [409, 422], true) && ! empty($payload['email'])) {
            $existing = $this->getUser((string) $payload['email']);
            if ($existing !== null) {
                return [
                    'success' => true,
                    'user' => $existing,
                ];
            }
        }

        return [
            'success' => false,
            'error' => $this->extractErrorMessage($response) ?: 'Failed to create Storeganise user (HTTP '.$response->status().').',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, error?: string, user?: array<string, mixed>}
     */
    public function updateUser(string $userId, array $payload): array
    {
        unset($payload['password']);

        $response = $this->request('PUT', 'users/'.rawurlencode($userId), $payload);
        if ($response->successful()) {
            $data = $response->json();

            return [
                'success' => true,
                'user' => is_array($data) ? $data : [],
            ];
        }

        return [
            'success' => false,
            'error' => $this->extractErrorMessage($response) ?: 'Failed to update Storeganise user (HTTP '.$response->status().').',
        ];
    }

    /**
     * @return list<array{id: string, email: ?string, phone: ?string, name: string, match_types: list<string>, match_values: list<string>}>
     */
    public function findDuplicateUsers(array $emails, array $phones, ?string $excludeUserId = null): array
    {
        $matches = [];

        foreach ($emails as $email) {
            $email = strtolower(trim((string) $email));
            if ($email === '') {
                continue;
            }

            $user = $this->getUser($email);
            if ($user === null) {
                $user = $this->searchUser($email);
            }

            if ($user !== null) {
                $this->addDuplicateMatch($matches, $user, 'email', $email, $excludeUserId);
            }
        }

        foreach ($phones as $phone) {
            $phone = trim((string) $phone);
            if ($phone === '') {
                continue;
            }

            foreach ($this->searchUsers($phone) as $user) {
                $userPhone = (string) ($user['phone'] ?? '');
                if ($userPhone === '' || ! $this->phoneMatches($phone, $userPhone)) {
                    continue;
                }

                $this->addDuplicateMatch($matches, $user, 'phone', $phone, $excludeUserId);
            }
        }

        return collect($matches)
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchUsers(string $term, int $limit = 10): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        $data = $this->get('users', ['search' => $term, 'limit' => $limit]);

        return $this->normalizeList($data);
    }

  /**
     * @return array<string, mixed>|null
     */
    public function searchUser(string $term): ?array
    {
        $wantEmail = str_contains($term, '@') ? strtolower(trim($term)) : '';
        $wantPhone = $this->digits($term);

        foreach ($this->searchUsers($term) as $user) {
            if (! is_array($user) || empty($user['id'])) {
                continue;
            }

            $email = strtolower(trim((string) ($user['email'] ?? '')));
            if ($wantEmail !== '' && $email === $wantEmail) {
                return $user;
            }

            $phone = $this->digits((string) ($user['phone'] ?? ''));
            if ($wantPhone !== '' && $phone !== '' && $this->phoneMatches($term, (string) ($user['phone'] ?? ''))) {
                return $user;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $user
     * @return array{id: string, email: ?string, phone: ?string, name: string}
     */
    public function summarizeUser(array $user): array
    {
        $first = trim((string) ($user['firstName'] ?? ''));
        $last = trim((string) ($user['lastName'] ?? ''));
        $name = trim($first.' '.$last);
        if ($name === '') {
            $name = trim((string) ($user['name'] ?? ''));
        }
        if ($name === '') {
            $name = trim((string) ($user['email'] ?? '')) ?: 'Storeganise user';
        }

        return [
            'id' => (string) ($user['id'] ?? ''),
            'email' => isset($user['email']) ? (string) $user['email'] : null,
            'phone' => isset($user['phone']) ? (string) $user['phone'] : null,
            'name' => $name,
            'site_id' => isset($user['siteId']) ? (string) $user['siteId'] : (isset($user['site_id']) ? (string) $user['site_id'] : null),
        ];
    }

    public function phoneMatches(string $left, string $right): bool
    {
        $a = $this->digits($left);
        $b = $this->digits($right);
        if ($a === '' || $b === '') {
            return false;
        }

        return $a === $b || str_ends_with($a, $b) || str_ends_with($b, $a);
    }

    public function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    /**
     * @param  array<string, array{id: string, email: ?string, phone: ?string, name: string, match_types: list<string>, match_values: list<string>}>  $matches
     * @param  array<string, mixed>  $user
     */
    protected function addDuplicateMatch(array &$matches, array $user, string $type, string $value, ?string $excludeUserId): void
    {
        $id = (string) ($user['id'] ?? '');
        if ($id === '' || ($excludeUserId !== null && $id === $excludeUserId)) {
            return;
        }

        if (! isset($matches[$id])) {
            $summary = $this->summarizeUser($user);
            $matches[$id] = array_merge($summary, [
                'match_types' => [],
                'match_values' => [],
            ]);
        }

        if (! in_array($type, $matches[$id]['match_types'], true)) {
            $matches[$id]['match_types'][] = $type;
        }

        if (! in_array($value, $matches[$id]['match_values'], true)) {
            $matches[$id]['match_values'][] = $value;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $path, array $query = []): ?array
    {
        $response = $this->request('GET', $path, $query);
        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    protected function request(string $method, string $path, array $body = []): Response
    {
        $apiKey = $this->getApiKey();
        $url = $this->adminUrl($path);

        $pending = Http::timeout(30)
            ->withHeaders([
                'Authorization' => 'ApiKey '.($apiKey ?? ''),
                'Accept' => 'application/json',
            ]);

        try {
            return match (strtoupper($method)) {
                'POST' => $pending->asJson()->post($url, $body),
                'PUT' => $pending->asJson()->put($url, $body),
                'PATCH' => $pending->asJson()->patch($url, $body),
                'DELETE' => $pending->delete($url, $body),
                default => $pending->get($url, $body),
            };
        } catch (\Throwable $e) {
            Log::error('Storeganise API request failed', [
                'method' => $method,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return new Response(new \GuzzleHttp\Psr7\Response(503, [], json_encode([
                'error' => ['message' => 'Could not reach Storeganise.'],
            ])));
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function normalizeList(?array $data): array
    {
        if (! is_array($data)) {
            return [];
        }

        foreach (['items', 'data', 'sites', 'results', 'users'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return array_values(array_filter($data[$key], 'is_array'));
            }
        }

        if (array_is_list($data)) {
            return array_values(array_filter($data, 'is_array'));
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $site
     * @return array{id: string, name: string, code: ?string}|null
     */
    protected function normalizeSite(array $site): ?array
    {
        if (
            ($site['archived'] ?? false) === true
            || ($site['disabled'] ?? false) === true
            || ($site['hidden'] ?? false) === true
            || ($site['active'] ?? true) === false
            || in_array((string) ($site['status'] ?? ''), ['archived', 'disabled', 'inactive'], true)
        ) {
            return null;
        }

        $id = (string) ($site['id'] ?? '');
        $code = trim((string) ($site['code'] ?? ''));
        if ($id === '' && $code === '') {
            return null;
        }

        $name = $this->localizedString($site['name'] ?? null)
            ?: $this->localizedString($site['title'] ?? null)
            ?: $this->localizedString($site['label'] ?? null)
            ?: ($code !== '' ? $code : 'Site '.$id);

        return [
            'id' => $id !== '' ? $id : $code,
            'name' => $name,
            'code' => $code !== '' ? $code : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function looksLikeSite(array $data): bool
    {
        return isset($data['id']) || isset($data['code']);
    }

    protected function localizedString(mixed $value): string
    {
        if (is_string($value) || is_numeric($value)) {
            return trim((string) $value);
        }

        if (! is_array($value)) {
            return '';
        }

        foreach (['en', 'EN'] as $locale) {
            if (! empty($value[$locale]) && is_scalar($value[$locale])) {
                return trim((string) $value[$locale]);
            }
        }

        foreach ($value as $item) {
            if (is_scalar($item) && trim((string) $item) !== '') {
                return trim((string) $item);
            }
        }

        return '';
    }

    protected function extractErrorMessage(Response $response): ?string
    {
        $body = $response->json();
        if (! is_array($body)) {
            return null;
        }

        if (isset($body['error']) && is_array($body['error'])) {
            return $body['error']['message'] ?? $body['error']['detail'] ?? null;
        }

        return $body['message'] ?? null;
    }
}
