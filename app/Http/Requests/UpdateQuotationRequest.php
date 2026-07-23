<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuotationRequest extends FormRequest
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
            'client_id' => ['sometimes', 'required', 'exists:clients,id'],
            'quotation_date' => ['sometimes', 'required', 'date'],
            'valid_until' => ['sometimes', 'required', 'date', 'after:quotation_date'],
            'status' => ['nullable', 'string', Rule::in(['draft', 'sent', 'accepted', 'rejected', 'expired'])],
            'items' => ['sometimes', 'required', 'array', 'min:1'],
            'items.*.item_name' => ['required_with:items', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.tax_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_type' => ['nullable', 'string', Rule::in(['amount', 'percent'])],
            'internal_notes' => ['nullable', 'string'],
            'terms_conditions' => ['nullable', 'string'],
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
            'client_id.required' => 'Please select a client.',
            'client_id.exists' => 'Selected client does not exist.',
            'quotation_date.required' => 'Quotation date is required.',
            'valid_until.required' => 'Valid until date is required.',
            'valid_until.after' => 'Valid until date must be after quotation date.',
            'items.required' => 'At least one item is required.',
            'items.min' => 'At least one item is required.',
            'items.*.item_name.required' => 'Item name is required.',
            'items.*.quantity.required' => 'Quantity is required.',
            'items.*.quantity.min' => 'Quantity must be greater than 0.',
            'items.*.unit_price.required' => 'Unit price is required.',
            'items.*.unit_price.min' => 'Unit price cannot be negative.',
        ];
    }
}
