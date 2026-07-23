<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeaveRequestRequest extends FormRequest
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
            'status' => 'sometimes|required|in:pending,approved,rejected,cancelled',
            'rejection_reason' => 'nullable|required_if:status,rejected|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Status is required.',
            'status.in' => 'Invalid status selected.',
            'rejection_reason.required_if' => 'Rejection reason is required when rejecting a leave request.',
            'rejection_reason.max' => 'Rejection reason cannot exceed 1000 characters.',
        ];
    }
}
