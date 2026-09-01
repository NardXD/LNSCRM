<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadIdentity;
use Illuminate\Support\Str;

class LeadQuoteMapper
{
    /**
     * Map a lead to the legacy tenant shape used by the storage quote form and PDFs.
     *
     * @return array<string, mixed>
     */
    public function toLegacy(Lead $lead): array
    {
        $lead->loadMissing('identities');

        $firstName = trim((string) ($lead->first_name ?: Str::before($lead->name, ' ')));
        $lastName = trim((string) ($lead->last_name ?: Str::after($lead->name, ' ')));
        if ($lastName === $firstName) {
            $lastName = '';
        }

        $email = $this->primaryIdentity($lead, LeadIdentity::TYPE_EMAIL)
            ?? ($lead->emailValues()[0] ?? '');
        $phone = $this->primaryIdentity($lead, LeadIdentity::TYPE_PHONE)
            ?? ($lead->phoneValues()[0] ?? '');

        $address = trim((string) ($lead->address ?? ''));
        $city = trim((string) ($lead->city ?? ''));
        $fullAddress = $address;
        if ($city !== '') {
            $fullAddress = trim($address.($address !== '' ? ', ' : '').$city);
        }

        $altAddress = trim((string) ($lead->alt_address ?? ''));
        $altCity = trim((string) ($lead->alt_city ?? ''));
        $fullAltAddress = $altAddress;
        if ($altCity !== '') {
            $fullAltAddress = trim($altAddress.($altAddress !== '' ? ', ' : '').$altCity);
        }

        return [
            'TenantID' => (string) ($lead->storeganise_user_id ?: $lead->id),
            'sFName' => $firstName !== '' ? $firstName : (string) $lead->name,
            'sLName' => $lastName,
            'sEmail' => $email,
            'sPhone' => $phone,
            'sCompany' => trim((string) ($lead->company_name ?? '')),
            'sMrMrs' => trim((string) ($lead->title ?? '')),
            'sTaxID' => '',
            'sAddr1' => $address,
            'sAddr2' => '',
            'sCity' => $city,
            'sPostalCode' => trim((string) ($lead->postal_code ?? '')),
            'sMrMrsAlt' => trim((string) ($lead->alt_title ?? '')),
            'sFNameAlt' => trim((string) ($lead->alt_first_name ?? '')),
            'sLNameAlt' => trim((string) ($lead->alt_last_name ?? '')),
            'sPhoneAlt' => $this->labeledIdentity($lead, LeadIdentity::TYPE_PHONE, 'Alternate') ?? '',
            'sEmailAlt' => $this->labeledIdentity($lead, LeadIdentity::TYPE_EMAIL, 'Alternate') ?? '',
            'sAddr1Alt' => $altAddress,
            'sAddr2Alt' => '',
            'sCityAlt' => $altCity,
            'sPostalCodeAlt' => trim((string) ($lead->alt_postal_code ?? '')),
            'address' => $fullAddress,
            'address_alt' => $fullAltAddress,
        ];
    }

    protected function primaryIdentity(Lead $lead, string $type): ?string
    {
        $identity = $lead->identities
            ->where('type', $type)
            ->sortByDesc(fn (LeadIdentity $item) => $item->is_primary ? 1 : 0)
            ->first();

        $value = trim((string) ($identity?->value ?? ''));

        return $value !== '' ? $value : null;
    }

    protected function labeledIdentity(Lead $lead, string $type, string $label): ?string
    {
        $identity = $lead->identities
            ->where('type', $type)
            ->first(fn (LeadIdentity $item) => strcasecmp((string) $item->label, $label) === 0);

        $value = trim((string) ($identity?->value ?? ''));

        return $value !== '' ? $value : null;
    }
}
