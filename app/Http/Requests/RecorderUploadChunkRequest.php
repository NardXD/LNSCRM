<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecorderUploadChunkRequest extends FormRequest
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
            'recording' => ['required', 'file', 'mimes:webm,mp4,ogg,mov', 'max:307200'],
        ];
    }
}
