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
     * `draft_token`: Inertia forms send a cleared/never-set value as an
     * empty string over the wire, so it's normalized back to null before
     * validation so `nullable` applies correctly.
     *
     * `tag_ids`/`draft_attachments`: neither the editor's TagSelector nor
     * AttachmentsPanel ever sends `null` in practice, only an array
     * (possibly empty) — normalized defensively anyway (Code review,
     * spec-3-1) so an explicit `null` from any other caller is treated the
     * same as "none" rather than failing the `array` rule.
     */
    protected function prepareForValidation(): void
    {
        if ($this->draft_token === '') {
            $this->merge(['draft_token' => null]);
        }

        if ($this->tag_ids === null) {
            $this->merge(['tag_ids' => []]);
        }

        if ($this->draft_attachments === null) {
            $this->merge(['draft_attachments' => []]);
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
            // assigned afterwards through SyncDocumentTagsAction (Boundaries
            // & Constraints, spec-3-1) — never blocking, `tag_ids` may be
            // absent or empty.
            'tag_ids' => ['array'],
            'tag_ids.*' => ['distinct', 'integer', Rule::exists('tags', 'id')],
            // The editor always generates one at open (Design Notes,
            // spec-2-2) — nullable here defensively, for a request that
            // never went through the editor at all.
            'draft_token' => ['nullable', 'uuid'],
            // The draft attachments to keep (spec-3-3, Design Notes) — an
            // attachment is never referenced from `content_html`, so the
            // client must explicitly list which ones survived to
            // "Enregistrer". Never blocking, may be absent or empty.
            //
            // `filename` is used directly to build a storage path in
            // CreateDocumentAction::relocateDraftAttachments() — the regex
            // (mirroring `$editorImageFilenamePattern` in routes/web.php)
            // is defense-in-depth restricting it to exactly the
            // `{uuid}.{ext}` shape UploadDraftAttachmentAction ever
            // generates (code review finding), never trusting client input
            // for a stored path, same principle already applied to
            // `mime_type` elsewhere in this story.
            'draft_attachments' => ['array'],
            'draft_attachments.*.filename' => [
                'required',
                'string',
                'regex:/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\.[a-zA-Z0-9]+$/',
            ],
            'draft_attachments.*.original_filename' => ['required', 'string', 'max:255'],
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
            'draft_attachments.array' => 'Pièces jointes invalides.',
            'draft_attachments.*.filename.required' => 'Pièce jointe invalide.',
            'draft_attachments.*.filename.regex' => 'Pièce jointe invalide.',
            'draft_attachments.*.original_filename.required' => 'Pièce jointe invalide.',
            'draft_attachments.*.original_filename.max' => 'Nom de pièce jointe trop long (255 caractères maximum).',
        ];
    }
}
