<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'exists:clients,id'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'effective_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:effective_date'],
            'signers' => ['required', 'array', 'min:1'],
            'signers.*.name' => ['required', 'string', 'max:255'],
            'signers.*.email' => ['required', 'email', 'max:255'],
            'signers.*.role' => ['required', 'in:client,company,witness'],
            'signers.*.signing_order' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
