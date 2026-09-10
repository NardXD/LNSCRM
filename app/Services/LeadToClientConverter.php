<?php

namespace App\Services;

use App\Exceptions\LeadMissingEmailException;
use App\Models\Client;
use App\Models\Lead;

class LeadToClientConverter
{
    public function __construct(
        protected LeadQuoteMapper $leadMapper,
    ) {}

    /**
     * Resolve the Client behind a Lead, auto-creating one from the lead's
     * details if it isn't linked to one yet. Idempotent — safe to call on
     * every quote save.
     */
    public function convert(Lead $lead): Client
    {
        if ($lead->client_id) {
            $lead->loadMissing('client');

            if ($lead->client) {
                return $lead->client;
            }
        }

        $tenant = $this->leadMapper->toLegacy($lead);

        $email = trim((string) ($tenant['sEmail'] ?? ''));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new LeadMissingEmailException();
        }

        $fullName = trim($tenant['sFName'].' '.$tenant['sLName']);
        $name = trim((string) $tenant['sCompany']) !== '' ? $tenant['sCompany'] : ($fullName !== '' ? $fullName : $email);

        $client = Client::create([
            'company_id' => $lead->company_id,
            'name' => $name,
            'contact_person' => $fullName !== '' ? $fullName : $name,
            'email' => $email,
            'phone' => trim((string) ($tenant['sPhone'] ?? '')) ?: null,
            'industry' => $lead->business_industry,
            'status' => 'active',
            'address' => trim((string) ($tenant['address'] ?? '')) ?: null,
        ]);

        $lead->client_id = $client->id;
        $lead->save();

        return $client;
    }
}
