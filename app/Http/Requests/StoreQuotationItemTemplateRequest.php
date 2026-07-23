<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuotationItemTemplateRequest extends FormRequest
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
            'item_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'default_quantity' => ['nullable', 'numeric', 'min:0.01'],
            'default_unit_price' => ['nullable', 'numeric', 'min:0'],
            'default_tax_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
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
            'item_name.required' => 'Item name is required.',
            'item_name.max' => 'Item name must not exceed 255 characters.',
            'default_quantity.numeric' => 'Default quantity must be a number.',
            'default_quantity.min' => 'Default quantity must be greater than 0.',
            'default_unit_price.numeric' => 'Default unit price must be a number.',
            'default_unit_price.min' => 'Default unit price cannot be negative.',
            'default_tax_percentage.numeric' => 'Default tax percentage must be a number.',
            'default_tax_percentage.min' => 'Default tax percentage cannot be negative.',
            'default_tax_percentage.max' => 'Default tax percentage cannot exceed 100%.',
        ];
    }
}
