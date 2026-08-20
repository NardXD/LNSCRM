<?php

namespace App\Http\Requests;

use App\Models\FacebookConversation;
use App\Models\Lead;
use App\Services\MessageContactExtractor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLeadRequest extends FormRequest
{
    protected bool $fullProfile = false;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->fullProfile = $this->exists('first_name')
            || $this->exists('title')
            || $this->exists('address')
            || $this->exists('city')
            || $this->exists('customer_type');
        $facebook = trim((string) $this->input('facebook_name', ''));
        $instagram = trim((string) $this->input('instagram_username', ''));
        $phones = $this->normalizeContactList($this->input('phones', []));
        $emails = $this->normalizeContactList($this->input('emails', []));
        $primaryPhones = $this->normalizeContactList($this->input('primary_phones', []));
        $primaryEmails = $this->normalizeContactList($this->input('primary_emails', []));
        $altPhones = $this->normalizeContactList($this->input('alt_phones', []));
        $altEmails = $this->normalizeContactList($this->input('alt_emails', []));

        $extracted = $this->extractedFacebookContacts();
        $phones = $this->mergeContactValues($phones, $extracted['phones']);
        $emails = $this->mergeContactValues($emails, $extracted['emails']);

        $firstName = trim((string) $this->input('first_name', ''));
        $lastName = trim((string) $this->input('last_name', ''));
        $composedName = trim($firstName.' '.$lastName);
        $name = trim((string) $this->input('name', ''));
        if ($composedName !== '') {
            $name = $composedName;
        }
        $extractedName = $extracted['names'][0] ?? null;
        if ($extractedName && ($name === '' || FacebookConversation::isPlaceholderName($name))) {
            $name = $extractedName;
        }
        if (FacebookConversation::isPlaceholderName($facebook) && $extractedName) {
            $facebook = $extractedName;
        }

        $phones = $this->mergeLabeledList($phones, $altPhones, 'Alternate');
        $phones = $this->mergeLabeledList($phones, $primaryPhones, 'Primary');
        $phones = $this->mergeLabeledContact($phones, $this->input('alt_phone'), 'Alternate');
        $phones = $this->mergeLabeledContact($phones, $this->input('phone'), 'Primary');
        $emails = $this->mergeLabeledList($emails, $altEmails, 'Alternate');
        $emails = $this->mergeLabeledList($emails, $primaryEmails, 'Primary');
        $emails = $this->mergeLabeledContact($emails, $this->input('alt_email'), 'Alternate');
        $emails = $this->mergeLabeledContact($emails, $this->input('email'), 'Primary');

        $this->merge([
            'name' => $name !== '' ? $name : $this->input('name'),
            'first_name' => $firstName !== '' ? $firstName : $this->input('first_name'),
            'last_name' => $lastName !== '' ? $lastName : $this->input('last_name'),
            'title' => $this->blankToNull($this->input('title')),
            'alt_title' => $this->blankToNull($this->input('alt_title')),
            'customer_type' => $this->blankToNull($this->input('customer_type')),
            'residential_type' => $this->blankToNull($this->input('residential_type')),
            'business_industry' => $this->blankToNull($this->input('business_industry')),
            'storage_reason' => $this->blankToNull($this->input('storage_reason')),
            'date_of_birth' => $this->blankToNull($this->input('date_of_birth')),
            'phones' => $phones,
            'emails' => $emails,
            'facebook_name' => FacebookConversation::isPlaceholderName($facebook) ? null : ($facebook !== '' ? $facebook : null),
            'instagram_username' => FacebookConversation::isPlaceholderName($instagram) ? null : ($instagram !== '' ? $instagram : null),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $full = $this->isFullProfile();

        return [
            'name' => ['required', 'string', 'max:255'],
            'title' => [$full ? 'required' : 'nullable', 'string', Rule::in(Lead::TITLES)],
            'first_name' => [$full ? 'required' : 'nullable', 'string', 'max:255'],
            'last_name' => [$full ? 'required' : 'nullable', 'string', 'max:255'],
            'address' => [$full ? 'required' : 'nullable', 'string', 'max:500'],
            'city' => [$full ? 'required' : 'nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'primary_phones' => ['nullable', 'array', 'max:20'],
            'primary_phones.*.value' => ['required', 'string', 'max:50'],
            'primary_emails' => ['nullable', 'array', 'max:20'],
            'primary_emails.*.value' => ['required', 'email', 'max:255'],
            'alt_title' => ['nullable', 'string', Rule::in(Lead::TITLES)],
            'alt_first_name' => ['nullable', 'string', 'max:255'],
            'alt_last_name' => ['nullable', 'string', 'max:255'],
            'alt_address' => ['nullable', 'string', 'max:500'],
            'alt_city' => ['nullable', 'string', 'max:255'],
            'alt_postal_code' => ['nullable', 'string', 'max:20'],
            'alt_phone' => ['nullable', 'string', 'max:50'],
            'alt_email' => ['nullable', 'email', 'max:255'],
            'alt_phones' => ['nullable', 'array', 'max:20'],
            'alt_phones.*.value' => ['required', 'string', 'max:50'],
            'alt_emails' => ['nullable', 'array', 'max:20'],
            'alt_emails.*.value' => ['required', 'email', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(Lead::STATUSES)],
            'source' => ['nullable', 'string', 'max:255'],
            'customer_type' => [$full ? 'required' : 'nullable', 'string', Rule::in(array_keys(Lead::CUSTOMER_TYPES))],
            'residential_type' => ['nullable', 'string', Rule::in(Lead::RESIDENTIAL_TYPES)],
            'business_industry' => ['nullable', 'string', Rule::in(Lead::BUSINESS_INDUSTRIES)],
            'business_industry_other' => ['nullable', 'string', 'max:255'],
            'storage_reason' => ['nullable', 'string', Rule::in(Lead::STORAGE_REASONS)],
            'storage_reason_other' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'phones' => ['nullable', 'array', 'max:20'],
            'phones.*.value' => ['required', 'string', 'max:50'],
            'phones.*.label' => ['nullable', 'string', 'max:50'],
            'emails' => ['nullable', 'array', 'max:20'],
            'emails.*.value' => ['required', 'email', 'max:255'],
            'emails.*.label' => ['nullable', 'string', 'max:50'],
            'facebook_name' => ['nullable', 'string', 'max:255'],
            'instagram_username' => ['nullable', 'string', 'max:255'],
            'facebook_conversation_id' => ['nullable', 'integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $name = trim((string) $this->input('name', ''));
            if (FacebookConversation::isPlaceholderName($name)) {
                $validator->errors()->add('name', 'Enter the person’s real name. Names like Messenger User cannot be saved as a lead.');
            }

            $phones = $this->input('phones', []);
            $emails = $this->input('emails', []);
            $facebook = trim((string) $this->input('facebook_name', ''));
            $instagram = trim((string) $this->input('instagram_username', ''));

            if ($this->isFullProfile()) {
                $primaryPhones = $this->normalizeContactList($this->input('primary_phones', []));
                if ($primaryPhones === [] && trim((string) $this->input('phone', '')) === '') {
                    $validator->errors()->add('primary_phones', 'Add at least one phone number.');
                }
                $primaryEmails = $this->normalizeContactList($this->input('primary_emails', []));
                if ($primaryEmails === [] && trim((string) $this->input('email', '')) === '') {
                    $validator->errors()->add('primary_emails', 'Add at least one email address.');
                }
                $customerType = (string) $this->input('customer_type', '');
                if ($customerType === Lead::CUSTOMER_TYPE_RESIDENTIAL && trim((string) $this->input('residential_type', '')) === '') {
                    $validator->errors()->add('residential_type', 'Select a residential type.');
                }
                if ($customerType === Lead::CUSTOMER_TYPE_BUSINESS) {
                    $industry = trim((string) $this->input('business_industry', ''));
                    if ($industry === '') {
                        $validator->errors()->add('business_industry', 'Select a business industry.');
                    }
                    if ($industry === 'Other' && trim((string) $this->input('business_industry_other', '')) === '') {
                        $validator->errors()->add('business_industry_other', 'Enter the business industry.');
                    }
                }
                if ((string) $this->input('storage_reason') === 'Other' && trim((string) $this->input('storage_reason_other', '')) === '') {
                    $validator->errors()->add('storage_reason_other', 'Enter the reason for storing.');
                }
            } elseif ($phones === [] && $emails === [] && $facebook === '' && $instagram === '') {
                $validator->errors()->add('phones', 'Add at least one phone number, email, or social name so channels can match this lead.');
            }

            $conversationId = (int) $this->input('facebook_conversation_id', 0);
            if ($conversationId > 0 && $phones === [] && $emails === []) {
                $conversation = FacebookConversation::query()->find($conversationId);
                if ($conversation && FacebookConversation::isPlaceholderName($conversation->name)) {
                    $validator->errors()->add('phones', 'Add a phone number or email. Unnamed Messenger contacts cannot be saved by Facebook name alone.');
                }
            }
        });
    }

    public function isFullProfile(): bool
    {
        return $this->fullProfile;
    }

    /**
     * @return array{phones: list<string>, emails: list<string>, names: list<string>}
     */
    protected function extractedFacebookContacts(): array
    {
        $conversationId = (int) $this->input('facebook_conversation_id', 0);
        $companyId = (int) ($this->user()?->company_id ?: 0);
        if ($conversationId < 1 || $companyId < 1) {
            return ['phones' => [], 'emails' => [], 'names' => []];
        }

        $conversation = FacebookConversation::query()
            ->where('company_id', $companyId)
            ->find($conversationId);

        if (! $conversation) {
            return ['phones' => [], 'emails' => [], 'names' => []];
        }

        return app(MessageContactExtractor::class)->fromFacebookConversation($conversation);
    }

    /**
     * @param  list<array{value: string, label: ?string}>  $existing
     * @return list<array{value: string, label: ?string}>
     */
    protected function mergeLabeledContact(array $existing, mixed $value, string $label): array
    {
        $value = trim((string) $value);
        if ($value === '') {
            return $existing;
        }

        foreach ($existing as $i => $item) {
            if (strcasecmp((string) $item['value'], $value) === 0) {
                $existing[$i]['label'] = $label;
                if ($i > 0) {
                    $row = $existing[$i];
                    unset($existing[$i]);
                    array_unshift($existing, $row);
                    $existing = array_values($existing);
                }

                return $existing;
            }
        }

        array_unshift($existing, ['value' => $value, 'label' => $label]);

        return $existing;
    }

    /**
     * @param  list<array{value: string, label: ?string}>  $existing
     * @param  list<array{value: string, label: ?string}>  $items
     * @return list<array{value: string, label: ?string}>
     */
    protected function mergeLabeledList(array $existing, array $items, string $label): array
    {
        foreach (array_reverse($items) as $item) {
            $existing = $this->mergeLabeledContact($existing, $item['value'] ?? '', $label);
        }

        return $existing;
    }

    protected function blankToNull(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  list<array{value: string, label: ?string}>  $existing
     * @param  list<string>  $values
     * @return list<array{value: string, label: ?string}>
     */
    protected function mergeContactValues(array $existing, array $values): array
    {
        $seen = [];
        foreach ($existing as $item) {
            $seen[strtolower($item['value'])] = true;
        }

        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value === '' || isset($seen[strtolower($value)])) {
                continue;
            }
            $seen[strtolower($value)] = true;
            $existing[] = ['value' => $value, 'label' => null];
        }

        return $existing;
    }

    /**
     * @return list<array{value: string, label: ?string}>
     */
    protected function normalizeContactList(mixed $list): array
    {
        if (! is_array($list)) {
            return [];
        }

        $out = [];
        foreach ($list as $item) {
            if (is_string($item)) {
                $value = trim($item);
                $label = null;
            } elseif (is_array($item)) {
                $value = trim((string) ($item['value'] ?? $item['phone'] ?? $item['email'] ?? ''));
                $label = isset($item['label']) ? trim((string) $item['label']) : null;
            } else {
                continue;
            }

            if ($value === '') {
                continue;
            }

            $out[] = [
                'value' => $value,
                'label' => $label !== '' ? $label : null,
            ];
        }

        return $out;
    }
}
