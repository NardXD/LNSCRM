<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecorderStartUploadRequest extends FormRequest
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
            'date' => ['required', 'date'],
            'duration' => ['nullable', 'integer', 'min:0'],
            'file_size' => ['nullable', 'integer', 'min:1'],
            'upload_checksum' => ['nullable', 'string', 'max:128'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
