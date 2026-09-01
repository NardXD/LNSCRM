<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadIdentity;
use Illuminate\Support\Str;

class LeadStoreganiseMapper
{
    /**
     * @param  array<string, mixed>  $site
     * @return array<string, mixed>
     */
    public function toUserPayload(Lead $lead, array $site, string $email, bool $includePassword = true): array
    {
        $lead->loadMissing('identities');

        $firstName = trim((string) ($lead->first_name ?: Str::before($lead->name, ' ')));
        $lastName = trim((string) ($lead->last_name ?: Str::after($lead->name, ' ')));
        if ($lastName === $firstName) {
            $lastName = '';
        }

        $payload = array_filter([
            'email' => $email,
            'firstName' => $firstName !== '' ? ucfirst($firstName) : ucfirst((string) $lead->name),
            'lastName' => $lastName !== '' ? ucfirst($lastName) : '.',
            'phone' => $this->phoneForApi($lead->phoneValues()[0] ?? ''),
            'address' => $lead->address ? trim((string) $lead->address) : null,
            'note' => $this->note($lead),
            'language' => 'en',
            'siteId' => (string) ($site['id'] ?? ''),
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);

        $companyName = $this->companyNameForApi($lead);
        if ($companyName !== null) {
            $payload['companyName'] = $companyName;
        }

        if ($includePassword) {
            $payload['password'] = Str::password(20);
        }

        $dob = $lead->date_of_birth?->format('Y-m-d');
        if ($dob) {
            $payload['dateOfBirth'] = $dob;
        }

        $customFields = $this->customFields($lead, $site, $dob);
        if ($customFields !== []) {
            $payload['customFields'] = $customFields;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $site
     * @return array<string, string>
     */
    public function customFields(Lead $lead, array $site, ?string $dob = null): array
    {
        $customerType = $this->customerTypeLabel($lead->customer_type);
        $storageReason = $lead->storage_reason === 'Other'
            ? ($lead->storage_reason_other ?: 'Other')
            : ($lead->storage_reason ?: null);
        $commercialType = $lead->business_industry === 'Other'
            ? ($lead->business_industry_other ?: 'Other')
            : ($lead->business_industry ?: null);

        $values = [
            'mrms' => $lead->title,
            'city' => $lead->city,
            'postal' => $lead->postal_code,
            'dob' => $dob,
            'hear_about' => $lead->source,
            'customer_type' => $customerType,
            'residential_type' => $customerType === 'Residential' ? $lead->residential_type : null,
            'residential_reason' => $customerType === 'Residential' ? $storageReason : null,
            'commercial_type' => $customerType === 'Commercial' ? $commercialType : null,
            'commercial_reason' => $customerType === 'Commercial' ? $storageReason : null,
            'site_code' => $site['code'] ?? null,
            'alt_title' => $lead->alt_title,
            'alt_first_name' => $lead->alt_first_name,
            'alt_last_name' => $lead->alt_last_name,
            'alt_address' => $lead->alt_address,
            'alt_city' => $lead->alt_city,
            'alt_postal' => $lead->alt_postal_code,
            'alt_phone' => $this->identityValue($lead, LeadIdentity::TYPE_PHONE, 'Alternate'),
            'alt_email' => $this->identityValue($lead, LeadIdentity::TYPE_EMAIL, 'Alternate'),
        ];

        $custom = [];
        foreach ($values as $formKey => $value) {
            $code = $this->customFieldCode($formKey);
            $value = is_string($value) ? trim($value) : $value;
            if ($code && $value !== null && $value !== '') {
                $custom[$code] = $value;
            }
        }

        return $custom;
    }

    protected function companyNameForApi(Lead $lead): ?string
    {
        $name = trim((string) ($lead->company_name ?? ''));
        if ($name === '') {
            return null;
        }

        return ucfirst($name);
    }

    protected function customerTypeLabel(?string $customerType): ?string
    {
        return match ($customerType) {
            Lead::CUSTOMER_TYPE_RESIDENTIAL => 'Residential',
            Lead::CUSTOMER_TYPE_BUSINESS => 'Commercial',
            default => null,
        };
    }

    protected function note(Lead $lead): ?string
    {
        $note = trim((string) ($lead->notes ?? ''));
        if ($note !== '') {
            return $note;
        }

        return null;
    }

    protected function identityValue(Lead $lead, string $type, string $label): ?string
    {
        $identity = $lead->identities
            ->where('type', $type)
            ->first(fn (LeadIdentity $identity) => strcasecmp((string) $identity->label, $label) === 0);

        $value = trim((string) ($identity?->value ?? ''));

        return $value !== '' ? $value : null;
    }

    protected function customFieldCode(string $formKey): ?string
    {
        $code = config('storeganise.user_custom_fields.'.$formKey);

        return is_string($code) && $code !== '' ? $code : null;
    }

    public function phoneForApi(string $phone): string
    {
        $phone = preg_replace('/\s+/', '', trim($phone)) ?? '';

        if ($phone === '') {
            return '';
        }

        if (str_starts_with($phone, '+')) {
            return $phone;
        }

        if (str_starts_with($phone, '63') && strlen($phone) >= 12) {
            return '+'.$phone;
        }

        return $phone;
    }
}
