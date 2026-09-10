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
     * `draft_token`: mirrors CreateDocumentRequest::prepareForValidation() —
     * Inertia forms send a cleared/never-set value as an empty string over
     * the wire, so normalize it back to null before validation so
     * `nullable` applies correctly.
     *
     * `tag_ids`: the editor's TagSelector never sends `null` in practice,
     * only an array (possibly empty) — normalized defensively anyway (Code
     * review, spec-3-1) so an explicit `null` from any other caller is
     * treated the same as "no tags" rather than failing the `array` rule.
     */
    protected function prepareForValidation(): void
    {
        if ($this->draft_token === '') {
            $this->merge(['draft_token' => null]);
        }

        if ($this->tag_ids === null) {
            $this->merge(['tag_ids' => []]);
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
            // An optional set of tags chosen in the editor's TagSelector,
            // re-synced on every save through SyncDocumentTagsAction
            // (Boundaries & Constraints, spec-3-1) — never blocking,
            // `tag_ids` may be absent or empty, including to clear every
            // previously assigned tag.
            'tag_ids' => ['array'],
            'tag_ids.*' => ['distinct', 'integer', Rule::exists('tags', 'id')],
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
            'tag_ids.array' => 'Tags invalides.',
            'tag_ids.*.integer' => 'Tag invalide.',
            'tag_ids.*.exists' => 'Tag invalide.',
            'draft_token.uuid' => 'Session d\'édition invalide, merci de recharger la page.',
        ];
    }
}
