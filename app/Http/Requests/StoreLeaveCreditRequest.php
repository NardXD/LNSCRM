<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveCreditRequest extends FormRequest
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
            'user_id' => 'required|exists:users,id',
            'leave_type' => 'required|in:vacation,sick,personal,emergency,other',
            'credits' => 'required|numeric|min:0|max:365',
            'year' => 'required|integer|min:2020|max:2100',
            'notes' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'Please select a user.',
            'user_id.exists' => 'Selected user does not exist.',
            'leave_type.required' => 'Please select a leave type.',
            'leave_type.in' => 'Invalid leave type selected.',
            'credits.required' => 'Leave credits are required.',
            'credits.numeric' => 'Leave credits must be a number.',
            'credits.min' => 'Leave credits cannot be negative.',
            'credits.max' => 'Leave credits cannot exceed 365 days.',
            'year.required' => 'Year is required.',
            'year.integer' => 'Year must be a valid number.',
            'year.min' => 'Year must be 2020 or later.',
            'year.max' => 'Year cannot exceed 2100.',
            'notes.max' => 'Notes cannot exceed 500 characters.',
        ];
    }
}
