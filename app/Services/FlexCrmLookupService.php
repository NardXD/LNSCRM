<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Lead;
use App\Models\LeadIdentity;
use App\Models\PhoneCallLog;
use App\Models\PhoneContact;
use Illuminate\Support\Collection;

class FlexCrmLookupService
{
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
            ->with('lead.identities')
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
            ->with('lead.identities')
            ->first()
            ?->lead;
    }

    public function findLeadByName(int $companyId, string $name): ?Lead
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $lead = Lead::query()
            ->where('company_id', $companyId)
            ->where(function ($q) use ($name) {
                $q->where('name', 'like', '%'.$name.'%')
                    ->orWhere('company_name', 'like', '%'.$name.'%');
            })
            ->with('identities')
            ->first();

        if ($lead) {
            return $lead;
        }

        $needle = strtolower($name);

        return LeadIdentity::query()
            ->whereIn('type', [LeadIdentity::TYPE_FACEBOOK, LeadIdentity::TYPE_INSTAGRAM])
            ->whereHas('lead', fn ($q) => $q->where('company_id', $companyId))
            ->with('lead.identities')
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
            ->with('identities')
            ->find($leadId);
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeLead(Lead $lead): array
    {
        $lead->loadMissing('identities');

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
            'crm_url' => url('/leads?lead='.$lead->id),
        ];
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
}
