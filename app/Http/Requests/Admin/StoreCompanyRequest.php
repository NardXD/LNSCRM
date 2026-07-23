<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'plan' => ['nullable', 'string', Rule::in(['free', 'gold', 'platinum'])],
            'status' => ['nullable', 'string', Rule::in(['trial', 'active', 'suspended'])],
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', 'exists:modules,slug'],
        ];
    }
}
