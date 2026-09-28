<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKnowledgeBaseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('knowledge_base_categories', 'id')
                    ->where('company_id', $this->user()?->company_id)
                    ->where('type', 'article'),
            ],
        ];
    }
}
