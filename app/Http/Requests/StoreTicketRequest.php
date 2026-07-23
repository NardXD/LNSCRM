<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
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
            'subject' => ['required', 'string', 'max:500'],
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'priority' => ['required', 'string', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string'],
            'image' => ['nullable', 'image', 'max:5120'],
        ];
    }
}
