<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class ImportDocumentRequest extends FormRequest
{
    /**
     * No authentication/authorization exists in v1 (NFR3) — always allowed.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `tag_ids`: the import page's TagSelector never sends `null` in
     * practice, only an array (possibly empty) — normalized defensively
     * anyway (Code review, spec-3-1) so an explicit `null` from any other
     * caller is treated the same as "no tags" rather than failing the
     * `array` rule.
     */
    protected function prepareForValidation(): void
    {
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
            'file' => [
                'required',
                File::types(['pdf', 'docx', 'xlsx'])->max(20 * 1024),
            ],
            // An optional set of tags chosen on the import page's
            // TagSelector, assigned afterwards through
            // SyncDocumentTagsAction (Boundaries & Constraints, spec-3-1) —
            // never blocking, `tag_ids` may be absent or empty.
            'tag_ids' => ['array'],
            'tag_ids.*' => ['distinct', 'integer', Rule::exists('tags', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $acceptedFormats = 'Formats acceptés : PDF, Word (.docx), Excel (.xlsx).';

        return [
            'file.required' => "Merci de sélectionner un fichier à importer. {$acceptedFormats}",
            'file.mimes' => "Format non supporté. {$acceptedFormats}",
            'file.max' => "Fichier trop volumineux (20 Mo maximum). {$acceptedFormats}",
            'tag_ids.array' => 'Tags invalides.',
            'tag_ids.*.integer' => 'Tag invalide.',
            'tag_ids.*.exists' => 'Tag invalide.',
        ];
    }
}
