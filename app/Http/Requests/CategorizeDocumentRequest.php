<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategorizeDocumentRequest extends FormRequest
{
    /**
     * No authentication/authorization exists in v1 (NFR3) — always allowed.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Inertia forms serialize a null `category_id` (clearing back to
     * "Uncategorized") as an empty string over the wire — normalize it
     * back to null before validation so `nullable` applies correctly.
     */
    protected function prepareForValidation(): void
    {
        if ($this->category_id === '') {
            $this->merge(['category_id' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_id.integer' => 'Catégorie invalide.',
            'category_id.exists' => 'Catégorie invalide.',
        ];
    }
}
