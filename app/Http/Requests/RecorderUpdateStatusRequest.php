<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecorderUpdateStatusRequest extends FormRequest
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
            'upload_id' => ['required', 'string', 'max:100'],
            'sync_status' => ['required', 'string', 'in:queued,uploading,uploaded,failed'],
            'last_error' => ['nullable', 'string'],
            'retry_count' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
