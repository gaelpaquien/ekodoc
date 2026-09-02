<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCategoryRequest extends FormRequest
{
    /**
     * No authentication/authorization exists in v1 (NFR3) — always allowed.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Case-insensitive duplicate-name rejection is intentionally not a
     * validation rule here — it's a business rule owned by
     * CreateCategoryAction (the sole write point for Category), which
     * raises the same ValidationException shape so this still surfaces as
     * a normal `name` form error.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Merci de saisir un nom de catégorie.',
            'name.max' => 'Le nom de la catégorie ne doit pas dépasser 255 caractères.',
        ];
    }
}
