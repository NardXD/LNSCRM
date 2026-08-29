<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKnowledgeBaseFaqRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'question' => 'required|string|max:500',
            'answer' => 'required|string',
            'category' => [
                'nullable',
                'string',
                Rule::exists('knowledge_base_categories', 'slug')
                    ->where('company_id', $companyId)
                    ->where('type', 'faq'),
            ],
            'visibility' => 'required|string|in:draft,published,archived',
        ];
    }
}
