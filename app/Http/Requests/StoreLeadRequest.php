<?php

namespace App\Http\Requests;

use App\Models\Lead;
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
        $this->merge([
            'phones' => $this->normalizeContactList($this->input('phones', [])),
            'emails' => $this->normalizeContactList($this->input('emails', [])),
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
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $phones = $this->input('phones', []);
            $emails = $this->input('emails', []);
            $facebook = trim((string) $this->input('facebook_name', ''));
            $instagram = trim((string) $this->input('instagram_username', ''));

            if ($phones === [] && $emails === [] && $facebook === '' && $instagram === '') {
                $validator->errors()->add('phones', 'Add at least one phone number, email, or social name so channels can match this lead.');
            }
        });
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
