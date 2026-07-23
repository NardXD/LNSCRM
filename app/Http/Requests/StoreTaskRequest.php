<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
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
            'project_id' => ['required', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', 'string', Rule::in(['low', 'medium', 'high'])],
            'deadline' => ['nullable', 'date'],
            'status' => ['sometimes', 'string', Rule::in(['todo', 'in-progress', 'review', 'done'])],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'progress' => ['sometimes', 'integer', 'min:0', 'max:100'],
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
            'project_id.required' => 'Project is required.',
            'project_id.exists' => 'Selected project does not exist.',
            'title.required' => 'Task title is required.',
            'priority.required' => 'Task priority is required.',
            'priority.in' => 'Invalid task priority.',
            'assigned_to.exists' => 'Selected user does not exist.',
            'progress.min' => 'Progress must be at least 0.',
            'progress.max' => 'Progress cannot exceed 100.',
        ];
    }
}
