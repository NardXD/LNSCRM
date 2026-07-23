<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in(['active', 'inactive', 'prospect', 'lead'])],
            'website' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'string'],
            'revenue' => ['nullable', 'numeric', 'min:0'],
            'contacts' => ['nullable', 'array'],
            'contacts.*.name' => ['required_with:contacts', 'string', 'max:255'],
            'contacts.*.role' => ['nullable', 'string', 'max:255'],
            'contacts.*.email' => ['nullable', 'email', 'max:255'],
            'contacts.*.phone' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom error messages for validation.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Company name is required.',
            'contact_person.required' => 'Contact person name is required.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please enter a valid email address.',
            'status.required' => 'Client status is required.',
            'status.in' => 'Invalid client status.',
            'website.url' => 'Please enter a valid website URL.',
            'revenue.numeric' => 'Revenue must be a number.',
            'revenue.min' => 'Revenue cannot be negative.',
            'contacts.*.name.required_with' => 'Contact name is required.',
            'contacts.*.email.email' => 'Please enter a valid email address for the contact.',
        ];
    }
}
