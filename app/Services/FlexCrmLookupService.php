<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\FacebookConversation;
use App\Models\Lead;
use App\Models\LeadIdentity;
use App\Models\PhoneCallLog;
use App\Models\PhoneContact;
use Illuminate\Support\Collection;

class FlexCrmLookupService
{
    /**
     * @var array<int, array{by_phone: array<string, array>, by_email: array<string, array>, by_name: array<string, array>}>
     */
    protected array $assignedLeadIndexCache = [];

    /**
     * @var array<int, array{by_phone: array<string, array>, by_email: array<string, array>, by_name: array<string, array>}>
     */
    protected array $leadIndexCache = [];

    public function __construct(
        protected TwilioCompanyService $twilioCompany
    ) {}

    /**
     * Resolve CRM context for a phone number (screen-pop / Flex plugin).
     *
     * @return array<string, mixed>
     */
    public function lookup(int $companyId, string $phone): array
    {
        $normalized = $this->twilioCompany->normalizePhone($phone);
        $digits = preg_replace('/\D+/', '', $normalized) ?? '';

        $client = $this->findClient($companyId, $normalized, $digits);
        $lead = $this->findLeadByPhone($companyId, $normalized, $digits);
        $phoneContact = $this->findPhoneContact($companyId, $normalized, $digits);
        $recentCalls = $this->recentCalls($companyId, $normalized, $digits);

        $crmUrl = null;
        if ($lead) {
            $crmUrl = url('/leads?lead='.$lead->id);
        } elseif ($client) {
            $crmUrl = url('/client-management?client='.$client->id);
        }

        return [
            'phone' => $normalized,
            'found' => (bool) ($lead || $client || $phoneContact),
            'lead' => $lead ? $this->serializeLead($lead) : null,
            'client' => $client ? [
                'id' => $client->id,
                'name' => $client->name,
                'contact_person' => $client->contact_person,
                'email' => $client->email,
                'phone' => $client->phone,
                'status' => $client->status,
                'industry' => $client->industry,
                'website' => $client->website,
                'crm_url' => url('/client-management?client='.$client->id),
            ] : null,
            'phone_contact' => $phoneContact ? [
                'id' => $phoneContact->id,
                'name' => $phoneContact->name,
                'phone' => $phoneContact->phone,
                'email' => $phoneContact->email,
                'notes' => $phoneContact->notes,
            ] : null,
            'recent_calls' => $recentCalls,
            'crm_url' => $crmUrl,
            'display_name' => $lead?->name
                ?? $client?->name
                ?? $phoneContact?->name
                ?? $normalized,
        ];
    }

    public function findLeadByPhone(int $companyId, string $normalized, string $digits): ?Lead
    {
        $identities = LeadIdentity::query()
            ->where('type', LeadIdentity::TYPE_PHONE)
            ->whereHas('lead', fn ($q) => $q->where('company_id', $companyId))
            ->with(['lead.identities', 'lead.assignedUser:id,name', 'lead.labels'])
            ->get();

        $match = $identities->first(fn (LeadIdentity $identity) => $this->phonesMatch((string) $identity->value, $normalized, $digits)
            || ($identity->normalized_value && $this->phonesMatch((string) $identity->normalized_value, $normalized, $digits)));

        return $match?->lead;
    }

    public function findLeadByEmail(int $companyId, string $email): ?Lead
    {
        $normalized = LeadIdentity::normalize(LeadIdentity::TYPE_EMAIL, $email);
        if ($normalized === '') {
            return null;
        }

        return LeadIdentity::query()
            ->where('type', LeadIdentity::TYPE_EMAIL)
            ->where('normalized_value', $normalized)
            ->whereHas('lead', fn ($q) => $q->where('company_id', $companyId))
            ->with(['lead.identities', 'lead.assignedUser:id,name', 'lead.labels'])
            ->first()
            ?->lead;
    }

    public function findLeadByName(int $companyId, string $name): ?Lead
    {
        $name = trim($name);
        if ($name === '' || FacebookConversation::isPlaceholderName($name)) {
            return null;
        }

        $lead = Lead::query()
            ->where('company_id', $companyId)
            ->where(function ($q) use ($name) {
                $q->where('name', 'like', '%'.$name.'%')
                    ->orWhere('company_name', 'like', '%'.$name.'%');
            })
            ->with(['identities', 'assignedUser:id,name', 'labels'])
            ->first();

        if ($lead) {
            return $lead;
        }

        $needle = strtolower($name);

        return LeadIdentity::query()
            ->whereIn('type', [LeadIdentity::TYPE_FACEBOOK, LeadIdentity::TYPE_INSTAGRAM])
            ->whereHas('lead', fn ($q) => $q->where('company_id', $companyId))
            ->with(['lead.identities', 'lead.assignedUser:id,name', 'lead.labels'])
            ->get()
            ->first(function (LeadIdentity $identity) use ($needle) {
                $cand = strtolower(trim((string) $identity->value));

                return $cand === $needle || str_contains($cand, $needle) || str_contains($needle, $cand);
            })
            ?->lead;
    }

    public function findLeadById(int $companyId, int $leadId): ?Lead
    {
        return Lead::query()
            ->where('company_id', $companyId)
            ->with(['identities', 'assignedUser:id,name', 'labels'])
            ->find($leadId);
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeLead(Lead $lead): array
    {
        $lead->loadMissing(['identities', 'assignedUser:id,name', 'labels']);

        return [
            'id' => $lead->id,
            'name' => $lead->name,
            'company_name' => $lead->company_name,
            'status' => $lead->status,
            'source' => $lead->source,
            'notes' => $lead->notes,
            'phones' => $lead->phoneValues(),
            'emails' => $lead->emailValues(),
            'facebook_name' => $lead->identities->firstWhere('type', LeadIdentity::TYPE_FACEBOOK)?->value,
            'instagram_username' => $lead->identities->firstWhere('type', LeadIdentity::TYPE_INSTAGRAM)?->value,
            'assigned_to' => $lead->assigned_to ? (int) $lead->assigned_to : null,
            'assigned_user' => $lead->assignedUser ? [
                'id' => $lead->assignedUser->id,
                'name' => $lead->assignedUser->name,
            ] : null,
            'labels' => $this->serializeLabels($lead),
            'crm_url' => url('/leads?lead='.$lead->id),
        ];
    }

    /**
     * Compact payload for channel UIs. Null when the lead is not assigned.
     *
     * @return array<string, mixed>|null
     */
    public function assignedLeadPayload(?Lead $lead): ?array
    {
        if (! $lead || ! $lead->assigned_to) {
            return null;
        }

        return $this->leadPayload($lead);
    }

    /**
     * Compact payload for channel UIs, including unassigned leads.
     *
     * @return array<string, mixed>|null
     */
    public function leadPayload(?Lead $lead): ?array
    {
        if (! $lead) {
            return null;
        }

        $serialized = $this->serializeLead($lead);

        return [
            'id' => $serialized['id'],
            'name' => $serialized['name'],
            'status' => $serialized['status'],
            'crm_url' => $serialized['crm_url'],
            'assigned_to' => $serialized['assigned_to'],
            'assigned_user' => $serialized['assigned_user'],
            'labels' => $serialized['labels'],
        ];
    }

    public function forgetLeadIndexes(int $companyId): void
    {
        unset($this->assignedLeadIndexCache[$companyId], $this->leadIndexCache[$companyId]);
    }

    /**
     * Index assigned leads for cheap matching on conversation lists.
     *
     * @return array{by_phone: array<string, array<string, mixed>>, by_email: array<string, array<string, mixed>>, by_name: array<string, array<string, mixed>>}
     */
    public function assignedLeadIndex(int $companyId): array
    {
        return $this->assignedLeadIndexCache[$companyId] ??= $this->buildLeadIndex($companyId, true);
    }

    /**
     * Index all leads (assigned or not) for inbox matching.
     *
     * @return array{by_phone: array<string, array<string, mixed>>, by_email: array<string, array<string, mixed>>, by_name: array<string, array<string, mixed>>}
     */
    public function leadIndex(int $companyId): array
    {
        return $this->leadIndexCache[$companyId] ??= $this->buildLeadIndex($companyId, false);
    }

    /**
     * @return array{by_phone: array<string, array<string, mixed>>, by_email: array<string, array<string, mixed>>, by_name: array<string, array<string, mixed>>}
     */
    protected function buildLeadIndex(int $companyId, bool $assignedOnly): array
    {
        $index = ['by_phone' => [], 'by_email' => [], 'by_name' => []];

        $query = Lead::query()
            ->where('company_id', $companyId)
            ->with(['identities', 'assignedUser:id,name', 'labels']);

        if ($assignedOnly) {
            $query->whereNotNull('assigned_to');
        }

        foreach ($query->get() as $lead) {
            $payload = $assignedOnly ? $this->assignedLeadPayload($lead) : $this->leadPayload($lead);
            if (! $payload) {
                continue;
            }

            foreach ($lead->identities as $identity) {
                if ($identity->type === LeadIdentity::TYPE_PHONE) {
                    foreach ($this->phoneIndexKeys((string) ($identity->normalized_value ?: $identity->value)) as $key) {
                        $index['by_phone'][$key] = $payload;
                    }
                } elseif ($identity->type === LeadIdentity::TYPE_EMAIL) {
                    $email = strtolower(trim((string) ($identity->normalized_value ?: $identity->value)));
                    if ($email !== '') {
                        $index['by_email'][$email] = $payload;
                    }
                } elseif (in_array($identity->type, [LeadIdentity::TYPE_FACEBOOK, LeadIdentity::TYPE_INSTAGRAM], true)) {
                    $label = $this->nameIndexKey((string) $identity->value);
                    if ($label !== null) {
                        $index['by_name'][$label] = $payload;
                    }
                }
            }

            $leadName = $this->nameIndexKey((string) $lead->name);
            if ($leadName !== null) {
                $index['by_name'][$leadName] = $payload;
            }
        }

        return $index;
    }

    /**
     * @param  array{by_phone: array<string, array>, by_email: array<string, array>, by_name: array<string, array>}  $index
     * @return array<string, mixed>|null
     */
    public function matchAssignedLead(
        array $index,
        ?string $phone = null,
        ?string $email = null,
        ?string $name = null,
        ?string $username = null
    ): ?array {
        if ($phone) {
            foreach ($this->phoneIndexKeys($phone) as $key) {
                if (isset($index['by_phone'][$key])) {
                    return $index['by_phone'][$key];
                }
            }
        }

        if ($email) {
            $normalized = strtolower(trim($email));
            if ($normalized !== '' && isset($index['by_email'][$normalized])) {
                return $index['by_email'][$normalized];
            }
        }

        foreach ([$username, $name] as $label) {
            $key = $this->nameIndexKey((string) $label);
            if ($key !== null && isset($index['by_name'][$key])) {
                return $index['by_name'][$key];
            }
        }

        return null;
    }

    protected function findClient(int $companyId, string $normalized, string $digits): ?Client
    {
        $clients = Client::query()
            ->where('company_id', $companyId)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get();

        $match = $clients->first(fn (Client $c) => $this->phonesMatch((string) $c->phone, $normalized, $digits));
        if ($match) {
            return $match;
        }

        $contact = ClientContact::query()
            ->whereHas('client', fn ($q) => $q->where('company_id', $companyId))
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->with('client')
            ->get()
            ->first(fn (ClientContact $c) => $this->phonesMatch((string) $c->phone, $normalized, $digits));

        return $contact?->client;
    }

    protected function findPhoneContact(int $companyId, string $normalized, string $digits): ?PhoneContact
    {
        return PhoneContact::query()
            ->where('company_id', $companyId)
            ->whereNotNull('phone')
            ->get()
            ->first(fn (PhoneContact $c) => $this->phonesMatch((string) $c->phone, $normalized, $digits));
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function recentCalls(int $companyId, string $normalized, string $digits): array
    {
        /** @var Collection<int, PhoneCallLog> $logs */
        $logs = PhoneCallLog::query()
            ->where('company_id', $companyId)
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->limit(40)
            ->get()
            ->filter(function (PhoneCallLog $log) use ($normalized, $digits) {
                foreach ([$log->from_number, $log->to_number] as $n) {
                    if ($n && $this->phonesMatch((string) $n, $normalized, $digits)) {
                        return true;
                    }
                }

                return false;
            })
            ->take(5)
            ->values();

        return $logs->map(fn (PhoneCallLog $log) => [
            'id' => $log->id,
            'call_sid' => $log->call_sid,
            'direction' => $log->direction,
            'from_number' => $log->from_number,
            'to_number' => $log->to_number,
            'status' => $log->status,
            'duration' => $log->duration,
            'started_at' => $log->started_at?->toIso8601String(),
        ])->all();
    }

    protected function phonesMatch(string $candidate, string $normalized, string $digits): bool
    {
        $candNorm = $this->twilioCompany->normalizePhone($candidate);
        if ($candNorm === $normalized) {
            return true;
        }

        $candDigits = preg_replace('/\D+/', '', $candNorm) ?? '';
        if ($digits === '' || $candDigits === '') {
            return false;
        }

        // Match on last 8–10 digits to tolerate country-code formatting differences.
        $len = min(10, strlen($digits), strlen($candDigits));
        if ($len < 7) {
            return $digits === $candDigits;
        }

        return substr($digits, -$len) === substr($candDigits, -$len);
    }

    /**
     * @return list<string>
     */
    protected function phoneIndexKeys(?string $phone): array
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return [];
        }

        $digits = preg_replace('/\D+/', '', $this->twilioCompany->normalizePhone($phone)) ?? '';
        if ($digits === '') {
            return [];
        }

        $keys = [$digits];
        if (strlen($digits) >= 10) {
            $keys[] = substr($digits, -10);
        }
        if (strlen($digits) >= 8) {
            $keys[] = substr($digits, -8);
        }

        return array_values(array_unique($keys));
    }

    protected function nameIndexKey(?string $name): ?string
    {
        $key = strtolower(ltrim(trim((string) $name), '@'));
        if ($key === '' || FacebookConversation::isPlaceholderName($key)) {
            return null;
        }

        return $key;
    }

    /**
     * @return list<array{id: int, name: string, color: string}>
     */
    protected function serializeLabels(Lead $lead): array
    {
        $lead->loadMissing('labels');

        return $lead->labels
            ->map(fn ($label) => [
                'id' => (int) $label->id,
                'name' => (string) $label->name,
                'color' => (string) ($label->color ?: '#4338ca'),
            ])
            ->values()
            ->all();
    }
}
