<?php

namespace App\Http\Requests;

use App\Enums\DocumentSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDocumentRequest extends FormRequest
{
    /**
     * `edit()`/`update()` only ever act on a `source=created` document
     * (Boundaries & Constraints, spec-2-3) — an `imported` document has no
     * `content_html` of its own to rewrite, and is never routed through the
     * WYSIWYG editor. The bound `{document}` route-model is guaranteed to
     * exist by this point (a missing id already 404s during model binding,
     * before authorization runs).
     */
    public function authorize(): bool
    {
        return $this->route('document')->source === DocumentSource::Created;
    }

    /**
     * Mirrors CreateDocumentRequest::prepareForValidation() — Inertia forms
     * send a cleared/never-set value as an empty string over the wire, so
     * normalize it back to null before validation so `nullable` applies
     * correctly.
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
