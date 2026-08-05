<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientContact;
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
        $phoneContact = $this->findPhoneContact($companyId, $normalized, $digits);
        $recentCalls = $this->recentCalls($companyId, $normalized, $digits);

        $crmUrl = null;
        if ($client) {
            $crmUrl = url('/client-management?client='.$client->id);
        }

        return [
            'phone' => $normalized,
            'found' => (bool) ($client || $phoneContact),
            'client' => $client ? [
                'id' => $client->id,
                'name' => $client->name,
                'contact_person' => $client->contact_person,
                'email' => $client->email,
                'phone' => $client->phone,
                'status' => $client->status,
                'industry' => $client->industry,
                'website' => $client->website,
                'crm_url' => $crmUrl,
            ] : null,
            'phone_contact' => $phoneContact ? [
                'id' => $phoneContact->id,
                'name' => $phoneContact->name,
                'phone' => $phoneContact->phone,
                'email' => $phoneContact->email,
                'notes' => $phoneContact->notes,
            ] : null,
            'recent_calls' => $recentCalls,
            'display_name' => $client?->name
                ?? $phoneContact?->name
                ?? $normalized,
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
