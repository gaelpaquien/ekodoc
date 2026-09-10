<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class UploadDraftAttachmentRequest extends FormRequest
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
            'draft_token' => ['required', 'uuid'],
            // Same accepted formats/size limit as ImportDocumentRequest/
            // AttachDocumentFileRequest (Code Map, spec-3-3).
            'file' => [
                'required',
                File::types(['pdf', 'docx', 'xlsx'])->max(20 * 1024),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $acceptedFormats = 'Formats acceptés : PDF, Word (.docx), Excel (.xlsx).';

        return [
            'draft_token.required' => 'Session d\'édition invalide, merci de recharger la page.',
            'draft_token.uuid' => 'Session d\'édition invalide, merci de recharger la page.',
            'file.required' => "Merci de sélectionner un fichier à joindre. {$acceptedFormats}",
            'file.mimes' => "Format non supporté. {$acceptedFormats}",
            'file.max' => "Fichier trop volumineux (20 Mo maximum). {$acceptedFormats}",
        ];
    }
}
