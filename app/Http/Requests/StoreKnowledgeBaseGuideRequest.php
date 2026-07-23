<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKnowledgeBaseGuideRequest extends FormRequest
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
        $companyId = $this->user()?->company_id;

        return [
            'title' => 'required|string|max:255',
            'excerpt' => 'required|string',
            'category' => [
                'required',
                'string',
                Rule::exists('knowledge_base_categories', 'slug')
                    ->where('company_id', $companyId)
                    ->where('type', 'guide'),
            ],
            'duration' => 'nullable|string|max:20',
            'icon' => 'nullable|string|max:10',
        ];
    }
}
