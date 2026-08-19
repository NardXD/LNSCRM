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
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $facebook = trim((string) $this->input('facebook_name', ''));
        $instagram = trim((string) $this->input('instagram_username', ''));
        $phones = $this->normalizeContactList($this->input('phones', []));
        $emails = $this->normalizeContactList($this->input('emails', []));

        $extracted = $this->extractedFacebookContacts();
        $phones = $this->mergeContactValues($phones, $extracted['phones']);
        $emails = $this->mergeContactValues($emails, $extracted['emails']);

        $name = trim((string) $this->input('name', ''));
        $extractedName = $extracted['names'][0] ?? null;
        if (FacebookConversation::isPlaceholderName($name) && $extractedName) {
            $name = $extractedName;
        }
        if (FacebookConversation::isPlaceholderName($facebook) && $extractedName) {
            $facebook = $extractedName;
        }

        $this->merge([
            'name' => $name !== '' ? $name : $this->input('name'),
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(Lead::STATUSES)],
            'source' => ['nullable', 'string', 'max:255'],
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

            if ($phones === [] && $emails === [] && $facebook === '' && $instagram === '') {
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
