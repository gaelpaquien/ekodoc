<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class UploadEditorImageRequest extends FormRequest
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
            // `File::image()` would allow SVG, whose validation message
            // below never mentions it — and an SVG can carry embedded
            // script content later served back with its real content-type
            // by serveDraftImage()/serveDocumentImage(). Restricted to the
            // exact raster types the message promises instead.
            'image' => [
                'required',
                File::types(['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'])->max(5 * 1024),
            ],
            // Required server-side, not only in the editor's own dialog
            // (UX-DR24, Boundaries & Constraints spec-2-2) — a request
            // forged straight against this endpoint must be rejected too.
            'alt' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'draft_token.required' => 'Session d\'édition invalide, merci de recharger la page.',
            'draft_token.uuid' => 'Session d\'édition invalide, merci de recharger la page.',
            'image.required' => 'Merci de sélectionner une image.',
            // `File::types()` (not `File::image()`, which would also allow
            // SVG) resolves to the `mimes` rule under the hood — this key
            // must track that, not `image.image`.
            'image.mimes' => 'Format non supporté. Formats acceptés : JPG, PNG, GIF, BMP, WEBP.',
            'image.max' => 'Image trop volumineuse (5 Mo maximum).',
            'alt.required' => 'Merci de renseigner un texte alternatif pour cette image.',
            'alt.max' => 'Le texte alternatif est trop long (255 caractères maximum).',
        ];
    }
}
