<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
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
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'client' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', 'required', 'string', Rule::in(['active', 'on-hold', 'completed'])],
            'deadline' => ['sometimes', 'required', 'date'],
            'description' => ['nullable', 'string'],
            'team' => ['nullable', 'array'],
            'team.*' => ['exists:users,id'],
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
            'title.required' => 'Project title is required.',
            'client.required' => 'Client name is required.',
            'status.required' => 'Project status is required.',
            'status.in' => 'Invalid project status.',
            'deadline.required' => 'Project deadline is required.',
            'deadline.date' => 'Please enter a valid date.',
        ];
    }
}
