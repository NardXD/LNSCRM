<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['sometimes', 'required', 'exists:clients,id'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'content' => ['sometimes', 'required', 'string'],
            'effective_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:effective_date'],
            'signers' => ['sometimes', 'array', 'min:1'],
            'signers.*.name' => ['required_with:signers', 'string', 'max:255'],
            'signers.*.email' => ['required_with:signers', 'email', 'max:255'],
            'signers.*.role' => ['required_with:signers', 'in:client,company,witness'],
            'signers.*.signing_order' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
