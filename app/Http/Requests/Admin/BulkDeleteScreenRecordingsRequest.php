<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteScreenRecordingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_from.required' => 'Start date is required.',
            'date_to.required' => 'End date is required.',
            'date_to.after_or_equal' => 'End date must be on or after the start date.',
        ];
    }
}
