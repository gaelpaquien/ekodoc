<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateDocumentRequest extends FormRequest
{
    /**
     * No authentication/authorization exists in v1 (NFR3) — always allowed.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * An optional category chosen in the editor's CategoryPicker is
     * serialized into the same request as the title/content — Inertia
     * forms send a null value as an empty string over the wire, so
     * normalize it back to null before validation so `nullable` applies
     * correctly (patron CategorizeDocumentRequest/ImportDocumentRequest).
     */
    protected function prepareForValidation(): void
    {
        if ($this->category_id === '') {
            $this->merge(['category_id' => null]);
        }

        if ($this->draft_token === '') {
            $this->merge(['draft_token' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content_html' => ['required', 'string'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            // The editor always generates one at open (Design Notes,
            // spec-2-2) — nullable here defensively, for a request that
            // never went through the editor at all.
            'draft_token' => ['nullable', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Merci de saisir un titre.',
            'title.max' => 'Le titre est trop long (255 caractères maximum).',
            'content_html.required' => 'Le contenu du document est requis.',
            'content_html.string' => 'Contenu de document invalide.',
            'category_id.integer' => 'Catégorie invalide.',
            'category_id.exists' => 'Catégorie invalide.',
            'draft_token.uuid' => 'Session d\'édition invalide, merci de recharger la page.',
        ];
    }
}
