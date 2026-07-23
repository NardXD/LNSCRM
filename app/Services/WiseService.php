<?php

namespace App\Services;

use App\Models\EmployeePayHistory;
use App\Models\PayrollReportItem;
use App\Models\WiseIntegration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WiseService
{
    protected ?WiseIntegration $integration = null;

    protected ?string $rawToken = null;

    /** Last error from fetchProfiles (for debugging). */
    public static ?array $lastProfileFetchError = null;

    /** Last error from getRecipients (for debugging). */
    public static ?array $lastRecipientsFetchError = null;

    public function __construct(?int $companyId = null, ?string $rawToken = null, bool $isSandbox = false)
    {
        if ($rawToken) {
            $this->rawToken = $rawToken;
            $this->integration = new WiseIntegration(['is_sandbox' => $isSandbox, 'is_active' => true]);

            return;
        }

        if ($companyId) {
            $this->integration = WiseIntegration::where('company_id', $companyId)
                ->where('is_active', true)
                ->first();
        }
    }

    public function isConfigured(): bool
    {
        return $this->integration !== null;
    }

    protected function getBaseUrl(): string
    {
        return $this->getBaseUrls()['primary'];
    }

    /**
     * Get API base URLs. Sandbox URL can be overridden via WISE_SANDBOX_API_URL if api.wise-sandbox.com has DNS issues.
     */
    protected function getBaseUrls(): array
    {
        $sandbox = $this->integration?->is_sandbox ?? false;

        $sandboxUrl = config('services.wise.sandbox_url', 'https://api.wise-sandbox.com');

        return [
            'primary' => $sandbox ? rtrim($sandboxUrl, '/') : 'https://api.wise.com',
            'legacy' => $sandbox ? 'https://api.sandbox.transferwise.tech' : 'https://api.transferwise.com',
        ];
    }

    protected function getToken(): ?string
    {
        if ($this->rawToken) {
            return $this->rawToken;
        }

        if (! $this->integration?->api_token) {
            return null;
        }

        try {
            return Crypt::decryptString($this->integration->api_token);
        } catch (\Exception $e) {
            Log::error('Wise: Failed to decrypt API token', ['error' => $e->getMessage()]);

            return null;
        }
    }

    protected function getProfileId(): ?int
    {
        $id = $this->integration?->profile_id;

        // Auto-fetch from Wise API when not set, or when a stored profile is no longer available.
        $profiles = $this->fetchProfiles();
        if (empty($profiles)) {
            return ($id && is_numeric($id)) ? (int) $id : null;
        }

        if ($id && is_numeric($id)) {
            $storedProfile = collect($profiles)->first(fn ($p) => (int) ($p['id'] ?? 0) === (int) $id);
            if ($storedProfile) {
                return (int) $id;
            }
        }

        // Prefer business profile, then first available
        $business = collect($profiles)->first(fn ($p) => strtolower($p['type'] ?? '') === 'business');
        $profile = $business ?? $profiles[0];
        $profileId = $profile['id'] ?? null;
        if ($profileId) {
            // Save to integration for future use
            $this->integration?->update(['profile_id' => (string) $profileId]);

            return (int) $profileId;
        }

        return null;
    }

    /**
     * Fetch profiles from Wise API. Public for use in Integrations.
     *
     * @return array<array{id: int, type: string, fullName?: string, businessName?: string}>
     */
    public function getProfiles(): array
    {
        return $this->fetchProfiles();
    }

    /**
     * Fetch recipient accounts from Wise API. Tries v2 then v1 (v1 uses profile param).
     * Fetches all pages and all profiles so recipients added via Wise dashboard are included.
     * Returns numeric IDs for payroll.
     *
     * @return array<array{id: int, name: string, currency: string, accountSummary?: string}>
     */
    public function getRecipients(): array
    {
        $profileId = $this->getProfileId();
        if (! $profileId) {
            self::$lastRecipientsFetchError = ['error' => 'Profile ID is not set.'];

            return [];
        }

        $token = $this->getToken();
        if (! $token) {
            self::$lastRecipientsFetchError = ['error' => 'API token is not configured.'];

            return [];
        }

        $urls = $this->getBaseUrls();

        foreach (['primary', 'legacy'] as $key) {
            $baseUrl = $urls[$key];
            try {
                // Collect recipient IDs from all profiles to avoid missing dashboard-created recipients
                $allProfiles = collect($this->fetchProfiles())->pluck('id')->filter()->unique()->values()->toArray();
                if (empty($allProfiles)) {
                    $allProfiles = [$profileId];
                } elseif (! in_array($profileId, $allProfiles)) {
                    array_unshift($allProfiles, $profileId);
                }

                // STEP 1: Fetch accounts from /v2/accounts to use as a detail-enrichment source.
                // Build a lookup map keyed by normalised name so contacts can be enriched.
                $accountsByName = [];
                $accountSeenIds = [];
                foreach ($allProfiles as $pid) {
                    $pages = $this->fetchAllRecipientPages($baseUrl, $token, (int) $pid);
                    if ($pages === null) {
                        $v1 = Http::timeout(15)->withToken($token)
                            ->get("{$baseUrl}/v1/accounts", ['profile' => $pid]);
                        if ($v1->successful()) {
                            $v1data = $v1->json();
                            $pages = is_array($v1data) ? $v1data : [];
                        }
                    }
                    foreach ((array) $pages as $acc) {
                        $aid = $acc['id'] ?? null;
                        if (! $aid || isset($accountSeenIds[$aid])) {
                            continue;
                        }
                        $accountSeenIds[$aid] = true;
                        $accName = trim(strtolower(
                            $acc['accountHolderName'] ?? ($acc['name']['fullName'] ?? ($acc['name'] ?? ''))
                        ));
                        if ($accName !== '') {
                            $accountsByName[$accName] = $acc;
                        }
                    }
                }

                $allContent = [];
                $seenIds = [];

                // STEP 2: Fetch contacts (PRIMARY source for IDs shown in the Wise app).
                foreach ($allProfiles as $pid) {
                    $contacts = $this->fetchProfileContacts($baseUrl, $token, (int) $pid);
                    foreach ($contacts as $contact) {
                        $id = $contact['id'] ?? null;
                        if (! $id || isset($seenIds[$id])) {
                            continue;
                        }
                        $seenIds[$id] = true;

                        // Enrich the contact with full account details (currency, bank, accountNumber)
                        // by matching on the holder name.
                        $contactName = trim(strtolower(
                            $contact['name'] ?? ($contact['accountHolderName'] ?? '')
                        ));
                        if ($contactName !== '' && isset($accountsByName[$contactName])) {
                            $enriched = $accountsByName[$contactName];
                            // Save numeric account ID before merge so payroll can use it for transfers
                            $numericAccountId = is_numeric($enriched['id'] ?? null) ? (int) $enriched['id'] : null;
                            // Merge: contact fields take precedence for id/name/type;
                            // account fields fill in missing currency and details.
                            $contact = array_merge($enriched, $contact);
                            // Ensure the contact's own UUID id is kept, not the numeric account id
                            $contact['id'] = $id;
                            if ($numericAccountId !== null) {
                                $contact['_numeric_account_id'] = $numericAccountId;
                            }
                        }

                        // Extract tag from all possible field names so it's available at contact['tag']
                        if (empty($contact['tag'])) {
                            $cd = $contact['details'] ?? $contact['contactDetails'] ?? [];
                            $found = $cd['wisetag'] ?? $cd['tag'] ?? $cd['handle'] ?? $cd['identifier'] ?? $cd['email'] ?? null;
                            if ($found !== null) {
                                $contact['tag'] = $found;
                            }
                        }
                        // Expose top-level tag into details for downstream extraction
                        if (! empty($contact['tag']) && empty($contact['details']['wisetag'])) {
                            $contact['details']['wisetag'] = $contact['tag'];
                        }

                        $allContent[] = $contact;
                    }
                }

                // FALLBACK: use /v2/accounts directly if contacts endpoint returned nothing
                if (empty($allContent)) {
                    foreach ($accountsByName as $acc) {
                        $aid = $acc['id'] ?? null;
                        if ($aid && ! isset($seenIds[$aid])) {
                            $seenIds[$aid] = true;
                            $allContent[] = $acc;
                        }
                    }
                }

                if (empty($allContent)) {
                    $err = ['base' => $baseUrl, 'error' => 'Empty response from all profiles'];
                    Log::debug('Wise getRecipients attempt', $err);
                    self::$lastRecipientsFetchError = $err;

                    continue;
                }

                $filtered = array_filter($allContent, function ($a) {
                    $id = $a['id'] ?? null;

                    return $id !== null && $id !== '' && $id !== 0 && $id !== '0';
                });

                if (empty($filtered)) {
                    self::$lastRecipientsFetchError = [
                        'error' => 'No recipients found. Add recipients in Wise or check that your Profile ID is correct.',
                    ];

                    return [];
                }

                self::$lastRecipientsFetchError = null;

                $mapped = array_map(function ($acc) {
                    $rawType = $acc['type'] ?? '';
                    $type = strtolower($rawType);

                    // Merge all possible sub-objects into one flat lookup array
                    $details = $acc['details'] ?? [];
                    $acctObj = $acc['account'] ?? [];                  // some endpoints use 'account' key
                    $typeKey = strtoupper($rawType);
                    if (isset($details[$typeKey]) && is_array($details[$typeKey])) {
                        $details = array_merge($details[$typeKey], $details); // type-keyed nesting
                    }
                    // Combine details + account sub-object + top-level for one unified lookup
                    $flat = array_merge($acctObj, $details, $acc);

                    // Raw details before flat merge — flat merge puts $acc last which can overwrite
                    // details sub-fields with top-level null values (e.g. email: null at top level
                    // kills details.email = '@handle').
                    $rawDetails = $acc['details'] ?? $acc['contactDetails'] ?? [];

                    // @tag: check every known field name
                    $tag = $flat['tag'] ?? $flat['wisetag'] ?? $flat['handle'] ?? $flat['identifier'] ?? null;
                    // Also check raw details directly to avoid null-override from flat merge
                    if ($tag === null) {
                        $tag = $rawDetails['wisetag'] ?? $rawDetails['tag'] ?? $rawDetails['handle'] ?? $rawDetails['identifier'] ?? null;
                    }
                    if ($tag === null && in_array($type, ['wisetag', 'email'])) {
                        // email field sometimes holds the wisetag handle
                        $tag = $rawDetails['email'] ?? ($flat['email'] ?: null);
                    }
                    // Last resort: scan every scalar value for an @-prefixed string (all types)
                    if ($tag === null) {
                        array_walk_recursive($acc, function ($val) use (&$tag) {
                            if ($tag === null && is_string($val) && strlen($val) > 1 && $val[0] === '@') {
                                $tag = $val;
                            }
                        });
                    }

                    $accountSummary = $acc['accountSummary'] ?? null;

                    // Name: contacts return a plain string; accounts nest it under name.fullName
                    $rawName = $acc['name'] ?? null;
                    $name = (is_array($rawName) ? ($rawName['fullName'] ?? null) : $rawName)
                        ?? $acc['accountHolderName']
                        ?? null;
                    if (! $name) {
                        $name = ($accountSummary && $accountSummary !== $tag) ? $accountSummary : null;
                    }
                    if (! $name) {
                        $name = $tag ?? ('Recipient #'.($acc['id'] ?? ''));
                    }

                    // Bank name: structured fields → fall back to the raw type string
                    $noBank = ['wisetag', 'email', ''];
                    $bank = $flat['bankName'] ?? $flat['bankCode']
                        ?? (! in_array($type, $noBank) ? $rawType : null);

                    // Account number / identifier: every common field name across all sources
                    $accountNumber = $flat['accountNumber'] ?? $flat['iban']
                        ?? $flat['phoneNumber'] ?? $flat['sortCode']
                        ?? $flat['routingNumber'] ?? $flat['ifscCode']
                        ?? null;

                    $rawId = $acc['id'] ?? 0;
                    $id = is_numeric($rawId) ? (int) $rawId : (string) $rawId;
                    // Numeric account ID for payroll transfers (UUID contacts store it in _numeric_account_id)
                    $numericAccountId = isset($acc['_numeric_account_id']) ? $acc['_numeric_account_id']
                        : (is_numeric($rawId) ? (int) $rawId : null);

                    return [
                        'id' => $id,
                        'account_id' => $numericAccountId,
                        'name' => $name,
                        'currency' => ($flat['currency'] ?? $flat['targetCurrency'] ?? null)
                            ?: ($acc['user']['currency'] ?? $acc['user']['targetCurrency'] ?? ''),
                        'accountSummary' => $accountSummary,
                        'tag' => $tag,
                        'type' => $type,
                        'bank' => $bank,
                        'accountNumber' => $accountNumber,
                        'profile_id' => isset($acc['profileId']) && is_numeric($acc['profileId']) ? (int) $acc['profileId'] : null,
                    ];
                }, array_values($filtered));

                return $this->dedupeRecipients($mapped, $profileId);
            } catch (\Exception $e) {
                $err = ['base' => $baseUrl, 'error' => $e->getMessage()];
                Log::debug('Wise getRecipients error', $err);
                self::$lastRecipientsFetchError = $err;
            }
        }

        return [];
    }

    /**
     * Collapse duplicate recipients that represent the same person.
     *
     * Wise can return more than one contact per recipient (e.g. a @wisetag contact plus an
     * empty bank-detail shell), each with its own UUID. We group by normalised name and keep a
     * single entry, preferring the configured profile's record and the most useful payment details,
     * then merging any missing fields (tag, account_id, currency, bank, accountNumber) from its twins.
     *
     * @param  array<int, array<string, mixed>>  $recipients
     * @return array<int, array<string, mixed>>
     */
    private function dedupeRecipients(array $recipients, ?int $preferredProfileId = null): array
    {
        $score = function (array $r) use ($preferredProfileId): int {
            return ($preferredProfileId !== null && (int) ($r['profile_id'] ?? 0) === $preferredProfileId ? 8 : 0)
                + ($r['account_id'] !== null ? 4 : 0)
                + (! empty($r['tag']) ? 2 : 0)
                + (! empty($r['accountNumber']) ? 1 : 0);
        };

        $groups = [];
        foreach ($recipients as $r) {
            $name = trim(strtolower((string) ($r['name'] ?? '')));
            // Records without a usable name can't be safely merged — keep them by unique id.
            $key = ($name === '' || str_starts_with($name, 'recipient #'))
                ? 'id:'.($r['id'] ?? uniqid())
                : 'name:'.$name;
            $groups[$key][] = $r;
        }

        $result = [];
        foreach ($groups as $group) {
            if (count($group) === 1) {
                $result[] = $group[0];

                continue;
            }

            usort($group, fn ($a, $b) => $score($b) <=> $score($a));
            $primary = $group[0];

            foreach (array_slice($group, 1) as $dup) {
                foreach (['account_id', 'tag', 'bank', 'accountNumber', 'accountSummary'] as $field) {
                    if (empty($primary[$field]) && ! empty($dup[$field])) {
                        $primary[$field] = $dup[$field];
                    }
                }
                if (($primary['currency'] ?? '') === '' && ($dup['currency'] ?? '') !== '') {
                    $primary['currency'] = $dup['currency'];
                }
            }

            $result[] = $primary;
        }

        return array_values($result);
    }

    /**
     * Fetch all paginated pages from the Wise /v2/accounts endpoint.
     *
     * Wise uses seek-based pagination here (not page numbers): each response returns at most
     * `size` records (capped at 20 by Wise) plus a `seekPositionForNext` value. That value must be
     * passed as `seekPosition` to fetch the next page, and is null once there are no more pages.
     *
     * @return array<int, array<string, mixed>>|null Null if the first request fails, otherwise a flat array.
     */
    private function fetchAllRecipientPages(string $baseUrl, string $token, ?int $profileId): ?array
    {
        $all = [];
        $seekPosition = null;
        $page = 0;
        $maxPages = 100; // safety cap (~2 000 recipients at 20/page)

        do {
            $params = ['size' => 20];
            if ($profileId !== null) {
                $params['profileId'] = $profileId;
            }
            if ($seekPosition !== null) {
                $params['seekPosition'] = $seekPosition;
            }

            $response = Http::timeout(15)->withToken($token)->get("{$baseUrl}/v2/accounts", $params);

            if (! $response->successful()) {
                return $page === 0 ? null : $all;
            }

            $data = $response->json();

            if (isset($data['content']) && is_array($data['content'])) {
                $content = $data['content'];
                $seekPosition = $data['seekPositionForNext'] ?? null;
            } elseif (is_array($data) && (empty($data) || isset($data[0]))) {
                // Legacy/flat array response — no pagination metadata.
                $content = $data;
                $seekPosition = null;
            } else {
                break;
            }

            $all = array_merge($all, $content);
            $page++;

            if ($seekPosition === null || empty($content) || $page >= $maxPages) {
                break;
            }
        } while (true);

        return $all;
    }

    /**
     * Fetch Wise @tag contacts from GET /v2/profiles/{profileId}/contacts?isDirectIdentifierCreation=true.
     * The endpoint is cursor-paginated: each response includes a `nextPage` cursor that must be
     * passed back as the `page` query parameter to fetch subsequent pages. We loop until it is null
     * so every recipient is returned, not just the first page.
     *
     * @return array<int, array<string, mixed>> Flat array of raw contact objects (empty on failure).
     */
    private function fetchProfileContacts(string $baseUrl, string $token, int $profileId): array
    {
        $all = [];
        $nextPage = null;
        $guard = 0;
        $maxPages = 50;

        try {
            do {
                $params = ['isDirectIdentifierCreation' => 'true'];
                if ($nextPage !== null && $nextPage !== '') {
                    $params['page'] = $nextPage;
                }

                $response = Http::timeout(15)->withToken($token)
                    ->get("{$baseUrl}/v2/profiles/{$profileId}/contacts", $params);

                if (! $response->successful()) {
                    break;
                }

                $data = $response->json();

                if (isset($data['contacts']) && is_array($data['contacts'])) {
                    $contacts = $data['contacts'];
                } elseif (isset($data['content']) && is_array($data['content'])) {
                    $contacts = $data['content'];
                } elseif (is_array($data) && (empty($data) || isset($data[0]))) {
                    $contacts = $data;
                } else {
                    $contacts = [];
                }

                $all = array_merge($all, $contacts);

                $nextPage = is_array($data) ? ($data['nextPage'] ?? null) : null;
                $guard++;
            } while ($nextPage !== null && $nextPage !== '' && $guard < $maxPages);

            if (! empty($all)) {
                Log::debug('Wise contacts raw all', [
                    'profileId' => $profileId,
                    'pages' => $guard,
                    'count' => count($all),
                ]);
            }

            return $all;
        } catch (\Exception $e) {
            Log::debug('Wise fetchProfileContacts error', ['profileId' => $profileId, 'error' => $e->getMessage()]);

            return $all;
        }
    }

    /**
     * Get last recipients fetch error for user-facing messages.
     */
    public static function getLastRecipientsFetchError(): ?string
    {
        $e = self::$lastRecipientsFetchError;
        if (! $e) {
            return null;
        }
        if (isset($e['error'])) {
            return $e['error'];
        }
        $status = $e['status'] ?? '';
        $body = $e['body'] ?? '';
        $decoded = is_string($body) ? json_decode($body, true) : $body;
        $msg = $decoded['errors'][0]['message'] ?? $decoded['error'] ?? $decoded['message'] ?? $body;

        return "HTTP {$status}: ".(is_string($msg) ? $msg : json_encode($msg));
    }

    /**
     * Create a quote for recipient account requirements (used to discover required fields).
     *
     * @return array{success: bool, quote_id?: string, error?: string}
     */
    public function createQuoteForRecipientRequirements(string $targetCurrency): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'Wise integration is not configured.'];
        }

        $token = $this->getToken();
        if (! $token) {
            return ['success' => false, 'error' => 'Invalid Wise API token.'];
        }

        $profileId = $this->getProfileId();
        if (! $profileId) {
            return ['success' => false, 'error' => 'Wise Profile ID is required.'];
        }

        $baseUrl = $this->getBaseUrl();
        $currency = strtoupper(substr($targetCurrency, 0, 3));

        try {
            $response = Http::timeout(15)->withToken($token)
                ->post("{$baseUrl}/v3/profiles/{$profileId}/quotes", [
                    'sourceCurrency' => $currency,
                    'targetCurrency' => $currency,
                    'targetAmount' => 100,
                    'payOut' => 'BANK_TRANSFER',
                ]);

            if (! $response->successful()) {
                $body = $response->json();
                $err = $body['errors'][0]['message'] ?? $response->body();

                return ['success' => false, 'error' => 'Quote failed: '.$err];
            }

            $data = $response->json();
            $quoteId = $data['id'] ?? $data['uuid'] ?? null;
            if (! $quoteId) {
                return ['success' => false, 'error' => 'Invalid quote response.'];
            }

            return ['success' => true, 'quote_id' => (string) $quoteId];
        } catch (\Exception $e) {
            Log::error('Wise createQuote error', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get account requirements for recipient creation (dynamic form fields).
     *
     * @return array{success: bool, requirements?: array, error?: string}
     */
    public function getAccountRequirements(string $quoteId): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'Wise integration is not configured.'];
        }

        $token = $this->getToken();
        if (! $token) {
            return ['success' => false, 'error' => 'Invalid Wise API token.'];
        }

        $baseUrl = $this->getBaseUrl();

        try {
            $response = Http::timeout(15)->withToken($token)
                ->withHeaders(['Accept-Minor-Version' => '1'])
                ->get("{$baseUrl}/v1/quotes/{$quoteId}/account-requirements");

            if (! $response->successful()) {
                $body = $response->json();
                $err = $body['errors'][0]['message'] ?? $response->body();

                return ['success' => false, 'error' => 'Failed to fetch requirements: '.$err];
            }

            $requirements = $response->json();

            return ['success' => true, 'requirements' => is_array($requirements) ? $requirements : []];
        } catch (\Exception $e) {
            Log::error('Wise getAccountRequirements error', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Post account requirements to get refreshed requirements (when fields have refreshRequirementsOnChange).
     *
     * @return array{success: bool, requirements?: array, error?: string}
     */
    public function postAccountRequirements(string $quoteId, array $payload): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'Wise integration is not configured.'];
        }

        $token = $this->getToken();
        if (! $token) {
            return ['success' => false, 'error' => 'Invalid Wise API token.'];
        }

        $baseUrl = $this->getBaseUrl();

        try {
            $response = Http::timeout(15)->withToken($token)
                ->withHeaders(['Accept-Minor-Version' => '1'])
                ->post("{$baseUrl}/v1/quotes/{$quoteId}/account-requirements", $payload);

            if (! $response->successful()) {
                $body = $response->json();
                $err = $body['errors'][0]['message'] ?? $response->body();

                return ['success' => false, 'error' => 'Failed to refresh requirements: '.$err];
            }

            $requirements = $response->json();

            return ['success' => true, 'requirements' => is_array($requirements) ? $requirements : []];
        } catch (\Exception $e) {
            Log::error('Wise postAccountRequirements error', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Create a recipient account.
     *
     * @return array{success: bool, recipient_id?: int, recipient?: array, error?: string}
     */
    public function createRecipient(array $payload): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'Wise integration is not configured.'];
        }

        $token = $this->getToken();
        if (! $token) {
            return ['success' => false, 'error' => 'Invalid Wise API token.'];
        }

        $profileId = $this->getProfileId();
        if (! $profileId) {
            return ['success' => false, 'error' => 'Wise Profile ID is required.'];
        }

        $baseUrl = $this->getBaseUrl();

        if (empty($payload['profile'])) {
            $payload['profile'] = $profileId;
        }

        try {
            Log::info('Wise createRecipient → POST /v1/accounts', ['url' => "{$baseUrl}/v1/accounts", 'body' => $payload]);

            $response = Http::timeout(15)->withToken($token)
                ->asJson()
                ->post("{$baseUrl}/v1/accounts", $payload);

            Log::info('Wise createRecipient ← response', ['status' => $response->status(), 'body' => $response->body()]);

            if (! $response->successful()) {
                $body = $response->json();
                $err = $body['errors'][0]['message'] ?? $response->body();

                return ['success' => false, 'error' => 'Recipient creation failed: '.$err];
            }

            $recipient = $response->json();
            $recipientId = $recipient['id'] ?? null;
            if (! $recipientId) {
                return ['success' => false, 'error' => 'Invalid recipient response.'];
            }

            return [
                'success' => true,
                'recipient_id' => (int) $recipientId,
                'recipient' => $recipient,
            ];
        } catch (\Exception $e) {
            Log::error('Wise createRecipient error', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Add a recipient to Wise by identifier (Wise @tag, email, or phone number).
     *
     * Uses POST /v2/profiles/{profileId}/contacts?isDirectIdentifierCreation=true, which finds the
     * discoverable Wise profile and adds it as a contact — reflecting on the Wise dashboard.
     *
     * @return array{success: bool, contact_id?: string, name?: string, error?: string}
     */
    public function createContactByIdentifier(string $identifier, string $targetCurrency): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'Wise integration is not configured.'];
        }

        $token = $this->getToken();
        if (! $token) {
            return ['success' => false, 'error' => 'Invalid Wise API token.'];
        }

        $profileId = $this->getProfileId();
        if (! $profileId) {
            return ['success' => false, 'error' => 'Wise Profile ID is required.'];
        }

        $identifier = trim($identifier);
        if ($identifier === '') {
            return ['success' => false, 'error' => 'A Wise tag, email, or phone number is required.'];
        }

        $targetCurrency = strtoupper(substr(trim($targetCurrency), 0, 3));
        if (strlen($targetCurrency) !== 3) {
            return ['success' => false, 'error' => 'A 3-letter currency code is required for Wise tag recipients.'];
        }

        // Normalise a bare wisetag (no @, not an email or phone) to the expected @handle form.
        $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;
        $isPhone = (bool) preg_match('/^\+?\d[\d\s\-]+$/', $identifier);
        if (! $isEmail && ! $isPhone && ! str_starts_with($identifier, '@')) {
            $identifier = '@'.$identifier;
        }

        $baseUrl = $this->getBaseUrl();

        $payload = [
            'identifier' => $identifier,
            'targetCurrency' => $targetCurrency,
        ];

        try {
            $response = Http::timeout(15)->withToken($token)
                ->asJson()
                ->post("{$baseUrl}/v2/profiles/{$profileId}/contacts?isDirectIdentifierCreation=true", $payload);

            if (! $response->successful()) {
                $body = $response->json();
                $err = $body['errors'][0]['message'] ?? $body['message'] ?? $response->body();

                return ['success' => false, 'error' => 'Could not add recipient: '.$err];
            }

            $contact = $response->json();
            $contactId = $contact['contactId'] ?? $contact['id'] ?? null;
            if (! $contactId) {
                return ['success' => false, 'error' => 'Wise did not return a contact ID. The identifier may not be a discoverable Wise account.'];
            }

            return [
                'success' => true,
                'contact_id' => (string) $contactId,
                'name' => $contact['name'] ?? $identifier,
            ];
        } catch (\Exception $e) {
            Log::error('Wise createContactByIdentifier error', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Fetch profiles from Wise API.
     *
     * @return array<array{id: int, type: string}>
     */
    protected function fetchProfiles(): array
    {
        $token = $this->getToken();
        if (! $token) {
            return [];
        }

        $urls = $this->getBaseUrls();

        // Try primary first (token matches this API), then legacy as fallback
        foreach (['primary', 'legacy'] as $key) {
            $baseUrl = $urls[$key];
            try {
                $response = Http::timeout(15)->withToken($token)->get("{$baseUrl}/v1/profiles");
                if (! $response->successful()) {
                    $response = Http::timeout(15)->withToken($token)->get("{$baseUrl}/v2/profiles");
                }
                if (! $response->successful()) {
                    $lastError = [
                        'base' => $baseUrl,
                        'status' => $response->status(),
                        'body' => substr($response->body(), 0, 800),
                    ];
                    Log::debug('Wise fetchProfiles attempt', $lastError);
                    self::$lastProfileFetchError = $lastError;

                    continue;
                }
                $data = $response->json();
                $profiles = isset($data['content']) && is_array($data['content'])
                    ? $data['content']
                    : (is_array($data) ? $data : []);
                if (! empty($profiles)) {
                    self::$lastProfileFetchError = null;

                    return $profiles;
                }
            } catch (\Exception $e) {
                $lastError = ['base' => $baseUrl, 'error' => $e->getMessage()];
                Log::debug('Wise fetchProfiles error', $lastError);
                self::$lastProfileFetchError = $lastError;
            }
        }

        return [];
    }

    /**
     * Get last profile fetch error for user-facing messages.
     */
    public static function getLastProfileFetchError(): ?string
    {
        $e = self::$lastProfileFetchError;
        if (! $e) {
            return null;
        }
        if (isset($e['error'])) {
            return $e['error'];
        }
        $status = $e['status'] ?? '';
        $body = $e['body'] ?? '';
        $decoded = is_string($body) ? json_decode($body, true) : $body;
        $msg = $decoded['errors'][0]['message'] ?? $decoded['error'] ?? $decoded['message'] ?? $body;

        return "HTTP {$status}: ".(is_string($msg) ? $msg : json_encode($msg));
    }

    /**
     * Send a transfer to a recipient.
     * wiseAccount can be: email address, or numeric Wise recipient account ID.
     *
     * @return array{success: bool, transfer_id?: int, error?: string}
     */
    public function sendTransfer(
        string $recipientEmailOrId,
        float $amount,
        string $currency,
        string $recipientName,
        string $reference = ''
    ): array {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'Wise integration is not configured.'];
        }

        $token = $this->getToken();
        if (! $token) {
            return ['success' => false, 'error' => 'Invalid Wise API token.'];
        }

        $profileId = $this->getProfileId();
        if (! $profileId) {
            return [
                'success' => false,
                'error' => 'Wise Profile ID is required. Go to Integrations → Wise → click "Fetch profiles" to load your profile, select it, and Save.',
            ];
        }

        $baseUrl = $this->getBaseUrl();

        try {
            // 1. Create quote (source and target same currency for domestic, or specify target)
            $quoteResponse = Http::withToken($token)
                ->post("{$baseUrl}/v3/profiles/{$profileId}/quotes", [
                    'sourceCurrency' => $currency,
                    'targetCurrency' => $currency,
                    'targetAmount' => $amount,
                    'payOut' => 'BANK_TRANSFER',
                ]);

            if (! $quoteResponse->successful()) {
                $body = $quoteResponse->json();
                $err = $body['errors'][0]['message'] ?? $quoteResponse->body();

                return ['success' => false, 'error' => 'Quote failed: '.$err];
            }

            $quote = $quoteResponse->json();
            $quoteId = $quote['id'] ?? null;
            if (! $quoteId) {
                return ['success' => false, 'error' => 'Invalid quote response.'];
            }

            // 2. Get or create recipient
            $targetAccountId = null;
            if (is_numeric($recipientEmailOrId)) {
                $targetAccountId = (int) $recipientEmailOrId;
                // Verify recipient exists and has a valid type for transfers
                $accResp = Http::withToken($token)->get("{$baseUrl}/v1/accounts/{$targetAccountId}");
                if (! $accResp->successful()) {
                    return ['success' => false, 'error' => 'Recipient account not found.'];
                }
                $acc = $accResp->json();
                $accType = strtolower($acc['type'] ?? '');
                if ($accType === 'email' && ($this->integration?->is_sandbox ?? false)) {
                    return [
                        'success' => false,
                        'error' => 'Wise Sandbox does not support transfers to email-type recipients. Assign a bank account recipient (IBAN, sort code, etc.) from the Wise Recipients page. Add one in wise.com → Recipients with bank details.',
                    ];
                }
            } elseif (filter_var($recipientEmailOrId, FILTER_VALIDATE_EMAIL)) {
                // Email recipients: sandbox does NOT support them. Use Wise recipient ID in production.
                if ($this->integration?->is_sandbox) {
                    return [
                        'success' => false,
                        'error' => 'Wise Sandbox does not support email recipients. Use the employee\'s Wise recipient ID (numeric) in wise_account instead. Get it from wise.com → Recipients → copy the ID.',
                    ];
                }

                // Check if email is valid for this quote (account-requirements)
                $reqResponse = Http::withToken($token)->get("{$baseUrl}/v1/quotes/{$quoteId}/account-requirements");
                $emailAllowed = false;
                if ($reqResponse->successful()) {
                    $requirements = $reqResponse->json();
                    foreach (is_array($requirements) ? $requirements : [] as $req) {
                        if (strtolower($req['type'] ?? '') === 'email') {
                            $emailAllowed = true;
                            break;
                        }
                    }
                }
                if (! $emailAllowed) {
                    return [
                        'success' => false,
                        'error' => 'Email recipients are not supported for '.$currency.' transfers. Add the employee\'s Wise recipient ID (numeric) in wise_account instead.',
                    ];
                }

                $recipientPayload = [
                    'profile' => $profileId,
                    'accountHolderName' => $recipientName,
                    'currency' => $currency,
                    'type' => 'email',
                    'details' => ['email' => $recipientEmailOrId],
                ];

                $recipientResponse = Http::withToken($token)
                    ->post("{$baseUrl}/v1/accounts", $recipientPayload);

                if (! $recipientResponse->successful()) {
                    $body = $recipientResponse->json();
                    $err = $body['errors'][0]['message'] ?? $recipientResponse->body();

                    return ['success' => false, 'error' => 'Recipient creation failed: '.$err];
                }

                $recipient = $recipientResponse->json();
                $targetAccountId = $recipient['id'] ?? null;
                if (! $targetAccountId) {
                    return ['success' => false, 'error' => 'Invalid recipient response.'];
                }
            } elseif (Str::isUuid($recipientEmailOrId)) {
                // UUID from the contacts endpoint — resolve to a numeric /v2/accounts ID via name-match
                $contacts = $this->fetchProfileContacts($baseUrl, $token, $profileId);
                $matchedContact = null;
                foreach ($contacts as $c) {
                    if (($c['id'] ?? '') === $recipientEmailOrId) {
                        $matchedContact = $c;
                        break;
                    }
                }
                if (! $matchedContact) {
                    foreach ($this->fetchProfiles() as $profile) {
                        $otherProfileId = (int) ($profile['id'] ?? 0);
                        if ($otherProfileId === 0 || $otherProfileId === $profileId) {
                            continue;
                        }

                        foreach ($this->fetchProfileContacts($baseUrl, $token, $otherProfileId) as $contact) {
                            if (($contact['id'] ?? '') === $recipientEmailOrId) {
                                $contactName = $contact['name'] ?? $contact['accountHolderName'] ?? 'another recipient';

                                return [
                                    'success' => false,
                                    'error' => "Linked Wise recipient belongs to {$contactName} on a different Wise profile. Please re-link this employee on the Wise Recipients page.",
                                ];
                            }
                        }
                    }

                    return ['success' => false, 'error' => 'Wise recipient not found. Please re-link the employee on the Wise Recipients page.'];
                }
                // Wise contacts (Wise-account, @tag, or email) are resolved by Wise itself: create a
                // quote that references the contactId and use the targetAccount Wise returns. This
                // replaces the prior name-match to /v2/accounts, which failed for contacts that have
                // no separate bank-account recipient.
                $contactQuoteResponse = Http::withToken($token)
                    ->post("{$baseUrl}/v3/profiles/{$profileId}/quotes", [
                        'sourceCurrency' => $currency,
                        'targetCurrency' => $currency,
                        'targetAmount' => $amount,
                        'payOut' => 'BANK_TRANSFER',
                        'contactId' => $recipientEmailOrId,
                    ]);

                if (! $contactQuoteResponse->successful()) {
                    $body = $contactQuoteResponse->json();
                    $err = $body['errors'][0]['message'] ?? $contactQuoteResponse->body();

                    return ['success' => false, 'error' => 'Quote failed: '.$err];
                }

                $contactQuote = $contactQuoteResponse->json();
                $quoteId = $contactQuote['id'] ?? $quoteId;
                $targetAccountId = $contactQuote['targetAccount'] ?? null;
                if (! $targetAccountId) {
                    return ['success' => false, 'error' => 'Could not resolve this Wise contact to a payable account. Please re-link the employee on the Wise Recipients page.'];
                }
            } else {
                return ['success' => false, 'error' => 'Recipient must be an email or Wise account ID.'];
            }

            // 3. Create transfer (v1) - customerTransactionId must be a valid UUID
            $customerTransactionId = (string) Str::uuid();
            $transferPayload = [
                'targetAccount' => $targetAccountId,
                'quoteUuid' => $quoteId,
                'customerTransactionId' => $customerTransactionId,
                'details' => [
                    'reference' => substr($reference ?: "Payroll {$recipientName}", 0, 255),
                ],
            ];

            $transferResponse = Http::withToken($token)
                ->post("{$baseUrl}/v1/transfers", $transferPayload);

            if (! $transferResponse->successful()) {
                $body = $transferResponse->json();
                $errCode = $body['errors'][0]['code'] ?? '';
                $err = $body['errors'][0]['message'] ?? $transferResponse->body();

                if (stripos($errCode, 'approvalRequired') !== false || stripos($err, 'missing approval') !== false) {
                    return [
                        'success' => false,
                        'error' => 'Wise is blocking this transfer because your Wise account requires manual approval for payments. Turn off transfer approvals in Wise (Business → Settings → Approvals / Manage permissions), or contact Wise support to disable the approval requirement on your profile, then try again.',
                    ];
                }

                $msg = 'Transfer creation failed: '.$err;
                if (stripos($err, 'Account type is not valid') !== false) {
                    $msg .= ' Use the employee\'s Wise recipient ID (numeric) in wise_account, not email.';
                }

                return ['success' => false, 'error' => $msg];
            }

            $transfer = $transferResponse->json();
            $transferId = $transfer['id'] ?? null;
            if (! $transferId) {
                return ['success' => false, 'error' => 'Invalid transfer response.'];
            }

            // 4. Fund transfer (pay from balance)
            $payResponse = Http::withToken($token)
                ->post("{$baseUrl}/v3/profiles/{$profileId}/transfers/{$transferId}/payments", [
                    'type' => 'BALANCE',
                ]);

            if (! $payResponse->successful()) {
                Log::warning('Wise: Transfer created but funding failed', [
                    'transfer_id' => $transferId,
                    'response' => $payResponse->body(),
                ]);

                return [
                    'success' => false,
                    'error' => 'Transfer created but funding failed. Check your Wise balance has sufficient funds in '.$currency.' and try again, or fund the transfer manually in Wise.',
                    'transfer_id' => $transferId,
                ];
            }

            $payBody = $payResponse->json();
            $payStatus = strtoupper($payBody['status'] ?? '');
            if ($payStatus === 'REJECTED') {
                $errCode = $payBody['errorCode'] ?? '';
                $msg = stripos($errCode, 'insufficient') !== false
                    ? 'Insufficient balance in your Wise account. Add funds in '.$currency.' and try again.'
                    : 'Funding was rejected by Wise. '.($payBody['errorCode'] ?? 'Please check your Wise account.');

                return [
                    'success' => false,
                    'error' => $msg,
                    'transfer_id' => $transferId,
                ];
            }

            return ['success' => true, 'transfer_id' => $transferId];
        } catch (\Exception $e) {
            Log::error('Wise API error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send payroll item to Wise.
     *
     * @return array{success: bool, transfer_id?: int, error?: string}
     */
    public function sendPayrollItem(PayrollReportItem $item, int $companyId): array
    {
        if (! $this->integration || $this->integration->company_id !== $companyId) {
            $this->integration = WiseIntegration::where('company_id', $companyId)
                ->where('is_active', true)
                ->first();
        }

        // Prefer current wise_account from users table; fallback to stored value on report item
        $user = $item->user;
        $wiseAccount = trim(($user?->wise_account ?? $item->wise_account) ?? '');
        if (empty($wiseAccount)) {
            return ['success' => false, 'error' => 'Employee has no Wise account configured.'];
        }

        $amount = (float) $item->net_pay;
        if ($amount <= 0) {
            return ['success' => false, 'error' => 'Net pay must be greater than zero.'];
        }

        $currency = $user?->wise_currency ?? $item->currency ?? 'USD';
        $name = $item->employee_name ?? 'Employee';
        $reference = "Payroll {$name}";

        $result = $this->sendTransfer($wiseAccount, $amount, $currency, $name, $reference);

        if ($result['success'] && isset($result['transfer_id'])) {
            $item->update([
                'wise_status' => 'sent',
                'wise_transfer_id' => (string) $result['transfer_id'],
                'wise_error' => null,
            ]);

            $report = $item->payrollReport;
            if ($report && $item->user_id) {
                EmployeePayHistory::updateOrCreate(
                    ['payroll_report_item_id' => $item->id],
                    [
                        'company_id' => $companyId,
                        'user_id' => $item->user_id,
                        'payroll_report_id' => $item->payroll_report_id,
                        'payroll_report_item_id' => $item->id,
                        'amount' => $amount,
                        'currency' => $currency,
                        'wise_transfer_id' => (string) $result['transfer_id'],
                        'period_start_date' => $report->period_start_date,
                        'period_end_date' => $report->period_end_date,
                        'paid_at' => now(),
                    ]
                );
            }
        } else {
            $item->update([
                'wise_status' => 'failed',
                'wise_error' => $result['error'] ?? 'Unknown error',
            ]);
        }

        return $result;
    }

    /**
     * Convert amount from one currency to another using Wise Quote API.
     * Uses the quote to get the actual target amount after Wise fees (what recipient receives).
     *
     * @return array{success: bool, target_amount?: float, rate?: float, fee_amount?: float, fee_currency?: string, error?: string}
     */
    public function convertAmount(string $sourceCurrency, string $targetCurrency, float $sourceAmount): array
    {
        $source = strtoupper($sourceCurrency);
        $target = strtoupper($targetCurrency);
        if ($source === $target) {
            return ['success' => true, 'target_amount' => $sourceAmount, 'rate' => 1.0, 'fee_amount' => 0, 'fee_currency' => $source];
        }

        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'Wise integration is not configured.'];
        }

        $token = $this->getToken();
        if (! $token) {
            return ['success' => false, 'error' => 'Invalid Wise API token.'];
        }

        $profileId = $this->getProfileId();
        if (! $profileId) {
            return ['success' => false, 'error' => 'Wise Profile ID is required.'];
        }

        $baseUrl = $this->getBaseUrl();

        try {
            $response = Http::timeout(15)->withToken($token)
                ->post("{$baseUrl}/v3/profiles/{$profileId}/quotes", [
                    'sourceCurrency' => $source,
                    'targetCurrency' => $target,
                    'sourceAmount' => $sourceAmount,
                    'payOut' => 'BANK_TRANSFER',
                    'preferredPayIn' => 'BALANCE',
                ]);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'error' => $response->json('errors.0.message') ?? $response->body(),
                ];
            }

            $quote = $response->json();
            $targetAmount = isset($quote['paymentOptions'][0]['targetAmount'])
                ? (float) $quote['paymentOptions'][0]['targetAmount']
                : (isset($quote['targetAmount']) ? (float) $quote['targetAmount'] : null);

            if ($targetAmount === null || $targetAmount < 0) {
                return ['success' => false, 'error' => 'Invalid quote response from Wise.'];
            }

            $rate = $sourceAmount > 0 ? round($targetAmount / $sourceAmount, 6) : 0;

            $feeAmount = 0;
            $feeCurrency = $source;
            $opt = $quote['paymentOptions'][0] ?? null;
            if ($opt && isset($opt['fee']['total'])) {
                $feeAmount = (float) $opt['fee']['total'];
                if (isset($opt['sourceCurrency'])) {
                    $feeCurrency = strtoupper($opt['sourceCurrency']);
                }
            }

            return [
                'success' => true,
                'target_amount' => round($targetAmount, 2),
                'rate' => $rate,
                'fee_amount' => round($feeAmount, 2),
                'fee_currency' => $feeCurrency,
            ];
        } catch (\Exception $e) {
            Log::error('Wise convertAmount error', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Fetch Wise account balances (multi-currency).
     *
     * @return array{success: bool, balances?: array<array{currency: string, amount: float}>, error?: string}
     */
    public function getBalances(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'Wise integration is not configured.'];
        }

        $token = $this->getToken();
        if (! $token) {
            return ['success' => false, 'error' => 'Invalid Wise API token.'];
        }

        $profileId = $this->getProfileId();
        if (! $profileId) {
            return ['success' => false, 'error' => 'Wise Profile ID is required.'];
        }

        $baseUrl = $this->getBaseUrl();

        try {
            $response = Http::timeout(15)->withToken($token)
                ->get("{$baseUrl}/v4/profiles/{$profileId}/balances", ['types' => 'STANDARD']);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'error' => $response->json('errors.0.message') ?? $response->body(),
                ];
            }

            $list = $response->json();
            if (! is_array($list)) {
                return ['success' => true, 'balances' => []];
            }

            $balances = [];
            foreach ($list as $b) {
                $currency = $b['currency'] ?? null;
                $amount = isset($b['amount']['value']) ? (float) $b['amount']['value'] : 0;
                if ($currency) {
                    $balances[] = ['currency' => $currency, 'amount' => $amount];
                }
            }

            return ['success' => true, 'balances' => $balances];
        } catch (\Exception $e) {
            Log::error('Wise getBalances error', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Fetch real transfer status from Wise API.
     *
     * @return array{success: bool, status?: string, error?: string}
     */
    public function getTransferStatus(int $transferId): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'Wise integration is not configured.'];
        }

        $token = $this->getToken();
        if (! $token) {
            return ['success' => false, 'error' => 'Invalid Wise API token.'];
        }

        $baseUrl = $this->getBaseUrl();

        try {
            $response = Http::withToken($token)
                ->get("{$baseUrl}/v1/transfers/{$transferId}");

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'error' => $response->json('errors.0.message') ?? $response->body(),
                ];
            }

            $transfer = $response->json();
            $status = $transfer['status'] ?? null;

            return [
                'success' => true,
                'status' => $status,
            ];
        } catch (\Exception $e) {
            Log::error('Wise getTransferStatus error', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Fetch recent incoming payments (balance credits) across all currency balances.
     * Used to manually reconcile invoices when a reusable/personal Wise link is used.
     *
     * @return array{success: bool, payments?: array<int, array<string, mixed>>, error?: string}
     */
    public function getIncomingPayments(int $daysBack = 90): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'Wise integration is not configured.'];
        }

        $token = $this->getToken();
        if (! $token) {
            return ['success' => false, 'error' => 'Invalid Wise API token.'];
        }

        $profileId = $this->getProfileId();
        if (! $profileId) {
            return ['success' => false, 'error' => 'Wise Profile ID is required.'];
        }

        $baseUrl = $this->getBaseUrl();
        $balances = $this->getBalanceAccounts();
        if (empty($balances)) {
            return ['success' => true, 'payments' => []];
        }

        $daysBack = max(1, min(400, $daysBack));
        $end = gmdate('Y-m-d\TH:i:s.000\Z');
        $start = gmdate('Y-m-d\TH:i:s.000\Z', time() - ($daysBack * 86400));

        $payments = [];
        foreach ($balances as $bal) {
            try {
                $response = Http::timeout(20)->withToken($token)
                    ->get("{$baseUrl}/v1/profiles/{$profileId}/balance-statements/{$bal['id']}/statement.json", [
                        'currency' => $bal['currency'],
                        'intervalStart' => $start,
                        'intervalEnd' => $end,
                        'type' => 'COMPACT',
                    ]);

                if (! $response->successful()) {
                    Log::debug('Wise statement fetch failed', ['balance' => $bal['id'], 'status' => $response->status()]);

                    continue;
                }

                $data = $response->json();
                foreach (($data['transactions'] ?? []) as $t) {
                    if (strtoupper($t['type'] ?? '') !== 'CREDIT') {
                        continue;
                    }
                    $amount = (float) ($t['amount']['value'] ?? 0);
                    if ($amount <= 0) {
                        continue;
                    }
                    $details = $t['details'] ?? [];
                    $ts = isset($t['date']) ? strtotime($t['date']) : 0;
                    $payments[] = [
                        'id' => (string) ($t['referenceNumber'] ?? ($t['id'] ?? uniqid('wise_'))),
                        'date' => $ts ? date('M d, Y', $ts) : '',
                        'timestamp' => $ts,
                        'amount' => round($amount, 2),
                        'currency' => $t['amount']['currency'] ?? $bal['currency'],
                        'sender' => $details['senderName'] ?? ($details['payerName'] ?? null),
                        'reference' => $details['paymentReference'] ?? ($details['reference'] ?? null),
                        'description' => $details['description'] ?? null,
                    ];
                }
            } catch (\Exception $e) {
                Log::debug('Wise getIncomingPayments error', ['error' => $e->getMessage()]);
            }
        }

        usort($payments, fn ($a, $b) => ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0));

        return ['success' => true, 'payments' => $payments];
    }

    /**
     * Get balance accounts (id + currency) for statement lookups.
     *
     * @return array<int, array{id: int|string, currency: string}>
     */
    private function getBalanceAccounts(): array
    {
        $token = $this->getToken();
        $profileId = $this->getProfileId();
        if (! $token || ! $profileId) {
            return [];
        }

        $baseUrl = $this->getBaseUrl();

        try {
            $response = Http::timeout(15)->withToken($token)
                ->get("{$baseUrl}/v4/profiles/{$profileId}/balances", ['types' => 'STANDARD']);

            if (! $response->successful()) {
                return [];
            }

            $out = [];
            foreach ((array) $response->json() as $b) {
                if (isset($b['id'], $b['currency'])) {
                    $out[] = ['id' => $b['id'], 'currency' => $b['currency']];
                }
            }

            return $out;
        } catch (\Exception $e) {
            Log::debug('Wise getBalanceAccounts error', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Verify a Wise webhook signature (X-Signature-SHA256, RSA-SHA256, Base64).
     * Tries the production key first, then sandbox, so it works in both environments.
     */
    public static function verifyWebhookSignature(string $rawBody, string $signatureBase64): bool
    {
        $signature = base64_decode($signatureBase64, true);
        if ($signature === false || $signature === '') {
            return false;
        }

        $keys = array_filter([
            config('services.wise.webhook_public_key'),
            config('services.wise.webhook_public_key_sandbox'),
        ]);

        foreach ($keys as $publicKey) {
            $verified = openssl_verify($rawBody, $signature, $publicKey, OPENSSL_ALGO_SHA256);
            if ($verified === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create a profile-level webhook subscription for incoming balance credits.
     *
     * @return array{success: bool, subscription_id?: string, error?: string}
     */
    public function createWebhookSubscription(string $callbackUrl, string $triggerOn = 'balances#update'): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'Wise integration is not configured.'];
        }

        $token = $this->getToken();
        if (! $token) {
            return ['success' => false, 'error' => 'Invalid Wise API token.'];
        }

        $profileId = $this->getProfileId();
        if (! $profileId) {
            return ['success' => false, 'error' => 'Wise Profile ID is required.'];
        }

        $baseUrl = $this->getBaseUrl();

        try {
            $response = Http::timeout(15)->withToken($token)
                ->post("{$baseUrl}/v3/profiles/{$profileId}/subscriptions", [
                    'name' => 'CRM incoming payments reconciliation',
                    'trigger_on' => $triggerOn,
                    'delivery' => [
                        'version' => '2.0.0',
                        'url' => $callbackUrl,
                    ],
                ]);

            if (! $response->successful()) {
                $body = $response->json();
                $err = $body['errors'][0]['message'] ?? $body['message'] ?? $response->body();

                return ['success' => false, 'error' => 'Could not create webhook: '.$err];
            }

            $data = $response->json();

            return ['success' => true, 'subscription_id' => (string) ($data['id'] ?? '')];
        } catch (\Exception $e) {
            Log::error('Wise createWebhookSubscription error', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * List profile-level webhook subscriptions.
     *
     * @return array{success: bool, subscriptions?: array<int, array<string, mixed>>, error?: string}
     */
    public function listWebhookSubscriptions(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'Wise integration is not configured.'];
        }

        $token = $this->getToken();
        if (! $token) {
            return ['success' => false, 'error' => 'Invalid Wise API token.'];
        }

        $profileId = $this->getProfileId();
        if (! $profileId) {
            return ['success' => false, 'error' => 'Wise Profile ID is required.'];
        }

        $baseUrl = $this->getBaseUrl();

        try {
            $response = Http::timeout(15)->withToken($token)
                ->get("{$baseUrl}/v3/profiles/{$profileId}/subscriptions");

            if (! $response->successful()) {
                return ['success' => false, 'error' => $response->json('errors.0.message') ?? $response->body()];
            }

            $data = $response->json();

            return ['success' => true, 'subscriptions' => is_array($data) ? $data : []];
        } catch (\Exception $e) {
            Log::error('Wise listWebhookSubscriptions error', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Delete a profile-level webhook subscription.
     *
     * @return array{success: bool, error?: string}
     */
    public function deleteWebhookSubscription(string $subscriptionId): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'Wise integration is not configured.'];
        }

        $token = $this->getToken();
        if (! $token) {
            return ['success' => false, 'error' => 'Invalid Wise API token.'];
        }

        $profileId = $this->getProfileId();
        if (! $profileId) {
            return ['success' => false, 'error' => 'Wise Profile ID is required.'];
        }

        $baseUrl = $this->getBaseUrl();

        try {
            $response = Http::timeout(15)->withToken($token)
                ->delete("{$baseUrl}/v3/profiles/{$profileId}/subscriptions/{$subscriptionId}");

            if (! $response->successful() && $response->status() !== 404) {
                return ['success' => false, 'error' => $response->json('errors.0.message') ?? $response->body()];
            }

            return ['success' => true];
        } catch (\Exception $e) {
            Log::error('Wise deleteWebhookSubscription error', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Map Wise API status to display label.
     */
    public static function formatWiseStatus(string $status): string
    {
        return match (strtolower($status)) {
            'incoming_payment_waiting' => 'Awaiting Payment',
            'processing' => 'Processing',
            'funds_converted' => 'Funds Converted',
            'outgoing_payment_sent' => 'Delivered',
            'cancelled' => 'Cancelled',
            'funds_refunded' => 'Refunded',
            'bounced_back' => 'Bounced Back',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }
}
